import { Component, inject, input } from '@angular/core';
import { GamePlatform } from '../core/api/models';
import { GamesService } from '../core/games/games.service';

@Component({
  selector: 'app-platform-badges',
  template: `
    <ul class="chips" [attr.aria-label]="label()">
      @for (platform of platforms(); track platform) {
        <li class="chip" [attr.data-platform]="platform">{{ games.label(platform) }}</li>
      }
    </ul>
  `,
  styles: `
    ul {
      display: flex;
      flex-wrap: wrap;
      gap: 0.3rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }
  `,
})
export class PlatformBadges {
  protected readonly games = inject(GamesService);
  readonly platforms = input.required<GamePlatform[]>();
  readonly label = input('Plateformes');
}
