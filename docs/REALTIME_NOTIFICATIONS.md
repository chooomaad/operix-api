# Temps réel & notifications — Operix TCN Web

Notifications professionnelles sans rechargement de page. Ably assure le transport
WebSocket ; PostgreSQL reste la source de vérité.

## Architecture

```
Utilisateur → POST API → validation → PostgreSQL (commit) → HseEventCreated
                                                              ├─ diffusion  → Ably → canal tenant.{id}  (flux live)
                                                              └─ listener   → AppNotification (persistée) → Ably → user.{id}
```

La base est écrite AVANT toute diffusion : une opération métier ne dépend jamais
d'Ably. `HseEventCreated` implémente `ShouldBroadcast` (mise en file, `afterCommit`)
— la réponse HTTP n'attend ni le worker ni le WebSocket, et un Ably indisponible
n'empêche pas de créer l'incident (l'envoi réel a lieu dans le worker de file).

## Transport : Ably via le protocole Pusher

Ably parle nativement le protocole Pusher. Le projet embarquant déjà
`pusher/pusher-php-server` (serveur) et `pusher-js` (navigateur), on réutilise ces
dépendances plutôt que d'ajouter `ably/ably-php` + un client Ably — deux librairies
pour le même rôle (brief §8). Bascule par configuration :

- Serveur : `BROADCAST_CONNECTION=ably`, connexion `ably` (driver `pusher`) pointée
  sur `rest-pusher.ably.io` (cf. `config/broadcasting.php`).
- Client : `VITE_ABLY_PUBLIC_KEY` présent → Ably ; absent → Reverb (dev local).

### Secrets

Une clé Ably `APPID.KEYID:SECRET` est scindée : `ABLY_SECRET` reste **exclusivement
serveur** (signe `/broadcasting/auth`), jamais dans une variable `VITE_*`. Seule la
partie **publique** (`ABLY_PUBLIC_KEY`) est exposée au navigateur.

## Canaux & autorisation

- `private-user.{id}` — notifications personnelles.
- `presence-tenant.{id}` — évènements HSE de l'entreprise.

`routes/channels.php` (via `RealtimeChannelAccess::usable`) exige à **chaque**
abonnement : identité correspondante, compte actif, tenant rattaché et actif. Un
jeton Sanctum survit à une désactivation ou une suspension ; on revalide donc
l'état en direct — un accès révoqué cesse aussitôt de recevoir. Deviner
`user.999` ou le canal d'une autre entreprise est refusé par le serveur.

## Notifications persistées

Un évènement HSE crée une `AppNotification` par destinataire habilité du tenant
(rôles détenant `{module}.view`, l'auteur exclu) : le centre de notifications les
retrouve même après une reconnexion. Payload minimal (titre, corps, type, lien) —
le client recharge la ressource via l'API avec ses propres permissions.

## Frontend

- Instance Echo unique, ouverte après authentification, fermée à la déconnexion.
- États de connexion (connecting/connected/reconnecting/unavailable/failed) →
  bandeau discret non technique ; l'application reste utilisable si Ably tombe.
- Dédup au niveau des stores (id déjà présent ignoré) : une reconnexion qui rejoue
  un message n'affiche pas de doublon.
- KPI du tableau de bord incrémentés en direct, sans rechargement global.

## Limite connue — compte Ably non testé

Aucun compte Ably réel ni credentials n'étaient disponibles : le **handshake Ably
live n'a pas été vérifié**. L'intégration est prouvée par les tests automatisés
(diffusion, forme du payload, autorisation de canal sur diffuseur réel, isolation
tenant, persistance) et par le fallback Reverb en dev. La bascule production ne
demande que de renseigner `ABLY_APP_ID` / `ABLY_PUBLIC_KEY` / `ABLY_SECRET` (serveur)
et `VITE_ABLY_PUBLIC_KEY` (client) — aucun changement de code.
