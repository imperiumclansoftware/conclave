import { filmLink, filmYears, seriesSize } from './film-format';

describe('film format', () => {
  it('links films and series to their own fiche', () => {
    expect(filmLink({ kind: 'movie', tmdbId: 438631 })).toEqual(['/films', 438631]);
    expect(filmLink({ kind: 'tv', tmdbId: 90228 })).toEqual(['/series', 90228]);
  });

  it('gives the years a series ran', () => {
    const series = { kind: 'tv' as const, year: '2011', endYear: '2019', ongoing: false };
    expect(filmYears(series)).toBe('2011 – 2019');
    expect(filmYears({ ...series, endYear: null, ongoing: true })).toBe('2011 – en cours');
    expect(filmYears({ ...series, endYear: '2011' })).toBe('2011');
    expect(filmYears({ kind: 'movie', year: '2021', endYear: null, ongoing: null })).toBe('2021');
  });

  it('counts seasons and episodes', () => {
    expect(seriesSize({ seasonCount: 1, episodeCount: 6 })).toBe('1 saison · 6 épisodes');
    expect(seriesSize({ seasonCount: 8, episodeCount: 73 })).toBe('8 saisons · 73 épisodes');
    expect(seriesSize({ seasonCount: null, episodeCount: null })).toBeNull();
  });
});
