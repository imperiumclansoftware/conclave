import { Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { ClanEvent, News } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { ChatStore } from '../../core/chat/chat.store';
import { RealtimeService } from '../../core/chat/realtime.service';
import { EventsService } from '../../core/events/events.service';
import { NewsService } from '../../core/news/news.service';
import { PresenceService } from '../../core/presence/presence.service';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { PresenceBadge } from '../../shared/presence-badge';
import { byDay, timeRange } from '../events/event-format';
import { CAROUSEL_NEWS, HOME_DAYS, onlineMembers } from './home';
import { NewsCarousel } from './news-carousel';

/** Home of the clan: the latest news, the week's events and who is connected. */
@Component({
  selector: 'app-home-page',
  imports: [
    RouterLink,
    Avatar,
    BranchCrest,
    Icon,
    MemberCardDirective,
    PresenceBadge,
    NewsCarousel,
  ],
  templateUrl: './home.page.html',
  styleUrl: './home.page.scss',
})
export class HomePage {
  private readonly newsService = inject(NewsService);
  private readonly eventsService = inject(EventsService);
  private readonly presence = inject(PresenceService);
  private readonly chat = inject(ChatStore);
  protected readonly auth = inject(AuthService);
  protected readonly panel = inject(ChatPanelService);

  protected readonly days = HOME_DAYS;
  protected readonly clanName = computed(() => this.auth.user()?.clan?.name ?? '');

  protected readonly news = signal<News[]>([]);
  protected readonly newsLoaded = signal(false);
  protected readonly newsError = signal<string | null>(null);

  protected readonly events = signal<ClanEvent[]>([]);
  protected readonly eventsLoaded = signal(false);
  protected readonly eventsError = signal<string | null>(null);
  protected readonly eventDays = computed(() => byDay(this.events()));

  /** The other members connected, in the app or on Discord */
  protected readonly online = computed(() =>
    onlineMembers(this.chat.contacts(), (id) => this.presence.presence(id)),
  );

  protected readonly time = timeRange;

  constructor() {
    void this.loadNews();
    void this.loadEvents();
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'events') {
          void this.loadEvents();
        }
      });
  }

  protected async loadNews(): Promise<void> {
    try {
      this.news.set((await this.newsService.page()).items.slice(0, CAROUSEL_NEWS));
      this.newsError.set(null);
    } catch (e) {
      this.newsError.set(apiErrorMessage(e, 'Impossible de charger les news.'));
    } finally {
      this.newsLoaded.set(true);
    }
  }

  protected async loadEvents(): Promise<void> {
    const from = new Date();
    const to = new Date(from);
    to.setDate(to.getDate() + HOME_DAYS);
    try {
      this.events.set(await this.eventsService.between(from, to));
      this.eventsError.set(null);
    } catch (e) {
      this.eventsError.set(apiErrorMessage(e, 'Impossible de charger les événements.'));
    } finally {
      this.eventsLoaded.set(true);
    }
  }
}
