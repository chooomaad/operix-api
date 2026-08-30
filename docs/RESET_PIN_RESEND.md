# Réinitialisation du PIN + Resend

Flux « PIN oublié » du web Operix TCN. Lien sécurisé envoyé par email.

## Parcours

```
Connexion → « PIN oublié ? » → email
        ↓  POST /api/v1/auth/forgot-pin { email }
Réponse GÉNÉRIQUE (jamais « ce compte existe »)
        ↓  email Resend → lien https://APP/reset-pin?token=…
Page /reset-pin → nouveau PIN + confirmation
        ↓  POST /api/v1/auth/reset-pin { token, new_pin, new_pin_confirmation }
PIN changé, sessions invalidées → connexion
```

## Token

- `Str::random(64)`, cryptographiquement aléatoire.
- Stocké **uniquement haché** (SHA-256) dans `pin_reset_tokens` — jamais en clair.
- **Usage unique** (`used_at`). Une nouvelle demande invalide les liens précédents.
- **Expiration : 60 min** (`PinResetService::TTL_MINUTES`).
- Le token n'apparaît que dans l'URL du lien — jamais le PIN, jamais le hash.

Mécanisme calqué sur `ActivationService` (patron éprouvé du projet), dans une
table dédiée pour ne pas mélanger les cycles de vie du login-OTP (`otp_tokens`)
et de l'activation (`tenant_activations`).

## Sécurité

- **Anti-énumération** : `forgot-pin` et `reset-pin` renvoient une réponse
  strictement constante quel que soit le cas (compte inconnu / connu / bloqué,
  token faux / expiré / utilisé). Un attaquant ne peut pas distinguer les comptes.
- **Rate limiting** : `throttle:5,1` sur `login`, `forgot-pin`, `reset-pin`.
- **PIN non trivial** : règle `StrongPin` (refuse `0000`, `1234`, chiffre répété,
  suite consécutive).
- **Sessions** : un reset réussi exécute `tokens()->delete()` → déconnexion de
  **toutes** les sessions, y compris un accès frauduleux éventuel. Comportement
  choisi : la sécurité prime sur le confort d'un utilisateur légitime connecté
  ailleurs, qui se reconnectera avec son nouveau PIN.
- **Journalisation** : un échec d'envoi d'email est journalisé **sans secret**
  (ni PIN, ni token) et ne bloque pas la requête — pour ne rien révéler.

## Resend

- Package : `resend/resend-php` (installé). Transport `resend` fourni par
  Laravel 13 (`config/mail.php`).
- Configuration (`.env`, **jamais** commitée, **jamais** en `VITE_*`) :

```env
MAIL_MAILER=resend
RESEND_KEY=            # cle serveur uniquement
MAIL_FROM_ADDRESS=no-reply@votre-domaine   # domaine verifie dans Resend
MAIL_FROM_NAME="Operix TCN"
```

- En développement, `MAIL_MAILER=log` écrit l'email dans `storage/logs` et suffit
  à tester le flux sans clé ni domaine.

## Limite connue — envoi réel non vérifié

L'envoi **réel** via Resend n'a pas été testé : il exige une `RESEND_KEY` et un
domaine vérifié, non disponibles à ce stade. Le flux complet est prouvé par les
tests (14 cas, dont un round-trip : demande → token du lien → reset → l'ancien
PIN est refusé, le nouveau ouvre une session), avec `Mail::fake` et le mailer
`log`. La bascule vers un envoi réel ne demande que de renseigner la clé et le
domaine — aucun changement de code.
