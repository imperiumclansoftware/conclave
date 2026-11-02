# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Conclave is a self-hosted private space for one gaming clan: instant messaging, presence and Discord video
calls, branches (Joueurs, Développeurs, Communicants, Cinéphiles) with their events, news, game library
(Steam/RAWG) and films (TMDB), plus a Windows desktop wrapper. The product is French: UI text, error
messages, docs (README, `windows/README.md`, `docker/DOCKERHUB.md`) and commit messages are written in
French; code, identifiers and code comments are in English. The root `README.md` documents every feature
and the full API route table: keep it up to date when behaviour or routes change.

## Commands

Backend (`backend/`, Symfony 8.1, PHP 8.4, PostgreSQL 16, Mercure):

```bash
docker compose up -d --wait                 # PostgreSQL :5432, Mercure hub :3000, Mailpit :1025/:8025
php bin/console lexik:jwt:generate-keypair  # once (keys are git-ignored)
php bin/console doctrine:migrations:migrate -n
php bin/console app:setup:code              # code asked by the installation wizard (/installation)
symfony server:start -d --no-tls --port=8000
php bin/console app:discord:gateway         # optional long-running Discord worker
php bin/console app:user:leader <username>  # rescue: make someone a leader

php bin/phpunit                                       # all tests (database app_test)
php bin/phpunit tests/Functional/SetupTest.php
php bin/phpunit --filter testRemovesWhatIsNotFormatting
php bin/console doctrine:database:create --env=test   # once, before the first test run
vendor/bin/phpstan analyse                            # level 6
vendor/bin/php-cs-fixer fix                           # @Symfony
```

Secrets (`APP_SECRET`, `JWT_PASSPHRASE`, `MERCURE_JWT_SECRET`) go in `.env.local` / `.env.dev.local`;
Docker Compose only reads `.env`, so export `MERCURE_JWT_SECRET` when starting the hub. When Docker runs on
another host (as here: host `docker`), override `DATABASE_URL`/`MERCURE_URL` in `.env.local` and
`.env.test.local`. Changing `APP_SECRET` makes the secrets saved in the administration unreadable. Schema
changes go through migrations (`make:migration`), never `doctrine:schema:update`.

Frontend (`frontend/`, Angular 22, Node 24+):

```bash
npm start                                   # http://localhost:4200, /api and /.well-known/mercure proxied
npm run start:code-server                   # same, served under /absproxy/4200/ behind code-server
npx ng build
npx ng test --watch=false                   # Vitest through @angular/build:unit-test
npx ng test --watch=false --include src/app/shared/icon.spec.ts
npm run format                              # Prettier, printWidth 100, single quotes (format:check in CI)
npm run icons                               # regenerate shared/icons.generated.ts from scripts/build-icons.mjs
```

The dev proxy (`proxy.conf.mjs`) targets `API_URL` (default `http://127.0.0.1:8000`) and `MERCURE_HUB_URL`
(default `http://localhost:3000`). Behind code-server, also set `MERCURE_BROWSER_URL` in `backend/.env.local`
(see README).

Windows app (`windows/`, .NET 10 SDK pinned in `global.json`, tests on Microsoft.Testing.Platform + xUnit v3):

```bash
dotnet test --project tests/Conclave.Desktop.Core.Tests
dotnet publish src/Conclave.Desktop -c Release -r win-x64 -p:Version=0.6.0 -o publish   # Windows only
iscc /DAppVersion=0.6.0 installer/Conclave.iss                                          # → dist/Conclave-Setup-0.6.0.exe
# compile check on Linux (no usable app):
dotnet build src/Conclave.Desktop -c Release -p:ManifestTool=/usr/local/bin/mt-linux-check -p:AppxGeneratePriEnabled=false
```

Docker image (the whole app in one image):

```bash
docker build --build-arg APP_VERSION=0.6.0 -t iemperor/conclave:0.6.0 .
```

## Architecture

### One image, one origin

In production, FrankenPHP (`docker/Caddyfile`) serves the Angular build from `/app/public`, sends `/api/*`
to Symfony, and runs the Mercure hub itself on the same address. In development the Angular proxy plays the
same role. Same origin is what makes real time work: the browser subscribes with an HttpOnly cookie
(`POST /api/mercure/authorize`, `MercureController`) scoped to its own topic, without CORS. The cookie name
is forced to `mercure_access_token` everywhere (hub config and `config/packages/mercure.yaml`) because the
default `__Secure-` name needs HTTPS and the image serves plain HTTP; `MERCURE_BROWSER_URL` replaces
`MERCURE_PUBLIC_URL` because the Symfony CLI overwrites the latter. The frontend calls the API with
**relative** URLs (`api/...`, no leading slash) so it works under any base href.

