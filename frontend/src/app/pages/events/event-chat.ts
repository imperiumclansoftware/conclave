import {
  Component,
  DestroyRef,
  ElementRef,
  afterNextRender,
  computed,
  effect,
  inject,
  Injector,
  input,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { ClanEvent, EVENT_MESSAGE_MAX, EventMessage } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { RealtimeService } from '../../core/chat/realtime.service';
import { EventsService } from '../../core/events/events.service';
import { Avatar } from '../../shared/avatar';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { chatItems, withMessage } from './event-chat-format';

/** Distance (px) from the bottom under which new messages keep the view scrolled down */
const STICK_TO_BOTTOM_PX = 80;
/** Longest delay setTimeout accepts; later closings are noticed on the next visit */
const MAX_TIMER_MS = 2_147_483_647;

const timeFormat = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });
const closingFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  hour: '2-digit',
  minute: '2-digit',
});

/**
 * Chat room of an event: the participants write before and during the event, everybody reads,
 * and it turns read-only once the event is over (closing on its own while the page is open).
 */
@Component({
  selector: 'app-event-chat',
  imports: [RouterLink, Avatar, Icon, MemberCardDirective],
  templateUrl: './event-chat.html',
  styleUrl: './event-chat.scss',
})
export class EventChat {
  private readonly eventsService = inject(EventsService);
  private readonly injector = inject(Injector);
  private readonly auth = inject(AuthService);
  private readonly scroller = viewChild<ElementRef<HTMLElement>>('scroller');

  readonly event = input.required<ClanEvent>();

  protected readonly maxLength = EVENT_MESSAGE_MAX;
  protected readonly messages = signal<EventMessage[]>([]);
  protected readonly hasMore = signal(false);
  protected readonly canModerate = signal(false);
  protected readonly loaded = signal(false);
  protected readonly loadingOlder = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly draft = signal('');
  protected readonly sendError = signal<string | null>(null);
  /** Set when the closing time passes while the page is open */
  private readonly closedHere = signal(false);

  private readonly eventId = computed(() => this.event().id);
  protected readonly open = computed(() => this.event().chat.open && !this.closedHere());
  protected readonly canWrite = computed(() => this.event().chat.canWrite && this.open());
  protected readonly items = computed(() =>
    chatItems(this.messages(), this.auth.user()?.id ?? null),
  );
  protected readonly closing = computed(() =>
    closingFormat.format(new Date(this.event().chat.closesAt)),
  );

  private atBottom = true;
  /** Messages being sent, in order */
  private queue = Promise.resolve();

  constructor() {
    effect(() => {
      const id = this.eventId();
      untracked(() => void this.load(id));
    });

    // Turns read-only on time, without waiting for a reload
    let timer: ReturnType<typeof setTimeout> | undefined;
    effect(() => {
      clearTimeout(timer);
      const delay = Date.parse(this.event().chat.closesAt) - Date.now();
      this.closedHere.set(delay <= 0);
      if (delay > 0 && delay < MAX_TIMER_MS) {
        timer = setTimeout(() => this.closedHere.set(true), delay);
      }
    });
    inject(DestroyRef).onDestroy(() => clearTimeout(timer));

    const realtime = inject(RealtimeService);
    realtime.events$.pipe(takeUntilDestroyed()).subscribe((event) => {
      if (event.type === 'event-message' && event.message.eventId === this.eventId()) {
        const stick = this.atBottom;
        this.messages.update((list) => withMessage(list, event.message));
        if (stick) {
          this.scrollToBottom();
        }
      } else if (event.type === 'event-message-deleted' && event.eventId === this.eventId()) {
        this.messages.update((list) => list.filter((m) => m.id !== event.messageId));
      }
    });
    // Messages sent while disconnected
    realtime.reconnected$
      .pipe(takeUntilDestroyed())
      .subscribe(() => void this.load(this.eventId()));
  }

  protected time(message: EventMessage): string {
    return timeFormat.format(new Date(message.createdAt));
  }

  protected canDelete(message: EventMessage): boolean {
    const mine = message.author !== null && message.author.id === this.auth.user()?.id;
    return this.canModerate() || (mine && this.open());
  }

  protected onScroll(): void {
    const el = this.scroller()?.nativeElement;
    if (el) {
      this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < STICK_TO_BOTTOM_PX;
    }
  }

  protected onKeydown(event: KeyboardEvent): void {
    // Enter sends, Shift+Enter adds a line break (ignored while composing accented characters)
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      void this.send();
    }
  }

  /**
   * Sends the draft and empties the field at once, so that the next message can be typed; the
   * messages leave one after the other, in the order they were written.
   */
  protected send(): Promise<void> {
    const content = this.draft().trim();
    if (!content || !this.canWrite()) {
      return this.queue;
    }
    this.draft.set('');
    this.sendError.set(null);
    this.queue = this.queue.then(() => this.post(content));
    return this.queue;
  }

  private async post(content: string): Promise<void> {
    try {
      const message = await this.eventsService.postMessage(this.eventId(), content);
      this.messages.update((list) => withMessage(list, message));
      this.scrollToBottom();
    } catch (e) {
      this.sendError.set(apiErrorMessage(e, "Le message n'a pas pu être envoyé."));
      // Give the text back, unless another one is being typed
      if (!this.draft()) {
        this.draft.set(content);
      }
    }
  }

  protected async remove(message: EventMessage): Promise<void> {
    if (!confirm('Retirer ce message de la discussion ?')) {
      return;
    }
    try {
      await this.eventsService.deleteMessage(this.eventId(), message.id);
      this.messages.update((list) => list.filter((m) => m.id !== message.id));
    } catch (e) {
      this.sendError.set(apiErrorMessage(e, "Le message n'a pas pu être retiré."));
    }
  }

  protected async loadOlder(): Promise<void> {
    const first = this.messages()[0];
    const el = this.scroller()?.nativeElement;
    if (!first || !el || this.loadingOlder()) {
      return;
    }
    this.loadingOlder.set(true);
    try {
      const page = await this.eventsService.messages(this.eventId(), first.id);
      const previousHeight = el.scrollHeight;
      this.messages.update((list) => [...page.items, ...list]);
      this.hasMore.set(page.hasMore);
      // Keep the message being read in place
      afterNextRender(() => (el.scrollTop += el.scrollHeight - previousHeight), {
        injector: this.injector,
      });
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les messages précédents.'));
    } finally {
      this.loadingOlder.set(false);
    }
  }

  private async load(id: number): Promise<void> {
    try {
      const page = await this.eventsService.messages(id);
      this.messages.set(page.items);
      this.hasMore.set(page.hasMore);
      this.canModerate.set(page.canModerate);
      this.error.set(null);
      this.scrollToBottom();
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger la discussion.'));
    } finally {
      this.loaded.set(true);
    }
  }

  private scrollToBottom(): void {
    afterNextRender(
      () => {
        const el = this.scroller()?.nativeElement;
        if (el) {
          el.scrollTop = el.scrollHeight;
          this.atBottom = true;
        }
      },
      { injector: this.injector },
    );
  }
}
