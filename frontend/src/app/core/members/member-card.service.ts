import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { MemberCard } from '../api/models';

/** Hover delays: no card for a pointer just passing by, time to reach the card */
const SHOW_DELAY_MS = 400;
const HIDE_DELAY_MS = 250;
/** Cards are reused for a while: hovering the same names again costs nothing */
const CACHE_MS = 2 * 60 * 1000;

export interface OpenCard {
  memberId: number;
  /** Position of the hovered name */
  anchor: DOMRect;
  card: MemberCard | null;
  error: boolean;
}

/** The member card shown when hovering a member's name (see MemberCardDirective). */
@Injectable({ providedIn: 'root' })
export class MemberCardService {
  private readonly http = inject(HttpClient);
  private readonly cache = new Map<number, { at: number; card: Promise<MemberCard> }>();
  private showTimer: ReturnType<typeof setTimeout> | undefined;
  private hideTimer: ReturnType<typeof setTimeout> | undefined;

  private readonly _open = signal<OpenCard | null>(null);
  readonly open = this._open.asReadonly();

  /** The pointer entered a member's name */
  show(memberId: number, anchor: Element): void {
    clearTimeout(this.hideTimer);
    clearTimeout(this.showTimer);
    if (this._open()?.memberId === memberId) {
      return;
    }
    // Load during the delay: the card is usually ready when it appears
    const card = this.load(memberId);
    this.showTimer = setTimeout(() => {
      const open: OpenCard = {
        memberId,
        anchor: anchor.getBoundingClientRect(),
        card: null,
        error: false,
      };
      this._open.set(open);
      card.then(
        (data) =>
          this._open.update((current) =>
            current?.memberId === memberId ? { ...current, card: data } : current,
          ),
        () =>
          this._open.update((current) =>
            current?.memberId === memberId ? { ...current, error: true } : current,
          ),
      );
    }, SHOW_DELAY_MS);
  }

  /** The pointer left the name or the card: it may still go to the card */
  hideSoon(): void {
    clearTimeout(this.showTimer);
    clearTimeout(this.hideTimer);
    this.hideTimer = setTimeout(() => this._open.set(null), HIDE_DELAY_MS);
  }

  /** The pointer is over the card */
  keep(): void {
    clearTimeout(this.hideTimer);
  }

  hide(): void {
    clearTimeout(this.showTimer);
    clearTimeout(this.hideTimer);
    this._open.set(null);
  }

  private load(memberId: number): Promise<MemberCard> {
    const cached = this.cache.get(memberId);
    if (cached && Date.now() - cached.at < CACHE_MS) {
      return cached.card;
    }
    const card = firstValueFrom(this.http.get<MemberCard>(`api/members/${memberId}/card`));
    this.cache.set(memberId, { at: Date.now(), card });
    card.catch(() => this.cache.delete(memberId));
    return card;
  }
}
