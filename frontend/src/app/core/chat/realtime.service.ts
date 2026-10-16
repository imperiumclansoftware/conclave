import { HttpClient } from '@angular/common/http';
import { DestroyRef, Injectable, inject, signal } from '@angular/core';
import { Subject, firstValueFrom } from 'rxjs';
import { EventMessage, Member, Message, Presence, VideoCall } from '../api/models';

export type RealtimeEvent =
  | { type: 'message'; message: Message }
  | { type: 'presence'; presence: Presence }
  | { type: 'call'; call: VideoCall }
  | { type: 'call-ended'; callId: number }
  | { type: 'member'; member: Member }
  | { type: 'member-joined'; member: Member }
  | { type: 'member-left'; userId: number }
  | { type: 'signups' }
  | { type: 'branches' }
  | { type: 'links' }
  | { type: 'events' }
  | { type: 'event-message'; message: EventMessage }
  | { type: 'event-message-deleted'; eventId: number; messageId: number };
export type RealtimeStatus = 'connecting' | 'connected' | 'disconnected';

interface Subscription {
  hubUrl: string;
  topic: string;
}

const RETRY_DELAY_MS = 5000;

/**
 * Listens to the user's private Mercure topic over Server-Sent Events.
 * Provided by the authenticated shell, so it lives only while the user is logged in.
 */
@Injectable()
export class RealtimeService {
  private readonly http = inject(HttpClient);

  private source: EventSource | null = null;
  private retryTimer: ReturnType<typeof setTimeout> | undefined;
  private hadConnection = false;
  private stopped = false;

  private readonly _status = signal<RealtimeStatus>('disconnected');
  readonly status = this._status.asReadonly();

  /** Real-time events pushed by the server. */
  readonly events$ = new Subject<RealtimeEvent>();
  /** Emits when the connection comes back after an interruption: missed events must be fetched. */
  readonly reconnected$ = new Subject<void>();

  constructor() {
    inject(DestroyRef).onDestroy(() => this.disconnect());
  }

  async connect(): Promise<void> {
    this.stopped = false;
    this.close();
    this._status.set('connecting');

    let subscription: Subscription;
    try {
      // Sets the HttpOnly cookie read by the hub (it expires, so it is renewed on every connection)
      subscription = await firstValueFrom(
        this.http.post<Subscription>('api/mercure/authorize', null),
      );
    } catch {
      this.scheduleRetry();
      return;
    }
    if (this.stopped) {
      return;
    }

    const url = new URL(subscription.hubUrl, document.baseURI);
    url.searchParams.append('match', subscription.topic);
    const source = new EventSource(url, { withCredentials: true });
    this.source = source;

    source.onopen = () => {
      this._status.set('connected');
      if (this.hadConnection) {
        this.reconnected$.next();
      }
      this.hadConnection = true;
    };
    source.onmessage = (event: MessageEvent<string>) => {
      try {
        this.events$.next(JSON.parse(event.data) as RealtimeEvent);
      } catch {
        // Ignore malformed events
      }
    };
    source.onerror = () => {
      // The browser retries by itself while CONNECTING; once CLOSED (e.g. expired
      // authorization cookie) a new authorization is needed.
      if (source.readyState === EventSource.CLOSED) {
        this.scheduleRetry();
      } else {
        this._status.set('connecting');
      }
    };
  }

  disconnect(): void {
    this.stopped = true;
    this.close();
    this._status.set('disconnected');
  }

  private scheduleRetry(): void {
    this.close();
    this._status.set('disconnected');
    if (!this.stopped) {
      this.retryTimer = setTimeout(() => void this.connect(), RETRY_DELAY_MS);
    }
  }

  private close(): void {
    clearTimeout(this.retryTimer);
    this.source?.close();
    this.source = null;
  }
}
