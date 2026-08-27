# Operix — Audit avant industrialisation temps réel

**Date** : 2026-08-27 · **Périmètre** : `operix-api`, `operix-web`, `operix-mobile`
**Aucun code modifié.** Ce document précède l'implémentation, conformément au §18 du brief.

---

## 1. Contradiction à lever avant tout

Le brief indique :

> Mobile : **React Native / Expo**

**L'application mobile existante est en Flutter/Dart**, livrée en Phase 1 et validée :
83 tests verts, APK debug et AAB release qui compilent, 17 commits atomiques sur
`phase-4-mobile-hse`.

Réécrire en React Native signifierait jeter :

- l'`ApiClient` et sa typologie d'erreurs (10 codes HTTP couverts) ;
- l'authentification par jeton de plateforme (`platform: "mobile"`) ;
- la biométrie, y compris le durcissement classe forte trouvé en lisant le code natif ;
- la localisation FR/EN/AR avec RTL ;
- le routage à gardes de permissions ;
- l'intégralité des tests.

Soit environ deux phases complètes de travail, pour un résultat fonctionnellement
identique.

**Ce point est bloquant** : tout le reste du plan en dépend. Voir §7.

---

## 2. Versions réelles

| Élément | Attendu au brief | Réel | Verdict |
|---|---|---|---|
| PHP | 8.4 | **8.4.8** | ✅ |
| Laravel | 13 | **13.17.0** | ✅ |
| PostgreSQL | oui | 17 | ✅ |
| Vue / Vite | oui | Vue 3 + Vite | ✅ |
| Mobile | React Native / Expo | **Flutter 3.29.2** | ❌ voir §1 |

La stack backend correspond exactement. Rien à remplacer.

---

## 3. Temps réel : échafaudé, mais éteint

C'est le constat le plus utile de cet audit. **L'infrastructure existe déjà**, elle
n'est simplement pas activée ni câblée.

### Ce qui est déjà en place

| Élément | État |
|---|---|
| `laravel/reverb` | Installé (`composer.json`) |
| Identifiants Reverb | Renseignés dans `.env` (app id, clé, secret, port 8080) |
| `routes/channels.php` | **Canaux corrects et isolés par tenant** |
| `app/Events/NotificationSent.php` | Implémente `ShouldBroadcast` |

Les canaux méritent d'être soulignés — ils sont déjà justes :

```php
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    if ((int) $user->tenant_id === (int) $tenantId) { ... }
    return false;
});
```

L'autorisation de canal compare le tenant de l'utilisateur authentifié. Un abonnement
au canal d'une autre entreprise est refusé. **L'isolation multi-tenant du temps réel
est donc déjà correcte** — c'est la partie la plus facile à rater.

### Ce qui bloque

| Constat | Conséquence |
|---|---|
| `BROADCAST_CONNECTION=log` | Les diffusions partent **dans un fichier de log**, pas vers Reverb |
| `QUEUE_CONNECTION=sync` | Les traitements s'exécutent **dans la requête** : une notification lente ralentit la création d'incident |
| `CACHE_STORE=file` | Redis n'est **pas utilisé**, malgré sa configuration |
| `NotificationSent` | **Jamais dispatché** — code mort |
| Redis | **Non joignable** sur 6379 (non installé ou arrêté) |
| Reverb | **Non démarré** |
| Web : `laravel-echo` / `pusher-js` | **Absents** de `package.json` |

**Conclusion** : passer au temps réel ne demande pas de construire l'infrastructure,
mais de l'allumer, de la câbler et d'installer Redis. C'est considérablement moins
lourd que ce que le brief laisse supposer.

---

## 4. Modules HSE : le découpage du brief ne correspond pas à la base

Le §10 du brief liste huit modules :

> Incident · Near Miss · First Aid · Environment · FIRE · LTI · MTC · RWC

La base contient en réalité **trois entités**, et cinq de ces « modules » sont des
**valeurs du champ `type` des incidents** :

```
safety_incidents.type ∈ { LTI, MTC, RWC, FAC, HPI, Fire, Security, Autre }
                          └──┴────┴────┴────┴──── les « modules » du brief
safety_near_miss        → table distincte
environment_reports     → table distincte
```

Construire huit modules dupliquerait cinq fois la même table, les mêmes règles et les
mêmes écrans, sans rien apporter. Un incendie et un accident avec arrêt sont deux
qualifications du même évènement, pas deux processus.

**Recommandation** : conserver trois entités et traiter les types comme un filtre.
« FIRE » devient `GET /incidents?type=Fire`. Le résultat visible pour l'utilisateur
est identique — un écran par catégorie — sans duplication de code.

