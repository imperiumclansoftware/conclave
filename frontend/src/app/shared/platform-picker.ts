import { Component, inject, input, model } from '@angular/core';
import { GamePlatform } from '../core/api/models';
import { GamesService } from '../core/games/games.service';

let nextId = 0;

/** Multi-selection of platforms, as checkboxes styled as chips. */
@Component({
  selector: 'app-platform-picker',
  template: `
    <fieldset>
      <legend>{{ legend() }}</legend>
      <div class="options">
        @for (option of games.platforms(); track option.value) {
          <label class="chip chip-toggle">
            <input
              type="checkbox"
              [name]="name"
              [value]="option.value"
              [checked]="selected().includes(option.value)"
              (change)="toggle(option.value)"
            />
            {{ option.label }}
          </label>
        }
      </div>
    </fieldset>
  `,
  styles: `
    fieldset {
      margin: 0;
      padding: 0;
      border: none;
    }
    legend {
      margin-bottom: 0.4rem;
      font-family: var(--font-display);
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    .options {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
    }
  `,
})
export class PlatformPicker {
  protected readonly games = inject(GamesService);
  protected readonly name = `platforms-${nextId++}`;

  readonly legend = input('Plateformes');
  readonly selected = model<GamePlatform[]>([]);

  protected toggle(platform: GamePlatform): void {
    this.selected.update((current) =>
      current.includes(platform) ? current.filter((p) => p !== platform) : [...current, platform],
    );
  }
}
