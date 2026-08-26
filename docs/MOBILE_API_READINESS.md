# OPERIX HSE MOBILE — API READINESS REPORT

**Phase 0 — Audit backend. Aucun code Flutter écrit.**

| | |
|---|---|
| Date | 2026-08-26 |
| Backend audité | `operix-api` @ `master`, tag `v0.3-saas` |
| Suite de tests | 78 tests / 252 assertions — PASS (PostgreSQL `operix_test`) |
| Périmètre | `routes/api.php` (396 l.), 30 modèles, 10 policies, 5 middlewares, 18 resources, 26 fichiers de test |
| Verdict | **Backend NON prêt en l'état pour le MVP mobile terrain.** 3 bloquants critiques, 6 majeurs/moyens. Fondations multi-tenant et fichiers : excellentes. |

---

## 1. Synthèse exécutive

Le backend est **architecturalement sain** sur les deux points les plus difficiles à rattraper après coup :

- **Isolation multi-tenant** — `TenantScope` global fail-closed, tenant résolu *exclusivement* depuis l'utilisateur authentifié, `tenant_id` non-`fillable`, 403 sur tenant suspendu/expiré. Aucun vecteur de spoofing trouvé.
- **Fichiers privés** — `TenantFileService` centralise 100 % des uploads sur un disque privé, chemins `tenants/{id}/{module}/{uuid}`, URLs signées 30 min. Aucune URL de stockage devinable.

Ces deux couches n'ont **pas besoin d'être retouchées** pour le mobile. C'est l'acquis majeur des Phases 2/3.

En revanche, le backend a été construit pour un **back-office web administratif**, pas pour du **travail terrain**. Trois conséquences structurelles :

1. **Un agent de terrain ne peut quasiment rien déclarer.** Il peut créer un incident — c'est tout. Near Miss, Environnement, Gemba : interdits par middleware. Or ce sont précisément les modules que le brief mobile (§20, §23, §24) place au cœur du MVP.
2. **Le mobile ne peut pas coexister avec le web.** Chaque login détruit tous les jetons de l'utilisateur.
3. **Il n'y a pas de permissions.** Le brief (§33) exige « les permissions backend sont la source de vérité ». Spatie est installé, 5 rôles existent, **zéro permission n'est définie**, et les 10 policies ne sont **jamais appelées**.

Aucun de ces points ne remet en cause l'architecture. Ce sont des ajustements de surface d'API, chiffrés en §6.

---

## 2. Ce qui est solide — à ne pas retoucher

| Composant | Fichier | Évaluation |
|---|---|---|
| Global scope tenant | `app/Models/Scopes/TenantScope.php` | Fail-closed hors console si aucun contexte (`1=0`). Bypass uniquement explicite via `runWithoutScope()`. Rien à redire. |
| Trait tenant | `app/Models/Concerns/BelongsToTenant.php` | Auto-affectation serveur, `tenant_id` hors `fillable` → pas de mass-assignment. Appliqué à 20 modèles. |
| Résolution du tenant | `app/Http/Middleware/ResolveTenant.php` | Lit **uniquement** `$user->tenant_id`. Jamais header/query/body. Conforme §4. |
| Garde-fou tenant | `app/Http/Middleware/EnsureTenantContext.php` | 403 si sans tenant / introuvable / suspendu / trial expiré. |
| Fichiers | `app/Services/TenantFileService.php` + `FileController` | Disque privé, URL signée courte, refus hors `tenants/`. R2/S3-ready par config. |
| Réponse JSON forcée | `app/Http/Middleware/ForceJsonResponse.php` | `Accept: application/json` forcé → jamais de HTML d'erreur vers le mobile. |
| Pagination | `app/Traits/HandlesApiResources.php` | `per_page` plafonné à 100. Conforme §38. |

**Conclusion : Flutter consomme cette API sans jamais dupliquer de logique métier ni de sécurité.** Conforme §0, §71.

---

## 3. Tableau de readiness — endpoints mobile

