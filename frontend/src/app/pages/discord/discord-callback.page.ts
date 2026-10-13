import { Component, OnInit, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { DiscordService } from '../../core/discord/discord.service';

/** Landing page of the Discord OAuth2 redirection (DISCORD_REDIRECT_URI). */
@Component({
  selector: 'app-discord-callback-page',
  imports: [RouterLink],
  template: `
    <main class="card">
      @if (error()) {
        <h1>Discord</h1>
        <p class="error" role="alert">{{ error() }}</p>
        <a class="btn" routerLink="/account">Retour à mon compte</a>
      } @else {
        <p role="status">Connexion à Discord…</p>
      }
    </main>
  `,
  styles: `
    :host {
      display: grid;
      place-items: center;
      min-height: 100%;
      padding: 1rem;
    }
    .card {
      width: min(100%, 26rem);
      text-align: center;
    }
  `,
})
export class DiscordCallbackPage implements OnInit {
  private readonly discord = inject(DiscordService);
  private readonly router = inject(Router);

  // Query parameters sent back by Discord (bound by the router)
  readonly code = input<string>();
  readonly state = input<string>();
  readonly error_description = input<string>();

  protected readonly error = signal<string | null>(null);

  async ngOnInit(): Promise<void> {
    const code = this.code();
    const state = this.state();
    if (!code || !state) {
      this.error.set(
        this.error_description()
          ? 'Autorisation refusée sur Discord.'
          : 'Réponse de Discord incomplète.',
      );
      return;
    }

    try {
      const purpose = await this.discord.completeAuthorization(code, state);
      await this.router.navigate(['/account'], {
        queryParams: { discord: purpose },
        replaceUrl: true,
      });
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }
}
