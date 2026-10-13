import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { MemberCard } from '../api/models';
import { MemberCardService } from './member-card.service';

const card = (id: number): MemberCard => ({
  id,
  username: 'lea',
  displayName: 'Léa',
  role: 'member',
  avatarUrl: null,
  memberSince: '2026-10-01T10:00:00+00:00',
  discordUsername: null,
  branches: [{ id: 3, name: 'Joueurs', crest: 'players' }],
  stats: { games: 4, news: 1, branches: 1 },
});

describe('MemberCardService', () => {
  let service: MemberCardService;
  let http: HttpTestingController;
  const anchor = document.createElement('a');

  beforeEach(() => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(MemberCardService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    vi.useRealTimers();
  });

  it('opens after a short rest on the name, loading the card meanwhile', async () => {
    service.show(7, anchor);
    http.expectOne('api/members/7/card').flush(card(7));
    expect(service.open()).toBeNull();

    await vi.advanceTimersByTimeAsync(400);

    expect(service.open()?.card?.stats).toEqual({ games: 4, news: 1, branches: 1 });
  });

  it('does not open for a pointer just passing by', async () => {
    service.show(7, anchor);
    http.expectOne('api/members/7/card').flush(card(7));
    service.hideSoon();

    await vi.advanceTimersByTimeAsync(1000);

    expect(service.open()).toBeNull();
  });

  it('stays open while the pointer is on the card, and reuses loaded cards', async () => {
    service.show(7, anchor);
    http.expectOne('api/members/7/card').flush(card(7));
    await vi.advanceTimersByTimeAsync(400);

    service.hideSoon();
    service.keep();
    await vi.advanceTimersByTimeAsync(1000);
    expect(service.open()?.memberId).toBe(7);

    service.hideSoon();
    await vi.advanceTimersByTimeAsync(250);
    expect(service.open()).toBeNull();

    // Hovering the same name again: no new request
    service.show(7, anchor);
    http.expectNone('api/members/7/card');
    await vi.advanceTimersByTimeAsync(400);
    expect(service.open()?.card?.displayName).toBe('Léa');
  });
});
