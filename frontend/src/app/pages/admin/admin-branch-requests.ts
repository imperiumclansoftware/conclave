import { Component, OnInit, inject, signal } from '@angular/core';
import { SignupsService } from '../../core/admin/signups.service';
import { apiErrorMessage } from '../../core/api/api-error';
import { BranchJoinRequest } from '../../core/api/models';
import { RealtimeService } from '../../core/chat/realtime.service';
import { BranchesService } from '../../core/clan/branches.service';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Avatar } from '../../shared/avatar';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';

type Status = { kind: 'success' | 'error'; message: string } | null;

const dateFormat = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  hour: '2-digit',
  minute: '2-digit',
});

/** Members asking to join a branch validated by the leaders (e.g. the Communicants). */
@Component({
  selector: 'app-admin-branch-requests',
  imports: [Avatar, BranchCrest, Icon],
  template: `
    <section class="card" aria-labelledby="branch-requests-title">
      <h2 id="branch-requests-title">Demandes d'accès aux branches</h2>
      @if (status(); as s) {
        <p [class]="s.kind" [attr.role]="s.kind === 'error' ? 'alert' : 'status'">
          {{ s.message }}
        </p>
      }
      @if (loaded() && requests().length === 0) {
        <p class="meta">Aucune demande en attente.</p>
      }
      <ul class="people">
        @for (r of requests(); track r.id) {
          <li>
            <app-avatar [member]="r.member" [size]="40" />
            <span class="who">
              <strong>{{ r.member.displayName }}</strong>
              <span class="meta branch">
                souhaite rejoindre
                @if (r.branch.crest) {
                  <app-branch-crest [crest]="r.branch.crest" [size]="16" />
                }
                <strong>{{ r.branch.name }}</strong>
              </span>
              <span class="meta">Demande du {{ date(r.createdAt) }}</span>
            </span>
            <span class="actions">
              <button
                class="btn"
                type="button"
                (click)="approve(r)"
                [disabled]="busy() !== null"
                [attr.aria-label]="'Valider ' + r.member.displayName + ' dans ' + r.branch.name"
              >
                <app-icon name="approve" /> Valider
              </button>
              <button
                class="btn btn-ghost"
                type="button"
                (click)="reject(r)"
                [disabled]="busy() !== null"
                [attr.aria-label]="'Refuser ' + r.member.displayName + ' dans ' + r.branch.name"
              >
                <app-icon name="reject" /> Refuser
              </button>
            </span>
          </li>
        }
      </ul>
    </section>
  `,
  styleUrl: './admin-members.scss',
  styles: `
    .branch {
      display: inline-flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.3rem;
    }
  `,
})
export class AdminBranchRequests implements OnInit {
  private readonly branches = inject(BranchesService);
  private readonly badge = inject(SignupsService);

  protected readonly requests = signal<BranchJoinRequest[]>([]);
  protected readonly loaded = signal(false);
  protected readonly busy = signal<number | null>(null);
  protected readonly status = signal<Status>(null);

  constructor() {
    // New requests arrive with the same event as the sign-ups
    inject(RealtimeService)
      .events$.pipe(takeUntilDestroyed())
      .subscribe((event) => {
        if (event.type === 'signups') {
          void this.load();
        }
      });
  }

  ngOnInit(): void {
    void this.load();
  }

  protected date(iso: string): string {
    return dateFormat.format(new Date(iso));
  }

  protected approve(request: BranchJoinRequest): Promise<void> {
    return this.answer(
      request,
      () => this.branches.approve(request),
      `${request.member.displayName} a rejoint ${request.branch.name}.`,
    );
  }

  protected reject(request: BranchJoinRequest): Promise<void> {
    if (
      !confirm(`Refuser la demande de ${request.member.displayName} pour ${request.branch.name} ?`)
    ) {
      return Promise.resolve();
    }
    return this.answer(request, () => this.branches.reject(request), 'Demande refusée.');
  }

  private async answer(
    request: BranchJoinRequest,
    action: () => Promise<void>,
    message: string,
  ): Promise<void> {
    this.busy.set(request.id);
    this.status.set(null);
    try {
      await action();
      this.requests.update((list) => list.filter((r) => r.id !== request.id));
      this.status.set({ kind: 'success', message });
      void this.badge.refresh();
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(null);
    }
  }

  private async load(): Promise<void> {
    try {
      this.requests.set(await this.branches.requests());
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.loaded.set(true);
    }
  }
}
