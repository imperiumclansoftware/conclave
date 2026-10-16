# Conclave

**Le quartier général privé de votre clan de joueurs.**

Conclave réunit les membres d'un clan dans un espace privé, auto-hébergé, en français :

- **Page d'accueil** : dernières news en carrousel, événements de la semaine, membres en ligne
- **Événements** propres à chaque branche (sessions de jeu, tournois, watch parties, ciné-clubs,
  réunions, game jams, streams), avec **planning** mensuel et **salon de discussion** par événement
- **Notifications** par e-mail et dans le navigateur quand un événement est organisé sur un de vos jeux
- **Messagerie instantanée** entre membres, messages conservés pour ceux qui sont hors ligne
- **Présences et visios Discord** : qui est en ligne, à quoi il joue, salons vocaux créés à la demande
- **Ludothèque** : les jeux de chacun et leurs plateformes, avec fiches et captures (Steam, RAWG)
- **Films et séries** pour les Cinéphiles, avec fiches TMDB
- **Branches** à blasons (Joueurs, Développeurs, Communicants, Cinéphiles), avec liens favoris partagés
- **News** du clan, publiées par la branche des Communicants, avec image ou visuel généré
- **Administration** : validation des inscriptions, rôles, e-mails, Discord, branches
- Deux thèmes : la **Confrérie** (clair, parchemin) et l'**Ordre** (sombre, pierre et or)

