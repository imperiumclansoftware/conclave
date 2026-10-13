import { excerpt, paragraphs } from './news-format';

describe('news format', () => {
  it('cuts the excerpt on a word', () => {
    expect(excerpt('Court texte')).toBe('Court texte');
    expect(excerpt('Rendez-vous samedi soir pour la grande soirée du clan', 30)).toBe(
      'Rendez-vous samedi soir pour…',
    );
  });

  it('splits paragraphs on blank lines and keeps single line breaks', () => {
    expect(paragraphs('Un\ndeux\n\n\nTrois\n\n')).toEqual(['Un\ndeux', 'Trois']);
  });
});
