<?php

namespace Tests\Integration;

use App\Controllers\Controller;
use App\Controllers\ReportController;
use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration de l'API des rapports DGI/OSAT.
 *
 * Le transport provider est simulé : aucun appel réseau n'est effectué.
 */
class DgiReportControllerTest extends TestCase
{
    private static $pdo;
    private array $sessionBackup = [];
    private array $getBackup = [];
    private array $shopIds = [];
    private array $samplePayload;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->getBackup = $_GET;

        $fixture = dirname(__DIR__) . '/Fixtures/dgi_report_sample.json';
        $decoded = json_decode((string)file_get_contents($fixture), true);
        $this->assertIsArray($decoded);
        $this->samplePayload = $decoded;

        $this->shopIds['a'] = $this->creerBoutique('ISF_A');
        $this->shopIds['b'] = $this->creerBoutique('ISF_B');
        $this->shopIds['sans_isf'] = $this->creerBoutique('');
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_GET = $this->getBackup;
        http_response_code(200);

        foreach (array_reverse($this->shopIds) as $shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$shopId]);
        }
        $this->shopIds = [];
    }

    private function creerBoutique(string $isf): int
    {
        $code = 'DGI_' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO shops (nom, code, isf, actif) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute(['Boutique DGI Test', $code, $isf !== '' ? $isf : null]);
        return (int)self::$pdo->lastInsertId();
    }

    /**
     * @return array{payload: array, status: int, raw: string}
     */
    private function appeler(Controller $controller, string $method): array
    {
        ob_start();
        try {
            $controller->$method();
        } finally {
            $raw = (string)ob_get_clean();
            $status = http_response_code();
            http_response_code(200);
        }

        $payload = json_decode($raw, true);
        $this->assertIsArray($payload, 'Le contrôleur doit répondre en JSON.');
        return [
            'payload' => $payload,
            'status' => $status,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{controller: ReportController, requestedUrl: string}
     */
    private function controllerAvecTransport(?string &$requestedUrl = null): ReportController
    {
        $transport = function (string $url) use (&$requestedUrl): array {
            $requestedUrl = $url;
            return [
                'status' => 200,
                'body' => json_encode($this->samplePayload),
            ];
        };
        return new ReportController($transport);
    }

    public function testUnAdminEstLimiteASaBoutiqueMemeAvecUnShopIdFourni(): void
    {
        $_SESSION = [
            'user_id' => 101,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
            'shop_id' => (string)$this->shopIds['b'],
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);
        $this->assertSame('ISF_A', $result['payload']['data']['header']['isf']);
        $this->assertStringContainsString('nif=ISF_A', $requestedUrl);
        $this->assertStringNotContainsString('nif=ISF_B', $requestedUrl);
    }

    public function testLeSuperAdminDoitSelectionnerUneBoutique(): void
    {
        $_SESSION = [
            'user_id' => 102,
            'role' => 'super_admin',
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['payload']['success']);
        $this->assertSame('dgi_report_shop_required', $result['payload']['code']);
        $this->assertSame('', $requestedUrl);
    }

    public function testLeSuperAdminUtiliseLaBoutiqueSelectionnee(): void
    {
        $_SESSION = [
            'user_id' => 103,
            'role' => 'super_admin',
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
            'shop_id' => (string)$this->shopIds['b'],
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(200, $result['status']);
        $this->assertSame('ISF_B', $result['payload']['data']['header']['isf']);
        $this->assertStringContainsString('nif=ISF_B', $requestedUrl);
    }

    public function testUnUtilisateurNonAdministrateurEstRefuse(): void
    {
        $_SESSION = [
            'user_id' => 104,
            'role' => 'vendeur',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(403, $result['status']);
        $this->assertSame('Accès refusé', $result['payload']['error']);
        $this->assertSame('', $requestedUrl);
    }

    public function testUneBoutiqueSansIsfEstRefuseeAvantLAppelProvider(): void
    {
        $_SESSION = [
            'user_id' => 105,
            'role' => 'admin',
            'shop_id' => $this->shopIds['sans_isf'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(422, $result['status']);
        $this->assertSame('dgi_report_missing_isf', $result['payload']['code']);
        $this->assertSame('', $requestedUrl);
    }

    public function testUneDateInvalideEstRefuseeAvantLAppelProvider(): void
    {
        $_SESSION = [
            'user_id' => 106,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-02-31',
            'to' => '2026-11-07',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(400, $result['status']);
        $this->assertSame('dgi_report_invalid_date', $result['payload']['code']);
        $this->assertSame('', $requestedUrl);
    }

    public function testLeZDgiUtiliseLaPeriodeDeLaDerniereClotureDgi(): void
    {
        $zReportId = 0;
        $stmt = self::$pdo->prepare(
            'INSERT INTO dgi_z_reports (shop_id, issued_at, period_start, period_end, counter, invoice_count, status)
             VALUES (?, NOW(), ?, ?, 1, 12, "active")'
        );
        $stmt->execute([
            $this->shopIds['a'],
            '2026-10-01 00:00:00',
            '2026-10-31 23:59:59',
        ]);
        $zReportId = (int)self::$pdo->lastInsertId();

        $_SESSION = [
            'user_id' => 108,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [];

        try {
            $requestedUrl = '';
            $result = $this->appeler(
                $this->controllerAvecTransport($requestedUrl),
                'generateDgiZReport'
            );

            $this->assertSame(200, $result['status']);
            $this->assertSame('Z_DGI', $result['payload']['data']['header']['type']);
            $this->assertSame('2026-10-31 23:59:59', $result['payload']['data']['header']['period_start']);
            $this->assertFalse(
                $result['payload']['data']['header']['official_closure'],
                'La consultation ne doit pas déclarer de clôture.'
            );
            // La période est convertie en UTC avant l'appel (Kinshasa = UTC+1).
            $this->assertStringContainsString('debut=31/10/2026%2022:59:59', $requestedUrl);
        } finally {
            self::$pdo->prepare('DELETE FROM dgi_z_reports WHERE id = ?')->execute([$zReportId]);
        }
    }

    public function testLaClotureZDgiEstPersisteeEtChainLaPeriodeSuivante(): void
    {
        $_SESSION = [
            'user_id' => 109,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiZClosure'
        );

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);
        $this->assertTrue(
            $result['payload']['data']['header']['official_closure'],
            'Une clôture doit être marquée comme effectuée.'
        );
        $this->assertArrayHasKey('closure_id', $result['payload']['data']['header']);

        $stored = self::$pdo->prepare(
            'SELECT * FROM dgi_z_reports WHERE shop_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stored->execute([$this->shopIds['a']]);
        $row = $stored->fetch();

        $this->assertNotFalse($row, 'La clôture doit être enregistrée en base.');
        $this->assertSame(1, (int)$row['counter']);
        $this->assertEqualsWithDelta(
            $result['payload']['data']['totals']['ht'],
            (float)$row['total_ht'],
            0.01
        );

        // La période suivante doit démarrer à la fin de la clôture.
        $_GET = [];
        $secondUrl = '';
        $second = $this->appeler(
            $this->controllerAvecTransport($secondUrl),
            'generateDgiZReport'
        );

        $this->assertSame(200, $second['status']);
        $this->assertSame(
            $result['payload']['data']['header']['period_end'],
            $second['payload']['data']['header']['period_start'],
            'La période suivante doit démarrer à la fin de la clôture précédente.'
        );

        self::$pdo->prepare('DELETE FROM dgi_z_reports WHERE shop_id = ?')->execute([$this->shopIds['a']]);
    }

    public function testUnTypeDeFactureInconnuEstRefuse(): void
    {
        $_SESSION = [
            'user_id' => 110,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
            'types' => 'XX',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(400, $result['status']);
        $this->assertSame('dgi_report_invalid_type', $result['payload']['code']);
        $this->assertSame('', $requestedUrl);
    }

    public function testLeFiltreParTypeEstTransmisAuProvider(): void
    {
        $_SESSION = [
            'user_id' => 111,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
            'types' => 'FV,FT',
        ];

        $requestedUrl = '';
        $result = $this->appeler(
            $this->controllerAvecTransport($requestedUrl),
            'generateDgiXReport'
        );

        $this->assertSame(200, $result['status']);
        $types = array_column($result['payload']['data']['by_invoice_type'], 'type');
        sort($types);
        $this->assertSame(['FT', 'FV'], $types, 'Seuls les types filtrés doivent être renvoyés.');
        $this->assertTrue($result['payload']['data']['filter']['filtered']);
        $this->assertSame(['FV', 'FT'], $result['payload']['data']['filter']['types']);
    }

    public function testLaClotureEstRefuseeSurUnePeriodeVide(): void
    {
        $_SESSION = [
            'user_id' => 112,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [];

        // Une période vide est simulée par un payload DGI sans facture.
        $controller = new ReportController(
            fn (string $url) => [
                'status' => 200,
                'body' => json_encode([
                    'data_final' => [
                        'ha' => [], 'va' => [], 'ts' => [],
                        'HT1' => 0, 'TTC' => 0, 'TVA' => 0, 'TS_TOTAL' => 0,
                        'nombre_factures_traitees' => 0, 'remise' => 0,
                    ],
                    'data_par_type' => [],
                ]),
            ]
        );

        $result = $this->appeler($controller, 'generateDgiZClosure');

        $this->assertSame(409, $result['status']);
        $this->assertSame('dgi_closure_empty_period', $result['payload']['code']);

        // Aucune clôture ne doit avoir été enregistrée.
        $stmt = self::$pdo->prepare(
            'SELECT COUNT(*) FROM dgi_z_reports WHERE shop_id = ?'
        );
        $stmt->execute([$this->shopIds['a']]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testUneClotureVideNeSertPasDePointDeDepartAuChaining(): void
    {
        // Clôture vide (0 facture) à une date récente...
        $stmt = self::$pdo->prepare(
            'INSERT INTO dgi_z_reports (shop_id, issued_at, period_start, period_end, counter, invoice_count, status)
             VALUES (?, NOW(), ?, ?, 99, 0, "active")'
        );
        $stmt->execute([
            $this->shopIds['a'],
            '2026-11-10 17:11:36',
            '2026-11-10 17:13:36',
        ]);

        // ...et une clôture valide juste avant.
        $stmt2 = self::$pdo->prepare(
            'INSERT INTO dgi_z_reports (shop_id, issued_at, period_start, period_end, counter, invoice_count, total_ht, status)
             VALUES (?, NOW(), ?, ?, 98, 5, 1000, "active")'
        );
        $stmt2->execute([
            $this->shopIds['a'],
            '2026-11-10 10:18:57',
            '2026-11-10 10:30:00',
        ]);

        $_SESSION = [
            'user_id' => 113,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [];

        try {
            $requestedUrl = '';
            $result = $this->appeler(
                $this->controllerAvecTransport($requestedUrl),
                'generateDgiZReport'
            );

            $this->assertSame(200, $result['status']);
            $this->assertSame(
                '2026-11-10 10:30:00',
                $result['payload']['data']['header']['period_start'],
                'Le chaînage doit repartir de la dernière clôture NON VIDE.'
            );
        } finally {
            self::$pdo->prepare('DELETE FROM dgi_z_reports WHERE shop_id = ?')->execute([$this->shopIds['a']]);
        }
    }

    public function testLeCorpsErreurDuProviderNEstPasExpose(): void
    {
        $_SESSION = [
            'user_id' => 107,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_GET = [
            'from' => '2026-10-07',
            'to' => '2026-11-07',
        ];

        $controller = new ReportController(
            fn (string $url) => [
                'status' => 500,
                'body' => 'SECRET_PROVIDER_BODY',
            ]
        );
        $result = $this->appeler($controller, 'generateDgiXReport');

        $this->assertSame(502, $result['status']);
        $this->assertSame('dgi_provider_http_error', $result['payload']['code']);
        $this->assertStringNotContainsString('SECRET_PROVIDER_BODY', $result['raw']);
    }

    /**
     * Le super_admin n'est rattaché à aucune boutique : l'API interroge alors
     * SON ISF d'entreprise (`company_info.isf`), comme il le fait pour les
     * shops. Une seule req HTTP suffit (pas d'agrégation multi-boutiques).
     */
    public function testLeSuperAdminInterrogeSonIsfQuandAucuneBoutiqueEstChoisie(): void
    {
        $_SESSION = [
            'user_id' => 115,
            'role' => 'super_admin',
        ];
        $_GET = [];

        // La base de test peut ne pas avoir de ligne `company_info` (id = 1) :
        // on en crée une le temps du test, puis on restaure l'état initial.
        $previousRow = self::$pdo
            ->query('SELECT * FROM company_info WHERE id = 1')
            ->fetch(PDO::FETCH_ASSOC);
        $existed = is_array($previousRow);

        if ($existed) {
            self::$pdo
                ->prepare('UPDATE company_info SET isf = ? WHERE id = 1')
                ->execute(['ISF_COMPANY']);
        } else {
            self::$pdo->exec(
                "INSERT INTO company_info (id, name, isf, token) VALUES (1, 'Test', 'ISF_COMPANY', 'TOKEN')"
            );
        }

        try {
            $requestedUrl = '';
            $payload = $this->samplePayload;
            $controller = new \App\Controllers\DashboardController(
                new \App\Services\DgiDashboardService(
                    new \App\Services\DgiReportService(
                        function (string $url) use (&$requestedUrl, $payload): array {
                            $requestedUrl = $url;
                            return [
                                'status' => 200,
                                'body' => json_encode($payload),
                            ];
                        }
                    )
                )
            );

            $result = $this->appeler($controller, 'dgiStats');

            $this->assertSame(200, $result['status']);
            $this->assertTrue($result['payload']['success']);
            $this->assertSame(
                1,
                $result['payload']['data']['meta']['shops_count'],
                'Une seule requete : pas d\'agregation multi-boutiques.'
            );
            $this->assertStringContainsString('nif=ISF_COMPANY', $requestedUrl);
        } finally {
            if ($existed) {
                self::$pdo
                    ->prepare('UPDATE company_info SET isf = ? WHERE id = 1')
                    ->execute([$previousRow['isf']]);
            } else {
                self::$pdo->exec('DELETE FROM company_info WHERE id = 1');
            }
        }
    }

    /**
     * Un super_admin qui selectionne une boutique obtient les chiffres de
     * CETTE boutique (et uniquement elle).
     */
    public function testLeSuperAdminPeutConsulterLesStatsDgiDUneBoutique(): void
    {
        $_SESSION = [
            'user_id' => 116,
            'role' => 'super_admin',
        ];
        $_GET = ['shop_id' => (string)$this->shopIds['a']];

        $requestedUrl = '';
        $payload = $this->samplePayload;
        $controller = new \App\Controllers\DashboardController(
            new \App\Services\DgiDashboardService(
                new \App\Services\DgiReportService(
                    function (string $url) use (&$requestedUrl, $payload): array {
                        $requestedUrl = $url;
                        return [
                            'status' => 200,
                            'body' => json_encode($payload),
                        ];
                    }
                )
            )
        );

        $result = $this->appeler($controller, 'dgiStats');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);
        $this->assertSame(1, $result['payload']['data']['meta']['shops_count']);
        $this->assertStringContainsString('nif=ISF_A', $requestedUrl);
    }

    /**
     * Le Z LOCAL a été retiré : la clôture de référence est celle de la DGI.
     *
     * Ce test verrouille l'absence d'un second chemin de clôture, qui
     * permettrait de compter deux fois les mêmes ventes (une fois côté POS,
     * une fois côté DGI).
     */
    public function testLeControllerNExposePlusDeZLocal(): void
    {
        $this->assertFalse(
            method_exists(ReportController::class, 'generateZReport'),
            'La methode de generation du Z local doit etre retiree.'
        );
        $this->assertFalse(
            method_exists(\App\Services\ReportService::class, 'generateZReport'),
            'Le service ne doit plus savoir ecrire de Z local.'
        );
        $this->assertFalse(
            method_exists(\App\Models\Report::class, 'createZReport'),
            'Le modele ne doit plus savoir ecrire dans z_reports.'
        );
        $this->assertFalse(
            method_exists(\App\Models\Report::class, 'getLastZReport'),
            'Le Z local ne doit plus servir de reference de periode.'
        );
        $this->assertFalse(
            method_exists(ReportController::class, 'generateXReportDaily'),
            'Le X local doit etre retire : le X DGI fait foi.'
        );
        $this->assertFalse(
            method_exists(ReportController::class, 'generateXReportPeriodic'),
            'Le X periodique local doit etre retire : le X DGI fait foi.'
        );
        $this->assertFalse(
            method_exists(\App\Services\ReportService::class, 'generateXReportDaily'),
            'Le service ne doit plus exposer de X local quotidien.'
        );
        $this->assertFalse(
            method_exists(\App\Services\ReportService::class, 'generateXReportPeriodic'),
            'Le service ne doit plus exposer de X local periodique.'
        );
    }

    /**
     * La periode ouverte de reference est la derniere cloture DGI (et non un
     * Z local), afin qu'il n'y ait qu'une seule reference de periode dans le
     * systeme.
     */
    public function testLaPeriodeOuverteRepartDeLaDerniereClotureDgi(): void
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO dgi_z_reports (shop_id, issued_at, period_start, period_end, counter, invoice_count, total_ht, status)
             VALUES (?, NOW(), ?, ?, 97, 3, 1000, "active")'
        );
        $stmt->execute([
            $this->shopIds['a'],
            '2026-11-10 08:00:00',
            '2026-11-10 09:30:00',
        ]);

        try {
            $service = new \App\Services\ReportService();
            $start = $service->resolveOpenPeriodStart($this->shopIds['a']);

            $this->assertSame(
                '2026-11-10 09:30:00',
                $start,
                'La periode ouverte doit repartir de la fin de la derniere cloture DGI.'
            );
        } finally {
            self::$pdo->prepare('DELETE FROM dgi_z_reports WHERE shop_id = ?')->execute([$this->shopIds['a']]);
        }
    }

    /**
     * Les lignes `z_reports` sont des archives POS : elles doivent etre
     * marquees comme telles dans l'historique fusionne.
     */
    public function testLesAnciennesCloturesPosSontMarqueesLegacyDansLHistorique(): void
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO z_reports (shop_id, issued_at, period_start, period_end, counter, total_sales_ht, total_ttc, invoice_count, status)
             VALUES (?, NOW(), ?, ?, 96, 1000, 1000, 1, "archived")'
        );
        $stmt->execute([
            $this->shopIds['a'],
            '2026-11-09 08:00:00',
            '2026-11-09 09:30:00',
        ]);

        try {
            $_SESSION = [
                'user_id' => 114,
                'role' => 'admin',
                'shop_id' => $this->shopIds['a'],
            ];
            $_GET = [];

            $result = $this->appeler(new ReportController(), 'getReportHistory');

            $this->assertSame(200, $result['status']);
            $rows = $result['payload']['data'];
            $this->assertNotEmpty($rows);

            $legacyRows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['origin'] ?? '') === 'local'
            ));
            $this->assertNotEmpty($legacyRows, 'La cloture POS historique doit etre listee.');

            foreach ($legacyRows as $row) {
                $this->assertTrue(
                    $row['legacy'] ?? false,
                    'Les lignes issues de z_reports doivent etre marquees legacy.'
                );
            }
        } finally {
            self::$pdo->prepare('DELETE FROM z_reports WHERE shop_id = ?')->execute([$this->shopIds['a']]);
        }
    }
}
