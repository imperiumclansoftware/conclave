import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';

/** Set by the Windows application before the page loads */
interface DesktopHost {
  platform: 'windows';
  version: string;
}

interface WebViewChannel {
  postMessage(message: unknown): void;
  addEventListener(type: 'message', listener: (event: MessageEvent) => void): void;
}

declare global {
  interface Window {
    conclaveDesktop?: DesktopHost;
    chrome?: { webview?: WebViewChannel };
  }
}

export interface DesktopNotice {
  title: string;
  body: string;
  /** The same tag replaces the previous notification */
  tag: string;
  /** Page opened by a click ("/evenements/4") */
  link: string | null;
}

/**
 * Conversation with the Windows application when Conclave runs inside it: Windows
 * notifications, the unread badge of the taskbar, and the pages it asks to open.
 */
@Injectable({ providedIn: 'root' })
export class DesktopBridge {
  private readonly router = inject(Router);
  private readonly channel = window.conclaveDesktop ? (window.chrome?.webview ?? null) : null;

  /** Version of the application, null in a browser */
  readonly version = this.channel ? (window.conclaveDesktop?.version ?? null) : null;
  readonly active = this.channel !== null;

  private lastBadge = -1;

  constructor() {
    // A click on a notification: the page changes without reloading Conclave
    this.channel?.addEventListener('message', (event) => {
      const data = event.data as { type?: unknown; link?: unknown } | null;
      if (data?.type === 'navigate' && isPagePath(data.link)) {
        void this.router.navigateByUrl(data.link);
      }
    });
  }

  notify(notice: DesktopNotice): void {
    this.post({ type: 'notification', ...notice });
  }

  badge(count: number): void {
    if (count !== this.lastBadge) {
      this.lastBadge = count;
      this.post({ type: 'badge', count });
    }
  }

  private post(message: object): void {
    // The application reads strings
    this.channel?.postMessage(JSON.stringify(message));
  }
}

/** "/evenements/4", never another site ("//example.com") */
export function isPagePath(link: unknown): link is string {
  return (
    typeof link === 'string' &&
    link.startsWith('/') &&
    !link.startsWith('//') &&
    !link.includes('\\')
  );
}
