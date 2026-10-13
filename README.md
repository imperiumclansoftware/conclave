# Conclave

**Le quartier général privé de votre clan.** Conclave réunit les membres d'un clan de joueurs :
messagerie instantanée, présences et visios Discord, jeux de chacun, branches à blasons, news,
liens favoris, aux couleurs de la Confrérie (thème clair) ou de l'Ordre (thème sombre).

Comptes dirigeants et membres, messagerie instantanée entre les membres d'un même clan. Les messages sont conservés et les non-lus sont signalés quand un membre se reconnecte.

| Partie     | Stack                                                       | URL de dev            |
|------------|-------------------------------------------------------------|-----------------------|
| `backend`  | Symfony 8, Doctrine ORM, PostgreSQL 16, LexikJWT, Mercure    | http://127.0.0.1:8000 |
| `frontend` | Angular 22 (standalone, signals, SCSS)                      | http://localhost:4200 |
| hub        | Mercure 1.1 (Docker), temps réel par Server-Sent Events     | http://localhost:3000 |

## Fonctionnement

- **Installation** : à la première visite, l'assistant d'installation (`/installation`) crée le clan et
  son premier dirigeant. Il demande le **code d'installation** affiché sur le serveur
  (`php bin/console app:setup:code`, ou les journaux), pour que seul l'administrateur du serveur puisse
  le faire, pas le premier venu. Les autres membres demandent ensuite à rejoindre le clan
  (`/register`) et un dirigeant valide leur compte ; un dirigeant peut aussi les ajouter lui-même.
- **Messagerie** : conversations 1-à-1, uniquement entre membres d'un même clan. Elle s'ouvre
  dans un panneau latéral à droite (petit onglet « Messages » au bord droit, Échap pour le fermer),
  à côté de la page en cours ; sous 64rem de large, le panneau passe par-dessus la page.
  Son état ouvert/fermé est mémorisé dans le navigateur. Les boutons « Écrire » ouvrent le
  panneau sur la conversation ; les anciens liens `/messages/{id}` y redirigent aussi.
  L'accueil est « Le clan » (`/clan`).
- **Branches** : l'application propose un catalogue de branches, une par blason (`Clan/BranchCatalog.php` :
  Joueurs, Développeurs, Communicants, Cinéphiles). Les dirigeants ne créent ni ne suppriment de branche :
  ils les activent ou les désactivent dans l'administration, onglet *Branches*, et peuvent changer leur
  nom, leur description et leurs options. Les branches du catalogue absentes du clan y sont ajoutées
  désactivées. Une branche désactivée est masquée aux membres et ne donne plus de droits (publier les
  news) ; ses membres sont conservés et la retrouvent à sa réactivation. Ajouter une branche au catalogue
  demande un blason : nouvelle valeur de `BranchCrest` (backend et frontend), son dessin et sa définition.
  - Chaque membre choisit ses branches dans son profil, onglet *Branches* (`/account?onglet=branches`) :
    il en rejoint ou quitte autant qu'il veut. Ses branches sont affichées sur sa fiche.
  - La page « Le clan » présente les membres par branche : un onglet « Tous » et un par branche active
    (`/clan?branche={id}`).
  - Une branche peut exiger la validation d'un dirigeant (case « Adhésion validée par un dirigeant »,
    active pour les Communicants) : le membre envoie une demande depuis son profil, visible dans l'onglet
    *Demandes* de l'administration avec les inscriptions (même pastille), et il est prévenu en temps réel
    de la réponse. Les dirigeants rejoignent ces branches sans demande.
  - Chaque branche a ses **liens favoris**, partagés par ses membres dans une colonne de droite (onglet
    *Liens* au bord droit, comme *Messages*) : les liens de toutes mes branches, regroupés par branche.
    Les membres de la branche en ajoutent (titre facultatif : le nom du site le remplace) ; celui qui a
    ajouté un lien, ou un dirigeant, le retire. Les autres membres voient les changements en temps réel.
    Une seule colonne est ouverte à la fois (`core/side-panel/side-panel.service.ts`).
  - Blasons dessinés par l'application (`shared/branch-crest.ts`) aux couleurs de la Confrérie ou de
    l'Ordre selon le thème : *Joueurs* (manette sur épées croisées), *Développeurs* (`</>` dont la barre
    est une lame secrète), *Communicants* (trompette de héraut à bannière) et *Cinéphiles* (clap de
    cinéma), sinon une simple bannière.
