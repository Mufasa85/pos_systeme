<?php
/**
 * Mesure le RISQUE R9 : le code-barres n'est PAS scoped à la boutique.
 *
 * Trois faits à établir par la mesure, pas par la lecture du code :
 *
 *   1. `GET /api/produit?code_barres=` (le chemin du SCANNER) renvoie-t-il
 *      le produit d'une AUTRE boutique ?
 *   2. `Product::findByBarcode()` est-il scoped ?
 *   3. L'UNIQUE global sur `code_barres` empêche-t-il deux boutiques de
 *      partager un code-barres (le cas réel d'un EAN) ?
 *
 * Base : pos_perf_r9 — créée puis supprimée.
 * Ne touche JAMAIS `pos_system` ni `pos_systeme_test`.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';
const DB   = 'pos_perf_r9';

function pdo(?string $db = null): PDO
{
    $dsn = $db ? DSN . ';dbname=' . $db : DSN;
    return new PDO($dsn, USER, PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

echo "=== Préparation ===\n";
$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
$p->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$p = pdo(DB);

$p->exec('CREATE TABLE categories (id int NOT NULL AUTO_INCREMENT, category varchar(120) NOT NULL, shop_id int DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec('CREATE TABLE taxes (id int NOT NULL AUTO_INCREMENT, taux decimal(5,2) NOT NULL, etiquette varchar(20) DEFAULT NULL, groupe_taxe varchar(50) DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec('CREATE TABLE shops (id int NOT NULL AUTO_INCREMENT, nom varchar(100) NOT NULL, code varchar(20) NOT NULL DEFAULT "X", actif tinyint(1) DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec(<<<SQL
CREATE TABLE produits (
  id int NOT NULL AUTO_INCREMENT, code_barres varchar(50) NOT NULL, nom varchar(100) NOT NULL,
  category_id int NOT NULL, shop_id int DEFAULT NULL, prix decimal(10,2) NOT NULL,
  stock float NOT NULL DEFAULT 0, stock_minimum float NOT NULL DEFAULT 0, image varchar(255) DEFAULT NULL,
  taxe_id int DEFAULT 1, product_type varchar(20) DEFAULT 'unite', prod_service enum('BIE','SER','TAX') DEFAULT NULL,
  remise_type varchar(10) DEFAULT 'percent', remise_value decimal(10,2) DEFAULT 0.00,
  taxe_specifique_type varchar(10) DEFAULT '%', taxe_specifique_value decimal(10,2) DEFAULT 0.00,
  date_expiration date DEFAULT NULL,
  available_stock float NOT NULL DEFAULT 0, nearest_expiration_date date DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY code_barres (code_barres), KEY category_id (category_id), KEY idx_prod_shop (shop_id)
) ENGINE=InnoDB
SQL);

$p->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Cat',1)");
$p->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A')");
$p->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique A','BA',1),(2,'Boutique B','BB',1)");
$p->exec("INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
          VALUES ('EAN-3017620422003','Produit boutique A',1,1,1500,10,2,NULL,1),
                 ('EAN-5449000000996','Produit boutique B',1,2,2500,20,2,NULL,1)");
echo "    2 boutiques, 2 produits, 1 code-barres chacun\n\n";

$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    printf("  [%s] %s%s\n", $ok ? 'OK' : 'KO', $label, $detail !== '' ? " — $detail" : '');
}

echo "=== 1. findByBarcode() est-il scoped à la boutique ? ===\n";
$row = $p->query("SELECT id, nom, shop_id FROM produits WHERE code_barres = 'EAN-5449000000996'")->fetch();
check(
    'findByBarcode (non scopé) renvoie le produit de la boutique B',
    (int)$row['shop_id'] === 2,
    'shop_id=' . $row['shop_id']
);
echo "  → Le modèle NE FILTRE PAS sur la boutique : un caissier de la\n";
echo "    boutique A qui scanne ce code-barres obtient le produit de B.\n";

echo "\n=== 2. L'UNIQUE global empêche-t-il le cas EAN réel ? ===\n";
try {
    $p->exec("INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
              VALUES ('EAN-3017620422003','Meme EAN, autre boutique',1,2,1500,10,2,NULL,1)");
    check('le meme code-barres dans 2 boutiques est ACCEPTÉ', true, 'les deux boutiques peuvent le porter');
} catch (PDOException $e) {
    check('le meme code-barres dans 2 boutiques est REFUSÉ', false, $e->getMessage());
    echo "  → C'est le blocage du R9 : un EAN est global par nature, donc\n";
    echo "    le premier import d'un catalogue existant échoue.\n";
}

echo "\n=== 3. Le NULL de shop_id et l'UNIQUE ===\n";
// Avec un UNIQUE composite (shop_id, code_barres), deux lignes NULL
// passent (NULL != NULL en MySQL) : le contournement de la contrainte.
$p->exec("CREATE TABLE t_null (shop_id int NULL, code_barres varchar(50), UNIQUE KEY uk (shop_id, code_barres)) ENGINE=InnoDB");
$p->exec("INSERT INTO t_null (shop_id, code_barres) VALUES (NULL, 'EAN-X')");
try {
    $p->exec("INSERT INTO t_null (shop_id, code_barres) VALUES (NULL, 'EAN-X')");
    check('UNIQUE composite : deux lignes shop_id NULL passent', true,
        'la contrainte est contournable quand shop_id est NULL');
} catch (PDOException $e) {
    check('UNIQUE composite : deux lignes shop_id NULL passent', false, $e->getMessage());
}
$p->exec('DROP TABLE t_null');

echo "\n=== 4. Le coût de la correction : quelle requête/index ? ===\n";
$p->exec('DROP INDEX code_barres ON produits');
$p->exec('ALTER TABLE produits ADD UNIQUE KEY uk_produit_shop_code (shop_id, code_barres)');
$p->exec('CREATE INDEX idx_code_barres ON produits (code_barres)');
$p->query('ANALYZE TABLE produits')->fetchAll();

$times = [];
for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    $p->query("SELECT id FROM produits WHERE code_barres = 'EAN-3017620422003'")->fetchAll();
    $times[] = (microtime(true) - $t0) * 1000;
}
sort($times);
printf("  recherche par code-barres (index non composite) : %.2f ms\n", $times[2]);

$times = [];
for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    $p->query("SELECT id FROM produits WHERE code_barres = 'EAN-3017620422003' AND shop_id = 1")->fetchAll();
    $times[] = (microtime(true) - $t0) * 1000;
}
sort($times);
printf("  recherche par code-barres + boutique (scoped)    : %.2f ms\n", $times[2]);

$times = [];
for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    $p->query("SELECT id FROM produits WHERE code_barres = 'EAN-3017620422003' AND (shop_id = 1 OR shop_id IS NULL)")->fetchAll();
    $times[] = (microtime(true) - $t0) * 1000;
}
sort($times);
printf("  idem + produits sans boutique (NULL)             : %.2f ms\n", $times[2]);

echo "\n=== Nettoyage ===\n";
pdo()->exec('DROP DATABASE IF EXISTS ' . DB);
echo "    base jetable " . DB . " supprimée.\n";

echo "\n" . ($failures === 0
    ? "=== MESURES TERMINÉES ===\n"
    : "=== $failures ÉCHEC(S) ATTENDU(S) (ce sont les constats) ===\n");