Rôles : `company_admin` (CA), `hsse_manager` (HM), `supervisor` (SV), `agent` (AG). `super_admin` est **exclu de toutes les routes métier** (son `tenant_id` est NULL → 403 par `EnsureTenantContext`) — conforme au brief §2 « Super Admin reste WEB uniquement ».

### 3.1 Authentification & session

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Login matricule + PIN | `/api/v1/auth/login` | POST | Public | n/a | ⚠️ | Fonctionne. **Détruit tous les autres jetons** → B1 |
| Demande OTP e-mail | `/api/v1/auth/request-otp` | POST | Public | n/a | ✅ | OTP 6 chiffres, 5 min, 3 tentatives |
| Vérification OTP | `/api/v1/auth/verify-otp` | POST | Public | n/a | ⚠️ | Même problème B1 |
| Profil courant | `/api/v1/auth/me` | GET | Tous | ✅ | ⚠️ | Renvoie `user` + `organisation` (branding tenant dynamique). **Ni `tenant.id` ni capacités** → B5 |
| Logout | `/api/v1/auth/logout` | POST | Tous | ✅ | ✅ | Supprime le jeton courant uniquement |
| Inscription | `/api/v1/auth/register` | POST | Public | ⚠️ | ❌ | Rattache en dur au tenant `slug='tcn'` → B4 |
| Mot de passe oublié | `/api/v1/auth/forgot-pin` · `/reset-pin` | POST | Public | n/a | ✅ | Réponse non-énumérante sur `forgot-pin`. Correct |
| Branding pré-login | `/api/v1/organisation` | GET | Public | ❌ | ❌ | **Codé en dur sur `slug='tcn'`** → B4 |

### 3.2 Dashboard & KPI (§17)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| KPI globaux | `/api/v1/dashboard` | GET | CA·HM·SV·AG | ✅ | ✅ | 7 blocs : employees, safety, environment, gemba, contractors, equipment, visitors |
| Timeline sécurité | `/api/v1/dashboard/safety-timeline` | GET | CA·HM·SV·AG | ✅ | ✅ | |
| Répartition effectifs | `/api/v1/dashboard/employee-breakdown` | GET | CA·HM·SV·AG | ✅ | ✅ | |
| Stats incidents | `/api/v1/dashboard/incident-stats` | GET | CA·HM·SV·AG | ✅ | ✅ | |
| Activité récente | `/api/v1/dashboard/recent-activity` | GET | CA·HM·SV·AG | ✅ | ✅ | Flux unifié ; champ `link` en routes **web** → remapper côté mobile (R3) |
| Top zones | `/api/v1/dashboard/top-zones` | GET | CA·HM·SV·AG | ✅ | ✅ | |

**Le dashboard est prêt et accessible à tous les rôles, agent inclus.** Stats calculées serveur, conforme §17.

### 3.3 Incidents (§19) — le seul module réellement ouvert au terrain

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Liste + filtres | `/api/v1/incidents` | GET | CA·HM·SV·AG | ✅ | ✅ | Filtres `search,type,severity,status,from,to` + pagination. Conforme §38/§40 |
| Créer | `/api/v1/incidents` | POST | CA·HM·SV·AG | ✅ | ⚠️ | **Agent autorisé ✅**. Mais `type=FIRE` / `FIRST_AID` → **500** → B3 |
| Détail | `/api/v1/incidents/{id}` | GET | CA·HM·SV·AG | ✅ | ✅ | 404 si autre tenant (scope global) |
| Modifier | `/api/v1/incidents/{id}` | PUT | CA·HM·SV | ✅ | ✅ | Agent exclu — cohérent règle §11 Phase 2 |
| Clôturer | `/api/v1/incidents/{id}/close` | POST | CA·HM | ✅ | ✅ | Exige `root_cause` + `corrective_action` |
| Supprimer | `/api/v1/incidents/{id}` | DELETE | CA·HM | ✅ | ✅ | |
| Statistiques | `/api/v1/incidents/stats` | GET | CA·HM·SV·AG | ✅ | ✅ | Par type / gravité / mois |

**Champs réels** (`StoreIncidentRequest`) — à reproduire **exactement**, aucun champ inventé :

