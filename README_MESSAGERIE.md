# Système de messagerie admin/vendeur : pourquoi JSON et notre stack ne conviennent pas

## Contexte

Le projet est un **système POS (Point de Vente) en PHP pur** (sans framework), avec une interface frontend en JavaScript vanilla, une base de données MySQL/MariaDB et une architecture classique **request/response HTTP**.

La demande consiste à ajouter un **système de messagerie interne entre administrateurs et vendeurs**.

Ce document explique pourquoi **stocker les messages dans un fichier JSON** (ou toute autre approche "JSON-only") est une mauvaise idée, et pourquoi notre technologie actuelle n’est pas adaptée à un vrai système de messagerie.

---

## 1. Pourquoi le stockage JSON est une mauvaise idée

### 1.1 Problèmes de concurrence et de corruption

Un fichier JSON est un fichier texte. Quand deux utilisateurs (admin + vendeur) écrivent en même temps :

- **Lecture/écriture simultanée** : PHP n’a pas de mécanisme natif de file locking robuste sur tous les environnements (Laragon, hébergement mutualisé, etc.).
- **Risque de corruption** : si deux scripts PHP ouvrent le fichier en même temps, le dernier écrasera les modifications du premier. Résultat : messages perdus.
- **Pas de transactions** : impossible de garantir l’intégrité des données (ACID). Aucun rollback en cas d’erreur.

### 1.2 Pas de requêtes efficaces

Avec JSON, pour :

- Lister les messages non lus d’un vendeur → on doit charger **tout** le fichier et filtrer en PHP.
- Rechercher un message → on doit parser tout le fichier.
- Paginer l’historique → on charge tout, puis on tranche en PHP.
- Compter les messages non lus → on parcourt tout le fichier.

À quelques centaines de messages, cela devient très lent.

### 1.3 Taille et performances

- Le fichier JSON grossit à chaque message.
- Chaque chargement de page de messagerie lit l’intégralité du fichier en mémoire.
- Risque de dépasser la limite `memory_limit` de PHP.

### 1.4 Pas de relations de données

Pour afficher un message, on a besoin de lier :

- l’expéditeur (`utilisateurs.id`)
- le destinataire (`utilisateurs.id`)
- la boutique (`shops.id`) pour limiter la portée
- le statut (lu/non lu)
- la date/heure

Avec JSON, ces relations sont gérées à la main, ce qui est source d’erreurs et de données incohérentes.

---

## 2. Pourquoi notre stack technique actuelle n’est pas adaptée

### 2.1 PHP classique = pas de temps réel

Le projet utilise PHP en mode **request/response** classique :

- Le client envoie une requête.
- PHP exécute le script.
- Le serveur renvoie une réponse.
- La connexion se ferme.

Un système de messagerie a besoin de **réception instantanée** de nouveaux messages. Sans WebSockets, SSE (Server-Sent Events) ou un service tiers (Pusher, Firebase, etc.), on ne peut pas faire de vrai temps réel.

### 2.2 JavaScript vanilla sans framework

L’application n’utilise pas React, Vue, Angular ou autre framework SPA. Les pages sont rechargées côté serveur. Pour simuler le temps réel, il faudrait :

- un `setInterval` qui interroge le serveur toutes les X secondes (**polling**)
- ou une connexion SSE maintenue ouverte

Le polling consomme des ressources serveur et crée beaucoup de requêtes inutiles.

### 2.3 MySQL n’est pas le problème, mais l’absence d’architecture l’est

MySQL peut très bien stocker des messages. Le vrai problème n’est pas la base de données, c’est :

- l’absence de mécanisme de **push** temps réel
- l’absence de **file d’attente** de messages
- l’absence de **gestion de connexions persistantes**
- l’absence de **sockets/WebSockets**

### 2.4 Hébergement et environnement

Le projet tourne visiblement sur **Laragon en local** et probablement sur un hébergement mutualisé en production. Or :

- Les hébergements mutualisés bloquent souvent les WebSockets et les connexions longues.
- Laragon n’a pas de service Node/WebSocket prêt à l’emploi.
- Installer Redis, RabbitMQ ou un broker de messages demande de nouvelles compétences et une infrastructure.

