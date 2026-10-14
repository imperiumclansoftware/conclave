import { ClanEvent } from '../../core/api/models';

const dayFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});
const dayYearFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});
const timeFormat = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });

function sameDay(a: Date, b: Date): boolean {
  return a.toDateString() === b.toDateString();
}

/** "Aujourd'hui", "Demain", or "samedi 30 mai" (with the year when it is not this one) */
export function dayLabel(iso: string): string {
  const date = new Date(iso);
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  if (sameDay(date, today)) {
    return "Aujourd'hui";
  }
  if (sameDay(date, tomorrow)) {
    return 'Demain';
  }
  const label = (date.getFullYear() === today.getFullYear() ? dayFormat : dayYearFormat).format(
    date,
  );
  return label.charAt(0).toUpperCase() + label.slice(1);
}

/** "21:00 – 23:30", or with the end day when it ends another day */
export function timeRange(event: Pick<ClanEvent, 'startsAt' | 'endsAt'>): string {
  const start = new Date(event.startsAt);
  if (!event.endsAt) {
    return timeFormat.format(start);
  }
  const end = new Date(event.endsAt);
  return sameDay(start, end)
    ? `${timeFormat.format(start)} – ${timeFormat.format(end)}`
    : `${timeFormat.format(start)} – ${dayLabel(event.endsAt).toLowerCase()} ${timeFormat.format(end)}`;
}

/** Events grouped by the day they start, in order */
export function byDay(events: ClanEvent[]): { day: string; events: ClanEvent[] }[] {
  const groups: { day: string; events: ClanEvent[] }[] = [];
  for (const event of events) {
    const day = dayLabel(event.startsAt);
    const last = groups.at(-1);
    if (last?.day === day) {
      last.events.push(event);
    } else {
      groups.push({ day, events: [event] });
    }
  }
  return groups;
}

/** Value of an <input type="datetime-local">, in the browser's time zone */
export function toLocalInput(iso: string | null): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** From an <input type="datetime-local"> to an instant with its offset, for the API */
export function fromLocalInput(value: string): string | null {
  return value ? new Date(value).toISOString() : null;
}
