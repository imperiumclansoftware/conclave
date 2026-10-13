import { AppStatus, DiscordStatus, Presence } from '../api/models';

const APP_LABELS: Record<AppStatus, string> = {
  online: 'En ligne',
  away: 'Absent',
  offline: 'Hors ligne',
};

const DISCORD_LABELS: Record<DiscordStatus, string> = {
  online: 'En ligne',
  idle: 'Inactif',
  dnd: 'Ne pas déranger',
  offline: 'Hors ligne',
};

export function appStatusLabel(status: AppStatus): string {
  return APP_LABELS[status];
}

export function discordStatusLabel(status: DiscordStatus): string {
  return DISCORD_LABELS[status];
}

/** One-line summary: "En ligne · Discord : Ne pas déranger · Joue à Elden Ring". */
export function presenceSummary(presence: Presence | null): string {
  if (!presence) {
    return '';
  }
  const parts = [`Ici : ${APP_LABELS[presence.app.status].toLowerCase()}`];
  if (presence.discord) {
    parts.push(`Discord : ${DISCORD_LABELS[presence.discord.status].toLowerCase()}`);
    if (presence.discord.activity) {
      parts.push(presence.discord.activity);
    }
  }
  return parts.join(' · ');
}
