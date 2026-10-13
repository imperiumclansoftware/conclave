import { Component, effect, inject, linkedSignal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ConsentService } from '../core/consent/consent.service';

/**
 * Cookie and third-party content consent banner. Not blocking (no cookie wall), with
 * "accept" and "refuse" equally prominent.
 */
@Component({
  selector: 'app-cookie-banner',
  imports: [RouterLink],
  template: `
    @if (consent.bannerVisible()) {
      <section class="banner card" aria-labelledby="cookie-title" aria-describedby="cookie-text">
        <h2 id="cookie-title">Cookies et contenus externes</h2>
        <p id="cookie-text">
          L'application n'utilise que des éléments indispensables à son fonctionnement (connexion,
          thème, temps réel), qui ne nécessitent pas votre accord. Elle peut aussi afficher des
          <strong>images hébergées par des services externes</strong>
          (avatars Gravatar et Discord, jaquettes et captures Steam et RAWG), qui reçoivent alors
          votre adresse IP et peuvent déposer leurs propres cookies.
          <a routerLink="/politique-confidentialite" fragment="cookies">En savoir plus</a>
        </p>

        @if (customizing()) {
          <ul class="choices">
            <li>
              <label class="choice">
                <input type="checkbox" checked disabled />
                <span
                  ><strong>Indispensables</strong> — connexion, thème, messages en temps réel.
                  Toujours actifs.</span
                >
              </label>
            </li>
            <li>
              <label class="choice">
                <input
                  type="checkbox"
                  [checked]="external()"
                  (change)="external.set(box.checked)"
                  #box
                />
                <span
                  ><strong>Contenus externes</strong> — images de Gravatar, Discord, Steam et RAWG.
                  Sans votre accord, les avatars sont remplacés par des initiales et les jaquettes
                  de jeux ne sont pas affichées.</span
                >
              </label>
            </li>
          </ul>
        }

        <div class="actions">
          <button class="btn" type="button" (click)="consent.decide(false)">Tout refuser</button>
          <button class="btn" type="button" (click)="consent.decide(true)">Tout accepter</button>
          @if (customizing()) {
            <button class="btn btn-ghost" type="button" (click)="consent.decide(external())">
              Enregistrer mes choix
            </button>
          } @else {
            <button class="btn btn-ghost" type="button" (click)="customizing.set(true)">
              Personnaliser
            </button>
          }
        </div>
      </section>
    }
  `,
  styles: `
    .banner {
      position: fixed;
      right: 1rem;
      bottom: 1rem;
      left: 1rem;
      z-index: 20;
      width: min(calc(100% - 2rem), 44rem);
      max-height: calc(100dvh - 2rem);
      margin: 0 auto;
      padding: 1.25rem 1.5rem;
      overflow-y: auto;
    }
    h2 {
      margin-bottom: 0.5rem;
      font-size: 1.05rem;
    }
    p {
      margin: 0 0 1rem;
      font-size: 0.95rem;
    }
    .choices {
      display: grid;
      gap: 0.6rem;
      margin: 0 0 1rem;
      padding: 0;
      list-style: none;
      font-size: 0.95rem;
    }
    .choice {
      display: flex;
      align-items: flex-start;
      gap: 0.6rem;
    }
    .choice input {
      margin-top: 0.3rem;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    /* Same prominence for refusing and accepting */
    .actions .btn:not(.btn-ghost) {
      flex: 1 1 10rem;
    }
  `,
})
export class CookieBanner {
  protected readonly consent = inject(ConsentService);
  protected readonly customizing = linkedSignal(() => this.consent.customizeRequested());
  /** Pending choice in the details (starts from the current one) */
  protected readonly external = linkedSignal(() => this.consent.externalContent());

  constructor() {
    // Move the focus to the banner when reopened from a "Gérer les cookies" link
    effect(() => {
      if (this.consent.customizeRequested()) {
        queueMicrotask(() =>
          document
            .getElementById('cookie-title')
            ?.closest('section')
            ?.querySelector<HTMLElement>('input:not([disabled]), button')
            ?.focus(),
        );
      }
    });
  }
}
