# Operix Web TCN — Audit PHASE A

Diagnostic de l'existant **avant toute modification**, comme l'exige le brief.
Ce document sépare ce qui a été **réellement vérifié** de ce qui **reste à
auditer** dans les phases suivantes. Rien n'est présumé « à jour » depuis la
documentation.

Date : 2026-08-30 · Branches : `phase-5-realtime` (API et Web), arbres propres.

---

## 0. Ligne de base (zéro régression)

| Contrôle | Résultat |
|---|---|
| `php artisan test` | **126 tests, 454 assertions, 0 échec** (35 s) |
| Type-check web (`vue-tsc`) | passe |
| Base PostgreSQL 17 | connectée, 44 tables |

C'est la référence : toute modification devra maintenir ces 126 tests verts.

---

## 1. Ce qui EXISTE déjà (à ne pas reconstruire)

Le brief demande de finaliser, pas de refaire. Plusieurs briques prioritaires
sont **déjà présentes** :

- **Reset PIN** : routes `POST /auth/forgot-pin` et `POST /auth/reset-pin`
  implémentées (OTP à 6 chiffres, expiration 10 min, usage unique, invalidation
  des sessions). **À durcir, pas à créer** — voir §2.
- **Temps réel** : Reverb + broadcasting fonctionnels, prouvés de bout en bout
  en Phase R (Flutter → API → file → Reverb → client). Le web consomme déjà via
  Laravel Echo. Cloisonnement vérifié (403 cross-tenant).
- **Rate limiting** : présent sur `request-otp` (5/min) et `verify-otp` (10/min).
- **Audit log**, **RBAC** (matrice `Permissions.php`), modules HSSE.

---

## 2. Défauts de sécurité trouvés (PHASE B)

### 2.1 Énumération des comptes — CONFIRMÉ

`forgotPin` et `resetPin` renvoient **des réponses différentes** selon que le
compte existe ou non :

- inconnu → « Si cet email existe, un code vous sera envoyé. » (générique, bon)
- **connu** → « Code de réinitialisation envoyé à {email}. » (révèle l'existence)
- connu mais tenant bloqué → message 403 spécifique (révèle l'existence)
- `resetPin` : « Compte introuvable » (404) vs succès (révèle l'existence)

Le brief (§9) exige une réponse **générique et constante**. À corriger.

### 2.2 Rate limiting manquant sur les routes sensibles

`login`, `forgot-pin`, `reset-pin` **ne portent aucun `throttle`**. Risque de
force brute sur le PIN et d'abus d'envoi d'emails. Le brief (§9, §21) l'exige.

### 2.3 Validation du PIN trop faible

`new_pin` : `min:4` seulement. Aucune interdiction des valeurs triviales
(`0000`, `1234`, `1111`). Le brief (§10) demande de les empêcher.

---

## 3. Email / Resend (PHASE C)

- `MAIL_MAILER=log` : **aucun email n'est réellement envoyé** aujourd'hui.
- **Resend n'est PAS installé** (absent de `composer.json`).
- `.env` contient des identifiants Gmail SMTP en clair (non commité, mais à
  retirer au profit de Resend).
- Classes Mail existantes : `OtpMail`, `ActivationMail` (utiles) —
  `OrderConfirmationMail`, `PaymentReceivedMail` (**billing SaaS, à retirer**).

Le flux reset PIN utilise un **code OTP**, pas un lien. Le brief (§8) décrit un
lien ; le code est une approche légitime pour un système matricule/PIN. À
**valider avec vous** avant de changer de mécanisme.

---

## 4. Périmètre SaaS à retirer (PHASE B)

Le brief acte : Operix n'est plus multi-tenant SaaS, c'est interne TCN. À
désactiver **si et seulement si** cela ne sert plus TCN :

- Mails `OrderConfirmationMail`, `PaymentReceivedMail`.
- Éventuels contrôleurs/routes `superadmin` de facturation/abonnement.

⚠️ **Point à trancher avec vous — le cloisonnement multi-tenant lui-même.**
Toute la base, les modèles (`BelongsToTenant`, `TenantScope`) et 126 tests sont
bâtis dessus, et **Demo Company** existe en base. Retirer l'isolation est un
chantier lourd et risqué. Deux lectures possibles :

1. **Garder l'architecture tenant**, ne servir qu'un client (TCN) — coût quasi
   nul, aucun risque, le SaaS commercial est simplement désactivé.
2. **Arracher le multi-tenant** — réécriture profonde, régression probable.

Recommandation : option 1. La demande « plus multi-tenant » se satisfait en
retirant le **commercial** (billing, marketplace), pas l'**isolation des
données**, qui est une garantie de sécurité, pas une fonctionnalité SaaS.

---

## 5. Infrastructure / O2Switch (PHASE F — à auditer finement)

- `QUEUE_CONNECTION=database`, `CACHE_STORE=database` : bon choix pour un
  hébergement mutualisé sans Redis persistant garanti.
- `SESSION_DRIVER=file`.
- **Reverb sur O2Switch** : à vérifier réellement (processus persistant, port
  WebSocket). Le brief (§25) interdit de bricoler ; si O2Switch ne permet pas un
  processus long, prévoir un serveur WebSocket externe. **Non tranché — à
  auditer en PHASE F.**

---

## 6. Ce qui RESTE à auditer (non fait dans cette passe)

Honnêteté : cette passe a couvert l'authentification, le reset PIN, le mail, le
temps réel et le périmètre SaaS. **N'ont pas encore été audités en profondeur** :

- Performance backend : N+1, eager loading, index PostgreSQL (§17-18).
- Performance frontend : lazy loading des routes, chunks Vite (§16).
- Sécurité large : IDOR sur chaque ressource, upload de fichiers, headers (§21).
- Dashboard : requêtes redondantes (§19).
- Build de production web complet et déploiement O2Switch (§23-25).

---

## 7. Plan d'exécution proposé (ordre du brief)

| Phase | Contenu | Risque |
|---|---|---|
| **B** | Réponse générique reset PIN, rate limiting, validation PIN, retrait billing | faible |
| **C** | Intégrer Resend, envoyer réellement l'email, gérer ses erreurs | moyen |
| **D** | Vérifier/compléter les évènements temps réel web (déjà en place) | faible |
| **E** | Performance backend (N+1, index) et frontend (lazy) | moyen |
| **F** | Production O2Switch : `.env`, HTTPS, WebSocket, build | à cadrer |
| **G** | Tests fonctionnels multi-utilisateurs | faible |
| **H** | Documentation + `PRODUCTION_CHECKLIST.md` | faible |

Chaque bloc se termine par `php artisan test` (126 verts) + build web.
