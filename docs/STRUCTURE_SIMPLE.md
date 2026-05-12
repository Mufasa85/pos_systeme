# 📋 Nouvelle Stratégie: Table `ventes` modifiée

## Concept:

On envoie les données sélectionnées (mois) à l'API REGIDESO via la structure vente existante.

---

## Modification: Table `ventes`

```sql
ALTER TABLE `ventes` ADD COLUMN (
    `type_vente` ENUM('product', 'bill_payment') DEFAULT 'product' COMMENT 'Type: produit ou facture',
    `provider_id` INT NULL COMMENT 'Fournisseur (SNEL, REGIDESO)',
    `numero_compteur` VARCHAR(50) NULL COMMENT 'N° compteur facture',
    `client_reference` VARCHAR(100) NULL COMMENT 'Réf client fournisseur',
    `api_response` TEXT NULL COMMENT 'Réponse brute API JSON'
);
```

### Nouveaux champs dans `ventes`:

| Colonne            | Type    | Description                 |
| ------------------ | ------- | --------------------------- |
| `type_vente`       | ENUM    | 'product' ou 'bill_payment' |
| `provider_id`      | INT FK  | SNEL ou REGIDESO            |
| `numero_compteur`  | VARCHAR | N° compteur                 |
| `client_reference` | VARCHAR | Réf. client fournisseur     |
| `api_response`     | TEXT    | Réponse JSON API            |

---

## Exemple: Vente facture REGIDESO

### `ventes`:

| id  | numero_facture | type_vente       | provider_id | numero_compteur | client_nom    | total | ... |
| --- | -------------- | ---------------- | ----------- | --------------- | ------------- | ----- | --- |
| 86  | FAC-001085     | **bill_payment** | 2           | 555000          | Kabongo Marie | 53.00 | ... |

### `details_vente` (les mois sélectionnés):

| id  | vente_id | produit_id | quantite | prix          |
| --- | -------- | ---------- | -------- | ------------- |
| 134 | 86       | NULL       | 1        | 25.00 (Avril) |
| 135 | 86       | NULL       | 1        | 28.00 (Mai)   |

---

## Alternative: Nouvelle table `bill_payments` (plus propre)

```sql
CREATE TABLE `bill_payments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `vente_id` INT NOT NULL UNIQUE COMMENT 'Lie au receipt',
    `provider_id` INT NOT NULL,
    `numero_compteur` VARCHAR(50) NOT NULL,
    `client_reference` VARCHAR(100) NULL,
    `client_nom` VARCHAR(100) NULL,
    `methode_paiement` ENUM('cash','mobile_money','card') DEFAULT 'cash',
    `api_reference` VARCHAR(100) NULL COMMENT 'Réf confirmation API',
    `vendeur_id` INT NOT NULL,
    `status` ENUM('pending','confirmed','failed') DEFAULT 'confirmed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`),
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`)
);
```

### Table `bill_payment_items`:

```sql
CREATE TABLE `bill_payment_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `bill_payment_id` INT NOT NULL,
    `annee` YEAR NOT NULL,
    `mois` TINYINT NOT NULL,
    `montant` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`bill_payment_id`) REFERENCES `bill_payments`(`id`)
);
```

---

## 💡 Résumé

| Option | Tables nouvelles | Modification                                   |
| ------ | ---------------- | ---------------------------------------------- |
| A      | 0                | Modifier `ventes` + `details_vente`            |
| B      | 2                | Ajouter `bill_payments` + `bill_payment_items` |

**Option A:** Plus simple,，充分利用现有的销售结构
**Option B:** Plus propre, séparation claire

---
