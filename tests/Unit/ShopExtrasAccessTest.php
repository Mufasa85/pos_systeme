<?php

namespace Tests\Unit;

use App\Core\Database;
use App\Models\Shop;
use PHPUnit\Framework\TestCase;

/**
 * Accès à la fonctionnalité « Extras » configuré par boutique.
 *
 * La colonne `shops.acces_extras` est posée par la migration
 * migrations/2026_11_02_shop_extras_access.sql (défaut 0 = accès refusé).
 *
 * Ces tests vérifient le comportement fail-closed du modèle :
 *   - pas d'identifiant de boutique → accès refusé ;
 *   - boutique inconnue → accès refusé ;
 *   - `acces_extras` à 0 → accès refusé ;
 *   - `acces_extras` à 1 → accès accordé.
 */
class ShopExtrasAccessTest extends TestCase
{
    private static $pdo;
    private $shopId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'EXTRAS_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO shops (nom, code, actif, acces_extras) VALUES (?, ?, 1, 0)"
        )->execute(['Boutique Test Extras', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private function setExtrasAccess(bool $enabled): void
    {
        self::$pdo->prepare('UPDATE shops SET acces_extras = ? WHERE id = ?')
            ->execute([$enabled ? 1 : 0, $this->shopId]);
    }

    public function testAccesRefuseSansIdentifiantDeBoutique(): void
    {
        $shop = new Shop();

        $this->assertFalse($shop->hasExtrasAccess(null));
        $this->assertFalse($shop->hasExtrasAccess(0));
    }

    public function testAccesRefusePourUneBoutiqueInexistante(): void
    {
        $shop = new Shop();

        $this->assertFalse($shop->hasExtrasAccess(999999999));
    }

    public function testAccesRefuseParDefaut(): void
    {
        $shop = new Shop();

        $this->assertFalse(
            $shop->hasExtrasAccess($this->shopId),
            'Une boutique non cochée ne doit pas avoir accès aux Extras.'
        );
    }

    public function testAccesAccordeQuandLaBoutiqueEstCochee(): void
    {
        $this->setExtrasAccess(true);
        $shop = new Shop();

        $this->assertTrue(
            $shop->hasExtrasAccess($this->shopId),
            'Une boutique cochée doit avoir accès aux Extras.'
        );
    }

    public function testRetraitDeLAccessEstApplique(): void
    {
        $this->setExtrasAccess(true);
        $shop = new Shop();
        $this->assertTrue($shop->hasExtrasAccess($this->shopId));

        $this->setExtrasAccess(false);

        $this->assertFalse(
            $shop->hasExtrasAccess($this->shopId),
            'Le retrait de l\'accès doit être pris en compte immédiatement.'
        );
    }
}
