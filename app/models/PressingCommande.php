<?php

namespace App\Models;

/**
 * Dossiers de pressing (`pressing_commandes`).
 *
 * Un dossier = une commande de pressing, c'est-à-dire un lot de vêtements
 * déposé par un client à l'attention d'un pressing.
 *
 * Origine : l'appli autonome `pressing/pressing/index.php`, dont on reprend
 * la logique métier à l'identique. Les tables ont été revues pour supprimer
 * les redondances avec le POS (cf. docs/PLAN_PRESSING.md) :
 *   - le client vient de la table `clients` du POS ;
 *   - l'historique des étapes est écrit dans `audit_logs` ;
 *   - les paiements sont dans `pressing_paiements` (le pressing a son propre
 *     flux d'encaissement : le total du dossier = prix × coefficient, qui
 *     diffère du montant de la vente POS).
 *
 * Le périmètre multi-boutique passe par `shop_id` (et non par une colonne
 * `nif` comme dans l'appli autonome).
 */
class PressingCommande
{
    /**
     * Les 7 étapes de traitement d'un vêtement.
     *
     * 0 Déposé / En attente      4 Repassage terminé
     * 1 Lavage commencé          5 Prêt pour récupération
     * 2 Lavage terminé           6 Remis au client
     * 3 Repassage commencé
     *
     * L'index dans ce tableau EST la valeur stockée en `etape`.
     */
    public const ETAPES = [
        'Déposé / En attente',
        'Lavage commencé',
        'Lavage terminé',
        'Repassage commencé',
        'Repassage terminé',
        'Prêt pour récupération',
        'Remis au client',
    ];

    /** Étape « prêt pour récupération » — au-delà, l'article n'est plus en retard. */
    public const ETAPE_PRETE = 5;

    /** Étape « remis au client » — état final. */
    public const ETAPE_REMISE = 6;

    /** Délais par défaut (heures) selon le type de délai. */
    public const DELAIS = ['normal' => 48, 'express' => 24];

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Calcule l'état global d'une commande à partir de ses articles.
     *
     * Portage à l'identique de `etat_commande()` dans l'appli autonome :
     *   - `remise` : tous les articles sont à l'étape 6 ;
     *   - `prete`  : tous les articles sont à l'étape >= 5 ;
     *   - `retard` : au moins un article non prêt dont la date de
     *                disponibilité est dépassée ;
     *   - `encours` : tout le reste.
     *
     * @param array $arts Liste d'articles (au minimum `etape` et `date_dispo`)
     *
     * @return array{0:string,1:string,2:int} [code, libellé, nb de retards]
     */
    public static function etatCommande(array $arts): array
    {
        $nb = count($arts);
        if ($nb === 0) {
            return ['encours', 'En cours', 0];
        }

        $remis = 0;
        $prets = 0;
        $retards = 0;
        foreach ($arts as $a) {
            $etape = (int)($a['etape'] ?? 0);
            if ($etape === self::ETAPE_REMISE) {
                $remis++;
            }
            if ($etape >= self::ETAPE_PRETE) {
                $prets++;
            }
            if ($etape < self::ETAPE_PRETE
                && !empty($a['date_dispo'])
                && strtotime($a['date_dispo']) < time()
            ) {
                $retards++;
            }
        }

        if ($remis === $nb) {
            return ['remise', 'Remise', 0];
        }
        if ($prets === $nb) {
            return ['prete', 'Prête', 0];
        }
        if ($retards > 0) {
            return ['retard', 'En retard', $retards];
        }
        return ['encours', 'En cours', 0];
    }

    /**
     * Formate un montant en francs congolais (identique au format de
     * l'appli autonome : espace comme séparateur de milliers).
     */
    public static function formatMontant(float $n): string
    {
        return number_format($n, 2, ',', ' ') . ' FC';
    }

