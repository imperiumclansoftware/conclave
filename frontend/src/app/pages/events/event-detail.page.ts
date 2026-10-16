import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { ClanEvent } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { RealtimeService } from '../../core/chat/realtime.service';
import { ConsentService } from '../../core/consent/consent.service';
import { EventsService } from '../../core/events/events.service';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { filmLink } from '../films/film-format';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { dayLabel, timeRange } from './event-format';

@Component({
  selector: 'app-event-detail-page',
  imports: [RouterLink, Avatar, BranchCrest, Icon, MemberCardDirective],
  templateUrl: './event-detail.page.html',
  styleUrl: './event-detail.page.scss',
})
export class EventDetailPage {
  protected readonly filmLink = filmLink;

  private readonly eventsService = inject(EventsService);
  private readonly router = inject(Router);
  protected readonly auth = inject(AuthService);
  protected readonly consent = inject(ConsentService);

  /** Route parameter */
  readonly id = input.required<string>();

  protected readonly event = signal<ClanEvent | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal(false);
  protected readonly day = computed(() => (this.event() ? dayLabel(this.event()!.startsAt) : ''));
  protected readonly time = computed(() => (this.event() ? timeRange(this.event()!) : ''));
  protected readonly picture = computed(() => {
    const e = this.event();
    return this.consent.src(e?.film?.posterUrl ?? e?.game?.coverUrl ?? null);
  });
  protected readonly paragraphs = computed(() =>
    (this.event()?.description ?? '')
      .split(/\n{2,}/)
      .map((p) => p.trim())
      .filter(Boolean),
  );

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => void this.load(id));
    });
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'events' && this.event()) {
          void this.load(this.event()!.id, true);
        }
      });
  }

  protected async participate(join: boolean): Promise<void> {
    const event = this.event();
    if (!event) {
      return;
    }
    await this.run(async () =>
      this.event.set(
        join ? await this.eventsService.join(event.id) : await this.eventsService.leave(event.id),
      ),
    );
  }

  protected async remove(): Promise<void> {
    const event = this.event();
    if (!event || !confirm(`Supprimer l’événement « ${event.title} » ?`)) {
      return;
    }
    await this.run(async () => {
      await this.eventsService.remove(event.id);
      await this.router.navigate(['/evenements']);
    });
  }

  private async run(task: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    try {
      await task();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }

  private async load(id: number, quiet = false): Promise<void> {
    try {
      this.event.set(await this.eventsService.get(id));
    } catch (e) {
      if (!quiet) {
        this.error.set(apiErrorMessage(e, 'Événement introuvable.'));
      } else {
        // Deleted by somebody else
        this.event.set(null);
        this.error.set('Cet événement a été supprimé.');
      }
    }
  }
}