```
date*        date
time         string
location*    string (max 255)
type*        LTI | FIRE | MTC | RWC | FIRST_AID | HPI   <-- voir B3
severity*    low | medium | high | critical
description* text
immediate_cause, root_cause, corrective_action   text
corrective_action_due                            date
status       open | in_progress | closed
employees[]  int[]  (jsonb en base)
image        image, max 5 Mo
```

### 3.4 First Aid (§21) et Fire (§22) — **ne sont pas des modules**

Découverte importante : le backend **ne possède aucune table ni endpoint** `first_aid` ou `fire`. Ce sont des **valeurs du champ `type` des incidents**.

> **Décision d'architecture recommandée : ne pas créer de modules séparés.**
> First Aid = `GET /incidents?type=FIRST_AID`, Fire = `GET /incidents?type=FIRE`.
> Zéro endpoint nouveau, zéro migration, zéro duplication. Conforme §0 et §22 (« ne pas simuler une fonctionnalité »).

**Mais** : ces deux valeurs sont aujourd'hui **inutilisables** — voir B3.

### 3.5 Near Miss (§20)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Liste · Créer · Détail · Modifier · Clôturer · Supprimer | `/api/v1/near-miss[...]` | GET·POST·PUT·DELETE | **CA·HM uniquement** | ✅ | ❌ | **Agent ET superviseur bloqués (403) sur tout le module** → B2 |

Champs (`StoreNearMissRequest`) : `date*, time, location*, severity* (low|medium|high), description*, potential_consequence, corrective_action, corrective_action_due, status, employees[], image`.

Techniquement complet et propre. **Le seul problème est le gating par rôle** — qui est précisément ce que le brief §20 demande d'ouvrir au terrain.

### 3.6 Environnement (§23)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Liste · Créer · Détail · Modifier · Clôturer · Supprimer · Stats | `/api/v1/environment[...]` | GET·POST·PUT·DELETE | **CA·HM uniquement** | ✅ | ❌ | Même blocage que Near Miss → B2 |

Champs : `date*, location*, type* (spill|emission|waste|noise|other), severity*, description*, impact, corrective_action, corrective_action_due, status, image`.

### 3.7 Inspections (§24)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Rondes Gemba | `/api/v1/gemba-walks[...]` | GET·POST·PUT·DELETE | CA·HM | ✅ | ❌ | Équivalent le plus proche d'« inspections ». Agent/SV bloqués |
| Inspection équipement | `/api/v1/equipment/{id}/inspect` | POST | CA·HM | ✅ | ❌ | Agent/SV bloqués |
| Checklists / findings structurés | — | — | — | — | ❌ | **MISSING API ENDPOINT** — n'existe pas. Ne pas simuler (§24) |

**Recommandation : Inspections hors périmètre MVP mobile.** Le brief §73 autorise explicitement le report.

### 3.8 Personnel (§25)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Liste | `/api/v1/employees` | GET | CA·HM·SV·AG | ✅ | ⚠️ | Fonctionne. **Expose des PII à tous les rôles** → B8 |
| Détail | `/api/v1/employees/{id}` | GET | CA·HM·SV·AG | ✅ | ⚠️ | Idem |
| Créer · Modifier · Supprimer | `/api/v1/employees[...]` | POST·PUT·DELETE | CA·HM | ✅ | ✅ | Correctement fermé à l'agent, conforme §25 |
| Recherche globale | `/api/v1/search?q=` | GET | CA·HM·SV·AG | ✅ | ✅ | Min. 2 car., 12 résultats/type, index GIN trigram |

### 3.9 Notifications (§28)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Liste in-app | `/api/v1/notifications` | GET | Tous | ✅ | ✅ | Paginé + `unread_count` |
| Compteur non lues | `/api/v1/notifications/unread-count` | GET | Tous | ✅ | ✅ | |
| Marquer lue · toutes lues | `/api/v1/notifications/{id}/read` · `/read-all` | PUT·POST | Tous | ✅ | ✅ | |
| **Enregistrement device token FCM** | — | — | — | — | ❌ | **MISSING API ENDPOINT** → B6 |
| **Envoi push** | — | — | — | — | ❌ | **MISSING** — aucune intégration FCM côté backend |

