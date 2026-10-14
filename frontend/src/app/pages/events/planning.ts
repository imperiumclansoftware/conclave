import { ClanEvent } from '../../core/api/models';

export interface PlanningDay {
  date: Date;
  /** "2026-10-17", for links and tracking */
  key: string;
  inMonth: boolean;
  today: boolean;
  past: boolean;
  events: ClanEvent[];
}

const pad = (n: number) => String(n).padStart(2, '0');

export function dayKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** "2026-10" for the month of a date */
export function monthKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

/** First day of the month "2026-10" (this month when invalid) */
export function parseMonth(value: string | undefined | null): Date {
  const match = /^(\d{4})-(\d{2})$/.exec(value ?? '');
  const now = new Date();
  return match
    ? new Date(Number(match[1]), Number(match[2]) - 1, 1)
    : new Date(now.getFullYear(), now.getMonth(), 1);
}

export function addMonths(month: Date, count: number): Date {
  return new Date(month.getFullYear(), month.getMonth() + count, 1);
}

/** Monday on or before the first day, to the Sunday on or after the last day: whole weeks */
export function monthGrid(month: Date): { start: Date; end: Date } {
  const first = new Date(month.getFullYear(), month.getMonth(), 1);
  const last = new Date(month.getFullYear(), month.getMonth() + 1, 0);
  const start = new Date(first);
  start.setDate(first.getDate() - ((first.getDay() + 6) % 7));
  const end = new Date(last);
  end.setDate(last.getDate() + (6 - ((last.getDay() + 6) % 7)) + 1);
  return { start, end };
}

/** The days of the grid, each with the events taking place that day (even partly) */
export function planningDays(month: Date, events: ClanEvent[], now = new Date()): PlanningDay[] {
  const { start, end } = monthGrid(month);
  const today = dayKey(now);
  const days: PlanningDay[] = [];
  for (const date = new Date(start); date < end; date.setDate(date.getDate() + 1)) {
    const dayStart = new Date(date);
    const dayEnd = new Date(date);
    dayEnd.setDate(dayEnd.getDate() + 1);
    const key = dayKey(dayStart);
    days.push({
      date: dayStart,
      key,
      inMonth: dayStart.getMonth() === month.getMonth(),
      today: key === today,
      past: key < today,
      events: events.filter((e) => {
        const startsAt = new Date(e.startsAt);
        const endsAt = e.endsAt ? new Date(e.endsAt) : startsAt;
        // An event ending at midnight does not spill over the next day
        return startsAt < dayEnd && (endsAt > dayStart || startsAt >= dayStart);
      }),
    });
  }
  return days;
}