L'abstraction « HSE Event » demandée au §10 reste pertinente, mais elle doit
factoriser **ce qui est réellement commun aux trois entités** : auteur, date,
localisation, gravité, statut, pièces jointes, historique.

---

## 5. Ce qui manque réellement

| Besoin | État | Effort |
|---|---|---|
| Colonnes GPS (`latitude`, `longitude`, `accuracy`, `captured_at`) | **Absentes des trois tables** | Migration + validation + resource |
| Enregistrement des jetons d'appareil (FCM) | **Aucune table, aucun endpoint** | Migration + endpoints + service d'envoi |
| Envoi push FCM | **Aucune intégration** | Service + file d'attente |
| Client temps réel web | **Aucun** | `laravel-echo` + `pusher-js` + composable |
| Cartographie web | **Aucune bibliothèque** | Leaflet (libre, sans clé API) |
| Workflow à 6 états | Base actuelle : `open`, `in_progress`, `closed` | Migration avec remappage |
| Redis | **Non installé / arrêté** | Installation serveur |
| File d'attente asynchrone | `sync` | Bascule + worker |

### Détail : le workflow

Le brief demande `NEW → ACKNOWLEDGED → UNDER_INVESTIGATION → ACTION_REQUIRED →
RESOLVED → CLOSED`.

La contrainte PostgreSQL actuelle n'autorise que trois valeurs. Le passage à six
impose une migration avec remappage des données existantes — le même type d'opération
que l'unification du vocabulaire `type` en Phase 1a, avec les mêmes précautions :
aucune perte, réversible, testée.

---

## 6. Sécurité : rien à refaire

Vérifié lors des phases précédentes et toujours valable :

- isolation multi-tenant par global scope *fail-closed* ;
- RBAC à 43 permissions, 40 routes gardées par `permission:` ;
- fichiers privés avec URLs signées ;
- PII employés restreintes par permission ;
- jetons de session par plateforme.

**Le temps réel ne doit pas contourner ces règles.** Un évènement diffusé sur
`tenant.{id}` ne doit contenir que des données déjà autorisées pour ce tenant : la
charge utile d'un évènement échappe au global scope Eloquent, c'est le point de
vigilance principal de ce chantier.

---

## 7. Ordre d'implémentation proposé

L'ordre suit celui du brief (§19), corrigé de ce que l'audit a montré.

### Phase A — Allumer l'existant (faible effort, effet immédiat)

1. Installer et démarrer Redis.
2. `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.
3. Démarrer un worker de file d'attente et Reverb.
4. Vérifier qu'une diffusion atteint réellement un client abonné.

Rien de neuf n'est écrit : on branche ce qui existe.

### Phase B — Géolocalisation

5. Migration : `latitude`, `longitude`, `accuracy`, `captured_at` sur les trois tables.
6. Validation serveur : coordonnées cohérentes, `accuracy` plafonnée, refus d'une
   position fabriquée. Aucune fausse position enregistrée — le champ reste nul si le
   GPS a échoué.
7. Exposition dans les resources.

### Phase C — Évènements métier et diffusion

8. Un évènement par création/changement de statut, diffusé sur `tenant.{id}`.
9. Charge utile **explicitement construite**, jamais un modèle sérialisé tel quel.
10. Tests d'isolation : un abonné du tenant B ne reçoit rien du tenant A.

### Phase D — Web temps réel

11. `laravel-echo` + `pusher-js`, composable d'abonnement.
12. Tableau de bord : compteurs, liste, activité récente mis à jour sans rechargement.
13. Cloche de notifications, compteur non lu, marquage lu.

### Phase E — Mobile : signalement rapide + GPS

14. Formulaire court, GPS automatique, photo optionnelle, compression avant envoi.
15. Clé d'idempotence à la création pour qu'un rejeu réseau ne crée pas de doublon.

### Phase F — Notifications push

16. Table `device_tokens` scopée tenant, endpoints d'enregistrement et de retrait.
17. Service d'envoi FCM en file d'attente, purge des jetons invalides.

### Phase G — Carte

18. Leaflet côté web, marqueurs depuis les coordonnées, filtres type/gravité/statut/période.

### Phase H — Workflow

19. Migration des statuts, transitions gardées par permission, journal d'audit.

### Phase I — Mode réseau faible (mobile)

20. File locale des signalements non envoyés, synchronisation à la reconnexion,
    déduplication par clé d'idempotence.

---

## 8. Décisions attendues avant de commencer

1. **Flutter ou React Native** — bloquant, voir §1.
2. **Trois entités ou huit modules** — voir §4.
3. **Migration du workflow à six états** — maintenant ou après la mise en service ?
4. **Redis** — installation locale, ou service managé si un hébergement est déjà choisi ?

Tant que le point 1 n'est pas tranché, toute implémentation mobile risque d'être jetée.
