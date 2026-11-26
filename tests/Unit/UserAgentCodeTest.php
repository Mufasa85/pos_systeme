<?php

namespace Tests\Unit;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Génération du code agent (format AFxxxx).
 *
 * Le modal de création d'utilisateur propose un code agent aléatoire
 * « AF » + 4 chiffres. La colonne `utilisateurs.agent_code` portant une
 * contrainte UNIQUE, le modèle doit garantir que le code proposé est
 * réellement LIBRE avant de l'enregistrer.
 *
 * Ces tests couvrent :
 *   - le format AFxxxx (lettres + exactement 4 chiffres, zéro-padding) ;
 *   - l'unicité : un code déjà attribué n'est jamais proposé ;
 *   - la boucle de ré-essai : les codes déjà pris sont écartés.
 */
class UserAgentCodeTest extends TestCase
{
    private static $pdo;
    private $createdUserIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $userId) {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
        }
        $this->createdUserIds = [];
    }

    /** Crée un utilisateur de test portant le code agent donné. */
    private function creerUtilisateur(string $agentCode): int
    {
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, nom_complet, mot_de_passe, role, actif, agent_code)
             VALUES (?, ?, 'x', 'vendeur', 1, ?)"
        )->execute([
            '__agent_code_' . bin2hex(random_bytes(4)),
            'Utilisateur Code Agent',
            $agentCode,
        ]);

        $userId = (int)self::$pdo->lastInsertId();
        $this->createdUserIds[] = $userId;

        return $userId;
    }

    public function testLeCodeGenereRespecteLeFormatAfQuatreChiffres(): void
    {
        $user = new User();

        for ($i = 0; $i < 50; $i++) {
            $code = $user->generateAgentCode();

            $this->assertNotNull($code, 'La génération doit aboutir.');
            $this->assertMatchesRegularExpression(
                '/^AF\d{4}$/',
                $code,
                'Le code agent doit respecter le format AFxxxx (lettres « AF » + 4 chiffres).'
            );
        }
    }

    public function testLeCodeGenereEstLibre(): void
    {
        $user = new User();
        $code = $user->generateAgentCode();

        $this->assertNotNull($code);
        $this->assertFalse(
            $user->agentCodeExists($code),
            'Un code agent généré ne doit pas être déjà attribué.'
        );
    }

    public function testUnCodeDejaAttribueEstDetecteCommeExistant(): void
    {
        $user = new User();
        $userId = $this->creerUtilisateur('AF1234');

        $this->assertTrue(
            $user->agentCodeExists('AF1234'),
            'Un code agent déjà attribué doit être détecté.'
        );
        $this->assertFalse(
            $user->agentCodeExists('AF1234', $userId),
            'Le code de l\'utilisateur lui-même ne doit pas être un conflit.'
        );
    }

    public function testLesCodesDejaPrisSontEcartesParLaBoucleDeReessai(): void
    {
        // On occupe deux codes consécutifs et on force le tirage à les
        // proposer en premier : la boucle doit les écarter et produire un
        // code libre.
        $this->creerUtilisateur('AF0001');
        $this->creerUtilisateur('AF0002');

        $user = new class () extends User {
            /** @var string[] */
            public array $tirages = [];

            protected function randomAgentCodeSuffix(): string
            {
                $code = array_shift($this->tirages);
                if ($code === null) {
                    return parent::randomAgentCodeSuffix();
                }
                return $code;
            }
        };

        $user->tirages = ['0001', '0002', '0003'];

        $code = $user->generateAgentCode();

        $this->assertSame('AF0003', $code, 'Les codes déjà pris doivent être écartés.');
        $this->assertFalse($user->agentCodeExists($code));
    }

    public function testAucunCodeLibreDisponibleRetourneNull(): void
    {
        $this->creerUtilisateur('AF7777');

        $user = new class () extends User {
            protected function randomAgentCodeSuffix(): string
            {
                return '7777';
            }
        };

        $this->assertNull(
            $user->generateAgentCode(5),
            'Si tous les codes tirés sont pris, la génération doit échouer proprement.'
        );
    }
}
