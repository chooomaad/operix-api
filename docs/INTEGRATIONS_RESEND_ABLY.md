# Intégrations production — Resend & Ably (Operix TCN)

Runbook pour activer réellement les emails transactionnels (Resend) et le temps
réel (Ably). Le code est déjà en place ; il ne reste qu'à fournir des credentials
réels. **Aucune vraie clé ne figure dans ce dépôt ni dans cette documentation.**

---

## 1. Resend (emails — réinitialisation du PIN)

### Prérequis à fournir
- Un **domaine réellement contrôlé** par le projet (ex. `operix-tcn.mr` ou un
  sous-domaine). Une adresse Gmail/Yahoo NE PEUT PAS être vérifiée comme expéditeur.
- Un compte Resend et une **clé API** dédiée à la production.

### Étapes
1. Dans Resend → **Domains** → ajouter le domaine → publier les enregistrements DNS
   fournis (SPF `TXT`, DKIM `CNAME`/`TXT`, et de préférence DMARC). Attendre le
   statut **Verified**.
2. Créer une **API key** (scope *Sending*).
3. Renseigner le `.env` **serveur** (jamais commité) :

```env
MAIL_MAILER=resend
RESEND_KEY=<clé serveur Resend>
MAIL_FROM_ADDRESS=no-reply@<domaine-vérifié>
MAIL_FROM_NAME="Operix TCN"
```

> La clé est lue par `config/services.php` → `services.resend.key` (variable
> `RESEND_KEY`) et le transport `resend` de `config/mail.php`. En dev, laisser
> `MAIL_MAILER=log` écrit les emails dans `storage/logs` sans clé ni domaine.

### Test réel
```bash
# 1. Envoi direct (remplacer par une adresse autorisée)
php artisan tinker
>>> Mail::raw('Test Operix', fn($m) => $m->to('vous@exemple.com')->subject('Test'));
# 2. Flux complet : /login → « PIN oublié » → email → lien → /reset-pin → nouveau PIN
```
Vérifier : email **reçu**, statut **Delivered** dans le tableau de bord Resend,
pas de bounce/spam. Les logs ne contiennent jamais la clé ni le token.

### Sécurité / anti-énumération (déjà en place, ne pas modifier)
- `forgot-pin` renvoie une réponse **générique identique** que le compte existe ou
  non. Token haché SHA-256, usage unique, expiration 60 min.

---

## 2. Ably (temps réel — notifications)

### Prérequis à fournir
- Une **application Ably dédiée** à Operix TCN (ne pas réutiliser une clé partagée
  entre environnements).
- Une **clé API Ably** de la forme `APPID.KEYID:SECRET`.

### Étapes
1. Ably → créer l'app « Operix TCN » → **API Keys** → une clé avec capacités
   `subscribe`, `publish`, `presence` (canaux privés/presence).
2. Scinder la clé et renseigner le `.env` **serveur** :

```env
BROADCAST_CONNECTION=ably
ABLY_APP_ID=<APPID>
ABLY_PUBLIC_KEY=<APPID.KEYID>   # partie publique
ABLY_SECRET=<SECRET>            # SERVEUR UNIQUEMENT
```

3. Côté **frontend** (`operix-web/.env`), exposer UNIQUEMENT la partie publique :

```env
VITE_ABLY_PUBLIC_KEY=<APPID.KEYID>
```

> Transport : Ably via le **protocole Pusher** (réutilise `pusher-php-server` +
> `pusher-js` déjà présents ; aucun SDK Ably ajouté). Serveur → `rest-pusher.ably.io`
> (`config/broadcasting.php`), client → `realtime-pusher.ably.io` (`echo.ts`). Le
> **secret ne quitte jamais le serveur** : il signe `/broadcasting/auth`.

### Worker de file (obligatoire en production)
La diffusion est mise en file (`ShouldBroadcast`, `afterCommit`) : lancer un worker.
```bash
php artisan queue:work --queue=default
```
Sans worker, l'incident est bien créé (réponse 201) mais la notification n'est pas
diffusée tant que le worker n'a pas tourné.

### Test réel (deux navigateurs, même tenant TCN)
- DevTools → Network → **WS** : la connexion s'établit vers Ably → état `connected`,
  le bandeau disparaît.
- A crée un incident → B reçoit « 🔴 Nouvel incident » sans F5, le compteur de la
  cloche augmente, la notification est persistée (retrouvée après reconnexion).
- Couper/rétablir Internet **après** connexion → `reconnecting` → `connected`.
- Sans transport configuré → état `unavailable` (jamais `reconnecting` en boucle).

### Isolation & permissions (déjà en place)
- Canaux privés autorisés par `routes/channels.php` : compte actif + tenant actif +
  identité + appartenance au tenant. Un incident TCN ne notifie jamais un autre
  tenant. Destinataires = utilisateurs habilités (`{module}.view`).

---

## 3. Comportement en cas de panne
- **Ably indisponible** : la création d'incident reste rapide (diffusion en file,
  hors requête HTTP). Le frontend affiche « Notifications temps réel indisponibles »
  et reste pleinement utilisable.
- **Resend indisponible** : l'échec d'envoi est journalisé **sans secret** et ne
  bloque pas la requête `forgot-pin` (réponse générique inchangée).

## 4. Sécurité des secrets
- `RESEND_KEY` et `ABLY_SECRET` : **serveur uniquement**, jamais en `VITE_*`, jamais
  dans le build JS, jamais commités (les `.env` sont gitignorés).
- Un secret trouvé dans l'historique Git doit être **considéré compromis et
  régénéré** immédiatement.
