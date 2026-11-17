import { HttpClient } from '@angular/common/http';
import { Component, OnInit, inject, input, signal } from '@angular/core';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Emblem } from '../../shared/emblem';

interface AuthorizationInfo {
  client: string;
  host: string;
  scopes: string[];
  claims: { name: string; branches?: string[] };
}

const BRANCH_NAMES: Record<string, string> = {
  players: 'Joueurs',
  developers: 'Développeurs',
  communicants: 'Communicants',
  cinephiles: 'Cinéphiles',
};

/**
 * Consent to an OpenID Connect sign-in (e.g. the Imperium Clan portal): reached from GET /oauth/authorize
 * with a signed request (?demande=…), or with an error to show (?erreur=…).
 */
@Component({
  selector: 'app-authorize-page',
  imports: [Emblem],
  styleUrl: '../auth-layout.scss',
  template: `
    <main class="card">
      <header class="crest">
        <app-emblem [size]="72" />
        <p class="app-name">Conclave</p>
      </header>

      @if (erreur(); as message) {
        <h1>Connexion impossible</h1>
        <p class="error" role="alert">{{ message }}</p>
        <p class="subtitle">Recommencez depuis l’application qui vous a envoyé ici.</p>
      } @else if (problem(); as message) {
        <h1>Connexion impossible</h1>
        <p class="error" role="alert">{{ message }}</p>
      } @else if (info(); as i) {
        <h1>Se connecter à {{ i.client }}</h1>
        <p class="subtitle">
          <strong>{{ i.client }}</strong> ({{ i.host }}) souhaite vous connecter avec votre compte
          Conclave.
        </p>
        <section class="partage" aria-labelledby="partage-titre">
          <h2 id="partage-titre">Sera transmis</h2>
          <ul>
            <li>un identifiant anonyme, toujours le même pour vous ;</li>
            <li>
              votre nom affiché : <strong>{{ i.claims.name }}</strong>
            </li>
            @if (i.claims.branches) {
              <li>
                vos branches :
                <strong>{{ branchNames(i.claims.branches) }}</strong>
              </li>
            }
          </ul>
          <p class="subtitle">
            Jamais votre mot de passe, votre adresse e-mail ni votre identifiant de connexion.
          </p>
        </section>
        <div class="actions">
          <button class="btn" type="button" [disabled]="pending()" (click)="decide(true)">
            Continuer
          </button>
          <button
            class="btn btn-ghost"
            type="button"
            [disabled]="pending()"
            (click)="decide(false)"
          >
            Refuser
          </button>
        </div>
      } @else {
        <p class="subtitle" role="status">Chargement…</p>
      }
    </main>
  `,
  styles: `
    .partage {
      display: grid;
      gap: 0.5rem;
      text-align: left;
    }
    .partage h2 {
      font-size: 1rem;
      margin: 0;
    }
    .partage ul {
      margin: 0;
      padding-left: 1.25rem;
      display: grid;
      gap: 0.25rem;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: center;
    }
  `,
})
export class AuthorizePage implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Signed request from /oauth/authorize */
  readonly demande = input<string>();
  /** Error found by /oauth/authorize (unknown application or redirect address) */
  readonly erreur = input<string>();

  protected readonly info = signal<AuthorizationInfo | null>(null);
  protected readonly problem = signal<string | null>(null);
  protected readonly pending = signal(false);

  async ngOnInit(): Promise<void> {
    const demande = this.demande();
    if (this.erreur()) {
      return;
    }
    if (!demande) {
      this.problem.set('Demande de connexion absente.');
      return;
    }
    // Not signed in yet: sign in, then come back here with the same request
    if (!(await this.auth.restoreSession())) {
      await this.router.navigate(['/login'], { queryParams: { retour: this.router.url } });
      return;
    }
    try {
      this.info.set(
        await firstValueFrom(
          this.http.get<AuthorizationInfo>('api/oauth/authorization', { params: { demande } }),
        ),
      );
    } catch (e) {
      this.problem.set(apiErrorMessage(e));
    }
  }

  protected async decide(approve: boolean): Promise<void> {
    this.pending.set(true);
    try {
      const { redirect } = await firstValueFrom(
        this.http.post<{ redirect: string }>('api/oauth/authorization', {
          demande: this.demande(),
          approve,
        }),
      );
      // Back to the application (address checked by the server against the registered ones)
      window.location.assign(redirect);
    } catch (e) {
      this.problem.set(apiErrorMessage(e));
      this.pending.set(false);
    }
  }

  protected branchNames(crests: string[]): string {
    return crests.map((c) => BRANCH_NAMES[c] ?? c).join(', ') || 'aucune';
  }
}
