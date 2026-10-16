import { EventMessage, Member } from '../../core/api/models';
import { chatItems, withMessage } from './event-chat-format';

const lea: Member = { id: 2, username: 'lea', displayName: 'Léa', role: 'member' };
const marc: Member = { id: 3, username: 'marc', displayName: 'Marc', role: 'member' };

const message = (id: number, author: Member | null, createdAt: string): EventMessage => ({
  id,
  eventId: 1,
  author,
  content: `Message ${id}`,
  createdAt,
});

describe('event chat', () => {
  it('groups the messages of an author and separates the days', () => {
    const items = chatItems(
      [
        message(1, lea, '2026-10-10T20:00:00'),
        message(2, lea, '2026-10-10T20:03:00'),
        message(3, lea, '2026-10-10T20:30:00'),
        message(4, marc, '2026-10-10T20:31:00'),
        message(5, marc, '2026-10-11T09:00:00'),
        message(6, null, '2026-10-11T09:01:00'),
      ],
      3,
    );

    expect(items.map((i) => i.showAuthor)).toEqual([true, false, true, true, true, true]);
    expect(items.map((i) => !!i.day)).toEqual([true, false, false, false, true, false]);
    expect(items.map((i) => i.mine)).toEqual([false, false, false, true, true, false]);
  });

  it('adds a message once, in order', () => {
    const list = [message(1, lea, '2026-10-10T20:00:00'), message(3, lea, '2026-10-10T20:02:00')];

    expect(withMessage(list, message(2, marc, '2026-10-10T20:01:00')).map((m) => m.id)).toEqual([
      1, 2, 3,
    ]);
    expect(withMessage(list, list[1])).toBe(list);
  });
});
