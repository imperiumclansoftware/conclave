import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RegisterPage } from './register.page';

describe('RegisterPage', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [RegisterPage],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  async function render(initialized: boolean) {
    const fixture = TestBed.createComponent(RegisterPage);
    fixture.detectChanges();
    http.expectOne('api/setup').flush({ initialized, clanName: initialized ? 'Imperium' : null });
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture;
  }

  function fill(el: HTMLElement, id: string, value: string) {
    const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
    input.value = value;
    input.dispatchEvent(new Event('input'));
  }

  it('asks to join the existing clan and tells the request is pending', async () => {
    const fixture = await render(true);
    const el = fixture.nativeElement as HTMLElement;
    expect(el.querySelector('h1')?.textContent).toContain('Rejoindre Imperium');
    expect(el.querySelector('#clan-name')).toBeNull();

    fill(el, 'display-name', 'Marc');
    fill(el, 'email', 'marc@example.com');
    fill(el, 'username', 'Marc');
    fill(el, 'new-password', 'motdepasse');
    el.querySelector<HTMLButtonElement>('button[type=submit]')!.click();

    const request = http.expectOne('api/register');
    expect(request.request.body).toEqual({
      displayName: 'Marc',
      email: 'marc@example.com',
      username: 'marc',
      password: 'motdepasse',
    });
    request.flush({ status: 'pending' });
    await fixture.whenStable();
    fixture.detectChanges();

    expect(el.querySelector('h1')?.textContent).toContain('Demande envoyée');
    expect(el.querySelector('[role=status]')?.textContent).toContain('Imperium');
  });
});
