import { Component, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { PASSWORD_MIN_LENGTH } from '../user-rules';

@Component({
  selector: 'app-reset-password-page',
  imports: [RouterLink],
  template: `
    <main class="card">
      <h1>Nouveau mot de passe</h1>
      @if (done()) {
        <p class="subtitle" role="status">
          Votre mot de passe a été modifié. Vous pouvez vous connecter.
        </p>
        <a class="btn" routerLink="/login">Se connecter</a>
      } @else if (!token()) {
        <p class="error" role="alert">Lien incomplet. Faites une nouvelle demande.</p>
        <a class="btn" routerLink="/forgot-password">Mot de passe oublié</a>
      } @else {
        <form (submit)="$event.preventDefault(); submit()">
          @if (error()) {
            <p class="error" role="alert">{{ error() }}</p>
          }
          <div class="field">
            <label for="new-password">Nouveau mot de passe</label>
            <input
              id="new-password"
              type="password"
              required
              autocomplete="new-password"
              [attr.minlength]="minLength"
              aria-describedby="password-hint"
              [value]="password()"
              (input)="password.set(input.value)"
              #input
            />
            <span class="hint" id="password-hint">Au moins {{ minLength }} caractères.</span>
          </div>
          <button class="btn" type="submit" [disabled]="pending() || password().length < minLength">
            {{ pending() ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </form>
      }
    </main>
  `,
  styleUrl: '../auth-layout.scss',
})
export class ResetPasswordPage {
  private readonly auth = inject(AuthService);

  /** Query parameter of the e-mailed link (bound by the router). */
  readonly token = input<string>();

  protected readonly minLength = PASSWORD_MIN_LENGTH;
  protected readonly password = signal('');
  protected readonly pending = signal(false);
  protected readonly done = signal(false);
  protected readonly error = signal<string | null>(null);

  protected async submit(): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      await firstValueFrom(this.auth.resetPassword(this.token() ?? '', this.password()));
      this.done.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
