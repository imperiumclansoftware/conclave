<?php

namespace App\Command;

use Amp\Websocket\Client\WebsocketConnection;
use App\Discord\DiscordConfig;
use App\Discord\Gateway\GatewayEventHandler;
use App\Discord\Gateway\GatewayStatus;
use App\Discord\VideoCallService;
use App\Settings\AppSettings;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

use function Amp\delay;
use function Amp\Websocket\Client\connect;

/**
 * Long-running worker listening to the Discord gateway: member presences, and who is in the
 * video call channels (to clean them up once empty). Run it with a process manager in production.
 *
 * It follows the settings saved in the administration: waits while Discord is not configured,
 * and reconnects when they change.
 */
#[AsCommand(name: 'app:discord:gateway', description: 'Listens to Discord presences and video call channels')]
final class DiscordGatewayCommand
{
    private const GATEWAY_URL = 'wss://gateway.discord.gg/?v=10&encoding=json';

    // GUILDS | GUILD_VOICE_STATES | GUILD_PRESENCES (privileged: enable "Presence Intent" in the portal)
    private const INTENTS = (1 << 0) | (1 << 7) | (1 << 8);

    private const OP_DISPATCH = 0;
    private const OP_HEARTBEAT = 1;
    private const OP_IDENTIFY = 2;
    private const OP_RECONNECT = 7;
    private const OP_INVALID_SESSION = 9;
    private const OP_HELLO = 10;
    private const OP_HEARTBEAT_ACK = 11;

    /** Close codes after which reconnecting is pointless (https://discord.com/developers/docs/topics/opcodes-and-status-codes) */
    private const FATAL_CLOSE_CODES = [
        4004 => 'Jeton du bot invalide (DISCORD_BOT_TOKEN).',
        4013 => 'Intents invalides.',
        4014 => 'Intent non autorisé : activez « Presence Intent » dans le portail développeur Discord (Bot > Privileged Gateway Intents).',
    ];

    private const SWEEP_INTERVAL = 60.0;
    private const SETTINGS_CHECK_INTERVAL = 15.0;

    /** SIGTERM or SIGINT received (docker stop, Ctrl+C): leave cleanly */
    private bool $stopping = false;
    /** Open gateway connection, closed to stop */
    private ?WebsocketConnection $connection = null;

    public function __construct(
        private readonly DiscordConfig $config,
        private readonly GatewayEventHandler $handler,
        private readonly VideoCallService $videoCalls,
        private readonly ManagerRegistry $doctrine,
        private readonly LockFactory $lockFactory,
        private readonly AppSettings $settings,
        private readonly GatewayStatus $status,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        // A single gateway connection must run, or every event would be applied twice
        $lock = $this->lockFactory->createLock('discord-gateway', ttl: null);
        if (!$lock->acquire()) {
            $io->error('Le worker Discord tourne déjà.');

            return Command::FAILURE;
        }

        $sweep = EventLoop::repeat(self::SWEEP_INTERVAL, function (): void {
            if ($this->config->isEnabled()) {
                $this->safely(fn () => $this->videoCalls->sweepUnused());
            }
        });
        // Stop cleanly: close the connection to Discord and release the lock
        $signals = [];
        foreach ([\SIGTERM, \SIGINT] as $signal) {
            $signals[] = EventLoop::onSignal($signal, function () use ($io): void {
                $io->writeln('<comment>Arrêt demandé, déconnexion de Discord…</comment>');
                $this->stopping = true;
                $this->connection?->close(1000, 'Shutting down');
            });
        }
        $backoff = 1;
        try {
            while (!$this->stopping) {
                $this->settings->reset();
                if (!$this->config->isEnabled()) {
                    $this->status->report('waiting-config', 'Renseignez la configuration Discord dans l\'administration.');
                    $this->pause(self::SETTINGS_CHECK_INTERVAL);
                    continue;
                }

                try {
                    $io->writeln('<info>Connexion à la gateway Discord…</info>');
                    $this->status->report('connecting');
                    $this->session($io);
                    $backoff = 1;
                } catch (FatalGatewayError $e) {
                    // Pointless to retry until the configuration is fixed in the administration
                    $io->error($e->getMessage());
                    $this->status->report('error', $e->getMessage());
                    $this->waitForSettingsChange();
                    continue;
                } catch (\Throwable $e) {
                    $this->logger->error('Discord gateway error: {error}', ['error' => $e->getMessage(), 'exception' => $e]);
                    $io->warning('Connexion perdue : '.$e->getMessage());
                    $this->status->report('error', 'Connexion perdue, nouvelle tentative…');
                }

                $this->pause($backoff);
                $backoff = min($backoff * 2, 60);
            }
            $this->status->report('stopped');
            $io->writeln('<info>Worker Discord arrêté.</info>');
        } finally {
            EventLoop::cancel($sweep);
            array_walk($signals, EventLoop::cancel(...));
            $lock->release();
        }

        return Command::SUCCESS;
    }

