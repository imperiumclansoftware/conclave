import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { EventInvitation } from '../api/models';
import { DesktopBridge } from '../desktop/desktop-bridge.service';

/** Device preference: 'on' or 'off', chosen by the member on this device */
const STORAGE_KEY = 'conclave.browser-notifications';

/**
 * - unsupported: the browser has no notifications
 * - insecure: notifications need HTTPS (or localhost)
 * - denied: blocked in the browser settings, only the member can lift it
 * - off / on: the member's choice on this device
 */
export type BrowserNotificationState = 'unsupported' | 'insecure' | 'denied' | 'off' | 'on';

const dateFormat = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  hour: '2-digit',
  minute: '2-digit',
});

/** Title and text of the notification of a game event */
export function invitationNotice(invitation: EventInvitation): { title: string; body: string } {
  const game = [invitation.game, invitation.platform ? `(${invitation.platform})` : null]
    .filter(Boolean)
    .join(' ');
  const who = invitation.organiser ? `${invitation.organiser} organise` : 'Nouvel événement :';
  return {
    title: game ? `${invitation.typeLabel} sur ${game}` : invitation.typeLabel,
    body: `${who} « ${invitation.title} », ${dateFormat.format(new Date(invitation.startsAt))}.`,
  };
}

/**
 * Notifications of the browser, chosen on each device: they show while Conclave is open in a
 * tab, even in the background (the e-mails cover the rest).
 */
@Injectable({ providedIn: 'root' })
export class BrowserNotifications {
  private readonly router = inject(Router);
  private readonly desktop = inject(DesktopBridge);

  private readonly permission = signal<NotificationPermission | null>(
    'Notification' in window ? Notification.permission : null,
  );
  // In the Windows application, on unless turned off; in a browser, off unless turned on
  private readonly wanted = signal(
    this.desktop.active ? readPreference() !== 'off' : readPreference() === 'on',
  );

  readonly state = computed<BrowserNotificationState>(() => {
    // Windows notifications: no browser permission, no HTTPS needed
    if (this.desktop.active) {
      return this.wanted() ? 'on' : 'off';
    }
    if (this.permission() === null) {
      return 'unsupported';
    }
    if (!window.isSecureContext) {
      return 'insecure';
    }
    if (this.permission() === 'denied') {
      return 'denied';
    }
    return this.wanted() && this.permission() === 'granted' ? 'on' : 'off';
  });

  /** Asks the browser for the permission if needed; true when the notifications are on */
  async enable(): Promise<boolean> {
    if (this.desktop.active) {
      this.wanted.set(true);
      writePreference(true);
      return true;
    }
    if (this.state() === 'unsupported' || this.state() === 'insecure') {
      return false;
    }
    const permission = await Notification.requestPermission();
    this.permission.set(permission);
    const on = permission === 'granted';
    this.wanted.set(on);
    writePreference(on);
    return on;
  }

  disable(): void {
    this.wanted.set(false);
    writePreference(false);
  }

  invitation(invitation: EventInvitation): void {
    const { title, body } = invitationNotice(invitation);
    // The tag replaces the notification shown by another tab for the same event
    this.show(title, body, `event-${invitation.id}`, ['/evenements', invitation.id]);
  }

  /**
   * A private message received while the Windows application is not in front (in a browser,
   * the tab's title and the messaging sidebar already show it).
   */
  message(sender: string, content: string, senderId: number): void {
    if (!this.desktop.active || document.hasFocus()) {
      return;
    }
    // One notification per person: the latest message replaces the previous one
    this.show(sender, content.replace(/\s+/g, ' ').trim(), `message-${senderId}`, [
      '/messages',
      senderId,
    ]);
  }

  /** Shows a notification if they are on; a click brings the app back, on the given page */
  show(title: string, body: string, tag: string, link: (string | number)[] | null = null): void {
    if (this.state() !== 'on') {
      return;
    }
    if (this.desktop.active) {
      this.desktop.notify({
        title,
        body,
        tag,
        link: link ? this.router.serializeUrl(this.router.createUrlTree(link)) : null,
      });
      return;
    }
    const notification = new Notification(title, {
      body,
      tag,
      icon: new URL('favicon.svg', document.baseURI).href,
    });
    notification.onclick = () => {
      window.focus();
      if (link) {
        void this.router.navigate(link);
      }
      notification.close();
    };
  }
}

function readPreference(): 'on' | 'off' | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'on' || value === 'off' ? value : null;
  } catch {
    return null;
  }
}

function writePreference(on: boolean): void {
  try {
    localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
  } catch {
    // Storage blocked: the choice lasts until the page is reloaded
  }
}
