<?php

namespace App\Portal;

use App\Entity\User;
use App\Enum\AvatarSource;
use App\Profile\Avatars;
use App\Profile\AvatarStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Avatars of the members exported to the clan's public portal, always served from Conclave's own host:
 * the portal only downloads from there (SSRF protection) and without credentials.
 *
 * - uploaded avatar: its existing public URL (random, unguessable name);
 * - Gravatar or Discord: a URL signed with APP_SECRET that changes with the picture, served from a
 *   local WebP copy refreshed every day (the portal asks every 15 minutes).
 */
class PortalAvatars
{
    public const SUB_PATTERN = '[A-Za-z0-9_-]{22}';
    public const SIGNATURE_PATTERN = '[a-f0-9]{32}';
    private const CACHE_TTL = 86400;
    private const MAX_DOWNLOAD = 2 * 1024 * 1024;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        #[Autowire('%kernel.project_dir%/var/avatars/%kernel.environment%/portal')]
        private readonly string $cacheDirectory,
    ) {
    }

    /** Absolute URL of the member's avatar on $baseUrl, null when they show their initial. */
    public function url(User $user, string $baseUrl): ?string
    {
        $source = Avatars::effectiveSource($user);
        if (AvatarSource::Upload === $source) {
            return $baseUrl.'/api/avatars/'.$user->getAvatarFile();
        }
        $remote = $this->remoteUrl($user);
        if (null === $remote) {
            return null;
        }

        return \sprintf('%s/api/portal/avatars/%s/%s.webp', $baseUrl, $user->getPublicSub(), $this->signature($user, $remote));
    }

    /**
     * Local WebP file of a member's remote avatar, if the signature matches their current picture.
     */
    public function file(User $user, string $signature): ?string
    {
        $remote = $this->remoteUrl($user);
        if (null === $remote || !hash_equals($this->signature($user, $remote), $signature)) {
            return null;
        }

        $path = $this->cacheDirectory.'/'.hash('sha256', $remote).'.webp';
        $fresh = is_file($path) && time() - (int) filemtime($path) < self::CACHE_TTL;
        if (!$fresh) {
            $webp = $this->download($remote);
            if (null !== $webp) {
                if (!is_dir($this->cacheDirectory)) {
                    mkdir($this->cacheDirectory, 0o775, true);
                }
                file_put_contents($path, $webp, \LOCK_EX);
            }
        }

        // Gravatar or Discord unreachable: the previous copy is better than nothing
        return is_file($path) ? $path : null;
    }

    /** Gravatar or Discord URL of the picture shown, at the portal's size */
    private function remoteUrl(User $user): ?string
    {
        $source = Avatars::effectiveSource($user);
        if (AvatarSource::Gravatar !== $source && AvatarSource::Discord !== $source) {
            return null;
        }
        $url = Avatars::url($user);

        return null === $url ? null : (string) preg_replace('/([?&])size=\d+/', '${1}size='.AvatarStorage::SIZE, $url);
    }

    private function signature(User $user, string $remoteUrl): string
    {
        return substr(hash_hmac('sha256', "portal-avatar\0".$user->getPublicSub()."\0".$remoteUrl, $this->secret), 0, 32);
    }

    private function download(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5, 'max_redirects' => 2]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }
            $length = (int) ($response->getHeaders()['content-length'][0] ?? 0);
            if ($length > self::MAX_DOWNLOAD) {
                return null;
            }
            $bytes = $response->getContent();
            if (\strlen($bytes) > self::MAX_DOWNLOAD) {
                return null;
            }

            return AvatarStorage::toWebp($bytes);
        } catch (ExceptionInterface|\InvalidArgumentException $e) {
            $this->logger->warning('Portal avatar unavailable: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
