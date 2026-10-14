import { Component, DestroyRef, computed, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { News } from '../../core/api/models';
import { NewsCover } from '../../shared/news-cover';
import { excerpt, newsDate } from '../news/news-format';
import { slideIndex } from './home';

/** Time each news stays on screen while the carousel plays */
const SLIDE_MS = 8000;
/** Horizontal move, in pixels, that turns a touch into a swipe */
const SWIPE_PX = 50;

/**
 * The latest news, one at a time. Plays by itself unless the user prefers reduced motion,
 * pauses while hovered or focused, and can be paused, browsed with the arrows, the dots or a
 * swipe. Hidden slides are inert, so that only the shown one is reachable with the keyboard.
 */
@Component({
  selector: 'app-news-carousel',
  imports: [RouterLink, NewsCover],
  templateUrl: './news-carousel.html',
  styleUrl: './news-carousel.scss',
  host: {
    '(mouseenter)': 'hovered.set(true)',
    '(mouseleave)': 'hovered.set(false)',
    '(focusin)': 'focused.set(true)',
    '(focusout)': 'onFocusOut($event)',
  },
})
export class NewsCarousel {
  readonly news = input.required<News[]>();
  readonly clanName = input('');

  protected readonly index = signal(0);
  /** Paused by the user (or from the start when they prefer reduced motion) */
  protected readonly paused = signal(matchMedia('(prefers-reduced-motion: reduce)').matches);
  protected readonly hovered = signal(false);
  protected readonly focused = signal(false);
  protected readonly playing = computed(
    () => !this.paused() && !this.hovered() && !this.focused() && this.news().length > 1,
  );
  /** Slide shown, kept in range when the list shrinks */
  protected readonly current = computed(() => Math.min(this.index(), this.news().length - 1));

  protected readonly date = newsDate;
  protected readonly excerpt = excerpt;

  private swipeStart: number | null = null;

  constructor() {
    const timer = setInterval(() => {
      if (this.playing()) {
        this.go(1);
      }
    }, SLIDE_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(timer));
  }

  protected go(step: number): void {
    this.index.set(slideIndex(this.current(), step, this.news().length));
  }

  protected show(index: number): void {
    this.index.set(index);
  }

  protected onFocusOut(event: FocusEvent): void {
    const host = event.currentTarget as HTMLElement;
    if (!host.contains(event.relatedTarget as Node | null)) {
      this.focused.set(false);
    }
  }

  protected onPointerDown(event: PointerEvent): void {
    this.swipeStart = event.pointerType === 'mouse' ? null : event.clientX;
  }

  protected onPointerUp(event: PointerEvent): void {
    if (this.swipeStart === null) {
      return;
    }
    const dx = event.clientX - this.swipeStart;
    this.swipeStart = null;
    if (Math.abs(dx) >= SWIPE_PX) {
      this.go(dx < 0 ? 1 : -1);
    }
  }
}
