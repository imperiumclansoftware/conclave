import { AppStatus, DiscordStatus, Member, Presence } from '../../core/api/models';
import { onlineMembers, slideIndex } from './home';

const member = (id: number, displayName: string): Member => ({
  id,
  username: displayName.toLowerCase(),
  displayName,
  role: 'member',
});

const presence = (userId: number, app: AppStatus, discord: DiscordStatus | null): Presence => ({
  userId,
  app: { status: app, ageSeconds: app === 'offline' ? null : 5 },
  discord: discord ? { username: 'd', avatarUrl: null, status: discord, activity: null } : null,
});

describe('onlineMembers', () => {
  it('keeps the connected members, the most available first', () => {
    const members = [
      member(1, 'Zoé'),
      member(2, 'Marc'),
      member(3, 'Alice'),
      member(4, 'Bruno'),
      member(5, 'Chloé'),
      member(6, 'Inconnu'),
    ];
    const presences = new Map<number, Presence>([
      [1, presence(1, 'online', null)],
      [2, presence(2, 'offline', 'dnd')],
      [3, presence(3, 'away', null)],
      [4, presence(4, 'online', 'online')],
      [5, presence(5, 'offline', 'offline')],
    ]);

    const online = onlineMembers(members, (id) => presences.get(id) ?? null);

    expect(online.map((m) => m.displayName)).toEqual(['Bruno', 'Zoé', 'Alice', 'Marc']);
  });
});

describe('slideIndex', () => {
  it('wraps around at both ends', () => {
    expect(slideIndex(0, 1, 3)).toBe(1);
    expect(slideIndex(2, 1, 3)).toBe(0);
    expect(slideIndex(0, -1, 3)).toBe(2);
    expect(slideIndex(0, 1, 0)).toBe(0);
  });
});
