import { isPagePath } from './desktop-bridge.service';

describe('isPagePath', () => {
  it('accepts only the pages of Conclave', () => {
    expect(isPagePath('/evenements/4')).toBe(true);
    expect(isPagePath('/messages/2')).toBe(true);
    expect(isPagePath('//evil.example.com')).toBe(false);
    expect(isPagePath('https://evil.example.com')).toBe(false);
    expect(isPagePath('/\\evil.example.com')).toBe(false);
    expect(isPagePath(4)).toBe(false);
    expect(isPagePath(null)).toBe(false);
  });
});
