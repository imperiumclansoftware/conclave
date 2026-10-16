import { ServiceState, SystemReport } from '../../core/api/models';

const UNITS = ['octets', 'Ko', 'Mo', 'Go', 'To'];
const number = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });
const dayLong = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  timeZone: 'UTC',
});
const dayShort = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'numeric',
  timeZone: 'UTC',
});

/** "22,2 Ko", "9,6 Mo" (powers of 1024, as file managers do) */
export function formatBytes(bytes: number): string {
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < UNITS.length - 1) {
    value /= 1024;
    unit++;
  }
  return `${number.format(unit ? value : Math.round(value))} ${UNITS[unit]}`;
}

/** Top of the chart's scale: 1, 2 or 5 times a power of ten, at least the highest value */
export function niceMax(max: number): number {
  if (max <= 1) {
    return 1;
  }
  const power = 10 ** Math.floor(Math.log10(max));
  return [1, 2, 5, 10].map((m) => m * power).find((step) => step >= max)!;
}

export const STATE_LABELS: Record<ServiceState, string> = {
  ok: 'Opérationnel',
  warning: 'À vérifier',
  error: 'En panne',
  off: 'Désactivé',
};

/** The worst state of the services (switched off ones are a choice, not a problem) */
export function overallState(services: SystemReport['services']): {
  state: 'ok' | 'warning' | 'error';
  problems: number;
} {
  const errors = services.filter((s) => s.state === 'error').length;
  const warnings = services.filter((s) => s.state === 'warning').length;
  return {
    state: errors ? 'error' : warnings ? 'warning' : 'ok',
    problems: errors + warnings,
  };
}

/** "12/10" under a bar, "lundi 12 octobre" in its tooltip (days are calendar dates) */
export function chartDay(day: string, long = false): string {
  const date = new Date(`${day}T00:00:00Z`);
  return long ? dayLong.format(date) : dayShort.format(date);
}
