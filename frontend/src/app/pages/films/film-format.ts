import { FilmDetails, FilmKind, FilmSummary } from '../../core/api/models';

export function filmKindLabel(kind: FilmKind): string {
  return kind === 'tv' ? 'Série' : 'Film';
}

/** Route of the fiche: /films/:id or /series/:id */
export function filmLink(film: Pick<FilmSummary, 'kind' | 'tmdbId'>): (string | number)[] {
  return [film.kind === 'tv' ? '/series' : '/films', film.tmdbId];
}

/** Years of a series: "2011 – 2019", "2024 – en cours", or the year of a film */
export function filmYears(
  film: Pick<FilmDetails, 'kind' | 'year' | 'endYear' | 'ongoing'>,
): string | null {
  if (film.kind !== 'tv' || !film.year) {
    return film.year;
  }
  if (film.ongoing) {
    return `${film.year} – en cours`;
  }
  return film.endYear && film.endYear !== film.year ? `${film.year} – ${film.endYear}` : film.year;
}

/** "3 saisons · 24 épisodes" */
export function seriesSize(film: Pick<FilmDetails, 'seasonCount' | 'episodeCount'>): string | null {
  const parts = [];
  if (film.seasonCount) {
    parts.push(`${film.seasonCount} saison${film.seasonCount > 1 ? 's' : ''}`);
  }
  if (film.episodeCount) {
    parts.push(`${film.episodeCount} épisode${film.episodeCount > 1 ? 's' : ''}`);
  }
  return parts.join(' · ') || null;
}
