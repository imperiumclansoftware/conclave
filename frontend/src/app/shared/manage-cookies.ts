import { Component, inject } from '@angular/core';
import { ConsentService } from '../core/consent/consent.service';

/** "Gérer les cookies" link-styled button, reopening the consent banner. */
@Component({
  selector: 'app-manage-cookies',
  template: `<button type="button" (click)="consent.manage()">Gérer les cookies</button>`,
  styles: `
    button {
      padding: 0;
      border: none;
      background: none;
      color: var(--accent-text);
      font: inherit;
      text-decoration: underline;
      cursor: pointer;
    }
  `,
})
export class ManageCookies {
  protected readonly consent = inject(ConsentService);
}
