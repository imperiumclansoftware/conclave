import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { FilmDetails, FilmSearchResult } from '../api/models';

/** Films of TMDB: search and fiches, for the Cinéphiles. */
@Injectable({ providedIn: 'root' })
export class FilmsService {
  private readonly http = inject(HttpClient);

  search(query: string): Promise<FilmSearchResult[]> {
    return firstValueFrom(
      this.http.get<FilmSearchResult[]>('api/films/search', { params: { q: query } }),
    );
  }

  get(tmdbId: number): Promise<FilmDetails> {
    return firstValueFrom(this.http.get<FilmDetails>(`api/films/${tmdbId}`));
  }
}
