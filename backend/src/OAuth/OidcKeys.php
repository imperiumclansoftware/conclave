<?php

namespace App\OAuth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * RSA key signing the OpenID Connect ID tokens (RS256), separate from the members' session keys
 * (LexikJWT). Generated on first use in var/oidc (the /data/oidc volume in Docker); its public part
 * is published at /.well-known/jwks.json.
 */
class OidcKeys
{
    private ?\OpenSSLAsymmetricKey $key = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/oidc/%kernel.environment%')]
        private readonly string $directory,
    ) {
    }

    /**
     * Signed compact JWT (RS256).
     *
     * @param array<string, mixed> $claims
     */
    public function sign(array $claims): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->keyId()];
        $input = self::b64(json_encode($header, \JSON_THROW_ON_ERROR)).'.'.self::b64(json_encode($claims, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        if (!openssl_sign($input, $signature, $this->key(), \OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Unable to sign the ID token.');
        }

        return $input.'.'.self::b64($signature);
    }

    /** @return array{keys: list<array<string, string>>} */
    public function jwks(): array
    {
        $rsa = $this->details()['rsa'];

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->keyId(),
            'n' => self::b64($rsa['n']),
            'e' => self::b64($rsa['e']),
        ]]];
    }

    public function keyId(): string
    {
        return substr(hash('sha256', $this->details()['rsa']['n']), 0, 16);
    }

    /** @return array{rsa: array{n: string, e: string}} */
    private function details(): array
    {
        $details = openssl_pkey_get_details($this->key());
        if (false === $details || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new \RuntimeException('Invalid OIDC key.');
        }

        return $details;
    }

    private function key(): \OpenSSLAsymmetricKey
    {
        if (null !== $this->key) {
            return $this->key;
        }
        $path = $this->directory.'/private.pem';
        if (!is_file($path)) {
            $this->generate($path);
        }
        $key = openssl_pkey_get_private((string) file_get_contents($path));
        if (false === $key) {
            throw new \RuntimeException('Unreadable OIDC key.');
        }

        return $this->key = $key;
    }

    private function generate(string $path): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o700, true);
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        if (false === $key || !openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('Unable to generate the OIDC key.');
        }
        // Two processes starting together: the first file written wins, the other one is discarded
        $tmp = $path.'.'.bin2hex(random_bytes(4));
        file_put_contents($tmp, $pem);
        chmod($tmp, 0o600);
        if (!@link($tmp, $path) && !is_file($path)) {
            rename($tmp, $path);
        }
        @unlink($tmp);
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