    /** Waits, but no longer than the stop: Docker kills the container 10 seconds after asking. */
    private function pause(float $seconds): void
    {
        for ($waited = 0.0; $waited < $seconds && !$this->stopping; $waited += 1.0) {
            delay(min(1.0, $seconds - $waited));
        }
    }

    private function waitForSettingsChange(): void
    {
        $version = $this->settings->version();
        while (!$this->stopping && $this->settings->version() === $version) {
            $this->pause(self::SETTINGS_CHECK_INTERVAL);
        }
    }

    private function session(SymfonyStyle $io): void
    {
        $connection = $this->connection = connect(self::GATEWAY_URL);
        $sequence = null;
        $acknowledged = true;
        $heartbeat = null;

        // Reconnect with the new configuration when it is changed in the administration
        $version = $this->settings->version();
        $settingsCheck = EventLoop::repeat(self::SETTINGS_CHECK_INTERVAL, function () use ($connection, $version): void {
            if ($this->settings->version() !== $version) {
                $this->settings->reset();
                $connection->close(1000, 'Settings changed');
            }
        });

        try {
            while ($message = $connection->receive()) {
                $payload = json_decode($message->buffer(), true, flags: \JSON_THROW_ON_ERROR);
                $sequence = $payload['s'] ?? $sequence;

                switch ($payload['op']) {
                    case self::OP_HELLO:
                        $interval = $payload['d']['heartbeat_interval'] / 1000;
                        $heartbeat = EventLoop::repeat($interval, function () use ($connection, &$sequence, &$acknowledged): void {
                            if (!$acknowledged) {
                                // Zombie connection: reconnect
                                $connection->close(4000, 'Heartbeat not acknowledged');

                                return;
                            }
                            $acknowledged = false;
                            $this->send($connection, self::OP_HEARTBEAT, $sequence);
                        });
                        $this->send($connection, self::OP_IDENTIFY, [
                            'token' => $this->config->botToken(),
                            'intents' => self::INTENTS,
                            'properties' => ['os' => \PHP_OS_FAMILY, 'browser' => 'conclave', 'device' => 'conclave'],
                        ]);
                        break;
                    case self::OP_HEARTBEAT_ACK:
                        $acknowledged = true;
                        $this->status->report('connected');
                        break;
                    case self::OP_HEARTBEAT:
                        $this->send($connection, self::OP_HEARTBEAT, $sequence);
                        break;
                    case self::OP_RECONNECT:
                    case self::OP_INVALID_SESSION:
                        $connection->close();

                        return;
                    case self::OP_DISPATCH:
                        if ('READY' === $payload['t']) {
                            $io->writeln(\sprintf('<info>Connecté en tant que %s, %d serveur(s).</info>', $payload['d']['user']['username'], \count($payload['d']['guilds'])));
                            $this->status->report('connected', null, $payload['d']['user']['username'], \count($payload['d']['guilds']));
                        }
                        $this->safely(fn () => $this->handler->handle($payload['t'], $payload['d']));
                        break;
                }
            }

            $code = $connection->getCloseInfo()->getCode();
            if (isset(self::FATAL_CLOSE_CODES[$code])) {
                throw new FatalGatewayError(self::FATAL_CLOSE_CODES[$code]);
            }
        } finally {
            EventLoop::cancel($settingsCheck);
            if (null !== $heartbeat) {
                EventLoop::cancel($heartbeat);
            }
            if (!$connection->isClosed()) {
                $connection->close();
            }
            $this->connection = null;
        }
    }

    private function send(WebsocketConnection $connection, int $op, mixed $data): void
    {
        $connection->sendText(json_encode(['op' => $op, 'd' => $data], \JSON_THROW_ON_ERROR));
    }

    /** Runs a unit of work with a clean Doctrine state, so that one failing event does not stop the worker. */
    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            $this->logger->error('Discord event failed: {error}', ['error' => $e->getMessage(), 'exception' => $e]);
            $this->doctrine->resetManager();
        } finally {
            $this->doctrine->getManager()->clear();
        }
    }
}
