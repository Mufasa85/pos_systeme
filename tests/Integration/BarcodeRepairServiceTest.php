<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Services\BarcodeRepairService;
use PHPUnit\Framework\TestCase;

/**
 * Réparation des codes-barres corrompus par l'import Excel.
 *
 * L'import lisait la valeur AFFICHÉE de la cellule « code_barres » au lieu
 * de sa valeur RÉELLE. Excel formate automatiquement en date (m/d/yyyy) les
 * codes-barres à 13 chiffres : « 8426423308202 » s'affichait
 * « 5/17/23070765319 » et c'était cette chaîne qui était enregistrée.
 */
class BarcodeRepairServiceTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $categoryId;
    private $createdIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'RPR_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Test Reparation', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['CatReparation', $this->shopId]);
        $this->categoryId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            self::$pdo->prepare('DELETE FROM product_batches WHERE product_id = ?')->execute([$id]);
            self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
        }
        $this->createdIds = [];

        if ($this->categoryId) {
            self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
            $this->categoryId = 0;
        }
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    /**
     * Insère un produit dont le code-barres est « corrompu » (forme date).
     */
    private function insertProduct(string $barcode, string $nom): int
    {
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type)
             VALUES (?, ?, ?, ?, 100, 5, 0, "", 1, "unite")'
        )->execute([$barcode, $nom, $this->categoryId, $this->shopId]);

        $id = (int)self::$pdo->lastInsertId();
        $this->createdIds[] = $id;

        return $id;
    }

    private function barcodeOf(int $id): string
    {
        $stmt = self::$pdo->prepare('SELECT code_barres FROM produits WHERE id = ?');
        $stmt->execute([$id]);

        return (string)$stmt->fetchColumn();
    }

    /**
     * La conversion affichée « m/d/yyyy » → sérial Excel doit être EXACTE
     * (aller-retour vérifié).
     */
    public function testLaConversionDeDateEstExacte(): void
    {
        // Cas réel : 8426423308202 s'affiche « 5/17/23070765319 ».
        $serial = BarcodeRepairService::serialFromDateDisplay('5/17/23070765319');

        $this->assertSame(8426423308202, $serial);

        // Aller-retour : la valeur reconstituée doit ré-afficher la même chose.
        $this->assertSame(
            '5/17/23070765319',
            \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$serial)
                ->format('n/j/Y')
        );

        // Une valeur qui n'est pas une date affichée est refusée.
        $this->assertNull(BarcodeRepairService::serialFromDateDisplay('8426423308202'));
        $this->assertNull(BarcodeRepairService::serialFromDateDisplay('ALMARAL1'));
        $this->assertNull(BarcodeRepairService::serialFromDateDisplay(''));
    }

    /**
     * Le plan détecte le code-barres corrompu et propose sa valeur réelle.
     */
    public function testLePlanDetecteEtProposeLaCorrection(): void
    {
        $id = $this->insertProduct('5/17/23070765319', 'MOLTEX ADVANCED MEDIA PEMPERS 80`S');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId);

        $this->assertSame(1, $plan['detected']);
        $this->assertCount(1, $plan['plan']);
        $this->assertSame($id, $plan['plan'][0]['id']);
        $this->assertSame('5/17/23070765319', $plan['plan'][0]['old']);
        $this->assertSame('8426423308202', $plan['plan'][0]['new']);
        $this->assertSame([], $plan['duplicates']);
        $this->assertSame([], $plan['skipped']);

        // buildPlan ne doit RIEN écrire en base.
        $this->assertSame('5/17/23070765319', $this->barcodeOf($id));
    }

    /**
     * L'application du plan répare réellement le code-barres.
     */
    public function testLaReparationEcritLeVraiCodeBarres(): void
    {
        $id = $this->insertProduct('5/17/23070765319', 'MOLTEX ADVANCED MEDIA PEMPERS 80`S');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId);
        $result = $service->applyPlan($plan['plan'], $plan['duplicates']);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame('8426423308202', $this->barcodeOf($id));
    }

    /**
     * Une collision est signalée et la ligne est IGNORÉE (pas de corruption).
     */
    public function testUneCollisionEstSignaleeEtIgnoree(): void
    {
        // Un produit occupe déjà le code-barres « 8426423308202 ».
        $existingId = $this->insertProduct('8426423308202', 'Produit Existant');

        $id = $this->insertProduct('5/17/23070765319', 'MOLTEX ADVANCED MEDIA PEMPERS 80`S');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId);

        $this->assertSame(1, $plan['detected']);
        $this->assertSame([], $plan['plan'], 'Aucune réparation ne doit être planifiée.');
        $this->assertCount(1, $plan['skipped']);
        $this->assertSame($existingId, $plan['skipped'][0]['collision_id']);

        // Aucune écriture : le produit corrompu garde son code-barres.
        $this->assertSame('5/17/23070765319', $this->barcodeOf($id));
    }

    /**
     * Avec resolve + delete_ids, le doublon est supprimé et le code-barres
     * réel est réattribué.
     */
    public function testLaResolutionDeCollisionSupprimeLeDoublon(): void
    {
        $existingId = $this->insertProduct('8426423308202', 'Produit Existant');
        $id = $this->insertProduct('5/17/23070765319', 'MOLTEX ADVANCED MEDIA PEMPERS 80`S');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId, true, [$existingId]);

        $this->assertCount(1, $plan['plan']);
        $this->assertCount(1, $plan['duplicates']);
        $this->assertSame($existingId, $plan['duplicates'][0]['id']);

        $result = $service->applyPlan($plan['plan'], $plan['duplicates']);

        $this->assertSame(1, $result['repaired']);
        $this->assertSame(1, $result['deleted']);
        $this->assertSame('8426423308202', $this->barcodeOf($id));

        // Le doublon a bien disparu.
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM produits WHERE id = ?');
        $stmt->execute([$existingId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    /**
     * La réparation est idempotente : une seconde passe ne trouve rien.
     */
    public function testLaReparationEstIdempotente(): void
    {
        $this->insertProduct('5/17/23070765319', 'MOLTEX ADVANCED MEDIA PEMPERS 80`S');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId);
        $service->applyPlan($plan['plan'], $plan['duplicates']);

        $plan2 = $service->buildPlan($this->shopId);
        $this->assertSame(0, $plan2['detected']);
        $this->assertSame([], $plan2['plan']);
    }

    /**
     * Un code-barres valide (non « en forme de date ») n'est jamais touché.
     */
    public function testUnCodeBarresValideEstIgnoree(): void
    {
        $id = $this->insertProduct('8426423308202', 'Produit Normal');

        $service = new BarcodeRepairService();
        $plan = $service->buildPlan($this->shopId);

        $this->assertSame(0, $plan['detected']);
        $this->assertSame('8426423308202', $this->barcodeOf($id));
    }
}
