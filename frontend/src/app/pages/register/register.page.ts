import { Component, OnInit, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { SetupStatus } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { Emblem } from '../../shared/emblem';
import { PASSWORD_MIN_LENGTH, USERNAME_HINT, USERNAME_PATTERN } from '../user-rules';

@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, Emblem],
  templateUrl: './register.page.html',
  styleUrl: '../auth-layout.scss',
})
export class RegisterPage implements OnInit {
  private readonly auth = inject(AuthService);

  protected readonly passwordMinLength = PASSWORD_MIN_LENGTH;
  protected readonly usernameHint = USERNAME_HINT;
  protected readonly form = inject(NonNullableFormBuilder).group({
    displayName: ['', [Validators.required, Validators.maxLength(50)]],
    username: ['', [Validators.required, Validators.pattern(USERNAME_PATTERN)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    password: ['', [Validators.required, Validators.minLength(PASSWORD_MIN_LENGTH)]],
  });
  protected readonly setup = signal<SetupStatus | null>(null);
  protected readonly showPassword = signal(false);
  protected readonly pending = signal(false);
  protected readonly requested = signal(false);
  protected readonly error = signal<string | null>(null);

  async ngOnInit(): Promise<void> {
    try {
      this.setup.set(await firstValueFrom(this.auth.setup()));
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }

  protected async submit(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.pending.set(true);
    this.error.set(null);
    try {
      const value = this.form.getRawValue();
      await firstValueFrom(
        this.auth.register({ ...value, username: value.username.trim().toLowerCase() }),
      );
      this.requested.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