**Push notifications : impossibles sans travail backend.** In-app par polling : disponible immédiatement.

### 3.10 Fichiers et photos (§26, §63)

| Feature | Endpoint | Method | Permission | Tenant scoped | Mobile ready | Notes |
|---|---|---|---|---|---|---|
| Upload générique | `/api/v1/media` | POST | Tous | ✅ | ⚠️ | max 20 Mo. `model_type` non validé → R1 |
| Métadonnées média | `/api/v1/media/{id}` | GET | Tous | ✅ | ✅ | 404 hors tenant |
| Suppression | `/api/v1/media/{id}` | DELETE | Tous | ✅ | ⚠️ | Aucun contrôle de propriété au-delà du tenant |
| Téléchargement signé | `/api/v1/media/{id}/download` | GET | URL signée | ✅ | ✅ | Sans jeton → `Image.network` direct |
| Service fichier par champ | `/api/v1/files/serve?path=` | GET | URL signée | ✅ | ✅ | Anti-traversée, refuse hors `tenants/` |

**Point d'attention mobile** : les `*_url` signées expirent après **30 min** (`MEDIA_SIGNED_URL_TTL`). Une photo mise en cache par Flutter échouera après expiration → le cache image doit être **indexé sur `image` (le chemin), pas sur `image_url`**, et l'URL rafraîchie en re-récupérant la ressource.

### 3.11 Modules web-only — hors périmètre mobile

`settings`, `users`, `departments` (écriture), `imports`, `exports`, `reports` PDF, `safety-tracker`, `visitors`, `contractors`, `equipment`, `permits`, `breaches`, `audit`, et l'intégralité de `/superadmin/*`.

Conforme §59 : **l'administration reste web.**

---

## 4. Bloquants

Classés par gravité. Chacun est reproductible.

### B1 — 🔴 CRITIQUE — Login mobile = déconnexion web (session unique)

**Fait.** `AuthController::loginWithMatricule()` et `verifyOtp()` exécutent tous deux :

```php
$user->tokens()->delete();          // <-- supprime TOUS les jetons de l'utilisateur
$token = $user->createToken(...)->plainTextToken;
```

**Conséquence.** Un HSE Manager connecté au web qui se connecte au mobile est **immédiatement déconnecté du web**, et réciproquement. Un aller-retour mobile/bureau devient une boucle de reconnexions. C'est rédhibitoire pour un usage terrain.

**Cause.** Choix légitime pour une app web mono-session ; incompatible avec un second client.

**Correctif recommandé.** Nommer les jetons par plateforme et ne révoquer que la plateforme concernée :

```php
$user->tokens()->where('name', 'operix-mobile')->delete();
$token = $user->createToken('operix-mobile')->plainTextToken;
```

Le nom de plateforme vient d'un paramètre validé contre une liste blanche (`web` | `mobile`), jamais d'une chaîne libre du client. Un test de non-régression doit vérifier qu'un login mobile **laisse vivant** le jeton web.

### B2 — 🔴 CRITIQUE — Un agent de terrain ne peut déclarer ni Near Miss ni observation environnementale

**Fait.** `routes/api.php` :

```php
Route::middleware('role:company_admin,hsse_manager')->prefix('near-miss')    // 403 pour AG et SV
Route::middleware('role:company_admin,hsse_manager')->prefix('environment')  // 403 pour AG et SV
```

**Conséquence.** Le brief §20 exige « déclaration en moins d'une minute » et §18 place « Report Near Miss » et « Environment Observation » dans les Quick Actions. **Ces deux écrans renverraient 403 pour la population cible de l'application.** Le MVP mobile n'a alors qu'une seule action réelle : déclarer un incident.

**Tension à arbitrer — décision produit, pas technique.** La règle §11 de la Phase 2 disait : « agent PEUT signaler un incident (pas supprimer/clôturer) ». La logique se transpose naturellement : **un agent doit pouvoir signaler un near miss et une observation environnementale, sans pouvoir les modifier, clôturer ni supprimer.** C'est le principe HSE de base — le signalement de presqu'accident n'a de valeur que s'il est massivement ouvert au terrain.

