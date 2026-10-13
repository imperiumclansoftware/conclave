import { Injectable, computed, inject, signal } from '@angular/core';
import { SidePanelService } from '../side-panel/side-panel.service';

/**
 * State of the messaging sidebar: open or closed (one sidebar at a time, see SidePanelService),
 * and the conversation it shows (null: the list of conversations).
 */
@Injectable({ providedIn: 'root' })
export class ChatPanelService {
  private readonly panels = inject(SidePanelService);
  private readonly _contactId = signal<number | null>(null);

  readonly isOpen = computed(() => this.panels.active() === 'chat');
  /** Member whose conversation is shown */
  readonly contactId = this._contactId.asReadonly();

  toggle(): void {
    this.panels.toggle('chat');
  }

  /** Opens the sidebar, on the conversation with a member if given. */
  open(contactId: number | null = null): void {
    if (contactId !== null) {
      this._contactId.set(contactId);
    }
    this.panels.open('chat');
  }

  close(): void {
    this.panels.close('chat');
  }

  /** Back to the list of conversations. */
  showList(): void {
    this._contactId.set(null);
  }

  /** On logout: closed, on the list. */
  reset(): void {
    this._contactId.set(null);
    this.panels.close('chat');
    this.panels.close('links');
  }
}
