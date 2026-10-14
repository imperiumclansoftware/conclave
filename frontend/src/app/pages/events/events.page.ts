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
import { EventCard } from './event-card';
import { byDay } from './event-format';

/** Events of the branches: upcoming or past, all branches or one. */
@Component({
  selector: 'app-events-page',
  imports: [RouterLink, BranchCrest, Icon, EventCard],
  templateUrl: './events.page.html',
  styleUrl: './events.page.scss',
})
export class EventsPage {
  private readonly eventsService = inject(EventsService);
  private readonly router = inject(Router);
  protected readonly branches = inject(BranchesService);

  /** ?periode=passes: the past events */
  readonly periode = input<string>();
  /** ?branche=id */
  readonly branche = input<string>();

  protected readonly past = computed(() => this.periode() === 'passes');
  protected readonly branchId = computed(() => Number(this.branche()) || null);
  protected readonly events = signal<ClanEvent[]>([]);
  protected readonly loaded = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal<number | null>(null);
  protected readonly types = signal<EventTypeInfo[]>([]);
  protected readonly canCreate = computed(() => this.types().some((t) => t.canCreate));
  protected readonly days = computed(() => byDay(this.events()));

  constructor() {
    effect(() => {
      const past = this.past();
      const branch = this.branchId();
      untracked(() => void this.load(past, branch));
    });
    void this.branches.load().catch(() => undefined);
    void this.eventsService.types().then(
      (t) => this.types.set(t.types),
      () => undefined,
    );

    // Created, updated, joined by somebody else: the list follows
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'events') {
          void this.load(this.past(), this.branchId(), true);
        }
      });
  }

  protected filter(params: { periode?: string | null; branche?: number | null }): void {
    void this.router.navigate([], {
      queryParams: params,
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  protected async participate(event: ClanEvent, join: boolean): Promise<void> {
    this.busy.set(event.id);
    this.error.set(null);
    try {
      const updated = join
        ? await this.eventsService.join(event.id)
        : await this.eventsService.leave(event.id);
      this.events.update((list) => list.map((e) => (e.id === updated.id ? updated : e)));
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.busy.set(null);
    }
  }

  private async load(past: boolean, branch: number | null, quiet = false): Promise<void> {
    if (!quiet) {
      this.loaded.set(false);
    }
    try {
      this.events.set(await this.eventsService.list(past ? 'past' : 'upcoming', branch));
      this.error.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les événements.'));
    } finally {
      this.loaded.set(true);
    }
  }
}
