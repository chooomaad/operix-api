# Operix — mise en route du temps réel

## Trois processus, pas un

En développement comme en production, le temps réel exige **trois processus
distincts** en plus de PostgreSQL :

```
php artisan serve          # API HTTP
php artisan reverb:start   # serveur WebSocket (port 8080)
php artisan queue:work     # traitement des diffusions et des tâches de fond
```

**Sans le worker de file d'attente, rien n'est diffusé.** `NotificationSent`
implémente `ShouldBroadcast` : l'évènement est mis en file pour que la réponse HTTP
reste rapide, conformément au principe « la création d'un incident ne doit pas
attendre les traitements secondaires ». C'est la contrepartie : quelqu'un doit
dépiler.

Symptôme d'un worker absent : l'API répond `201`, la notification apparaît en base,
mais aucun client ne reçoit rien. Vérifier alors :

```bash
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

Une valeur qui monte sans redescendre signifie qu'aucun worker ne tourne.

## Configuration

| Clé | Valeur | Effet si mal réglée |
|---|---|---|
| `BROADCAST_CONNECTION` | `reverb` | `log` écrit les évènements dans un fichier au lieu de les émettre |
| `QUEUE_CONNECTION` | `database` | `sync` exécute tout dans la requête et la ralentit |
| `CACHE_STORE` | `database` | — |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` | propres au projet | connexion refusée |

## Redis n'est pas nécessaire

Reverb fonctionne en autonome, et les tables `jobs`, `job_batches`, `failed_jobs` et
`cache` existent déjà en PostgreSQL.

Redis devient utile pour la **montée en charge** : plusieurs nœuds Reverb qui doivent
partager leurs abonnements, ou un cache partagé entre plusieurs serveurs applicatifs.
Ce n'est pas un prérequis de mise en service, et l'introduire trop tôt ajoute un
composant à exploiter sans bénéfice mesurable.

## Canaux

| Canal | Autorisation | Usage |
|---|---|---|
| `user.{id}` | `$user->id === $id` | Notifications personnelles |
| `tenant.{tenantId}` | `$user->tenant_id === $tenantId` | Évènements de l'entreprise (présence, flux d'activité) |

### Point de vigilance

Le canal `user.{id}` **n'autorise que sur l'identifiant, pas sur le tenant**. C'est
correct — un utilisateur n'appartient qu'à une entreprise — mais cela signifie que
**la sécurité dépend entièrement de qui le serveur choisit comme destinataire**.

Un envoi mal filtré côté serveur pousse le message directement à l'écran d'un
utilisateur d'une autre entreprise, sans que l'autorisation de canal ne s'y oppose.
C'est exactement le défaut corrigé dans `NotificationController::store()` : le modèle
`User` ne porte pas le global scope tenant, le filtrage doit donc être explicite à
chaque requête qui sélectionne des destinataires.

**Règle** : toute requête qui construit une liste de destinataires filtre
explicitement sur `tenant_id`. Couvert par `NotificationIsolationTest`.

## Charge utile des évènements

`broadcastWith()` construit la charge utile **à la main**. Ne jamais y sérialiser un
modèle Eloquent : la sérialisation échappe au global scope et exposerait des colonnes
internes (`tenant_id`, clés étrangères, champs non destinés au client).

`NotificationIsolationTest` verrouille la liste exacte des champs diffusés.

## Vérifier que la chaîne fonctionne

```bash
# 1. Les trois processus tournent
# 2. Envoyer une notification
curl -X POST http://127.0.0.1:8000/api/v1/notifications \
  -H "Authorization: Bearer <jeton>" -H "Content-Type: application/json" \
  -d '{"title":"Test","body":"Diffusion","type":"alert"}'

# 3. Le worker doit afficher NotificationSent ... DONE
# 4. Aucun échec :
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"
```

Une diffusion vers un Reverb injoignable fait échouer le job : `failed_jobs` à zéro
après un envoi réussi prouve que l'évènement a bien atteint le serveur WebSocket.

## État actuel

| | |
|---|---|
| Diffusion activée | ✅ vérifiée de bout en bout |
| File d'attente asynchrone | ✅ `database` |
| Isolation tenant des envois | ✅ corrigée et testée par mutation |
| Client web abonné | ❌ `laravel-echo` non installé — Phase D |
| Évènements métier (incidents) | ❌ Phase C |
| Push FCM | ❌ Phase F |
