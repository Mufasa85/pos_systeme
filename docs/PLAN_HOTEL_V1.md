# Plan d'implémentation — Module Hôtel V1 (PMS)

**Statut :** conception — non validé
**Cible :** hôtel réel ~20 chambres (simples et doubles)
**Objectif v1 :** remplacer le carnet papier, digitalisation du check-in/out, du housekeeping et de la facturation des extras vers la caisse POS existante.

---

## Sommaire

1. [Périmètre V1 et hors-périmètre](#1-périmètre-v1-ce-quon-fait-et-hors-périmètre-ce-quon-ne-fait-pas)
2. [Rôles et permissions](#2-rôles-et-permissions)
3. [Cohabitation POS / PMS](#3-cohabitation-pos--pms)
4. [Modèle de données (schéma visuel)](#4-modèle-de-données-schéma-visuel)
5. [Flux d'un séjour complet](#5-flux-dun-séjour-complet)
6. [Wireframes des écrans](#6-wireframes-des-écrans)
7. [Modèle de données (SQL)](#7-modèle-de-données-sql)
8. [Flux utilisateurs](#8-flux-utilisateurs)
9. [Routes](#9-routes)
10. [Découpage en sprints](#10-découpage-en-sprints)
11. [Risques identifiés](#11-risques-identifiés)
12. [Décisions à valider](#12-décisions-à-valider)

---

## 1. Périmètre V1 (ce qu'on fait) et hors-périmètre (ce qu'on ne fait PAS)

### ✅ Dans la V1
- 20 chambres (numérotées 1-20), 2 types (simple / double)
- Attribution d'une chambre à un client (arrivée et départ)
- Réservation à l'avance (par téléphone, sans acompte obligatoire)
- 4 états de chambre : `disponible`, `occupée`, `à_nettoyer`, `hors_service`
- Tarification variable : prix par chambre + saison + remise manuelle
- Extras sur la note de chambre (bar, restaurant, pressing interne) — facturés via la caisse POS existante
- Rôles : `manager` (admin), `receptionniste`, `gouvernante`
- Application accessible sur smartphone pour la gouvernante
- Tableau de bord manager : taux d'occupation du jour, chambres à nettoyer, chiffre du jour
- Rapport journalier (recettes hôtel + extras)

### ❌ Hors V1 (explicite)
- Pressing / buanderie avec étiquetage (le pressing en v1 = juste un produit "service pressing" qu'on vend)
- Synchronisation Booking.com / Expedia
- Multi-hôtels (un seul hôtel = une seule boutique dans le POS actuel)
- Paiement en ligne / carte bancaire
- Facture client en PDF
- Programme de fidélité
- Yield management automatique
- Night audit (clôture comptable automatique à minuit)
- Canal de messagerie client (email/SMS automatique de confirmation)

---

## 2. Rôles et permissions

Trois rôles, dérivés du système d'utilisateurs existant (`utilisateurs` + colonne `role`).

| Rôle | Description | Accès |
|---|---|---|
| `manager` (= admin actuel) | Le propriétaire / gérant | Tout : voir, créer, modifier, supprimer, superviser |
| `receptionniste` (= vendeur promu) | Personnel d'accueil | Check-in/out, facturation, extras — pas la config |
| `gouvernante` | Personnel d'étage | Voir sa liste de chambres à nettoyer, marquer comme fait, signaler un problème |

**Mécanisme technique :** la colonne `role` existe déjà (`admin`, `super_admin`, `vendeur`). On ajoute `receptionniste` et `gouvernante` comme valeurs possibles. Le manager peut promouvoir un vendeur en `receptionniste` depuis la page utilisateurs (déjà existante). Une même personne peut cumuler (ex: manager qui fait aussi réception).

**Gouvernante sur smartphone :** elle a son propre login, elle atterrit directement sur la vue mobile "Mes chambres à faire" — pas de sidebar POS.

### Matrice de permissions

| Action | Manager | Réceptionniste | Gouvernante | Vendeur (POS) |
|---|:-:|:-:|:-:|:-:|
| Voir la grille des chambres | ✅ | ✅ | ❌ | ❌ |
| Check-in / check-out | ✅ | ✅ | ❌ | ❌ |
| Ajouter un extra sur un séjour | ✅ | ✅ | ❌ | ❌ |
| Encaiser un paiement | ✅ | ✅ | ❌ | ✅ (caisse POS) |
| Voir les chambres à nettoyer | ✅ | ✅ | ✅ (assignées) | ❌ |
| Marquer une chambre comme faite | ✅ | ✅ (au nom de la gouvernante) | ✅ | ❌ |
| Signaler un problème | ✅ | ✅ | ✅ | ❌ |
| Tableau de bord hôtel | ✅ | ❌ | ❌ | ❌ |
| CRUD chambres / saisons | ✅ | ❌ | ❌ | ❌ |
| Gérer les utilisateurs (promouvoir) | ✅ | ❌ | ❌ | ❌ |
| Utiliser la caisse POS | ✅ | ✅ | ❌ | ✅ |

Légende : ✅ autorisé — ❌ refusé (redirection vers /dashboard)

---

## 3. Cohabitation POS / PMS

Les deux systèmes partagent la même base MySQL mais restent des modules **logiquement séparés**. Le seul pont entre eux est la table `ventes` du POS.

```
┌─────────────────────────────────────────────────────────────────┐
│                        AUTHENTIFICATION                         │
│              table `utilisateurs` + colonne `role`              │
│   (admin / super_admin / vendeur / receptionniste / gouvernante)│
└─────────────┬──────────────────────────┬─────────────────────────┘
              │                          │
              ▼                          ▼
┌──────────────────────────┐   ┌──────────────────────────────────┐
│   MODULE POS (existant)  │   │   MODULE HÔTEL (PMS - nouveau)   │
│                          │   │                                  │
│ • produits               │   │ • hotel_chambres                 │
│ • ventes                 │◄──┤ • hotel_sejours                  │
│ • clients                │   │ • hotel_extras ──► ventes.id     │
│ • categories             │   │ • hotel_paiements                │
│ • shops                  │   │ • hotel_housekeeping             │
│ • caisse / historique    │   │ • hotel_guests                   │
│                          │   │ • hotel_tarifs_saison            │
│ Route : /caisse          │   │                                  │
│                          │   │ Routes : /hotel/*                │
└──────────────────────────┘   └──────────────────────────────────┘
              │                          │
              └──────────┬───────────────┘
                         ▼
              ┌──────────────────────┐
              │  MySQL — base unique │
              │  `pos_system`        │
              └──────────────────────┘
```

**Règles de cohabitation :**

1. **Un hôtel = une boutique** : la table `shops` (POS) représente déjà l'entité commerciale. Pour la V1, on a 1 hôtel = 1 shop. Pas de multi-hôtel.
2. **Pas de duplication de la facturation** : les ventes du bar/restaurant passent par la table `ventes` du POS. Le module hôtel n'a pas sa propre table de vente.
3. **Rôles étendus, pas nouvelle table** : on ajoute `receptionniste` et `gouvernante` à la colonne `role` existante. Aucun changement de schéma utilisateur.
4. **Sidebar conditionnelle** : un utilisateur ne voit dans la sidebar que les liens auxquels son rôle lui donne accès (un vendeur ne voit pas "Hôtel", une gouvernante ne voit pas "Caisse").

---

## 4. Modèle de données (schéma visuel)

```
                     ┌──────────────────┐
                     │   utilisateurs   │
                     │   (POS existant) │
                     └────────┬─────────┘
                              │ user_id
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌────────────────┐    ┌────────────────┐    ┌────────────────┐
│ hotel_sejours  │    │ hotel_extras   │    │ hotel_paiements│
│                │    │                │    │                │
│ • id           │    │ • sejour_id  ──┤    │ • sejour_id  ──┤
│ • chambre_id ──┼────┤ • vente_id*    │    │ • montant      │
│ • guest_id   ──┼────┤ • description  │    │ • mode_paiement│
│ • user_id      │    │ • montant      │    │ • user_id      │
│ • date_arrivee │    │ • user_id      │    │ • created_at   │
│ • date_depart  │    │                │    │                │
│ • statut       │    └────────┬───────┘    └────────────────┘
│ • prix/nuit    │             │
│ • remise_pct   │             │ vente_id (nullable)
│ • version      │             ▼
└───────┬────────┘    ┌──────────────────┐
        │             │   ventes (POS)   │
        │             │   (existant)     │
        │             └──────────────────┘
        │ sejour_id
        ▼
┌────────────────────┐         ┌──────────────────────┐
│ hotel_housekeeping │         │   hotel_guests       │
│                    │         │                      │
│ • chambre_id     ──┼──┐      │ • id                 │
│ • sejour_id        │  │      │ • nom, prenom        │
│ • type             │  │      │ • telephone          │
│ • statut           │  │      │ • piece_identite     │
│ • assigne_a        │  │      │ • nationalite        │
│ • date_creation    │  │      │ • created_at         │
│ • date_fait        │  │      └──────────────────────┘
└────────────────────┘  │
                        │
                        ▼
              ┌──────────────────────┐
              │   hotel_chambres     │
              │                      │
              │ • id                 │
              │ • numero (unique)    │
              │ • type (simple/dbl)  │
              │ • prix_base          │
              │ • etat (3 valeurs)   │
              │ • notes              │
              └──────────────────────┘
                        ▲
                        │ chambre_id
                        │
              ┌──────────────────────┐
              │  hotel_tarifs_saison │
              │                      │
              │ • date_debut         │
              │ • date_fin           │
              │ • multiplicateur     │  ← appliqué à prix_base
              │ • actif              │
              └──────────────────────┘
```

**Cardinalités** :
- `hotel_chambres` 1 ── * `hotel_sejours` (une chambre a plusieurs séjours dans le temps)
- `hotel_guests` 1 ── * `hotel_sejours` (un client peut revenir)
- `hotel_sejours` 1 ── * `hotel_extras` (un séjour a plusieurs extras)
- `hotel_sejours` 1 ── * `hotel_paiements` (un séjour a plusieurs paiements)
- `hotel_sejours` 1 ── * `hotel_housekeeping` (un séjour génère des tâches ménage)
- `hotel_chambres` 1 ── * `hotel_housekeeping` (une chambre a plusieurs tâches)
- `ventes` (POS) 1 ── * `hotel_extras` (une vente peut être imputée à un séjour, mais toutes les ventes ne le sont pas)

**Couleurs du diagramme** :
- Bleu : tables nouvelles du module hôtel
- Gris : tables du POS existant (réutilisées)
- `*` : champ nullable

---

### Tables nouvelles (toutes préfixées `hotel_`)

> **Note** : Le schéma SQL complet de référence est dans la **section 7** plus bas.
> Le diagramme visuel des relations est dans la **section 4** plus haut.

## 5. Flux d'un séjour complet

Du moment où le client appelle (ou arrive) jusqu'à son départ. Chaque étape modifie l'état de la chambre.

```
                    ┌─────────────────┐
                    │  RÉSERVATION    │   (optionnel)
                    │  (téléphone)    │   statut = reserve
                    └────────┬────────┘
                             │ date d'arrivée
                             ▼
                    ┌─────────────────┐
                    │   CHECK-IN      │   statut = en_cours
                    │   chambre 7     │   etat = occupee
                    │   → BLEU        │   ↓
                    │   acompte?      │   séjour créé
                    └────────┬────────┘
                             │
                ┌────────────┼────────────┐
                │            │            │
                ▼            ▼            ▼
          ┌──────────┐  ┌──────────┐  ┌──────────┐
          │ extra #1 │  │ extra #2 │  │ paiement │
          │ (bar)    │  │ (resto)  │  │ partiel  │
          │ lié à    │  │ lié à    │  │ (50 000) │
          │ vente_id │  │ vente_id │  │          │
          └────┬─────┘  └────┬─────┘  └────┬─────┘
               │             │            │
               └─────────────┼────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │   CHECK-OUT     │   statut = termine
                    │   chambre 7     │   etat = a_nettoyer
                    │   → JAUNE       │   ↓
                    │   note finale   │   tâche ménage créée
                    │   encaissement  │
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │  HOUSEKEEPING   │   statut = a_faire
                    │  gouvernante    │   puis = en_cours
                    │  Smartphone     │   puis = fait
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │  CHAMBRE LIBRE  │   etat = disponible
                    │  → VERT         │   prête pour nouveau client
                    └─────────────────┘
```

**Points importants** :

- La transition `disponible → occupée` se fait au check-in.
- La transition `occupée → à_nettoyer` se fait au check-out (création automatique d'une tâche housekeeping).
- La transition `à_nettoyer → disponible` se fait quand la gouvernante marque "fait".
- Un client peut consommer (extra) N'IMPORTE QUAND pendant `en_cours`, via la caisse POS.
- Un acompte à l'arrivée = un `hotel_paiements` immédiat, rattaché au séjour.
- Le solde final = (nuits × prix_par_nuit × (1 - remise)) + extras - somme_paiements.

---

## 6. Wireframes des écrans

Les wireframes ci-dessous sont des **Maquettes ASCII** pour montrer la disposition. Le rendu final sera en HTML/CSS.

### 6.1 Réception — `/hotel/reception` (réceptionniste)

```
┌──────────────────────────────────────────────────────────────────────┐
│ 🏨 Hôtel — Réception                          [+ Réservation] [⚙]   │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   Grille des chambres (aujourd'hui)                                 │
│                                                                      │
│   ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐                          │
│   │ 101 │ │ 102 │ │ 103 │ │ 104 │ │ 105 │                          │
│   │SIMPL│ │SIMPL│ │SIMPL│ │SIMPL│ │SIMPL│                          │
│   │     │ │ 🔵  │ │     │ │     │ │     │   ← Vert = libre         │
│   │ 🟢  │ │OCCUP│ │ 🟢  │ │ 🟢  │ │ 🟢  │     Bleu = occupé        │
│   └─────┘ └─────┘ └─────┘ └─────┘ └─────┘     Jaune = à nettoyer   │
│   ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐     Gris = HS            │
│   │ 106 │ │ 107 │ │ 108 │ │ 109 │ │ 110 │                          │
│   │ 🟡  │ │ 🟢  │ │ 🟢  │ │ 🟡  │ │ ⚫  │                          │
│   │NETTO│ │     │ │     │ │NETTO│ │  HS │                          │
│   └─────┘ └─────┘ └─────┘ └─────┘ └─────┘                          │
│   ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐                          │
│   │ 201 │ │ 202 │ │ 203 │ │ 204 │ │ 205 │                          │
│   │DOUBL│ │DOUBL│ │DOUBL│ │DOUBL│ │DOUBL│                          │
│   │ 🔵  │ │     │ │ 🟢  │ │ 🟢  │ │ 🟢  │                          │
│   │OCCUP│ │ 🟢  │ │     │ │     │ │     │                          │
│   └─────┘ └─────┘ └─────┘ └─────┘ └─────┘                          │
│   ... (10 doubles)                                                  │
│                                                                      │
├──────────────────────────────────────────────────────────────────────┤
│ Réservations à venir : 3   |  Occupées : 7  |  Libres : 10          │
└──────────────────────────────────────────────────────────────────────┘
```

**Clic sur chambre verte** → modal "Arrivée" (voir 6.4)
**Clic sur chambre bleue** → modal "Fiche séjour" (voir 6.5)
**Clic sur chambre jaune** → détail tâche housekeeping

### 6.2 Mobile Gouvernante — `/hotel/etages` (smartphone)

```
┌────────────────────────┐
│ 🧹 Mes chambres        │
├────────────────────────┤
│                        │
│  ┌──────────────────┐  │
│  │ Chambre 106      │  │  ← bouton "FAIT" gros
│  │ À nettoyer       │  │
│  │                  │  │
│  │   [  ✓ FAIT  ]   │  │  ← tap = finit
│  └──────────────────┘  │
│                        │
│  ┌──────────────────┐  │
│  │ Chambre 109      │  │
│  │ À nettoyer       │  │
│  │                  │  │
│  │   [  ✓ FAIT  ]   │  │
│  └──────────────────┘  │
│                        │
│  ┌──────────────────┐  │
│  │ Chambre 205      │  │
│  │ Maintenance      │  │  ← type différent
│  │                  │  │
│  │   [ ⚠ PROBLÈME ] │  │  ← signale au manager
│  └──────────────────┘  │
│                        │
└────────────────────────┘
```

Mode sombre, gros boutons, conçu pour une utilisation à une main en marchant.

### 6.3 Tableau de bord manager — `/hotel/tableau-bord`

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📊 Tableau de bord — Hôtel                       [Aujourd'hui ▼]     │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐      │
│   │    7/20    │ │     3      │ │  450 000   │ │   85 000   │      │
│   │  occupées  │ │ à nettoyer │ │  FC recet. │ │  FC extras │      │
│   └────────────┘ └────────────┘ └────────────┘ └────────────┘      │
│                                                                      │
│   Séjours en cours (7)                                              │
│   ─────────────────────                                             │
│   Chambre 102 │ M. KABANGE  │ 2 nuits │ payé 50k │ reste 50k FC    │
│   Chambre 201 │ M. MUKENDI  │ 3 nuits │ payé 0   │ reste 150k FC   │
│   ...                                                                │
│                                                                      │
│   Tâches housekeeping (3 actives)                                   │
│   ──────────────────────────────                                    │
│   106 │ en_cours │ assignée à Marie                                │
│   109 │ a_faire  │ non assignée                                    │
│   205 │ bloque   │ "baignoire cassée" signalé par José             │
│                                                                      │
│   [Voir rapport journalier →]                                       │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 6.4 Modal d'arrivée (check-in)

```
┌──────────────────────────────────────────────┐
│  Arrivée — Chambre 103                  [X]  │
├──────────────────────────────────────────────┤
│                                              │
│  Client                                       │
│  ( ) Client existant    (●) Nouveau client   │
│                                              │
│  Nom *           [_____________________]     │
│  Téléphone       [_____________________]     │
│  Pièce ID        [_____________________]     │
│  Nationalité     [_____________________]     │
│                                              │
│  Séjour                                       │
│  Nombre de nuits *  [____3____]              │
│  Prix / nuit         30 000 FC (auto-rempli)  │
│  Remise (%)          [____0____]              │
│                                              │
│  Acompte (optionnel)                          │
│  Montant            [____50000____]          │
│  Mode               [Cash ▼]                  │
│                                              │
│  Notes         [_____________________]       │
│                                              │
│        [ Annuler ]    [ ✓ Valider arrivée ]   │
└──────────────────────────────────────────────┘
```

### 6.5 Modal de fiche séjour (pendant le séjour)

```
┌──────────────────────────────────────────────┐
│  Chambre 103 — M. KABANGE              [X]  │
├──────────────────────────────────────────────┤
│                                              │
│  Arrivée : 15/10 14h  │  Départ : 18/10     │
│  3 nuits × 30 000 = 90 000 FC               │
│  Remise : 0%                                │
│                                              │
│  ── Extras (2) ────────────────────         │
│  • 3 bières          6 000 FC (15/10 19h)   │
│  • 1 petit-déj       5 000 FC (16/10 08h)   │
│  Total extras : 11 000 FC                   │
│                                              │
│  ── Paiements (1) ────────────────          │
│  • Acompte           50 000 FC (cash)       │
│                                              │
│  ═══════════════════════════════════════     │
│  TOTAL À PAYER :      51 000 FC              │
│  ═══════════════════════════════════════     │
│                                              │
│  [+ Ajouter extra]   [Départ →]              │
│                                              │
└──────────────────────────────────────────────┘
```

### 6.6 Modal de départ (check-out)

```
┌──────────────────────────────────────────────┐
│  Départ — Chambre 103                  [X]  │
├──────────────────────────────────────────────┤
│                                              │
│  Récapitulatif                                │
│  ─────────────                                │
│  3 nuits × 30 000 FC    =  90 000 FC         │
│  Extras (2)             =  11 000 FC         │
│  ─────────────────────────────────            │
│  TOTAL                   101 000 FC          │
│                                              │
│  Déjà payé (acompte)      50 000 FC          │
│  ─────────────────────────────────            │
│  SOLDE À ENCAISSER       51 000 FC           │
│                                              │
│  Paiement final                             │
│  Montant *     [____51000____]              │
│  Mode          [Cash ▼]                      │
│                                              │
│  ☑ Libérer la chambre et créer tâche ménage  │
│                                              │
│        [ Annuler ]    [ ✓ Encaisser et partir ]│
└──────────────────────────────────────────────┘
```

---

## 7. Modèle de données (SQL)

```sql
-- Un client de passage (guest)
CREATE TABLE hotel_guests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NULL,
  telephone VARCHAR(30) NULL,
  piece_identite VARCHAR(50) NULL,             -- CIN, passeport
  nationalite VARCHAR(50) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Un séjour (= le client dans la chambre sur une période)
CREATE TABLE hotel_sejours (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chambre_id INT NOT NULL,
  guest_id INT NOT NULL,
  date_arrivee DATETIME NOT NULL,              -- quand il est arrivé physiquement
  date_depart_prevue DATE NOT NULL,            -- combien de nuits
  date_depart_reelle DATETIME NULL,            -- quand il est vraiment parti
  statut ENUM('reserve','en_cours','termine','annule','no_show') NOT NULL,
  prix_par_nuit DECIMAL(10,2) NOT NULL,        -- prix figé à l'arrivée (gère saison + remise)
  remise_pct DECIMAL(5,2) DEFAULT 0,           -- remise appliquée à l'arrivée
  notes TEXT NULL,
  user_id INT NOT NULL,                        -- qui a fait l'arrivée
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chambre_id) REFERENCES hotel_chambres(id),
  FOREIGN KEY (guest_id) REFERENCES hotel_guests(id)
);

-- Les extras consommés (bar, restaurant, etc.) — reliés à la caisse POS existante
CREATE TABLE hotel_extras (
  id INT PRIMARY KEY AUTO_INCREMENT,
  sejour_id INT NOT NULL,
  vente_id INT NULL,                           -- ID dans la table ventes du POS (si vendu via caisse)
  description VARCHAR(255) NOT NULL,           -- ex: "3 bières", "petit-déj"
  montant DECIMAL(10,2) NOT NULL,
  date_consommation DATETIME DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  FOREIGN KEY (sejour_id) REFERENCES hotel_sejours(id)
);

-- Les paiements partiels (acompte, soldes)
CREATE TABLE hotel_paiements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  sejour_id INT NOT NULL,
  montant DECIMAL(10,2) NOT NULL,
  mode_paiement ENUM('cash','mobile_money','carte','autre') NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sejour_id) REFERENCES hotel_sejours(id)
);

-- Les tâches housekeeping (générées automatiquement)
CREATE TABLE hotel_housekeeping (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chambre_id INT NOT NULL,
  sejour_id INT NULL,                          -- lié au séjour qui vient de partir
  type ENUM('nettoyage','inspection','maintenance') NOT NULL,
  statut ENUM('a_faire','en_cours','fait','bloque') NOT NULL DEFAULT 'a_faire',
  assigne_a INT NULL,                          -- user_id de la gouvernante
  date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_fait DATETIME NULL,
  notes TEXT NULL,
  FOREIGN KEY (chambre_id) REFERENCES hotel_chambres(id)
);

-- Saison / tarif variable
CREATE TABLE hotel_tarifs_saison (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nom VARCHAR(50) NOT NULL,                    -- ex: "Haute saison 2026"
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  multiplicateur DECIMAL(4,2) NOT NULL DEFAULT 1.00,  -- ex: 1.50 pour +50%
  actif TINYINT(1) DEFAULT 1
);
```

### Réutilisation du POS existant

- **Les extras passent par la table `ventes` existante** : le réceptionniste utilise la caisse, et à la fin de la vente il choisit "imputer sur la note de la chambre X" au lieu d'encaisser. Ça évite de réinventer la facturation.
- **Le mode de paiement** : on réutilise les modes existants de la caisse.
- **Le rôle utilisateur** : on étend la colonne `role` (string), pas de nouvelle table.

---

## 8. Flux utilisateurs (ce que fait chaque rôle au quotidien)

### Réceptionniste (vue principale : `/hotel/reception`)

1. **Vue d'ensemble des chambres** : grille 20 cases, code couleur par état
   - Vert = disponible
   - Bleu = occupée
   - Jaune = à nettoyer
   - Gris = hors service
2. **Check-in** (4 clics) :
   - Clic sur une chambre verte → modal "Arrivée"
   - Saisie : nom du client (+ tél, pièce ID si nouveau guest, ou recherche dans la liste)
   - Nombre de nuits
   - Prix par nuit (auto-rempli selon chambre + saison, modifiable)
   - Acompte éventuel (montant libre, défaut = 0)
   - Clic "Valider" → chambre passe bleue, séjour créé
3. **Check-out** :
   - Clic sur une chambre bleue → modal "Départ"
   - Affiche : note totale (nuits + extras), paiements déjà versés, solde
   - Saisie paiement final (montant + mode)
   - Clic "Encaisser et libérer" → chambre passe jaune, tâche housekeeping créée
4. **Ajouter un extra** :
   - Sur la fiche d'une chambre bleue : bouton "+ Extra"
   - Soit saisir à la main (description + montant), soit "Aller à la caisse et imputer"
5. **Voir les réservations à venir** : onglet "Réservations" → liste des séjours `statut=reserve` avec date d'arrivée prévue

### Gouvernante (vue mobile : `/hotel/etages`)

1. **Mes chambres à faire** : liste des chambres `a_nettoyer` qui lui sont assignées
2. **Pour chaque chambre** :
   - "Marquer comme fait" (1 clic) → la chambre redevient `disponible`
   - "Signaler un problème" → texte libre + photo optionnelle → notifie le manager
3. Vue **sombre** pour économiser la batterie, gros boutons, fonctionne sur 3G

### Manager (vue : `/hotel/tableau-bord`)

1. **KPI du jour** : chambres occupées / totales, recettes hôtel du jour, extras du jour, chambres à nettoyer
2. **Liste des séjours en cours** (avec solde restant)
3. **Tâches housekeeping** (en cours, bloquées)
4. **Chiffre du jour** = somme des paiements `hotel_paiements` + ventes POS dont l'extrapolation est hôtel
5. **Configuration** (sous-menu) :
   - Liste des chambres (CRUD)
   - Tarifs saisonniers
   - Gestion des utilisateurs (promotion en réceptionniste / gouvernante)
6. Accès total : peut faire tout ce que les deux autres rôles font

---

## 9. Routes

### Web (interface)
| Méthode | Route | Contrôleur | Rôle requis |
|---|---|---|---|
| GET | `/hotel` | redirige vers `/hotel/tableau-bord` ou `/hotel/reception` selon le rôle | tous |
| GET | `/hotel/reception` | PageController::hotelReception | manager, receptionniste |
| GET | `/hotel/etages` | PageController::hotelEtages | manager, gouvernante |
| GET | `/hotel/tableau-bord` | PageController::hotelDashboard | manager |

### API
| Méthode | Route | Action |
|---|---|---|
| GET | `/api/hotel/chambres` | Liste chambres (avec état courant) |
| POST | `/api/hotel/sejours/checkin` | Créer un séjour (check-in) |
| POST | `/api/hotel/sejours/{id}/checkout` | Clôturer un séjour |
| POST | `/api/hotel/sejours/{id}/extra` | Ajouter un extra manuel |
| GET | `/api/hotel/reservations` | Liste séjours `statut=reserve` |
| POST | `/api/hotel/sejours/{id}/acompte` | Enregistrer un paiement |
| GET | `/api/hotel/housekeeping` | Liste tâches (filtrable par gouvernante) |
| POST | `/api/hotel/housekeeping/{id}/done` | Marquer comme fait |
| POST | `/api/hotel/housekeeping/{id}/issue` | Signaler un problème |
| GET/POST/PUT/DELETE | `/api/hotel/chambres` | CRUD chambres (manager) |
| GET/POST | `/api/hotel/tarifs-saison` | CRUD saisons (manager) |

---

## 10. Découpage en sprints

### Sprint 1 — Fondations (1 semaine)
- Migration SQL : créer les 6 tables
- Seed : 20 chambres (10 simples + 10 doubles, prix 30 000 / 50 000 FC)
- Bouton "Hôtel" dans la sidebar (visible seulement pour rôles hôtel)
- Route `/hotel` + redirection selon rôle
- Page vide `/hotel/reception` (juste le squelette)

### Sprint 2 — Le réceptionniste (1.5 semaines)
- Grille 20 chambres avec couleurs
- Modal check-in fonctionnel (création guest + séjour)
- Modal check-out (récap + paiement)
- Ajout d'extra manuel
- Test manuel sur 2-3 scénarios

### Sprint 3 — La gouvernante (1 semaine)
- Page mobile `/hotel/etages` (responsive, gros boutons)
- Liste chambres à nettoyer
- Bouton "fait" + signalement problème
- Notification manager quand problème signalé
- Test smartphone

### Sprint 4 — Le manager (1 semaine)
- Tableau de bord avec KPI
- Rapport journalier
- CRUD chambres (modifier prix, mettre hors service)
- CRUD saisons tarifaires
- Historique des séjours

### Sprint 5 — Liaison caisse + polish (1 semaine)
- Bouton "Aller à la caisse et imputer sur chambre X" depuis la fiche séjour
- Affichage des ventes POS liées dans la fiche du séjour
- Tests d'intégration (séjour complet : arrive → consomme au bar → part → note cohérente)
- Documentation utilisateur (1 page chacun pour réceptionniste, gouvernante, manager)

**Total estimé : ~5-6 semaines** (en travaillant quelques heures par soir, comme tes autres sprints)

---

## 11. Risques identifiés

| Risque | Mitigation |
|---|---|
| La gouvernante n'a pas de smartphone au travail | Mode dégradé : la réceptionniste peut cocher "fait" à sa place |
| Le réceptionniste oublie d'encaisser un extra | À la sortie, le modal check-out liste les extras non payés et bloque si écart |
| Deux réceptionnistes modifient le même séjour en même temps | Verrou optimiste (colonne `version` sur `hotel_sejours`) |
| Le pressing est demandé en v1 | Hors périmètre, mais on peut vendre des "services pressing" comme produit POS dans la caisse |
| Le wifi de l'hôtel est mauvais | App utilisable hors-ligne : les actions sont mises en file et synchronisées (PWA) — **optionnel, à décider en sprint 5** |

---

## 12. Décisions à valider avec le manager (toi)

1. **Noms des rôles** : on garde `receptionniste` et `gouvernante` ou tu préfères autre chose ?
2. **Couleur des chambres** : les codes couleur ci-dessus te vont ?
3. **Extras** : tu confirmes qu'on passe par la caisse POS pour les ventes (bar, restaurant) et pas un module séparé ?
4. **Saisons** : tu utilises déjà des saisons tarifaires ? Si oui, lesquelles et quelles dates ?
5. **Mobile gouvernante** : elles auront un smartphone perso ou fourni par l'hôtel ?
6. **Impression** : la fiche de police (papier pour l'état) est obligatoire chez toi ?

Une fois ces 6 points tranchés, on peut démarrer le sprint 1.
