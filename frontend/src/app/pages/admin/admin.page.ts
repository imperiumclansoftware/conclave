import {
  Component,
  DestroyRef,
  OnInit,
  effect,
  inject,
  input,
  linkedSignal,
  signal,
  untracked,
} from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { AdminService } from '../../core/admin/admin.service';
import { apiErrorMessage } from '../../core/api/api-error';
import { AdminSettings, CheckResult, GatewayState } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { SignupsService } from '../../core/admin/signups.service';
import { DiscordCard } from '../account/discord-card';
import { AdminBranchRequests } from './admin-branch-requests';
import { AdminBranches } from './admin-branches';
import { AdminMail } from './admin-mail';
import { AdminMembers } from './admin-members';
import { Icon } from '../../shared/icon';
import { IconName } from '../../shared/icons.generated';

type Tab = 'signups' | 'members' | 'branches' | 'general' | 'mail' | 'discord' | 'catalog';

const TABS: { id: Tab; label: string; icon: IconName }[] = [
  { id: 'signups', label: 'Demandes', icon: 'pending' },
  { id: 'members', label: 'Membres', icon: 'members' },
  { id: 'branches', label: 'Branches', icon: 'branch' },
  { id: 'general', label: 'Général', icon: 'settings' },
  { id: 'mail', label: 'E-mail', icon: 'mail' },
  { id: 'discord', label: 'Discord', icon: 'discord' },
  { id: 'catalog', label: 'Catalogues', icon: 'library' },
];
type Status = { kind: 'success' | 'error'; message: string } | null;

const GATEWAY_REFRESH_MS = 10_000;

@Component({
  selector: 'app-admin-page',
  imports: [
    ReactiveFormsModule,
    AdminMembers,
    AdminBranchRequests,
    AdminBranches,
    AdminMail,
    DiscordCard,
    Icon,
  ],
  templateUrl: './admin.page.html',
  styleUrl: './admin.page.scss',
})
export class AdminPage implements OnInit {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  protected readonly me = this.auth.user;
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly signups = inject(SignupsService);
  protected readonly tabs = TABS;
  /** ?onglet=…: tab to open, e.g. from the clan page */
  readonly onglet = input<string>();
  protected readonly tab = linkedSignal<Tab>(() => {
    const requested = TABS.find((t) => t.id === this.onglet())?.id;
    // Only the URL changes the tab, not a refreshed count of sign-ups
    return requested ?? (untracked(this.signups.count) > 0 ? 'signups' : 'members');
  });
  protected readonly settings = signal<AdminSettings | null>(null);
  protected readonly generalStatus = signal<Status>(null);
  protected readonly busy = signal(false);
  protected readonly discordStatus = signal<Status>(null);
  protected readonly discordCheck = signal<CheckResult | null>(null);
  protected readonly rawgStatus = signal<Status>(null);
  protected readonly rawgCheck = signal<CheckResult | null>(null);
  protected readonly tmdbStatus = signal<Status>(null);
  protected readonly tmdbCheck = signal<CheckResult | null>(null);

  /** Redirection to declare in the Discord portal: this app's callback route. */
  protected readonly suggestedRedirectUri = new URL('discord/callback', document.baseURI).href;
  /** Public URL of this app, for the links in the e-mails */
  protected readonly suggestedAppUrl = new URL('.', document.baseURI).href;

  protected readonly generalForm = this.fb.group({ clanName: [''], appUrl: [''] });

  protected readonly discordForm = this.fb.group({
    clientId: [''],
    clientSecret: [''],
    botToken: [''],
    redirectUri: [''],
  });
  protected readonly rawgForm = this.fb.group({ apiKey: [''] });
  protected readonly tmdbForm = this.fb.group({ apiKey: [''] });

  /** The leader picked a tab: the page no longer chooses for them */
  private tabChosen = false;

  constructor() {
    const timer = setInterval(() => void this.refreshGateway(), GATEWAY_REFRESH_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(timer));

    // Requests counted after the page opened (direct link, reload): show them first
    effect(() => {
      if (
        this.signups.count() > 0 &&
        !this.tabChosen &&
        !this.onglet() &&
        untracked(this.tab) === 'members'
      ) {
        this.tab.set('signups');
      }
    });
  }

  async ngOnInit(): Promise<void> {
    try {
      this.apply(await firstValueFrom(this.admin.settings()));
    } catch (e) {
      this.discordStatus.set({ kind: 'error', message: apiErrorMessage(e) });
    }
  }

  protected selectTab(tab: Tab): void {
    this.tabChosen = true;
    this.tab.set(tab);
  }

