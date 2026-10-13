import { HttpClient } from '@angular/common/http';
import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import { Conversation, Member, Message } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { RealtimeService } from './realtime.service';

/** Must match the page size of GET /api/conversations/{id}/messages. */
const PAGE_SIZE = 50;

interface Thread {
  messages: Message[];
  hasOlder: boolean;
}

/**
 * Client-side state of the messaging: clan members, conversations, loaded messages
 * and unread counts, kept in sync with the server through real-time events.
 */
@Injectable()
export class ChatStore {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly realtime = inject(RealtimeService);

  private readonly _members = signal<Member[]>([]);
  private readonly _conversations = signal<Conversation[]>([]);
  private readonly _threads = signal<Record<number, Thread>>({});
  private readonly _activeConversationId = signal<number | null>(null);

  readonly status = this.realtime.status;
  readonly me = this.auth.user;

  /** The other clan members, i.e. the people one can talk to. */
  readonly contacts = computed(() => this._members().filter((m) => m.id !== this.me()?.id));

  /** Conversation with each contact, indexed by the contact's id. */
  readonly conversationByContact = computed(() => {
    const myId = this.me()?.id;
    const map = new Map<number, Conversation>();
    for (const conversation of this._conversations()) {
      const other = conversation.participants.find((p) => p.id !== myId);
      if (other) {
        map.set(other.id, conversation);
      }
    }
    return map;
  });

  readonly totalUnread = computed(() =>
    this._conversations().reduce((sum, c) => sum + c.unreadCount, 0),
  );

  constructor() {
    this.realtime.events$.pipe(takeUntilDestroyed()).subscribe((event) => {
      if (event.type === 'message') {
        this.receive(event.message);
      } else if (event.type === 'member') {
        this.memberUpdated(event.member);
      } else if (event.type === 'member-joined') {
        this.addMember(event.member);
      } else if (event.type === 'member-left') {
        this._members.update((members) => members.filter((m) => m.id !== event.userId));
      }
    });
    // Catch up on what happened while the connection was down
    this.realtime.reconnected$.pipe(takeUntilDestroyed()).subscribe(() => void this.resync());

    const onVisible = () => {
      const active = this._activeConversationId();
      if (document.visibilityState === 'visible' && active !== null) {
        void this.markRead(active);
      }
    };
    document.addEventListener('visibilitychange', onVisible);
    inject(DestroyRef).onDestroy(() => document.removeEventListener('visibilitychange', onVisible));
  }

  async init(): Promise<void> {
    void this.realtime.connect();
    await Promise.all([this.loadMembers(), this.loadConversations()]);
  }

  async loadMembers(): Promise<void> {
    this._members.set(await firstValueFrom(this.http.get<Member[]>('api/clan/members')));
  }

  addMember(member: Member): void {
    this._members.update((members) =>
      [...members.filter((m) => m.id !== member.id), member].sort((a, b) =>
        a.displayName.localeCompare(b.displayName),
      ),
    );
  }

  /** A member changed their profile (name, avatar…). */
  memberUpdated(member: Member): void {
    this._members.update((members) =>
      members.map((m) => (m.id === member.id ? { ...m, ...member } : m)),
    );
    this._conversations.update((list) =>
      list.map((c) => ({
        ...c,
        participants: c.participants.map((p) => (p.id === member.id ? { ...p, ...member } : p)),
      })),
    );
    if (member.id === this.me()?.id) {
      this.auth.updateUser(member);
    }
  }

  member(id: number): Member | undefined {
    return this._members().find((m) => m.id === id);
  }

  thread(conversationId: number | null): Thread | undefined {
    return conversationId === null ? undefined : this._threads()[conversationId];
  }

  /** Opens the conversation with a contact (creating it if needed) and loads its latest messages. */
  async open(contactId: number): Promise<Conversation> {
    const conversation =
      this.conversationByContact().get(contactId) ??
      (await firstValueFrom(
        this.http.post<Conversation>('api/conversations', { userId: contactId }),
      ));

    this.upsertConversation(conversation);
    if (!this._threads()[conversation.id]) {
      await this.loadLatest(conversation.id);
    }

    return conversation;
  }

  /** Marks the conversation currently displayed, whose incoming messages are read right away. */
  setActive(conversationId: number | null): void {
    this._activeConversationId.set(conversationId);
    if (conversationId !== null) {
      void this.markRead(conversationId);
    }
  }

