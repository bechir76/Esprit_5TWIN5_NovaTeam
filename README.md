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
| `/admin/analyses` | Qualité de l'eau : analyses, KPIs, filtres statut / zone / période |
| `/admin/mesures` | Mesures non conformes : liste globale, filtres paramètre / zone |
| `/incidents`, `/incidents/signaler` | Suivi public des incidents ; signalement (connecté) |
| `/admin/incidents` | Incidents : CRUD, filtres, changement de statut |
| `/admin/interventions` | Interventions : CRUD, filtres statut / date |
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

## Module Qualité de l'eau

Module 4 « Qualité de l'eau » : analyses physico-chimiques et bactériologiques réalisées sur les infrastructures du réseau.

**Entités** (relation 1-n) :

- `WaterAnalysis` (`water_analyses`) : référence auto-générée (`ANA-2026-0001`), infrastructure concernée, date/heure et point de prélèvement, laboratoire, technicien préleveur (nullable → `users`), statut (`en_attente`, `validee`, `non_conforme`), notes. Statut recalculé automatiquement à chaque création/modification/suppression de mesure.
- `Measurement` (`measurements`) : paramètre (enum `WaterParameter`), valeur décimale, unité (remplie automatiquement depuis l'enum), conformité (`is_compliant`, calculée automatiquement). Contrainte unique `(water_analysis_id, parameter)`.
- Enums : `WaterParameter` (chlore, ph, turbidite, nitrate, conductivite, ecoli) avec `label()`, `unit()`, `min()`, `max()`, `isCompliant(float)`, `thresholdHint()` ; `AnalysisStatus` avec `label()`, `badge()`.
- Accesseur `compliance_rate` (pourcentage de mesures conformes).
- Scopes : `search`, `ofStatus`, `nonCompliant`, `inZone` (via infrastructure).

**Back office** (personnel connecté) :

| URL | Écran |
|---|---|
| `/admin/analyses` | Liste avec KPIs réels (analyses du mois, taux de conformité global, non conformes, dernière analyse), filtres statut / zone / infrastructure / période, pagination |
| `/admin/analyses/creer`, `/admin/analyses/{id}/modifier` | Formulaire avec tableau de mesures intégré (tous les paramètres, valeurs facultatives), enregistré en transaction DB |
| `/admin/analyses/{id}` | Fiche avec tableau des mesures, barre de conformité, badges Conforme / Non conforme, accès rapide aux actions mesure |
| `/admin/mesures` | Index des mesures **non conformes uniquement**, filtres paramètre / zone |
| `/admin/analyses/{id}/mesures/creer` | Ajout d'une mesure individuelle (paramètres déjà saisis exclus, aide seuil en JS) |
| `/admin/mesures/{id}/modifier` | Modification d'une mesure (recalcul immédiat du statut de l'analyse parente) |

**Front office** :

| URL | Écran |
|---|---|
| `/qualite-eau` | Page publique par zone : dernière analyse validée, 4 paramètres principaux (chlore, pH, turbidité, nitrate), badges conforme / non conforme, filtre par zone |
| `/` | Bloc « Qualité mesurée aujourd'hui » dans le hero : données réelles de la dernière analyse validée |

**Tableau de bord admin** : bandeau d'alerte rouge dynamique affichant la dernière analyse non conforme (paramètre, valeur, seuil, infrastructure, date) avec lien direct. Disparaît s'il n'y en a aucune.

**Validation** : `infrastructure_id` existant, `sampled_at` ≤ maintenant, `sampling_point` 3–120 car., `laboratory` obligatoire, `sampled_by` doit être du personnel, `measurements.*.value` ≥ 0, `ph` ≤ 14, au moins une mesure renseignée.

**Données de test** : `WaterAnalysisSeeder` crée 4 analyses fixes (dont `FOR-BAR-003` nitrate à 62 mg/L non conforme) puis 3 à 5 analyses par infrastructure de type réservoir / traitement / forage, réparties sur 6 mois, prélevées par les techniciens existants.

**Tests** : `tests/Feature/WaterAnalysisTest.php` (23 tests) et `tests/Feature/MeasurementTest.php` (18 tests).

## Module Incidents

Module 2 « Incidents et interventions » : signalements des citoyens et interventions des équipes terrain.

**Entités** (relation 1-n) :

