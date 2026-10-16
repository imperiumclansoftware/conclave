import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { FilmDetails, FilmKind } from '../../core/api/models';
import { ConsentService } from '../../core/consent/consent.service';
import { FilmsService } from '../../core/films/films.service';
import { Icon } from '../../shared/icon';
import { filmKindLabel, filmYears, seriesSize } from './film-format';

/** Fiche of a film or a TV series from TMDB, for the Cinéphiles. */
@Component({
  selector: 'app-film-page',
  imports: [RouterLink, Icon],
  templateUrl: './film.page.html',
  styleUrl: './film.page.scss',
})
export class FilmPage {
  private readonly films = inject(FilmsService);
  protected readonly consent = inject(ConsentService);

  /** Route parameter: TMDB identifier */
  readonly id = input.required<string>();
  /** Route data: /films/:id or /series/:id */
  readonly kind = input<FilmKind>('movie');

  protected readonly film = signal<FilmDetails | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly runtime = computed(() => {
    const minutes = this.film()?.runtime;
    if (!minutes) {
      return null;
    }
    return minutes < 60
      ? `${minutes} min`
      : `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}`;
  });
  /** "1 saison · 6 épisodes · 1 h 03 par épisode · Drame" */
  protected readonly facts = computed(() => {
    const film = this.film();
    if (!film) {
      return null;
    }
    const runtime = this.runtime();
    return (
      [
        seriesSize(film),
        runtime && film.kind === 'tv' ? `${runtime} par épisode` : runtime,
        film.genres.join(', '),
      ]
        .filter(Boolean)
        .join(' · ') || null
    );
  });
  protected readonly years = computed(() => {
    const film = this.film();
    return film ? filmYears(film) : null;
  });
  protected readonly kindLabel = filmKindLabel;

  constructor() {
    effect(() => {
      const id = Number(this.id());
      const kind = this.kind();
      untracked(() => void this.load(kind, id));
    });
  }

  private async load(kind: FilmKind, id: number): Promise<void> {
    this.film.set(null);
    this.error.set(null);
    try {
      this.film.set(await this.films.get(kind, id));
    } catch (e) {
      this.error.set(
        apiErrorMessage(e, kind === 'tv' ? 'Série introuvable.' : 'Film introuvable.'),
      );
    }
  }
}
