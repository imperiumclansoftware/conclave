import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { CookieBanner } from '../../shared/cookie-banner';
import { ConsentService } from './consent.service';

const GRAVATAR = 'https://gravatar.com/avatar/abc?s=256&d=404';
const LOCAL = 'api/avatars/0123456789abcdef0123456789abcdef.webp';

describe('Consent', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ imports: [CookieBanner], providers: [provideRouter([])] });
  });

  it('loads no third-party content before consent, local images always', () => {
    const consent = TestBed.inject(ConsentService);

    expect(consent.bannerVisible()).toBe(true);
    expect(consent.src(GRAVATAR)).toBeNull();
    expect(consent.src(LOCAL)).toBe(LOCAL);
    expect(consent.src(null)).toBeNull();
  });

  it('remembers the choice, then asks again after 6 months', () => {
    TestBed.inject(ConsentService).decide(true);
    expect(TestBed.inject(ConsentService).src(GRAVATAR)).toBe(GRAVATAR);
    expect(JSON.parse(localStorage.getItem('cookie-consent')!).externalContent).toBe(true);

    // A new visit reads the stored choice
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({});
    expect(TestBed.inject(ConsentService).bannerVisible()).toBe(false);

    // 7 months later
    const old = {
      version: 1,
      externalContent: true,
      decidedAt: new Date(Date.now() - 213 * 24 * 3600 * 1000).toISOString(),
    };
    localStorage.setItem('cookie-consent', JSON.stringify(old));
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({});
    const later = TestBed.inject(ConsentService);
    expect(later.bannerVisible()).toBe(true);
    expect(later.src(GRAVATAR)).toBeNull();
  });

  it('offers refusing as prominently as accepting, and customizing', async () => {
    const fixture = TestBed.createComponent(CookieBanner);
    fixture.detectChanges();
    const el = fixture.nativeElement as HTMLElement;
    const buttons = () =>
      [...el.querySelectorAll('button')].map((b) => [b.textContent?.trim(), b.className]);

    expect(buttons()).toEqual([
      ['Tout refuser', 'btn'],
      ['Tout accepter', 'btn'],
      ['Personnaliser', 'btn btn-ghost'],
    ]);

    el.querySelectorAll('button')[2].click();
    fixture.detectChanges();
    const boxes = el.querySelectorAll<HTMLInputElement>('input[type=checkbox]');
    expect([boxes[0].checked, boxes[0].disabled, boxes[1].checked]).toEqual([true, true, false]);

    boxes[1].click();
    fixture.detectChanges();
    el.querySelector<HTMLButtonElement>('.btn-ghost')!.click();
    fixture.detectChanges();

    const consent = TestBed.inject(ConsentService);
    expect(consent.externalContent()).toBe(true);
    expect(el.querySelector('section')).toBeNull();

    // "Gérer les cookies" reopens it with the details
    consent.manage();
    fixture.detectChanges();
    expect(el.querySelectorAll('input[type=checkbox]')[1]).toBeTruthy();
  });
});