  protected onTabKey(event: KeyboardEvent): void {
    const tabs = TABS.map((t) => t.id);
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step) {
      const next = tabs[(tabs.indexOf(this.tab()) + step + tabs.length) % tabs.length];
      this.tabChosen = true;
      this.tab.set(next);
      document.getElementById(`tab-${next}`)?.focus();
    }
  }

  protected useSuggestedRedirect(): void {
    this.discordForm.controls.redirectUri.setValue(this.suggestedRedirectUri);
  }

  protected async saveDiscord(): Promise<void> {
    const { clientId, clientSecret, botToken, redirectUri } = this.discordForm.getRawValue();
    await this.run(this.discordStatus, async () => {
      this.apply(
        await firstValueFrom(
          this.admin.saveDiscord({
            clientId: clientId.trim(),
            redirectUri: redirectUri.trim(),
            // Empty secret fields keep the saved values
            clientSecret: clientSecret.trim() || null,
            botToken: botToken.trim() || null,
          }),
        ),
      );
      this.discordCheck.set(null);
      return 'Configuration Discord enregistrée. Le worker se reconnecte avec les nouveaux paramètres.';
    });
  }

  protected async clearSecret(field: 'clientSecret' | 'botToken'): Promise<void> {
    if (!confirm('Effacer cette valeur ? Celle du fichier .env sera utilisée si elle existe.')) {
      return;
    }
    const { clientId, redirectUri } = this.discordForm.getRawValue();
    await this.run(this.discordStatus, async () => {
      this.apply(
        await firstValueFrom(
          this.admin.saveDiscord({
            clientId,
            redirectUri,
            clientSecret: field === 'clientSecret' ? '' : null,
            botToken: field === 'botToken' ? '' : null,
          }),
        ),
      );
      return 'Valeur effacée.';
    });
  }

  protected async testDiscord(): Promise<void> {
    await this.run(this.discordStatus, async () => {
      this.discordCheck.set(await firstValueFrom(this.admin.testDiscord()));
      return '';
    });
  }

  protected async saveRawg(clear = false): Promise<void> {
    const apiKey = clear ? '' : this.rawgForm.getRawValue().apiKey.trim();
    if (!clear && !apiKey) {
      return;
    }
    await this.run(this.rawgStatus, async () => {
      this.apply(await firstValueFrom(this.admin.saveRawg(apiKey)));
      this.rawgCheck.set(null);
      return clear ? 'Clé retirée : la recherche utilise Steam.' : 'Clé RAWG enregistrée.';
    });
  }

  protected async testRawg(): Promise<void> {
    await this.run(this.rawgStatus, async () => {
      this.rawgCheck.set(await firstValueFrom(this.admin.testRawg()));
      return '';
    });
  }

  protected async saveTmdb(clear = false): Promise<void> {
    const apiKey = clear ? '' : this.tmdbForm.getRawValue().apiKey.trim();
    if (!clear && !apiKey) {
      return;
    }
    await this.run(this.tmdbStatus, async () => {
      this.apply(await firstValueFrom(this.admin.saveTmdb(apiKey)));
      this.tmdbCheck.set(null);
      return clear ? 'Clé TMDB retirée.' : 'Clé TMDB enregistrée.';
    });
  }

  protected async testTmdb(): Promise<void> {
    await this.run(this.tmdbStatus, async () => {
      this.tmdbCheck.set(await firstValueFrom(this.admin.testTmdb()));
      return '';
    });
  }

  protected useSuggestedAppUrl(): void {
    this.generalForm.controls.appUrl.setValue(this.suggestedAppUrl);
  }

  protected async saveGeneral(): Promise<void> {
    const { clanName, appUrl } = this.generalForm.getRawValue();
    await this.run(this.generalStatus, async () => {
      this.apply(await firstValueFrom(this.admin.saveGeneral(clanName.trim(), appUrl.trim())));
      this.auth.updateUser({ clan: { ...this.me()!.clan, name: clanName.trim() } });
      return 'Paramètres enregistrés.';
    });
  }

  protected gatewayLabel(gateway: GatewayState): string {
    switch (gateway.state) {
      case 'connected':
        return `Connecté${gateway.botName ? ` en tant que ${gateway.botName}` : ''}${gateway.guilds !== null ? ` · ${gateway.guilds} serveur(s)` : ''}`;
      case 'connecting':
        return 'Connexion en cours…';
      case 'waiting-config':
        return 'En attente de configuration';
      case 'error':
        return gateway.detail ?? 'Erreur';
      default:
        return 'Arrêté';
    }
  }

  private async refreshGateway(): Promise<void> {
    if (this.tab() !== 'discord' || document.visibilityState !== 'visible') {
      return;
    }
    try {
      const fresh = await firstValueFrom(this.admin.settings());
      this.settings.update((s) =>
        s ? { ...s, discord: { ...s.discord, gateway: fresh.discord.gateway } } : fresh,
      );
    } catch {
      // Keep the last known state
    }
  }

  protected apply(settings: AdminSettings): void {
    this.settings.set(settings);
    this.generalForm.setValue({
      clanName: settings.general.clanName ?? '',
      appUrl: settings.general.appUrl.value ?? '',
    });
    this.discordForm.setValue({
      clientId: settings.discord.clientId.value ?? '',
      clientSecret: '',
      botToken: '',
      redirectUri: settings.discord.redirectUri.value ?? '',
    });
    this.rawgForm.setValue({ apiKey: '' });
    this.tmdbForm.setValue({ apiKey: '' });
  }

  private async run(
    status: ReturnType<typeof signal<Status>>,
    action: () => Promise<string>,
  ): Promise<void> {
    this.busy.set(true);
    status.set(null);
    try {
      const message = await action();
      status.set(message ? { kind: 'success', message } : null);
    } catch (e) {
      status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(false);
    }
  }
}