    /**
     * Solde restant à payer d'un dossier.
     *
     * Total = somme des (quantité × prix unitaire final) des articles.
     * Le prix unitaire stocké est DÉJÀ majoré par le coefficient (la
     * majoration est appliquée à la création, côté contrôleur), donc le
     * coefficient n'est pas réappliqué ici.
     *
     * @param int $commandeId
     *
     * @return float Solde dû (0 si la commande est soldée)
     */
    public function solde(int $commandeId): float
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(quantite * prix_unitaire), 0) AS total
             FROM pressing_articles
             WHERE commande_id = ?',
            [$commandeId]
        );

        $rowPaye = $this->db->fetch(
            'SELECT COALESCE(SUM(montant), 0) AS paye
             FROM pressing_paiements
             WHERE commande_id = ?',
            [$commandeId]
        );

        return (float)($row['total'] ?? 0) - (float)($rowPaye['paye'] ?? 0);
    }

    /**
     * Liste les dossiers d'une boutique, avec recherche et filtre d'état.
     *
     * @param int|null $shopId Périmètre (null = pas de filtre, super_admin)
     * @param string   $q      Recherche (numéro, facture, nom, téléphone)
     * @param string   $f      Filtre d'état : encours|prete|remise|retard|all
     *
     * @return array{commandes:array,compteurs:array} La liste des dossiers
     *                                                et les compteurs par état
     */
    public function listWithEtat(?int $shopId, string $q = '', string $f = 'all'): array
    {
        $sql = 'SELECT pc.id, pc.numero, pc.facture, pc.date_depot, pc.vente_id,
                       pc.created_at,
                       c.id AS client_id, c.nom_client, c.numero AS telephone,
                       v.numero_facture AS vente_facture, v.total AS vente_total,
                       (SELECT COUNT(*) FROM pressing_articles a WHERE a.commande_id = pc.id) AS nb_art,
                       (SELECT COALESCE(SUM(a.quantite * a.prix_unitaire), 0)
                          FROM pressing_articles a WHERE a.commande_id = pc.id) AS total,
                       (SELECT COALESCE(SUM(p.montant), 0)
                          FROM pressing_paiements p WHERE p.commande_id = pc.id) AS paye
                FROM pressing_commandes pc
                LEFT JOIN clients c ON c.id = pc.client_id
                LEFT JOIN ventes v ON v.id = pc.vente_id';

        $params = [];
        $conds = [];

        if ($shopId) {
            $conds[] = 'pc.shop_id = :shop';
            $params[':shop'] = $shopId;
        }

        if ($q !== '') {
            // EMULATE_PREPARES = false : un placeholder nomme ne peut pas etre
            // reutilise. Une cle distincte par occurrence.
            // ⚠️ On ne mélange JAMAIS placeholders nommés et positionnels
            // dans la même requête (PDO lève HY093).
            $conds[] = '(pc.numero LIKE :k1 OR pc.facture LIKE :k2
                        OR c.nom_client LIKE :k3 OR c.numero LIKE :k4)';
            $like = '%' . $q . '%';
            $params[':k1'] = $like;
            $params[':k2'] = $like;
            $params[':k3'] = $like;
            $params[':k4'] = $like;
        }

        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $sql .= ' ORDER BY pc.id DESC LIMIT 300';

        $commandes = $this->db->fetchAll($sql, $params);

        // Etat de chaque commande + compteurs par etat pour les onglets
        $nbParEtat = ['encours' => 0, 'prete' => 0, 'remise' => 0, 'retard' => 0];
        foreach ($commandes as &$c) {
            $arts = $this->articlesEtapes((int)$c['id']);
            [$c['etat_code'], $c['etat'], $c['nb_retard']] = self::etatCommande($arts);
            $c['solde'] = (float)$c['total'] - (float)$c['paye'];
            $nbParEtat[$c['etat_code']] = ($nbParEtat[$c['etat_code']] ?? 0) + 1;
        }
        unset($c);

        $nbParEtat['all'] = count($commandes);

        // Filtre d etat
        if ($f !== 'all' && isset($nbParEtat[$f])) {
            $commandes = array_values(array_filter(
                $commandes,
                fn ($c) => $c['etat_code'] === $f
            ));
        }

        // Les compteurs sont renvoyés à part : les mélanger à la liste
        // rendrait le tableau impur (clé "__compteurs" au milieu des
        // dossiers), et le controller fait array_values() dessus.
        return [
            'commandes' => $commandes,
            'compteurs' => $nbParEtat,
        ];
    }

    /**
     * Compteurs du tableau de bord (stats).
     *
     * @param int|null $shopId
     *
     * @return array{total:int,encours:int,pretes:int,remises:int,encaisse:float}
     */
    public function stats(?int $shopId): array
    {
        $params = [];
        $where = '';
        if ($shopId) {
            $where = ' WHERE shop_id = :shop';
            $params[':shop'] = $shopId;
        }

        $total = (int)$this->db->fetch(
            "SELECT COUNT(*) AS c FROM pressing_commandes$where",
            $params
        )['c'];

        // Encaissement pressing : somme des paiements des dossiers de la
        // boutique. Attention à ne PAS mélanger placeholders nommés et
        // positionnels dans la même requête (PDO lève HY093).
        $encaisseSql = 'SELECT COALESCE(SUM(p.montant), 0) AS m
                        FROM pressing_paiements p
                        INNER JOIN pressing_commandes pc ON pc.id = p.commande_id';
        $encaisseParams = [];
        if ($shopId) {
            $encaisseSql .= ' WHERE pc.shop_id = :shop';
            $encaisseParams[':shop'] = $shopId;
        }

        $encaisse = (float)$this->db->fetch($encaisseSql, $encaisseParams)['m'];

        // Repartition des etats : on passe par les articles, l etat d une
        // commande se deduisant de ses articles.
        $rowsSql = 'SELECT pc.id AS cmd_id, a.etape, a.date_dispo
                    FROM pressing_commandes pc
                    LEFT JOIN pressing_articles a ON a.commande_id = pc.id';
        $rowsParams = [];
        if ($shopId) {
            $rowsSql .= ' WHERE pc.shop_id = :shop';
            $rowsParams[':shop'] = $shopId;
        }

        $rows = $this->db->fetchAll($rowsSql, $rowsParams);

        $parCommande = [];
        foreach ($rows as $r) {
            $parCommande[(int)$r['cmd_id']][] = [
                'etape' => $r['etape'],
                'date_dispo' => $r['date_dispo'],
            ];
        }

        $encours = 0;
        $pretes = 0;
        $remises = 0;
        foreach ($parCommande as $arts) {
            [$code] = self::etatCommande($arts);
            if ($code === 'encours' || $code === 'retard') {
                $encours++;
            } elseif ($code === 'prete') {
                $pretes++;
            } else {
                $remises++;
            }
        }

        return [
            'total'    => $total,
            'encours'  => $encours,
            'pretes'   => $pretes,
            'remises'  => $remises,
            'encaisse' => $encaisse,
        ];
    }

    /**
     * Detail complet d'un dossier : articles + historique + paiements.
     *
     * @param int      $id      Identifiant du dossier
     * @param int|null $shopId  Périmètre (null = super_admin)
     *
     * @return array|null null si le dossier n'existe pas ou est hors périmètre
     */
    public function findWithDetail(int $id, ?int $shopId): ?array
    {
        $sql = 'SELECT pc.*, c.nom_client, c.numero AS telephone, c.nif AS client_nif,
                       v.numero_facture AS vente_facture, v.total AS vente_total,
                       v.id AS vente_id_ref
                FROM pressing_commandes pc
                LEFT JOIN clients c ON c.id = pc.client_id
                LEFT JOIN ventes v ON v.id = pc.vente_id
                WHERE pc.id = ?';
        $params = [$id];

        if ($shopId) {
            $sql .= ' AND pc.shop_id = ?';
            $params[] = $shopId;
        }

        $commande = $this->db->fetch($sql, $params);
        if (!$commande) {
            return null;
        }

        $articles = $this->articlesEtapes($id);
        $commande['articles'] = $articles;

        $paiements = $this->db->fetchAll(
            'SELECT id, montant, mode, user_id, date_paiement
             FROM pressing_paiements
             WHERE commande_id = ?
             ORDER BY date_paiement ASC',
            [$id]
        );
        $commande['paiements'] = $paiements;

        $total = 0.0;
        foreach ($articles as $a) {
            $total += (int)$a['quantite'] * (float)$a['prix_unitaire'];
        }
        $paye = 0.0;
        foreach ($paiements as $p) {
            $paye += (float)$p['montant'];
        }

        [$code, $label, $nbRetard] = self::etatCommande($articles);

        $commande['total'] = $total;
        $commande['paye'] = $paye;
        $commande['solde'] = $total - $paye;
        $commande['etat_code'] = $code;
        $commande['etat'] = $label;
        $commande['nb_retard'] = $nbRetard;
        $commande['etapes'] = self::ETAPES;

        // Retrait prevu = date de disponibilite la plus tardive
        $prevu = null;
        foreach ($articles as $a) {
            if (!empty($a['date_dispo']) && ($prevu === null || $a['date_dispo'] > $prevu)) {
                $prevu = $a['date_dispo'];
            }
        }
        $commande['retrait_prevu'] = $prevu;

        return $commande;
    }

    /**
     * Articles d'un dossier avec leur etape, leur retard et leur historique.
     *
     * @param int $commandeId
     *
     * @return array
     */
    public function articlesEtapes(int $commandeId): array
    {
        $articles = $this->db->fetchAll(
            'SELECT id, commande_id, type_vetement, couleur, quantite, prix_unitaire,
                    coefficient, observation, delai_type, delai_heures, date_dispo,
                    etape, updated_at
             FROM pressing_articles
             WHERE commande_id = ?
             ORDER BY id ASC',
            [$commandeId]
        );

        foreach ($articles as &$a) {
            $a['etape'] = (int)$a['etape'];
            $a['etape_label'] = self::ETAPES[$a['etape']] ?? '?';
            $a['retard'] = ($a['etape'] < self::ETAPE_PRETE
                && !empty($a['date_dispo'])
                && strtotime($a['date_dispo']) < time());

            // Historique : lu dans audit_logs (la table dediee de l appli
            // autonome a ete supprimee — cf. docs/PLAN_PRESSING.md).
            $a['suivi'] = $this->historiqueArticle((int)$a['id']);

            // Date reelle de remise (etape 6)
            $remise = null;
            foreach ($a['suivi'] as $s) {
                if ((int)($s['etape'] ?? -1) === self::ETAPE_REMISE) {
                    $remise = $s['date_heure'];
                }
            }
            $a['remise_le'] = $remise;
        }
        unset($a);

        return $articles;
    }

    /**
     * Historique des etapes d'un article, lu dans `audit_logs`.
     *
     * @param int $articleId
     *
     * @return array Liste des evenements (etape, valide_par, date_heure)
     */
    public function historiqueArticle(int $articleId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT al.created_at AS date_heure, al.details, al.user_id,
                    COALESCE(u.nom_complet, '—') AS agent
             FROM audit_logs al
             LEFT JOIN utilisateurs u ON u.id = al.user_id
             WHERE al.entity = 'pressing_article' AND al.entity_id = ?
             ORDER BY al.created_at ASC",
            [$articleId]
        );

        $suivi = [];
        foreach ($rows as $r) {
            $details = json_decode((string)($r['details'] ?? '{}'), true);
            if (!is_array($details)) {
                $details = [];
            }
            $suivi[] = [
                'etape'      => (int)($details['etape'] ?? -1),
                'valide_par' => (string)($details['valide_par'] ?? ''),
                'agent'      => (string)($r['agent'] ?? ''),
                'date_heure' => (string)$r['date_heure'],
            ];
        }

        return $suivi;
    }

    /**
     * Liste les ventes POS d'une boutique qui n'ont pas encore de dossier
     * de pressing : c'est la vue « assistance » qui permet de demarrer un
     * pressing en un clic depuis la caisse.
     *
     * @param int|null $shopId
     * @param int      $limit
     *
     * @return array
     */
    public function ventesSansCommande(?int $shopId, int $limit = 50): array
    {
        $sql = "SELECT v.id, v.numero_facture, v.total, v.date, v.invoice_type,
                       c.id AS client_id, c.nom_client, c.numero AS telephone
                FROM ventes v
                LEFT JOIN clients c ON c.id = v.client_id
                LEFT JOIN pressing_commandes pc ON pc.vente_id = v.id
                WHERE pc.id IS NULL
                  AND v.type_vente = 'product'";

        $params = [];
        if ($shopId) {
            $sql .= ' AND v.shop_id = ?';
            $params[] = $shopId;
        }

        $sql .= ' ORDER BY v.date DESC LIMIT ' . (int)$limit;

        $ventes = $this->db->fetchAll($sql, $params);

        if (empty($ventes)) {
            return [];
        }

        // Charge les lignes de vente (articles) en une seule requete : sans
        // elles, le dossier cree depuis la facture serait vide et son total
        // a 0 (le pressing facture le linge, pas la vente POS).
        $ids = array_map(static fn ($v) => (int)$v['id'], $ventes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $lignes = $this->db->fetchAll(
            "SELECT d.vente_id, d.quantite, d.prix, d.remise_type, d.remise_value,
                    pr.nom AS article_nom
             FROM details_vente d
             LEFT JOIN produits pr ON pr.id = d.produit_id
             WHERE d.vente_id IN ($placeholders)
             ORDER BY d.id ASC",
            $ids
        );

        // Regroupe les articles par vente.
        $parVente = [];
        foreach ($lignes as $l) {
            $prix = (float)$l['prix'];

            // La remise de la ligne de vente s'applique au prix pressing.
            $remiseType = (string)($l['remise_type'] ?? '');
            $remiseValue = (float)($l['remise_value'] ?? 0);
            if ($remiseType === 'percent' && $remiseValue > 0) {
                $prix -= $prix * ($remiseValue / 100);
            } elseif ($remiseType === 'montant' && $remiseValue > 0) {
                $prix -= $remiseValue;
            }
            $prix = max(0.0, round($prix, 2));

            $parVente[(int)$l['vente_id']][] = [
                'name'     => (string)($l['article_nom'] ?? 'Service pressing'),
                'quantity' => max(1, (int)$l['quantite']),
                'price'    => $prix,
            ];
        }

        foreach ($ventes as &$v) {
            $v['articles'] = $parVente[(int)$v['id']] ?? [];
        }
        unset($v);

        return $ventes;
    }

    /**
     * Trouve une vente POS par son numero de facture, bornee a une boutique.
     *
     * @param string   $facture Numero de facture DGI
     * @param int|null $shopId
     *
     * @return array|null
     */
    public function findVenteParFacture(string $facture, ?int $shopId): ?array
    {
        $sql = 'SELECT id, numero_facture, client_id, total, shop_id, store_isf
                FROM ventes
                WHERE numero_facture = ?';
        $params = [$facture];

        if ($shopId) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }

        $sql .= ' LIMIT 1';

        $row = $this->db->fetch($sql, $params);

        return $row ?: null;
    }
}
