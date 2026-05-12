# 📋 Option A Finale: Bill Payment dans `ventes`

## Schéma final

```
service_providers (1)
        │
        │ 1:N
        ▼
    ventes (modifié)
        │
        │ 1:N
        ▼
    details_vente (mois sélectionnés)
```

---

## Table `service_providers` (NOUVELLE)

```sql
CREATE TABLE `service_providers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `nom` VARCHAR(100) NOT NULL,
    `type_service` ENUM('electricity', 'water') NOT NULL,
    `api_endpoint` VARCHAR(255) NULL,
    `api_key` VARCHAR(255) NULL,
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

| code     | nom                             | type_service |
| -------- | ------------------------------- | ------------ |
| SNEL     | Société Nationale d'Electricité | electricity  |
| REGIDESO | Régie de Distribution d'Eau     | water        |

---

## Table `ventes` (MODIFIÉE)

### Nouveaux champs:

```sql
ALTER TABLE `ventes` ADD COLUMN (
    `type_vente` ENUM('product', 'bill_payment') DEFAULT 'product',
    `provider_id` INT NULL,
    `numero_compteur` VARCHAR(50) NULL,
    `client_reference` VARCHAR(100) NULL,
    `api_response` TEXT NULL
);
```

### Champs existants + nouveaux:

| Colonne            | Type    | Description                     |
| ------------------ | ------- | ------------------------------- |
| `id`               | INT PK  | -                               |
| `numero_facture`   | VARCHAR | -                               |
| `type_vente`       | ENUM    | **product** ou **bill_payment** |
| `provider_id`      | INT FK  | SNEL ou REGIDESO                |
| `numero_compteur`  | VARCHAR | N° compteur                     |
| `client_reference` | VARCHAR | Réf. client fournisseur         |
| `client_id`        | INT FK  | Client POS                      |
| `vendeur_id`       | INT FK  | -                               |
| `sous_total_ht`    | DECIMAL | -                               |
| `tva`              | DECIMAL | -                               |
| `total`            | DECIMAL | -                               |
| `api_response`     | TEXT    | Réponse JSON API                |

---

## Table `details_vente` (existante)

Pour les factures, les lignes seront:

| vente_id | produit_id | quantite | prix          |
| -------- | ---------- | -------- | ------------- |
| 86       | NULL ou -1 | 1        | 25.00 (Avril) |
| 86       | NULL ou -1 | 1        | 28.00 (Mai)   |

**Note:** Utiliser `comment` ou ajouter `mois_annee` VARCHAR(20) pour identifier "04/2026"

---

## Exemple: Paiement REGIDESO Kabongo Marie

### `ventes`:

| id  | numero_facture | type_vente   | provider_id  | numero_compteur | total |
| --- | -------------- | ------------ | ------------ | --------------- | ----- |
| 86  | FAC-001086     | bill_payment | 2 (REGIDESO) | 555000          | 53.00 |

### `details_vente`:

| vente_id | produit_id | quantite | prix  | mois_annee |
| -------- | ---------- | -------- | ----- | ---------- |
| 86       | NULL       | 1        | 25.00 | 04/2026    |
| 86       | NULL       | 1        | 28.00 | 05/2026    |

---

## Fichier SQL

`migrations/option_a_bill_payment.sql`
