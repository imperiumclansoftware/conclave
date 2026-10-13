import { Component, ElementRef, computed, inject, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../core/api/api-error';
import { Member, VideoCall } from '../core/api/models';
import { ChatStore } from '../core/chat/chat.store';
import { CallsService } from '../core/discord/calls.service';
import { PresenceService } from '../core/presence/presence.service';
import { PresenceBadge } from './presence-badge';
import { Icon } from './icon';

/** Modal to start a Discord video call with chosen clan members. */
@Component({
  selector: 'app-call-dialog',
  imports: [PresenceBadge, RouterLink, Icon],
  template: `
    <dialog
      #dialog
      closedby="any"
      aria-labelledby="call-dialog-title"
      (click)="onDialogClick($event)"
      (close)="reset()"
    >
      <h2 id="call-dialog-title"><app-icon name="video" /> Lancer une visio sur Discord</h2>

      @if (created(); as call) {
        <p class="success" role="status">
          La visio « {{ call.title }} » est prête. Les membres invités ont été prévenus.
        </p>
        <div class="actions">
          <a
            class="btn"
            [href]="call.joinUrl"
            target="_blank"
            rel="noopener noreferrer"
            (click)="close()"
            >Rejoindre sur Discord ↗</a
          >
          <button class="btn btn-ghost" type="button" (click)="close()">Fermer</button>
        </div>
      } @else {
        <form (submit)="$event.preventDefault(); submit()">
          <div class="field">
            <label for="call-title">Titre (facultatif)</label>
            <input
              id="call-title"
              maxlength="80"
              placeholder="Raid du soir, réunion du clan…"
              autocomplete="off"
              [value]="title()"
              (input)="title.set(titleInput.value)"
              #titleInput
            />
          </div>

          <fieldset>
            <legend>Membres invités</legend>
            <ul>
              @for (member of chat.contacts(); track member.id) {
                @let linked = !!presence.presence(member.id)?.discord;
                <li>
                  <label [class.disabled]="!linked">
                    <input
                      type="checkbox"
                      [disabled]="!linked"
                      [checked]="selected().includes(member.id)"
                      (change)="toggle(member)"
                    />
                    <span class="name">{{ member.displayName }}</span>
                    <app-presence-badge [userId]="member.id" />
                    @if (!linked) {
                      <span class="hint">Compte Discord non lié</span>
                    }
                  </label>
                </li>
              }
            </ul>
          </fieldset>

          @if (error()) {
            <p class="error" role="alert">{{ error() }}</p>
          }
          <div class="actions">
            <button class="btn" type="submit" [disabled]="pending() || selected().length === 0">
              {{ pending() ? 'Création du salon…' : 'Lancer la visio' }}
            </button>
            <button class="btn btn-ghost" type="button" (click)="close()">Annuler</button>
          </div>
          <p class="note">
            Un salon vocal privé est créé sur le serveur Discord du clan, visible uniquement par les
            participants, puis supprimé quand tout le monde est parti. Les membres doivent avoir lié
            leur compte dans <a routerLink="/account" (click)="close()">Mon compte</a>.
          </p>
        </form>
      }
    </dialog>
  `,
  styles: `
    dialog {
      width: min(94vw, 30rem);
      padding: 1.5rem;
      border: 1px solid var(--border-strong);
      border-radius: var(--radius);
      background: var(--surface-solid);
      color: var(--text);
      box-shadow: var(--shadow);
    }
    dialog::backdrop {
      background: rgb(0 0 0 / 0.55);
    }
    h2 {
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }
    fieldset {
      margin: 0 0 1rem;
      padding: 0;
      border: none;
    }
    legend {
      margin-bottom: 0.5rem;
      font-family: var(--font-display);
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    ul {
      display: grid;
      gap: 0.35rem;
      max-height: 16rem;
      margin: 0;
      padding: 0;
      overflow-y: auto;
      list-style: none;
    }
    label {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.4rem 0.5rem;
      border-radius: var(--radius);
      cursor: pointer;
    }
    label:hover {
      background: var(--surface-muted);
    }
    label.disabled {
      cursor: not-allowed;
      opacity: 0.6;
    }
    .name {
      font-weight: 600;
    }
    .hint,
    .note {
      color: var(--text-muted);
      font-size: 0.85rem;
    }
    .note {
      margin: 1rem 0 0;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .success {
      color: var(--success);
    }
  `,
})
export class CallDialog {
  protected readonly chat = inject(ChatStore);
  protected readonly presence = inject(PresenceService);
  private readonly calls = inject(CallsService);
  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  protected readonly title = signal('');
  protected readonly selected = signal<number[]>([]);
  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly created = signal<VideoCall | null>(null);
  protected readonly canSubmit = computed(() => this.selected().length > 0 && !this.pending());

  /** Opens the dialog, optionally with members already chosen. */
  open(memberIds: number[] = []): void {
    this.selected.set(memberIds.filter((id) => !!this.presence.presence(id)?.discord));
    this.dialog().nativeElement.showModal();
  }

  protected close(): void {
    this.dialog().nativeElement.close();
  }

  protected reset(): void {
    this.title.set('');
    this.selected.set([]);
    this.error.set(null);
    this.created.set(null);
  }

  protected toggle(member: Member): void {
    this.selected.update((ids) =>
      ids.includes(member.id) ? ids.filter((id) => id !== member.id) : [...ids, member.id],
    );
  }

  protected async submit(): Promise<void> {
    if (!this.canSubmit()) {
      return;
    }
    this.pending.set(true);
    this.error.set(null);
    try {
      this.created.set(await this.calls.create(this.selected(), this.title()));
    } catch (e) {
      this.error.set(apiErrorMessage(e, "La visio n'a pas pu être créée."));
    } finally {
      this.pending.set(false);
    }
  }

  /** Light dismiss for browsers without <dialog closedby> support. */
  protected onDialogClick(event: MouseEvent): void {
    const dialog = event.currentTarget as HTMLDialogElement;
    if ('closedBy' in HTMLDialogElement.prototype || event.target !== dialog) {
      return;
    }
    const r = dialog.getBoundingClientRect();
    if (
      event.clientY < r.top ||
      event.clientY > r.bottom ||
      event.clientX < r.left ||
      event.clientX > r.right
    ) {
      dialog.close();
    }
  }
}
