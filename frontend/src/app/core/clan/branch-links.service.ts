import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { BranchLink, BranchLinks } from '../api/models';

/** Favourite links of my branches, for the links sidebar. */
@Injectable({ providedIn: 'root' })
export class BranchLinksService {
  private readonly http = inject(HttpClient);
  private readonly _groups = signal<BranchLinks[]>([]);
  private readonly _loaded = signal(false);

  /** One group per branch of mine, by branch name */
  readonly groups = this._groups.asReadonly();
  readonly loaded = this._loaded.asReadonly();

  async load(): Promise<void> {
    this._groups.set(await firstValueFrom(this.http.get<BranchLinks[]>('api/me/branch-links')));
    this._loaded.set(true);
  }

  async add(branchId: number, url: string, title: string | null): Promise<void> {
    const link = await firstValueFrom(
      this.http.post<BranchLink>(`api/clan/branches/${branchId}/links`, { url, title }),
    );
    this._groups.update((groups) =>
      groups.map((g) => (g.branch.id === branchId ? { ...g, links: [...g.links, link] } : g)),
    );
  }

  async remove(link: BranchLink): Promise<void> {
    await firstValueFrom(this.http.delete<void>(`api/branch-links/${link.id}`));
    this._groups.update((groups) =>
      groups.map((g) => ({ ...g, links: g.links.filter((l) => l.id !== link.id) })),
    );
  }

  /** On logout */
  reset(): void {
    this._groups.set([]);
    this._loaded.set(false);
  }
}
