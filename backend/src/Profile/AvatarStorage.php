<?php

namespace App\Profile;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Stores avatars as 256×256 WebP files with random, unguessable names: they are served
 * publicly (<img> tags cannot send the API token).
 */
final readonly class AvatarStorage
{
    public const SIZE = 256;
    public const NAME_PATTERN = '[a-f0-9]{32}\.webp';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/avatars/%kernel.environment%')]
        private string $directory,
    ) {
    }

    /**
     * Crops the image to a centered square, resizes it and replaces the user's avatar.
     *
     * @throws \InvalidArgumentException when the image cannot be read
     */
    public function store(User $user, File $image): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($image->getPathname()));
        if (false === $source) {
            throw new \InvalidArgumentException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $avatar = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);
        imagecopyresampled($avatar, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $name = bin2hex(random_bytes(16)).'.webp';
        imagewebp($avatar, $this->path($name), 85);

        $this->remove($user);
        $user->setAvatarFile($name);
    }

    public function remove(User $user): void
    {
        if (null !== $user->getAvatarFile() && is_file($this->path($user->getAvatarFile()))) {
            unlink($this->path($user->getAvatarFile()));
        }
        $user->setAvatarFile(null);
    }

    public function path(string $name): string
    {
        if (!preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            throw new \InvalidArgumentException('Invalid avatar name.');
        }

        return $this->directory.'/'.$name;
    }
}
