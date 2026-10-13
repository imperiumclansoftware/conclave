import { HttpClient } from '@angular/common/http';
import { DestroyRef, Injectable, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import { AppStatus, Presence } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { RealtimeService } from '../chat/realtime.service';

const HEARTBEAT_MS = 30_000;
/** Must match AppStatus::TIMEOUT_SECONDS on the API. */
const TIMEOUT_SECONDS = 75;
const TICK_MS = 15_000;

interface Entry {
  presence: Presence;
  /** Local time at which the app status stops being valid (null: offline). */
  expiresAt: number | null;
}

/**
 * Presence of the clan members, in the app and on Discord. Also reports the user's own
 * presence with heartbeats. Provided by the authenticated shell.
 */
@Injectable()
export class PresenceService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly realtime = inject(RealtimeService);

  private readonly entries = signal<Map<number, Entry>>(new Map());
  /** Ticks so that statuses expire without server events (tab closed without notice…). */
  private readonly now = signal(Date.now());

  constructor() {
    this.realtime.events$.pipe(takeUntilDestroyed()).subscribe((event) => {
      if (event.type === 'presence') {
        this.store([event.presence]);
      }
    });
    this.realtime.reconnected$.pipe(takeUntilDestroyed()).subscribe(() => void this.load());

    const heartbeat = setInterval(() => this.beat(), HEARTBEAT_MS);
    const tick = setInterval(() => this.now.set(Date.now()), TICK_MS);
    const onVisibility = () => this.beat();
    const onPageHide = () => this.leave();
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', onPageHide);
    inject(DestroyRef).onDestroy(() => {
      clearInterval(heartbeat);
      clearInterval(tick);
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('pagehide', onPageHide);
    });
  }

  async init(): Promise<void> {
    this.beat();
    await this.load();
  }

  /** Effective presence of a member (null until loaded). */
  presence(userId: number): Presence | null {
    const entry = this.entries().get(userId);
    if (!entry) {
      return null;
    }
    const expired = entry.expiresAt !== null && this.now() >= entry.expiresAt;
    return expired
      ? { ...entry.presence, app: { status: 'offline', ageSeconds: null } }
      : entry.presence;
  }

  appStatus(userId: number): AppStatus {
    return this.presence(userId)?.app.status ?? 'offline';
  }

  /** Tells the clan the user left (logout, tab closed); must work while the page unloads. */
  leave(): void {
    const token = this.auth.token();
    if (!token) {
      return;
    }
    void fetch(new URL('api/presence', document.baseURI), {
      method: 'POST',
      keepalive: true,
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ state: 'offline' }),
    }).catch(() => undefined);
  }

  private beat(): void {
    const state = document.visibilityState === 'visible' ? 'online' : 'away';
    this.http.post('api/presence', { state }).subscribe({ error: () => undefined });
  }

  private async load(): Promise<void> {
    try {
      this.store(await firstValueFrom(this.http.get<Presence[]>('api/clan/presence')), true);
    } catch {
      // Presence is not essential: keep what we have
    }
  }

  private store(presences: Presence[], replace = false): void {
    const now = Date.now();
    this.now.set(now);
    this.entries.update((current) => {
      const next = replace ? new Map<number, Entry>() : new Map(current);
      for (const presence of presences) {
        const age = presence.app.ageSeconds;
        const expiresAt =
          presence.app.status === 'offline' || age === null
            ? null
            : now + (TIMEOUT_SECONDS - age) * 1000;
        next.set(presence.userId, { presence, expiresAt });
      }
      return next;
    });
  }
}
