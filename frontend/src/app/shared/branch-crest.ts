import { Component, computed, input } from '@angular/core';
import { BranchCrest as Crest } from '../core/api/models';

let nextId = 0;

interface Variant {
  faction: 'assassin' | 'templar';
  field: string;
  border: string;
  inner: string;
  innerDash: string | null;
  chief: string;
  chiefCharge: string;
  charge: string;
  outline: string;
}

/** Colours of each faction, as in the themes and the faction emblem */
const VARIANTS: Variant[] = [
  {
    faction: 'assassin',
    field: '#a3171d',
    border: '#231c15',
    inner: '#fbf7ee',
    innerDash: null,
    chief: '#fbf7ee',
    chiefCharge: '#a3171d',
    charge: '#fbf7ee',
    outline: '#231c15',
  },
  {
    faction: 'templar',
    field: '#181413',
    border: '#d4af37',
    inner: '#d4af37',
    innerDash: '1.6 1.4',
    chief: '#b3121b',
    chiefCharge: '#d4af37',
    charge: '#d4af37',
    outline: '#000',
  },
];

/** Charges outlined by a wider line drawn underneath */
const SWORDS = {
  blades: [
    'M18.9 54.2 L44 28.3 L47 27 L45.8 30.1 L20.7 56 Z',
    'M45.1 54.2 L20 28.3 L17 27 L18.2 30.1 L43.3 56 Z',
  ],
  guards: ['M22.7 57.9 L16.9 52.3', 'M41.3 57.9 L47.1 52.3'],
  grips: ['M19.8 55.1 L17 58', 'M44.2 55.1 L47 58'],
  pommels: [
    [16.2, 58.9],
    [47.8, 58.9],
  ],
};

/**
 * Coat of arms of a branch (original artwork): a heater shield whose chief bears the faction's mark,
 * in the colours of the Brotherhood (light theme) or of the Order (dark theme).
 * - players: a gamepad over two crossed swords;
 * - developers: "</>", the slash being a hidden blade;
 * - communicants: a herald's trumpet with its hanging banner;
 * - cinephiles: a clapperboard, its clapper open.
 */
