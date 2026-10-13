import { DOCUMENT, Injectable, effect, inject, signal } from '@angular/core';

/** Light scheme: Assassins. Dark scheme: Templars. */
export type Faction = 'assassin' | 'templar';
type Scheme = 'light' | 'dark';

const STORAGE_KEY = 'color-scheme';

/**
 * Lets the user pick a side against their system color scheme. The choice is stored as
 * "light" or "dark" and applied through <meta name="color-scheme"> (index.html applies it
 * before the first paint); styles only depend on that meta, never on this service.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  private readonly meta = this.document.querySelector<HTMLMetaElement>('meta[name="color-scheme"]');
  private readonly systemDark = this.document.defaultView?.matchMedia(
    '(prefers-color-scheme: dark)',
  );

  private readonly _faction = signal<Faction>(this.resolve());
  readonly faction = this._faction.asReadonly();

  constructor() {
    this.systemDark?.addEventListener('change', () => this._faction.set(this.resolve()));
    // Keep tabs in sync
    this.document.defaultView?.addEventListener('storage', (event) => {
      if (event.key === STORAGE_KEY) {
        this.apply(readStored());
      }
    });
    effect(() => this.updateFavicon(this._faction()));
  }

  /**
   * Switches to the other faction. Picking the side matching the system scheme removes the
   * stored override, so that the app follows the system again.
   */
  /** Switches to the other faction and returns it (applied asynchronously when animated). */
  toggle(origin?: { x: number; y: number }): Faction {
    const target: Scheme = this._faction() === 'assassin' ? 'dark' : 'light';
    this.switchTo(target === this.systemScheme() ? null : target, origin);
    return target === 'dark' ? 'templar' : 'assassin';
  }

  /**
   * Faction chosen on this device: "assassin"/"templar", or null when following the device's setting.
   */
  devicePreference(): Faction | null {
    const stored = readStored();
    return stored === null ? null : stored === 'dark' ? 'templar' : 'assassin';
  }

  /**
   * Applies a preference (e.g. the one saved in the member's account): a faction, or null to
   * follow the device's light/dark setting.
   */
  setPreference(
    preference: Faction | null,
    options: { animate?: boolean; origin?: { x: number; y: number } } = {},
  ): void {
    const stored: Scheme | null =
      preference === null ? null : preference === 'templar' ? 'dark' : 'light';
    if (stored === readStored()) {
      return;
    }
    if (options.animate === false) {
      writeStored(stored);
      this.apply(stored);
    } else {
      this.switchTo(stored, options.origin);
    }
  }

  private switchTo(stored: Scheme | null, origin?: { x: number; y: number }): void {
    writeStored(stored);

    const root = this.document.documentElement;
    const reducedMotion = this.document.defaultView?.matchMedia(
      '(prefers-reduced-motion: reduce)',
    ).matches;
    if (!this.document.startViewTransition || reducedMotion) {
      this.apply(stored);
      return;
    }

    if (origin) {
      root.style.setProperty('--reveal-x', `${origin.x}px`);
      root.style.setProperty('--reveal-y', `${origin.y}px`);
    }
    root.classList.add('faction-switch');
    const transition = this.document.startViewTransition(() => this.apply(stored));
    void transition.finished.finally(() => root.classList.remove('faction-switch'));
  }

  private apply(stored: Scheme | null): void {
    if (this.meta) {
      this.meta.content = stored ?? 'light dark';
    }
    this._faction.set(this.resolve());
  }

  private resolve(): Faction {
    const content = this.meta?.content;
    const scheme = content === 'light' || content === 'dark' ? content : this.systemScheme();
    return scheme === 'dark' ? 'templar' : 'assassin';
  }

  private systemScheme(): Scheme {
    return this.systemDark?.matches ? 'dark' : 'light';
  }

  private updateFavicon(faction: Faction): void {
    const icon = this.document.querySelector<HTMLLinkElement>(
      'link[rel="icon"][type="image/svg+xml"]',
    );
    if (icon) {
      // The default favicon follows the system scheme by itself
      icon.href = readStored() ? `favicon-${faction}.svg` : 'favicon.svg';
    }
  }
}

function readStored(): Scheme | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'light' || value === 'dark' ? value : null;
  } catch {
    return null;
  }
}

function writeStored(value: Scheme | null): void {
  try {
    if (value) {
      localStorage.setItem(STORAGE_KEY, value);
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    // Storage unavailable: the choice lasts until the page is closed
  }
}
