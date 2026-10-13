import { ChangeDetectionStrategy, Component, ViewEncapsulation } from '@angular/core';

interface Ember {
  x: string;
  size: string;
  duration: string;
  delay: string;
  drift: string;
}

/** Deterministic pseudo-random embers, so that the layout is stable across renders. */
const EMBERS: Ember[] = Array.from({ length: 18 }, (_, i) => {
  const r = (n: number) => (((Math.sin(i * 12.9898 + n * 78.233) * 43758.5453) % 1) + 1) % 1;
  return {
    x: `${(r(1) * 100).toFixed(1)}%`,
    size: `${(2 + r(2) * 2.5).toFixed(1)}px`,
    duration: `${(9 + r(3) * 9).toFixed(1)}s`,
    delay: `-${(r(4) * 18).toFixed(1)}s`,
    drift: `${((r(5) - 0.5) * 120).toFixed(0)}px`,
  };
});

/**
 * Fixed decorative background of the active faction (styles in src/styles/_backdrop.scss).
 * Both scenes are rendered; CSS shows the one matching the color scheme.
 */
@Component({
  selector: 'app-backdrop',
  encapsulation: ViewEncapsulation.None,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="backdrop" aria-hidden="true">
      <div class="bd-assassin only-assassin">
        <svg
          class="codex top"
          viewBox="0 0 400 400"
          fill="none"
          stroke="currentColor"
          stroke-width="1.2"
        >
          <circle cx="200" cy="200" r="150" />
          <circle cx="200" cy="200" r="106" stroke-dasharray="3 5" />
          <rect x="94" y="94" width="212" height="212" />
          <path d="M50 200 H350 M200 50 V350 M94 94 L306 306 M306 94 L94 306" />
          <path d="M200 50 L330 275 H70 Z" />
          <circle cx="200" cy="200" r="18" />
          <path d="M200 182 L206 200 L200 218 L194 200 Z" fill="currentColor" />
          <path
            d="M40 372 c12 -6 22 4 34 -2 s20 -8 32 0 s22 4 34 -2 M40 388 c14 -5 24 3 36 -1 s22 -6 34 1"
            stroke-width="0.8"
          />
          <path
            d="M262 30 c10 -5 18 3 28 -1 s16 -6 26 0 M262 44 c12 -4 20 2 30 -1"
            stroke-width="0.8"
          />
        </svg>
        <svg
          class="codex bottom"
          viewBox="0 0 400 400"
          fill="none"
          stroke="currentColor"
          stroke-width="1.2"
        >
          <path d="M60 300 C120 120 280 120 340 300" />
          <path d="M60 300 H340 M120 300 V196 M200 300 V160 M280 300 V196" />
          <circle cx="200" cy="160" r="40" stroke-dasharray="2 4" />
          <path d="M150 340 l50 -40 l50 40 M30 60 h120 M30 76 h90 M30 92 h110" stroke-width="0.8" />
          <circle cx="330" cy="80" r="42" />
          <path d="M330 38 V122 M288 80 H372" stroke-width="0.8" />
        </svg>
        <svg class="skyline" viewBox="0 0 1600 240" preserveAspectRatio="xMidYMax slice">
          <path
            class="far"
            d="M0 240 L0 171 L24 161 L48 171 L48 122 L64 103 L80 122 L80 126 L96 115 L111 126 L111 137 L138 126 L166 137 L166 136 L198 117 L229 136 L229 162 L264 162 L264 35 L261 35 L261 27 L301 27 L301 35 L298 35 L298 140 L314 127 L329 140 L329 173 L344 161 L359 173 L359 175 L421 175 L421 177 L460 177 L460 178 L486 167 L511 178 L511 183 L575 183 L575 136 L637 136 L637 144 L666 122 L694 144 L694 159 L714 145 L733 159 L733 127 L794 127 L794 181 L817 165 L840 181 L840 147 L859 130 L878 147 L878 50 L878 44 L881 44 L881 50 L884 50 L884 44 L887 44 L887 50 L890 50 L890 44 L893 44 L893 50 L896 50 L896 44 L899 44 L899 50 L902 50 L902 44 L905 44 L905 50 L908 50 L888 50 L888 20 L893 8 L898 20 L898 50 L908 50 L908 185 L962 185 L962 150 L994 135 L1026 150 L1026 127 L1092 127 L1092 156 L1108 136 L1125 156 L1125 183 L1157 183 L1157 133 L1189 117 L1221 133 L1221 146 L1256 129 L1291 146 L1291 169 L1341 169 L1341 154 L1362 141 L1382 154 L1382 35 L1379 35 L1379 27 L1419 27 L1419 35 L1416 35 L1416 140 L1469 140 L1469 133 L1488 119 L1507 133 L1507 135 L1543 135 L1543 145 L1597 145 L1597 171 L1618 159 L1639 171 L1600 240 Z"
          />
          <path
            class="near"
            d="M0 240 L0 178 L22 163 L43 178 L43 185 L109 185 L109 199 L167 199 L167 180 L198 160 L229 180 L229 201 L266 201 L266 191 L327 191 L327 205 L359 205 L359 166 L382 152 L406 166 L406 177 L464 177 L464 190 L519 190 L519 192 L537 180 L555 192 L555 120 L567 120 L567 100 C569 40 615 10 625 8 L625 -10 L628 -18 L631 -10 L631 8 C643 10 691 40 693 100 L693 120 L705 120 L705 202 L734 182 L764 202 L764 166 L819 166 L819 191 L879 191 L879 178 L906 163 L933 178 L933 198 L962 198 L962 171 L1000 171 L1000 179 L1032 159 L1064 179 L1064 175 L1105 175 L1105 208 L1128 188 L1151 208 L1151 50 L1151 44 L1154 44 L1154 50 L1157 50 L1157 44 L1160 44 L1160 50 L1163 50 L1163 44 L1166 44 L1166 50 L1169 50 L1169 44 L1172 44 L1172 50 L1175 50 L1175 44 L1178 44 L1178 50 L1181 50 L1161 50 L1161 20 L1166 8 L1171 20 L1171 50 L1181 50 L1181 210 L1210 199 L1239 210 L1239 206 L1266 190 L1293 206 L1293 208 L1320 189 L1347 208 L1347 191 L1377 191 L1377 183 L1400 173 L1422 183 L1422 215 L1446 196 L1469 215 L1469 213 L1531 213 L1531 176 L1554 155 L1577 176 L1577 194 L1592 182 L1607 194 L1600 240 Z"
          />
        </svg>
        <svg class="eagle" viewBox="0 0 72 24" fill="currentColor">
          <path
            d="M0 9 C8 3 15 1 22 5 C26 7 30 9 33 12 L36 10 L39 12 C42 9 46 7 50 5 C57 1 64 3 72 9 C64 7 57 8 51 11 C46 13 42 15 39 17 L36 22 L33 17 C30 15 26 13 21 11 C15 8 8 7 0 9 Z"
          />
        </svg>
      </div>

      <div class="bd-templar only-templar">
        <div class="glow"></div>
        <svg class="cross" viewBox="0 0 64 64">
          <circle cx="32" cy="32" r="30" fill="none" stroke="#d4af37" stroke-width="0.6" />
          <path
            d="M32.0 31.2 L20.8 9.8 L32.0 17.4 L43.2 9.8 Z M32.8 32.0 L54.2 20.8 L46.6 32.0 L54.2 43.2 Z M32.0 32.8 L43.2 54.2 L32.0 46.6 L20.8 54.2 Z M31.2 32.0 L9.8 43.2 L17.4 32.0 L9.8 20.8 Z"
            stroke-linejoin="round"
            fill="#b3121b"
          />
        </svg>
        @for (side of banners; track side) {
          <div class="banner" [class]="side">
            <svg viewBox="0 0 64 64">
              <path
                d="M32.0 31.2 L20.8 9.8 L32.0 17.4 L43.2 9.8 Z M32.8 32.0 L54.2 20.8 L46.6 32.0 L54.2 43.2 Z M32.0 32.8 L43.2 54.2 L32.0 46.6 L20.8 54.2 Z M31.2 32.0 L9.8 43.2 L17.4 32.0 L9.8 20.8 Z"
                stroke-linejoin="round"
                fill="#f2e6cc"
                stroke="#d4af37"
                stroke-width="1.5"
              />
            </svg>
          </div>
        }
        @for (ember of embers; track $index) {
          <span
            class="ember"
            [style.--x]="ember.x"
            [style.--size]="ember.size"
            [style.--duration]="ember.duration"
            [style.--delay]="ember.delay"
            [style.--drift]="ember.drift"
          ></span>
        }
      </div>
    </div>
  `,
})
export class Backdrop {
  protected readonly banners = ['left', 'right'];
  protected readonly embers = EMBERS;
}
