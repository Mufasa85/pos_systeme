<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Code agent généré à la création d'un utilisateur (format AFxxxx).
 *
 * Le modal de création (/utilisateurs) pré-remplit le champ « Code Agent »
 * avec un code aléatoire « AF » + 4 chiffres. Cette génération côté
 * navigateur est indicative : c'est le SERVEUR qui reste l'autorité, car
 * la colonne `utilisateurs.agent_code` porte une contrainte UNIQUE.
 *
 * Ce test vérifie le comportement serveur :
 *   - un code déjà attribué est remplacé par un code libre ;
 *   - le code réellement enregistré respecte le format AFxxxx ;
 *   - deux créations successives n'entrent jamais en collision.
 */
class UserAgentCodeCreationTest extends TestCase
{
    private static $pdo;
    private $createdUserIds = [];
    private $sessionBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $userId) {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
        }
        $this->createdUserIds = [];

        $_SESSION = $this->sessionBackup;
    }

    /** Crée un utilisateur via le modèle et mémorise son id pour le nettoyage. */
    private function creerUtilisateur(string $agentCode): int
    {
        $user = new User();
        $user->create(
            '__agent_code_' . bin2hex(random_bytes(4)),
            'motdepasse',
            'Utilisateur Code Agent',
            'vendeur',
            1,
            $agentCode,
            null,
            null,
            null
        );

        $userId = (int)Database::getInstance()->lastInsertId();
        $this->createdUserIds[] = $userId;

        return $userId;
    }

    /** Relit le code agent enregistré en base. */
    private function codeAgentEnBase(int $userId): ?string
    {
        $row = self::$pdo->prepare('SELECT agent_code FROM utilisateurs WHERE id = ?');
        $row->execute([$userId]);

        return $row->fetch()['agent_code'] ?? null;
    }

    public function testLeCodeEnregistreRespecteLeFormatAfQuatreChiffres(): void
    {
        $userId = $this->creerUtilisateur('AF4242');

        $this->assertMatchesRegularExpression(
            '/^AF\d{4}$/',
            (string) $this->codeAgentEnBase($userId),
            'Le code agent doit respecter le format AFxxxx.'
        );
    }

    public function testUnCodeDejaAttribueEstRemplaceParUnCodeLibre(): void
    {
        $existingUserId = $this->creerUtilisateur('AF5555');

        // Le contrôleur reçoit un code déjà pris : il doit le remplacer.
        $submitted = 'AF5555';
        $user = new User();

        $resolved = ($submitted !== '' && !$user->agentCodeExists($submitted))
            ? $submitted
            : $user->generateAgentCode();

        $this->assertNotNull($resolved);
        $this->assertNotSame(
            'AF5555',
            $resolved,
            'Un code déjà attribué ne doit jamais être réutilisé.'
        );
        $this->assertMatchesRegularExpression('/^AF\d{4}$/', $resolved);
        $this->assertFalse($user->agentCodeExists($resolved));

        // Et l'enregistrement passe sans violer la contrainte UNIQUE.
        $newUserId = $this->creerUtilisateur($resolved);
        $this->assertNotSame($existingUserId, $newUserId);
        $this->assertSame($resolved, $this->codeAgentEnBase($newUserId));
    }

    public function testDeuxCreationsSuccessivesNEntrentPasEnCollision(): void
    {
        $user = new User();

        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $code = $user->generateAgentCode();
            $this->assertNotNull($code);
            $this->assertArrayNotHasKey($code, $codes, 'Les codes générés doivent être uniques.');
            $codes[$code] = true;
        }
    }
}
