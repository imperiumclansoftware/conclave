import { Component, computed, inject, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ClanEvent } from '../../core/api/models';
import { ConsentService } from '../../core/consent/consent.service';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { timeRange } from './event-format';

/** Shown participants; the others are counted */
const SHOWN_PARTICIPANTS = 6;

/** An event in a list: branch, time, specifics, participants and the "I come" button. */
@Component({
  selector: 'app-event-card',
  imports: [RouterLink, Avatar, BranchCrest, Icon, MemberCardDirective],
  templateUrl: './event-card.html',
  styleUrl: './event-card.scss',
})
export class EventCard {
  protected readonly consent = inject(ConsentService);

  readonly event = input.required<ClanEvent>();
  readonly busy = input(false);
  /** true: join, false: leave */
  readonly participate = output<boolean>();

  protected readonly time = computed(() => timeRange(this.event()));
  protected readonly shown = computed(() => this.event().participants.slice(0, SHOWN_PARTICIPANTS));
  protected readonly more = computed(() =>
    Math.max(0, this.event().participants.length - SHOWN_PARTICIPANTS),
  );
  /** Picture of the event: the game cover or the film poster */
  protected readonly picture = computed(() =>
    this.consent.src(this.event().film?.posterUrl ?? this.event().game?.coverUrl ?? null),
  );
}
