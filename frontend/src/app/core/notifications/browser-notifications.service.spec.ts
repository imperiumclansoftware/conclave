import { invitationNotice } from './browser-notifications.service';

describe('invitationNotice', () => {
  it('names the game, its platform and the organiser', () => {
    const notice = invitationNotice({
      id: 4,
      title: 'Raid du vendredi',
      typeLabel: 'Session de jeu',
      startsAt: '2026-10-23T19:00:00Z',
      game: 'ELDEN RING',
      platform: 'PC',
      organiser: 'Léa',
    });

    expect(notice.title).toBe('Session de jeu sur ELDEN RING (PC)');
    expect(notice.body).toMatch(
      /^Léa organise « Raid du vendredi », vendredi 23 octobre à \d{2}:00\.$/,
    );
  });

  it('copes with an organiser who left', () => {
    const notice = invitationNotice({
      id: 4,
      title: 'Tournoi',
      typeLabel: 'Tournoi',
      startsAt: '2026-10-23T19:00:00Z',
      game: 'Rocket League',
      platform: null,
      organiser: null,
    });

    expect(notice.title).toBe('Tournoi sur Rocket League');
    expect(notice.body.startsWith('Nouvel événement : « Tournoi »')).toBe(true);
  });
});
