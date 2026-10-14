import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ChatPanelService } from '../chat/chat-panel.service';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return (await inject(AuthService).restoreSession()) || router.createUrlTree(['/login']);
};

/** Before the installation, every public page leads to the installation wizard. */
export const installedGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return (await inject(AuthService).installed()) || router.createUrlTree(['/installation']);
};

/** The installation wizard, only before the installation. */
export const setupGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return !(await inject(AuthService).installed()) || router.createUrlTree(['/login']);
};

export const guestGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return !(await inject(AuthService).restoreSession()) || router.createUrlTree(['/']);
};

/** /messages[/:memberId]: opens the messaging sidebar (on that conversation), over the home page. */
export const openChatPanel: CanActivateFn = (route) => {
  const memberId = Number(route.paramMap.get('memberId'));
  inject(ChatPanelService).open(memberId > 0 ? memberId : null);
  return inject(Router).createUrlTree(['/']);
};

/** Writing news: members of a publishing branch (the Communicants) */
export const newsPublisherGuard: CanActivateFn = () => {
  const router = inject(Router);
  return inject(AuthService).user()?.canPublishNews === true || router.createUrlTree(['/news']);
};

/** The games part: members of the Joueurs branch (otherwise, their profile to join it) */
export const gamesGuard: CanActivateFn = () => {
  const router = inject(Router);
  return (
    inject(AuthService).user()?.canAccessGames === true ||
    router.createUrlTree(['/account'], { queryParams: { onglet: 'branches' } })
  );
};

/** Film fiches: members of the Cinéphiles branch */
export const filmsGuard: CanActivateFn = () => {
  const router = inject(Router);
  return (
    inject(AuthService).user()?.canAccessFilms === true ||
    router.createUrlTree(['/account'], { queryParams: { onglet: 'branches' } })
  );
};

export const leaderGuard: CanActivateFn = () => {
  const router = inject(Router);
  return inject(AuthService).isLeader() || router.createUrlTree(['/']);
};
