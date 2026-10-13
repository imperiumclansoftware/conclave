import {
  Component,
  ElementRef,
  Injector,
  afterNextRender,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import {
  AbstractControl,
  NonNullableFormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Emblem } from '../../shared/emblem';
import { Icon } from '../../shared/icon';
import { PASSWORD_MIN_LENGTH, USERNAME_HINT, USERNAME_PATTERN } from '../user-rules';

type Step = 'code' | 'clan' | 'leader' | 'summary' | 'done';

const STEPS: { id: Exclude<Step, 'done'>; label: string }[] = [
  { id: 'code', label: 'Code' },
  { id: 'clan', label: 'Clan' },
  { id: 'leader', label: 'Dirigeant' },
  { id: 'summary', label: 'Confirmation' },
];

/** Command giving the installation code on the server */
const CODE_COMMAND = 'php bin/console app:setup:code';

function samePasswords(group: AbstractControl): ValidationErrors | null {
  const { password, confirm } = group.value as { password: string; confirm: string };
  return confirm && password !== confirm ? { mismatch: true } : null;
}

/**
 * Installation wizard, before the clan exists: installation code, clan, first leader, then the
 * leader is logged in and pointed to the remaining settings.
 */
@Component({
  selector: 'app-setup-page',
  imports: [ReactiveFormsModule, RouterLink, Emblem, Icon],
  templateUrl: './setup.page.html',
  styleUrl: './setup.page.scss',
})
export class SetupPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly heading = viewChild<ElementRef<HTMLElement>>('heading');
  private readonly injector = inject(Injector);

  protected readonly steps = STEPS;
  protected readonly command = CODE_COMMAND;
  protected readonly passwordMinLength = PASSWORD_MIN_LENGTH;
  protected readonly usernameHint = USERNAME_HINT;
  protected readonly step = signal<Step>('code');
  protected readonly stepIndex = computed(() => STEPS.findIndex((s) => s.id === this.step()));
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly copied = signal(false);
  protected readonly showPassword = signal(false);
  /** Where the app is reached now: the address of the links in the e-mails */
  protected readonly currentUrl = new URL('.', document.baseURI).href;

  protected readonly codeForm = this.fb.group({
    code: [
      '',
      [Validators.required, Validators.pattern(/^\s*[A-Za-z2-9]{4}[\s-]?[A-Za-z2-9]{4}\s*$/)],
    ],
  });
  protected readonly clanForm = this.fb.group({
    clanName: ['', [Validators.required, Validators.maxLength(100), Validators.pattern(/\S/)]],
    appUrl: [this.currentUrl, Validators.maxLength(255)],
  });
  protected readonly leaderForm = this.fb.group(
    {
      displayName: ['', [Validators.required, Validators.maxLength(50)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
      username: ['', [Validators.required, Validators.pattern(USERNAME_PATTERN)]],
      password: ['', [Validators.required, Validators.minLength(PASSWORD_MIN_LENGTH)]],
      confirm: ['', Validators.required],
    },
    { validators: samePasswords },
  );
  private readonly clan = toSignal(this.clanForm.valueChanges, {
    initialValue: this.clanForm.getRawValue(),
  });
  private readonly leader = toSignal(this.leaderForm.valueChanges, {
    initialValue: this.leaderForm.getRawValue(),
  });
  protected readonly clanName = computed(() => this.clan().clanName?.trim() ?? '');
  protected readonly leaderName = computed(() => this.leader().displayName?.trim() ?? '');
  protected readonly summary = computed(() => ({ ...this.clan(), ...this.leader() }));

  protected async copyCommand(): Promise<void> {
    try {
      await navigator.clipboard.writeText(CODE_COMMAND);
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 2000);
    } catch {
      // Clipboard refused: the command stays visible to copy by hand
    }
  }

  protected async checkCode(): Promise<void> {
    if (this.codeForm.invalid) {
      this.codeForm.markAllAsTouched();
      return;
    }
    await this.run(async () => {
      await firstValueFrom(this.auth.verifySetupCode(this.codeForm.getRawValue().code));
      this.go('clan');
    });
  }

  protected next(form: 'clan' | 'leader'): void {
    const group = form === 'clan' ? this.clanForm : this.leaderForm;
    if (group.invalid) {
      group.markAllAsTouched();
      return;
    }
    this.go(form === 'clan' ? 'leader' : 'summary');
  }

  protected go(step: Step): void {
    this.error.set(null);
    this.step.set(step);
    // Screen readers and keyboards start at the title of the new step, once it is rendered
    afterNextRender(() => this.heading()?.nativeElement.focus(), { injector: this.injector });
  }

  protected async install(): Promise<void> {
    const { clanName, appUrl } = this.clanForm.getRawValue();
    const { displayName, email, username, password } = this.leaderForm.getRawValue();
    await this.run(async () => {
      await firstValueFrom(
        this.auth.install({
          code: this.codeForm.getRawValue().code,
          clanName: clanName.trim(),
          appUrl: appUrl.trim() || null,
          displayName: displayName.trim(),
          email: email.trim(),
          username: username.trim().toLowerCase(),
          password,
        }),
      );
      this.go('done');
    });
  }

  protected open(path: string, onglet?: string): void {
    void this.router.navigate([path], onglet ? { queryParams: { onglet } } : {});
  }

  private async run(task: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    try {
      await task();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }
}
