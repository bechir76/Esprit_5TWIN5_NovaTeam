# AquaSecure

Application de gestion des infrastructures d'eau potable (Laravel 12, PHP 8.2+).

- **AquaSecure Citoyens** (front) : carte du réseau, signalement d'incidents, projets, espace citoyen.
- **AquaSecure Gestion** (back office, `/admin`) : console du personnel (admin, gestionnaires, techniciens).

## Installation

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed     # base SQLite : database/database.sqlite
php artisan serve                    # http://127.0.0.1:8000
```

`MAIL_MAILER=log` : les e-mails (lien de réinitialisation du mot de passe) sont écrits dans `storage/logs/laravel.log`.

## Comptes de test

Créés par `UserSeeder`, mot de passe **`password`** pour tous :

| Rôle | E-mail | Accès |
|---|---|---|
| Administrateur | `admin@aquasecure.tn` | Back office complet, gestion des utilisateurs et des rôles |
| Gestionnaire réseau | `gestionnaire@aquasecure.tn` | Back office (sans utilisateurs ni rôles) |
| Technicien terrain | `technicien@aquasecure.tn` | Back office (sans utilisateurs ni rôles) |
| Citoyen | `citoyen@aquasecure.tn` | Espace citoyen uniquement |

Le seeder crée aussi 20 citoyens et 2 comptes suspendus (adresses aléatoires), qui ne peuvent pas se connecter.

## URL principales

| URL | Écran |
|---|---|
| `/connexion`, `/inscription`, `/mot-de-passe-oublie` | Authentification citoyen |
| `/profil` | Profil et mot de passe (connecté) |
| `/admin/login` | Connexion au back office (personnel uniquement) |
| `/carte` | Carte publique du réseau (infrastructures publiques, filtres type / état / zone) |
| `/admin` | Tableau de bord (KPI « Ouvrages à risque » réel) |
| `/admin/zones` | Zones de desserte : CRUD, recherche, infrastructures de la zone |
| `/admin/infrastructures` | Infrastructures : CRUD, recherche, filtres, pagination |
| `/admin/utilisateurs` | Utilisateurs : recherche, filtres, création, modification, suspension, suppression (admin) |
| `/admin/roles` | Rôles et nombre d'utilisateurs par rôle (admin) |
| `/admin/profil` | Profil du personnel |

Les mots de passe choisis dans l'application doivent contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre.

## Module Infrastructures

Module 1 « Réseau et infrastructures », dont dépendent les autres modules (incidents, projets, qualité de l'eau).

**Entités** (relation 1-n) :

- `Zone` (`zones`) : nom unique, commune, gouvernorat, population desservie, description. `hasMany` infrastructures.
- `Infrastructure` (`infrastructures`) : code unique `ABC-ABC-000` (type, gouvernorat, numéro : `RES-ARI-004`), nom, type, matériau, capacité + unité, année de mise en service, pression nominale, état, dernière inspection, latitude / longitude, description, visibilité publique. `belongsTo` zone (`zone_id` en `restrictOnDelete`).
- Enums : `InfrastructureType` (réservoir, conduite, pompage, traitement, forage), `InfrastructureStatus` (bon, à surveiller, critique), `CapacityUnit` (m³, L/s, km), avec `label()` en français.
- Scopes : `search`, `ofType`, `ofStatus`, `inZone`, `public`, `atRisk`.

**Back office** (personnel connecté) :

| URL | Écran |
|---|---|
| `/admin/zones` | Liste avec nombre d'infrastructures (`withCount`), recherche, pagination |
| `/admin/zones/creer`, `/admin/zones/{id}/modifier` | Formulaire (validation `StoreZoneRequest` / `UpdateZoneRequest`) |
| `/admin/zones/{id}` | Fiche de la zone, compteurs par état, infrastructures de la zone |
| `/admin/infrastructures` | Liste (jointure `with('zone')`), recherche nom / code, filtres type / état / zone, pagination |
| `/admin/infrastructures/creer`, `/admin/infrastructures/{id}/modifier` | Formulaire du template, sélection de la zone, placement sur la carte au clic |
| `/admin/infrastructures/{id}` | Fiche de l'infrastructure, zone et petite carte |

Une zone qui contient encore des infrastructures ne peut pas être supprimée (message d'erreur ; la clé étrangère l'interdit aussi).

**Front office** : `/carte` affiche les infrastructures publiques géolocalisées (repère coloré selon l'état, fiche au clic), avec les filtres type, état et zone ; l'accueil affiche le nombre réel d'infrastructures.

**Données de test** : `ZoneSeeder` crée les 6 zones du template (Tunis Centre, Ariana Nord, La Soukra, Ben Arous, Mégrine, La Manouba) et `InfrastructureSeeder` 5 à 8 ouvrages par zone, dont ceux du template (Réservoir de La Soukra `RES-ARI-004`, Forage de Mornag F3 `FOR-BAR-003`, Station de pompage Ariana Nord `PMP-ARI-003`…). `ZoneFactory` et `InfrastructureFactory` génèrent des données du Grand Tunis (états `critical()`, `toWatch()`, `good()`, `reservoir()`, `conduite()`, `pompage()`, `traitement()`, `forage()`, `hidden()`, `withoutCoordinates()`).

**Tests** : `tests/Feature/ZoneTest.php` et `tests/Feature/InfrastructureTest.php`.

## Développement

```bash
composer run dev        # serveur + queue + logs + vite
php artisan test        # tests (SQLite en mémoire)
vendor/bin/pint         # style de code
```

Voir `CLAUDE.md` pour les conventions (templates, Blade, routes, rôles).
