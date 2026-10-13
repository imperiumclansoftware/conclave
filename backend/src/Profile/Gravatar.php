<?php

namespace App\Profile;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gravatar (https://gravatar.com): avatars identified by the SHA-256 of an e-mail address.
 */
final readonly class Gravatar
{
    public function __construct(
        #[Autowire(service: 'gravatar.client')]
        private HttpClientInterface $client,
    ) {
    }

    public static function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /** "d=404": no default picture, the client falls back to the initial if the Gravatar is removed */
    public static function url(string $hash, int $size = 256): string
    {
        return \sprintf('https://gravatar.com/avatar/%s?s=%d&d=404', $hash, $size);
    }

    /**
     * @return bool|null whether a Gravatar exists for this hash, null when Gravatar is unreachable
     */
    public function exists(string $hash): ?bool
    {
        try {
            $status = $this->client->request('HEAD', 'avatar/'.$hash, ['query' => ['d' => '404', 's' => 64]])->getStatusCode();
        } catch (ExceptionInterface) {
            return null;
        }

        return match (true) {
            200 === $status => true,
            404 === $status => false,
            default => null,
        };
    }
}
