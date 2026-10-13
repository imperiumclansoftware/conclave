import { Component, OnInit, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { ConsentService } from '../../core/consent/consent.service';
import {
  catchError,
  debounceTime,
  distinctUntilChanged,
  firstValueFrom,
  map,
  of,
  startWith,
  switchMap,
} from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { CatalogGame, GamePlatform, MyGame } from '../../core/api/models';
import { GamesService } from '../../core/games/games.service';
import { PlatformBadges } from '../../shared/platform-badges';
import { PlatformPicker } from '../../shared/platform-picker';
import { GamesTabs } from './games-tabs';

type SearchState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'done'; results: CatalogGame[] }
  | { status: 'error'; message: string };

const SEARCH_DEBOUNCE_MS = 350;

@Component({
  selector: 'app-my-games-page',
  imports: [RouterLink, GamesTabs, PlatformBadges, PlatformPicker],
  templateUrl: './my-games.page.html',
  styleUrl: './my-games.page.scss',
})
export class MyGamesPage implements OnInit {
  protected readonly consent = inject(ConsentService);
  private readonly games = inject(GamesService);

  protected readonly query = signal('');
  protected readonly search = toSignal(
    toObservable(this.query).pipe(
      map((q) => q.trim()),
      debounceTime(SEARCH_DEBOUNCE_MS),
      distinctUntilChanged(),
      switchMap((q) =>
        q.length < 2
          ? of<SearchState>({ status: 'idle' })
          : this.games.search(q).pipe(
              map((results): SearchState => ({ status: 'done', results })),
              startWith<SearchState>({ status: 'loading' }),
              catchError((e) => of<SearchState>({ status: 'error', message: apiErrorMessage(e) })),
            ),
      ),
    ),
    { initialValue: { status: 'idle' } as SearchState },
  );

  protected readonly results = computed(() => {
    const state = this.search();
    return state.status === 'done' ? state.results : [];
  });
  protected readonly searchError = computed(() => {
    const state = this.search();
    return state.status === 'error' ? state.message : null;
  });
  protected readonly searchStatus = computed(() => {
    const state = this.search();
    switch (state.status) {
      case 'loading':
        return 'Recherche des informations…';
      case 'done':
        return state.results.length === 0
          ? 'Aucun jeu trouvé.'
          : `${state.results.length} résultat(s), source : ${state.results[0].source === 'rawg' ? 'RAWG' : 'Steam'}.`;
      default:
        return '';
    }
  });

  protected readonly myGames = signal<MyGame[]>([]);
  protected readonly loaded = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Search result being added, and the platforms chosen for it. */
  protected readonly adding = signal<CatalogGame | null>(null);
  protected readonly addPlatforms = signal<GamePlatform[]>([]);
  protected readonly saving = signal(false);

  /** Card whose platforms are being edited ("<game id>:<platform group>": a game appears in each of its groups). */
  protected readonly editing = signal<string | null>(null);

  private readonly router = inject(Router);

  /** Platform tab in the URL (bound by the router): ?plateforme=ps5 */
  readonly plateforme = input<string>();

  /** My games grouped by platform, in the platforms' order; a game appears in each of its platforms. */
  protected readonly groups = computed(() =>
    this.games
      .platforms()
      .map((platform) => ({
        platform: platform.value as GamePlatform | 'all',
        label: platform.label,
        games: this.myGames().filter((g) => g.platforms.includes(platform.value)),
      }))
      .filter((group) => group.games.length > 0),
  );

  /** "Toutes", then one tab per platform having games */
  protected readonly tabs = computed(() => [
    { platform: 'all' as const, label: 'Toutes', games: this.myGames() },
    ...this.groups(),
  ]);

  protected readonly tab = linkedSignal<GamePlatform | 'all'>(() => {
    const requested = this.plateforme();
    return this.groups().some((g) => g.platform === requested)
      ? (requested as GamePlatform)
      : 'all';
  });

  /** Games of the selected tab (back to all games when its platform has none left) */
  protected readonly shown = computed(
    () => this.tabs().find((t) => t.platform === this.tab()) ?? this.tabs()[0],
  );

  protected selectTab(platform: GamePlatform | 'all'): void {
    this.tab.set(platform);
    void this.router.navigate([], {
      queryParams: { plateforme: platform === 'all' ? null : platform },
      replaceUrl: true,
    });
  }

  protected onTabKey(event: KeyboardEvent): void {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step) {
      const tabs = this.tabs();
      const index = tabs.findIndex((t) => t.platform === this.shown().platform);
      const next = tabs[(index + step + tabs.length) % tabs.length].platform;
      this.selectTab(next);
      document.getElementById(`platform-tab-${next}`)?.focus();
    }
  }
  protected readonly editPlatforms = signal<GamePlatform[]>([]);

  private readonly libraryKeys = computed(
    () => new Set(this.myGames().map((g) => `${g.game.source}:${g.game.externalId}`)),
  );

  async ngOnInit(): Promise<void> {
    try {
      this.myGames.set(await firstValueFrom(this.games.myGames()));
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.loaded.set(true);
    }
  }

  protected inLibrary(result: CatalogGame): boolean {
    return result.inLibrary || this.libraryKeys().has(`${result.source}:${result.externalId}`);
  }

  protected startAdding(result: CatalogGame): void {
    this.adding.set(result);
    // A single known platform (e.g. a Steam game) is preselected
    this.addPlatforms.set(result.platforms.length === 1 ? [...result.platforms] : []);
    this.error.set(null);
  }

  protected async confirmAdd(): Promise<void> {
    const result = this.adding();
    if (!result || this.addPlatforms().length === 0 || this.saving()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    try {
      const added = await firstValueFrom(this.games.add(result, this.addPlatforms()));
      this.myGames.update((list) =>
        [...list, added].sort((a, b) => a.game.name.localeCompare(b.game.name)),
      );
      this.adding.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.saving.set(false);
    }
  }

  protected startEditing(myGame: MyGame, group: GamePlatform | 'all'): void {
    this.editing.set(`${myGame.id}:${group}`);
    this.editPlatforms.set([...myGame.platforms]);
    this.error.set(null);
  }

  protected async saveEdit(myGame: MyGame): Promise<void> {
    if (this.editPlatforms().length === 0 || this.saving()) {
      return;
    }

    this.saving.set(true);
    try {
      const updated = await firstValueFrom(
        this.games.updatePlatforms(myGame.id, this.editPlatforms()),
      );
      this.myGames.update((list) => list.map((g) => (g.id === updated.id ? updated : g)));
      this.editing.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.saving.set(false);
    }
  }

  protected async remove(myGame: MyGame): Promise<void> {
    if (!confirm(`Retirer « ${myGame.game.name} » de vos jeux ?`)) {
      return;
    }
    try {
      await firstValueFrom(this.games.remove(myGame.id));
      this.myGames.update((list) => list.filter((g) => g.id !== myGame.id));
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }
}
