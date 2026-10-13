import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthService } from './auth.service';

describe('AuthService.restoreSession', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.setItem('clan-app.token', 'jwt');
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => localStorage.clear());

  it('keeps the session after a network error or an interrupted request', async () => {
    const auth = TestBed.inject(AuthService);
    const restore = auth.restoreSession();
    http.expectOne('api/me').error(new ProgressEvent('error'));

    expect(await restore).toBe(false);
    expect(auth.token()).toBe('jwt');
    expect(localStorage.getItem('clan-app.token')).toBe('jwt');
  });

  it('logs out when the server rejects the session', async () => {
    const auth = TestBed.inject(AuthService);
    const restore = auth.restoreSession();
    http
      .expectOne('api/me')
      .flush({ message: 'Expired JWT Token' }, { status: 401, statusText: 'Unauthorized' });

    expect(await restore).toBe(false);
    expect(auth.token()).toBeNull();
    expect(localStorage.getItem('clan-app.token')).toBeNull();
  });
});
