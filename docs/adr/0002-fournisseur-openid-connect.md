# ADR 0002 — Conclave fournisseur OpenID Connect (connexion au portail Imperium Clan)

- **Statut** : accepté
- **Date** : 2026-11-17
- **Contexte lié** : ADR 0001 (clients OAuth2) ; ADR 0003 du portail `imperiumclansoftware/imperium-clan` (SSO)

## Contexte

Les membres doivent se connecter au portail Imperium Clan **avec leur compte Conclave**. Le portail en
déduit leurs droits (espace membre ; publication des actualités pour les membres de la branche
Communicants). Conclave n'avait que des sessions JWT pour sa propre application et des clients machine
(ADR 0001).

## Décision

Implémentation minimale d'un fournisseur OpenID Connect, sur les clients de l'ADR 0001 (`App\OAuth`) :

- **Flux** : *authorization code* uniquement, **PKCE S256 obligatoire**, `state` obligatoire, `nonce`
  repris dans le jeton d'identité. Pas de flux implicite ni hybride.
- **Clients** : portée `openid` (et `profile`) et **adresses de retour enregistrées**, comparées à
  l'identique (`app:oauth:client:create|update --redirect-uri`). Un client sans adresse de retour ne peut
  pas connecter de membre. Une adresse inconnue n'est **jamais** suivie : l'erreur s'affiche dans Conclave.
- **Consentement** : `GET /oauth/authorize` vérifie la demande et la confie, signée (HMAC, `APP_SECRET`,
  10 min), à la page `/autoriser` de l'application. Le membre s'y connecte au besoin, voit ce qui sera
  transmis et choisit *Continuer* ou *Refuser*. Aucun état serveur avant le consentement.
- **Code** : 256 bits, valable 60 s, usage unique, stocké en SHA-256, lié au client, au membre, à l'adresse
  de retour et au défi PKCE. Un code rejoué révoque les jetons déjà émis à partir de lui.
- **Jeton d'identité** : JWT **RS256**, clé RSA 2048 dédiée (distincte des clés de session LexikJWT),
  générée au premier usage dans `var/oidc` (`/data/oidc`), publiée sur `/.well-known/jwks.json` avec un
  `kid`. Validité 5 min. Signature faite avec OpenSSL, sans dépendance supplémentaire.
- **Revendications** : `iss` (adresse publique, `APP_PUBLIC_URL`), `sub` = **`User::publicSub`** (le même que
  l'export des membres, ADR 0001), `aud` = `client_id`, `iat`, `exp`, `nonce` ; avec `profile` : `name` (nom
  affiché) et `branches` (blasons des branches actives). Jamais l'e-mail, l'identifiant de connexion, le
  compte Discord ni le rôle interne.
- **Points d'entrée** : `/.well-known/openid-configuration`, `/.well-known/jwks.json`, `/oauth/authorize`,
  `/oauth/token` (`grant_type=authorization_code`, client en HTTP Basic), `/oauth/userinfo`.

## Pourquoi pas une bibliothèque

`league/oauth2-server` ne gère pas OpenID Connect sans extension tierce ; un serveur d'identité complet
(Keycloak, Authentik…) serait un service de plus à exploiter pour un seul client. Le périmètre utile (un
flux, un algorithme, des clients enregistrés à la main) reste petit et entièrement testé
(`tests/Functional/OidcTest.php`).

## Conséquences

- La connexion est demandée à chaque fois (un clic sur *Continuer*) ; mémoriser le consentement pourra
  venir ensuite.
- Se déconnecter du portail ne déconnecte pas de Conclave (pas de déconnexion fédérée).
- Remplacer la clé (`/data/oidc/prod/private.pem`) invalide les jetons d'identité en cours de validité
  (5 min au plus) ; le portail relit la JWKS à chaque connexion.
