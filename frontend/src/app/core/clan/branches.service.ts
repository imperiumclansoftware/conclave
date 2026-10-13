import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Branch, BranchJoinRequest } from '../api/models';

/** What leaders change on a branch (its coat of arms comes with it) */
export interface BranchInput {
  name: string;
  description: string | null;
  publishesNews: boolean;
  approvalRequired: boolean;
}

/** Active branches of the clan, shared by the pages; the administration also manages the inactive ones. */
@Injectable({ providedIn: 'root' })
export class BranchesService {
  private readonly http = inject(HttpClient);
  private readonly _branches = signal<Branch[]>([]);
  private readonly _loaded = signal(false);

  /** By name, as sorted by the API */
  readonly branches = this._branches.asReadonly();
  readonly loaded = this._loaded.asReadonly();
  /** Branches of each member */
  readonly byMember = computed(() => {
    const map = new Map<number, Branch[]>();
    for (const branch of this._branches()) {
      for (const id of branch.memberIds) {
        map.set(id, [...(map.get(id) ?? []), branch]);
      }
    }
    return map;
  });

  async load(): Promise<void> {
    this._branches.set(await firstValueFrom(this.http.get<Branch[]>('api/clan/branches')));
    this._loaded.set(true);
  }

  /** Joins the branch, or asks for it when a leader's approval is needed */
  async join(id: number): Promise<void> {
    this.replace(
      await firstValueFrom(this.http.put<Branch>(`api/clan/branches/${id}/membership`, null)),
    );
  }

  /** Leaves the branch, or cancels the request */
  async leave(id: number): Promise<void> {
    this.replace(
      await firstValueFrom(this.http.delete<Branch>(`api/clan/branches/${id}/membership`)),
    );
  }

  /** Every branch of the catalogue, active or not (administration) */
  all(): Promise<Branch[]> {
    return firstValueFrom(this.http.get<Branch[]>('api/admin/branches'));
  }

  async setActive(id: number, active: boolean): Promise<Branch> {
    const branch = await firstValueFrom(
      this.http.put<Branch>(`api/admin/branches/${id}/active`, { active }),
    );
    this._branches.update((list) =>
      sortByName([...list.filter((b) => b.id !== id), ...(active ? [branch] : [])]),
    );
    return branch;
  }

  async update(id: number, input: BranchInput): Promise<Branch> {
    const branch = await firstValueFrom(this.http.put<Branch>(`api/admin/branches/${id}`, input));
    this.replace(branch);
    return branch;
  }

  /** Requests waiting for a leader (administration) */
  requests(): Promise<BranchJoinRequest[]> {
    return firstValueFrom(this.http.get<BranchJoinRequest[]>('api/admin/branch-requests'));
  }

  async approve(request: BranchJoinRequest): Promise<void> {
    this.replace(
      await firstValueFrom(
        this.http.post<Branch>(`api/admin/branch-requests/${request.id}/approve`, null),
      ),
    );
  }

  async reject(request: BranchJoinRequest): Promise<void> {
    this.replace(
      await firstValueFrom(
        this.http.post<Branch>(`api/admin/branch-requests/${request.id}/reject`, null),
      ),
    );
  }

  private replace(branch: Branch): void {
    this._branches.update((list) => sortByName(list.map((b) => (b.id === branch.id ? branch : b))));
  }
}

const collator = new Intl.Collator('fr', { sensitivity: 'base' });

function sortByName(list: Branch[]): Branch[] {
  return [...list].sort((a, b) => collator.compare(a.name, b.name));
}
