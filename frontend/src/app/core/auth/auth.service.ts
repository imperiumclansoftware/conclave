import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, firstValueFrom, tap } from 'rxjs';
import { AuthResponse, Me, RegisterResponse, SetupPayload, SetupStatus } from '../api/models';

const TOKEN_KEY = 'clan-app.token';

export interface RegisterPayload {
  username: string;
  displayName: string;
  email: string;
  password: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly _token = signal<string | null>(readToken());
  private readonly _user = signal<Me | null>(null);

  readonly token = this._token.asReadonly();
  readonly user = this._user.asReadonly();
  readonly isLeader = computed(() => this._user()?.role === 'leader');
  private _installed = false;

  login(username: string, password: string): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>('api/login', { username, password })
      .pipe(tap((response) => this.storeToken(response.token)));
  }

  setup(): Observable<SetupStatus> {
    return this.http.get<SetupStatus>('api/setup');
  }

  /** Whether the installation wizard has run; known for good once it is true. */
  async installed(): Promise<boolean> {
    if (!this._installed) {
      try {
        this._installed = (await firstValueFrom(this.setup())).initialized;
      } catch {
        // API unreachable: let the pages show the error
        return true;
      }
    }
    return this._installed;
  }

  /** First step of the installation wizard: the code shown on the server. */
  verifySetupCode(code: string): Observable<void> {
    return this.http.post<void>('api/setup/verify', { code });
  }

  /** Installs the app: creates the clan and its first leader, logged in right away. */
  install(payload: SetupPayload): Observable<AuthResponse & { user: Me }> {
    return this.http.post<AuthResponse & { user: Me }>('api/setup', payload).pipe(
      tap((response) => {
        this._installed = true;
        this.storeToken(response.token);
        this._user.set(response.user);
      }),
    );
  }

  /** Asks to join the clan: the account waits for a leader's approval. */
  register(payload: RegisterPayload): Observable<RegisterResponse> {
    return this.http.post<RegisterResponse>('api/register', payload);
  }

  forgotPassword(identifier: string): Observable<void> {
    return this.http.post<void>('api/password/forgot', { identifier });
  }

  resetPassword(token: string, password: string): Observable<void> {
    return this.http.post<void>('api/password/reset', { token, password });
  }

  /** Loads the current user's profile; resolves to false when there is no valid session. */
  async restoreSession(): Promise<boolean> {
    if (!this._token()) {
      return false;
    }
    if (this._user()) {
      return true;
    }
    try {
      this._user.set(await firstValueFrom(this.http.get<Me>('api/me')));
      return true;
    } catch (e) {
      // Only a rejected session logs out: a network error or an interrupted request keeps it
      if (e instanceof HttpErrorResponse && (e.status === 401 || e.status === 403)) {
        this.clear();
      }
      return false;
    }
  }

  /** Reloads the user's profile, e.g. after a change of rights (joining a publishing branch). */
  async refreshUser(): Promise<void> {
    if (this._user()) {
      this._user.set(await firstValueFrom(this.http.get<Me>('api/me')));
    }
  }

  /** Applies a change of the user's own profile (name, avatar…). */
  updateUser(changes: Partial<Me>): void {
    this._user.update((user) => (user ? { ...user, ...changes } : user));
  }

  logout(): void {
    this.clear();
    void this.router.navigate(['/login']);
  }

  private storeToken(token: string): void {
    this._token.set(token);
    try {
      localStorage.setItem(TOKEN_KEY, token);
    } catch {
      // Storage unavailable (private mode…): the session lasts until the page is closed
    }
  }

  private clear(): void {
    this._token.set(null);
    this._user.set(null);
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch {
      // Nothing to clean up
    }
  }
}

function readToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}
