import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Injectable, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Subject } from 'rxjs';
import { Conversation, Me, Member, Message } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { ChatStore } from './chat.store';
import { RealtimeEvent, RealtimeService, RealtimeStatus } from './realtime.service';

@Injectable()
class FakeRealtime {
  readonly status = signal<RealtimeStatus>('connected');
  readonly events$ = new Subject<RealtimeEvent>();
  readonly reconnected$ = new Subject<void>();
  connect = vi.fn(async () => undefined);
}

const papa: Me = {
  id: 1,
  username: 'papa',
  displayName: 'Papa',
  role: 'leader',
  clan: { id: 1, name: 'Martin' },
  email: null,
  faction: null,
  canPublishNews: false,
};
const lea: Member = { id: 2, username: 'lea', displayName: 'Léa', role: 'member' };

function message(id: number, senderId: number, content = `Message ${id}`): Message {
  return { id, conversationId: 10, senderId, content, createdAt: '2026-10-09T10:00:00+00:00' };
}

function conversation(patch: Partial<Conversation> = {}): Conversation {
  return {
    id: 10,
    participants: [papa, lea],
    lastMessage: null,
    lastReadMessageId: null,
    unreadCount: 0,
    ...patch,
  };
}

describe('ChatStore', () => {
  let store: ChatStore;
  let http: HttpTestingController;
  let realtime: FakeRealtime;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        ChatStore,
        { provide: RealtimeService, useClass: FakeRealtime },
        { provide: AuthService, useValue: { user: signal(papa), updateUser: vi.fn() } },
      ],
    });
    store = TestBed.inject(ChatStore);
    http = TestBed.inject(HttpTestingController);
    realtime = TestBed.inject(RealtimeService) as unknown as FakeRealtime;

    const init = store.init();
    http.expectOne('api/clan/members').flush([papa, lea]);
    http
      .expectOne('api/conversations')
      .flush([conversation({ lastMessage: message(1, 2), unreadCount: 1 })]);
    await init;
  });

  afterEach(() => http.verify());

  it('lists the other members and their unread messages', () => {
    expect(realtime.connect).toHaveBeenCalled();
    expect(store.contacts()).toEqual([lea]);
    expect(store.conversationByContact().get(lea.id)?.unreadCount).toBe(1);
    expect(store.totalUnread()).toBe(1);
  });

  it('counts messages pushed while the conversation is not displayed as unread', () => {
    realtime.events$.next({ type: 'message', message: message(2, lea.id) });

    expect(store.totalUnread()).toBe(2);
    expect(store.conversationByContact().get(lea.id)?.lastMessage?.id).toBe(2);
  });

  it('does not duplicate a sent message echoed by the real-time channel', async () => {
    const open = store.open(lea.id);
    http.expectOne('api/conversations/10/messages').flush([message(1, lea.id)]);
    await open;

    const send = store.send(10, 'Coucou');
    http.expectOne('api/conversations/10/messages').flush(message(2, papa.id, 'Coucou'));
    await send;
    realtime.events$.next({ type: 'message', message: message(2, papa.id, 'Coucou') });

    expect(store.thread(10)?.messages.map((m) => m.id)).toEqual([1, 2]);
    // Sending a message does not make the conversation unread for its author
    expect(store.totalUnread()).toBe(1);
  });

  it('marks the active conversation as read', async () => {
    const open = store.open(lea.id);
    http.expectOne('api/conversations/10/messages').flush([message(1, lea.id)]);
    await open;

    store.setActive(10);
    const read = http.expectOne('api/conversations/10/read');
    expect(read.request.body).toEqual({ messageId: 1 });
    read.flush(null);

    expect(store.totalUnread()).toBe(0);
  });

  it('reloads conversations when a new conversation starts', () => {
    realtime.events$.next({
      type: 'message',
      message: { ...message(5, lea.id), conversationId: 99 },
    });

    http.expectOne('api/conversations').flush([]);
  });

  it('applies profile changes pushed in real time', () => {
    realtime.events$.next({
      type: 'member',
      member: { ...lea, displayName: 'Léa A.', avatarUrl: 'api/avatars/x.webp' },
    });

    expect(store.contacts()[0]).toMatchObject({
      displayName: 'Léa A.',
      avatarUrl: 'api/avatars/x.webp',
    });
    expect(store.conversationByContact().get(lea.id)?.participants[1].displayName).toBe('Léa A.');

    const auth = TestBed.inject(AuthService) as unknown as { updateUser: ReturnType<typeof vi.fn> };
    realtime.events$.next({
      type: 'member',
      member: { ...papa, avatarUrl: 'api/avatars/me.webp' },
    });
    expect(auth.updateUser).toHaveBeenCalledWith(
      expect.objectContaining({ avatarUrl: 'api/avatars/me.webp' }),
    );
  });
});