**Correctif recommandé.** Aligner Near Miss et Environnement sur le découpage déjà validé pour Incidents :

| Opération | Rôles |
|---|---|
| `GET` liste / détail / stats | CA · HM · SV · AG |
| `POST` créer | CA · HM · SV · AG |
| `PUT` modifier | CA · HM · SV |
| `POST /close`, `DELETE` | CA · HM |

Chaque changement couvert par un test « agent peut créer / agent ne peut pas clôturer » et par un test d'isolation inter-tenant.

**Non appliqué sans accord explicite** : cela élargit une surface d'écriture en production. C'est l'arbitrage n°1 avant la Phase 1.

### B3 — 🔴 CRITIQUE — `type=FIRE` et `type=FIRST_AID` provoquent une erreur 500

**Fait, reproduit.** La validation API et la contrainte PostgreSQL divergent :

| Source | Valeurs acceptées |
|---|---|
| `StoreIncidentRequest` | `LTI`, **`FIRE`**, `MTC`, `RWC`, **`FIRST_AID`**, `HPI` |
| CHECK `safety_incidents_type_check` | `LTI`, `MTC`, `RWC`, **`FAC`**, `HPI`, **`Fire`**, `Security`, `Autre` |

Reproduction sur la base de développement :

```
FIRE       => REJETE PAR LA BASE  (SQLSTATE[23514] Check violation)
FIRST_AID  => REJETE PAR LA BASE  (SQLSTATE[23514] Check violation)
LTI        => INSERT OK
```

**Conséquence.** La requête passe la validation (pas de 422), puis la base rejette l'insertion → **`QueryException` → HTTP 500**. Côté mobile : échec de soumission sans message exploitable, photo déjà uploadée mais aucun enregistrement créé (violation directe de §63).

Symétriquement, `FAC`, `Security` et `Autre` existent en base mais sont **refusés** par la validation : d'anciens incidents peuvent porter un type que l'API refuse en modification.

**Pourquoi ça n'a jamais explosé.** Le front web n'expose que les types de l'intersection sûre (`LTI`, `MTC`, `RWC`, `HPI`). Le bug est **latent**, et le mobile le déclencherait immédiatement puisque §21/§22 demandent explicitement First Aid et Fire.

**Correctif recommandé.** Migration `backward-compatible` reconstruisant la contrainte CHECK sur le **vocabulaire unifié**, avec **remap des données existantes** (`FAC` → `FIRST_AID`, `Fire` → `FIRE`) sans perte, puis alignement de `StoreIncidentRequest` / `UpdateIncidentRequest` et des libellés du front web. Conforme §70 : aucune suppression de données, migration testée et réversible.

### B4 — 🟠 MAJEUR — Deux points d'entrée codés en dur sur le tenant `tcn`

**Fait.** Deux endroits violent §34 et §60 (« ne jamais écrire TCN dans le code ») :

1. `GET /api/v1/organisation` — `Tenant::where('slug','tcn')->first()`, avec valeurs de repli en dur : « Terminal à Conteneurs de Nouakchott », `#0f2847`, `MR`.
2. `POST /api/v1/auth/register` — rattache tout nouvel inscrit au tenant `tcn` (travail actuellement non commité sur `master`).

**Conséquence mobile.** Un utilisateur d'ABC Logistics verrait le branding TCN sur l'écran de login, et l'auto-inscription créerait son compte **dans le tenant TCN**.

**Nuance importante — non bloquant pour le MVP.** `AuthController::orgInfo()` résout correctement `$user->tenant` : **après login, le branding est déjà 100 % dynamique et correct.**

**Correctif recommandé, en deux temps.**
- *MVP, aucun changement backend* : le mobile **n'appelle jamais `/organisation`**. L'écran de login affiche l'identité **OPERIX HSE** seule ; le branding du tenant n'apparaît qu'**après** authentification, depuis `/auth/me`. C'est de toute façon le comportement correct pour une app multi-tenant — on ne connaît pas l'entreprise avant de savoir qui se connecte.
- *Assainissement backend* : sortir le slug en configuration (`config('operix.public_tenant_slug')`), et à terme faire passer l'inscription par le flux d'activation existant (`tenant_activations`), déjà propre et multi-tenant.

