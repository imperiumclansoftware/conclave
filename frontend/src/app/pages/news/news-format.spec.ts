import { excerpt, plainText } from './news-format';

describe('news format', () => {
  it('cuts the excerpt on a word', () => {
    expect(excerpt('Court texte')).toBe('Court texte');
    expect(excerpt('Rendez-vous samedi soir pour la grande soirée du clan', 30)).toBe(
      'Rendez-vous samedi soir pour…',
    );
  });

  it('reads the words of the editor HTML', () => {
    expect(
      plainText(
        '<h2>Programme</h2><p>Un &amp; <a href="https://example.org" class="button">deux</a></p><p>Trois<br>quatre</p>',
      ),
    ).toBe('Programme\nUn & deux\nTrois\nquatre');
    expect(plainText('Texte sans balise')).toBe('Texte sans balise');
  });

  it('cuts the excerpt of the editor HTML on a word', () => {
    expect(
      excerpt('<p>Rendez-vous <strong>samedi</strong> soir</p><p>pour la grande soirée</p>', 30),
    ).toBe('Rendez-vous samedi soir pour…');
  });
});