  async loadOlder(conversationId: number): Promise<void> {
    const thread = this._threads()[conversationId];
    const oldest = thread?.messages[0];
    if (!thread?.hasOlder || !oldest) {
      return;
    }

    const older = await firstValueFrom(
      this.http.get<Message[]>(`api/conversations/${conversationId}/messages`, {
        params: { before: oldest.id },
      }),
    );
    this.setThread(conversationId, {
      messages: mergeMessages(older, this._threads()[conversationId]?.messages ?? []),
      hasOlder: older.length === PAGE_SIZE,
    });
  }

  async send(conversationId: number, content: string): Promise<void> {
    const message = await firstValueFrom(
      this.http.post<Message>(`api/conversations/${conversationId}/messages`, { content }),
    );
    this.receive(message);
  }

  async markRead(conversationId: number): Promise<void> {
    const conversation = this._conversations().find((c) => c.id === conversationId);
    const lastId = conversation?.lastMessage?.id;
    if (!conversation || !lastId || (conversation.lastReadMessageId ?? 0) >= lastId) {
      return;
    }

    this.patchConversation(conversationId, { unreadCount: 0, lastReadMessageId: lastId });
    await firstValueFrom(
      this.http.post(`api/conversations/${conversationId}/read`, { messageId: lastId }),
    );
  }

  private async loadConversations(): Promise<void> {
    this._conversations.set(
      await firstValueFrom(this.http.get<Conversation[]>('api/conversations')),
    );
  }

  private async loadLatest(conversationId: number): Promise<void> {
    const latest = await firstValueFrom(
      this.http.get<Message[]>(`api/conversations/${conversationId}/messages`),
    );
    const current = this._threads()[conversationId];
    this.setThread(conversationId, {
      messages: mergeMessages(current?.messages ?? [], latest),
      hasOlder: current?.hasOlder ?? latest.length === PAGE_SIZE,
    });
  }

  private async resync(): Promise<void> {
    await Promise.all([this.loadMembers(), this.loadConversations()]);
    const active = this._activeConversationId();
    if (active !== null) {
      await this.loadLatest(active);
      await this.markRead(active);
    }
  }

  /** Handles a message, whether sent by the user or pushed by the server (possibly both). */
  private receive(message: Message): void {
    const conversation = this._conversations().find((c) => c.id === message.conversationId);
    if (!conversation) {
      // First message of a conversation started by someone else
      void this.loadConversations();
      return;
    }

    const thread = this._threads()[message.conversationId];
    if (thread) {
      this.setThread(message.conversationId, {
        ...thread,
        messages: mergeMessages(thread.messages, [message]),
      });
    }

    if (conversation.lastMessage && conversation.lastMessage.id >= message.id) {
      return; // Already known
    }

    const mine = message.senderId === this.me()?.id;
    const isRead =
      mine ||
      (this._activeConversationId() === message.conversationId &&
        document.visibilityState === 'visible');
    this.patchConversation(message.conversationId, {
      lastMessage: message,
      unreadCount: mine ? conversation.unreadCount : conversation.unreadCount + (isRead ? 0 : 1),
      lastReadMessageId: mine ? message.id : conversation.lastReadMessageId,
    });
    // Most recently active conversations first
    this._conversations.update((list) => [
      ...list.filter((c) => c.id === message.conversationId),
      ...list.filter((c) => c.id !== message.conversationId),
    ]);

    if (isRead && !mine) {
      void this.markRead(message.conversationId);
    }
  }

  private upsertConversation(conversation: Conversation): void {
    this._conversations.update((list) =>
      list.some((c) => c.id === conversation.id) ? list : [...list, conversation],
    );
  }

  private patchConversation(id: number, patch: Partial<Conversation>): void {
    this._conversations.update((list) => list.map((c) => (c.id === id ? { ...c, ...patch } : c)));
  }

  private setThread(id: number, thread: Thread): void {
    this._threads.update((threads) => ({ ...threads, [id]: thread }));
  }
}

/** Merges two lists of messages, without duplicates, in chronological order. */
function mergeMessages(a: Message[], b: Message[]): Message[] {
  const byId = new Map<number, Message>();
  for (const message of [...a, ...b]) {
    byId.set(message.id, message);
  }
  return [...byId.values()].sort((x, y) => x.id - y.id);
}
