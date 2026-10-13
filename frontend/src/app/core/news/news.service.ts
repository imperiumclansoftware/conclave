import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { News, NewsPage } from '../api/models';

/** Must match News::TITLE_MAX_LENGTH and BODY_MAX_LENGTH on the API. */
export const NEWS_TITLE_MAX = 120;
export const NEWS_BODY_MAX = 10000;

export interface NewsInput {
  title: string;
  body: string;
  /** New picture */
  image: File | null;
  /** Back to the default visual */
  removeImage: boolean;
}

@Injectable({ providedIn: 'root' })
export class NewsService {
  private readonly http = inject(HttpClient);

  page(before: number | null = null): Promise<NewsPage> {
    return firstValueFrom(
      this.http.get<NewsPage>('api/news', before ? { params: { before } } : {}),
    );
  }

  get(id: number): Promise<News> {
    return firstValueFrom(this.http.get<News>(`api/news/${id}`));
  }

  create(input: NewsInput): Promise<News> {
    return firstValueFrom(this.http.post<News>('api/news', formData(input)));
  }

  /** POST: PHP only reads multipart bodies (the picture) on POST */
  update(id: number, input: NewsInput): Promise<News> {
    return firstValueFrom(this.http.post<News>(`api/news/${id}`, formData(input)));
  }

  remove(id: number): Promise<void> {
    return firstValueFrom(this.http.delete<void>(`api/news/${id}`));
  }
}

function formData(input: NewsInput): FormData {
  const data = new FormData();
  data.append('title', input.title);
  data.append('body', input.body);
  if (input.image) {
    data.append('image', input.image);
  } else if (input.removeImage) {
    data.append('removeImage', '1');
  }
  return data;
}
