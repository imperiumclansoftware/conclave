import { Component, OnInit, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AvatarSettings, AvatarSource, GamerNetwork, Profile } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { AvatarChange, ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { Avatar } from '../../shared/avatar';
import { Emblem } from '../../shared/emblem';
import { ManageCookies } from '../../shared/manage-cookies';
import { PresenceBadge } from '../../shared/presence-badge';
import { PASSWORD_MIN_LENGTH } from '../user-rules';
import { DiscordCard } from './discord-card';
import { MyBranches } from './my-branches';
import { Icon } from '../../shared/icon';

const FLASH: Record<string, string> = {
  link: 'Votre compte Discord est lié.',
  install: 'Le serveur Discord du clan est connecté.',
};
const MAX_AVATAR_BYTES = 5 * 1024 * 1024;
const NETWORKS: GamerNetwork[] = ['psn', 'xbox', 'steam', 'nintendo', 'epic', 'battlenet'];

type Status = { kind: 'success' | 'error'; message: string } | null;

const TABS = [
  { id: 'profil', label: 'Profil', icon: 'profile' },
  { id: 'avatar', label: 'Avatar', icon: 'avatar' },
  { id: 'branches', label: 'Branches', icon: 'branch' },
  { id: 'apparence', label: 'Apparence', icon: 'appearance' },
  { id: 'securite', label: 'Sécurité', icon: 'security' },
  { id: 'discord', label: 'Discord', icon: 'discord' },
] as const;
type Tab = (typeof TABS)[number]['id'];

@Component({
  selector: 'app-account-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    DiscordCard,
    MyBranches,
    PresenceBadge,
    Avatar,
    ManageCookies,
    Emblem,
    Icon,
  ],
  templateUrl: './account.page.html',
  styleUrl: './account.page.scss',
})
export class AccountPage implements OnInit {
  protected readonly auth = inject(AuthService);
  private readonly profiles = inject(ProfileService);
  private readonly fb = inject(NonNullableFormBuilder);

  private readonly router = inject(Router);

  /** Query parameter set after the Discord authorization (bound by the router). */
  readonly discord = input<string>();
  /** Query parameter selecting the tab, so that each tab has its own link (bound by the router). */
  readonly onglet = input<string>();

  protected readonly tabs = TABS;
  protected readonly tab = linkedSignal<Tab>(() => {
    const requested = TABS.find((t) => t.id === this.onglet())?.id;
    // Back from Discord: show the result of the authorization
    return requested ?? (this.discord() ? 'discord' : 'profil');
  });
  protected readonly flash = computed(() => FLASH[this.discord() ?? ''] ?? null);

  private readonly theme = inject(ThemeService);
  /** Faction currently displayed (resolved from the device's setting when following it) */
  protected readonly shownFaction = this.theme.faction;
  protected readonly factionStatus = signal<Status>(null);

  protected readonly profile = signal<Profile | null>(null);
  protected readonly avatar = computed(() => this.profile()?.avatar ?? null);
  protected readonly gravatarEmail = signal('');
  protected readonly avatarChoices = computed(() => {
    const avatar = this.avatar();
    if (!avatar) {
      return [];
    }
    const choices: { source: AvatarSource; label: string; url: string | null }[] = [];
    if (avatar.available.upload) {
      choices.push({ source: 'upload', label: 'Image envoyée', url: avatar.available.upload });
    }
    if (avatar.available.gravatar) {
      choices.push({ source: 'gravatar', label: 'Gravatar', url: avatar.available.gravatar });
    }
    if (avatar.available.discord) {
      choices.push({ source: 'discord', label: 'Discord', url: avatar.available.discord });
    }
    choices.push({ source: 'initials', label: 'Initiale', url: null });
    return choices;
  });
  protected readonly avatarStatus = signal<Status>(null);
  protected readonly profileStatus = signal<Status>(null);
  protected readonly passwordStatus = signal<Status>(null);
  protected readonly busy = signal<'avatar' | 'profile' | 'password' | null>(null);
  protected readonly bioMax = 280;
  protected readonly passwordMinLength = PASSWORD_MIN_LENGTH;

  protected readonly form = this.fb.group({
    displayName: ['', [Validators.required, Validators.maxLength(50)]],
    email: ['', [Validators.email, Validators.maxLength(180)]],
    bio: ['', Validators.maxLength(280)],
    gamerTags: this.fb.group({
      psn: ['', Validators.maxLength(50)],
      xbox: ['', Validators.maxLength(50)],
      steam: ['', Validators.maxLength(50)],
      nintendo: ['', Validators.maxLength(50)],
      epic: ['', Validators.maxLength(50)],
      battlenet: ['', Validators.maxLength(50)],
    }),
  });

  protected readonly passwordForm = this.fb.group({
    currentPassword: ['', Validators.required],
    newPassword: ['', [Validators.required, Validators.minLength(PASSWORD_MIN_LENGTH)]],
  });

