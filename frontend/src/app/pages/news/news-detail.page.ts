import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { News } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { NewsService } from '../../core/news/news.service';
import { Avatar } from '../../shared/avatar';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { NewsCover } from '../../shared/news-cover';
import { newsDate, paragraphs } from './news-format';

@Component({
  selector: 'app-news-detail-page',
  imports: [MemberCardDirective, RouterLink, Avatar, Icon, NewsCover],
  templateUrl: './news-detail.page.html',
  styleUrl: './news-detail.page.scss',
})
export class NewsDetailPage {
  private readonly newsService = inject(NewsService);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);

  /** Route parameter */
  readonly id = input.required<string>();

  protected readonly news = signal<News | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly deleting = signal(false);
  protected readonly paragraphs = computed(() => paragraphs(this.news()?.body ?? ''));
  protected readonly clanName = computed(() => this.auth.user()?.clan.name ?? '');
  protected readonly date = newsDate;

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => void this.load(id));
    });
  }

  protected async remove(): Promise<void> {
    const news = this.news();
    if (!news || !confirm(`Supprimer la news « ${news.title} » ?`)) {
      return;
    }
    this.deleting.set(true);
    try {
      await this.newsService.remove(news.id);
      await this.router.navigate(['/news']);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
      this.deleting.set(false);
    }
  }

  private async load(id: number): Promise<void> {
    this.news.set(null);
    this.error.set(null);
    try {
      this.news.set(await this.newsService.get(id));
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'News introuvable.'));
    }
  }
}
