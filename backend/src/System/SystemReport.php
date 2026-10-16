<?php

namespace App\System;

use App\Catalog\RawgCatalog;
use App\Catalog\RawgHealth;
use App\Discord\DiscordConfig;
use App\Discord\Gateway\GatewayStatus;
use App\Films\TmdbClient;
use App\Mail\AppMailer;
use App\Presence\AppStatus;
use App\Settings\AppSettings;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * State of the installation for the leaders' dashboard: services, versions, database, storage
 * and activity. Every check is quick and read-only; a failing one is reported, never thrown.
 */
final readonly class SystemReport
{
    /** Days of the activity chart */
    public const ACTIVITY_DAYS = 14;
    /** The real-time hub must answer within this delay (seconds) */
    private const HUB_TIMEOUT = 3;

    public function __construct(
        private Connection $db,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $migrations,
        private AppSettings $settings,
        private AppMailer $mailer,
        private DiscordConfig $discord,
        private GatewayStatus $gateway,
        private RawgCatalog $rawg,
        private RawgHealth $rawgHealth,
        private TmdbClient $tmdb,
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'MERCURE_URL')]
        private string $hubUrl,
        #[Autowire(env: 'APP_TIMEZONE')]
        private string $timezone,
        #[Autowire(env: 'default::APP_VERSION')]
        private ?string $version,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        #[Autowire('%kernel.project_dir%/var/avatars/%kernel.environment%')]
        private string $avatarsDir,
        #[Autowire('%kernel.project_dir%/var/news-images/%kernel.environment%')]
        private string $newsImagesDir,
        #[Autowire(env: 'resolve:JWT_SECRET_KEY')]
        private string $jwtSecretKey,
        #[Autowire(env: 'resolve:JWT_PUBLIC_KEY')]
        private string $jwtPublicKey,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'generatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'application' => $this->application(),
            'services' => $this->services(),
            'database' => $this->database(),
            'storage' => $this->storage(),
            'activity' => $this->activity(),
        ];
    }

    /** @return array<string, mixed> */
    private function application(): array
    {
        return [
            'version' => $this->version ?: 'dev',
            'environment' => $this->environment,
            'debug' => $this->debug,
            'php' => \PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'timezone' => $this->timezone,
            'publicUrl' => $this->settings->get(AppSettings::APP_URL),
        ];
    }

    /**
     * One line per service: ok, warning (works, partly), error (broken) or off (not configured).
     *
     * @return list<array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string}>
     */
    private function services(): array
    {
        $services = [$this->databaseCheck(), $this->hubCheck(), $this->keysCheck()];

        $url = $this->settings->get(AppSettings::APP_URL);
        $services[] = match (true) {
            null === $url => ['id' => 'url', 'label' => 'Adresse publique', 'state' => 'warning', 'detail' => 'Non renseignée (Général) : les e-mails n\'ont pas de liens vers Conclave.'],
            str_starts_with($url, 'https://') => ['id' => 'url', 'label' => 'Adresse publique', 'state' => 'ok', 'detail' => "$url, en HTTPS."],
            default => ['id' => 'url', 'label' => 'Adresse publique', 'state' => 'warning', 'detail' => "$url : sans HTTPS, les notifications du navigateur sont indisponibles."],
        };

        $services[] = $this->mailer->isConfigured()
            ? ['id' => 'mail', 'label' => 'E-mail', 'state' => 'ok', 'detail' => 'Serveur configuré (testez l\'envoi dans l\'onglet E-mail).']
            : ['id' => 'mail', 'label' => 'E-mail', 'state' => 'off', 'detail' => 'Non configuré : aucun e-mail ne part (inscriptions, mots de passe, événements).'];

        $services[] = $this->discordCheck();

        $services[] = $this->rawgCheck();
        $services[] = $this->tmdb->isConfigured()
            ? ['id' => 'tmdb', 'label' => 'Catalogue TMDB', 'state' => 'ok', 'detail' => 'Clé enregistrée : films et séries des Cinéphiles.']
            : ['id' => 'tmdb', 'label' => 'Catalogue TMDB', 'state' => 'off', 'detail' => 'Sans clé : pas de recherche de films ni de séries.'];

        return $services;
    }

    /** @return array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string} */
    private function databaseCheck(): array
    {
        try {
            $start = hrtime(true);
            $this->db->executeQuery('SELECT 1')->fetchOne();
            $ms = (hrtime(true) - $start) / 1e6;

            return ['id' => 'database', 'label' => 'Base de données', 'state' => 'ok', 'detail' => \sprintf('Répond en %s ms.', number_format($ms, 1, ',', ' '))];
        } catch (\Throwable $e) {
            return ['id' => 'database', 'label' => 'Base de données', 'state' => 'error', 'detail' => 'Injoignable : '.$e->getMessage()];
        }
    }

    /**
     * The hub answers a request without topic with "400 Missing topic": reachable.
     *
     * @return array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string}
     */
    private function hubCheck(): array
    {
        $check = ['id' => 'mercure', 'label' => 'Temps réel (Mercure)'];
        try {
            $start = hrtime(true);
            $status = $this->httpClient->request('GET', $this->hubUrl, ['timeout' => self::HUB_TIMEOUT, 'max_duration' => self::HUB_TIMEOUT])->getStatusCode();
            $ms = (hrtime(true) - $start) / 1e6;
            if ($status >= 500) {
                return $check + ['state' => 'error', 'detail' => "Le hub répond $status : messages et présences ne sont plus diffusés."];
            }

            return $check + ['state' => 'ok', 'detail' => \sprintf('Hub joignable (%s ms).', number_format($ms, 0, ',', ' '))];
        } catch (ExceptionInterface $e) {
            return $check + ['state' => 'error', 'detail' => 'Hub injoignable : messages et présences ne sont plus diffusés en temps réel.'];
        }
    }

    /** @return array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string} */
    private function keysCheck(): array
    {
        $readable = is_readable($this->jwtSecretKey) && is_readable($this->jwtPublicKey);

        return ['id' => 'jwt', 'label' => 'Clés de connexion', 'state' => $readable ? 'ok' : 'error', 'detail' => $readable
            ? 'Présentes et lisibles.'
            : 'Introuvables : plus personne ne peut se connecter (bin/console lexik:jwt:generate-keypair).'];
    }

    /** @return array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string} */
    private function rawgCheck(): array
    {
        $check = ['id' => 'rawg', 'label' => 'Catalogue RAWG'];
        if (!$this->rawg->isConfigured()) {
            return $check + ['state' => 'off', 'detail' => 'Sans clé : la recherche de jeux passe par Steam (PC).'];
        }
        $failure = $this->rawgHealth->lastFailure();
        if (null !== $failure) {
            $at = (new \DateTimeImmutable('@'.$failure['at']))->setTimezone(new \DateTimeZone($this->timezone));

            return $check + ['state' => 'warning', 'detail' => \sprintf(
                'La dernière recherche a échoué (%s, le %s) : Steam a pris le relais. Vérifiez la clé dans Catalogues.',
                rtrim($failure['reason'], '.'),
                (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, $this->timezone))->format($at),
            )];
        }

        return $check + ['state' => 'ok', 'detail' => 'Clé enregistrée : jeux de toutes les plateformes.'];
    }

    /** @return array{id: string, label: string, state: 'ok'|'warning'|'error'|'off', detail: string} */
    private function discordCheck(): array
    {
        $check = ['id' => 'discord', 'label' => 'Discord'];
        if (!$this->discord->isEnabled()) {
            return $check + ['state' => 'off', 'detail' => 'Non configuré : pas de présences ni de visios Discord.'];
        }
        $gateway = $this->gateway->read();

        return $check + match ($gateway['state']) {
            'connected' => ['state' => 'ok', 'detail' => \sprintf('Bot %s connecté%s.', $gateway['botName'] ?? '', null !== $gateway['guilds'] ? \sprintf(' (%d serveur%s)', $gateway['guilds'], $gateway['guilds'] > 1 ? 's' : '') : '')],
            'connecting', 'waiting-config' => ['state' => 'warning', 'detail' => 'Le worker Discord se connecte…'],
            'error' => ['state' => 'error', 'detail' => 'Worker Discord en erreur'.(null !== $gateway['detail'] ? ' : '.$gateway['detail'] : '.')],
            default => ['state' => 'warning', 'detail' => 'Worker Discord arrêté : les présences Discord ne sont plus à jour (conteneur discord-worker).'],
        };
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            $status = $this->migrations->getMigrationStatusCalculator();
            $pending = \count($status->getNewMigrations());
            $executed = \count($this->migrations->getMetadataStorage()->getExecutedMigrations()->getItems());
        } catch (\Throwable) {
            $pending = $executed = null;
        }

        return [
            'server' => $this->scalar('SHOW server_version'),
            'sizeBytes' => (int) $this->scalar('SELECT pg_database_size(current_database())'),
            'migrations' => ['executed' => $executed, 'pending' => $pending],
        ];
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        // The volume of the uploaded files (/data in the Docker image)
        $volume = is_dir($this->avatarsDir) ? $this->avatarsDir : \dirname($this->avatarsDir, 2);
        $free = @disk_free_space($volume);
        $total = @disk_total_space($volume);

        return [
            'avatarsBytes' => self::directorySize($this->avatarsDir),
            'newsImagesBytes' => self::directorySize($this->newsImagesDir),
            'diskFreeBytes' => false !== $free ? (int) $free : null,
            'diskTotalBytes' => false !== $total ? (int) $total : null,
        ];
    }

    /** @return array<string, mixed> */
    private function activity(): array
    {
        $online = (new \DateTimeImmutable(\sprintf('-%d seconds', AppStatus::TIMEOUT_SECONDS)))->format('Y-m-d H:i:s');
        $day = (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s');

        return [
            'members' => (int) $this->scalar("SELECT count(*) FROM \"user\" WHERE status = 'active'"),
            'pendingSignups' => (int) $this->scalar("SELECT count(*) FROM \"user\" WHERE status = 'pending'"),
            'online' => (int) $this->scalar("SELECT count(*) FROM \"user\" WHERE status = 'active' AND last_seen_at >= ?", [$online]),
            'messages24h' => (int) $this->scalar('SELECT (SELECT count(*) FROM message WHERE created_at >= ?) + (SELECT count(*) FROM event_message WHERE created_at >= ?)', [$day, $day]),
            'upcomingEvents' => (int) $this->scalar('SELECT count(*) FROM event WHERE COALESCE(ends_at, starts_at) >= now()'),
            'news' => (int) $this->scalar('SELECT count(*) FROM news'),
            'games' => (int) $this->scalar('SELECT count(DISTINCT game_id) FROM user_game'),
            'films' => (int) $this->scalar('SELECT count(*) FROM film'),
            'messagesByDay' => $this->messagesByDay(),
        ];
    }

    /**
     * Messages (conversations and event chat rooms) per day, in the clan's time zone, the last
     * days included even without messages.
     *
     * @return list<array{day: string, count: int}>
     */
    private function messagesByDay(): array
    {
        $zone = new \DateTimeZone($this->timezone);
        $today = new \DateTimeImmutable('today', $zone);
        $first = $today->modify(\sprintf('-%d days', self::ACTIVITY_DAYS - 1));
        $since = $first->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $counts = [];
        try {
            // Dates are stored in UTC (the server's zone)
            $rows = $this->db->fetchAllKeyValue(
                "SELECT to_char((created_at AT TIME ZONE 'UTC') AT TIME ZONE :zone, 'YYYY-MM-DD') AS day, count(*)
                 FROM (SELECT created_at FROM message WHERE created_at >= :since
                       UNION ALL SELECT created_at FROM event_message WHERE created_at >= :since) m
                 GROUP BY day",
                ['zone' => $this->timezone, 'since' => $since],
            );
            $counts = array_map('intval', $rows);
        } catch (\Throwable) {
            // Reported as zero: the database check says what is wrong
        }

        $days = [];
        for ($d = $first; $d <= $today; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[] = ['day' => $key, 'count' => $counts[$key] ?? 0];
        }

        return $days;
    }

    /** @param list<mixed> $params */
    private function scalar(string $sql, array $params = []): mixed
    {
        try {
            return $this->db->fetchOne($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        $size = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