`docker/entrypoint.sh` checks required env vars, derives the Mercure/URL variables from `CONCLAVE_URL`, waits
for the database, and (web container only, not the worker) runs migrations, generates JWT keys and prints
the setup code. Uploads, keys, setup code and downloads live in `/data`, symlinked from `var/` and
`config/jwt` by the Dockerfile. The same image runs the Discord worker with a different command.

### Backend

- **Request flow**: thin controllers (`src/Controller`) bind input with `#[MapRequestPayload]` /
  `#[MapQueryParameter]` into DTOs (`src/Dto`), delegate to domain services grouped by feature (`src/Chat`,
  `src/Events`, `src/Clan`, `src/Catalog`, `src/Films`…), and build JSON by hand with `App\Api\ApiPresenter`.
  No serializer groups: the API shape is what `ApiPresenter` returns, mirrored in
  `frontend/src/app/core/api/models.ts`.
- **Errors** are thrown as HTTP exceptions (`BadRequestHttpException`, `AccessDeniedHttpException`,
  `#[IsGranted(message: …)]`…) with French messages; the frontend shows `detail` or the validation
  `violations`. Without debug (the Docker image), Symfony would replace `detail` with the status text
  ("Forbidden"): `App\Api\ApiErrorListener` sends the message in every environment, except Symfony's own
  technical messages (unknown route, entity not found, missing role). `tests/Functional/ApiErrorTest.php`
  checks this with a non-debug kernel.
- **Security** (`config/packages/security.yaml`): stateless JWT (LexikJWT, 7-day tokens), a few public paths
  (login, register, setup, password reset, health, avatars/news images, `/api/desktop/windows`),
  `/api/admin` requires `ROLE_LEADER`, everything else needs a full login. `UserChecker` blocks accounts
  still pending approval. Finer rules are voters (`src/Security`: news, games, films, conversations) backed
  by `GamesAccess` / `FilmsAccess` / `NewsPermissions`, which `ApiPresenter` also uses to expose `canEdit`…
- **Single clan, but clan-scoped data**: the schema has a `Clan` entity and queries filter by
  `$me->getClan()`; members only see and message members of their own clan (`User::belongsToSameClanAs()`).
  Branches come from a fixed catalog (`Clan/BranchCatalog.php`, `BranchCrest` enum on both sides); leaders
  activate them rather than create them. Event types and their fields are per branch (`App\Enum\EventType`).
- **Real time**: every change is persisted first, then `App\Chat\RealtimeNotifier` publishes a *private*
  Mercure update to the personal topic of each concerned user (`UserTopic`: `MERCURE_TOPIC_PREFIX/users/{id}`).
  Publishing failures are logged and swallowed; clients resynchronise on reconnection. New event kinds need
  a matching case in the `RealtimeEvent` union of `frontend/src/app/core/chat/realtime.service.ts`.
- **Settings and mail**: `App\Settings\AppSettings` reads values saved in the `setting` table, falling back to
  environment variables; secrets are encrypted with `SecretBox` (key derived from `APP_SECRET`) and never
  sent back to the browser. The mailer DSN is `settings://default`: `SettingsTransport` reads the real DSN
  at sending time. Mail failures are logged, never block the request; game-event e-mails are queued and
  sent on `kernel.terminate` (`GameEventInvitations`). `AppSettings::version()` lets the Discord worker
  reload when settings change.
- **Concurrency**: `symfony/lock` (`LOCK_DSN=flock`) guards installation (`app-setup`) and keeps a single
  Discord gateway worker; the setup code is rate-limited (`config/packages/rate_limiter.yaml`).
- **Rich text** (news, event descriptions): the Tiptap editor's HTML is sanitized server-side by
  `App\Text\RichText` + `config/packages/html_sanitizer.yaml`; length limits count text, not markup
  (`RichTextLength` constraint).
- `backend/AGENTS.md` (imported by `backend/CLAUDE.md`) holds the Symfony conventions to follow: attributes
  and autowiring rather than YAML, `composer require` + Flex recipes rather than hand-wiring bundles,
  `symfony/lock` for mutual exclusion, constructor promotion and `readonly` DTOs, `make:*` with
  `--no-interaction`.

