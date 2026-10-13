import { Component, computed, input } from '@angular/core';

let nextId = 0;

interface Palette {
  faction: 'assassin' | 'templar';
  from: string;
  to: string;
  ray: string;
  rayOpacity: number;
  ornament: string;
  frame: string;
  seal: string;
  sealRing: string;
  letter: string;
  ribbon: string;
  ribbonText: string;
}

const PALETTES: Palette[] = [
  {
    faction: 'assassin',
    from: '#f6eedb',
    to: '#dcc89c',
    ray: '#a3171d',
    rayOpacity: 0.07,
    ornament: '#a3171d',
    frame: '#a3171d',
    seal: '#fbf7ee',
    sealRing: '#231c15',
    letter: '#a3171d',
    ribbon: '#a3171d',
    ribbonText: '#fbf7ee',
  },
  {
    faction: 'templar',
    from: '#2a201d',
    to: '#0b0908',
    ray: '#d4af37',
    rayOpacity: 0.08,
    ornament: '#d4af37',
    frame: '#d4af37',
    seal: '#181413',
    sealRing: '#d4af37',
    letter: '#d4af37',
    ribbon: '#b3121b',
    ribbonText: '#f6e7c1',
  },
];

/** Marks of the factions, as on the branch crests (64 × 64 drawing) */
const ASSASSIN_MARK =
  'M32 7.6 C27.6 8.4 24.6 11.5 23.4 16.6 L26.6 16.6 C27.4 13.4 29.3 11.5 32 11 C34.7 11.5 36.6 13.4 37.4 16.6 L40.6 16.6 C39.4 11.5 36.4 8.4 32 7.6 Z M32 12.7 L30.2 16.6 H33.8 Z';
const TEMPLAR_CROSS =
  'M32 31.2 L20.8 9.8 L32 17.4 L43.2 9.8 Z M32.8 32 L54.2 20.8 L46.6 32 L54.2 43.2 Z M32 32.8 L43.2 54.2 L32 46.6 L20.8 54.2 Z M31.2 32 L9.8 43.2 L17.4 32 L9.8 20.8 Z';

const WIDTH = 1200;
const HEIGHT = 630;

/**
 * Default visual of a news without picture (original artwork, generated in the browser): a seal
 * with the initial of the title, rays and ornaments in the colours of the active faction. The
 * composition derives from the seed, so that every news gets its own visual and keeps it.
 */
@Component({
  selector: 'app-news-cover',
  template: `
    <svg
      [attr.viewBox]="'0 0 ' + width + ' ' + height"
      preserveAspectRatio="xMidYMid slice"
      role="img"
      [attr.aria-label]="'Illustration : ' + title()"
    >
      @let c = composition();
      @for (p of palettes; track p.faction) {
        <g [attr.class]="'only-' + p.faction">
          <defs>
            <linearGradient
              [attr.id]="ids.gradient + p.faction"
              [attr.gradientTransform]="'rotate(' + c.gradientAngle + ' .5 .5)'"
            >
              <stop offset="0" [attr.stop-color]="p.from" />
              <stop offset="1" [attr.stop-color]="p.to" />
            </linearGradient>
          </defs>
          <rect
            [attr.width]="width"
            [attr.height]="height"
            [attr.fill]="'url(#' + ids.gradient + p.faction + ')'"
          />

          <!-- Rays from a point that changes with each news -->
          <g [attr.fill]="p.ray" [attr.opacity]="p.rayOpacity">
            @for (ray of c.rays; track $index) {
              <path [attr.d]="ray" />
            }
          </g>

          <!-- Faction's mark as a watermark -->
          <path
            [attr.d]="p.faction === 'assassin' ? assassinMark : templarCross"
            [attr.fill]="p.ornament"
            opacity="0.1"
            [attr.transform]="c.watermark"
          />

          @for (o of c.ornaments; track $index) {
            <path
              [attr.d]="p.faction === 'assassin' ? diamond : smallCross"
              [attr.fill]="p.ornament"
              [attr.opacity]="o.opacity"
              [attr.transform]="
                'translate(' +
                o.x +
                ' ' +
                o.y +
                ') scale(' +
                o.scale +
                ') rotate(' +
                o.rotation +
                ')'
              "
            />
          }

          <rect
            x="22"
            y="22"
            [attr.width]="width - 44"
            [attr.height]="height - 44"
            fill="none"
            [attr.stroke]="p.frame"
            stroke-width="3"
            opacity="0.55"
          />
          <rect
            x="34"
            y="34"
            [attr.width]="width - 68"
            [attr.height]="height - 68"
            fill="none"
            [attr.stroke]="p.frame"
            stroke-width="1"
            opacity="0.4"
          />

          <!-- Seal with the illuminated initial -->
          <g [attr.transform]="'translate(' + c.sealX + ' 285)'">
            <circle r="128" [attr.fill]="p.sealRing" opacity="0.18" />
            <circle r="112" [attr.fill]="p.seal" [attr.stroke]="p.sealRing" stroke-width="5" />
            <circle
              r="98"
              fill="none"
              [attr.stroke]="p.sealRing"
              stroke-width="1.5"
              stroke-dasharray="4 6"
            />
            <text y="52" text-anchor="middle" font-size="150" [attr.fill]="p.letter" class="letter">
              {{ initial() }}
            </text>
          </g>

          <!-- Ribbon with the clan's name -->
          <g [attr.transform]="'translate(' + c.sealX + ' 448)'">
            <path
              d="M-250 -10 L-210 -34 H210 L250 -10 L210 14 L215 34 H-215 L-210 14 Z"
              [attr.fill]="p.ribbon"
              opacity="0.92"
            />
            <path
              d="M-205 -24 H205 M-205 24 H205"
              [attr.stroke]="p.ribbonText"
              stroke-width="1.2"
              opacity="0.6"
            />
            <text
              y="9"
              text-anchor="middle"
              font-size="26"
              letter-spacing="6"
              [attr.fill]="p.ribbonText"
              class="label"
            >
              {{ label() }}
            </text>
          </g>
        </g>
      }
    </svg>
  `,
  styles: `
    :host {
      display: block;
      overflow: hidden;
      background: var(--surface-muted);
    }
    svg {
      display: block;
      width: 100%;
      height: 100%;
    }
    text {
      font-family: var(--font-display);
      font-weight: 700;
      /* Inherited from the page, it makes Chromium ignore the scale of the drawing */
      font-size-adjust: none;
    }
  `,
})
export class NewsCover {
  /** Changes the composition (the title of the news) */
  readonly seed = input.required<string | number>();
  readonly title = input('');
  readonly clanName = input('');

