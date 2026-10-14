<?php

namespace App\Settings;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Platform settings: values saved in the administration, falling back to environment
 * variables (.env.local) when not set there.
 */
class AppSettings implements ResetInterface
{
    public const DISCORD_CLIENT_ID = 'discord.client_id';
    public const DISCORD_CLIENT_SECRET = 'discord.client_secret';
    public const DISCORD_BOT_TOKEN = 'discord.bot_token';
    public const DISCORD_REDIRECT_URI = 'discord.redirect_uri';
    public const RAWG_API_KEY = 'rawg.api_key';
    public const TMDB_API_KEY = 'tmdb.api_key';
    public const APP_URL = 'app.url';
    public const MAILER_DSN = 'mailer.dsn';
    public const MAILER_FROM = 'mailer.from';
    public const MAILER_FROM_NAME = 'mailer.from_name';

    /** Setting => [environment variable, is a secret] */
    private const DEFINITIONS = [
        self::DISCORD_CLIENT_ID => ['DISCORD_CLIENT_ID', false],
        self::DISCORD_CLIENT_SECRET => ['DISCORD_CLIENT_SECRET', true],
        self::DISCORD_BOT_TOKEN => ['DISCORD_BOT_TOKEN', true],
        self::DISCORD_REDIRECT_URI => ['DISCORD_REDIRECT_URI', false],
        self::RAWG_API_KEY => ['RAWG_API_KEY', true],
        self::TMDB_API_KEY => ['TMDB_API_KEY', true],
        self::APP_URL => ['APP_PUBLIC_URL', false],
        self::MAILER_DSN => ['MAILER_DSN', true],
        self::MAILER_FROM => ['MAILER_FROM', false],
        self::MAILER_FROM_NAME => ['MAILER_FROM_NAME', false],
    ];

    /** @var array<string, ?string>|null decrypted values saved in the database */
    private ?array $stored = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecretBox $secretBox,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $name): ?string
    {
        $value = $this->stored()[$name] ?? null;
        if (null === $value || '' === $value) {
            $value = $_SERVER[self::DEFINITIONS[$name][0]] ?? $_ENV[self::DEFINITIONS[$name][0]] ?? null;
        }

        return '' === $value ? null : $value;
    }

    /** "admin" when saved in the administration, "env" when coming from the environment, null when unset. */
    public function source(string $name): ?string
    {
        if (null !== ($this->stored()[$name] ?? null)) {
            return 'admin';
        }

        return null !== $this->get($name) ? 'env' : null;
    }

    /** Whether the environment provides a value, used when the one saved in the administration is removed. */
    public function hasEnvironmentValue(string $name): bool
    {
        $value = $_SERVER[self::DEFINITIONS[$name][0]] ?? $_ENV[self::DEFINITIONS[$name][0]] ?? null;

        return null !== $value && '' !== $value;
    }

    public function isSecret(string $name): bool
    {
        return self::DEFINITIONS[$name][1];
    }

    /** Saves a value (null removes it, so that the environment variable applies again). */
    public function set(string $name, ?string $value): void
    {
        if (!isset(self::DEFINITIONS[$name])) {
            throw new \InvalidArgumentException(\sprintf('Unknown setting "%s".', $name));
        }

        $setting = $this->em->find(Setting::class, $name) ?? new Setting($name);
        $value = null === $value || '' === trim($value) ? null : trim($value);
        $setting->setValue(null !== $value && $this->isSecret($name) ? $this->secretBox->encrypt($value) : $value);
        $this->em->persist($setting);
        $this->em->flush();
        $this->stored = null;
    }

    /**
     * Changes whenever a setting is saved: long-running workers compare it to reload their configuration.
     */
    public function version(): string
    {
        $last = $this->em->createQuery('SELECT MAX(s.updatedAt) FROM '.Setting::class.' s')->getSingleScalarResult();

        return (string) $last;
    }

    public function reset(): void
    {
        $this->stored = null;
    }

    /** @return array<string, ?string> */
    private function stored(): array
    {
        if (null === $this->stored) {
            $this->stored = [];
            foreach ($this->em->getRepository(Setting::class)->findAll() as $setting) {
                $value = $setting->getValue();
                if (null !== $value && isset(self::DEFINITIONS[$setting->getName()]) && $this->isSecret($setting->getName())) {
                    $value = $this->secretBox->decrypt($value);
                    if (null === $value) {
                        $this->logger->error('Unable to decrypt the setting {name} (APP_SECRET changed?)', ['name' => $setting->getName()]);
                    }
                }
                $this->stored[$setting->getName()] = $value;
            }
        }

        return $this->stored;
    }
}
