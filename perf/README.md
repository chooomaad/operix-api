# Outillage de test de charge — Phase E

Mesure la capacité « 20 utilisateurs simultanés » d'Operix TCN Web.

## Prérequis
- PostgreSQL local (base `operix_local`), migrations à jour.
- Node ≥ 18 (client de charge, sans dépendance externe).

## 1. Semer un tenant de charge ISOLÉ (jamais les données réelles)
```
php artisan perf:seed --incidents=200 --employees=300
```
Comptes créés (PIN `739124`) : `LOAD-ADMIN`, `LOAD-HM`, `LOAD-AGENT`.

## 2. Serveur(s)
`php artisan serve` sous Windows est MONO-THREAD et SANS opcache : il sérialise les
requêtes et n'est PAS représentatif. Pour approcher un php-fpm multi-workers, lancer
un pool de serveurs `php -S` avec opcache (voir `perf/router.php`) sur 8010-8017.

## 3. Mesures
- `node perf/baseline.mjs`  — latence + nombre de requêtes SQL par endpoint (N+1).
- `VUS=20 node perf/load.mjs` — parcours réaliste concurrent (10/20/25/30 VUs).

## Instrumentation
Le middleware `QueryCountHeaders` (hors production) ajoute `X-Query-Count`,
`X-DB-Ms`, `X-Duration-Ms` à chaque réponse — détecteur de N+1.

## IMPORTANT
Un test local ne valide PAS O2Switch. La validation définitive des 20 utilisateurs
se fera sur l'infrastructure réelle (phase ultérieure).