- `Incident` (`incidents`) : référence auto-générée (`INC-2026-0001`), titre, description, type, gravité, statut, adresse, latitude / longitude, photo (`photo_path`), infrastructure liée (nullable, `nullOnDelete`), déclarant `user_id` (nullable, `nullOnDelete`), date de résolution `resolved_at`. `hasMany` interventions.
- `Intervention` (`interventions`) : incident (`cascadeOnDelete`), intitulé, équipe (liste `Intervention::TEAMS`), technicien `technician_id` (nullable → `users`, `nullOnDelete`), date prévue, durée estimée (heures, décimal), statut, compte rendu, date de fin `completed_at`.
- Relations ajoutées : `Infrastructure::incidents()`, `User::reportedIncidents()`, `User::interventions()` (technicien).
- Enums avec `label()`, `badge()` et `options()` : `IncidentType` (fuite, contamination, coupure), `IncidentSeverity` (faible, moyenne, elevee, critique), `IncidentStatus` (signale, qualifie, en_intervention, resolu, rejete), `InterventionStatus` (planifiee, en_cours, terminee, annulee).
- Scopes : `Incident::search`, `ofType`, `ofSeverity`, `ofStatus`, `open`, `inZone` (via l'infrastructure) ; `Intervention::search`, `ofStatus`, `scheduledOn`, `active`.

**Règles de statut** (hooks des modèles, enregistrements en transaction) :

- `resolved_at` est renseigné au passage à « Résolu » et effacé si l'incident est rouvert ; `completed_at` suit de même le statut « Terminée » (date saisie ou heure actuelle).
- Une intervention « En cours » fait passer son incident « Signalé » ou « Qualifié » à « En intervention ».
- Un incident ne peut pas être résolu ni rejeté tant qu'une intervention est planifiée ou en cours, ni revenir à « Signalé » / « Qualifié » pendant une intervention en cours. Une intervention planifiée ou en cours ne peut pas être rattachée à un incident clôturé.

**Front office** :

| URL | Écran |
|---|---|
| `/incidents` | Liste publique paginée : onglets par statut, recherche (référence, titre, description, adresse), filtres type / zone. Les incidents rejetés et l'identité des déclarants ne sont pas publiés |
| `/incidents/signaler` | Formulaire de signalement (utilisateur connecté), photo facultative, « Utiliser ma position » |
| `/profil` | « Mes signalements » : incidents de l'utilisateur connecté |
| `/` | « Derniers signalements » et nombre d'incidents résolus dans l'année |

**Back office** (personnel connecté) :

| URL | Écran |
|---|---|
| `/admin/incidents` | Liste (`with(['infrastructure.zone', 'user'])`), recherche, filtres type / gravité / statut / zone / date, pagination |
| `/admin/incidents/creer`, `/admin/incidents/{id}/modifier` | Formulaire (infrastructure « CODE · Nom », carte cliquable, photo à ajouter / remplacer / retirer) |
| `/admin/incidents/{id}` | Fiche : détails, photo, interventions, historique, carte ; changement de statut (`PATCH /admin/incidents/{id}/statut`, route `admin.incidents.status`) |
| `/admin/interventions` | Liste (`with(['incident.infrastructure', 'technician'])`), recherche, filtres statut / date, pagination |
| `/admin/interventions/creer` | Planification depuis la liste (incidents ouverts) ou depuis la fiche d'un incident (`?incident=ID`, retour sur la fiche) |
| `/admin/interventions/{id}`, `/admin/interventions/{id}/modifier` | Fiche et modification |

Le tableau de bord (incidents ouverts, incidents critiques ouverts, signalés / résolus par mois, répartition des incidents ouverts par type, derniers incidents), le compteur du menu « Incidents » et la fiche d'une infrastructure (« Incidents liés ») utilisent les données réelles.

**Validation** (messages en français) :

- Signalement / incident : titre 10–120 caractères, description 30 caractères minimum, type et gravité parmi les enums, adresse obligatoire, infrastructure existante (publique côté citoyen), latitude / longitude valides et renseignées ensemble, photo JPG ou PNG de 5 Mo maximum.
- Intervention : incident existant, intitulé, équipe de la liste, date prévue valide, durée de 0,5 à 72 h, technicien ayant le rôle `technicien`, statut valide, compte rendu obligatoire si « Terminée ».

**Photos** : enregistrées avec `Storage` sur le disque `public`, dans `storage/app/public/incidents`. Lancer `php artisan storage:link` pour les afficher. La photo est supprimée quand elle est remplacée, retirée ou quand l'incident est supprimé.

**Données de test** : `IncidentSeeder` crée 40 incidents : les 7 du template (`INC-2026-0918` fuite rue Ibn Sina, `INC-2026-0915` eau trouble au forage de Mornag F3…) et 33 incidents générés sur 6 mois, répartis sur les infrastructures et les citoyens existants (au moins 4 pour `citoyen@aquasecure.tn`). `InterventionSeeder` crée 1 à 3 interventions cohérentes avec le statut de chaque incident non « signalé », assignées aux techniciens actifs. `IncidentFactory` (états `reported()`, `qualified()`, `inProgress()`, `resolved()`, `rejected()`, `critical()`, `fuite()`, `contamination()`, `coupure()`, `withoutInfrastructure()`, `withoutCoordinates()`) et `InterventionFactory` (`planned()`, `inProgress()`, `done()`, `cancelled()`, `withoutTechnician()`) réutilisent les enregistrements existants avec `recycle()`.

**Tests** : `tests/Feature/IncidentTest.php` (20 tests) et `tests/Feature/InterventionTest.php` (14 tests).

## Développement

```bash
composer run dev        # serveur + queue + logs + vite
php artisan test        # tests (SQLite en mémoire)
vendor/bin/pint         # style de code
```

Voir `CLAUDE.md` pour les conventions (templates, Blade, routes, rôles).
