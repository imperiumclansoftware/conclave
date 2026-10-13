import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { News } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { NewsService } from '../../core/news/news.service';
import { Icon } from '../../shared/icon';
import { MemberCardDirective } from '../../shared/member-card.directive';
import { NewsCover } from '../../shared/news-cover';
import { excerpt, newsDate } from './news-format';

/** News of the clan, the latest one first and larger. */
@Component({
  selector: 'app-news-list-page',
  imports: [MemberCardDirective, RouterLink, Icon, NewsCover],
  templateUrl: './news-list.page.html',
  styleUrl: './news-list.page.scss',
})
export class NewsListPage implements OnInit {
  private readonly newsService = inject(NewsService);
  protected readonly auth = inject(AuthService);

  protected readonly news = signal<News[]>([]);
  protected readonly hasMore = signal(false);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly featured = computed(() => this.news()[0] ?? null);
  protected readonly others = computed(() => this.news().slice(1));
  protected readonly clanName = computed(() => this.auth.user()?.clan.name ?? '');
  protected readonly date = newsDate;
  protected readonly excerpt = excerpt;

  ngOnInit(): void {
    void this.load();
  }

  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const page = await this.newsService.page(this.news().at(-1)?.id ?? null);
      this.news.update((list) => [...list, ...page.items]);
      this.hasMore.set(page.hasMore);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger les news.'));
    } finally {
      this.loading.set(false);
    }
  }
}
