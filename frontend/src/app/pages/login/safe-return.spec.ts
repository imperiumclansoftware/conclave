import { safeReturn } from './login.page';

describe('safeReturn', () => {
  it('accepte un chemin interne, avec sa demande', () => {
    expect(safeReturn('/autoriser?demande=abc.def')).toBe('/autoriser?demande=abc.def');
  });

  it('refuse les adresses externes ou détournées', () => {
    for (const value of [
      'https://evil.example',
      '//evil.example',
      '/\\evil.example',
      'javascript:alert(1)',
      '',
      undefined,
    ]) {
      expect(safeReturn(value)).toBeNull();
    }
  });
});
