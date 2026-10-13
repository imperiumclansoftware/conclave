import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Icon } from '../../shared/icon';

@Component({
  selector: 'app-games-tabs',
  imports: [RouterLink, RouterLinkActive, Icon],
  template: `
    <nav class="tabs" aria-label="Jeux">
      <a routerLink="/games" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }"
        ><app-icon name="games" /> Mes jeux</a
      >
      <a routerLink="/games/clan" routerLinkActive="active"
        ><app-icon name="library" /> Ludothèque du clan</a
      >
    </nav>
  `,
  styles: `
    :host {
      display: block;
      margin-bottom: 1.25rem;
    }
  `,
})
export class GamesTabs {}
