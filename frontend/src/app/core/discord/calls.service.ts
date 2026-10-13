import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import { VideoCall } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { RealtimeService } from '../chat/realtime.service';

/**
 * Ongoing Discord video calls the user is invited to, kept in sync in real time.
 * Provided by the authenticated shell.
 */
@Injectable()
export class CallsService {
  private readonly http = inject(HttpClient);
  private readonly me = inject(AuthService).user;
  private readonly realtime = inject(RealtimeService);

  private readonly _calls = signal<VideoCall[]>([]);
  readonly calls = this._calls.asReadonly();

  /** Invitations received in real time and not dismissed yet. */
  private readonly _invitations = signal<VideoCall[]>([]);
  readonly invitations = computed(() => {
    const active = new Set(this._calls().map((c) => c.id));
    return this._invitations().filter((c) => active.has(c.id));
  });

  constructor() {
    this.realtime.events$.pipe(takeUntilDestroyed()).subscribe((event) => {
      if (event.type === 'call') {
        const known = this._calls().some((c) => c.id === event.call.id);
        this.upsert(event.call);
        if (!known && event.call.createdBy?.id !== this.me()?.id) {
          this._invitations.update((list) => [...list, event.call]);
        }
      } else if (event.type === 'call-ended') {
        this._calls.update((list) => list.filter((c) => c.id !== event.callId));
      }
    });
    this.realtime.reconnected$.pipe(takeUntilDestroyed()).subscribe(() => void this.load());
  }

  async load(): Promise<void> {
    try {
      this._calls.set(await firstValueFrom(this.http.get<VideoCall[]>('api/calls')));
    } catch {
      // Keep the current list
    }
  }

  async create(userIds: number[], title: string): Promise<VideoCall> {
    const call = await firstValueFrom(
      this.http.post<VideoCall>('api/calls', { userIds, title: title.trim() || null }),
    );
    this.upsert(call);
    return call;
  }

  async end(callId: number): Promise<void> {
    await firstValueFrom(this.http.delete(`api/calls/${callId}`));
    this._calls.update((list) => list.filter((c) => c.id !== callId));
  }

  dismissInvitation(callId: number): void {
    this._invitations.update((list) => list.filter((c) => c.id !== callId));
  }

  private upsert(call: VideoCall): void {
    this._calls.update((list) =>
      list.some((c) => c.id === call.id)
        ? list.map((c) => (c.id === call.id ? call : c))
        : [call, ...list],
    );
  }
}
