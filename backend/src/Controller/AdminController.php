<?php

namespace App\Controller;

use App\Catalog\CatalogUnavailableException;
use App\Catalog\RawgCatalog;
use App\Discord\DiscordApi;
use App\Discord\DiscordApiException;
use App\Discord\DiscordConfig;
use App\Discord\Gateway\GatewayStatus;
use App\Dto\DiscordSettingsRequest;
use App\Dto\GeneralSettingsRequest;
use App\Dto\MailSettingsRequest;
use App\Dto\RawgSettingsRequest;
use App\Dto\TmdbSettingsRequest;
use App\Entity\User;
use App\Films\FilmCatalogUnavailableException;
use App\Films\TmdbClient;
use App\Mail\AppMailer;
use App\Mail\MailServer;
use App\Mail\MailServerTester;
use App\Repository\ClanRepository;
use App\Settings\AppSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration of the app (leaders): general, mail, Discord and game catalog settings.
 */
#[Route('/api/admin', format: 'json')]
#[IsGranted('ROLE_LEADER')]
final class AdminController extends AbstractController
{
    // Application flags (https://discord.com/developers/docs/resources/application#application-object-application-flags)
    private const FLAG_GATEWAY_PRESENCE = 1 << 12;
    private const FLAG_GATEWAY_PRESENCE_LIMITED = 1 << 13;

    public function __construct(
        private readonly AppSettings $settings,
        private readonly ClanRepository $clans,
        private readonly AppMailer $mailer,
    ) {
    }

    #[Route('/settings/general', name: 'api_admin_settings_general', methods: ['PUT'])]
    public function saveGeneral(#[MapRequestPayload] GeneralSettingsRequest $request, EntityManagerInterface $em, GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        $this->clans->findMain()?->rename(trim($request->clanName));
        $em->flush();
        $this->settings->set(AppSettings::APP_URL, $request->appUrl);

        return $this->settings($gateway, $discord);
    }

    #[Route('/settings/mail', name: 'api_admin_settings_mail', methods: ['PUT'])]
    public function saveMail(#[MapRequestPayload] MailSettingsRequest $request, GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        // Disabled is saved explicitly, so that it also overrides MAILER_DSN from .env
        $this->settings->set(AppSettings::MAILER_DSN, $this->mailServer($request)->toDsn() ?? 'null://null');
        $this->settings->set(AppSettings::MAILER_FROM, $request->from);
        $this->settings->set(AppSettings::MAILER_FROM_NAME, $request->fromName);

        return $this->settings($gateway, $discord);
    }

    /** Back to the server of the .env file. */
    #[Route('/settings/mail/server', name: 'api_admin_settings_mail_reset', methods: ['DELETE'])]
    public function resetMailServer(GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        $this->settings->set(AppSettings::MAILER_DSN, null);

        return $this->settings($gateway, $discord);
    }

    /** Sends a test e-mail with the values of the form, saved or not. */
    #[Route('/mail/test', name: 'api_admin_mail_test', methods: ['POST'])]
    public function testMail(#[MapRequestPayload] MailSettingsRequest $request, #[CurrentUser] User $me, MailServerTester $tester): JsonResponse
    {
        $recipient = $request->recipient ?? $me->getEmail();
        if (null === $recipient) {
            return $this->json(['ok' => false, 'checks' => [['label' => 'Destinataire', 'ok' => false, 'detail' => 'Indiquez une adresse, ou ajoutez la vôtre dans votre profil.']]]);
        }

        $checks = $tester->test($this->mailServer($request), $request->from, $request->fromName, trim($recipient), $me);

        return $this->json(['ok' => !\in_array(false, array_column($checks, 'ok'), true), 'checks' => $checks]);
    }

    #[Route('/settings', name: 'api_admin_settings', methods: ['GET'])]
    public function settings(GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        $clan = $this->clans->findMain();

        return $this->json([
            'general' => [
                'clanName' => $clan?->getName(),
                'appUrl' => $this->plain(AppSettings::APP_URL),
            ],
            'mail' => $this->mailSettings(),
            'discord' => [
                'enabled' => $discord->isEnabled(),
                'clientId' => $this->plain(AppSettings::DISCORD_CLIENT_ID),
                'clientSecret' => $this->secret(AppSettings::DISCORD_CLIENT_SECRET),
                'botToken' => $this->secret(AppSettings::DISCORD_BOT_TOKEN),
                'redirectUri' => $this->plain(AppSettings::DISCORD_REDIRECT_URI),
                'gateway' => $gateway->read(),
            ],
            'rawg' => ['apiKey' => $this->secret(AppSettings::RAWG_API_KEY)],
            'tmdb' => ['apiKey' => $this->secret(AppSettings::TMDB_API_KEY)],
        ]);
    }

    #[Route('/settings/discord', name: 'api_admin_settings_discord', methods: ['PUT'])]
    public function saveDiscord(#[MapRequestPayload] DiscordSettingsRequest $request, GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        $this->settings->set(AppSettings::DISCORD_CLIENT_ID, $request->clientId);
        $this->settings->set(AppSettings::DISCORD_REDIRECT_URI, $request->redirectUri);
        // Secrets are never sent back to the browser: null means "unchanged"
        if (null !== $request->clientSecret) {
            $this->settings->set(AppSettings::DISCORD_CLIENT_SECRET, $request->clientSecret);
        }
        if (null !== $request->botToken) {
            $this->settings->set(AppSettings::DISCORD_BOT_TOKEN, $request->botToken);
        }

        return $this->settings($gateway, $discord);
    }

    #[Route('/settings/rawg', name: 'api_admin_settings_rawg', methods: ['PUT'])]
    public function saveRawg(#[MapRequestPayload] RawgSettingsRequest $request, GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        if (null !== $request->apiKey) {
            $this->settings->set(AppSettings::RAWG_API_KEY, $request->apiKey);
        }

        return $this->settings($gateway, $discord);
    }

