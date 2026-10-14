import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { SetupPage } from './setup.page';

describe('SetupPage', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      imports: [SetupPage],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  function render() {
    const fixture = TestBed.createComponent(SetupPage);
    fixture.detectChanges();
    const el = fixture.nativeElement as HTMLElement;
    const fill = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector<HTMLButtonElement>('button[type=submit]')!.click();
      await fixture.whenStable();
      fixture.detectChanges();
    };
    return { fixture, el, fill, submit };
  }

  it('checks the code, then installs the clan and logs the first leader in', async () => {
    const { fixture, el, fill, submit } = render();
    expect(el.querySelector('h1')?.textContent).toContain('Bienvenue');
    expect(el.textContent).toContain('app:setup:code');

    fill('setup-code', 'abcd-efgh');
    el.querySelector<HTMLButtonElement>('button[type=submit]')!.click();
    const verify = http.expectOne('api/setup/verify');
    expect(verify.request.body).toEqual({ code: 'abcd-efgh' });
    verify.flush(null, { status: 204, statusText: 'No Content' });
    await fixture.whenStable();
    fixture.detectChanges();

    expect(el.querySelector('h1')?.textContent).toContain('Votre clan');
    fill('clan-name', ' Imperium ');
    fill('app-url', 'https://clan.example.com/');
    await submit();

    expect(el.querySelector('h1')?.textContent).toContain('Premier dirigeant');
    fill('display-name', 'IEmperor');
    fill('email', 'chef@example.com');
    fill('username', 'Papa');
    fill('new-password', 'motdepasse');
    fill('confirm-password', 'autre-chose');
    await submit();
    expect(el.querySelector('[role=alert]')?.textContent).toContain('diffèrent');

    fill('confirm-password', 'motdepasse');
    await submit();
    expect(el.querySelector('.recap')?.textContent).toContain('Imperium');

    [...el.querySelectorAll<HTMLButtonElement>('button')]
      .find((b) => b.textContent?.includes('Installer'))!
      .click();
    const install = http.expectOne('api/setup');
    expect(install.request.body).toEqual({
      code: 'abcd-efgh',
      clanName: 'Imperium',
      appUrl: 'https://clan.example.com/',
      displayName: 'IEmperor',
      email: 'chef@example.com',
      username: 'papa',
      password: 'motdepasse',
    });
    install.flush({
      token: 'jwt',
      user: {
        id: 1,
        username: 'papa',
        displayName: 'IEmperor',
        role: 'leader',
        clan: { id: 1, name: 'Imperium' },
        email: 'chef@example.com',
        faction: null,
        canPublishNews: false,
        canAccessGames: false,
        canAccessFilms: false,
      },
    });
    await fixture.whenStable();
    fixture.detectChanges();

    expect(el.querySelector('h1')?.textContent).toContain('Bienvenue, IEmperor');
    const auth = TestBed.inject(AuthService);
    expect([auth.token(), auth.user()?.role]).toEqual(['jwt', 'leader']);
    expect(await auth.installed()).toBe(true);
  });

  it('shows why a wrong code is refused', async () => {
    const { fixture, el, fill } = render();
    fill('setup-code', 'ZZZZ-ZZZZ');
    el.querySelector<HTMLButtonElement>('button[type=submit]')!.click();
    http
      .expectOne('api/setup/verify')
      .flush(
        { detail: 'Code d’installation incorrect.' },
        { status: 403, statusText: 'Forbidden' },
      );
    await fixture.whenStable();
    fixture.detectChanges();

    expect(el.querySelector('[role=alert]')?.textContent).toContain('incorrect');
    expect(el.querySelector('h1')?.textContent).toContain('Bienvenue');
  });
});
