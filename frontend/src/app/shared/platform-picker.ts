import { NgTemplateOutlet } from '@angular/common';
import { Component, computed, inject, input, model } from '@angular/core';
import { GamePlatform, PlatformOption } from '../core/api/models';
import { GamesService } from '../core/games/games.service';

let nextId = 0;

/**
 * Multi-selection of platforms, as checkboxes styled as chips: the current platforms, then the
 * retro consoles in a section of their own, opened when the game is one of theirs.
 */
@Component({
  selector: 'app-platform-picker',
  template: `
    <fieldset>
      <legend>{{ legend() }}</legend>
      <div class="options">
        @for (option of current(); track option.value) {
          <ng-container *ngTemplateOutlet="chip; context: { $implicit: option }"></ng-container>
        }
      </div>
      @if (retro().length) {
        <details class="retro" [open]="retroOpen()">
          <summary>
            Consoles rétro
            @if (retroSelected()) {
              <span class="count">({{ retroSelected() }})</span>
            }
          </summary>
          <div class="options">
            @for (option of retro(); track option.value) {
              <ng-container *ngTemplateOutlet="chip; context: { $implicit: option }"></ng-container>
            }
          </div>
        </details>
      }
    </fieldset>

    <ng-template #chip let-option>
      <label
        class="chip chip-toggle"
        [class.suggested]="suggested().includes(option.value)"
        [title]="suggested().includes(option.value) ? 'Le jeu existe sur cette plateforme' : ''"
      >
        <input
          type="checkbox"
          [name]="name"
          [value]="option.value"
          [checked]="selected().includes(option.value)"
          (change)="toggle(option.value)"
        />
        {{ option.label }}
      </label>
    </ng-template>
  `,
  imports: [NgTemplateOutlet],
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
    .retro {
      margin-top: 0.6rem;
    }
    summary {
      margin-bottom: 0.4rem;
      color: var(--text-muted);
      font-size: 0.9rem;
      cursor: pointer;
    }
    .count {
      color: var(--accent-text);
      font-weight: 600;
    }
    /* Platforms the game was released on */
    .suggested {
      border-style: dashed;
      border-color: var(--accent);
    }
  `,
})
export class PlatformPicker {
  protected readonly games = inject(GamesService);
  protected readonly name = `platforms-${nextId++}`;

  readonly legend = input('Plateformes');
  readonly selected = model<GamePlatform[]>([]);
  /** Platforms the game exists on, when known (catalog): shown, and opening the retro section */
  readonly suggested = input<GamePlatform[]>([]);

  protected readonly current = computed(() => this.games.platforms().filter((p) => !p.retro));
  protected readonly retro = computed(() => this.games.platforms().filter((p) => p.retro));
  private readonly retroValues = computed(
    () => new Set(this.retro().map((p: PlatformOption) => p.value)),
  );
  protected readonly retroSelected = computed(
    () => this.selected().filter((p) => this.retroValues().has(p)).length,
  );
  protected readonly retroOpen = computed(
    () => this.retroSelected() > 0 || this.suggested().some((p) => this.retroValues().has(p)),
  );

  protected toggle(platform: GamePlatform): void {
    this.selected.update((current) =>
      current.includes(platform) ? current.filter((p) => p !== platform) : [...current, platform],
    );
  }
}
