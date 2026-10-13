import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { apiErrorMessage } from '../../core/api/api-error';
import { Branch } from '../../core/api/models';
import { ChatStore } from '../../core/chat/chat.store';
import { BranchesService } from '../../core/clan/branches.service';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';

type Status = { kind: 'success' | 'error'; message: string } | null;

/** Must match Branch::NAME_MAX_LENGTH and DESCRIPTION_MAX_LENGTH on the API. */
const NAME_MAX = 50;
const DESCRIPTION_MAX = 280;

/** Branches of the app's catalogue: activation, name and options. */
@Component({
  selector: 'app-admin-branches',
  imports: [ReactiveFormsModule, BranchCrest, Icon],
  templateUrl: './admin-branches.html',
  styleUrl: './admin-branches.scss',
})
export class AdminBranches implements OnInit {
  private readonly chat = inject(ChatStore);
  private readonly branches = inject(BranchesService);

  protected readonly nameMax = NAME_MAX;
  protected readonly descriptionMax = DESCRIPTION_MAX;
  /** Every branch, active or not */
  protected readonly all = signal<Branch[]>([]);
  protected readonly loaded = signal(false);
  protected readonly activeCount = computed(() => this.all().filter((b) => b.active).length);
  protected readonly busy = signal<number | null>(null);
  protected readonly status = signal<Status>(null);
  /** Branch being edited */
  protected readonly editing = signal<number | null>(null);

  protected readonly editForm = inject(NonNullableFormBuilder).group({
    name: ['', [Validators.required, Validators.maxLength(NAME_MAX), Validators.pattern(/\S/)]],
    description: ['', Validators.maxLength(DESCRIPTION_MAX)],
    publishesNews: [false],
    approvalRequired: [false],
  });

  async ngOnInit(): Promise<void> {
    try {
      this.all.set(await this.branches.all());
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.loaded.set(true);
    }
  }

  protected memberNames(branch: Branch): string {
    const me = this.chat.me();
    return branch.memberIds
      .map((id) => (id === me?.id ? me.displayName : this.chat.member(id)?.displayName))
      .filter((name): name is string => !!name)
      .join(', ');
  }

  protected async toggle(branch: Branch): Promise<void> {
    const active = !branch.active;
    await this.run(branch.id, async () => {
      this.replace(await this.branches.setActive(branch.id, active));
      return active
        ? `Branche « ${branch.name} » activée : les membres peuvent la rejoindre.`
        : `Branche « ${branch.name} » désactivée : elle est masquée, ses membres sont conservés.`;
    });
  }

  protected edit(branch: Branch): void {
    this.editForm.reset({
      name: branch.name,
      description: branch.description ?? '',
      publishesNews: branch.publishesNews,
      approvalRequired: branch.approvalRequired,
    });
    this.editing.set(branch.id);
    this.status.set(null);
    queueMicrotask(() => document.getElementById(`branch-name-${branch.id}`)?.focus());
  }

  protected cancelEdit(id: number): void {
    this.editing.set(null);
    queueMicrotask(() => document.getElementById(`branch-edit-${id}`)?.focus());
  }

  protected async save(branch: Branch): Promise<void> {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }
    const { name, description, publishesNews, approvalRequired } = this.editForm.getRawValue();
    await this.run(branch.id, async () => {
      this.replace(
        await this.branches.update(branch.id, {
          name: name.trim(),
          description: description.trim() || null,
          publishesNews,
          approvalRequired,
        }),
      );
      this.editing.set(null);
      return 'Branche enregistrée.';
    });
  }

  private replace(branch: Branch): void {
    this.all.update((list) => list.map((b) => (b.id === branch.id ? branch : b)));
  }

  private async run(id: number, task: () => Promise<string>): Promise<void> {
    this.busy.set(id);
    this.status.set(null);
    try {
      this.status.set({ kind: 'success', message: await task() });
    } catch (e) {
      this.status.set({ kind: 'error', message: apiErrorMessage(e) });
    } finally {
      this.busy.set(null);
    }
  }
}
