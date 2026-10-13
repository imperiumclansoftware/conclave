import { Component, inject } from '@angular/core';
import { AuthService } from '../core/auth/auth.service';
import { ProfileService } from '../core/profile/profile.service';
import { ThemeService } from '../core/theme/theme.service';
import { Emblem } from './emblem';

/** Switches between the Assassin (light) and Templar (dark) themes. */
@Component({
  selector: 'app-faction-toggle',
  imports: [Emblem],
  template: `
    <button class="btn btn-ghost" type="button" (click)="toggle($event)">
      <span class="only-assassin"><app-emblem faction="templar" [size]="22" /></span>
      <span class="only-templar"><app-emblem faction="assassin" [size]="22" /></span>
      <span class="label">
        <span class="only-assassin">Rejoindre l'Ordre</span>
        <span class="only-templar">Rejoindre la Confrérie</span>
      </span>
    </button>
  `,
  styles: `
    .btn {
      padding: 0.3rem 0.75rem 0.3rem 0.35rem;
      font-size: 0.7rem;
    }
    @media (max-width: 40rem) {
      .btn {
        padding: 0.3rem;
      }
      .label {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
      }
    }
  `,
})
export class FactionToggle {
  private readonly theme = inject(ThemeService);
  private readonly auth = inject(AuthService);
  private readonly profiles = inject(ProfileService);

  protected toggle(event: MouseEvent): void {
    const button = event.currentTarget as HTMLElement;
    const rect = button.getBoundingClientRect();
    const faction = this.theme.toggle({
      x: rect.left + rect.width / 2,
      y: rect.top + rect.height / 2,
    });
    // Also the account's preference, so that other devices follow
    if (this.auth.user()) {
      this.profiles.setFaction(faction).subscribe({ error: () => undefined });
    }
  }
}
