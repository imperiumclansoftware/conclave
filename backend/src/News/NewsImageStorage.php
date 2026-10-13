<?php

namespace App\News;

use App\Entity\News;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Stores news images as WebP files (1600 px at most, proportions kept) with random, unguessable
 * names: they are served publicly, like the avatars (<img> tags cannot send the API token).
 */
final readonly class NewsImageStorage
{
    public const MAX_SIZE = 1600;
    public const NAME_PATTERN = '[a-f0-9]{32}\.webp';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/news-images/%kernel.environment%')]
        private string $directory,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the image cannot be read
     */
    public function store(News $news, File $image): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($image->getPathname()));
        if (false === $source) {
            throw new \InvalidArgumentException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SIZE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $name = bin2hex(random_bytes(16)).'.webp';
        imagewebp($resized, $this->path($name), 82);

        $this->remove($news);
        $news->setImageFile($name);
    }

    public function remove(News $news): void
    {
        if (null !== $news->getImageFile() && is_file($this->path($news->getImageFile()))) {
            unlink($this->path($news->getImageFile()));
        }
        $news->setImageFile(null);
    }

    public function path(string $name): string
    {
        if (!preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            throw new \InvalidArgumentException('Invalid image name.');
        }

        return $this->directory.'/'.$name;
    }
}
