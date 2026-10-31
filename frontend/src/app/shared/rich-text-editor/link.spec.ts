import { normalizeLink } from './link';

describe('normalizeLink', () => {
  it('completes the addresses typed by hand', () => {
    expect(normalizeLink('discord.gg/abc')).toBe('https://discord.gg/abc');
    expect(normalizeLink('  https://example.org/page?x=1 ')).toBe('https://example.org/page?x=1');
    expect(normalizeLink('http://nas.local:8080/')).toBe('http://nas.local:8080/');
    expect(normalizeLink('papa@example.com')).toBe('mailto:papa@example.com');
    expect(normalizeLink('mailto:papa@example.com')).toBe('mailto:papa@example.com');
    expect(normalizeLink('/evenements/4')).toBe('/evenements/4');
  });

  it('refuses what is not an address', () => {
    for (const value of [
      '',
      '   ',
      'javascript:alert(1)',
      'data:text/html,x',
      '//evil.example.com',
      'mot',
      'deux mots.com',
    ]) {
      expect(normalizeLink(value)).toBeNull();
    }
  });
});
