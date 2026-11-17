<?php

namespace App\Tests\Support;

/** Converts an RSA JWK (n, e) to a PEM public key, to verify RS256 signatures with openssl. */
final class Jwk
{
    public static function toPem(array $jwk): string
    {
        $n = self::decode($jwk['n']);
        $e = self::decode($jwk['e']);
        $rsaKey = self::seq(self::int($n).self::int($e));
        $algorithm = self::seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"); // rsaEncryption, NULL
        $der = self::seq($algorithm."\x03".self::len(\strlen($rsaKey) + 1)."\x00".$rsaKey);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function decode(string $b64url): string
    {
        return (string) base64_decode(strtr($b64url, '-_', '+/'), true);
    }

    private static function int(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ('' === $bytes || \ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::len(\strlen($bytes)).$bytes;
    }

    private static function seq(string $content): string
    {
        return "\x30".self::len(\strlen($content)).$content;
    }

    private static function len(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");

        return \chr(0x80 | \strlen($bytes)).$bytes;
    }
}
