import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Observable, catchError, of, shareReplay } from 'rxjs';
import {
  CatalogGame,
  ClanGame,
  GameDetails,
  GamePlatform,
  MyGame,
  PlatformOption,
} from '../api/models';

@Injectable({ providedIn: 'root' })
export class GamesService {
  private readonly http = inject(HttpClient);

  private readonly platforms$ = this.http.get<PlatformOption[]>('api/games/platforms').pipe(
    catchError(() => of<PlatformOption[]>([])),
    shareReplay(1),
  );

  /** Every supported platform, in display order. */
  readonly platforms = toSignal(this.platforms$, { initialValue: [] });
  private readonly labels = computed(
    () => new Map(this.platforms().map((p) => [p.value, p.label])),
  );

  label(platform: GamePlatform): string {
    return this.labels().get(platform) ?? platform;
  }

  search(query: string): Observable<CatalogGame[]> {
    return this.http.get<CatalogGame[]>('api/games/search', { params: { q: query } });
  }

  myGames(): Observable<MyGame[]> {
    return this.http.get<MyGame[]>('api/me/games');
  }

  add(
    game: Pick<CatalogGame, 'source' | 'externalId'>,
    platforms: GamePlatform[],
  ): Observable<MyGame> {
    return this.http.post<MyGame>('api/me/games', {
      source: game.source,
      externalId: game.externalId,
      platforms,
    });
  }

  updatePlatforms(myGameId: number, platforms: GamePlatform[]): Observable<MyGame> {
    return this.http.put<MyGame>(`api/me/games/${myGameId}`, { platforms });
  }

  remove(myGameId: number): Observable<void> {
    return this.http.delete<void>(`api/me/games/${myGameId}`);
  }

  details(gameId: number): Observable<GameDetails> {
    return this.http.get<GameDetails>(`api/games/${gameId}`);
  }

  clanLibrary(): Observable<ClanGame[]> {
    return this.http.get<ClanGame[]>('api/clan/games');
  }
}
