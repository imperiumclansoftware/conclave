import {
  Component,
  ElementRef,
  Injector,
  OnDestroy,
  afterNextRender,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { Message } from '../../core/api/models';
import { ChatStore } from '../../core/chat/chat.store';
import { DiscordService } from '../../core/discord/discord.service';
import { PresenceService } from '../../core/presence/presence.service';
import { PresenceBadge } from '../../shared/presence-badge';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';

/** Must match SendMessageRequest::MAX_LENGTH on the API. */
const MAX_LENGTH = 4000;
/** Distance (px) from the bottom under which new messages keep the view scrolled down. */
const STICK_TO_BOTTOM_THRESHOLD = 80;

interface MessageItem {
  message: Message;
  mine: boolean;
  /** Day label shown before the first message of each day. */
  day: string | null;
  /** First message received since the user's previous visit. */
  firstUnread: boolean;
}

@Component({
  selector: 'app-thread',
  imports: [MemberCardDirective, RouterLink, PresenceBadge, Icon],
  templateUrl: './thread.html',
  styleUrl: './thread.scss',
})
export class Thread implements OnDestroy {
  private readonly chat = inject(ChatStore);
  private readonly injector = inject(Injector);
  private readonly scroller = viewChild<ElementRef<HTMLElement>>('scroller');
  private readonly composer = viewChild<ElementRef<HTMLTextAreaElement>>('composer');

  /** Member whose conversation is shown */
  readonly memberId = input.required<number>();
  /** Back to the list of conversations */
  readonly back = output<void>();
  /** Start a video call with this member */
  readonly call = output<number>();

  private readonly discord = inject(DiscordService);
  private readonly presence = inject(PresenceService);

  protected readonly maxLength = MAX_LENGTH;
  protected readonly numericMemberId = computed(() => this.memberId());
  /** A call needs the clan's Discord server and a linked account for this member. */
  protected readonly canCall = computed(
    () =>
      !!this.discord.status()?.guild && !!this.presence.presence(this.numericMemberId())?.discord,
  );
  protected readonly member = computed(() => this.chat.member(this.memberId()));
  protected readonly conversationId = signal<number | null>(null);
  protected readonly loadError = signal<string | null>(null);
  protected readonly sendError = signal<string | null>(null);
  protected readonly draft = signal('');
  protected readonly sending = signal(false);
  protected readonly loadingOlder = signal(false);

  /** Read marker when the conversation was opened, to highlight what arrived since. */
  private readonly readMarker = signal<number | null>(null);
  private atBottom = true;
  private forceScroll = true;

  private readonly thread = computed(() => this.chat.thread(this.conversationId()));
  protected readonly hasOlder = computed(() => this.thread()?.hasOlder ?? false);
  protected readonly items = computed<MessageItem[]>(() => {
    const myId = this.chat.me()?.id;
    const marker = this.readMarker();
    let previousDay: string | null = null;
    let unreadShown = false;

    return (this.thread()?.messages ?? []).map((message) => {
      const day = dayLabel(message.createdAt);
      const mine = message.senderId === myId;
      const firstUnread = !unreadShown && !mine && marker !== null && message.id > marker;
      unreadShown ||= firstUnread;
      const item = { message, mine, day: day !== previousDay ? day : null, firstUnread };
      previousDay = day;
      return item;
    });
  });

  constructor() {
    // (Re)open the conversation whenever the selected member changes
    effect((onCleanup) => {
      const memberId = this.memberId();
      let cancelled = false;
      onCleanup(() => (cancelled = true));
      untracked(() => void this.open(memberId, () => cancelled));
    });

    // Keep the latest message visible, unless the user scrolled up to read older ones
    effect(() => {
      const last = this.items().at(-1);
      if (!last) {
        return;
      }
      const shouldScroll = this.forceScroll || this.atBottom || last.mine;
      afterNextRender(
        () => {
          if (shouldScroll) {
            this.scrollToBottom();
            this.forceScroll = false;
          }
        },
        { injector: this.injector },
      );
    });
  }

  ngOnDestroy(): void {
    this.chat.setActive(null);
  }

  protected onScroll(): void {
    const el = this.scroller()?.nativeElement;
    if (el) {
      this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < STICK_TO_BOTTOM_THRESHOLD;
    }
  }

  protected async loadOlder(): Promise<void> {
    const id = this.conversationId();
    const el = this.scroller()?.nativeElement;
    if (id === null || !el || this.loadingOlder()) {
      return;
    }

    this.loadingOlder.set(true);
    const previousHeight = el.scrollHeight;
    try {
      await this.chat.loadOlder(id);
      // Keep the messages the user was looking at in place
      afterNextRender(() => (el.scrollTop += el.scrollHeight - previousHeight), {
        injector: this.injector,
      });
    } catch (e) {
      this.loadError.set(apiErrorMessage(e));
    } finally {
      this.loadingOlder.set(false);
    }
  }

  protected onKeydown(event: KeyboardEvent): void {
    // Enter sends, Shift+Enter adds a line break (ignored while composing accented characters)
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      void this.send();
    }
  }

  protected async send(): Promise<void> {
    const id = this.conversationId();
    const content = this.draft().trim();
    if (id === null || !content || this.sending()) {
      return;
    }

    this.sending.set(true);
    this.sendError.set(null);
    // Clear right away so that the user can type the next message during the request
    this.draft.set('');
    try {
      await this.chat.send(id, content);
    } catch (e) {
      this.sendError.set(apiErrorMessage(e, "Le message n'a pas pu être envoyé."));
      if (!this.draft().trim()) {
        this.draft.set(content);
      }
    } finally {
      this.sending.set(false);
      this.composer()?.nativeElement.focus();
    }
  }

  protected time(message: Message): string {
    return timeFormat.format(new Date(message.createdAt));
  }

  private async open(memberId: number, isCancelled: () => boolean): Promise<void> {
    this.conversationId.set(null);
    this.loadError.set(null);
    this.sendError.set(null);
    this.draft.set('');
    this.forceScroll = true;
    this.atBottom = true;

    try {
      const conversation = await this.chat.open(memberId);
      if (isCancelled()) {
        return;
      }
      this.readMarker.set(
        conversation.unreadCount > 0 ? (conversation.lastReadMessageId ?? 0) : null,
      );
      this.conversationId.set(conversation.id);
      this.chat.setActive(conversation.id);
    } catch (e) {
      if (!isCancelled()) {
        this.loadError.set(apiErrorMessage(e, "Impossible d'ouvrir la conversation."));
      }
    }
  }

  private scrollToBottom(): void {
    const el = this.scroller()?.nativeElement;
    if (el) {
      el.scrollTop = el.scrollHeight;
      this.atBottom = true;
    }
  }
}

const timeFormat = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });
const dayFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});

function dayLabel(iso: string): string {
  const date = new Date(iso);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return "Aujourd'hui";
  }
  if (date.toDateString() === yesterday.toDateString()) {
    return 'Hier';
  }
  return dayFormat.format(date);
}
