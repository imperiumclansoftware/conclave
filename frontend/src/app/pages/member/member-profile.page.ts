import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { ConsentService } from '../../core/consent/consent.service';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { GamerNetwork, MemberProfile } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { ProfileService } from '../../core/profile/profile.service';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { PlatformBadges } from '../../shared/platform-badges';
import { PresenceBadge } from '../../shared/presence-badge';
import { Icon } from '../../shared/icon';

@Component({
  selector: 'app-member-profile-page',
  imports: [RouterLink, Avatar, BranchCrest, PresenceBadge, PlatformBadges, Icon],
  templateUrl: './member-profile.page.html',
  styleUrl: './member-profile.page.scss',
})
export class MemberProfilePage {
  protected readonly panel = inject(ChatPanelService);
  protected readonly consent = inject(ConsentService);
  private readonly profiles = inject(ProfileService);
  protected readonly me = inject(AuthService).user;

  /** Route parameter (bound by the router). */
  readonly id = input.required<string>();

  protected readonly profile = signal<MemberProfile | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly copied = signal<GamerNetwork | null>(null);
  protected readonly isMe = computed(() => this.profile()?.id === this.me()?.id);
  protected readonly tags = computed(() => {
    const profile = this.profile();
    return (profile?.networks ?? [])
      .filter((n) => profile?.gamerTags[n.value])
      .map((n) => ({ ...n, tag: profile!.gamerTags[n.value]! }));
  });

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => void this.load(id));
    });
  }

  protected async copy(network: GamerNetwork, tag: string): Promise<void> {
    try {
      await navigator.clipboard.writeText(tag);
      this.copied.set(network);
      setTimeout(() => this.copied.set(null), 2000);
    } catch {
      // Clipboard unavailable: the tag stays selectable
    }
  }

  private async load(id: number): Promise<void> {
    this.error.set(null);
    try {
      this.profile.set(await firstValueFrom(this.profiles.member(id)));
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Membre introuvable.'));
    }
  }
}
