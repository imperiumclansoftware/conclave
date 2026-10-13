import { Component, inject } from '@angular/core';
import { CallsService } from '../core/discord/calls.service';

/** Video call invitations received in real time, shown over the app. */
@Component({
  selector: 'app-call-invitations',
  template: `
    <div class="stack" role="status" aria-live="polite">
      @for (call of calls.invitations(); track call.id) {
        <div class="invitation card">
          <p>
            <strong>{{ call.createdBy?.displayName ?? 'Un membre' }}</strong> vous invite à une
            visio :
            <em>{{ call.title }}</em>
          </p>
          <div class="actions">
            <a
              class="btn"
              [href]="call.joinUrl"
              target="_blank"
              rel="noopener noreferrer"
              (click)="calls.dismissInvitation(call.id)"
              >Rejoindre ↗</a
            >
            <button class="btn btn-ghost" type="button" (click)="calls.dismissInvitation(call.id)">
              Plus tard
            </button>
          </div>
        </div>
      }
    </div>
  `,
  styles: `
    .stack {
      position: fixed;
      right: 1rem;
      bottom: 1rem;
      z-index: 10;
      display: grid;
      gap: 0.75rem;
      width: min(22rem, calc(100vw - 2rem));
    }
    .invitation {
      padding: 1rem 1.1rem;
      animation: slide-in 0.3s ease-out;
      --animation-reduced: none;
    }
    p {
      margin: 0 0 0.75rem;
    }
    .actions {
      display: flex;
      gap: 0.5rem;
    }
    @keyframes slide-in {
      from {
        opacity: 0;
        translate: 0 1rem;
      }
    }
  `,
})
export class CallInvitations {
  protected readonly calls = inject(CallsService);
}
