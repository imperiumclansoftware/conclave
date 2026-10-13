import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { BranchLink } from '../../core/api/models';
import { BranchLinksService } from '../../core/clan/branch-links.service';
import { SidePanelService } from '../../core/side-panel/side-panel.service';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';

/** Must match BranchLink::TITLE_MAX_LENGTH and URL_MAX_LENGTH on the API. */
const TITLE_MAX = 80;
const URL_MAX = 500;

/** Favourite links of my branches, in the sidebar on the right. */
@Component({
  selector: 'app-links-panel',
  imports: [RouterLink, BranchCrest, Icon],
  templateUrl: './links-panel.html',
  styleUrl: './links-panel.scss',
})
export class LinksPanel implements OnInit {
  protected readonly links = inject(BranchLinksService);
  protected readonly panels = inject(SidePanelService);

  protected readonly titleMax = TITLE_MAX;
  protected readonly urlMax = URL_MAX;
  protected readonly error = signal<string | null>(null);
  /** Branch whose "add a link" form is open */
  protected readonly adding = signal<number | null>(null);
  protected readonly busy = signal(false);
  protected readonly formError = signal<string | null>(null);

  async ngOnInit(): Promise<void> {
    try {
      await this.links.load();
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les liens.'));
    }
  }

  protected openForm(branchId: number): void {
    this.adding.set(branchId);
    this.formError.set(null);
    queueMicrotask(() => document.getElementById(`link-url-${branchId}`)?.focus());
  }

  protected closeForm(branchId: number): void {
    this.adding.set(null);
    queueMicrotask(() => document.getElementById(`link-add-${branchId}`)?.focus());
  }

  protected async add(
    branchId: number,
    url: HTMLInputElement,
    title: HTMLInputElement,
  ): Promise<void> {
    const address = withScheme(url.value.trim());
    if (!address) {
      url.focus();
      return;
    }
    this.busy.set(true);
    this.formError.set(null);
    try {
      await this.links.add(branchId, address, title.value.trim() || null);
      this.closeForm(branchId);
    } catch (e) {
      this.formError.set(apiErrorMessage(e, 'Le lien n’a pas pu être ajouté.'));
    } finally {
      this.busy.set(false);
    }
  }

  protected async remove(link: BranchLink): Promise<void> {
    if (!confirm(`Retirer le lien « ${link.title} » ?`)) {
      return;
    }
    try {
      await this.links.remove(link);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }
}

/** "example.com" is understood as https://example.com */
function withScheme(url: string): string {
  return url && !/^[a-z][a-z0-9+.-]*:/i.test(url) ? `https://${url}` : url;
}
