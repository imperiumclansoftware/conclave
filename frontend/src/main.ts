import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { App } from './app/app';

// Popover API (user menu): polyfilled only in browsers lacking it
if (!('popover' in HTMLElement.prototype)) {
  void import('@oddbird/popover-polyfill');
}

bootstrapApplication(App, appConfig).catch((err) => console.error(err));