- **News** : actualités du clan (titre, texte, image facultative), lues par tous les membres (menu
  *News*). Seuls les membres des branches autorisées dans l'administration (case « Ses membres peuvent
  publier des news »), c'est-à-dire les *Communicants*, les publient : un dirigeant doit rejoindre la
  branche pour publier. L'auteur modifie et supprime ses news ; les dirigeants peuvent supprimer
  toutes les news (modération). Les images (8 Mo max.) sont réduites à 1600 px en WebP et servies
  sous un nom aléatoire (`/api/news-images/…`). Sans image, un visuel est généré dans le navigateur
  (`shared/news-cover.ts`) à partir du titre : sceau avec l'initiale, rayons et ornements aux couleurs
  de la faction active.
- **Carte de membre** : au survol à la souris du nom d'un membre (page Le clan, news, conversations,
  joueurs d'un jeu, administration), une carte affiche ses branches avec leur blason, son nombre de jeux,
  de news publiées et de branches, son ancienneté, et des liens vers sa fiche et la messagerie
  (`shared/member-card.ts`, directive `[appMemberCard]`, `GET /api/members/{id}/card`). Elle apparaît
  après un court délai, reste ouverte tant que la souris est dessus et se ferme avec Échap. Sur écran
  tactile ou au clavier, le lien mène à la fiche, qui contient les mêmes informations.
- **Persistance** : chaque message est enregistré en base avant d'être diffusé. Chaque participant a
  un marqueur « lu jusqu'au message n° X », d'où le nombre de non-lus et le séparateur
  « Nouveaux messages » au retour d'un membre.
- **Temps réel** : à l'envoi, l'API publie une mise à jour Mercure *privée* sur le topic personnel
  de chaque participant (`https://clan-app.local/users/{id}`). Le navigateur s'y abonne via
  `EventSource`, autorisé par un cookie HttpOnly limité à son propre topic. Si le hub est
  indisponible, le message reste enregistré ; après une coupure, le client resynchronise.

```
Angular ──/api──────────────▶ Symfony ──publish──▶ Mercure
   ▲   (proxy de dev :4200)      │                   │
   └──/.well-known/mercure ◀─────┼───── SSE ─────────┘
                                 ▼
                             PostgreSQL
```

En dev, le navigateur ne parle qu'à `localhost:4200` : le proxy Angular (`frontend/proxy.conf.mjs`)
transmet `/api` à Symfony et `/.well-known/mercure` au hub, ce qui permet au cookie Mercure de
fonctionner sans configuration CORS.

## Prérequis

PHP 8.4+, Composer, Symfony CLI, Node 24+, Docker.

## Démarrage

```bash
# Backend
cd backend
composer install
docker compose up -d --wait                 # PostgreSQL + hub Mercure
php bin/console lexik:jwt:generate-keypair  # une seule fois (clés ignorées par git)
php bin/console doctrine:migrations:migrate -n
php bin/console app:setup:code              # code demandé par l'assistant d'installation
symfony server:start -d --no-tls --port=8000

# Frontend (autre terminal)
cd frontend
npm install
npm start
```

Ouvrir http://localhost:4200 : l'assistant d'installation crée le clan et son dirigeant. Ajouter ensuite
un membre (*Administration > Membres*), puis se connecter avec son compte dans une fenêtre de navigation
privée pour discuter entre les deux.

