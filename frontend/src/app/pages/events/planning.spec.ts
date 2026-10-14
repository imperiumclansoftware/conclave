import { ClanEvent } from '../../core/api/models';
import { monthGrid, parseMonth, planningDays } from './planning';

const event = (id: number, startsAt: string, endsAt: string | null = null) =>
  ({ id, startsAt, endsAt }) as ClanEvent;

describe('planning', () => {
  it('covers whole weeks, from Monday to Sunday', () => {
    const { start, end } = monthGrid(parseMonth('2026-10'));
    // October 2026 starts on a Thursday and ends on a Saturday
    expect([start.getDate(), start.getMonth(), start.getDay()]).toEqual([28, 8, 1]);
    expect([end.getDate(), end.getMonth()]).toEqual([2, 10]);
    expect(planningDays(parseMonth('2026-10'), [])).toHaveLength(35);
  });

  it('puts each event on the days it takes place', () => {
    const days = planningDays(parseMonth('2026-10'), [
      event(1, new Date(2026, 9, 17, 21).toISOString(), new Date(2026, 9, 17, 23).toISOString()),
      event(2, new Date(2026, 9, 17, 22).toISOString(), new Date(2026, 9, 18, 2).toISOString()),
      event(3, new Date(2026, 9, 19, 20).toISOString(), new Date(2026, 9, 20, 0).toISOString()),
    ]);
    const on = (day: number) =>
      days
        .find((d) => d.date.getMonth() === 9 && d.date.getDate() === day)!
        .events.map((e) => e.id);
    expect(on(17)).toEqual([1, 2]);
    expect(on(18)).toEqual([2]);
    // Ending at midnight: not on the next day
    expect(on(19)).toEqual([3]);
    expect(on(20)).toEqual([]);
  });

  it('falls back to this month for an invalid parameter', () => {
    const month = parseMonth('n’importe quoi');
    expect([month.getFullYear(), month.getMonth(), month.getDate()]).toEqual([
      new Date().getFullYear(),
      new Date().getMonth(),
      1,
    ]);
  });
});
