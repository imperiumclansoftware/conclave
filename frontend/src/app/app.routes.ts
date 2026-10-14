import { Routes } from '@angular/router';
import {
  authGuard,
  filmsGuard,
  gamesGuard,
  guestGuard,
  installedGuard,
  leaderGuard,
  newsPublisherGuard,
  openChatPanel,
  setupGuard,
} from './core/auth/auth.guards';

export const routes: Routes = [
  {
    path: 'installation',
    canActivate: [setupGuard],
    title: 'Installation',
    loadComponent: () => import('./pages/setup/setup.page').then((m) => m.SetupPage),
  },
  {
    path: 'login',
    canActivate: [installedGuard, guestGuard],
    title: 'Connexion',
    loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'register',
    canActivate: [installedGuard, guestGuard],
    title: 'Inscription',
    loadComponent: () => import('./pages/register/register.page').then((m) => m.RegisterPage),
  },
  {
    path: 'forgot-password',
    canActivate: [installedGuard, guestGuard],
    title: 'Mot de passe oublié',
    loadComponent: () =>
      import('./pages/password/forgot-password.page').then((m) => m.ForgotPasswordPage),
  },
  {
    path: 'reset-password',
    title: 'Nouveau mot de passe',
    loadComponent: () =>
      import('./pages/password/reset-password.page').then((m) => m.ResetPasswordPage),
  },
  {
    // Public: readable before signing up
    path: 'conditions-utilisation',
    title: 'Conditions d’utilisation',
    loadComponent: () => import('./pages/legal/terms.page').then((m) => m.TermsPage),
  },
  {
    path: 'politique-confidentialite',
    title: 'Politique de confidentialité',
    loadComponent: () => import('./pages/legal/privacy.page').then((m) => m.PrivacyPage),
  },
  {
    path: 'discord/callback',
    canActivate: [authGuard],
    title: 'Discord',
    loadComponent: () =>
      import('./pages/discord/discord-callback.page').then((m) => m.DiscordCallbackPage),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/shell/shell').then((m) => m.Shell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'clan' },
      // Messaging lives in a sidebar: former links open it, on the clan page
      { path: 'messages', canActivate: [openChatPanel], children: [] },
      { path: 'messages/:memberId', canActivate: [openChatPanel], children: [] },
      {
        path: 'evenements',
        children: [
          {
            path: '',
            title: 'Événements',
            loadComponent: () => import('./pages/events/events.page').then((m) => m.EventsPage),
          },
          {
            path: 'nouveau',
            title: 'Créer un événement',
            loadComponent: () =>
              import('./pages/events/event-form.page').then((m) => m.EventFormPage),
          },
          {
            path: ':id',
            title: 'Événement',
            loadComponent: () =>
              import('./pages/events/event-detail.page').then((m) => m.EventDetailPage),
          },
          {
            path: ':id/modifier',
            title: 'Modifier l’événement',
            loadComponent: () =>
              import('./pages/events/event-form.page').then((m) => m.EventFormPage),
          },
        ],
      },
      {
        path: 'films/:id',
        title: 'Film',
        canActivate: [filmsGuard],
        loadComponent: () => import('./pages/films/film.page').then((m) => m.FilmPage),
      },
      {
        path: 'news',
        children: [
          {
            path: '',
            title: 'News du clan',
            loadComponent: () => import('./pages/news/news-list.page').then((m) => m.NewsListPage),
          },
          {
            path: 'nouvelle',
            title: 'Publier une news',
            canActivate: [newsPublisherGuard],
            loadComponent: () => import('./pages/news/news-form.page').then((m) => m.NewsFormPage),
          },
          {
            path: ':id',
            title: 'News',
            loadComponent: () =>
              import('./pages/news/news-detail.page').then((m) => m.NewsDetailPage),
          },
          {
            path: ':id/modifier',
            title: 'Modifier la news',
            loadComponent: () => import('./pages/news/news-form.page').then((m) => m.NewsFormPage),
          },
        ],
      },
      {
        path: 'admin',
        title: 'Administration',
        canActivate: [leaderGuard],
        loadComponent: () => import('./pages/admin/admin.page').then((m) => m.AdminPage),
      },
      {
        path: 'members/:id',
        title: 'Profil',
        loadComponent: () =>
          import('./pages/member/member-profile.page').then((m) => m.MemberProfilePage),
      },
      {
        path: 'account',
        title: 'Mon profil',
        loadComponent: () => import('./pages/account/account.page').then((m) => m.AccountPage),
      },
      {
        path: 'games',
        canActivate: [gamesGuard],
        children: [
          {
            path: '',
            title: 'Mes jeux',
            loadComponent: () => import('./pages/games/my-games.page').then((m) => m.MyGamesPage),
          },
          {
            path: 'clan',
            title: 'Ludothèque du clan',
            loadComponent: () =>
              import('./pages/games/clan-library.page').then((m) => m.ClanLibraryPage),
          },
          {
            path: ':id',
            title: 'Jeu',
            loadComponent: () =>
              import('./pages/games/game-detail.page').then((m) => m.GameDetailPage),
          },
        ],
      },
      {
        path: 'clan',
        title: 'Le clan',
        loadComponent: () => import('./pages/clan/clan.page').then((m) => m.ClanPage),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
