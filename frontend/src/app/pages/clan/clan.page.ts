import { Component, OnInit, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { Branch, Member } from '../../core/api/models';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { AuthService } from '../../core/auth/auth.service';
import { ChatStore } from '../../core/chat/chat.store';
import { BranchesService } from '../../core/clan/branches.service';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { PresenceBadge } from '../../shared/presence-badge';
import { Icon } from '../../shared/icon';

/** The clan's members, all of them or branch by branch (one tab each). */
@Component({
  selector: 'app-clan-page',
  imports: [MemberCardDirective, RouterLink, Avatar, BranchCrest, PresenceBadge, Icon],
  templateUrl: './clan.page.html',
  styleUrl: './clan.page.scss',
})
export class ClanPage implements OnInit {
  protected readonly panel = inject(ChatPanelService);
  protected readonly chat = inject(ChatStore);
  protected readonly auth = inject(AuthService);
  protected readonly branches = inject(BranchesService);
  private readonly router = inject(Router);

  /** ?branche=id: the tab of this branch */
  readonly branche = input<string>();

  protected readonly error = signal<string | null>(null);
  /** Selected tab: a branch id, or null for every member */
  protected readonly tab = linkedSignal<number | null>(() => {
    const id = Number(this.branche());
    return Number.isInteger(id) && id > 0 ? id : null;
  });
  protected readonly branch = computed<Branch | null>(
    () => this.branches.branches().find((b) => b.id === this.tab()) ?? null,
  );
  /** Me first, then the others by name */
  private readonly everyone = computed<Member[]>(() => {
    const me = this.chat.me();
    return [...(me ? [me] : []), ...this.chat.contacts()];
  });
  protected readonly members = computed<Member[]>(() => {
    const branch = this.branch();
    return branch
      ? this.everyone().filter((m) => branch.memberIds.includes(m.id))
      : this.everyone();
  });
  protected readonly myId = computed(() => this.chat.me()?.id ?? null);
  protected readonly everyoneCount = computed(() => this.everyone().length);

  async ngOnInit(): Promise<void> {
    try {
      await this.branches.load();
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les branches.'));
    }
  }

  protected tabCount(branch: Branch | null): number {
    return branch ? branch.memberIds.length : this.everyone().length;
  }

  protected selectTab(id: number | null): void {
    this.tab.set(id);
    void this.router.navigate([], { queryParams: { branche: id }, replaceUrl: true });
  }

  protected onTabKey(event: KeyboardEvent): void {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (!step) {
      return;
    }
    const ids: (number | null)[] = [null, ...this.branches.branches().map((b) => b.id)];
    const index = Math.max(0, ids.indexOf(this.branch()?.id ?? null));
    const next = ids[(index + step + ids.length) % ids.length];
    this.selectTab(next);
    document.getElementById(`clan-tab-${next ?? 'all'}`)?.focus();
  }
}
