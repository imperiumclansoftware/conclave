import { Component, DestroyRef, computed, inject, output, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminService } from '../../core/admin/admin.service';
import { apiErrorMessage } from '../../core/api/api-error';
import { SystemReport } from '../../core/api/models';
import { Icon } from '../../shared/icon';
import { STATE_LABELS, chartDay, formatBytes, niceMax, overallState } from './admin-system-format';

/** The dashboard follows the installation on its own while it is shown */
const REFRESH_MS = 30_000;

const timeFormat = new Intl.DateTimeFormat('fr-FR', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
});

/**
 * Dashboard of the installation: state of each service, activity of the clan, versions,
 * database and storage.
 */
@Component({
  selector: 'app-admin-system',
  imports: [Icon],
  templateUrl: './admin-system.html',
  styleUrl: './admin-system.scss',
})
export class AdminSystem {
  private readonly admin = inject(AdminService);

  /** Opens another tab of the administration (the sign-ups waiting, a misconfigured service) */
  readonly openTab = output<string>();

  protected readonly report = signal<SystemReport | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly loading = signal(false);
  /** Bar under the pointer or the keyboard focus, for its tooltip */
  protected readonly hovered = signal<number | null>(null);

  protected readonly stateLabels = STATE_LABELS;
  protected readonly bytes = formatBytes;
  protected readonly day = chartDay;

  protected readonly overall = computed(() => {
    const report = this.report();
    return report ? overallState(report.services) : null;
  });
  protected readonly updatedAt = computed(() => {
    const report = this.report();
    return report ? timeFormat.format(new Date(report.generatedAt)) : '';
  });
  protected readonly days = computed(() => this.report()?.activity.messagesByDay ?? []);
  protected readonly scaleMax = computed(() =>
    niceMax(Math.max(0, ...this.days().map((d) => d.count))),
  );
  protected readonly total = computed(() => this.days().reduce((sum, d) => sum + d.count, 0));
  protected readonly diskUsed = computed(() => {
    const storage = this.report()?.storage;
    if (!storage?.diskTotalBytes || storage.diskFreeBytes === null) {
      return null;
    }
    const used = storage.diskTotalBytes - storage.diskFreeBytes;
    return { used, percent: Math.round((used / storage.diskTotalBytes) * 100) };
  });

  /** Headline figures; the sign-ups waiting lead to their tab */
  protected readonly tiles = computed(() => {
    const a = this.report()?.activity;
    if (!a) {
      return [];
    }
    const tile = (value: number, one: string, many: string, tab: string | null = null) => ({
      value,
      label: value > 1 ? many : one,
      tab: value > 0 ? tab : null,
      attention: tab !== null && value > 0,
    });
    return [
      tile(a.members, 'membre actif', 'membres actifs'),
      tile(a.online, 'en ligne', 'en ligne'),
      tile(a.pendingSignups, 'demande en attente', 'demandes en attente', 'signups'),
      tile(a.messages24h, 'message sur 24 h', 'messages sur 24 h'),
      tile(a.upcomingEvents, 'événement à venir', 'événements à venir'),
      tile(a.news, 'news', 'news'),
      tile(a.games, 'jeu en ludothèque', 'jeux en ludothèque'),
      tile(a.films, 'film ou série', 'films et séries'),
    ];
  });

  /** Where to fix each service */
  protected readonly serviceTabs: Record<string, string> = {
    url: 'general',
    mail: 'mail',
    discord: 'discord',
    rawg: 'catalog',
    tmdb: 'catalog',
  };

  constructor() {
    void this.load();
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') {
        void this.load();
      }
    }, REFRESH_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(timer));
  }

  protected async load(): Promise<void> {
    this.loading.set(true);
    try {
      this.report.set(await firstValueFrom(this.admin.system()));
      this.error.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de lire l’état du système.'));
    } finally {
      this.loading.set(false);
    }
  }

  protected barHeight(count: number): string {
    return `${(count / this.scaleMax()) * 100}%`;
  }

  protected plural(count: number, one: string, many: string): string {
    return `${count.toLocaleString('fr-FR')} ${count > 1 ? many : one}`;
  }
}
