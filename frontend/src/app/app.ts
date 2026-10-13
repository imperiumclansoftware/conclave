import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ThemeService } from './core/theme/theme.service';
import { Backdrop } from './shared/backdrop';
import { CookieBanner } from './shared/cookie-banner';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Backdrop, CookieBanner],
  template: '<app-backdrop /><router-outlet /><app-cookie-banner />',
})
export class App {
  constructor() {
    // Instantiated early so that the favicon matches the stored faction
    inject(ThemeService);
  }
}
