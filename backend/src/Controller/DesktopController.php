<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The Windows application of Conclave: the version that goes with this server, and its
 * installer. Public: the application asks before anyone signs in.
 *
 * The installer is the one dropped in the downloads folder (/data/downloads in Docker) if any,
 * otherwise the one of the matching GitHub release.
 */
#[Route('/api/desktop/windows', format: 'json')]
final class DesktopController extends AbstractController
{
    private const RELEASES = 'https://github.com/imperiumclansoftware/conclave/releases';

    public function __construct(
        #[Autowire(env: 'default::APP_VERSION')]
        private readonly ?string $version,
        #[Autowire('%kernel.project_dir%/var/downloads')]
        private readonly string $downloads,
    ) {
    }

    #[Route('', name: 'api_desktop_windows', methods: ['GET'])]
    public function release(): JsonResponse
    {
        $local = $this->localInstaller();

        return $this->json([
            // The application offers an update only for a version number ("dev" never)
            'version' => null !== $local ? $local['version'] : ($this->version ?: 'dev'),
            'downloadUrl' => $this->generateUrl('api_desktop_windows_download', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/download', name: 'api_desktop_windows_download', methods: ['GET'])]
    public function download(): Response
    {
        if (null !== $local = $this->localInstaller()) {
            return (new BinaryFileResponse($local['path']))
                ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($local['path']));
        }

        $version = $this->version;
        if (null === $version || '' === $version || 'dev' === $version) {
            return new RedirectResponse(self::RELEASES.'/latest');
        }

        return new RedirectResponse(\sprintf('%s/download/v%2$s/Conclave-Setup-%2$s.exe', self::RELEASES, $version));
    }

    /** @return array{path: string, version: string}|null the most recent installer of the folder */
    private function localInstaller(): ?array
    {
        $found = null;
        foreach (glob($this->downloads.'/Conclave-Setup-*.exe') ?: [] as $path) {
            if (preg_match('/Conclave-Setup-(\d+\.\d+\.\d+)\.exe$/', $path, $m) && (null === $found || version_compare($m[1], $found['version'], '>'))) {
                $found = ['path' => $path, 'version' => $m[1]];
            }
        }

        return $found;
    }
}