### B5 — 🟠 MAJEUR — Aucune permission n'existe : le §33 ne peut pas être respecté tel quel

**Fait.** Trois constats convergents :

- `grep "Permission::"` sur `app/` et `database/` → **0 résultat**. Aucune permission Spatie définie ni assignée.
- `grep "authorize\|Gate::"` sur `app/Http/Controllers/` → **0 résultat**. Les **10 policies** (`IncidentPolicy`, `NearMissPolicy`, …) sont enregistrées dans le provider mais **jamais appelées**. Ce sont des stubs permissifs (`viewAny → true`) — du code mort décoratif.
- L'autorisation réelle est intégralement portée par le middleware de route `role:...` et par le champ `users.role` (string sous contrainte CHECK).

**Conséquence.** Le brief §33 dit « utiliser les permissions réelles, ne jamais faire `if role == admin` ». **Le backend ne fournit aucune permission à consommer.** Un client mobile n'a d'autre choix que de raisonner sur la chaîne `role` — exactement ce que §33 proscrit.

**Ce n'est pas une faille de sécurité** : le middleware protège effectivement chaque route, et le mobile ne peut rien débloquer côté client. C'est un problème de **contrat d'API** : le mobile doit deviner ce que l'utilisateur a le droit de faire, en dupliquant la table des rôles du backend — donc en la désynchronisant tôt ou tard.

**Correctif recommandé — le plus rentable de tout ce rapport.** Faire renvoyer par `/auth/me` une **carte de capacités calculée serveur** :

```jsonc
{
  "user":   { "id": 42, "name": "...", "role": "agent" },
  "tenant": { "id": 1, "name": "TCN Mauritanie", "logo_url": "...", "primary_color": "#0f2847" },
  "abilities": {
    "incidents.create": true,   "incidents.close": false,
    "near_miss.create": true,   "near_miss.close": false,
    "environment.create": true, "employees.read": true, "employees.write": false
  }
}
```

Le mobile ne fait alors **que** masquer/désactiver selon `abilities` (§33 : « Flutter peut cacher, désactiver, rediriger »), et le backend reste seul juge. Ajouter au passage les rôles Spatie effectifs (`$user->getRoleNames()`). Un seul endpoint modifié, aucune migration, la table des rôles cesse d'être dupliquée dans Flutter.

*Nota : introduire de vraies permissions granulaires Spatie est un chantier légitime mais bien plus lourd. La carte de capacités donne l'essentiel du bénéfice pour une fraction du coût, et reste compatible avec une migration ultérieure vers Spatie.*

### B6 — 🟠 MAJEUR — Aucune infrastructure de push (§28)

**MISSING API ENDPOINT.** Rien n'existe : ni table `device_tokens`, ni endpoint d'enregistrement, ni intégration FCM, ni envoi.

Les notifications actuelles sont **in-app uniquement** (table `notifications`, scopée tenant, lue par polling).