---

## 3. Ce que cela implique concrètement

### 3.1 Expérience utilisateur médiocre

- Les vendeurs ne voient pas les messages arriver instantanément.
- Ils doivent rafraîchir la page ou attendre le polling.
- Les notifications seront décalées ou absentes.
- Risque de messages dupliqués ou perdus.

### 3.2 Problèmes de fiabilité

- Perte de messages en cas d’écriture concurrente.
- Fichier JSON corrompu si une écriture est interrompue.
- Pas de garantie de livraison.
- Aucun historique fiable et performant.

### 3.3 Scalabilité nulle

- Dès que le nombre de messages augmente, le système devient lent.
- Dès qu’il y a plusieurs vendeurs connectés, les conflits d’écriture augmentent.
- Impossible de faire évoluer vers une app mobile plus tard.

---

## 4. Que faire à la place ?

### 4.1 Si l’on veut une messagerie simple (recommandé minimal)

**Solution : table MySQL + polling JavaScript**

1. Créer une table `messages` :

```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    shop_id INT,
    subject VARCHAR(255),
    content TEXT,
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (receiver_id) REFERENCES utilisateurs(id)
);
```

2. Créer des endpoints PHP (`/api/messages/send`, `/api/messages/list`, `/api/messages/mark-read`).
3. Utiliser JavaScript pour :
   - envoyer un message en AJAX
   - rafraîchir la liste toutes les 10-30 secondes (polling)
   - afficher un compteur de non lus

C’est simple, fiable, et compatible avec la stack actuelle.

### 4.2 Si l’on veut du temps réel

Il faut ajouter une technologie de push :

| Technologie | Avantage | Inconvénient |
|---|---|---|
| **WebSockets (Node.js + Socket.io)** | Vrai temps réel | Nécessite un serveur Node séparé |
| **Server-Sent Events (SSE)** | Unidirectionnel, simple | Connexion longue, peut poser problème sur mutualisé |
| **Pusher / Ably / Firebase** | Service clé en main | Coût, dépendance externe |
| **Redis Pub/Sub** | Très performant | Nécessite Redis et un serveur Node |

Ces solutions sortent du scope d’un **PHP pur sans framework**.

---

## 5. Pourquoi JSON n’est JAMAIS une bonne base de données

JSON est un **format d’échange de données**, pas un système de stockage.

| Critère | JSON fichier | Base de données (MySQL) |
|---|---|---|
| Concurrence | Risque de corruption | Verrouillage et transactions |
| Recherche | Lente et manuelle | Indexée, rapide |
| Volume | Tout chargé en mémoire | Requêtes paginées |
| Sécurité | Facilement modifiable/corruptible | Permissions, backups |
| Évolutivité | Nulle | Bonne |
| Relations | Gérées à la main | Clés étrangères |

---

## 6. Conclusion

**Stocker les messages entre admin et vendeurs dans un fichier JSON est à proscrire** car cela mène rapidement à :

- des pertes de données
- des performances catastrophiques
- une expérience utilisateur dégradée
- une impossibilité d’évolution

Avec notre stack actuelle (PHP pur + JS vanilla + MySQL), la meilleure approche raisonnable est :

> **une table MySQL `messages` + une interface frontend avec envoi AJAX + un polling léger**.

Si le besoin de vrai temps réel devient critique, il faudra intégrer des technologies dédiées (WebSockets, SSE, services tiers), ce qui représente un changement d’architecture important.

---

## 7. Pourquoi MySQL n’est pas non plus une solution miracle pour la messagerie

### 7.1 La base de données va gonfler très vite

Chaque message = une ligne. Avec seulement **50 vendeurs** et un admin actif :

- 10 messages par jour et par vendeur = 500 messages/jour
- 1 mois = **15 000 messages**
- 1 an = **182 500 messages**

Et cela ne compte pas :

- les messages de notification automatique
- les fichiers joints
- les messages groupés (admin vers plusieurs vendeurs)
- les messages de récupération de mot de passe (déjà gérés par `otp_codes` et `password_resets`)

MySQL peut stocker ces lignes, mais :

