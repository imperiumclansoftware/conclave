import { Component, computed, input } from '@angular/core';
import { ICONS, IconName } from './icons.generated';

/**
 * Icon from game-icons.net (see scripts/build-icons.mjs), drawn inline in the current text
 * color and size. Decorative by default; give a label when the icon carries meaning on its own.
 */
@Component({
  selector: 'app-icon',
  template: `
    <svg
      [attr.viewBox]="icon().viewBox"
      [attr.aria-hidden]="label() ? null : 'true'"
      [attr.role]="label() ? 'img' : null"
      [attr.aria-label]="label() ?? null"
    >
      @for (d of icon().paths; track $index) {
        <path [attr.d]="d" fill="currentColor" />
      }
    </svg>
  `,
  styles: `
    :host {
      display: inline-flex;
      flex: none;
      width: 1.15em;
      height: 1.15em;
      vertical-align: -0.2em;
    }
    svg {
      width: 100%;
      height: 100%;
    }
  `,
})
export class Icon {
  readonly name = input.required<IconName>();
  readonly label = input<string>();

  protected readonly icon = computed(() => ICONS[this.name()]);
}
