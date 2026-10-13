import {
  ApplicationConfig,
  DOCUMENT,
  inject,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { ConclaveTitleStrategy } from './core/title.strategy';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import {
  ActivatedRouteSnapshot,
  TitleStrategy,
  provideRouter,
  withComponentInputBinding,
  withInMemoryScrolling,
  withViewTransitions,
} from '@angular/router';
import { routes } from './app.routes';
import { authInterceptor } from './core/auth/auth.interceptor';

/** First URL segment of a route snapshot tree ("messages", "clan", "login"…). */
function section(snapshot: ActivatedRouteSnapshot): string | undefined {
  for (let route: ActivatedRouteSnapshot | null = snapshot; route; route = route.firstChild) {
    if (route.url.length) {
      return route.url[0].path;
    }
  }
  return undefined;
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(
      routes,
      withComponentInputBinding(),
      // Table of contents links (#section) of long pages such as the terms of use
      withInMemoryScrolling({ anchorScrolling: 'enabled' }),
      withViewTransitions({
        skipInitialTransition: true,
        // Switching between conversations must stay instant: only animate between sections
        // (made instant rather than skipped: Angular logs skipped transitions as errors in dev mode)
        onViewTransitionCreated: ({ transition, from, to }) => {
          if (section(from) === section(to)) {
            const root = inject(DOCUMENT).documentElement;
            root.classList.add('vt-instant');
            void transition.finished.finally(() => root.classList.remove('vt-instant'));
          }
        },
      }),
    ),
    provideHttpClient(withFetch(), withInterceptors([authInterceptor])),
    { provide: TitleStrategy, useClass: ConclaveTitleStrategy },
  ],
};
