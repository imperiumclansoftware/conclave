# ADR 0001 — Clients OAuth2 « machine » pour le portail Imperium Clan

- **Statut** : accepté
- **Date** : 2026-11-17
- **Contexte lié** : ADR 0004 (synchronisation des membres) et ADR 0003 (SSO OIDC, à venir) du portail
  `imperiumclansoftware/imperium-clan`

## Contexte

Le portail public du clan (Imperium Clan) affiche les membres en les synchronisant depuis Conclave toutes
les 15 minutes. Son code est déjà déployé et impose un contrat précis :

1. `POST /oauth/token` en `client_credentials`, identifiants en HTTP Basic, portée `portal:members`,
   réponse `{"access_token", "token_type", "expires_in"}` ;
2. `GET /api/portal/members` avec `Authorization: Bearer <jeton>`, réponse `{"items": [...]}` complète,
   sans pagination ;
3. avatars téléchargés sans authentification, uniquement depuis l'hôte de `CONCLAVE_URL`.

Conclave n'avait jusqu'ici que des sessions JWT de membres (LexikJWT) : aucun moyen d'authentifier un
autre serveur.

## Options étudiées

1. **`league/oauth2-server-bundle`** : implémentation OAuth2 complète (tous les flux, clés RSA, cinq
   tables, scopes en base, jetons JWT signés).
2. **Implémentation minimale** du seul flux `client_credentials`, avec des jetons opaques.
3. **Réutiliser LexikJWT** en émettant des JWT pour un faux « utilisateur portail ».

## Décision

Option 2 : implémentation minimale, dans `App\OAuth`.

- **Clients** (`oauth_client`) : `client_id` aléatoire (`cc_…`, 128 bits), secret de 256 bits affiché une
  seule fois par `app:oauth:client:create`, stocké **haché en Argon2id** (hacheur `oauth_client_secret`,
  `security.yaml`). Libellé, portées autorisées, date de révocation, date de dernière utilisation.
- **Vérification en temps constant** : `password_verify` (via le PasswordHasher de Symfony) ; un
  `client_id` inconnu déclenche quand même une vérification sur une empreinte factice, pour que le temps
  de réponse ne révèle pas lequel des deux est faux.
- **Jetons opaques** de 256 bits, valables **300 s**, stockés en **SHA-256** (`oauth_access_token`) :
  une fuite de la base ne donne aucun jeton utilisable. Les jetons expirés depuis plus d'un jour sont
  purgés à chaque émission. Révoquer un client supprime ses jetons : l'effet est immédiat.
- **`POST /oauth/token`** : public, sans session ni CSRF (hors du pare-feu JWT), limité à 10 demandes par
  minute et par adresse, identifiants **en HTTP Basic uniquement**, erreurs conformes à RFC 6749 §5.2
  (`invalid_client` 401, `invalid_request`, `unsupported_grant_type`, `invalid_scope` 400),
  `Cache-Control: no-store`.
- **`GET /api/portal/members`** : pare-feu dédié `portal` (`BearerTokenAuthenticator`), la portée devient
  le rôle `ROLE_OAUTH_PORTAL_MEMBERS` exigé par `access_control` ; erreurs RFC 6750 (`invalid_token` 401,
  `insufficient_scope` 403). Un JWT de membre n'y donne pas accès.

## Pourquoi pas les autres options

- **league/oauth2-server-bundle** : un seul flux est utile ici. Le bundle apporterait des clés RSA à
  gérer en plus de celles de LexikJWT, cinq tables, et la surface des autres flux, pour un seul client
  appelé toutes les 15 minutes. Il ne fournit pas OpenID Connect : il ne règle donc pas non plus l'ADR 0003.
- **LexikJWT** : mélanger les jetons de membres et de machines dans le même pare-feu rendrait les règles
  d'accès fragiles (un JWT de membre ne doit jamais lire l'export, et réciproquement). Un JWT ne se révoque
  pas non plus avant son expiration.

## Données exportées

- Uniquement les comptes **validés** qui ont coché « Apparaître sur le site public du clan » (choix
  explicite, désactivé par défaut, `PUT /api/me/public-profile`).
- `sub` : identifiant public aléatoire (`User::publicSub`, 128 bits en base64url), généré une fois puis
  immuable, attribué aux membres existants par la migration. Ce n'est ni l'id SQL, ni l'id Discord, ni
  l'identifiant de connexion. **Ce sera le `sub` du futur SSO OpenID Connect (ADR 0003 du portail).**
- `pseudo` (nom affiché), `bio` (présentation), `branches` (blasons des branches actives), `avatarUrl`.
  `title` vaut `null` : Conclave n'a pas de titre public, et le rôle interne n'est pas exporté.
- Jamais : e-mail, identifiant de connexion, id ou pseudo Discord, rôle, présence, dernière connexion.

## Avatars

Le portail n'accepte que des URL du même schéma, hôte et port que `CONCLAVE_URL`, et les télécharge sans
jeton toutes les 15 minutes.

- Avatar envoyé : son URL publique existante `/api/avatars/{nom aléatoire}.webp`.
- Gravatar ou Discord : `/api/portal/avatars/{sub}/{signature}.webp`, où la signature est un HMAC-SHA256
  (clé `APP_SECRET`) du `sub` et de l'URL d'origine. L'URL n'est pas devinable et change quand l'image
  change ; elle répond 404 dès que le membre retire son consentement. Conclave télécharge l'image une fois
  par jour au plus, la convertit en WebP 256 × 256 (quelques Ko) et la sert lui-même, sans redirection
  et avec `Content-Length`.
- L'URL absolue est construite sur l'adresse publique de Conclave (`APP_PUBLIC_URL`, déduite de
  `CONCLAVE_URL` dans l'image Docker). `CONCLAVE_URL` côté portail doit donc être la même adresse
  publique HTTPS.

## Conséquences

- Changer `APP_SECRET` change les URL signées des avatars : le portail les retélécharge à la
  synchronisation suivante, sans autre effet.
- Revenir en arrière sur la migration (`down`) supprime les `sub` : les réappliquer en génère de nouveaux,
  et le portail verrait alors de « nouveaux » membres.
- Un futur fournisseur OpenID Connect pourra réutiliser `oauth_client` (ou s'y substituer). Il devra
  reprendre `publicSub` comme `sub`.
- La gestion des clients se fait en ligne de commande. Une page d'administration réservée aux dirigeants
  pourra être ajoutée plus tard sur le même service `ClientCredentials`.
