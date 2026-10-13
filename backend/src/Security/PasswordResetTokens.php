<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stateless password reset tokens: signed with APP_SECRET and with the current password hash,
 * so that a token expires after an hour and stops working once the password has changed.
 */
final readonly class PasswordResetTokens
{
    public const VALID_MINUTES = 60;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private UserRepository $users,
    ) {
    }

    public function create(User $user, \DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        $payload = self::base64(json_encode(['u' => $user->getId(), 'e' => $now->getTimestamp() + self::VALID_MINUTES * 60]));

        return $payload.'.'.$this->sign($payload, $user);
    }

    public function verify(string $token): ?User
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (!\is_array($data) || !isset($data['u'], $data['e']) || $data['e'] < time()) {
            return null;
        }

        $user = $this->users->find($data['u']);
        if (null === $user || !$user->isActive() || !hash_equals($this->sign($payload, $user), $signature)) {
            return null;
        }

        return $user;
    }

    private function sign(string $payload, User $user): string
    {
        return self::base64(hash_hmac('sha256', $payload.'|'.$user->getPassword(), 'password-reset:'.$this->secret, true));
    }

    private static function base64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
