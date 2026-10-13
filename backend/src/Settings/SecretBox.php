<?php

namespace App\Settings;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the secrets stored in the database (sodium secretbox, key derived from APP_SECRET).
 */
final readonly class SecretBox
{
    private string $key;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        string $appSecret,
    ) {
        $this->key = sodium_crypto_generichash('settings:'.$appSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    /** @return string|null null when the value cannot be decrypted (e.g. APP_SECRET changed) */
    public function decrypt(string $encrypted): ?string
    {
        $raw = base64_decode($encrypted, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $plain ? null : $plain;
    }
}
