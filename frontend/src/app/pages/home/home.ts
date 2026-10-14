import { Member, Presence } from '../../core/api/models';

/** Days of upcoming events shown on the home page */
export const HOME_DAYS = 7;
/** Latest news in the carousel */
export const CAROUSEL_NEWS = 5;

/**
 * Members connected somewhere, the most available first: in the app, away from it, then only
 * on Discord; by name within each group.
 */
export function onlineMembers(
  members: Member[],
  presence: (userId: number) => Presence | null,
): Member[] {
  const rank = (member: Member): number => {
    const p = presence(member.id);
    if (p?.app.status === 'online') {
      return 0;
    }
    if (p?.app.status === 'away') {
      return 1;
    }
    return p?.discord && p.discord.status !== 'offline' ? 2 : -1;
  };
  return members
    .map((member) => ({ member, rank: rank(member) }))
    .filter((m) => m.rank >= 0)
    .sort(
      (a, b) => a.rank - b.rank || a.member.displayName.localeCompare(b.member.displayName, 'fr'),
    )
    .map((m) => m.member);
}

/** Slide reached from another one, wrapping around at both ends */
export function slideIndex(current: number, step: number, count: number): number {
  return count ? (((current + step) % count) + count) % count : 0;
}
