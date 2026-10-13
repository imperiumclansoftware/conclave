import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { CatalogGame, MyGame } from '../../core/api/models';
import { MyGamesPage } from './my-games.page';

const elden: CatalogGame = {
  source: 'steam',
  externalId: '1245620',
  name: 'ELDEN RING',
  coverUrl: 'https://cdn.test/elden.jpg',
  releaseDate: null,
  platforms: ['pc'],
  inLibrary: false,
};

describe('MyGamesPage', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      imports: [MyGamesPage],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    vi.useRealTimers();
  });

  it('searches the catalog as the user types, then adds a game with its platforms', async () => {
    const fixture = TestBed.createComponent(MyGamesPage);
    fixture.detectChanges();
    http.expectOne('api/games/platforms').flush([
      { value: 'pc', label: 'PC' },
      { value: 'ps5', label: 'PS5' },
    ]);
    http.expectOne('api/me/games').flush([]);
    await vi.advanceTimersByTimeAsync(20);

    const el = fixture.nativeElement as HTMLElement;
    const input = el.querySelector<HTMLInputElement>('input[type=search]')!;
    input.value = 'eld';
    input.dispatchEvent(new Event('input'));
    input.value = 'elden';
    input.dispatchEvent(new Event('input'));
    await vi.advanceTimersByTimeAsync(400);

    // Debounced: a single request for the last value
    http
      .expectOne((req) => req.url === 'api/games/search' && req.params.get('q') === 'elden')
      .flush([elden]);
    fixture.detectChanges();
    expect(el.querySelector('[role=status]')?.textContent).toContain(
      '1 résultat(s), source : Steam',
    );

    el.querySelector<HTMLButtonElement>('.result .btn')!.click();
    fixture.detectChanges();
    // The only platform known by the catalog is preselected
    const checked = [...el.querySelectorAll<HTMLInputElement>('.add-form input:checked')].map(
      (i) => i.value,
    );
    expect(checked).toEqual(['pc']);

    el.querySelector<HTMLInputElement>('.add-form input[value=ps5]')!.click();
    fixture.detectChanges();
    el.querySelector<HTMLButtonElement>('.add-form .btn')!.click();

    const add = http.expectOne('api/me/games');
    expect(add.request.body).toEqual({
      source: 'steam',
      externalId: '1245620',
      platforms: ['pc', 'ps5'],
    });
    const added: MyGame = {
      id: 1,
      platforms: ['pc', 'ps5'],
      addedAt: '2026-10-09T12:00:00+00:00',
      game: {
        id: 7,
        source: 'steam',
        externalId: '1245620',
        name: 'ELDEN RING',
        coverUrl: null,
        releaseDate: null,
        genres: [],
        availablePlatforms: ['pc'],
      },
    };
    add.flush(added);
    await vi.advanceTimersByTimeAsync(20);
    fixture.detectChanges();

    expect(el.querySelector('.game-card .name')?.textContent).toContain('ELDEN RING');
    // Platform tabs: "Toutes", then each platform having games (a game appears in each of its platforms)
    const tabs = () =>
      [...el.querySelectorAll('[role=tab]')].map((t) => t.textContent?.replace(/\s+/g, ' ').trim());
    expect(tabs()).toEqual(['Toutes 1', 'PC 1', 'PS5 1']);
    expect(el.querySelector('[role=tab][aria-selected=true]')?.textContent).toContain('Toutes');

    el.querySelectorAll<HTMLButtonElement>('[role=tab]')[2].click();
    await vi.advanceTimersByTimeAsync(20);
    fixture.detectChanges();
    expect(el.querySelector('[role=tab][aria-selected=true]')?.textContent).toContain('PS5');
    expect(el.querySelectorAll('#platform-panel .game-card')).toHaveLength(1);
  });
});
