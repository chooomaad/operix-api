# Phase 3 — SaaS Commercial (architecture)

Baseline : `v0.2-multitenant`. Aucune fondation multi-tenant de Phase 2 modifiée (isolation,
scope, fichiers privés préservés). Devise commerciale **EUR** (source de vérité, montants en
centimes) ; **MRU** = équivalence d'affichage indicative (`exchange_rates`, jamais hardcodée client).

## Modèles (tous GLOBAUX plateforme — pas de tenant_id)
- `plans` — offres (EUR centimes, limites, features, `contact_sales`, actif/public). Prix administrables (Super Admin).
- `exchange_rates` — taux EUR→MRU (affichage indicatif).
- `demo_requests` — leads (public rate-limité → conversion trial par Super Admin).
- `orders` — commande indépendante du paiement (référence serveur `OPX-AAAA-000000`, montant calculé serveur).
- `payments` — transactions (`UNIQUE(provider, transaction_id)` = anti-replay ; payload assaini, jamais de carte/CVV).
- `subscriptions` — abonnement (source de vérité de l'offre ; `tenants.plan` = cache dénormalisé).
- `tenant_activations` — jetons d'activation (hachés sha256, usage unique, expiration courte).

## Parcours payant (le webhook est la SEULE preuve de paiement)
```
GET /plans (public) → POST /checkout (montant calculé serveur, Order pending)
 → redirection provider → webhook POST /webhooks/payments/{provider}
 → signature vérifiée → order trouvée → idempotence (tx déjà traité / order déjà payée)
 → montant & devise vérifiés serveur → Payment succeeded + Order paid (transaction)
 → ProvisioningService (transactionnel, idempotent) : Tenant + company_admin (sans mot de passe)
   + Subscription + TenantActivation → emails (paiement reçu + activation, en queue)
 → POST /activate {token, password} : l'utilisateur définit son accès.
```
Une redirection `/payment-success` ne prouve JAMAIS un paiement.

## Parcours démo
`POST /demo-requests` (public, throttle) → Super Admin `PUT .../status` puis
`POST .../convert` → `ProvisioningService::provisionTrialFromDemo` (même service, aucune
duplication) → Tenant trial + activation.

## Abstraction paiement
`App\Payments\PaymentProvider` (createCheckout/verifyWebhook/parseWebhook/retrievePayment).
Provider actif = **`fake`** (dev/tests). **Aucun prestataire réel connecté.** Le provider réel
(Mauritanie / international) sera choisi et branché séparément, par simple binding + config.

## Idempotence (webhook reçu N fois → jamais 2 entreprises)
1. `payments UNIQUE(provider, transaction_id)` + garde `Payment::exists`. 2. `order.status = paid` → no-op.
3. `ProvisioningService` verrouille (`lockForUpdate`) et court-circuite si `order.tenant_id` déjà posé.
4. Transaction DB → rollback complet si échec (aucun tenant partiel).

## Super Admin (plateforme, hors tenant)
Routes `/superadmin/*` (`auth:sanctum` + `superadmin`). Les lectures cross-tenant sur des
modèles scopés passent par le bypass **explicite** `TenantContext::runWithoutScope` — **jamais**
un bypass global automatique. Écrans : dashboard, demandes de démo, plans (prix), commandes,
paiements, abonnements. Frontend : console `/superadmin` (operix-web) + page `/activate`.

## Sécurité (tests dédiés)
`PlanTest`, `CheckoutTest`, `PaymentProviderTest`, `PaymentModelTest`, `WebhookTest`,
`ActivationTest`, `ProvisioningTest`, `DemoRequestTest`, `DemoConversionTest`,
`SubscriptionTest`, `SuperAdminCommerceTest`, `EmailTest`, `CommercialSecurityTest` :
signature forgée, doublon, montant/devise, order inconnue/déjà payée, provisioning idempotent
et **rollback transactionnel**, token expiré/utilisé, refus company_admin/agent, sanitization.

## Reporté (phases suivantes)
Prestataire de paiement réel, emails de cycle (trial expirant / renouvellement / expiré) via
tâches planifiées, R2 production (l'abstraction `TenantFileService` est déjà prête),
compression/WebP, quotas d'usage (mesure), refactor TS/optim Vite, infra/CI/mobile.
