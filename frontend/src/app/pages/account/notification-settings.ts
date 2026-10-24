import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { DesktopBridge } from '../../core/desktop/desktop-bridge.service';
import { BrowserNotifications } from '../../core/notifications/browser-notifications.service';
import { ProfileService } from '../../core/profile/profile.service';

/** How the member hears about the game events organised on their games. */
@Component({
  selector: 'app-notification-settings',
  imports: [RouterLink],
  templateUrl: './notification-settings.html',
  styleUrl: './notification-settings.scss',
})
export class NotificationSettings {
  protected readonly auth = inject(AuthService);
  protected readonly browser = inject(BrowserNotifications);
  protected readonly desktop = inject(DesktopBridge);
  private readonly profiles = inject(ProfileService);

  protected readonly saving = signal(false);
  protected readonly status = signal<{ kind: 'success' | 'error'; message: string } | null>(null);

  protected async setEmails(wanted: boolean): Promise<void> {
    this.saving.set(true);
    this.status.set(null);
    try {
      await firstValueFrom(this.profiles.setNotifications({ gameEventEmails: wanted }));
      this.status.set({
        kind: 'success',
        message: wanted ? 'Vous recevrez ces e-mails.' : 'Vous ne recevrez plus ces e-mails.',
      });
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.saving.set(false);
    }
  }

  protected async enableBrowser(): Promise<void> {
    await this.browser.enable();
  }

  /** Shows what a notification looks like */
  protected tryBrowser(): void {
    this.browser.show(
      'Session de jeu sur votre jeu (PC)',
      `${this.auth.user()?.displayName ?? 'Un membre'} organise « Exemple de soirée », demain soir.`,
      'conclave-test',
    );
  }
}
