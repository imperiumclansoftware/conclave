import { Component, inject, input, signal } from '@angular/core';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { ConsentService } from '../../core/consent/consent.service';
import { DiscordFlow, DiscordService } from '../../core/discord/discord.service';

/** Discord integration settings: the member's account, and (leaders) the clan's server. */
@Component({
  selector: 'app-discord-card',
  template: `
    @let status = discord.status();
    <section class="card" aria-labelledby="discord-title">
      <h2 id="discord-title">Discord</h2>

      @if (flash()) {
        <p class="success" role="status">{{ flash() }}</p>
      }
      @if (error()) {
        <p class="error" role="alert">{{ error() }}</p>
      }

      @if (!status) {
        <p class="meta">Chargement…</p>
      } @else if (!status.enabled) {
        <p class="meta">
          L'intégration Discord n'est pas encore configurée sur ce serveur (voir le README).
        </p>
      } @else {
        @if (scope() !== 'server') {
          <h3>Mon compte</h3>
          @if (status.account; as account) {
            <div class="account">
              @if (consent.src(account.avatarUrl); as avatar) {
                <img [src]="avatar" alt="" width="40" height="40" referrerpolicy="no-referrer" />
              }
              <span
                >Lié à <strong>{{ account.username }}</strong></span
              >
            </div>
            @if (status.guild && account.onServer === false) {
              <p class="meta">Vous n'êtes pas encore sur le serveur du clan.</p>
              <button class="btn" type="button" (click)="go('link')">
                Rejoindre « {{ status.guild.name }} »
              </button>
            }
            <p>
              <button class="link" type="button" (click)="unlink()">
                Délier mon compte Discord
              </button>
            </p>
          } @else {
            <p class="meta">
              Liez votre compte pour afficher votre statut Discord au clan et participer aux visios.
            </p>
            <button class="btn" type="button" (click)="go('link')">Lier mon compte Discord</button>
          }
        }

        @if (scope() !== 'account') {
          <h3>Serveur du clan</h3>
          @if (status.guild; as guild) {
            <p>
              Connecté à <strong>{{ guild.name }}</strong> : présences et visios activées.
            </p>
            @if (auth.isLeader()) {
              <button class="link" type="button" (click)="disconnect()">
                Déconnecter le serveur
              </button>
            }
          } @else if (auth.isLeader()) {
            <p class="meta">
              Ajoutez le bot du clan à votre serveur Discord : il affichera les statuts et créera
              les salons de visio.
            </p>
            <button class="btn" type="button" (click)="go('install')">
              Connecter un serveur Discord
            </button>
          } @else {
            <p class="meta">Un dirigeant doit connecter le serveur Discord du clan.</p>
          }
        }
      }
    </section>
  `,
  styles: `
    h2 {
      margin-bottom: 0.75rem;
      font-size: 1.1rem;
    }
    h3 {
      margin: 1.25rem 0 0.5rem;
      font-size: 0.9rem;
      text-transform: uppercase;
    }
    h2 + h3,
    p + h3:first-of-type {
      margin-top: 0;
    }
    .account {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
    }
    .account img {
      border-radius: 50%;
    }
    .meta {
      color: var(--text-muted);
    }
    .success {
      color: var(--success);
    }
    .link {
      padding: 0;
      border: none;
      background: none;
      color: var(--accent-text);
      text-decoration: underline;
      cursor: pointer;
    }
  `,
})
export class DiscordCard {
  protected readonly discord = inject(DiscordService);
  protected readonly auth = inject(AuthService);
  protected readonly consent = inject(ConsentService);

  /** Which settings to show. */
  readonly scope = input<'account' | 'server' | 'all'>('all');
  readonly flash = input<string | null>(null);
  protected readonly error = signal<string | null>(null);

  constructor() {
    void this.discord.refresh();
  }

  protected async go(flow: DiscordFlow): Promise<void> {
    this.error.set(null);
    try {
      await this.discord.authorize(flow);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }

  protected async unlink(): Promise<void> {
    if (confirm('Délier votre compte Discord ? Votre statut Discord ne sera plus affiché.')) {
      await this.run(() => this.discord.unlink());
    }
  }

  protected async disconnect(): Promise<void> {
    if (
      confirm(
        'Déconnecter le serveur Discord ? Le bot quittera le serveur et les visios ne seront plus possibles.',
      )
    ) {
      await this.run(() => this.discord.disconnectServer());
    }
  }

  private async run(action: () => Promise<void>): Promise<void> {
    this.error.set(null);
    try {
      await action();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }
}
