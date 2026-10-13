import { Component, computed, input } from '@angular/core';
import { Faction } from '../core/theme/theme.service';

let nextId = 0;

/**
 * Faction emblem (original artwork): a hooded Assassin, or the Maltese cross of the Templar order.
 * - a faction: that emblem;
 * - "split": half Assassin, half Templar (e.g. "follow my device");
 * - none: both are rendered and CSS shows the active one.
 */
@Component({
  selector: 'app-emblem',
  template: `
    <svg viewBox="0 0 64 64" [attr.width]="size()" [attr.height]="size()" aria-hidden="true">
      @if (split()) {
        <clipPath [attr.id]="ids.left"><rect x="-8" y="-8" width="40" height="80" /></clipPath>
        <clipPath [attr.id]="ids.right"><rect x="32" y="-8" width="40" height="80" /></clipPath>
      }

      @if (faction() !== 'templar') {
        <g
          [class.only-assassin]="!faction()"
          [attr.clip-path]="split() ? 'url(#' + ids.left + ')' : null"
        >
          <circle cx="32" cy="32" r="29.5" fill="#a3171d" stroke="#231c15" stroke-width="2.5" />
          <circle cx="32" cy="32" r="26" fill="none" stroke="#fbf7ee" stroke-width="0.8" />
          <clipPath [attr.id]="ids.hood"><circle cx="32" cy="32" r="25.4" /></clipPath>
          <g
            [attr.clip-path]="'url(#' + ids.hood + ')'"
            stroke="#231c15"
            stroke-width="1"
            stroke-linejoin="round"
          >
            <path d="M6 64 C9 50 17 44.5 25 43 L39 43 C47 44.5 55 50 58 64 Z" fill="#e9e0cc" />
            <path d="M32 50 L27 43.5 L37 43.5 Z" fill="#a3171d" />
            <path
              d="M32 8 C23.5 12 18.5 21.5 18.5 32 C18.5 40.5 23 46.5 32 50 C41 46.5 45.5 40.5 45.5 32 C45.5 21.5 40.5 12 32 8 Z"
              fill="#fbf7ee"
            />
            <path
              d="M32 29 L27.6 24.6 C24.8 28.4 23.6 33.2 24.2 38.2 C26.2 42.4 28.8 44.6 32 45.6 C35.2 44.6 37.8 42.4 39.8 38.2 C40.4 33.2 39.2 28.4 36.4 24.6 Z"
              fill="#1b1410"
            />
            <path
              d="M32 8 C30.6 14 30.4 20 31 26 M32 8 C33.4 14 33.6 20 33 26"
              fill="none"
              stroke-width="0.6"
              opacity=".6"
            />
          </g>
        </g>
      }

      @if (faction() !== 'assassin') {
        <g
          [class.only-templar]="!faction()"
          [attr.clip-path]="split() ? 'url(#' + ids.right + ')' : null"
        >
          <circle cx="32" cy="32" r="29.5" fill="#181413" stroke="#d4af37" stroke-width="2.5" />
          <circle
            cx="32"
            cy="32"
            r="25.5"
            fill="none"
            stroke="#d4af37"
            stroke-width="0.8"
            stroke-dasharray="1.5 1.5"
          />
          <path
            d="M32.0 31.2 L20.8 9.8 L32.0 17.4 L43.2 9.8 Z M32.8 32.0 L54.2 20.8 L46.6 32.0 L54.2 43.2 Z M32.0 32.8 L43.2 54.2 L32.0 46.6 L20.8 54.2 Z M31.2 32.0 L9.8 43.2 L17.4 32.0 L9.8 20.8 Z"
            stroke-linejoin="round"
            fill="#b3121b"
            stroke="#d4af37"
            stroke-width="1"
          />
        </g>
      }

      @if (split()) {
        <line x1="32" y1="1.5" x2="32" y2="62.5" stroke="#d4af37" stroke-width="1.6" />
      }
    </svg>
  `,
  styles: `
    :host {
      display: inline-flex;
      flex: none;
    }
    svg {
      display: block;
      filter: drop-shadow(0 2px 3px rgb(0 0 0 / 0.25));
    }
  `,
})
export class Emblem {
  readonly size = input(32);
  readonly faction = input<Faction | 'split'>();

  protected readonly split = computed(() => this.faction() === 'split');

  /** SVG ids must be unique in the page */
  protected readonly ids = (() => {
    const id = nextId++;
    return { hood: `emblem-hood-${id}`, left: `emblem-left-${id}`, right: `emblem-right-${id}` };
  })();
}
