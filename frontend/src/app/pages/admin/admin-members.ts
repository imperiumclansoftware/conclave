import { Component, OnInit, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AdminService } from '../../core/admin/admin.service';
import { SignupsService } from '../../core/admin/signups.service';
import { apiErrorMessage } from '../../core/api/api-error';
import { AdminMember, ClanRole } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { Avatar } from '../../shared/avatar';
import { PASSWORD_MIN_LENGTH, USERNAME_HINT, USERNAME_PATTERN } from '../user-rules';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';

type Status = { kind: 'success' | 'error'; message: string } | null;

/** Sign-up requests or clan members, depending on the view. */
@Component({
  selector: 'app-admin-members',
  imports: [MemberCardDirective, ReactiveFormsModule, RouterLink, Avatar, Icon],
  templateUrl: './admin-members.html',
  styleUrl: './admin-members.scss',
})
export class AdminMembers implements OnInit {
  private readonly admin = inject(AdminService);
  private readonly signupsCount = inject(SignupsService);
  protected readonly me = inject(AuthService).user;

  readonly view = input.required<'signups' | 'members'>();

  protected readonly signups = signal<AdminMember[]>([]);
  protected readonly members = signal<AdminMember[]>([]);
  protected readonly loaded = signal(false);
  protected readonly busy = signal<number | 'add' | null>(null);
  protected readonly status = signal<Status>(null);
  protected readonly passwordMinLength = PASSWORD_MIN_LENGTH;
  protected readonly usernameHint = USERNAME_HINT;

  protected readonly addForm = inject(NonNullableFormBuilder).group({
    displayName: ['', [Validators.required, Validators.maxLength(50)]],
    username: ['', [Validators.required, Validators.pattern(USERNAME_PATTERN)]],
    email: ['', [Validators.email, Validators.maxLength(180)]],
    password: ['', [Validators.required, Validators.minLength(PASSWORD_MIN_LENGTH)]],
    role: ['member' as ClanRole, Validators.required],
  });

  async ngOnInit(): Promise<void> {
    await this.load();
  }

  async load(): Promise<void> {
    try {
      const [signups, members] = await Promise.all([
        firstValueFrom(this.admin.signups()),
        firstValueFrom(this.admin.members()),
      ]);
      this.signups.set(signups);
      this.members.set(members);
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.loaded.set(true);
    }
  }

  protected async approve(user: AdminMember): Promise<void> {
    await this.run(user.id, async () => {
      const approved = await firstValueFrom(this.admin.approve(user.id));
      this.signups.update((list) => list.filter((u) => u.id !== user.id));
      this.members.update((list) =>
        [...list, approved].sort((a, b) => a.displayName.localeCompare(b.displayName)),
      );
      void this.signupsCount.refresh();
      return `${user.displayName} a rejoint le clan.`;
    });
  }

  protected async reject(user: AdminMember): Promise<void> {
    if (!confirm(`Refuser la demande de ${user.displayName} ? Son compte sera supprimé.`)) {
      return;
    }
    await this.run(user.id, async () => {
      await firstValueFrom(this.admin.reject(user.id));
      this.signups.update((list) => list.filter((u) => u.id !== user.id));
      void this.signupsCount.refresh();
      return `Demande de ${user.displayName} refusée.`;
    });
  }

  protected async changeRole(user: AdminMember, select: HTMLSelectElement): Promise<void> {
    const role = select.value as ClanRole;
    await this.run(user.id, async () => {
      const updated = await firstValueFrom(this.admin.changeRole(user.id, role));
      this.members.update((list) => list.map((u) => (u.id === updated.id ? updated : u)));
      return `${updated.displayName} est maintenant ${updated.role === 'leader' ? 'dirigeant' : 'membre'}.`;
    });
    // On failure, show the actual role again
    select.value = this.members().find((u) => u.id === user.id)?.role ?? select.value;
  }

  protected async remove(user: AdminMember): Promise<void> {
    if (
      !confirm(
        `Retirer ${user.displayName} du clan ? Son compte, ses messages et ses jeux seront supprimés.`,
      )
    ) {
      return;
    }
    await this.run(user.id, async () => {
      await firstValueFrom(this.admin.removeMember(user.id));
      this.members.update((list) => list.filter((u) => u.id !== user.id));
      return `${user.displayName} a été retiré du clan.`;
    });
  }

  protected async add(): Promise<void> {
    if (this.addForm.invalid) {
      this.addForm.markAllAsTouched();
      return;
    }
    await this.run('add', async () => {
      const value = this.addForm.getRawValue();
      const member = await firstValueFrom(
        this.admin.addMember({
          ...value,
          username: value.username.trim().toLowerCase(),
          email: value.email.trim() || null,
        }),
      );
      this.members.update((list) =>
        [...list, member].sort((a, b) => a.displayName.localeCompare(b.displayName)),
      );
      this.addForm.reset();
      return `${member.displayName} peut se connecter avec l’identifiant « ${member.username} ».`;
    });
  }

  protected since(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(iso),
    );
  }

  private async run(task: number | 'add', action: () => Promise<string>): Promise<void> {
    this.busy.set(task);
    this.status.set(null);
    try {
      this.status.set({ kind: 'success', message: await action() });
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(null);
    }
  }
}