Les fichiers `.env` et `.env.dev` versionnés ne contiennent que des valeurs d'exemple. Les secrets d'une
installation (`APP_SECRET`, `JWT_PASSPHRASE`, `MERCURE_JWT_SECRET`…) se placent dans `.env.local` et
`.env.dev.local`, ignorés par git. `APP_SECRET` chiffre aussi les secrets saisis dans l'administration :
le changer les rend illisibles (il faut alors les ressaisir). Docker Compose ne lit que `.env` : pour
le hub Mercure, exporter la même valeur, par exemple
`MERCURE_JWT_SECRET=… docker compose up -d`.

Si Docker ne tourne pas sur `localhost` (Docker distant, DinD…), surcharger les URLs :
`DATABASE_URL` et `MERCURE_URL` dans `backend/.env.local` (et `.env.test.local` pour les tests),
et `MERCURE_HUB_URL=http://<hôte>:3000 npm start` côté frontend.

### Derrière code-server

Le proxy `/proxy/4200/` de code-server retire le préfixe d'URL, ce qui empêche l'application de
charger ses scripts (page vide). Utiliser `/absproxy/4200/`, qui le conserve :

```bash
cd frontend && npm run start:code-server   # sert l'app sous /absproxy/4200/
```

puis ouvrir `https://<domaine-code-server>/absproxy/4200/`. Côté backend, dans `.env.local` :
`MERCURE_BROWSER_URL=https://<domaine-code-server>/absproxy/4200/.well-known/mercure`.
Ajouter aussi le domaine à `allowedHosts` (`angular.json`, cible `serve`).

## Jeux

