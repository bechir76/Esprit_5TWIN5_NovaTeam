# DevOps : intégration et déploiement continus

## Objectif

Chaque modification poussée sur `main` est testée automatiquement, empaquetée dans une image Docker publiée sur Docker Hub, puis déployée sur Render. Une pull request vers `main` lance seulement les tests, ce qui permet de vérifier une branche avant de la fusionner.

## Outils

| Outil | Rôle |
|---|---|
| **Docker Desktop** | Construire et lancer l'image en local (`docker compose`), dans les mêmes conditions qu'en production |
| **Docker Hub** | Registre qui stocke les images publiées (`<utilisateur>/aquasecure:latest` et une étiquette par commit) |
| **GitHub Actions** | Pipeline CI/CD (`.github/workflows/ci-cd.yml`) : tests, build et publication de l'image, déclenchement du déploiement |
| **Render** | Hébergement : un Web Service qui exécute l'image Docker Hub et l'expose en HTTPS |

## Processus

```mermaid
flowchart LR
    dev[Développeur] -->|git push| gh[GitHub]
    gh --> tests[CI : tests PHPUnit]
    tests -->|push sur main| build[Build de l'image Docker]
    build --> hub[Docker Hub]
    hub -->|deploy hook| render[Render]
    render --> users[Utilisateurs]
```

## Étapes du pipeline

1. **tests** (push et pull request vers `main`) : PHP 8.3 avec `pdo_sqlite`, `gd`, `zip`, `intl`, `bcmath` ; cache Composer et npm ; `composer install`, `npm ci`, `npm run build` ; copie de `.env.example` vers `.env`, `php artisan key:generate`, création de `database/database.sqlite` ; `php artisan test`.
2. **docker** (après les tests, uniquement sur un push vers `main`) : build de l'image avec Buildx (cache GitHub Actions `type=gha`), connexion à Docker Hub et publication de `aquasecure:latest` et `aquasecure:<sha du commit>`.
3. **deploy** (après la publication) : appel du deploy hook de Render, qui récupère la nouvelle image et redémarre le service.

### L'image Docker

Le `Dockerfile` est multi-étapes :

- **composer** : `composer install --no-dev --optimize-autoloader`. `fakerphp/faker` est dans `require`, car `DatabaseSeeder` utilise des factories et le seed est lancé au démarrage du conteneur.
- **node:20** : `npm ci` puis `npm run build` (assets Vite dans `public/build`). Le paquet Livewire de `vendor/` est copié depuis l'étape précédente, car `resources/js/app.js` l'importe.
- **php:8.3-apache** : extensions `pdo_sqlite`, `gd`, `zip`, `intl`, `bcmath`, `mod_rewrite`, DocumentRoot sur `public/`, Apache à l'écoute de `${PORT:-8080}` (Render fournit `PORT`).

Au démarrage, `docker/entrypoint.sh` crée `database/database.sqlite` s'il n'existe pas, lance `php artisan migrate --force --seed` si la base est vide (sinon `migrate --force` seul), met en cache la configuration, les routes et les vues, crée le lien `public/storage`, puis démarre Apache.

`bootstrap/app.php` fait confiance aux proxies (`trustProxies(at: '*')`) : Render termine le HTTPS, et sans cela les URL des assets seraient générées en `http` (page sans style).

## Lancer en local (Docker Desktop)

Avec une `APP_KEY` dans le fichier `.env` (sinon `php artisan key:generate`) :

```bash
docker compose up --build
```

Puis ouvrir http://localhost:8000 (site citoyen) ou http://localhost:8000/admin/login (comptes de test du README). Sans Compose :

```bash
docker build -t aquasecure .
docker run --rm -p 8000:8080 -e APP_KEY=base64:... aquasecure
```

## Secrets et variables d'environnement

**Secrets GitHub** (Settings → Secrets and variables → Actions) :

- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`
- `RENDER_DEPLOY_HOOK_URL`

**Variables d'environnement Render** :

- `APP_KEY` (obligatoire)
- `APP_URL`
- `APP_ENV`, `APP_DEBUG` (valeurs par défaut de l'image : `production`, `false`)
- `DB_CONNECTION`, `QUEUE_CONNECTION` (par défaut : `sqlite`, `sync`)
- facultatives : `ANTHROPIC_API_KEY` ou `GEMINI_API_KEY`, `AI_PROVIDER`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `MAIL_MAILER` et les variables `MAIL_*`

`PORT` est fourni par Render.

## Limites

- **Base SQLite éphémère** : sur le plan gratuit de Render, le disque du conteneur n'est pas persistant. La base est recréée et réensemencée à chaque déploiement ou redémarrage ; les données saisies sont perdues. Une base persistante demanderait un disque Render (payant) ou une base PostgreSQL / MySQL externe.
- **Mise en veille** : le service gratuit s'endort après une période d'inactivité ; la première requête suivante prend plusieurs dizaines de secondes (réveil, migrations et seed).
- **Pas de worker de queue** : l'image utilise `QUEUE_CONNECTION=sync`. Les notifications et les jobs (analyse IA des incidents, des mesures…) s'exécutent pendant la requête, ce qui peut la ralentir. Les tâches planifiées (`routes/console.php`) ne tournent pas, faute de `schedule:run`.
