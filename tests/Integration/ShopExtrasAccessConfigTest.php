<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Shop;
use PHPUnit\Framework\TestCase;

/**
 * Cycle de vie de la configuration « Accès aux Extras » d'une boutique.
 *
 * Vérifie que la valeur est bien persistée par Shop::create() / Shop::update()
 * et relue par hasExtrasAccess() — c'est le chemin emprunté par
 * ShopController (POST /api/shops et POST /api/shops/update/{id}).
 */
class ShopExtrasAccessConfigTest extends TestCase
{
    private static $pdo;
    private $shopId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'EXTCFG_' . strtoupper(bin2hex(random_bytes(3)));
        $shopModel = new Shop();
        $this->shopId = (int)$shopModel->create([
            'nom'          => 'Boutique Test Config Extras',
            'code'         => $code,
            'actif'        => 1,
            'acces_extras' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    public function testUneBoutiqueCreeeSansOptionNAccessPasAuxExtras(): void
    {
        $shopModel = new Shop();

        $this->assertFalse($shopModel->hasExtrasAccess($this->shopId));
    }

    public function testLaCaseCocheeEstPersiteeParUpdate(): void
    {
        $shopModel = new Shop();

        $shopModel->update($this->shopId, ['acces_extras' => 1]);

        $this->assertTrue(
            $shopModel->hasExtrasAccess($this->shopId),
            'Le super_admin doit pouvoir ouvrir l\'accès via l\'édition de la boutique.'
        );
    }

    public function testLaCaseDecocheeEstPersiteeParUpdate(): void
    {
        $shopModel = new Shop();
        $shopModel->update($this->shopId, ['acces_extras' => 1]);

        $shopModel->update($this->shopId, ['acces_extras' => 0]);

        $this->assertFalse(
            $shopModel->hasExtrasAccess($this->shopId),
            'Le super_admin doit pouvoir retirer l\'accès via l\'édition de la boutique.'
        );
    }

    public function testLaValeurEstRelueEnBase(): void
    {
        $shopModel = new Shop();
        $shopModel->update($this->shopId, ['acces_extras' => 1]);

        $row = self::$pdo->prepare('SELECT acces_extras FROM shops WHERE id = ?');
        $row->execute([$this->shopId]);

        $this->assertSame(1, (int)$row->fetch()['acces_extras']);
    }
}