### Backend tests

Functional tests in `tests/Functional` extend `ApiTestCase`, which drops and recreates the schema and clears
caches/rate limiters before **each** test, and offers `api()` (JSON in, decoded JSON out), `registerClan()`,
`signUp()`, `addMember()`, `joinBranch()`, `login()` and `linkDiscord()`. All outgoing HTTP (Mercure, Steam,
RAWG, TMDB, Discord, Gravatar) goes through `tests/Support/HttpClientMock` (static recorders and fixtures,
reset per test). PHPUnit fails on deprecations, notices and warnings. "Uncaught PHP Exception …" lines in the
output come from tests that check error responses, not from failures.

### Frontend

- Standalone components with signals (inline or separate templates); pages lazy-loaded from `app.routes.ts`
  with guards (`authGuard`, `leaderGuard`, `gamesGuard`, `filmsGuard`…); route params arrive as `input()`s
  (`withComponentInputBinding`).
- The authenticated `Shell` (`pages/shell`) provides the session-scoped services (`RealtimeService`,
  `ChatStore`, `PresenceService`, `CallsService`, `SignupsService`): they live only while logged in. The chat
  and branch links are side panels coordinated by `core/side-panel/side-panel.service.ts` (one open at a time).
- HTTP: services in `core/` wrap `HttpClient` (often with `firstValueFrom`); `authInterceptor` adds the JWT
  and logs out on a 401; `apiErrorMessage()` (`core/api/api-error.ts`) turns errors into French messages.
- Theme: Assassins (light) / Templiers (dark), driven only by `<meta name="color-scheme">` and CSS
  (`src/styles/_faction.scss`); `ThemeService` applies the choice (cached in `localStorage`), saved on the account through `ProfileService`. Icons are embedded SVG
  from game-icons.net: declare new ones in `scripts/build-icons.mjs`, run `npm run icons`, never edit
  `icons.generated.ts`. Third-party images (Gravatar, Discord, Steam/RAWG) load only after cookie consent
  (`ConsentService.src()`).
- Avenger feedback widget: configured by `AVENGER_URL`/`AVENGER_PRODUCT`, read via `GET /api/feedback`, script
  loaded on first click (`core/feedback/feedback.service.ts`).

### Windows app

C# / WinUI 3 without XAML (`Program.Main`), unpackaged and self-contained, wrapping the server's page in
WebView2. `Conclave.Desktop.Core` holds everything testable without Windows (server address, navigation
policy, bridge messages, settings, version comparison); `Conclave.Desktop` is the Windows shell (tray,
notifications, startup registration). Warnings are errors (`Directory.Build.props`).

- **Bridge contract** (keep both sides in sync): the app injects `window.conclaveDesktop`; the page
  (`frontend/src/app/core/desktop/desktop-bridge.service.ts`) posts `{type:"notification",…}` and
  `{type:"badge",count}`; the app answers `{type:"navigate",link}`. Only server paths are accepted
  (`NavigationPolicy`); other links open in the browser.
- **Versions**: the app asks `GET /api/desktop/windows` (public) for the version matching the server.
  `DesktopController` serves the newest installer dropped in `var/downloads` (`/data/downloads`), otherwise
  redirects to the GitHub release `v{APP_VERSION}`; a `dev` server never offers an update.
- `Conclave.pri` must be in the publish output (custom target in the `.csproj`), otherwise WinUI finds no
  theme and the app closes at launch; the workflow's *Launch check* step tests exactly that.

## Releases

A release adds a commit "Overview Docker Hub : version X.Y.Z" that updates the tag table in
`docker/DOCKERHUB.md`, then an annotated tag `vX.Y.Z` ("Conclave X.Y.Z"), and the image
`iemperor/conclave:X.Y.Z` also tagged `latest`, built with `--build-arg APP_VERSION=X.Y.Z`. Pushing the tag
runs `.github/workflows/windows.yml`, which tests, publishes and attaches `Conclave-Setup-X.Y.Z.exe` to the
GitHub release, the file `DesktopController` redirects to. The workflow also runs on manual dispatch and on
pushes to `main` touching `windows/` (build and check only). The Dockerfile refuses a base image with
PHP < 8.4.27: rebuild with `--pull` if it fails on that check.