    #[Route('/settings/tmdb', name: 'api_admin_settings_tmdb', methods: ['PUT'])]
    public function saveTmdb(#[MapRequestPayload] TmdbSettingsRequest $request, GatewayStatus $gateway, DiscordConfig $discord): JsonResponse
    {
        if (null !== $request->apiKey) {
            $this->settings->set(AppSettings::TMDB_API_KEY, trim($request->apiKey));
        }

        return $this->settings($gateway, $discord);
    }

    #[Route('/tmdb/test', name: 'api_admin_tmdb_test', methods: ['POST'])]
    public function testTmdb(TmdbClient $tmdb): JsonResponse
    {
        if (!$tmdb->isConfigured()) {
            return $this->json(['ok' => false, 'checks' => [['label' => 'Clé TMDB', 'ok' => false, 'detail' => 'Aucune clé : les Cinéphiles ne peuvent pas chercher de films.']]]);
        }

        try {
            $valid = $tmdb->test();
            $check = ['label' => 'Clé TMDB', 'ok' => $valid, 'detail' => $valid ? 'Clé acceptée par TMDB.' : 'Clé refusée par TMDB.'];
        } catch (FilmCatalogUnavailableException) {
            $check = ['label' => 'Clé TMDB', 'ok' => false, 'detail' => 'TMDB est injoignable, réessayez plus tard.'];
        }

        return $this->json(['ok' => $check['ok'], 'checks' => [$check]]);
    }

    /**
     * Checks the Discord configuration against the Discord API.
     */
    #[Route('/discord/test', name: 'api_admin_discord_test', methods: ['POST'])]
    public function testDiscord(DiscordConfig $config, DiscordApi $api): JsonResponse
    {
        $checks = [];
        $checks[] = ['label' => 'Configuration complète', 'ok' => $config->isEnabled(), 'detail' => $config->isEnabled() ? null : 'Renseignez les quatre champs.'];

        if (null === $config->botToken()) {
            return $this->json(['ok' => false, 'checks' => $checks]);
        }

        try {
            $bot = $api->botUser();
            $checks[] = ['label' => 'Jeton du bot', 'ok' => true, 'detail' => 'Connecté en tant que '.$bot['username']];
            $application = $api->application();
            $sameApp = $application['id'] === $config->clientId();
            $checks[] = ['label' => 'Identifiant d\'application', 'ok' => $sameApp, 'detail' => $sameApp ? $application['name'] : 'Le jeton appartient à l\'application '.$application['id'].'.'];
            $presence = 0 !== (($application['flags'] ?? 0) & (self::FLAG_GATEWAY_PRESENCE | self::FLAG_GATEWAY_PRESENCE_LIMITED));
            $checks[] = ['label' => 'Presence Intent', 'ok' => $presence, 'detail' => $presence ? null : 'Activez « Presence Intent » dans Bot > Privileged Gateway Intents.'];
            $redirects = $application['redirect_uris'] ?? null;
            if (\is_array($redirects)) {
                $registered = \in_array($config->redirectUri(), $redirects, true);
                $checks[] = ['label' => 'URL de redirection', 'ok' => $registered, 'detail' => $registered ? null : 'Ajoutez-la dans OAuth2 > Redirects.'];
            }
        } catch (DiscordApiException $e) {
            $checks[] = ['label' => 'Jeton du bot', 'ok' => false, 'detail' => 401 === $e->getCode() ? 'Jeton refusé par Discord.' : 'Discord injoignable.'];
        }

        return $this->json(['ok' => !\in_array(false, array_column($checks, 'ok'), true), 'checks' => $checks]);
    }

    #[Route('/rawg/test', name: 'api_admin_rawg_test', methods: ['POST'])]
    public function testRawg(RawgCatalog $rawg): JsonResponse
    {
        if (!$rawg->isConfigured()) {
            return $this->json(['ok' => false, 'checks' => [['label' => 'Clé API', 'ok' => false, 'detail' => 'Aucune clé : la recherche utilise Steam (jeux PC).']]]);
        }

        try {
            $results = $rawg->search('zelda');
            $check = ['label' => 'Clé API', 'ok' => true, 'detail' => \count($results).' résultat(s) pour « zelda »'];
        } catch (CatalogUnavailableException) {
            $check = ['label' => 'Clé API', 'ok' => false, 'detail' => 'Clé refusée ou RAWG injoignable.'];
        }

        return $this->json(['ok' => $check['ok'], 'checks' => [$check]]);
    }

    /**
     * The password and the custom DSN are never sent back, only whether they are set.
     *
     * @return array{configured: bool, source: ?string, envServer: bool, server: array{mode: string, host: ?string, port: ?int, security: string, username: ?string, passwordSet: bool, verifyPeer: bool, customDsn: ?array{set: true, hint: string}}, from: array{value: ?string, source: ?string}, fromName: array{value: ?string, source: ?string}}
     */
    private function mailSettings(): array
    {
        $server = MailServer::fromDsn($this->settings->get(AppSettings::MAILER_DSN));

        return [
            // null://… is accepted by the mailer but sends nothing
            'configured' => $this->mailer->isConfigured() && MailServer::DISABLED !== $server->mode,
            'source' => $this->settings->source(AppSettings::MAILER_DSN),
            'envServer' => $this->settings->hasEnvironmentValue(AppSettings::MAILER_DSN),
            'server' => [
                'mode' => $server->mode,
                'host' => $server->host,
                'port' => $server->port,
                'security' => $server->security,
                'username' => $server->username,
                'passwordSet' => null !== $server->password,
                'verifyPeer' => $server->verifyPeer,
                'customDsn' => null !== $server->customDsn ? ['set' => true, 'hint' => strstr($server->customDsn, '://', true).'://…'] : null,
            ],
            'from' => $this->plain(AppSettings::MAILER_FROM),
            'fromName' => $this->plain(AppSettings::MAILER_FROM_NAME),
        ];
    }

    /** Server described by the form; empty secrets keep the saved ones. */
    private function mailServer(MailSettingsRequest $request): MailServer
    {
        $saved = MailServer::fromDsn($this->settings->get(AppSettings::MAILER_DSN));
        $username = '' !== trim((string) $request->username) ? trim($request->username) : null;
        $password = match ($request->password) {
            // Kept only for the same account on the same server
            null => MailServer::SMTP === $saved->mode && $saved->username === $username && $saved->host === strtolower(trim((string) $request->host)) ? $saved->password : null,
            '' => null,
            default => $request->password,
        };

        return new MailServer(
            $request->mode,
            host: null !== $request->host ? strtolower(trim($request->host)) : null,
            port: $request->port,
            security: $request->security,
            username: $username,
            password: null !== $username ? $password : null,
            verifyPeer: $request->verifyPeer,
            customDsn: null !== $request->dsn && '' !== trim($request->dsn) ? trim($request->dsn) : $saved->customDsn,
        );
    }

    /** @return array{value: ?string, source: ?string} */
    private function plain(string $name): array
    {
        return ['value' => $this->settings->get($name), 'source' => $this->settings->source($name)];
    }

    /**
     * Secrets are only described: set or not, last characters as a hint.
     *
     * @return array{set: bool, hint: ?string, source: ?string}
     */
    private function secret(string $name): array
    {
        $value = $this->settings->get($name);

        return [
            'set' => null !== $value,
            'hint' => null !== $value ? '…'.substr($value, -4) : null,
            'source' => $this->settings->source($name),
        ];
    }
}
