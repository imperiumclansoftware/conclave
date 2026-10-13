import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-forgot-password-page',
  imports: [RouterLink],
  template: `
    <main class="card">
      <h1>Mot de passe oublié</h1>
      @if (sent()) {
        <p class="subtitle" role="status">
          Si un compte correspond, un e-mail contenant un lien de réinitialisation vient d'être
          envoyé. Le lien est valable une heure.
        </p>
        <a class="btn" routerLink="/login">Retour à la connexion</a>
      } @else {
        <p class="subtitle">
          Indiquez votre identifiant ou votre adresse e-mail : vous recevrez un lien pour choisir un
          nouveau mot de passe.
        </p>
        <form (submit)="$event.preventDefault(); submit()">
          @if (error()) {
            <p class="error" role="alert">{{ error() }}</p>
          }
          <div class="field">
            <label for="identifier">Identifiant ou e-mail</label>
            <input
              id="identifier"
              name="username"
              required
              autocomplete="username"
              autocapitalize="none"
              spellcheck="false"
              [value]="identifier()"
              (input)="identifier.set(input.value)"
              #input
            />
          </div>
          <button class="btn" type="submit" [disabled]="pending() || !identifier().trim()">
            {{ pending() ? 'Envoi…' : 'Recevoir le lien' }}
          </button>
        </form>
        <p class="switch"><a routerLink="/login">Retour à la connexion</a></p>
      }
    </main>
  `,
  styleUrl: '../auth-layout.scss',
})
export class ForgotPasswordPage {
  private readonly auth = inject(AuthService);

  protected readonly identifier = signal('');
  protected readonly pending = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal<string | null>(null);

  protected async submit(): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      await firstValueFrom(this.auth.forgotPassword(this.identifier().trim()));
      this.sent.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