@Component({
  selector: 'app-branch-crest',
  template: `
    <svg viewBox="0 0 64 72" [attr.width]="size()" [attr.height]="height()" aria-hidden="true">
      <clipPath [attr.id]="clipId"><path [attr.d]="shield" /></clipPath>
      @for (v of variants; track v.faction) {
        <g [attr.class]="'only-' + v.faction">
          <path [attr.d]="shield" [attr.fill]="v.field" />
          <!-- Chief: the faction's mark -->
          <g [attr.clip-path]="'url(#' + clipId + ')'">
            <rect x="0" y="0" width="64" height="20" [attr.fill]="v.chief" />
            <path d="M6 20 H58" [attr.stroke]="v.border" stroke-width="1" />
          </g>
          @if (v.faction === 'assassin') {
            <path
              d="M32 7.6 C27.6 8.4 24.6 11.5 23.4 16.6 L26.6 16.6 C27.4 13.4 29.3 11.5 32 11 C34.7 11.5 36.6 13.4 37.4 16.6 L40.6 16.6 C39.4 11.5 36.4 8.4 32 7.6 Z M32 12.7 L30.2 16.6 H33.8 Z"
              [attr.fill]="v.chiefCharge"
            />
          } @else {
            <path
              transform="translate(32 12.8) scale(0.24) translate(-32 -32)"
              [attr.fill]="v.chiefCharge"
              d="M32 31.2 L20.8 9.8 L32 17.4 L43.2 9.8 Z M32.8 32 L54.2 20.8 L46.6 32 L54.2 43.2 Z M32 32.8 L43.2 54.2 L32 46.6 L20.8 54.2 Z M31.2 32 L9.8 43.2 L17.4 32 L9.8 20.8 Z"
            />
          }

          <g stroke-linecap="round" stroke-linejoin="round">
            @switch (crest()) {
              @case ('players') {
                <g transform="translate(32 40) scale(0.8) translate(-32 -40)">
                  @for (i of [0, 1]; track i) {
                    <path
                      [attr.d]="swords.guards[i]"
                      [attr.stroke]="v.outline"
                      stroke-width="3.8"
                    />
                    <path [attr.d]="swords.grips[i]" [attr.stroke]="v.outline" stroke-width="3.4" />
                    <path
                      [attr.d]="swords.blades[i]"
                      [attr.fill]="v.charge"
                      [attr.stroke]="v.outline"
                      stroke-width="0.9"
                    />
                    <path [attr.d]="swords.guards[i]" [attr.stroke]="v.charge" stroke-width="2.2" />
                    <path [attr.d]="swords.grips[i]" [attr.stroke]="v.charge" stroke-width="1.8" />
                    <circle
                      [attr.cx]="swords.pommels[i][0]"
                      [attr.cy]="swords.pommels[i][1]"
                      r="1.7"
                      [attr.fill]="v.charge"
                      [attr.stroke]="v.outline"
                      stroke-width="0.9"
                    />
                  }
                </g>
                <path
                  d="M22.5 34 H41.5 C45 34 47 36.5 47.5 40 L48.8 47.5 C49.3 50.6 46 52.4 43.7 50.3 L39.5 46.5 H24.5 L20.3 50.3 C18 52.4 14.7 50.6 15.2 47.5 L16.5 40 C17 36.5 19 34 22.5 34 Z"
                  [attr.fill]="v.charge"
                  [attr.stroke]="v.outline"
                  stroke-width="1"
                />
                <path d="M24 37.4 V43.4 M21 40.4 H27" [attr.stroke]="v.field" stroke-width="2" />
                @for (b of buttons; track $index) {
                  <circle [attr.cx]="b[0]" [attr.cy]="b[1]" r="1.25" [attr.fill]="v.field" />
                }
              }
              @case ('communicants') {
                <g
                  transform="rotate(-14 32 40) translate(32 41) scale(0.84) translate(-32 -40)"
                  [attr.stroke]="v.outline"
                  stroke-width="0.9"
                >
                  <!-- Banner hanging from the trumpet -->
                  <path d="M19 38 H37 V52 L28 47.5 L19 52 Z" [attr.fill]="v.charge" />
                  <path
                    d="M28 40.4 L30.6 43 L28 45.6 L25.4 43 Z"
                    [attr.fill]="v.field"
                    stroke="none"
                  />
                  <!-- Trumpet: mouthpiece, tube, knob, bell -->
                  <path
                    d="M10 34.4 C11.6 34.4 12.6 35.2 13.5 35.8 V38.6 C12.6 39.2 11.6 40 10 40 Z"
                    [attr.fill]="v.charge"
                  />
                  <path d="M13.5 35.8 H40.5 V38.6 H13.5 Z" [attr.fill]="v.charge" />
                  <ellipse cx="25" cy="37.2" rx="1.7" ry="2.7" [attr.fill]="v.charge" />
                  <path
                    d="M40.5 35.8 C45 35.2 48.5 31.5 51 28 V50.4 C48.5 46.9 45 43.2 40.5 38.6 Z"
                    [attr.fill]="v.charge"
                  />
                  <path d="M51 29.5 V48.9" [attr.stroke]="v.field" stroke-width="1" />
                </g>
              }
              @case ('cinephiles') {
                <g [attr.stroke]="v.outline" stroke-width="0.9">
                  <!-- Board, with its striped band and the lines of the slate -->
                  <rect x="18" y="36.5" width="28" height="18" rx="1.5" [attr.fill]="v.charge" />
                  <path d="M18 42 H46" fill="none" />
                  <path
                    d="M23.5 36.5 H27.5 L24.5 42 H20.5 Z M31.5 36.5 H35.5 L32.5 42 H28.5 Z M39.5 36.5 H43.5 L40.5 42 H36.5 Z"
                    [attr.fill]="v.field"
                    stroke="none"
                  />
                  <path
                    d="M21.5 46.5 H42.5 M21.5 50.5 H36"
                    fill="none"
                    [attr.stroke]="v.field"
                    stroke-width="1.3"
                  />
                  <!-- Clapper, open on its hinge -->
                  <g transform="rotate(-16 18 35.5)">
                    <rect x="18" y="30" width="28" height="5.5" rx="1" [attr.fill]="v.charge" />
                    <path
                      d="M23.5 30 H27.5 L24.5 35.5 H20.5 Z M31.5 30 H35.5 L32.5 35.5 H28.5 Z M39.5 30 H43.5 L40.5 35.5 H36.5 Z"
                      [attr.fill]="v.field"
                      stroke="none"
                    />
                  </g>
                  <circle cx="19.6" cy="35.8" r="1.3" [attr.fill]="v.field" />
                </g>
              }
              @case ('developers') {
                <path
                  d="M25 30 L14 42 L25 54 M39 30 L50 42 L39 54"
                  fill="none"
                  [attr.stroke]="v.outline"
                  stroke-width="5.4"
                />
                <path
                  d="M25 30 L14 42 L25 54 M39 30 L50 42 L39 54"
                  fill="none"
                  [attr.stroke]="v.charge"
                  stroke-width="3.6"
                />
                <!-- Hidden blade -->
                <g transform="translate(32 42.5) scale(0.84) translate(-32 -42.5)">
                  <path d="M33.6 53.9 L24.9 51.4" [attr.stroke]="v.outline" stroke-width="3.8" />
                  <path d="M29.3 52.7 L28 57" [attr.stroke]="v.outline" stroke-width="3.4" />
                  <path
                    d="M28 52.3 L34.4 30 L36.5 27.5 L36.9 30.7 L30.5 53 Z"
                    [attr.fill]="v.charge"
                    [attr.stroke]="v.outline"
                    stroke-width="0.9"
                  />
                  <path d="M33.6 53.9 L24.9 51.4" [attr.stroke]="v.charge" stroke-width="2.2" />
                  <path d="M29.3 52.7 L28 57" [attr.stroke]="v.charge" stroke-width="1.8" />
                  <circle
                    cx="27.7"
                    cy="58.2"
                    r="1.7"
                    [attr.fill]="v.charge"
                    [attr.stroke]="v.outline"
                    stroke-width="0.9"
                  />
                </g>
              }
            }
          </g>

          <path
            [attr.d]="innerBorder"
            fill="none"
            [attr.stroke]="v.inner"
            stroke-width="0.9"
            [attr.stroke-dasharray]="v.innerDash"
          />
          <path
            [attr.d]="shield"
            fill="none"
            [attr.stroke]="v.border"
            stroke-width="2.4"
            stroke-linejoin="round"
          />
        </g>
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
export class BranchCrest {
  readonly crest = input.required<Crest>();
  /** Width in pixels */
  readonly size = input(40);

  protected readonly height = computed(() => Math.round((this.size() * 72) / 64));
  protected readonly variants = VARIANTS;
  protected readonly swords = SWORDS;
  protected readonly buttons = [
    [40, 37.7],
    [42.6, 40.3],
    [40, 42.9],
    [37.4, 40.3],
  ];
  protected readonly shield = 'M6 5 H58 V33 C58 51 47 62 32 68 C17 62 6 51 6 33 Z';
  /** Below the chief only, so as not to cross the faction's mark */
  protected readonly innerBorder = 'M9.5 20 V33 C9.5 49 19 59 32 64.4 C45 59 54.5 49 54.5 33 V20';
  /** SVG ids must be unique in the page */
  protected readonly clipId = `crest-clip-${nextId++}`;
}
