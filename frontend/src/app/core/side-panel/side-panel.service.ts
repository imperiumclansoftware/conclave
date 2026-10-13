import { Injectable, signal } from '@angular/core';

export type SidePanel = 'chat' | 'links';

const STORAGE_KEY = 'side-panel';
/** Before the links sidebar, only the messaging one was remembered */
const LEGACY_CHAT_KEY = 'chat-panel-open';

/**
 * The sidebar open on the right of the page, one at a time: messaging or favourite links
 * (remembered on this device).
 */
@Injectable({ providedIn: 'root' })
export class SidePanelService {
  private readonly _active = signal<SidePanel | null>(readActive());
  readonly active = this._active.asReadonly();

  open(panel: SidePanel): void {
    this.set(panel);
  }

  /** Closes this sidebar if it is the open one */
  close(panel: SidePanel): void {
    if (this._active() === panel) {
      this.set(null);
    }
  }

  toggle(panel: SidePanel): void {
    this.set(this._active() === panel ? null : panel);
  }

  private set(panel: SidePanel | null): void {
    this._active.set(panel);
    try {
      localStorage.setItem(STORAGE_KEY, panel ?? '');
      localStorage.removeItem(LEGACY_CHAT_KEY);
    } catch {
      // Storage unavailable: the state lasts until the page is closed
    }
  }
}

function readActive(): SidePanel | null {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'chat' || stored === 'links') {
      return stored;
    }
    return stored === null && localStorage.getItem(LEGACY_CHAT_KEY) === '1' ? 'chat' : null;
  } catch {
    return null;
  }
}
