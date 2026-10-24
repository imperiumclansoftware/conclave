import {
  Component,
  ElementRef,
  Injector,
  OnInit,
  afterNextRender,
  effect,
  inject,
  viewChild,
} from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { ChatStore } from '../../core/chat/chat.store';
import { RealtimeService } from '../../core/chat/realtime.service';
import { BranchesService } from '../../core/clan/branches.service';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { SignupsService } from '../../core/admin/signups.service';
import { ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { CallsService } from '../../core/discord/calls.service';
import { DiscordService } from '../../core/discord/discord.service';
import { PresenceService } from '../../core/presence/presence.service';
import { BrowserNotifications } from '../../core/notifications/browser-notifications.service';
import { DesktopBridge } from '../../core/desktop/desktop-bridge.service';
import { UserMenu } from '../../shared/user-menu';
import { MemberCard } from '../../shared/member-card';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { ChatPanel } from '../chat/chat-panel';
import { LinksPanel } from '../links/links-panel';
import { SidePanelService } from '../../core/side-panel/side-panel.service';
import { BranchLinksService } from '../../core/clan/branch-links.service';
import { CallInvitations } from '../../shared/call-invitations';
import { Emblem } from '../../shared/emblem';
import { FactionToggle } from '../../shared/faction-toggle';
import { Icon } from '../../shared/icon';

/** Layout of the authenticated area; owns the messaging state for the session. */
@Component({
  selector: 'app-shell',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    Emblem,
    FactionToggle,
    CallInvitations,
    UserMenu,
    Icon,
    ChatPanel,
    LinksPanel,
    MemberCard,
  ],
  providers: [RealtimeService, ChatStore, PresenceService, CallsService, SignupsService],
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell implements OnInit {
  protected readonly auth = inject(AuthService);
  protected readonly chat = inject(ChatStore);
  private readonly presence = inject(PresenceService);
  private readonly calls = inject(CallsService);
  private readonly discord = inject(DiscordService);
  protected readonly signups = inject(SignupsService);
  protected readonly panel = inject(ChatPanelService);
  protected readonly panels = inject(SidePanelService);
  private readonly links = inject(BranchLinksService);
  private readonly chatTab = viewChild<ElementRef<HTMLButtonElement>>('chatTab');
  private readonly linksTab = viewChild<ElementRef<HTMLButtonElement>>('linksTab');
  private readonly injector = inject(Injector);
  private readonly theme = inject(ThemeService);
  private readonly profiles = inject(ProfileService);

  constructor() {
    // A leader answered a request to join a branch: the branches and the rights change
    const branches = inject(BranchesService);
    // A game event on one of the member's games: browser notification, if wanted on this device
    const notifications = inject(BrowserNotifications);
    // Unread messages on the taskbar icon of the Windows application
    const desktop = inject(DesktopBridge);
    if (desktop.active) {
      effect(() => desktop.badge(this.chat.totalUnread()));
    }
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'event-invitation') {
          notifications.invitation(event.event);
        }
        // In the Windows application: a message while the window is not in front
        if (event.type === 'message' && event.message.senderId !== this.auth.user()?.id) {
          const sender = this.chat.member(event.message.senderId);
          notifications.message(
            sender?.displayName ?? 'Nouveau message',
            event.message.content,
            event.message.senderId,
          );
        }
        if (event.type === 'branches') {
          void this.auth.refreshUser();
          if (branches.loaded()) {
            void branches.load();
          }
        }
        // My branches, or their links, changed: the links sidebar follows
        if ((event.type === 'branches' || event.type === 'links') && this.links.loaded()) {
          void this.links.load();
        }
      });

    // Escape or ✕ in a sidebar removes the focused element: give the focus back to its tab
    let wasOpen = this.panels.active();
    effect(() => {
      const open = this.panels.active();
      const closed = wasOpen;
      if (closed && closed !== open) {
        afterNextRender(
          () => {
            if (!document.activeElement || document.activeElement === document.body) {
              (closed === 'chat' ? this.chatTab() : this.linksTab())?.nativeElement.focus();
            }
          },
          { injector: this.injector },
        );
      }
      wasOpen = open;
    });
  }

  ngOnInit(): void {
    this.applyFactionPreference();
    void this.chat.init();
    void this.presence.init();
    void this.calls.load();
    void this.discord.refresh();
    void this.signups.refresh();
  }

  /**
   * The theme follows the account on every device. An account without preference adopts the one
   * chosen on this device (before the setting existed) rather than discarding it.
   */
  private applyFactionPreference(): void {
    const account = this.auth.user()?.faction ?? null;
    const device = this.theme.devicePreference();
    if (account === null && device !== null) {
      this.profiles.setFaction(device).subscribe({ error: () => undefined });
    } else {
      this.theme.setPreference(account, { animate: false });
    }
  }

  protected logout(): void {
    this.panel.reset();
    this.links.reset();
    this.presence.leave();
    this.auth.logout();
  }
}
