import { Injectable, computed, signal } from '@angular/core';

const STORAGE_KEY = 'cookie-consent';
const VERSION = 1;
/** The choice is asked again after 6 months (CNIL recommendation). */
const VALIDITY_MS = 182 * 24 * 3600 * 1000;

interface Consent {
  version: number;
  /** Images hosted by third parties (Gravatar, Discord, Steam, RAWG) */
  externalContent: boolean;
  decidedAt: string;
}

/**
 * Consent to optional third-party content. Nothing optional is loaded until the visitor
 * accepts it (privacy by default); essential storage (login, theme, real time) needs no consent.
 */
@Injectable({ providedIn: 'root' })
export class ConsentService {
  private readonly consent = signal<Consent | null>(read());
  /** Banner opened from a "Gérer les cookies" link, with the details shown */
  private readonly reopened = signal(false);

  readonly externalContent = computed(() => this.consent()?.externalContent === true);
  readonly bannerVisible = computed(() => this.consent() === null || this.reopened());
  readonly customizeRequested = this.reopened.asReadonly();

  constructor() {
    // Keep tabs in sync
    window.addEventListener('storage', (event) => {
      if (event.key === STORAGE_KEY) {
        this.consent.set(read());
      }
    });
  }

  /**
   * URL of an image to display: local images always, third-party ones only with consent.
   * Returns null when the image must not be loaded (callers show a placeholder).
   */
  src(url: string | null | undefined): string | null {
    if (!url) {
      return null;
    }
    return isExternal(url) && !this.externalContent() ? null : url;
  }

  decide(externalContent: boolean): void {
    const consent: Consent = {
      version: VERSION,
      externalContent,
      decidedAt: new Date().toISOString(),
    };
    this.consent.set(consent);
    this.reopened.set(false);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));
    } catch {
      // Storage unavailable: the choice lasts until the page is closed
    }
  }

  /** Reopens the banner to change one's choice. */
  manage(): void {
    this.reopened.set(true);
  }

  close(): void {
    this.reopened.set(false);
  }
}

export function isExternal(url: string): boolean {
  return /^https?:\/\//i.test(url) && new URL(url).origin !== window.location.origin;
}

function read(): Consent | null {
  try {
    const consent = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null') as Consent | null;
    if (
      !consent ||
      consent.version !== VERSION ||
      Date.now() - Date.parse(consent.decidedAt) > VALIDITY_MS
    ) {
      return null;
    }
    return consent;
  } catch {
    return null;
  }
}
