# Roadmap d'implémentation — Pressing & Restaurant

Ce document liste les fonctionnalités manquantes pour que **Pressing** et **Restaurant** deviennent des modules complets.  
Cochez chaque case au fur et à mesure de l'implémentation.

> Légende : `[x]` = terminé · `[~]` = partiel · `[ ]` = à faire

---

## Objectif

Compléter les deux modules avec les fonctionnalités métier, l'impression, les notifications, la tarification/ catalogue, la livraison, la fidélité et les rapports.

---

## Partie A — Pressing

### 1. Tarification centralisée
- [x] Créer une table `pressing_tarifs` : `service`, `article_type`, `prix_unitaire`, `shop_id`
- [~] Créer une page `pressing/tarifs` pour gérer la grille de prix (onglet dans `/pressing/admin`)
- [ ] Utiliser la grille dans le formulaire de dépôt pour pré-remplir les prix

### 2. Impression ticket / reçu
- [x] Générer un ticket dépôt avec `numero`, `client`, `articles`, `total`
- [x] Ajouter un bouton "Imprimer ticket" sur la page dépôt/suivi
- [x] Option d'impression du reçu de retrait au moment du paiement

### 3. Photos de l’article
- [x] Ajouter la table `pressing_photos` (`depot_id`, `article_id`, `chemin`, `type`)
- [~] Afficher les photos dans le suivi et le retrait (suivi ok, retrait en attente)

### 4. Notifications client
- [ ] Ajouter l'envoi SMS/WhatsApp quand le statut passe à `pret`
- [ ] Ajouter une option "Notifier par SMS" dans les paramètres client
- [ ] Log des notifications envoyées

### 5. Suivi du statut détaillé
- [x] Table `pressing_historique_statut` (`depot_id`, `ancien_statut`, `nouveau_statut`, `changed_by`, `created_at`)
- [x] Horodatage automatique à chaque changement de statut
- [x] Afficher la timeline dans le suivi

### 6. Livraison à domicile
- [x] Ajouter `adresse_livraison` et `date_retour_prevue` dans `pressing_depots`
- [~] Créer une page / vue des livraisons à effectuer (affichée dans dépôt/suivi/retrait/historique)
- [ ] Marquer un dépôt comme `livre` avec signature / preuve

### 7. Catalogue de services
- [x] Créer une table `pressing_services` (`nom`, `description`, `duree_estimee`, `shop_id`)
- [x] Page de gestion des services (`/pressing/admin`)
- [ ] Lier les articles déposés à un service du catalogue (API prêt, UI dépôt en attente)

### 8. Paiement partiel / mixte
- [x] Permettre plusieurs modes de paiement pour un même dépôt (cash, mobile, carte, etc.)
- [x] Table `pressing_paiements` liée à `pressing_depots`
- [x] Afficher l'historique des paiements et le solde restant

### 9. Rappels de retard
- [ ] Notification automatique pour les dépôts `pret` non retirés après X jours
- [ ] Page des retards avec compteur de jours
- [ ] Option d'envoi de relance SMS

### 10. Stock produits de nettoyage
- [x] Table `pressing_consommables` (`nom`, `quantite`, `stock_minimum`, `shop_id`)
- [ ] Décompter automatiquement les consommables lors du traitement
- [ ] Alerte `stock_low` dans les notifications

---

## Partie B — Restaurant

### 1. Réservations de tables
- [ ] Table `restaurant_reservations` (`table_id`, `client`, `date_heure`, `nb_personnes`, `statut`)
- [ ] Calendrier/onglet des réservations
- [ ] Bloquer une table occupée par une réservation

### 2. Commandes emporter / livraison
- [ ] Type de commande : `sur place`, `emporter`, `livraison`
- [ ] Ajouter `client_id`, `adresse_livraison`, `type` dans `restaurant_commandes`
- [ ] Page des commandes à emporter / livrer

### 3. Addition partagée (split bill)
- [ ] Permettre de séparer une commande par personne / article
- [ ] Générer plusieurs tickets partiels
- [ ] Paiement individuel par client

### 4. Transfert / fusion de tables
- [ ] Bouton "Transférer la commande" vers une autre table
- [ ] Bouton "Fusionner 2 tables" en une seule commande
- [ ] Mise à jour automatique des statuts de tables

### 5. Options / modificateurs de plats
- [ ] Table `restaurant_menu_item_options` (`menu_item_id`, `nom`, `prix_supp`)
- [ ] Gérer les options dans la page menu
- [ ] Sélection des options lors de la prise de commande

### 6. Menus / formules / happy hour
- [ ] Table `restaurant_menus` (formules : entrée + plat + dessert)
- [ ] Gestion des happy hour (plage horaire et prix réduit)
- [ ] Application automatique du tarif happy hour

### 7. Gestion des ingrédients / stock
- [ ] Table `restaurant_ingredients` et `menu_item_ingredients`
- [ ] Déduire automatiquement le stock des ingrédients à l'envoi en cuisine
- [ ] Alerte stock insuffisant avant la commande

### 8. Impression cuisine + reçu client
- [ ] Imprimer le ticket cuisine (commande par table) à l'envoi
- [ ] Imprimer le ticket caisse/client au paiement
- [ ] Paramètre d'impression automatique ou manuelle

### 9. Paiement mixte + pourboire
- [ ] Paiement en plusieurs modes pour une même commande
- [ ] Champ `pourboire` ajouté au paiement
- [ ] Répartition caisse / serveur

### 10. Service livraison / zones
- [ ] Table `restaurant_livreurs` (`nom`, `telephone`, `shop_id`)
- [ ] Table `restaurant_zones` avec frais de livraison
- [ ] Affecter un livreur à une commande et suivre l'état

### 11. QR code / commande client
- [ ] Générer un QR par table redirigeant vers une page de commande
- [ ] Page web légère pour ajouter des articles depuis son téléphone
- [ ] Synchronisation avec la commande en cours

### 12. Fidélité client
- [ ] Table `restaurant_fidelite` (`client_id`, `points`, `shop_id`)
- [ ] Règles de points (ex: 1 point par 1000 CDF dépensé)
- [ ] Utilisation des points en remise

---

## Phase transversale — Préparation

- [ ] Sauvegarder la BDD
- [x] Créer les migrations SQL pour les nouvelles tables
- [ ] Mettre à jour le schéma BDD de test
- [ ] Documenter les nouvelles routes API

---

## Phase transversale — Développement backend

- [x] Créer/modifier les modèles Pressing et Restaurant
- [x] Ajouter les contrôleurs et routes API
- [x] Sécuriser les accès par rôle et par `shop_id`
- [~] Ajouter les appels d'audit et de notifications (audit ok, notifications en attente)

---

## Phase transversale — Développement frontend

- [x] Créer / mettre à jour les vues PHP
- [x] Ajouter les nouveaux menus dans `header.php`
- [x] Adapter le JavaScript pour les nouvelles fonctionnalités
- [~] Gérer le responsive mobile

---

## Phase transversale — Tests & déploiement

- [ ] Tester chaque nouvelle fonctionnalité individuellement
- [ ] Tester le flux complet pressing (dépôt → paiement → retrait)
- [ ] Tester le flux complet restaurant (commande → cuisine → paiement)
- [ ] Mettre à jour les manuels utilisateur
- [ ] Déployer en production

---

*Ce README est une roadmap cliquable. Remplacez `[ ]` par `[x]` à chaque étape validée.*
