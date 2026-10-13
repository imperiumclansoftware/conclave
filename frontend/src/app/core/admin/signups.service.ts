import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import { AdminMember, BranchJoinRequest } from '../api/models';
import { AuthService } from '../auth/auth.service';
import { RealtimeService } from '../chat/realtime.service';

/**
 * Number of requests waiting for a leader (sign-ups and branch memberships), for the badge of the
 * "Demandes" tab. Provided by the shell.
 */
@Injectable()
export class SignupsService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  private readonly _count = signal(0);
  readonly count = this._count.asReadonly();

  constructor() {
    const realtime = inject(RealtimeService);
    realtime.events$.pipe(takeUntilDestroyed()).subscribe((event) => {
      if (event.type === 'signups') {
        void this.refresh();
      }
    });
    realtime.reconnected$.pipe(takeUntilDestroyed()).subscribe(() => void this.refresh());
  }

  async refresh(): Promise<void> {
    if (!this.auth.isLeader()) {
      this._count.set(0);
      return;
    }
    try {
      const [signups, branchRequests] = await Promise.all([
        firstValueFrom(this.http.get<AdminMember[]>('api/admin/signups')),
        firstValueFrom(this.http.get<BranchJoinRequest[]>('api/admin/branch-requests')),
      ]);
      this._count.set(signups.length + branchRequests.length);
    } catch {
      // Keep the last count
    }
  }
}
