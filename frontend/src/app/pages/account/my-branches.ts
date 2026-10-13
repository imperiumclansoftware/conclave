import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { Branch } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { BranchesService } from '../../core/clan/branches.service';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';

/** The member chooses their branches: join, leave, or ask a leader for the validated ones. */
@Component({
  selector: 'app-my-branches',
  imports: [RouterLink, BranchCrest, Icon],
  templateUrl: './my-branches.html',
  styleUrl: './my-branches.scss',
})
export class MyBranches implements OnInit {
  protected readonly auth = inject(AuthService);
  protected readonly branches = inject(BranchesService);

  protected readonly busy = signal<number | null>(null);
  protected readonly error = signal<string | null>(null);
  private readonly myId = computed(() => this.auth.user()?.id ?? null);
  protected readonly joinedCount = computed(
    () => this.branches.branches().filter((b) => this.isIn(b.memberIds)).length,
  );

  async ngOnInit(): Promise<void> {
    try {
      await this.branches.load();
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les branches.'));
    }
  }

  protected isIn(ids: number[]): boolean {
    const me = this.myId();
    return me !== null && ids.includes(me);
  }

  /** Leaders join directly, the others ask when approval is required */
  protected needsApproval(branch: Branch): boolean {
    return branch.approvalRequired && !this.auth.isLeader();
  }

  /** joined: leave, or cancel the request */
  protected async toggle(branch: Branch, joined: boolean): Promise<void> {
    this.busy.set(branch.id);
    this.error.set(null);
    try {
      await (joined ? this.branches.leave(branch.id) : this.branches.join(branch.id));
      // The right to publish the news comes with some branches
      if (branch.publishesNews) {
        await this.auth.refreshUser();
      }
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.busy.set(null);
    }
  }
}