**Recommandation.** MVP = notifications in-app par polling ou pull-to-refresh, **sans FCM**. Le push exige côté backend : migration `device_tokens` (scopée tenant, unique par token, liée à l'utilisateur), endpoints `POST/DELETE /device-tokens`, service d'envoi FCM, purge des tokens invalides, et tests associés. C'est une phase à part entière — à placer après le MVP terrain, conformément à §73.

### B7 — 🟡 MOYEN — Les jetons Sanctum n'expirent jamais

**Fait.** `config/sanctum.php` : `'expiration' => null`.

**Conséquence.** Un jeton volé sur un téléphone perdu reste **valide indéfiniment**. Et le brief §11 (« gérer token expiré », « éviter les boucles 401 → refresh ») ainsi que §76 (« token expiration tested ») portent sur un comportement **que le backend ne produit jamais** — impossible à tester honnêtement.

**Recommandation.** Le mobile doit **quand même** implémenter le chemin 401 → logout propre (défense en profondeur ; le jeton peut être révoqué côté serveur). Côté backend, une expiration explicite est souhaitable mais **impacte le web en production** : à décider séparément, pas en marge du chantier mobile.

### B8 — 🟡 MOYEN — PII employés exposées à tous les rôles (§62)

**Fait.** `GET /employees` est ouvert à `CA·HM·SV·AG`, et `EmployeeResource` sérialise sans filtre : `num_cni`, `date_naissance`, `lieu_naissance`, `adresse`, `contact_urgence_nom`, `contact_urgence_tel`, `email`, `phone`, `nationalite`.

**Conséquence.** N'importe quel agent peut lire le numéro de carte d'identité et l'adresse personnelle de tous ses collègues. Sur mobile — appareil personnel, perdu ou partagé — l'exposition est nettement plus élevée que sur un poste web.

**Recommandation.** Conditionner les champs sensibles dans `EmployeeResource` au rôle (`$request->user()->isAdmin()`), en laissant le reste (nom, matricule, poste, département, photo) accessible à tous. Correction ciblée, un seul fichier, sans impact sur le web administratif. À couvrir par un test « un agent ne voit pas `num_cni` ».

### B9 — 🟡 MOYEN — Aucun second tenant de test (§5, §61)

**Fait.** Seul `tcn` est semé (`DatabaseSeeder`, `TcnSeeder`). Aucun tenant « Demo Company ».

**Conséquence.** L'exigence §5/§61 — « le même build Flutter fonctionne pour TCN et Demo Company, aucune donnée croisée » — n'est pas démontrable en l'état.

**Recommandation.** Un `DemoTenantSeeder` créant un second tenant complet (utilisateurs des 5 rôles, employés, incidents, near miss) — indispensable aussi pour la démonstration de soutenance, qui est l'argument le plus fort de l'architecture multi-tenant.

*Nota : côté tests automatisés, l'isolation est déjà couverte (`TenantIsolationTest`, `SecurityIsolationTest`, `UserIsolationTest`, `MediaIsolationTest`, `FileUploadIsolationTest`, `UniqueScopeTest`). Le manque porte sur le jeu de données **démonstrable**, pas sur la preuve automatisée.*

---

## 5. Remarques mineures (à traiter, non bloquantes)

| Réf | Constat | Action |
|---|---|---|
| R1 | `MediaController::store` injecte `model_type` (chaîne libre du client) directement dans le chemin de stockage, et ne vérifie pas que `model_id` appartient au tenant. La traversée `../` est neutralisée par Flysystem 3, mais rien n'empêche de créer des répertoires arbitraires ni d'attacher un média à un `model_id` inexistant. | Liste blanche de `model_type` + vérification d'appartenance |
| R2 | Trois formes de pagination coexistent : modules métier `{total, per_page, current_page, last_page}` ; notifications `{current_page, last_page, total, unread_count}` (sans `per_page`) ; users `{current_page, last_page, total}`. | Le mobile tolère les trois — un modèle `PageMeta` à champs optionnels suffit |
| R3 | `dashboard/recent-activity` renvoie des `link` en routes **web** (`/incidents/3`). | Le mobile remappe via `type` + `id`, jamais via `link` |
| R4 | `X-Tenant-Slug` figure encore dans `config/cors.php` (`allowed_headers`). Aucun code ne le lit — vestige inoffensif. | Retirer pour cohérence avec la décision Phase 2 |
| R5 | `GET /health` annonce `'app' => 'Operix HSSE — TCN'`. | Nom de produit codé en dur — devrait être « Operix HSE » |
| R6 | Les 10 policies ne sont jamais appelées (code mort). | Les brancher via `authorize()` ou les supprimer. Ne pas laisser croire à une couche de sécurité inexistante |

---

## 6. Ce qui doit être décidé avant la première ligne de Flutter

Le brief §69 interdit de créer des endpoints sans analyse préalable. L'analyse est faite ; voici les arbitrages.

### Modifications backend nécessaires au MVP mobile

| # | Changement | Bloquant | Coût | Migration DB |
|---|---|---|---|---|
| 1 | Jetons nommés par plateforme (B1) | 🔴 oui | faible | non |
| 2 | Vocabulaire `type` incident unifié (B3) | 🔴 oui | moyen | **oui** (remap `FAC`→`FIRST_AID`, `Fire`→`FIRE`) |
| 3 | Ouvrir `POST near-miss` + `POST environment` à SV·AG (B2) | 🔴 oui | faible | non |
| 4 | `abilities` + `tenant` dans `/auth/me` (B5) | 🟠 fort | faible | non |
| 5 | Filtrer les PII de `EmployeeResource` (B8) | 🟡 souhaitable | faible | non |
| 6 | `DemoTenantSeeder` (B9) | 🟡 souhaitable | faible | non |

Chaque point suit la discipline §69 : route → contrôleur → validation → tenant scope → resource → **tests** → documentation.

### Reporté hors MVP, assumé et documenté

Push FCM (B6) · expiration des jetons (B7) · inspections avec checklists (§24) · offline-first (§30, architecture préparée uniquement) · deep links (§66) · Sentry (§43, `AppLogger` / `ErrorReporter` seulement).

### Trois questions ouvertes

1. **B2 — ouvre-t-on Near Miss et Environnement au terrain ?** Décision structurante : elle détermine si le MVP mobile a une ou trois actions réelles. Recommandation : oui, en création seule.
2. **B3 — remappe-t-on `FAC`/`Fire` vers `FIRST_AID`/`FIRE` ?** Alternative : conserver les valeurs existantes et corriger la validation dans l'autre sens (pas de migration, mais vocabulaire incohérent `Fire`/`FAC` face à `LTI`/`HPI`). Recommandation : remap, avec migration réversible.
3. **Nom de la branche.** Les dépôts suivent la convention `phase-N-*` (`phase-2-multitenant`, `phase-3-saas-commercial`). **`phase-4-mobile-hse`** est cohérent avec l'historique.

### Point Git à traiter d'abord

`operix-api` et `operix-web` portent du travail **non commité directement sur `master`** (rattachement tenant de l'inscription + page Super Admin « Entreprises »). Conformément à §68, ce travail doit être **commité ou écarté avant** de créer la branche mobile — sinon il sera mécaniquement absorbé dans le premier commit Flutter.

---

## 7. Conformité au brief

| Exigence | État |
|---|---|
| §0 Ne rien réécrire, pas de second backend | ✅ Aucune duplication proposée |
| §4 Tenant jamais fourni par le client | ✅ Garanti par `ResolveTenant` |
| §5 Deux tenants de test | ❌ Un seul semé — B9 |
| §13 Ne pas inventer d'endpoints | ✅ 3 manques documentés « MISSING API ENDPOINT » |
| §21 First Aid | ⚠️ Existe comme `type` d'incident, cassé — B3 |
| §22 Fire, ne pas simuler | ⚠️ Idem — B3 |
| §24 Inspections | ⚠️ Gemba Walks uniquement, sans checklist — report recommandé |
| §26 Fichiers privés + signed URLs | ✅ Déjà exemplaire |
| §28 Push FCM | ❌ Aucune infrastructure — B6 |
| §33 Permissions source de vérité | ❌ Aucune permission n'existe — B5 |
| §34/§60 Aucun « TCN » en dur | ⚠️ 2 points backend — B4 (contournable côté mobile) |
| §59 Administration reste web | ✅ Découpage déjà conforme |
| §71 Jamais d'accès direct PostgreSQL | ✅ Par construction |

---

## 8. Statut

**Phase 0 terminée. Aucun code Flutter écrit, conformément à §0 et §79.**

Toutes les vérifications automatisées de l'existant passent : **78 tests backend / 252 assertions PASS**, build front vert. Les bloquants ci-dessus sont des **manques de couverture fonctionnelle et des incohérences de contrat**, pas des régressions — à l'exception de **B3, qui est un vrai bug latent en production**, reproduit et documenté ci-dessus.

**Prochaine étape : arbitrage des trois questions ouvertes du §6, puis Phase 1 (fondation Flutter) sur `phase-4-mobile-hse`.**
