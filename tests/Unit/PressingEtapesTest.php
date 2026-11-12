<?php

namespace Tests\Unit;

use App\Models\PressingCommande;
use PHPUnit\Framework\TestCase;

/**
 * Logique métier du pressing : calcul d'état et formatage des montants.
 *
 * Ces tests sont purs (pas de base de données) : ils rejouent la logique
 * de l'appli autonome `pressing/pressing/index.php` (fonction
 * `etat_commande()`), qui est le cœur du workflow de suivi.
 *
 * Les 7 étapes (0 Déposé → 6 Remis) :
 *   0 Déposé / En attente      4 Repassage terminé
 *   1 Lavage commencé          5 Prêt pour récupération
 *   2 Lavage terminé           6 Remis au client
 *   3 Repassage commencé
 */
class PressingEtapesTest extends TestCase
{
    /**
     * Construit un article de test.
     *
     * @param int   $etape      Étape courante (0 → 6)
     * @param string $dateDispo Date de disponibilité ('' = aucune)
     */
    private function article(int $etape, string $dateDispo = ''): array
    {
        return [
            'etape'      => $etape,
            'date_dispo' => $dateDispo !== '' ? $dateDispo : null,
        ];
    }

    public function testCommandeVideEstEnCours(): void
    {
        [$code, $label, $retards] = PressingCommande::etatCommande([]);

        $this->assertSame('encours', $code);
        $this->assertSame('En cours', $label);
        $this->assertSame(0, $retards);
    }

    public function testTousLesArticlesRemisDonneRemise(): void
    {
        [$code, $label] = PressingCommande::etatCommande([
            $this->article(6),
            $this->article(6),
        ]);

        $this->assertSame('remise', $code);
        $this->assertSame('Remise', $label);
    }

    public function testTousLesArticlesPretsDonnePrete(): void
    {
        [$code, $label] = PressingCommande::etatCommande([
            $this->article(5),
            $this->article(6),
        ]);

        $this->assertSame('prete', $code);
        $this->assertSame('Prête', $label);
    }

    public function testArticlesEnTraitementDonneEncours(): void
    {
        [$code, $label] = PressingCommande::etatCommande([
            $this->article(0),
            $this->article(3),
        ]);

        $this->assertSame('encours', $code);
        $this->assertSame('En cours', $label);
    }

    public function testArticleNonPretEtDateDepasseeDonneRetard(): void
    {
        $hier = date('Y-m-d H:i:s', strtotime('-10 hours'));

        [$code, $label, $retards] = PressingCommande::etatCommande([
            $this->article(2, $hier),
        ]);

        $this->assertSame('retard', $code);
        $this->assertSame('En retard', $label);
        $this->assertSame(1, $retards);
    }

    public function testArticlePretIgnoreLeRetard(): void
    {
        // Un article prêt (étape 5) n'est plus compté en retard, même si sa
        // date de disponibilité est dépassée.
        $hier = date('Y-m-d H:i:s', strtotime('-10 hours'));

        [$code, , $retards] = PressingCommande::etatCommande([
            $this->article(5, $hier),
        ]);

        $this->assertSame('prete', $code);
        $this->assertSame(0, $retards);
    }

    public function testArticleSansDateDeDisponibiliteNEstPasEnRetard(): void
    {
        [$code, , $retards] = PressingCommande::etatCommande([
            $this->article(2),
        ]);

        $this->assertSame('encours', $code);
        $this->assertSame(0, $retards);
    }

    public function testLaRemisePrimeSurLeRetard(): void
    {
        // Tous remis, mais l'un avait une date dépassée : l'état reste
        // « remise » (c'est l'ordre de la source).
        $hier = date('Y-m-d H:i:s', strtotime('-10 hours'));

        [$code, , $retards] = PressingCommande::etatCommande([
            $this->article(6),
            $this->article(6, $hier),
        ]);

        $this->assertSame('remise', $code);
        $this->assertSame(0, $retards);
    }

    public function testLeNombreDeRetardsCompteLesArticles(): void
    {
        $hier = date('Y-m-d H:i:s', strtotime('-10 hours'));

        [, , $retards] = PressingCommande::etatCommande([
            $this->article(1, $hier),
            $this->article(2, $hier),
            $this->article(3),
        ]);

        $this->assertSame(2, $retards);
    }

    public function testLesSeptEtapesSontBienDefinies(): void
    {
        $this->assertCount(7, PressingCommande::ETAPES);
        $this->assertSame('Déposé / En attente', PressingCommande::ETAPES[0]);
        $this->assertSame('Remis au client', PressingCommande::ETAPES[6]);
        $this->assertSame(5, PressingCommande::ETAPE_PRETE);
        $this->assertSame(6, PressingCommande::ETAPE_REMISE);
    }

    public function testLesDelaisParDefaut(): void
    {
        $this->assertSame(48, PressingCommande::DELAIS['normal']);
        $this->assertSame(24, PressingCommande::DELAIS['express']);
    }

    public function testFormatageDuMontant(): void
    {
        $this->assertSame('1 234,50 FC', PressingCommande::formatMontant(1234.5));
        $this->assertSame('0,00 FC', PressingCommande::formatMontant(0));
        $this->assertSame('70 000,00 FC', PressingCommande::formatMontant(70000));
    }
}