- les requêtes deviennent lentes si la table n’est pas indexée correctement
- les sauvegardes de la BDD deviennent lourdes
- le temps de restauration augmente
- les sauvegardes/exports prennent beaucoup de place

### 7.2 Le cas des 50 utilisateurs

Avec 50 vendeurs + 1 admin = **51 utilisateurs**, chacun peut envoyer des messages à chacun.

- 51 expéditeurs × 50 destinataires = **2 550 combinaisons possibles**
- si chaque vendeur envoie seulement 2 messages par jour : 100 messages/jour
- en un mois : 3 000 messages à gérer
- en un an : 36 500 messages

Et si l’admin envoie un message à tous les vendeurs, c’est 50 insertions d’un coup. Plusieurs messages par jour et la table explose.

### 7.3 Les emojis et icônes dans MySQL

Les messages contiendront des emojis (✅, 🔔, 🚀, 😊, etc.). Cela pose plusieurs problèmes :

- **Encodage** : la base doit être en `utf8mb4`, pas seulement `utf8`. Si elle est en `utf8` standard, les emojis seront rejetés ou remplacés par `????`.
- **Taille des colonnes** : un emoji compte pour 4 octets minimum. Un texte avec beaucoup d’emojis prend plus d’espace qu’un texte normal.
- **Indexation** : certains caractères spéciaux/émojis compliquent les recherches full-text.
- **Copies de sécurité** : exporter/importer des données `utf8mb4` demande une configuration cohérente. Une mauvaise config et les messages deviennent illisibles.
- **Compatibilité PHP** : `json_encode`/`json_decode` doit gérer `JSON_UNESCAPED_UNICODE` pour ne pas transformer les emojis en caractères échappés (`\uXXXX`).

### 7.4 Le polling détruit les performances de la BDD

Si chaque vendeur rafraîchit ses messages toutes les 5 secondes :

- 50 vendeurs × 12 requêtes/minute = **600 requêtes/minute**
- 8 heures de caisse = **28 800 requêtes/jour**
- chaque requête doit vérifier s’il y a de nouveaux messages

Sans cache Redis ou mémoire partagée, ces requêtes frappent MySQL en permanence. Cela ralentit le reste du POS (ventes, produits, caisse).

### 7.5 Pas de statut "en ligne" / "envoyé" / "reçu"

Un vrai système de messagerie doit savoir :

- si le destinataire est connecté
- si le message a été envoyé
- si le message a été reçu
- si le message a été lu
- si le destinataire est en train d’écrire

Avec MySQL + polling, on peut connaître le statut "lu" (`is_read = 1`). Mais tout le reste est approximatif ou impossible :

- "connecté" → non, il faut une connexion persistante (WebSocket)
- "en train d’écrire" → non
- "reçu en temps réel" → non

### 7.6 Gestion des pièces jointes

Si les vendeurs envoient des photos de tickets, factures, captures d’écran :

- stocker l’image en BLOB dans MySQL = **mauvaise pratique** (la BDD devient énorme et lente)
- stocker un chemin vers un fichier = ok, mais il faut gérer l’upload, le stockage, les doublons, la sécurité
- les pièces jointes multiplient l’espace disque utilisé

### 7.7 Archivage et purge nécessaires

À 36 500 messages par an, il faut obligatoirement :

- une politique d’archivage (messages de plus de 3 mois déplacés ailleurs)
- une purge régulière
- une table d’historique (`messages_archive`)
- des scripts de maintenance (cron)

Sinon, la table messages ralentit jusqu’à rendre le système inutilisable.

### 7.8 Conclusion sur MySQL

MySQL peut stocker des messages, mais **ce n’est pas conçu pour de la messagerie instantanée**. Le stockage MySQL + le polling HTTP crée :

- une base de données qui gonfle
- des requêtes incessantes
- une charge serveur élevée
- une UX dégradée (pas de temps réel)
- une maintenance lourde (archivage, purge, encodage)

Pour un POS, l’administrateur n’a pas besoin d’un chat interne. Une simple **notification par email/SMS** ou un tableau de bord d’alertes est largement suffisant.
