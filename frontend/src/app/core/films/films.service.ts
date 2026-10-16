import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { FilmDetails, FilmKind, FilmSearchResult } from '../api/models';

/** Films and TV series of TMDB: search and fiches, for the Cinéphiles. */
@Injectable({ providedIn: 'root' })
export class FilmsService {
  private readonly http = inject(HttpClient);

  search(query: string): Promise<FilmSearchResult[]> {
    return firstValueFrom(
      this.http.get<FilmSearchResult[]>('api/films/search', { params: { q: query } }),
    );
  }

  get(kind: FilmKind, tmdbId: number): Promise<FilmDetails> {
    return firstValueFrom(this.http.get<FilmDetails>(`api/films/${kind}/${tmdbId}`));
  }
}
