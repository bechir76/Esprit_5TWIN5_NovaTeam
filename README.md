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
| `/admin` | Tableau de bord |
| `/admin/utilisateurs` | Utilisateurs : recherche, filtres, création, modification, suspension, suppression (admin) |
| `/admin/roles` | Rôles et nombre d'utilisateurs par rôle (admin) |
| `/admin/profil` | Profil du personnel |

Les mots de passe choisis dans l'application doivent contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre.

## Développement

```bash
composer run dev        # serveur + queue + logs + vite
php artisan test        # tests (SQLite en mémoire)
vendor/bin/pint         # style de code
```

Voir `CLAUDE.md` pour les conventions (templates, Blade, routes, rôles).