L'image contient toute l'application : l'interface Angular, l'API Symfony et le hub temps réel Mercure,
servis par [FrankenPHP](https://frankenphp.dev) sur le port 80. Il lui faut seulement une base PostgreSQL.

- Code source : https://github.com/imperiumclansoftware/conclave
- Licence : MIT

## Tags

| Tag | Contenu |
|---|---|
| `latest` | Dernière version publiée |
| `0.2.0` | Événements des branches, planning, discussion des événements, notifications, page d'accueil, films et séries (TMDB) |
| `0.1.2` | Démarrage plus lisible (raison d'un échec de connexion à la base) et plus tolérant |
| `0.1.1` | Première image publiée |

Plateforme : `linux/amd64`.

## Installation

### 1. Le fichier `compose.yaml`

Dans un dossier vide (par exemple `conclave/`), créez ce fichier :

```yaml
# Réglages des deux conteneurs Conclave, pris dans les variables de la stack (.env ou Portainer)
x-conclave-environment: &conclave-environment
  CONCLAVE_URL: ${CONCLAVE_URL:?CONCLAVE_URL à renseigner, par ex. http://192.168.1.10:8080}
  APP_SECRET: ${APP_SECRET:?APP_SECRET à renseigner (openssl rand -hex 32)}
  JWT_PASSPHRASE: ${JWT_PASSPHRASE:?JWT_PASSPHRASE à renseigner (openssl rand -hex 32)}
  MERCURE_JWT_SECRET: ${MERCURE_JWT_SECRET:?MERCURE_JWT_SECRET à renseigner (openssl rand -hex 32)}
  DATABASE_URL: postgresql://conclave:${POSTGRES_PASSWORD}@database:5432/conclave?serverVersion=16&charset=utf8
  APP_TIMEZONE: ${APP_TIMEZONE:-Europe/Paris}

services:
  conclave:
    image: iemperor/conclave:${CONCLAVE_VERSION:-latest}
    restart: unless-stopped
    ports:
      - "${CONCLAVE_PORT:-8080}:80"
    environment: *conclave-environment
    volumes:
      - conclave_data:/data
    depends_on:
      database:
        condition: service_healthy

  # Présences et visios Discord (facultatif : Discord se configure dans l'administration)
  discord-worker:
    image: iemperor/conclave:${CONCLAVE_VERSION:-latest}
    restart: unless-stopped
    command: ["php", "bin/console", "app:discord:gateway"]
    environment:
      <<: *conclave-environment
      # Publie les événements temps réel via le hub du conteneur web
      MERCURE_URL: http://conclave/.well-known/mercure
    healthcheck:
      disable: true
    volumes:
      - conclave_data:/data
    depends_on:
      conclave:
        condition: service_healthy

  database:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: conclave
      POSTGRES_USER: conclave
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?POSTGRES_PASSWORD à renseigner}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "conclave", "-U", "conclave"]
      interval: 5s
      timeout: 5s
      retries: 10
    volumes:
      - database_data:/var/lib/postgresql/data

volumes:
  conclave_data:
  database_data:
```

### 2. Les variables

En ligne de commande, créez à côté un fichier `.env` ; avec **Portainer**, collez le fichier ci-dessus dans
l'éditeur web de la stack et saisissez ces variables dans « Environment variables » (bouton « Advanced mode »
pour les coller d'un bloc). Générez chaque secret avec `openssl rand -hex 32` :

```dotenv
# Adresse à laquelle les membres joignent Conclave (schéma, hôte et port, sans / final)
CONCLAVE_URL=http://192.168.1.10:8080
# Port publié sur la machine (doit correspondre à CONCLAVE_URL)
CONCLAVE_PORT=8080
# Tag de l'image : latest, ou une version comme 0.2.0
CONCLAVE_VERSION=latest

# Secrets : à conserver précieusement
APP_SECRET=
JWT_PASSPHRASE=
MERCURE_JWT_SECRET=
POSTGRES_PASSWORD=
```

### 3. Démarrage

```bash
docker compose up -d
docker compose exec conclave php bin/console app:setup:code
```

La seconde commande affiche le **code d'installation** (avec Portainer : journaux du conteneur `conclave`,
ou sa console avec la même commande). Ouvrez `CONCLAVE_URL` dans un navigateur :
l'assistant d'installation vous le demande, puis crée le clan et son premier dirigeant. Le code évite qu'un
inconnu qui atteindrait le site avant vous ne s'en empare ; il figure aussi dans `docker compose logs conclave`.

Ensuite, depuis l'administration : configurez le service d'e-mail, activez les branches, connectez Discord.
Les membres demandent à rejoindre le clan via « S'inscrire » et un dirigeant valide leur compte.

## Variables d'environnement

| Variable | Obligatoire | Rôle |
|---|---|---|
| `CONCLAVE_URL` | oui | Adresse publique de Conclave, par ex. `http://192.168.1.10:8080` ou `https://clan.example.com` (hub temps réel, liens des e-mails) |
| `APP_SECRET` | oui | Clé de l'application. Elle chiffre aussi les secrets saisis dans l'administration : la changer les rend illisibles |
| `JWT_PASSPHRASE` | oui | Protège les clés de connexion, générées au premier démarrage |
| `MERCURE_JWT_SECRET` | oui | Signe les jetons du hub temps réel |
| `DATABASE_URL` | oui | Connexion PostgreSQL (fournie par le `compose.yaml` ci-dessus) |
| `APP_TIMEZONE` | non | Fuseau horaire des dates écrites dans les e-mails (par défaut `Europe/Paris`) |
| `MERCURE_URL` | non | Hub auquel le serveur publie ; par défaut celui du conteneur. Le worker Discord utilise `http://conclave/.well-known/mercure` |

Les réglages fonctionnels (e-mail SMTP, Discord, clés RAWG et TMDB) se font dans l'administration et sont stockés
chiffrés en base.

## Données et sauvegarde

- Volume **`/data`** : avatars, images des news, clés de connexion, historique du hub temps réel.
- Volume de la base PostgreSQL.
- Le fichier **`.env`**, surtout `APP_SECRET`.

Sauvegardez les trois. Pour la base : `docker compose exec database pg_dump -U conclave conclave > conclave.sql`.

## HTTPS

L'image sert du HTTP. Pour un accès depuis Internet, placez-la derrière un reverse proxy qui gère le
certificat (Traefik, Caddy, Nginx Proxy Manager…), faites-le pointer vers le port publié, et indiquez
l'adresse `https://…` dans `CONCLAVE_URL`.
Les notifications du navigateur n'existent qu'en HTTPS : en `http://`, les membres sont prévenus
par e-mail seulement.

## Mise à jour

```bash
docker compose pull
docker compose up -d
```

Les migrations de la base s'appliquent au démarrage. Pour rester sur une version précise, indiquez-la dans
`CONCLAVE_VERSION`.

## Détails techniques

- Base `dunglas/frankenphp` (PHP 8.4, Alpine), utilisateur non root, port 80.
- Au démarrage : attente de la base, migrations, génération des clés si besoin, affichage du code
  d'installation tant que l'application n'est pas installée. Une variable obligatoire manquante arrête
  le conteneur avec un message explicite, et un échec de connexion à la base affiche sa raison.
- Contrôle de santé : `GET /api/health`, avec 3 minutes de délai au premier démarrage.

## Dépannage

- **`container conclave-conclave-1 is unhealthy`** : consultez les journaux du conteneur `conclave`. Si
  la base refuse le mot de passe, `POSTGRES_PASSWORD` a changé depuis la création du volume de la base :
  PostgreSQL garde le premier. Remettez l'ancienne valeur, ou supprimez le volume de la base (ses données
  avec) et redéployez.
- **Messagerie sans temps réel** : `CONCLAVE_URL` doit être exactement l'adresse tapée dans le navigateur,
  port compris.
- Contrôle de santé : `GET /api/health`.
- Le worker Discord s'arrête proprement (`docker compose stop`). N'en lancez qu'un seul : chaque événement
  Discord serait sinon traité en double.

---

Conclave est un projet de fans indépendant, ni affilié à Ubisoft ni approuvé par Ubisoft.
*Assassin's Creed* est une marque d'Ubisoft Entertainment. Icônes : [game-icons.net](https://game-icons.net) (CC BY 3.0).
