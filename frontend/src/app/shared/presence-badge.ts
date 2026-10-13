import { Component, computed, inject, input } from '@angular/core';
import { PresenceService } from '../core/presence/presence.service';
import { Icon } from './icon';
import {
  appStatusLabel,
  discordStatusLabel,
  presenceSummary,
} from '../core/presence/presence-label';

/**
 * Presence of a member: a dot for the app, a Discord badge when the account is linked,
 * and optionally a text line ("Joue à Elden Ring").
 */
@Component({
  selector: 'app-presence-badge',
  imports: [Icon],
  template: `
    @let p = presence();
    <span class="indicators" [title]="summary()">
      <span class="dot" [attr.data-status]="p?.app?.status ?? 'offline'"></span>
      @if (p?.discord; as discord) {
        <span class="discord" [attr.data-status]="discord.status" aria-hidden="true">
          <app-icon name="discord" />
        </span>
      }
      <span class="visually-hidden">{{ summary() }}</span>
    </span>
    @if (withText()) {
      <span class="text" aria-hidden="true">
        {{ appLabel() }}
        @if (p?.discord; as discord) {
          · Discord {{ discordLabel(discord.status).toLowerCase() }}
          @if (discord.activity) {
            · <em>{{ discord.activity }}</em>
          }
        }
      </span>
    }
  `,
  styles: `
    :host {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      min-width: 0;
    }
    .indicators {
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
      flex: none;
    }
    .dot {
      width: 0.65rem;
      height: 0.65rem;
      border: 2px solid var(--text-muted);
      border-radius: 50%;
    }
    .dot[data-status='online'] {
      border-color: var(--success);
      background: var(--success);
      box-shadow: 0 0 5px var(--success);
    }
    .dot[data-status='away'] {
      border-color: #d29922;
      background: #d29922;
    }
    .discord {
      display: grid;
      place-items: center;
      width: 1rem;
      height: 1rem;
      border-radius: 4px;
      background: #5865f2;
      color: #fff;
      box-shadow: 0 0 0 2px var(--discord-ring, #80848e);
    }
    .discord app-icon {
      width: 0.7rem;
      height: 0.7rem;
    }
    .discord[data-status='online'] {
      --discord-ring: #23a55a;
    }
    .discord[data-status='idle'] {
      --discord-ring: #f0b232;
    }
    .discord[data-status='dnd'] {
      --discord-ring: #f23f43;
    }
    .discord[data-status='offline'] {
      opacity: 0.55;
    }
    .text {
      overflow: hidden;
      color: var(--text-muted);
      font-size: 0.85rem;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  `,
})
export class PresenceBadge {
  private readonly presenceService = inject(PresenceService);

  readonly userId = input.required<number>();
  readonly withText = input(false);

  protected readonly presence = computed(() => this.presenceService.presence(this.userId()));
  protected readonly summary = computed(() => presenceSummary(this.presence()));
  protected readonly appLabel = computed(() =>
    appStatusLabel(this.presence()?.app.status ?? 'offline'),
  );
  protected readonly discordLabel = discordStatusLabel;
}
