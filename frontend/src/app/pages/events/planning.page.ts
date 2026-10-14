import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { ClanEvent, EventTypeInfo } from '../../core/api/models';
import { RealtimeService } from '../../core/chat/realtime.service';
import { BranchesService } from '../../core/clan/branches.service';
import { EventsService } from '../../core/events/events.service';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';
import { addMonths, monthGrid, monthKey, parseMonth, planningDays } from './planning';

const monthFormat = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
const dayFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});
const timeFormat = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });

/** Planning of the clan: the events of a month, day by day. */
@Component({
  selector: 'app-planning-page',
  imports: [RouterLink, BranchCrest, Icon],
  templateUrl: './planning.page.html',
  styleUrl: './planning.page.scss',
})
export class PlanningPage {
  private readonly eventsService = inject(EventsService);
  private readonly router = inject(Router);
  protected readonly branches = inject(BranchesService);

  /** ?mois=2026-10 */
  readonly mois = input<string>();
  /** ?branche=id */
  readonly branche = input<string>();

  protected readonly weekdays = [
    'Lundi',
    'Mardi',
    'Mercredi',
    'Jeudi',
    'Vendredi',
    'Samedi',
    'Dimanche',
  ];
  protected readonly month = computed(() => parseMonth(this.mois()));
  protected readonly branchId = computed(() => Number(this.branche()) || null);
  protected readonly title = computed(() => {
    const label = monthFormat.format(this.month());
    return label.charAt(0).toUpperCase() + label.slice(1);
  });
  protected readonly events = signal<ClanEvent[]>([]);
  protected readonly loaded = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly types = signal<EventTypeInfo[]>([]);
  protected readonly canCreate = computed(() => this.types().some((t) => t.canCreate));
  protected readonly days = computed(() => planningDays(this.month(), this.events()));
  /** Days of the month with events, for the agenda shown on small screens */
  protected readonly agenda = computed(() =>
    this.days().filter((d) => d.inMonth && (d.events.length || d.today)),
  );
  protected readonly isThisMonth = computed(() => monthKey(this.month()) === monthKey(new Date()));

  constructor() {
    effect(() => {
      const month = this.month();
      const branch = this.branchId();
      untracked(() => void this.load(month, branch));
    });
    void this.branches.load().catch(() => undefined);
    void this.eventsService.types().then(
      (t) => this.types.set(t.types),
      () => undefined,
    );
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'events') {
          void this.load(this.month(), this.branchId(), true);
        }
      });
  }

  protected go(params: { mois?: string | null; branche?: number | null }): void {
    void this.router.navigate([], {
      queryParams: params,
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  protected shift(count: number): void {
    this.go({ mois: monthKey(addMonths(this.month(), count)) });
  }

  protected today(): void {
    this.go({ mois: null });
  }

  protected time(event: ClanEvent): string {
    return timeFormat.format(new Date(event.startsAt));
  }

  protected dayLabel(date: Date): string {
    const label = dayFormat.format(date);
    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  private async load(month: Date, branch: number | null, quiet = false): Promise<void> {
    if (!quiet) {
      this.loaded.set(false);
    }
    const { start, end } = monthGrid(month);
    try {
      this.events.set(await this.eventsService.between(start, end, branch));
      this.error.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger le planning.'));
    } finally {
      this.loaded.set(true);
    }
  }
}
