import { TestBed } from '@angular/core/testing';
import { ThemeService } from './theme.service';

describe('ThemeService preferences', () => {
  let meta: HTMLMetaElement;

  beforeEach(() => {
    localStorage.clear();
    // jsdom has no matchMedia: a light device that does not prefer reduced motion
    window.matchMedia = vi
      .fn()
      .mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
    meta =
      document.querySelector('meta[name="color-scheme"]') ??
      document.head.appendChild(
        Object.assign(document.createElement('meta'), { name: 'color-scheme' }),
      );
    meta.content = 'light dark';
  });

  afterEach(() => localStorage.clear());

  it('applies an explicit faction, then follows the device again', () => {
    const theme = TestBed.inject(ThemeService);

    theme.setPreference('templar', { animate: false });
    expect(meta.content).toBe('dark');
    expect(theme.faction()).toBe('templar');
    expect(theme.devicePreference()).toBe('templar');

    theme.setPreference('assassin', { animate: false });
    expect(meta.content).toBe('light');
    expect(localStorage.getItem('color-scheme')).toBe('light');

    theme.setPreference(null, { animate: false });
    expect(meta.content).toBe('light dark');
    expect(theme.devicePreference()).toBeNull();
  });

  it('returns the new faction even when the switch is animated (applied later)', () => {
    const theme = TestBed.inject(ThemeService);
    theme.setPreference('assassin', { animate: false });
    let update: (() => void) | undefined;
    const done = Promise.resolve();
    Object.assign(document, {
      startViewTransition: (callback: () => void) => {
        update = callback;
        return { finished: done };
      },
    });

    expect(theme.toggle()).toBe('templar');
    expect(theme.faction()).toBe('assassin');
    update!();
    expect(theme.faction()).toBe('templar');
    delete (document as { startViewTransition?: unknown }).startViewTransition;
  });

  it('toggles to the other faction and returns it', () => {
    const theme = TestBed.inject(ThemeService);
    theme.setPreference('assassin', { animate: false });

    expect(theme.toggle()).toBe('templar');
    expect(meta.content).toBe('dark');
  });
});
