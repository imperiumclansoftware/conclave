import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Injectable, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Subject } from 'rxjs';
import { Presence, VideoCall } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { CallsService } from '../discord/calls.service';
import { RealtimeEvent, RealtimeService } from '../chat/realtime.service';
import { PresenceService } from './presence.service';

@Injectable()
class FakeRealtime {
  readonly status = signal('connected');
  readonly events$ = new Subject<RealtimeEvent>();
  readonly reconnected$ = new Subject<void>();
}

const me = {
  id: 1,
  username: 'chef',
  displayName: 'Chef',
  role: 'leader' as const,
  clan: { id: 1, name: 'Dupont' },
};
const tom = { id: 2, username: 'tom', displayName: 'Tom', role: 'member' as const };

function presence(
  userId: number,
  status: Presence['app']['status'],
  ageSeconds: number | null,
): Presence {
  return { userId, app: { status, ageSeconds }, discord: null };
}

describe('Presence and calls', () => {
  let http: HttpTestingController;
  let realtime: FakeRealtime;

  beforeEach(() => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        PresenceService,
        CallsService,
        { provide: RealtimeService, useClass: FakeRealtime },
        { provide: AuthService, useValue: { user: signal(me), token: signal('jwt') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    realtime = TestBed.inject(RealtimeService) as unknown as FakeRealtime;
  });

  afterEach(() => vi.useRealTimers());

  it('expires an app status when heartbeats stop, without server events', async () => {
    const service = TestBed.inject(PresenceService);
    const init = service.init();
    http.expectOne('api/presence').flush(null);
    http.expectOne('api/clan/presence').flush([presence(2, 'online', 60)]);
    await init;

    expect(service.appStatus(2)).toBe('online');

    // 75 s timeout - 60 s already elapsed: offline after ~15 s
    await vi.advanceTimersByTimeAsync(16_000);
    expect(service.appStatus(2)).toBe('offline');

    // A real-time update brings it back
    realtime.events$.next({ type: 'presence', presence: presence(2, 'away', 0) });
    expect(service.appStatus(2)).toBe('away');
    http.match('api/presence');
  });

  it('shows invitations to calls started by others, until they end', () => {
    const calls = TestBed.inject(CallsService);
    const call: VideoCall = {
      id: 9,
      title: 'Raid du soir',
      joinUrl: 'https://discord.com/channels/900/5001',
      createdBy: tom,
      participants: [tom, me],
      connectedUserIds: [],
      started: false,
      createdAt: '2026-10-09T20:00:00+00:00',
    };

    realtime.events$.next({ type: 'call', call });
    expect(calls.invitations().map((c) => c.id)).toEqual([9]);

    // Updates of a known call are not new invitations
    realtime.events$.next({ type: 'call', call: { ...call, connectedUserIds: [2] } });
    expect(calls.invitations()).toHaveLength(1);
    expect(calls.calls()[0].connectedUserIds).toEqual([2]);

    realtime.events$.next({ type: 'call-ended', callId: 9 });
    expect(calls.calls()).toEqual([]);
    expect(calls.invitations()).toEqual([]);
  });

  it('does not invite the creator to their own call', () => {
    const calls = TestBed.inject(CallsService);
    realtime.events$.next({
      type: 'call',
      call: {
        id: 3,
        title: 'x',
        joinUrl: '',
        createdBy: { ...me },
        participants: [],
        connectedUserIds: [],
        started: false,
        createdAt: '',
      },
    });

    expect(calls.calls()).toHaveLength(1);
    expect(calls.invitations()).toEqual([]);
  });
});