  protected readonly width = WIDTH;
  protected readonly height = HEIGHT;
  protected readonly palettes = PALETTES;
  protected readonly assassinMark = ASSASSIN_MARK;
  protected readonly templarCross = TEMPLAR_CROSS;
  protected readonly diamond = 'M0 -10 L6 0 L0 10 L-6 0 Z';
  protected readonly smallCross = 'M-2 -11 H2 V-2 H11 V2 H2 V11 H-2 V2 H-11 V-2 H-2 Z';
  /** SVG ids must be unique in the page */
  protected readonly ids = { gradient: `news-cover-${nextId++}-` };

  protected readonly initial = computed(() => {
    const letter =
      this.title()
        .trim()
        .match(/\p{L}|\p{N}/u)?.[0] ?? '✦';
    return letter.toLocaleUpperCase('fr');
  });
  protected readonly label = computed(() =>
    (this.clanName() || 'Chronique').toLocaleUpperCase('fr').slice(0, 28),
  );

  protected readonly composition = computed(() => {
    const random = mulberry32(hash(String(this.seed())));
    const sealX = 600 + Math.round((random() - 0.5) * 260);

    // Rays: alternate wedges around a focal point
    const fx = Math.round(random() * WIDTH);
    const fy = random() < 0.5 ? -40 : HEIGHT + 40;
    const count = 14 + Math.floor(random() * 8);
    const start = random() * Math.PI;
    const rays: string[] = [];
    for (let i = 0; i < count; i += 2) {
      const a1 = start + (i / count) * Math.PI * 2;
      const a2 = start + ((i + 1) / count) * Math.PI * 2;
      const r = 2000;
      rays.push(
        `M${fx} ${fy} L${(fx + Math.cos(a1) * r).toFixed(0)} ${(fy + Math.sin(a1) * r).toFixed(0)} L${(fx + Math.cos(a2) * r).toFixed(0)} ${(fy + Math.sin(a2) * r).toFixed(0)} Z`,
      );
    }

    // Ornaments, away from the seal
    const ornaments: { x: number; y: number; scale: number; rotation: number; opacity: number }[] =
      [];
    for (let tries = 0; ornaments.length < 16 && tries < 200; tries++) {
      const x = 70 + random() * (WIDTH - 140);
      const y = 70 + random() * (HEIGHT - 140);
      if (Math.hypot(x - sealX, y - 300) < 230) {
        continue;
      }
      ornaments.push({
        x: Math.round(x),
        y: Math.round(y),
        scale: +(0.6 + random() * 1.4).toFixed(2),
        rotation: Math.round(random() * 45),
        opacity: +(0.12 + random() * 0.25).toFixed(2),
      });
    }

    // Watermark on the side opposite to the seal
    const left = sealX > 600;
    const size = 9 + random() * 3;
    const watermark = `translate(${left ? 40 : WIDTH - 40 - 64 * size} ${Math.round(-60 + random() * 120)}) scale(${size.toFixed(2)})`;

    return { sealX, rays, ornaments, watermark, gradientAngle: Math.round(random() * 360) };
  });
}

/** FNV-1a */
function hash(text: string): number {
  let h = 2166136261;
  for (let i = 0; i < text.length; i++) {
    h ^= text.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

/** Small seeded generator: the same seed always draws the same visual */
function mulberry32(seed: number): () => number {
  let a = seed;
  return () => {
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
