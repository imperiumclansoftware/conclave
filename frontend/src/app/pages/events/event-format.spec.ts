import { ClanEvent } from '../../core/api/models';
import { byDay, dayLabel, fromLocalInput, timeRange, toLocalInput } from './event-format';

function at(days: number, hours: number, minutes = 0): string {
  const date = new Date();
  date.setDate(date.getDate() + days);
  date.setHours(hours, minutes, 0, 0);
  return date.toISOString();
}

describe('event format', () => {
  it('names the days', () => {
    expect(dayLabel(at(0, 21))).toBe("Aujourd'hui");
    expect(dayLabel(at(1, 21))).toBe('Demain');
    expect(dayLabel('2031-05-30T21:00:00+02:00')).toBe('Vendredi 30 mai 2031');
  });

  it('shows the times, with the end day when it ends another day', () => {
    expect(timeRange({ startsAt: at(0, 21), endsAt: at(0, 23, 30) })).toBe('21:00 – 23:30');
    expect(timeRange({ startsAt: at(0, 21), endsAt: null })).toBe('21:00');
    expect(timeRange({ startsAt: at(0, 21), endsAt: at(1, 1) })).toBe('21:00 – demain 01:00');
  });

  it('groups the events by day, in order', () => {
    const event = (id: number, startsAt: string) => ({ id, startsAt }) as ClanEvent;
    const groups = byDay([event(1, at(0, 18)), event(2, at(0, 21)), event(3, at(1, 20))]);
    expect(groups.map((g) => [g.day, g.events.map((e) => e.id)])).toEqual([
      ["Aujourd'hui", [1, 2]],
      ['Demain', [3]],
    ]);
  });

  it('converts the date fields of the form back and forth', () => {
    const iso = at(3, 20, 45);
    expect(fromLocalInput(toLocalInput(iso))).toBe(iso);
    expect(toLocalInput(null)).toBe('');
    expect(fromLocalInput('')).toBeNull();
  });
});
