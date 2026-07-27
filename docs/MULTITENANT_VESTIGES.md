# Vestiges multi-tenant & critères d'acceptation Phase 2

> Document produit en **Phase 1 (stabilisation)**. But : documenter, sans les supprimer,
> les composants hérités de l'ancienne architecture multi-tenant, et figer les
> **critères d'acceptation** que la Phase 2 devra satisfaire.
>
> Contexte : le code a été volontairement rendu **mono-instance TCN** (`ADR-001`,
> voir `database/migrations/..._create_tenants_table.php`). La décision produit validée
> est de **revenir au multi-tenant complet**. Les fichiers ci-dessous sont donc
> **conservés intentionnellement** : ils seront réactivés/reconstruits en Phase 2, pas jetés.

---

## 1. Inventaire des vestiges conservés

| Composant | Fichier | État actuel | Rôle en Phase 2 |
|---|---|---|---|
| Modèle `Tenant` | `app/Models/Tenant.php` | Stub vide `@deprecated`, **sans** `HasFactory` | Redevient le modèle central de l'entreprise cliente (name, slug, status, plan, demo_expires_at…) |
| `TenantFactory` | `database/factories/TenantFactory.php` | Référence des colonnes (`slug`, `plan`, `status`, `max_employees`) d'une table inexistante | Factory de test des entreprises (base des tests d'isolation A/B) |
| `SuperAdmin/TenantController` | `app/Http/Controllers/SuperAdmin/TenantController.php` | **Non routé** (code mort). CRUD tenant complet + `impersonate()` | Base du back-office Super Admin Operix (à router + sécuriser) |
| `SuperAdmin/DashboardController` | `app/Http/Controllers/SuperAdmin/DashboardController.php` | Non routé | Dashboard global Operix |
| Middleware `SuperAdmin` | `app/Http/Middleware/SuperAdmin.php` | Aliasé `superadmin` mais **aucune route** ne l'utilise | Garde des routes `/api/v1/superadmin/*` |
| Middleware `TenantScope` | `app/Http/Middleware/TenantScope.php` | **No-op** (`return $next()`), aliasé `tenant.scope` | Résolution du tenant depuis l'utilisateur authentifié (jamais depuis le client) |
| Trait `HasTenantScope` | `app/Traits/HasTenantScope.php` | **Mal nommé** : ne fait aucun scoping, juste pagination/`generateReference`/`auditLog` | À renommer ; `generateReference()` devra compter **par tenant** |
| Modèle `Organisation` | `app/Models/Organisation.php` | Config mono-ligne TCN | À faire évoluer OU à conserver comme paramètres du tenant TCN |

> ⚠️ **Ne pas supprimer ces fichiers en Phase 1.** Leur suppression ou modification est
> réservée à la Phase 2, sur validation explicite.

---

## 2. Classification des 15 tests en échec (spécification Phase 2)

Tous échouent aujourd'hui au **premier** appel `App\Models\Tenant::factory()`
(`Call to undefined method` : le stub `Tenant` n'a pas `HasFactory`, et la table
`tenants` n'existe pas). Cette erreur **masque** les dépendances en aval, listées
ci-dessous groupe par groupe. Ces tests restent **volontairement rouges** : ils sont
le **cahier des charges exécutable** de la Phase 2.

### Groupe A — `tests/Feature/Api/AuthTest.php` (5 tests) — cycle de vie + suspension tenant
Dépendances manquantes :
1. Modèle `Tenant` réel + `HasFactory` + table `tenants` (colonnes dont `status`).
2. Colonne `users.tenant_id` (les tests font `User::factory()->create(['tenant_id'=>…])`).
3. **Garde de suspension** : `test_suspended_tenant_is_blocked` attend `GET /auth/me` → **403**
   + message `« Votre compte est suspendu. Contactez support@operix-app.com »`.
   Aucune logique de vérification du statut tenant n'existe (à ajouter : middleware ou `AuthController::me`).
4. `test_request_otp_for_known_user`, `test_verify_otp_*`, `test_me_*` : ne requièrent que (1)+(2)
   — les endpoints OTP/me fonctionnent déjà (prouvé par les 2 tests OTP verts).

### Groupe B — `tests/Feature/Api/IncidentTest.php` (5 tests) — création, référence scindée, clôture, RBAC
Dépendances manquantes :
1. `Tenant::factory()` + `users.tenant_id`.
2. Colonne `safety_incidents.tenant_id` (`assertDatabaseHas('safety_incidents', ['tenant_id'=>…])`
   + `SafetyIncident::factory()` fixe déjà `'tenant_id' => Tenant::factory()`).
3. **Auto-affectation** du `tenant_id` à la création (`IncidentController::store` depuis l'utilisateur authentifié).
4. **Référence par tenant** : `test_incident_reference_is_tenant_scoped` attend `INC-2026-0001`
   pour l'entreprise A **et** pour B. Or `HasTenantScope::generateReference()` compte
   **globalement** → à rendre scindé par `tenant_id`.
5. `test_agent_cannot_create_incident` (403) : le RBAC via `role:admin` **fonctionne déjà** ;
   seul `Tenant::factory()` le bloque.

### Groupe C — `tests/Feature/Api/TenantIsolationTest.php` (5 tests) — isolation stricte + route Super Admin
Dépendances manquantes :
1. `Tenant::factory()` + `tenant_id` sur `users`, `employees`, `safety_incidents`.
2. **Global scope tenant** sur `Employee` / `SafetyIncident` : les `index` ne doivent renvoyer
   que les lignes du tenant courant (`test_*_list_only_shows_own_tenant`). Aujourd'hui : requêtes globales.
3. **Isolation sur `find` → 404** inter-tenant (`test_cannot_read_other_tenant_employee_by_id`,
   `test_cannot_delete_other_tenant_employee`).
4. **Route Super Admin** `GET /api/v1/superadmin/dashboard` → **403** pour un admin d'entreprise.
   Cette route **n'est pas déclarée** dans `routes/api.php` (renverrait 404, pas 403).
   Nécessite : groupe de routes `superadmin` + middleware `SuperAdmin`.

---

## 3. Tests fonctionnels réellement verts (5) — à ne pas régresser

- `AuthTest::test_request_otp_requires_email`
- `AuthTest::test_request_otp_for_unknown_email_returns_404`
- `IncidentTest::test_unauthenticated_request_returns_401`
- `Tests\Feature\ExampleTest`
- `Tests\Unit\ExampleTest`

---

## 4. Prérequis techniques transverses pour la Phase 2

- **Découpler les migrations de PostgreSQL** (`jsonb`, `ilike`, index `pg_trgm` GIN) **ou** exécuter
  la suite sur PostgreSQL. Les tests tournent aujourd'hui sur **SQLite `:memory:`** (`phpunit.xml`).
- Décider du support des rôles : colonne string `role` (utilisée) vs **Spatie Permission** (installé, non utilisé).
- Repenser les contraintes d'unicité globales → `UNIQUE(tenant_id, …)` (ex. `employees.matricule`).
- Nettoyer les factories `Employee`/`SafetyIncident` qui référencent déjà `Tenant::factory()`.
