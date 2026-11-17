<?php

namespace App\Portal;

use App\Repository\BranchRepository;
use App\Repository\UserRepository;
use App\Settings\AppSettings;

/**
 * Members exported to the clan's public portal (Imperium Clan, its ADR 0004): only those who opted in,
 * and only what they show publicly. Never the e-mail, Discord id, username, internal role, presence or
 * last connection.
 */
class PortalMembers
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly BranchRepository $branches,
        private readonly PortalAvatars $avatars,
        private readonly AppSettings $settings,
    ) {
    }

    /**
     * The complete list (the portal removes whoever is missing), without pagination.
     *
     * @param string $requestBaseUrl scheme and host of the request, when no public URL is configured
     *
     * @return list<array{sub: string, pseudo: string, title: null, bio: ?string, branches: list<string>, avatarUrl: ?string}>
     */
    public function export(string $requestBaseUrl): array
    {
        $baseUrl = rtrim($this->settings->get(AppSettings::APP_URL) ?: $requestBaseUrl, '/');
        $members = $this->users->findForPublicSite();
        $crests = $this->branches->crestsByMember($members);

        $items = [];
        foreach ($members as $member) {
            $items[] = [
                'sub' => $member->getPublicSub(),
                'pseudo' => $member->getDisplayName(),
                // Conclave has no public title yet; the internal role is not exported
                'title' => null,
                'bio' => $member->getBio(),
                'branches' => $crests[$member->getId()] ?? [],
                'avatarUrl' => $this->avatars->url($member, $baseUrl),
            ];
        }

        return $items;
    }
}
