# Manuel de Contrôle et Supervision - POS System

> **Document à destination des responsables, gérants et superviseurs**  
> Ce guide explique comment surveiller, contrôler et superviser l'activité du système de caisse. Aucune connaissance technique approfondie n'est requise.

---

## Table des matières

1. [Rôle du superviseur](#rôle-du-superviseur)
2. [Accès et connexion](#accès-et-connexion)
3. [Tableau de bord : les indicateurs clés](#tableau-de-bord--les-indicateurs-clés)
4. [Contrôler les ventes](#contrôler-les-ventes)
5. [Vérifier les stocks](#vérifier-les-stocks)
6. [Suivre les utilisateurs et les vendeurs](#suivre-les-utilisateurs-et-les-vendeurs)
7. [Contrôler les factures et les reçus](#contrôler-les-factures-et-les-reçus)
8. [Superviser les recharges SNEL / REGIDESO](#superviser-les-recharges-snel--regideso)
9. [Rapports et analytics](#rapports-et-analytics)
10. [Clôture de journée](#clôture-de-journée)
11. [Sauvegardes et sécurité](#sauvegardes-et-sécurité)
12. [Checklists de supervision](#checklists-de-supervision)
13. [Problèmes et actions correctives](#problèmes-et-actions-correctives)
14. [Glossaire](#glossaire)

---

## Rôle du superviseur

Le superviseur (ou responsable) a pour mission de s'assurer que :

- Les ventes sont correctement enregistrées
- Les stocks correspondent à la réalité
- Les vendeurs respectent les procédures
- Les paiements sont suivis et justifiés
- Les recharges SNEL/REGIDESO sont bien payées et tracées
- Les sauvegardes sont régulières
- Le système fonctionne sans problème technique

> Le superviseur doit avoir un compte avec le rôle **admin**.

---

## Accès et connexion

1. Connectez-vous avec votre nom d'utilisateur et mot de passe.
2. Vérifiez que votre rôle est bien **admin** (en haut à droite de l'écran ou dans les paramètres).
3. Si vous ne voyez pas les menus **Utilisateurs**, **Catégories**, **Taxes**, **Paramètres** ou **Analytics**, demandez la création d'un compte administrateur.

---

## Tableau de bord : les indicateurs clés

Le tableau de bord affiche les chiffres essentiels à surveiller :

| Indicateur | À quoi il sert | Ce qu'il faut vérifier |
|------------|---------------|------------------------|
| **Ventes du jour** | Total des ventes de la journée | Comparer avec le cash en caisse |
| **Ventes de la semaine** | Total sur 7 jours | Identifier les tendances |
| **Ventes du mois** | Total sur le mois en cours | Suivre les objectifs |
| **Nombre de ventes** | Quantité de transactions | Évaluer l'activité |
| **Panier moyen** | Montant moyen par vente | Détecter les baisses ou hausses |
| **Produits en stock faible** | Liste des articles à réapprovisionner | Vérifier l'approvisionnement |
| **Meilleurs produits** | Articles les plus vendus | Adapter les commandes |
| **Meilleurs vendeurs** | Classement des vendeurs | Suivre les performances |

> Si un chiffre semble anormal (très bas, très haut, ou nul), cliquez dessus pour voir le détail.

---

## Contrôler les ventes

### 1. Consulter l'historique

1. Cliquez sur **Historique** dans le menu.
2. Vous voyez toutes les ventes avec :
   - Numéro de facture
   - Date et heure
   - Vendeur
   - Montant total
   - Type de vente (produit ou recharge)

### 2. Vérifier une vente

1. Cliquez sur une facture dans la liste.
2. Vérifiez :
   - Les articles vendus
   - Les quantités
   - Les prix
   - Le mode de paiement
   - Le vendeur
   - La présence ou non d'un client

### 3. Rechercher une vente

Utilisez la barre de recherche pour trouver une facture par :
- Numéro de facture
- Nom du vendeur
- Date
- Nom du client

### 4. Annuler ou supprimer une vente

> Cette action est réservée au superviseur.

1. Dans l'historique, trouvez la vente concernée.
2. Cliquez sur **Supprimer** ou **Annuler**.
3. Confirmez l'action.
4. Le stock des produits concernés est automatiquement rétabli.

> **Important** : notez toujours la raison de l'annulation dans un registre papier ou numérique.

---

## Vérifier les stocks

### 1. Voir les stocks faibles

1. Allez dans **Tableau de bord** ou **Analytics**.
2. Consultez la section **Produits en stock faible**.
3. Cette liste indique les articles dont la quantité est inférieure au seuil minimum.

### 2. Vérifier un produit spécifique

1. Allez dans **Produits**.
2. Utilisez la recherche pour trouver le produit.
3. Vérifiez la colonne **Stock**.

### 3. Corriger un stock incorrect

1. Allez dans **Produits**.
2. Cliquez sur **Modifier** à côté du produit.
3. Corrigez la quantité dans le champ **Stock**.
4. Cliquez sur **Enregistrer**.

> Pensez à documenter les raisons d'une correction manuelle (inventaire, casse, erreur de saisie...).

### 4. Faire un inventaire

1. Imprimez ou exportez la liste des produits et de leurs stocks.
2. Comptez physiquement les articles en rayon et en réserve.
3. Comparez les quantités réelles avec le système.
4. Corrigez les écarts dans **Produits**.

> Il est recommandé de faire un inventaire partiel chaque semaine et complet chaque mois.

---

## Suivre les utilisateurs et les vendeurs

### 1. Lister les utilisateurs

1. Cliquez sur **Utilisateurs**.
2. Vous voyez la liste des comptes avec :
   - Nom d'utilisateur
   - Nom complet
   - Rôle (admin ou vendeur)
   - État (actif ou inactif)
   - Code agent

### 2. Vérifier l'activité d'un vendeur

1. Allez dans **Analytics**.
2. Consultez le classement des vendeurs.
3. Vous pouvez filtrer par période (jour, semaine, mois).
4. Comparez les chiffres de chaque vendeur.

### 3. Ajouter un vendeur

1. Allez dans **Utilisateurs**.
2. Cliquez sur **Ajouter**.
3. Remplissez :
   - Nom d'utilisateur (pour se connecter)
   - Mot de passe temporaire
   - Nom complet
   - Rôle : **vendeur**
   - Code agent (optionnel)
4. Cliquez sur **Enregistrer**.
5. Donnez le mot de passe au vendeur et demandez-lui de le changer s'il le souhaite.

### 4. Désactiver un compte

1. Allez dans **Utilisateurs**.
2. Cliquez sur **Modifier** à côté du compte.
3. Changez le champ **Actif** à **0** ou décochez la case.
4. Cliquez sur **Enregistrer**.

> Désactivez immédiatement le compte d'un employé qui quitte l'entreprise.

---

## Contrôler les factures et les reçus

### 1. Vérifier la numérotation

1. Allez dans **Historique**.
2. Vérifiez que les numéros de facture se suivent sans trou.
3. Si un numéro manque, recherchez la facture par date ou par vendeur.

### 2. Imprimer une facture en retard

1. Allez dans **Historique**.
2. Trouvez la vente.
3. Cliquez sur **Imprimer** ou **Voir la facture**.
4. Imprimez le reçu pour le client.

### 3. Envoyer une facture au client

1. Ouvrez la facture.
2. Cliquez sur **Envoyer**.
3. Choisissez **WhatsApp** ou **SMS**.
4. Confirmez le numéro de téléphone.
5. Cliquez sur **Envoyer**.

### 4. Vérifier les paiements fractionnés

1. Ouvrez une vente payée en plusieurs modes.
2. Vérifiez dans le détail :
   - Montant en espèces
   - Montant Mobile Money
   - Montant carte bancaire
3. Assurez-vous que le total correspond au montant encaissé.

---

## Superviser les recharges SNEL / REGIDESO

### 1. Voir les paiements de factures

1. Allez dans **Historique**.
2. Filtrez les ventes par type **Recharge** ou **Bill Payment** si le filtre existe.
3. Vous voyez les paiements avec le numéro de compteur, le fournisseur et le montant.

### 2. Vérifier un paiement

1. Cliquez sur la recharge concernée.
2. Vérifiez :
   - Le fournisseur (SNEL ou REGIDESO)
   - Le numéro de compteur
   - Les mois payés
   - Le montant total
   - Le vendeur qui a effectué l'opération

### 3. Contrôler les reçus de recharge

1. Ouvrez la recharge.
2. Cliquez sur **Imprimer**.
3. Vérifiez que le reçu mentionne bien le compteur et le montant.
4. Remettez le reçu au client.

### 4. Recouvrement et conciliation

Chaque jour, comparez :
- Le montant total des recharges payées dans le système
- L'argent reçu en caisse ou via Mobile Money
- Les confirmations de paiement du fournisseur

> En cas d'écart, vérifiez immédiatement la transaction avec le vendeur concerné.

---

## Rapports et analytics

### 1. Ouvrir Analytics

1. Cliquez sur **Analytics** dans le menu.
2. Vous voyez plusieurs rapports :
   - Ventes par jour / semaine / mois
   - Ventes par vendeur
   - Ventes par produit
   - Produits les plus vendus
   - Stocks faibles
   - Évolution des revenus

### 2. Filtrer par période

Utilisez les champs de date pour choisir une période précise.

### 3. Comprendre les chiffres

| Terme | Signification |
|-------|---------------|
| **Total TTC** | Montant final payé par le client (taxes incluses) |
| **Total HT** | Montant avant taxes |
| **TVA** | Montant des taxes collectées |
| **Panier moyen** | Total des ventes divisé par le nombre de ventes |
| **Marge** | Différence entre prix de vente et coût d'achat (si saisie) |

### 4. Exporter les données

Si l'option est disponible, utilisez le bouton **Exporter** pour télécharger un fichier Excel ou CSV. Cela facilite les analyses dans Excel ou Google Sheets.

---

## Clôture de journée

### 1. À faire en fin de journée

1. Allez dans **Historique**.
2. Filtrez les ventes du jour.
3. Notez :
   - Nombre total de ventes
   - Total encaissé en espèces
   - Total encaissé en Mobile Money
   - Total encaissé par carte
   - Total des recharges

4. Comptez le cash présent en caisse.
5. Comparez avec le total des ventes en espèces.
6. En cas d'écart, recherchez la cause (erreur de saisie, remise, fond de caisse, etc.).

### 2. Imprimer le rapport de clôture

Si le système propose un rapport de clôture :
1. Allez dans **Analytics** ou **Historique**.
2. Sélectionnez la période "aujourd'hui".
3. Cliquez sur **Imprimer** ou **Exporter**.
4. Gardez le rapport avec la caisse.

### 3. Sécuriser la caisse

- Fermez la session du vendeur.
- Éteignez ou verrouillez l'ordinateur/tablette.
- Rangez le cash dans un lieu sécurisé.

---

## Sauvegardes et sécurité

### 1. Sauvegarder la base de données

> Cette opération peut être faite par l'informaticien ou automatiquement.

1. Si vous avez accès à l'hébergement ou à phpMyAdmin, exportez régulièrement la base de données.
2. Gardez les sauvegardes dans un endroit sûr (clé USB, cloud, disque dur externe).
3. Faites au minimum une sauvegarde par semaine, et idéalement une par jour en activité.

### 2. Vérifier les connexions

1. Allez dans **Utilisateurs**.
2. Vérifiez régulièrement que tous les comptes actifs sont légitimes.
3. Désactivez les comptes inutilisés.

### 3. Surveiller les modifications anormales

Soyez vigilant si vous observez :
- Des ventes supprimées en dehors des horaires d'ouverture
- Des remises importantes ou fréquentes
- Des modifications de prix non justifiées
- Des utilisateurs créés sans autorisation
- Des stocks corrigés sans justification

En cas de doute, consultez l'historique et parlez avec le vendeur concerné.

---

## Checklists de supervision

### Checklist quotidienne

- [ ] Vérifier les ventes du jour dans le tableau de bord
- [ ] Comparer le cash en caisse avec les ventes en espèces
- [ ] Vérifier les recharges SNEL/REGIDESO du jour
- [ ] Contrôler les alertes de stock faible
- [ ] Imprimer ou exporter le rapport de clôture
- [ ] Fermer les sessions ouvertes

### Checklist hebdomadaire

- [ ] Analyser les ventes de la semaine dans **Analytics**
- [ ] Vérifier les performances des vendeurs
- [ ] Faire un inventaire partiel des produits les plus vendus
- [ ] Vérifier les comptes utilisateurs actifs
- [ ] Sauvegarder la base de données

### Checklist mensuelle

- [ ] Faire un inventaire complet
- [ ] Vérifier les écarts de stock et les corriger
- [ ] Analyser les produits les plus et les moins vendus
- [ ] Vérifier les paramètres du magasin (prix, taxes, informations)
- [ ] Revoir les accès et les rôles des utilisateurs
- [ ] Faire une sauvegarde complète et la stocker en lieu sûr

---

## Problèmes et actions correctives

| Problème observé | Action corrective |
|------------------|-------------------|
| Écart de caisse | Vérifier l'historique, les remises, les annulations et les paiements fractionnés |
| Stock incorrect | Faire un inventaire et corriger les quantités dans **Produits** |
| Vente anormale | Ouvrir la facture, vérifier les articles et parler avec le vendeur |
| Compte utilisateur suspect | Changer le mot de passe ou désactiver le compte |
| Recharge non confirmée | Vérifier le numéro de compteur et contacter OSAT-Energie si besoin |
| Facture non imprimée | Renvoyer l'impression depuis **Historique** |
| Bug ou erreur technique | Noter le message, actualiser la page, et contacter l'informaticien |
| Données perdues | Restaurer la dernière sauvegarde et appeler l'informaticien |

---

## Glossaire

| Terme | Explication simple |
|-------|-------------------|
| **Stock** | Quantité disponible d'un produit |
| **Seuil minimum** | Quantité à partir de laquelle il faut réapprovisionner |
| **HT** | Hors Taxes : prix avant la TVA |
| **TTC** | Toutes Taxes Comprises : prix final |
| **TVA** | Taxe sur la Valeur Ajoutée |
| **Panier moyen** | Montant moyen dépensé par client |
| **Recharge** | Paiement d'une facture SNEL/REGIDESO pour un client |
| **Bill Payment** | Autre nom pour "recharge" ou paiement de facture |
| **Facture** | Document officiel de vente |
| **Reçu** | Petit ticket de caisse |
| **Admin** | Responsable avec tous les droits |
| **Vendeur** | Employé qui enregistre les ventes |
| **Code agent** | Identifiant interne optionnel du vendeur |
| **Client** | Personne ou entreprise qui achète |
| **NIF** | Numéro d'Identification Fiscale |
| **ICE** | Identifiant Commun de l'Entreprise |
| **RCCM** | Registre du Commerce et du Crédit Mobilier |
| **IF** | Identifiant Fiscal |

---

## Pour aller plus loin

- Pour la procédure d'utilisation quotidienne, consultez `MANUEL_UTILISATION.md`.
- Pour les détails techniques, consultez `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md`.
- Pour l'historique des modifications, consultez `README-MODIF.md`.

---

*Bon contrôle et bonne supervision !*
