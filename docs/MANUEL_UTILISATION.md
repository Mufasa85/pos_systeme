# Manuel d'Utilisation - POS System

> **Document à destination des utilisateurs**  
> Ce guide explique pas à pas comment utiliser le système de caisse. Aucune connaissance en informatique n'est requise.

---

## Table des matières

1. [Avant de commencer](#avant-de-commencer)
2. [Se connecter](#se-connecter)
3. [Découverte de l'écran principal](#découverte-de-lécran-principal)
4. [Faire une vente à la caisse](#faire-une-vente-à-la-caisse)
5. [Gérer les clients](#gérer-les-clients)
6. [Gérer les produits](#gérer-les-produits)
7. [Consulter l'historique des ventes](#consulter-lhistorique-des-ventes)
8. [Payer une facture SNEL ou REGIDESO](#payer-une-facture-snel-ou-regideso)
9. [Imprimer ou envoyer une facture](#imprimer-ou-envoyer-une-facture)
10. [Utiliser le scanner](#utiliser-le-scanner)
11. [Fonctions réservées au responsable](#fonctions-réservées-au-responsable)
12. [Problèmes fréquents et solutions](#problèmes-fréquents-et-solutions)
13. [Questions fréquentes](#questions-fréquentes)

---

## Avant de commencer

### Ce dont vous avez besoin

- Un ordinateur, une tablette ou un téléphone avec un navigateur internet (Chrome, Edge, Firefox, Safari)
- Une connexion internet
- L'adresse du système de caisse (exemple : `http://localhost/pos_systeme` ou `https://caisse.monsite.com`)
- Un nom d'utilisateur et un mot de passe

### Les deux types d'utilisateurs

- **Vendeur** : peut vendre, consulter l'historique, payer des factures et scanner des produits.
- **Responsable (admin)** : peut en plus gérer les produits, les utilisateurs, les taxes et les paramètres du magasin.

> Si vous ne savez pas quel est votre rôle, demandez au responsable.

---

## Se connecter

1. Ouvrez votre navigateur et tapez l'adresse du système de caisse.
2. Vous arrivez sur l'écran de connexion.
3. Saisissez votre **nom d'utilisateur** et votre **mot de passe**.
4. Cliquez sur le bouton **Se connecter**.

### Problème de connexion ?

- Vérifiez que le nom d'utilisateur et le mot de passe ne contiennent pas d'espaces.
- Vérifiez que la touche `Verr Maj` (Majuscule) n'est pas activée.
- Si vous avez oublié votre mot de passe, demandez au responsable de le réinitialiser.

Une fois connecté, vous arrivez sur le **tableau de bord**.

---

## Découverte de l'écran principal

À gauche de l'écran se trouve un **menu**. Voici les principales sections :

| Menu | À quoi ça sert |
|------|---------------|
| **Tableau de bord** | Voir les chiffres clés du jour, de la semaine, du mois |
| **Caisse** | Enregistrer une nouvelle vente |
| **Recharges** | Payer une facture SNEL ou REGIDESO pour un client |
| **Produits** | Voir et gérer les articles en vente |
| **Historique** | Voir toutes les ventes passées |
| **Utilisateurs** | Gérer les comptes vendeur/responsable (admin) |
| **Catégories** | Gérer les groupes de produits (admin) |
| **Taxes** | Gérer les taxes (admin) |
| **Paramètres** | Modifier les informations du magasin (admin) |
| **Analytics** | Voir des statistiques détaillées (admin) |
| **Scanner** | Scanner un code-barres avec la caméra |

En haut à droite, vous voyez votre nom et un bouton pour **vous déconnecter**.

---

## Faire une vente à la caisse

### 1. Ouvrir la caisse

Cliquez sur **Caisse** dans le menu à gauche. Vous voyez :
- À gauche : la liste des produits et une barre de recherche
- À droite : le **panier** avec le total

### 2. Ajouter un produit au panier

Vous avez plusieurs façons de faire :

- **Cliquer sur la carte du produit** dans la grille
- **Utiliser un scanner de code-barres** : scannez un article, il s'ajoute automatiquement
- **Taper le nom ou le code-barres** dans la barre de recherche, puis cliquer sur le produit

> Pour les produits vendus au poids (fromage, viande...), le système demande de saisir le poids avant d'ajouter au panier.

### 3. Changer la quantité

Dans le panier, à côté de chaque produit :
- Cliquez sur **+** pour ajouter une unité
- Cliquez sur **-** pour retirer une unité
- Cliquez sur la **corbeille** pour supprimer la ligne

### 4. Appliquer une remise

Si le responsable vous a autorisé, vous pouvez ajouter une remise sur un produit ou sur le total de la vente. Cochez l'option remise et indiquez le montant ou le pourcentage.

### 5. Choisir le client

Si le client est enregistré :
1. Cliquez dans le champ **Numéro client**.
2. Tapez le numéro de téléphone du client.
3. Appuyez sur **Entrée** ou cliquez sur **Rechercher**.
4. Si le client existe, son nom apparaît. Sinon, le système propose de le créer.

### 6. Choisir le mode de paiement

En bas du panier, choisissez comment le client paie :

- **Espèces** : le client paie en liquide
- **Mobile Money** : paiement via téléphone
- **Carte bancaire** : paiement par carte

Vous pouvez combiner plusieurs modes de paiement (par exemple 50 000 Fc en espèces et le reste en Mobile Money).

### 7. Valider la vente

1. Cliquez sur le bouton **Payer** ou **Valider**.
2. Un récapitulatif s'affiche. Vérifiez le montant et les articles.
3. Cliquez sur **Valider la facture** pour confirmer.
4. Le système génère une **facture** avec un numéro unique.

### 8. Imprimer le ticket

Après validation, une fenêtre s'affiche avec le ticket. Vous pouvez :
- Cliquer sur **Imprimer** pour lancer l'impression
- Cliquer sur **Fermer** si le client ne veut pas de ticket

> Si l'imprimante ne fonctionne pas, vérifiez qu'elle est bien allumée et connectée.

---

## Gérer les clients

### Créer un nouveau client

1. Cliquez sur **Caisse**.
2. Dans le champ **Numéro client**, tapez le numéro de téléphone du nouveau client.
3. Cliquez sur **Rechercher**.
4. Si le client n'existe pas, cliquez sur **Créer un client**.
5. Remplissez les informations : nom, numéro, type de client, NIF (si le client en a un), adresse.
6. Cliquez sur **Enregistrer**.

### Modifier un client

1. Allez dans **Caisse**.
2. Recherchez le client par son numéro de téléphone.
3. Cliquez sur l'icône **modifier** à côté du nom du client.
4. Modifiez les informations.
5. Cliquez sur **Enregistrer**.

### Types de client

Le système propose plusieurs types :
- **PP** : Personne Physique (particulier)
- **PM** : Personne Morale (entreprise)
- **PC** : Personne Physique Commerçante
- **PL** : Profession Libérale
- **AO** : Ambassades et Organisations

Choisissez le type qui correspond au client. Si vous ne savez pas, laissez **PP**.

---

## Gérer les produits

> Cette fonction est réservée au responsable.

### Ajouter un produit

1. Cliquez sur **Produits** dans le menu.
2. Cliquez sur le bouton **Ajouter un produit**.
3. Remplissez le formulaire :
   - **Nom du produit** : le nom visible en caisse
   - **Code-barres** : le code unique (souvent 13 chiffres)
   - **Catégorie** : le groupe du produit (ex: Comestible, Boisson)
   - **Prix** : prix de vente
   - **Stock** : quantité disponible
   - **Stock minimum** : quantité à partir de laquelle une alerte s'affiche
   - **Taxe** : groupe de taxe appliqué (par défaut Groupe A)
   - **Type de vente** : à l'unité ou au poids
   - **Image** : photo du produit (optionnel)
4. Cliquez sur **Enregistrer**.

### Modifier un produit

1. Allez dans **Produits**.
2. Trouvez le produit dans la liste ou utilisez la recherche.
3. Cliquez sur l'icône **modifier** (souvent un crayon).
4. Changez les informations.
5. Cliquez sur **Enregistrer**.

### Supprimer un produit

1. Allez dans **Produits**.
2. Trouvez le produit.
3. Cliquez sur l'icône **corbeille**.
4. Confirmez la suppression.

> Attention : supprimer un produit peut affecter l'historique des ventes. Vérifiez avant de confirmer.

---

## Consulter l'historique des ventes

1. Cliquez sur **Historique** dans le menu.
2. Vous voyez la liste de toutes les ventes avec :
   - le numéro de facture
   - la date
   - le vendeur
   - le montant total
3. Vous pouvez :
   - Cliquer sur une facture pour voir le détail
   - Rechercher une facture par numéro
   - Filtrer par date

### Afficher ou imprimer une ancienne facture

1. Dans l'historique, cliquez sur la facture concernée.
2. Cliquez sur **Imprimer** ou **Envoyer**.

---

## Payer une facture SNEL ou REGIDESO

1. Cliquez sur **Recharges** dans le menu.
2. Choisissez le fournisseur :
   - **SNEL** : facture d'électricité
   - **REGIDESO** : facture d'eau
3. Saisissez le **numéro de compteur** du client.
4. Cliquez sur **Rechercher**.
5. Attendez quelques secondes. Le système affiche les mois impayés.
6. Sélectionnez les mois à payer en cliquant sur les cartes.
7. Vérifiez le total dans le panier.
8. Cliquez sur **Valider**.
9. Le système enregistre le paiement et génère un reçu.

> Si la recherche ne fonctionne pas, vérifiez le numéro de compteur et la connexion internet.

---

## Imprimer ou envoyer une facture

### Imprimer un reçu

1. Après une vente, cliquez sur **Imprimer** dans la fenêtre du reçu.
2. Choisissez l'imprimante si plusieurs sont disponibles.
3. Cliquez sur **Imprimer**.

### Envoyer par WhatsApp ou SMS

1. Ouvrez la facture (depuis la caisse ou l'historique).
2. Cliquez sur **Envoyer**.
3. Choisissez **WhatsApp** ou **SMS**.
4. Saisissez ou confirmez le numéro de téléphone du client.
5. Cliquez sur **Envoyer**.

> Cette fonction nécessite une connexion internet et un numéro de téléphone valide.

---

## Utiliser le scanner

Le scanner permet de scanner un code-barres avec la caméra de l'appareil.

1. Cliquez sur **Scanner** dans le menu.
2. Autorisez l'accès à la caméra si le navigateur le demande.
3. Placez le code-barres devant la caméra.
4. Le système émet un bip et affiche le produit.
5. Vous pouvez ensuite l'ajouter au panier.

> En cas de problème avec la caméra, essayez d'actualiser la page ou utilisez un scanner USB classique.

---

## Fonctions réservées au responsable

### Gérer les utilisateurs

1. Cliquez sur **Utilisateurs**.
2. Vous voyez la liste des vendeurs et responsables.
3. Cliquez sur **Ajouter** pour créer un nouveau compte.
4. Remplissez : nom d'utilisateur, mot de passe, nom complet, rôle.
5. Cliquez sur **Enregistrer**.

Vous pouvez aussi désactiver un compte ou modifier son rôle.

### Gérer les catégories

1. Cliquez sur **Catégories**.
2. Ajoutez, modifiez ou supprimez les groupes de produits.

### Gérer les taxes

1. Cliquez sur **Taxes**.
2. Le système contient déjà les taxes par défaut (Groupe A à P).
3. Vous pouvez ajouter des taxes personnalisées, mais vous ne pouvez pas modifier les 16 premières taxes du système.

### Modifier les paramètres du magasin

1. Cliquez sur **Paramètres**.
2. Remplissez les informations du magasin :
   - Nom du magasin
   - Adresse
   - Téléphone
   - Email
   - Numéro ICE, RCCM, IF
   - Taux de TVA par défaut
   - Thème de couleur
   - Format d'impression (80mm, 57mm, A4, A5...)
3. Cliquez sur **Enregistrer**.

### Voir les statistiques

1. Cliquez sur **Analytics** ou **Tableau de bord**.
2. Vous voyez :
   - Les ventes par jour, semaine, mois
   - Les meilleurs produits
   - Les performances des vendeurs
   - Les stocks faibles

---

## Problèmes fréquents et solutions

### Je ne peux pas me connecter

- Vérifiez votre nom d'utilisateur et mot de passe.
- Demandez au responsable si votre compte est bien actif.
- Vérifiez que l'adresse du site est correcte.

### La page est blanche ou affiche une erreur

- Actualisez la page avec `F5`.
- Si le problème persiste, contactez le responsable ou l'informaticien.

### Le produit n'apparaît pas à la caisse

- Vérifiez dans **Produits** que le produit existe.
- Vérifiez que le stock est supérieur à 0.
- Vérifiez le code-barres saisi ou scanné.

### Le stock est négatif ou incorrect

- Le stock se met à jour automatiquement après chaque vente.
- Si le stock est incorrect, modifiez-le dans **Produits**.

### L'impression ne fonctionne pas

- Vérifiez que l'imprimante est allumée.
- Vérifiez le câble USB ou la connexion Wi-Fi.
- Vérifiez dans les paramètres que le format de papier est correct (80mm pour une imprimante thermique classique).
- Essayez d'imprimer depuis un autre navigateur.

### Le paiement SNEL/REGIDESO ne marche pas

- Vérifiez le numéro de compteur.
- Vérifiez la connexion internet.
- Vérifiez que le fournisseur est bien sélectionné (SNEL ou REGIDESO).
- Si le message d'erreur persiste, contactez le support OSAT-Energie.

### Le scanner ne lit pas le code-barres

- Essuyez l'objectif de la caméra.
- Assurez-vous que le code-barres est bien éclairé.
- Essayez de scanner plus lentement.
- Utilisez un scanner USB si la caméra ne fonctionne pas.

---

## Questions fréquentes

**Q : Puis-je utiliser le système sur mon téléphone ?**  
R : Oui, le système fonctionne sur ordinateur, tablette et téléphone. Ouvrez simplement l'adresse dans votre navigateur.

**Q : Que faire si le client change d'avis après validation ?**  
R : Le responsable peut annuler une vente dans **Historique**. Le stock est alors rétabli.

**Q : Comment savoir si le stock est faible ?**  
R : Le système affiche une alerte sur le produit lorsque le stock descend sous le seuil minimum. Vous pouvez aussi voir les stocks faibles dans **Analytics**.

**Q : Puis-je vendre un produit même si le stock est à 0 ?**  
R : Cela dépend de la configuration. Normalement, le système bloque la vente si le stock est insuffisant. Demandez au responsable.

**Q : Comment changer mon mot de passe ?**  
R : Demandez au responsable de modifier votre mot de passe dans **Utilisateurs**.

**Q : Le système fonctionne-t-il sans internet ?**  
R : Non, le système nécessite une connexion internet pour fonctionner, notamment pour les paiements SNEL/REGIDESO et la sauvegarde des données.

---

## Pour aller plus loin

- Pour les détails techniques (installation, développement, API), consultez le fichier `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md`.
- Pour l'historique des modifications récentes, consultez `README-MODIF.md`.

---

*Merci d'utiliser POS System.*
