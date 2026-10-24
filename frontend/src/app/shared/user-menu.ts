import { Component, ElementRef, inject, output, viewChild } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { SignupsService } from '../core/admin/signups.service';
import { AuthService } from '../core/auth/auth.service';
import { Avatar } from './avatar';
import { DesktopBridge } from '../core/desktop/desktop-bridge.service';
import { Icon } from './icon';

/**
 * Menu under the user's name: profile, administration (leaders) and logout. A popover
 * (top layer, closes with Escape or a click outside), positioned under its button.
 */
@Component({
  selector: 'app-user-menu',
  imports: [RouterLink, RouterLinkActive, Avatar, Icon],
  template: `
    @if (auth.user(); as me) {
      <button
        #trigger
        class="trigger"
        type="button"
        popovertarget="user-menu"
        [title]="'Menu de ' + me.displayName"
      >
        <app-avatar [member]="me" [size]="30" />
        <span class="name">{{ me.displayName }}</span>
        @if (signups.count() > 0) {
          <span class="badge pending"
            >{{ signups.count()
            }}<span class="visually-hidden"> demande(s) d'inscription</span></span
          >
        }
        <span class="chevron" aria-hidden="true">▾</span>
      </button>

      <div
        #panel
        id="user-menu"
        class="panel card"
        popover="auto"
        aria-label="Menu du compte"
        (toggle)="onToggle($event)"
      >
        <p class="who">
          <strong>{{ me.displayName }}</strong>
          <span>{{ me.role === 'leader' ? 'Dirigeant' : 'Membre' }} · {{ me.clan.name }}</span>
        </p>
        <ul>
          <li>
            <a routerLink="/account" routerLinkActive="active" (click)="close()"
              ><app-icon name="profile" /> Mon profil</a
            >
          </li>
          @if (auth.isLeader()) {
            <li>
              <a routerLink="/admin" routerLinkActive="active" (click)="close()">
                <app-icon name="admin" /> Administration
                @if (signups.count() > 0) {
                  <span class="badge"
                    >{{ signups.count() }}<span class="visually-hidden"> demande(s)</span></span
                  >
                }
              </a>
            </li>
          }
          @if (!desktop.active) {
            <li>
              <a href="api/desktop/windows/download" (click)="close()"
                ><app-icon name="desktop" /> Application Windows</a
              >
            </li>
          }
          <li class="separator">
            <button type="button" (click)="close(); logout.emit()">
              <app-icon name="logout" /> Déconnexion
            </button>
          </li>
        </ul>
      </div>
    }
  `,
  styles: `
    .trigger {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.2rem 0.5rem 0.2rem 0.2rem;
      border: 1px solid transparent;
      border-radius: 999px;
      background: none;
      color: var(--text);
      font: inherit;
      cursor: pointer;
    }
    .trigger:hover,
    /* :popover-open combined with the polyfill's class, or older browsers drop the whole rule */
    .trigger:has(+ .panel:is(:popover-open, .\\:popover-open)) {
      border-color: var(--border);
      background: var(--surface-muted);
    }
    .chevron {
      color: var(--text-muted);
      font-size: 0.8rem;
    }
    .pending {
      min-width: 1.2rem;
      height: 1.2rem;
      font-size: 0.65rem;
    }
    .panel {
      /* Top layer: placed under the button by onToggle() */
      position: fixed;
      inset: auto;
      top: var(--menu-top, 3.5rem);
      right: var(--menu-right, 1rem);
      width: 15rem;
      margin: 0;
      padding: 0.5rem;
    }
    .who {
      display: grid;
      margin: 0 0 0.4rem;
      padding: 0.4rem 0.6rem 0.6rem;
      border-bottom: 1px solid var(--border);
      font-size: 0.9rem;
    }
    .who span {
      color: var(--text-muted);
      font-size: 0.8rem;
    }
    ul {
      display: grid;
      gap: 0.15rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }
    a,
    li button {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      width: 100%;
      padding: 0.5rem 0.6rem;
      border: none;
      border-radius: var(--radius);
      background: none;
      color: var(--text);
      font: inherit;
      text-align: left;
      text-decoration: none;
      cursor: pointer;
    }
    a:hover,
    li button:hover,
    a.active {
      background: var(--surface-muted);
      color: var(--accent-text);
    }
    a .badge {
      margin-left: auto;
    }
    .separator {
      margin-top: 0.25rem;
      padding-top: 0.25rem;
      border-top: 1px solid var(--border);
    }
    @media (max-width: 40rem) {
      .name,
      .chevron {
        display: none;
      }
    }
  `,
})
export class UserMenu {
  protected readonly desktop = inject(DesktopBridge);
  protected readonly auth = inject(AuthService);
  protected readonly signups = inject(SignupsService);
  private readonly trigger = viewChild<ElementRef<HTMLElement>>('trigger');
  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');

  readonly logout = output<void>();

  /** Places the panel under its button, aligned on its right edge. */
  protected onToggle(event: Event): void {
    const trigger = this.trigger()?.nativeElement;
    const panel = this.panel()?.nativeElement;
    if ((event as ToggleEvent).newState !== 'open' || !trigger || !panel) {
      return;
    }
    const rect = trigger.getBoundingClientRect();
    panel.style.setProperty('--menu-top', `${rect.bottom + 6}px`);
    panel.style.setProperty('--menu-right', `${Math.max(8, window.innerWidth - rect.right)}px`);
  }

  protected close(): void {
    this.panel()?.nativeElement.hidePopover?.();
  }
}
