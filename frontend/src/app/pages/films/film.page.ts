import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { FilmDetails } from '../../core/api/models';
import { ConsentService } from '../../core/consent/consent.service';
import { FilmsService } from '../../core/films/films.service';
import { Icon } from '../../shared/icon';

/** Film fiche from TMDB, for the Cinéphiles. */
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

  protected readonly film = signal<FilmDetails | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly runtime = computed(() => {
    const minutes = this.film()?.runtime;
    return minutes
      ? `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}`
      : null;
  });

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => void this.load(id));
    });
  }

  private async load(id: number): Promise<void> {
    this.film.set(null);
    this.error.set(null);
    try {
      this.film.set(await this.films.get(id));
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Film introuvable.'));
    }
  }
}