  protected selectTab(tab: Tab): void {
    this.tab.set(tab);
    void this.router.navigate([], { queryParams: { onglet: tab }, replaceUrl: true });
  }

  protected onTabKey(event: KeyboardEvent): void {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step) {
      const index = TABS.findIndex((t) => t.id === this.tab());
      const next = TABS[(index + step + TABS.length) % TABS.length].id;
      this.selectTab(next);
      document.getElementById(`tab-${next}`)?.focus();
    }
  }

  async ngOnInit(): Promise<void> {
    try {
      const profile = await firstValueFrom(this.profiles.mine());
      this.profile.set(profile);
      this.form.setValue({
        displayName: profile.displayName,
        email: profile.email ?? '',
        bio: profile.bio ?? '',
        gamerTags: Object.fromEntries(
          NETWORKS.map((n) => [n, profile.gamerTags[n] ?? '']),
        ) as Record<GamerNetwork, string>,
      });
    } catch (e) {
      this.profileStatus.set({ kind: 'error', message: apiErrorMessage(e) });
    }
  }

  protected async onAvatarSelected(input: HTMLInputElement): Promise<void> {
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
      return;
    }
    if (file.size > MAX_AVATAR_BYTES) {
      this.avatarStatus.set({ kind: 'error', message: 'Image trop lourde (5 Mo maximum).' });
      return;
    }
    await this.run('avatar', this.avatarStatus, async () => {
      this.applyAvatar(await firstValueFrom(this.profiles.uploadAvatar(file)));
      return 'Avatar mis à jour.';
    });
  }

  protected async removeAvatar(): Promise<void> {
    await this.run('avatar', this.avatarStatus, async () => {
      this.applyAvatar(await firstValueFrom(this.profiles.removeAvatar()));
      return 'Image retirée.';
    });
  }

  protected async useGravatar(): Promise<void> {
    const email = this.gravatarEmail().trim();
    if (!email) {
      return;
    }
    await this.run('avatar', this.avatarStatus, async () => {
      this.applyAvatar(await firstValueFrom(this.profiles.useGravatar(email)));
      this.gravatarEmail.set('');
      return 'Gravatar associé.';
    });
  }

  protected async removeGravatar(): Promise<void> {
    await this.run('avatar', this.avatarStatus, async () => {
      this.applyAvatar(await firstValueFrom(this.profiles.removeGravatar()));
      return 'Gravatar retiré.';
    });
  }

  protected async chooseAvatar(source: AvatarSource): Promise<void> {
    await this.run('avatar', this.avatarStatus, async () => {
      this.applyAvatar(await firstValueFrom(this.profiles.chooseAvatar(source)));
      return 'Avatar mis à jour.';
    });
  }

  protected async chooseFaction(
    faction: 'assassin' | 'templar' | null,
    event: Event,
  ): Promise<void> {
    const rect = (event.target as HTMLElement).closest('label')?.getBoundingClientRect();
    this.theme.setPreference(faction, {
      origin: rect ? { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 } : undefined,
    });
    this.factionStatus.set(null);
    try {
      await firstValueFrom(this.profiles.setFaction(faction));
      this.factionStatus.set({
        kind: 'success',
        message: 'Préférence enregistrée pour tous vos appareils.',
      });
    } catch (e) {
      this.factionStatus.set({ kind: 'error', message: apiErrorMessage(e) });
    }
  }

  private applyAvatar(change: AvatarChange): void {
    this.profile.update((p) =>
      p ? { ...p, avatarUrl: change.avatarUrl, avatar: change.avatar } : p,
    );
  }

  protected async saveProfile(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    await this.run('profile', this.profileStatus, async () => {
      const { displayName, email, bio, gamerTags } = this.form.getRawValue();
      const profile = await firstValueFrom(
        this.profiles.update({ displayName, email: email || null, bio: bio || null, gamerTags }),
      );
      this.profile.update((p) => ({ ...profile, avatar: p?.avatar }));
      this.auth.updateUser({ email: profile.email ?? null });
      return 'Profil enregistré.';
    });
  }

  protected async changePassword(): Promise<void> {
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }
    await this.run('password', this.passwordStatus, async () => {
      const { currentPassword, newPassword } = this.passwordForm.getRawValue();
      await firstValueFrom(this.profiles.changePassword(currentPassword, newPassword));
      this.passwordForm.reset();
      return 'Mot de passe modifié.';
    });
  }

  private async run(
    task: 'avatar' | 'profile' | 'password',
    status: ReturnType<typeof signal<Status>>,
    action: () => Promise<string>,
  ): Promise<void> {
    this.busy.set(task);
    status.set(null);
    try {
      status.set({ kind: 'success', message: await action() });
    } catch (e) {
      status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(null);
    }
  }
}
