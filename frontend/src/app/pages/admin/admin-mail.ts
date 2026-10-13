import {
  Component,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
  untracked,
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormControl, NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { AdminService } from '../../core/admin/admin.service';
import { apiErrorMessage } from '../../core/api/api-error';
import {
  AdminSettings,
  CheckResult,
  MailMode,
  MailSecurity,
  MailSettings,
  MailSettingsInput,
} from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';

type Status = { kind: 'success' | 'error'; message: string } | null;

interface Provider {
  id: string;
  label: string;
  host: string;
  port: number;
  security: MailSecurity;
  hint: string;
  link?: { url: string; label: string };
}

/** Usual settings of common providers, to avoid looking them up. */
const PROVIDERS: Provider[] = [
  {
    id: 'gmail',
    label: 'Gmail / Google Workspace',
    host: 'smtp.gmail.com',
    port: 587,
    security: 'starttls',
    hint: 'Identifiant : l’adresse Gmail. Mot de passe : un mot de passe d’application (validation en deux étapes requise). Gmail expédie toujours depuis l’adresse du compte.',
    link: {
      url: 'https://myaccount.google.com/apppasswords',
      label: 'Créer un mot de passe d’application',
    },
  },
  {
    id: 'ovh',
    label: 'OVHcloud (MX Plan, E-mail Pro)',
    host: 'ssl0.ovh.net',
    port: 465,
    security: 'tls',
    hint: 'Identifiant : l’adresse e-mail complète.',
  },
  {
    id: 'ionos',
    label: 'IONOS',
    host: 'smtp.ionos.fr',
    port: 465,
    security: 'tls',
    hint: 'Identifiant : l’adresse e-mail complète.',
  },
  {
    id: 'orange',
    label: 'Orange',
    host: 'smtp.orange.fr',
    port: 465,
    security: 'tls',
    hint: 'Identifiant et mot de passe de votre messagerie Orange.',
  },
  {
    id: 'free',
    label: 'Free',
    host: 'smtp.free.fr',
    port: 465,
    security: 'tls',
    hint: 'Identifiant et mot de passe de votre messagerie Free.',
  },
  {
    id: 'brevo',
    label: 'Brevo',
    host: 'smtp-relay.brevo.com',
    port: 587,
    security: 'starttls',
    hint: 'Identifiant et clé SMTP : Brevo › SMTP & API. L’adresse d’expédition doit être validée dans Brevo.',
  },
  {
    id: 'mailjet',
    label: 'Mailjet',
    host: 'in-v3.mailjet.com',
    port: 587,
    security: 'starttls',
    hint: 'Identifiant : la clé API ; mot de passe : la clé secrète. L’adresse d’expédition doit être validée dans Mailjet.',
  },
  {
    id: 'mailpit',
    label: 'Mailpit (tests)',
    host: 'localhost',
    port: 1025,
    security: 'none',
    hint: 'Rien n’est vraiment envoyé : les e-mails sont capturés et lisibles dans Mailpit.',
  },
];

const DEFAULT_PORTS: Record<MailSecurity, number> = { starttls: 587, tls: 465, none: 25 };

const MODES: { id: MailMode; label: string }[] = [
  { id: 'smtp', label: 'Serveur SMTP' },
  { id: 'sendmail', label: 'Sendmail (serveur local)' },
  { id: 'custom', label: 'Avancé (DSN)' },
  { id: 'disabled', label: 'Désactivé' },
];

/** Mail service of the administration: server, sender and test. */
@Component({
  selector: 'app-admin-mail',
  imports: [ReactiveFormsModule],
  templateUrl: './admin-mail.html',
  styleUrl: './admin-mail.scss',
})
export class AdminMail {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);

  readonly mail = input.required<MailSettings>();
  /** The settings after a change, for the page */
  readonly saved = output<AdminSettings>();

  protected readonly providers = PROVIDERS;
  protected readonly modes = MODES;
  protected readonly clanName = computed(() => this.auth.user()?.clan.name ?? 'le clan');
  protected readonly busy = signal<'save' | 'test' | null>(null);
  protected readonly status = signal<Status>(null);
  protected readonly check = signal<CheckResult | null>(null);
  /** The saved password is removed on the next save */
  protected readonly clearPassword = signal(false);
  protected readonly showPassword = signal(false);
  /** The form is being filled with the saved settings: no automatic adjustment */
  private resetting = false;

  protected readonly form = inject(NonNullableFormBuilder).group({
    mode: ['smtp' as MailMode],
    provider: [''],
    host: [''],
    port: new FormControl<number | null>(null),
    security: ['starttls' as MailSecurity],
    username: [''],
    password: [''],
    acceptAnyCertificate: [false],
    dsn: [''],
    from: [''],
    fromName: [''],
    recipient: [''],
  });

  private readonly value = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });
  protected readonly mode = computed(() => this.value().mode);
  protected readonly provider = computed(
    () => PROVIDERS.find((p) => p.id === this.value().provider) ?? null,
  );
  /** The saved password is only reused for the same account on the same server */
  protected readonly passwordKept = computed(() => {
    const server = this.mail().server;
    const value = this.value();
    return (
      server.passwordSet &&
      !this.clearPassword() &&
      server.host === (value.host ?? '').trim().toLowerCase() &&
      (server.username ?? '') === (value.username ?? '').trim()
    );
  });
  protected readonly passwordDropped = computed(
    () => this.mail().server.passwordSet && !this.passwordKept() && !this.clearPassword(),
  );

  constructor() {
    // The form shows the saved settings, again after each save
    effect(() => {
      const mail = this.mail();
      untracked(() => this.reset(mail));
    });

    // A provider fills in its server
    this.form.controls.provider.valueChanges.subscribe((id) => {
      const provider = PROVIDERS.find((p) => p.id === id);
      if (provider && !this.resetting) {
        this.form.patchValue({ host: provider.host, security: provider.security });
        this.form.controls.port.setValue(provider.port);
      }
    });

    // The port follows the encryption while it is a default one
    let previous = this.form.controls.security.value;
    this.form.controls.security.valueChanges.subscribe((security) => {
      const port = this.form.controls.port.value;
      if (!this.resetting && (port === null || port === DEFAULT_PORTS[previous])) {
        this.form.controls.port.setValue(DEFAULT_PORTS[security]);
      }
      previous = security;
    });

    // Typing another server no longer matches the chosen provider
    this.form.controls.host.valueChanges.subscribe((host) => {
      const provider = PROVIDERS.find((p) => p.id === this.form.controls.provider.value);
      if (provider && !this.resetting && provider.host !== host.trim().toLowerCase()) {
        this.form.controls.provider.setValue('');
      }
    });
  }

  protected async save(): Promise<void> {
    await this.run('save', async () => {
      this.saved.emit(await firstValueFrom(this.admin.saveMail(this.input())));
      return this.mode() === 'disabled'
        ? 'Envoi d’e-mails désactivé.'
        : 'Configuration du mail enregistrée.';
    });
  }

  protected async test(): Promise<void> {
    this.check.set(null);
    await this.run('test', async () => {
      this.check.set(
        await firstValueFrom(
          this.admin.testMail(this.input(), this.form.controls.recipient.value.trim() || null),
        ),
      );
      return '';
    });
  }

  protected async resetToEnv(): Promise<void> {
    if (!confirm('Revenir au serveur défini dans le fichier .env ?')) {
      return;
    }
    await this.run('save', async () => {
      this.saved.emit(await firstValueFrom(this.admin.resetMailServer()));
      return 'Serveur du fichier .env rétabli.';
    });
  }

  protected removePassword(): void {
    this.clearPassword.set(true);
    this.form.controls.password.setValue('');
  }

  private input(): MailSettingsInput {
    const v = this.form.getRawValue();
    const text = (value: string) => value.trim() || null;
    return {
      mode: v.mode,
      host: text(v.host),
      port: v.port,
      security: v.security,
      username: text(v.username),
      // null keeps the saved password, "" removes it
      password: v.password !== '' ? v.password : this.clearPassword() ? '' : null,
      verifyPeer: !v.acceptAnyCertificate,
      dsn: text(v.dsn),
      from: text(v.from),
      fromName: text(v.fromName),
    };
  }

  private reset(mail: MailSettings): void {
    const server = mail.server;
    this.resetting = true;
    this.form.reset({
      mode: server.mode,
      provider: PROVIDERS.find((p) => p.host === server.host)?.id ?? '',
      host: server.host ?? '',
      port:
        server.port ??
        (server.mode === 'smtp' ? DEFAULT_PORTS[server.security] : DEFAULT_PORTS.starttls),
      security: server.security,
      username: server.username ?? '',
      password: '',
      acceptAnyCertificate: !server.verifyPeer,
      dsn: '',
      from: mail.from.value ?? '',
      fromName: mail.fromName.value ?? '',
      recipient: this.form.controls.recipient.value || (this.auth.user()?.email ?? ''),
    });
    this.resetting = false;
    this.clearPassword.set(false);
  }

  private async run(action: 'save' | 'test', task: () => Promise<string>): Promise<void> {
    this.busy.set(action);
    this.status.set(null);
    try {
      const message = await task();
      this.status.set(message ? { kind: 'success', message } : null);
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(null);
    }
  }
}