Chaque membre déclare les jeux auxquels il joue et sur quelles plateformes (PC, PS5, PS4,
Xbox Series, Xbox One, Switch, Switch 2, Mobile). Les informations (jaquette, description, genres,
développeurs, captures d'écran) sont récupérées automatiquement :

- **Steam** par défaut : aucune clé nécessaire, descriptions en français, mais jeux PC uniquement ;
- **RAWG** si `RAWG_API_KEY` est renseignée (`backend/.env.local`) : toutes les plateformes, consoles
  comprises. Clé gratuite sur https://rawg.io/apidocs.

Les fiches sont mises en cache en base (rafraîchies au bout de 30 jours) et les recherches pendant 1 h.

## Présence et visios Discord

**Présence dans l'app** (sans configuration) : chaque onglet ouvert envoie un signal toutes les 30 s,
d'où trois états diffusés en direct au clan : en ligne, absent (onglet en arrière-plan), hors ligne.

**Discord** (facultatif) : statut Discord (en ligne, inactif, ne pas déranger), activité
(« Joue à Elden Ring ») et **visios** : un dirigeant ou un membre choisit des membres du clan,
le bot crée sur le serveur Discord du clan un salon vocal privé (visible des seuls invités, vidéo
et partage d'écran autorisés), prévient les invités par message privé et dans l'app, puis supprime
le salon quand tout le monde est parti (ou au bout de 15 min si personne ne l'a rejoint).
Discord ne permet pas d'intégrer un appel dans une page web : « Rejoindre » ouvre Discord.

### Mise en place

La configuration se fait dans l'**administration** de l'app (menu sous son nom, réservé aux
dirigeants) : identifiants Discord, test de connexion, état du worker,
clé RAWG. Les secrets y sont chiffrés en base (clé dérivée de `APP_SECRET`) et ne sont jamais
renvoyés au navigateur. Les variables d'environnement ci-dessous restent possibles en repli.

1. Sur https://discord.com/developers/applications, créer une application :
   - **OAuth2** : noter le *Client ID* et le *Client Secret*, ajouter la redirection
     `https://<domaine-de-l-app>/discord/callback` (derrière code-server :
     `https://<domaine>/absproxy/4200/discord/callback`) ;
   - **Bot** : générer le *Token* et activer **Presence Intent** (Privileged Gateway Intents).
2. Les saisir dans *Admin > Discord* (bouton « Tester la connexion »), ou dans `backend/.env.local` :
   ```
   DISCORD_CLIENT_ID=...
   DISCORD_CLIENT_SECRET=...
   DISCORD_BOT_TOKEN=...
   DISCORD_REDIRECT_URI=https://<domaine-de-l-app>/discord/callback
   ```
3. Lancer le worker qui écoute Discord (présences, occupation des salons) — en production, le
   confier à un gestionnaire de processus (systemd, Supervisor, conteneur). Il attend tant que
   Discord n'est pas configuré et se reconnecte quand la configuration change :
   ```bash
   php bin/console app:discord:gateway
   ```
4. Dans l'app : un dirigeant clique sur **Admin > Discord > Connecter un serveur Discord** (ajoute le bot),
   puis chaque membre sur **Mon compte > Lier mon compte Discord** (il rejoint alors le serveur).

## Clan, inscriptions et administration

L'application sert **un seul clan**.

- **Installation** : l'assistant (`/installation`, protégé par le code d'installation du serveur) crée le
  clan et son premier dirigeant, enregistre l'adresse publique de l'application et le connecte. Il n'est
  plus accessible ensuite, et l'inscription (`/register`) n'est possible qu'une fois l'application
  installée.
- **Inscriptions suivantes** : chacun peut créer son compte (pseudo, identifiant, e-mail, mot de passe),
  mais il reste **en attente** et ne peut pas se connecter tant qu'un dirigeant ne l'a pas validé.
  Les dirigeants sont alertés par e-mail et dans l'app (pastille sur leur nom et dans le menu *Administration*) ; l'inscrit reçoit un
  e-mail quand sa demande est validée (ou refusée : le compte est alors supprimé).
- **Administration** (menu sous son nom > *Administration*, réservée aux dirigeants) :
  - *Demandes* : valider / refuser les inscriptions ;
  - *Membres* : rôles (membre / dirigeant ; le clan garde toujours au moins un dirigeant), retrait,
    ajout direct d'un membre (actif tout de suite) ;
  - *Général* : nom du clan, adresse publique de l'app (pour les liens des e-mails) ;
  - *E-mail* : mode d'envoi (serveur SMTP, sendmail, DSN avancé ou désactivé). Pour le SMTP, champ par champ
    (serveur, port, chiffrement STARTTLS/SSL/aucun, identifiant, mot de passe) avec des réglages tout prêts
    (Gmail, OVHcloud, IONOS, Orange, Free, Brevo, Mailjet, Mailpit). Adresse et nom d'expéditeur. Le test
    utilise les valeurs du formulaire, même non enregistrées : il vérifie connexion, authentification et
    envoi vers le destinataire choisi, et explique les erreurs courantes. Le tout est enregistré sous forme
    de DSN Symfony (caractères spéciaux encodés automatiquement) ; le mot de passe enregistré n'est réutilisé
    que pour le même serveur et le même identifiant ;
  - *Discord* et *Catalogue de jeux* (voir plus haut).
- **Mot de passe oublié** : lien envoyé par e-mail, valable 1 h et à usage unique (jeton signé,
  invalidé dès que le mot de passe change). La réponse ne révèle pas si le compte existe.
- **Secours** : `php bin/console app:user:leader <identifiant>` nomme un dirigeant (et valide son
  compte), par exemple si plus aucun dirigeant ne peut se connecter.

Les secrets saisis dans l'administration (DSN du mail, jetons Discord, clé RAWG) sont chiffrés en base
(clé dérivée de `APP_SECRET`) et jamais renvoyés au navigateur ; les variables de `.env.local`
(`MAILER_DSN`, `MAILER_FROM`, `MAILER_FROM_NAME`, `APP_PUBLIC_URL`, `DISCORD_*`, `RAWG_API_KEY`) restent possibles en repli.

## Profils

- **Profil** (*clic sur son nom*) : avatar au choix parmi une image envoyée (JPEG/PNG/WebP/GIF ≤ 5 Mo,
  recadrée en carré 256 px WebP, stockée dans `backend/var/avatars/<env>/`), **Gravatar** (seule
  l'empreinte SHA-256 de l'adresse est conservée, l'image est chargée depuis gravatar.com), l'avatar
  Discord ou l'initiale — par défaut la première source configurée dans cet ordre ; nom affiché,
  e-mail (visible des seuls dirigeants), présentation, identifiants de jeu (PSN, Xbox, Steam,
  Nintendo, Epic, Battle.net), mot de passe, compte Discord.
- **Fiche publique** de chaque membre (`/members/{id}`) : présence, identifiants à copier, jeux.

## Pages légales et cookies

- **Conditions d'utilisation** : `/conditions-utilisation` ; **politique de confidentialité** :
  `/politique-confidentialite` (pages publiques, liées depuis l'inscription, la connexion et le profil ;
  date de mise à jour dans `frontend/src/app/pages/legal/*.page.ts`).
- **Bannière de consentement** (`shared/cookie-banner.ts`) : l'app ne dépose que des éléments
  indispensables (exemptés de consentement). Les **images de services tiers** (avatars Gravatar et
  Discord, jaquettes et captures Steam/RAWG) ne sont chargées qu'avec l'accord du visiteur
  (`ConsentService.src()`), sans référent (`referrerpolicy="no-referrer"`). « Tout refuser » et
  « Tout accepter » ont la même importance, aucun blocage de l'accès, choix redemandé après 6 mois et
  modifiable via « Gérer les cookies » (connexion, profil, pages légales).

## Thèmes : Assassins et Templiers

Par défaut, l'interface suit le mode clair/sombre de l'appareil : **clair = Assassins**, **sombre = Templiers**.
Chaque membre choisit son camp dans *Mon profil > Apparence* (Confrérie, Ordre, ou suivre l'appareil) ;
ce choix est enregistré dans son compte et appliqué sur tous ses appareils. Le bouton « Rejoindre
l'Ordre » / « Rejoindre la Confrérie » de l'en-tête change aussi la préférence du compte.

| | Assassins (clair) | Templiers (sombre) |
|---|---|---|
| Palette | parchemin, blanc, cramoisi, encre | pierre noire, rouge sang, or |
| Fond | croquis de codex, silhouette de Florence, aigle en vol | croix en filigrane, bannières, lueur de bougie, braises |
| Effets | badges à aura dorée, transitions révélant la page comme une fresque, lame secrète | badges sceau de cire, transitions dans l'ombre, épée |

- Polices auto-hébergées (`@fontsource`) : Cinzel (titres) et EB Garamond (texte).
- Icônes : [game-icons.net](https://game-icons.net) (Lorc, Delapouite et contributeurs, licence CC BY 3.0,
  créditée dans les conditions d'utilisation), intégrées en SVG sans police ni CDN. Seules les icônes
  utilisées sont embarquées : pour en ajouter une, la déclarer dans `frontend/scripts/build-icons.mjs`
  puis lancer `npm run icons`, et l'utiliser avec `<app-icon name="…" />`.
- Emblèmes et décors : SVG originaux, inspirés de l'univers mais sans reprendre les logos
  officiels (marques d'Ubisoft). Remplaçables dans `src/app/shared/emblem.ts` et `public/favicon*.svg`.
- Le thème repose uniquement sur `<meta name="color-scheme">` et du CSS (`src/styles/_faction.scss`) :
  il fonctionne sans JavaScript et sans flash au chargement. Les animations sont coupées si
  l'utilisateur demande à réduire les animations.

## Tests

```bash
cd backend && php bin/phpunit       # tests fonctionnels de l'API (base PostgreSQL app_test)
cd frontend && npx ng test          # tests unitaires (Vitest)
```

Qualité du code :

```bash
cd backend && vendor/bin/phpstan analyse          # analyse statique (niveau 6)
cd backend && vendor/bin/php-cs-fixer fix         # style @Symfony
cd frontend && npm run format                     # Prettier (npm run format:check en CI)
```

Pour la base de test : `php bin/console doctrine:database:create --env=test` (une fois).

## API

Toutes les routes sauf `setup`, `register`, `login`, `password/*` et `health` exigent
`Authorization: Bearer <jwt>`.

| Méthode | Route                                   | Description                                         |
|---------|-----------------------------------------|-----------------------------------------------------|
| GET     | `/api/setup`                            | `{initialized, clanName}` : l'application est-elle installée |
| POST    | `/api/setup/verify`                     | `{code}` → vérifie le code d'installation (10 essais / 15 min) |
| POST    | `/api/setup`                            | `{code, clanName, appUrl?, username, displayName, email, password}` → crée le clan et son dirigeant (JWT) |
| POST    | `/api/register`                         | Demande à rejoindre le clan (compte en attente de validation) |
| POST    | `/api/login`                            | `{username, password}` → `{token}`                  |
| GET     | `/api/me`                               | Profil et clan de l'utilisateur                     |
| GET     | `/api/clan/members`                     | Membres du clan                                     |
| GET     | `/api/conversations`                    | Conversations avec dernier message et non-lus       |
| POST    | `/api/conversations`                    | `{userId}` → ouvre/crée la conversation 1-à-1       |
| GET     | `/api/conversations/{id}/messages`      | 50 derniers messages, `?before={id}` pour remonter  |
| POST    | `/api/conversations/{id}/messages`      | `{content}` → envoie un message                     |
| POST    | `/api/conversations/{id}/read`          | `{messageId}` → marque comme lu jusqu'à ce message  |
| POST    | `/api/mercure/authorize`                | Pose le cookie Mercure, renvoie `{hubUrl, topic}`   |
| GET     | `/api/games/platforms`                  | Plateformes disponibles (PC, PS5, Xbox…)            |
| GET     | `/api/games/search?q=`                  | Recherche dans le catalogue externe                 |
| GET     | `/api/games/{id}`                       | Fiche du jeu + membres du clan qui y jouent         |
| GET     | `/api/me/games`                         | Mes jeux et mes plateformes                         |
| POST    | `/api/me/games`                         | `{source, externalId, platforms}` → ajoute un jeu   |
| PUT     | `/api/me/games/{id}`                    | `{platforms}` → modifie mes plateformes             |
| DELETE  | `/api/me/games/{id}`                    | Retire un jeu                                       |
| GET     | `/api/clan/games`                       | Ludothèque du clan (jeux et joueurs)                |
| POST    | `/api/presence`                         | `{state: online\|away\|offline}` → signal de présence |
| GET     | `/api/clan/presence`                    | Présence des membres (app et Discord)               |
| GET     | `/api/discord`                          | État de l'intégration Discord                       |
| POST    | `/api/discord/authorize`                | `{purpose: link\|install}` → URL d'autorisation     |
| POST    | `/api/discord/callback`                 | `{code, state}` → lie le compte / connecte le serveur |
| DELETE  | `/api/discord/account`                  | Délie le compte Discord                             |
| DELETE  | `/api/discord/guild`                    | Déconnecte le serveur du clan (dirigeants)          |
| GET     | `/api/calls`                            | Visios en cours où je suis invité                   |
| POST    | `/api/calls`                            | `{userIds, title?}` → crée une visio Discord        |
| DELETE  | `/api/calls/{id}`                       | Termine une visio (créateur ou dirigeant)           |
| GET/PUT | `/api/me/profile`                       | Mon profil (nom, présentation, identifiants de jeu) |
| POST/DELETE | `/api/me/avatar`                    | Envoie (multipart `avatar`) / retire mon avatar     |
| PUT/DELETE | `/api/me/avatar/gravatar`            | `{email}` → associe / retire un Gravatar            |
| PUT     | `/api/me/avatar/source`                 | `{source: upload\|gravatar\|discord\|initials}`     |
| PUT     | `/api/me/password`                      | `{currentPassword, newPassword}`                    |
| GET     | `/api/members/{id}`                     | Fiche publique d'un membre du clan                  |
| GET     | `/api/avatars/{fichier}`                | Image d'avatar (publique, nom aléatoire)            |
| GET     | `/api/admin/settings`                   | Paramètres des intégrations (dirigeants)                 |
| PUT     | `/api/admin/settings/discord`, `/rawg`  | Enregistre les paramètres (dirigeants)                   |
| POST    | `/api/admin/discord/test`, `/rawg/test` | Teste la configuration (dirigeants)                      |
| PUT     | `/api/admin/settings/general`, `/mail`  | Nom du clan, adresse publique, service de mail      |
| GET     | `/api/clan/branches`                    | Branches du clan, avec les identifiants des membres |
| PUT/DELETE | `/api/clan/branches/{id}/membership` | Rejoindre (ou demander, 202) / quitter ou annuler   |
| GET     | `/api/admin/branch-requests`            | Demandes d'accès aux branches en attente            |
| POST    | `/api/admin/branch-requests/{id}/approve`, `/reject` | Valide / refuse une demande            |
| GET     | `/api/admin/branches`                   | Toutes les branches du catalogue, actives ou non    |
| PUT     | `/api/admin/branches/{id}/active`       | `{active}` → active / désactive une branche         |
| PUT     | `/api/admin/branches/{id}`              | `{name, description, publishesNews, approvalRequired}` |
| GET     | `/api/me/branch-links`                  | Liens favoris de mes branches, groupés par branche  |
| POST    | `/api/clan/branches/{id}/links`         | `{url, title?}` → ajoute un lien (membres)          |
| DELETE  | `/api/branch-links/{id}`                | Retire un lien (auteur ou dirigeant)                |
| GET     | `/api/members/{id}/card`                | Carte de membre : branches et compteurs             |
| GET     | `/api/news?before={id}`                 | News, 12 par page, des plus récentes aux anciennes  |
| GET     | `/api/news/{id}`                        | Une news, avec `canEdit` / `canDelete`              |
| POST    | `/api/news`                             | Multipart `title`, `body`, `image?` → publie        |
| POST    | `/api/news/{id}`                        | Multipart, `removeImage=1` → modifie                |
| DELETE  | `/api/news/{id}`                        | Supprime la news et son image                       |
| DELETE  | `/api/admin/settings/mail/server`       | Revient au serveur `MAILER_DSN` du fichier .env     |
| POST    | `/api/admin/mail/test`                  | Teste les réglages envoyés (enregistrés ou non)     |
| GET     | `/api/admin/signups`                    | Inscriptions en attente                             |
| POST    | `/api/admin/signups/{id}/approve`, `/reject` | Valide / refuse une inscription                |
| GET/POST | `/api/admin/members`                   | Membres (avec e-mail) / ajout direct d'un membre    |
| PUT     | `/api/admin/members/{id}/role`          | `{role: member\|leader}`                            |
| DELETE  | `/api/admin/members/{id}`               | Retire un membre du clan                            |
| GET     | `/api/setup`                            | Le clan existe-t-il déjà ? (page d'inscription)     |
| POST    | `/api/password/forgot`, `/reset`        | Mot de passe oublié / nouveau mot de passe          |

## Configuration notable

- `MERCURE_JWT_SECRET` (`backend/.env`) est partagé par Symfony et le hub : à changer en production.
- `MERCURE_BROWSER_URL` remplace volontairement `MERCURE_PUBLIC_URL`, que la CLI Symfony écrase
  avec l'URL Docker du hub.
- En HTTP (dev), le hub accepte le cookie `mercure_access_token` ; en production (HTTPS, debug
  désactivé), Symfony utilise `__Secure-mercure_access_token`, le nom par défaut du hub.
- Le JWT d'API est valable 7 jours (`config/packages/lexik_jwt_authentication.yaml`).

## Licence

[MIT](LICENSE) © 2026 Imperium Clan Software. Les icônes de [game-icons.net](https://game-icons.net) sont
sous licence CC BY 3.0. Conclave est un projet de fans indépendant, ni affilié à Ubisoft ni approuvé par
Ubisoft ; *Assassin's Creed* est une marque d'Ubisoft Entertainment.
