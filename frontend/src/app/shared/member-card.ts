import { Component, DestroyRef, computed, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationStart, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../core/auth/auth.service';
import { ChatPanelService } from '../core/chat/chat-panel.service';
import { MemberCardService } from '../core/members/member-card.service';
import { Avatar } from './avatar';
import { BranchCrest } from './branch-crest';
import { Icon } from './icon';
import { PresenceBadge } from './presence-badge';

const WIDTH_PX = 304;
const GAP_PX = 8;
/** Usual height, to put the card above the name near the bottom of the screen */
const HEIGHT_PX = 280;

const monthFormat = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });

/** The member card, opened by MemberCardDirective; rendered once, by the shell. */
@Component({
  selector: 'app-member-card',
  imports: [RouterLink, Avatar, BranchCrest, Icon, PresenceBadge],
  host: {
    '(document:keydown.escape)': 'cards.hide()',
    '(window:resize)': 'cards.hide()',
  },
  template: `
    @if (cards.open(); as open) {
      <div
        class="card member-card"
        role="dialog"
        [attr.aria-label]="'Carte de membre' + (open.card ? ' de ' + open.card.displayName : '')"
        [style.top.px]="position().top"
        [style.bottom.px]="position().bottom"
        [style.left.px]="position().left"
        (pointerenter)="cards.keep()"
        (pointerleave)="cards.hideSoon()"
      >
        @if (open.card; as c) {
          <header>
            <app-avatar [member]="c" [size]="56" />
            <div class="who">
              <strong class="name">{{ c.displayName }}</strong>
              <span class="meta"
                >{{ c.role === 'leader' ? 'Dirigeant' : 'Membre' }} · {{ c.username }}</span
              >
              <app-presence-badge [userId]="c.id" [withText]="true" />
            </div>
          </header>

          @if (c.branches.length) {
            <ul class="branches" aria-label="Branches">
              @for (b of c.branches; track b.id) {
                <li>
                  @if (b.crest) {
                    <app-branch-crest [crest]="b.crest" [size]="22" />
                  } @else {
                    <app-icon name="branch" />
                  }
                  {{ b.name }}
                </li>
              }
            </ul>
          } @else {
            <p class="meta none">Membre d'aucune branche.</p>
          }

          <dl class="stats">
            <div>
              <dt><app-icon name="games" /> Jeux</dt>
              <dd>{{ c.stats.games }}</dd>
            </div>
            <div>
              <dt><app-icon name="news" /> News</dt>
              <dd>{{ c.stats.news }}</dd>
            </div>
            <div>
              <dt><app-icon name="branch" /> Branches</dt>
              <dd>{{ c.stats.branches }}</dd>
            </div>
          </dl>

          <footer>
            <span class="meta"
              >Membre depuis {{ since(c.memberSince) }}
              @if (c.discordUsername) {
                · <app-icon name="discord" /> {{ c.discordUsername }}
              }
            </span>
            <div class="actions">
              <a [routerLink]="['/members', c.id]" (click)="cards.hide()">Voir la fiche</a>
              @if (c.id !== myId()) {
                <button type="button" class="link" (click)="write(c.id)">
                  <app-icon name="write" /> Écrire
                </button>
              }
            </div>
          </footer>
        } @else if (open.error) {
          <p class="meta">Carte indisponible.</p>
        } @else {
          <p class="meta loading" aria-live="polite">Chargement…</p>
        }
      </div>
    }
  `,
  styleUrl: './member-card.scss',
})
export class MemberCard {
  protected readonly cards = inject(MemberCardService);
  private readonly panel = inject(ChatPanelService);
  private readonly auth = inject(AuthService);
  protected readonly myId = computed(() => this.auth.user()?.id ?? null);

  /** Under the name, or above it near the bottom of the screen; always within the screen */
  protected readonly position = computed(() => {
    const anchor = this.cards.open()?.anchor;
    if (!anchor) {
      return { top: null, bottom: null, left: 0 };
    }
    const left = Math.max(GAP_PX, Math.min(anchor.left, window.innerWidth - WIDTH_PX - GAP_PX));
    return anchor.bottom + GAP_PX + HEIGHT_PX > window.innerHeight && anchor.top > HEIGHT_PX
      ? { top: null, bottom: window.innerHeight - anchor.top + GAP_PX, left }
      : { top: anchor.bottom + GAP_PX, bottom: null, left };
  });

  constructor() {
    // Pages scroll in their own container: listen to every scroll, which moves the name away
    const hide = () => this.cards.hide();
    document.addEventListener('scroll', hide, { capture: true, passive: true });
    inject(DestroyRef).onDestroy(() =>
      document.removeEventListener('scroll', hide, { capture: true }),
    );

    inject(Router)
      .events.pipe(
        filter((e) => e instanceof NavigationStart),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.cards.hide());
  }

  protected since(iso: string): string {
    return monthFormat.format(new Date(iso));
  }

  protected write(memberId: number): void {
    this.cards.hide();
    this.panel.open(memberId);
  }
}
