<?php

namespace App\Controllers;

use App\Models\CaisseBrouillon;

/**
 * Brouillons de panier de la caisse — API REST.
 *
 * Permet au caissier de sauvegarder un panier en cours, puis de le reprendre
 * plus tard pour relancer la facture.
 *
 * Routes (déclarées dans routes/api.php) :
 *   GET  /api/caisse/brouillons                 → liste des brouillons (scopée boutique)
 *   GET  /api/caisse/brouillons/compteur        → nombre de brouillons 'ouvert' (badge)
 *   GET  /api/caisse/brouillons/[i:id]          → détail (payload complet)
 *   POST /api/caisse/brouillons                 → création / mise à jour (upsert)
 *   POST /api/caisse/brouillons/[i:id]/restore  → reprise dans le panier local
 *   POST /api/caisse/brouillons/[i:id]/delete   → suppression
 *
 * ⚠️ Règles invariantes :
 *   - Un brouillon ne déduit JAMAIS le stock et ne consomme JAMAIS un numéro
 *     de facture. La facturation passe EXCLUSIVEMENT par SaleController::create
 *     (cf. AGENT.md §7 : ne pas réinventer la facturation).
 *   - Le payload ne contient que des articles : ni NIF, ni adresse client.
 *   - Le scoping boutique est fail-closed : une erreur SQL ⇒ accès refusé.
 *
 * Référence : docs/PLAN_BROUILLONS_CAISSE.md
 */
class CaisseBrouillonController extends Controller
{
    /** Longueur maximale du libellé d'un brouillon (colonne VARCHAR(120)). */
    private const LIBELLE_MAX = 120;

    /** Nombre maximum d'articles acceptés dans un payload (garde-fou). */
    private const ARTICLES_MAX = 500;

    // ── Lecture ────────────────────────────────────────────────────

    /**
     * GET /api/caisse/brouillons
     *
     * Liste les brouillons de la boutique de l'utilisateur.
     *
     * Q2 (lecture croisée) : un caissier voit les brouillons de ses collègues
     * de la même boutique, mais seul l'AUTEUR ou un admin peut les supprimer.
     */
    public function index($params = null): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $shopId = $this->shopScope();

        // Le super_admin peut cibler une boutique via ?shop_id=.
        if ($this->isSuperAdmin() && !empty($_GET['shop_id'])) {
            $shopId = (int)$_GET['shop_id'];
        }

        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['', CaisseBrouillon::STATUS_OUVERT, CaisseBrouillon::STATUS_CONVERTI], true)) {
            $status = '';
        }

        $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));

        try {
            $model = new CaisseBrouillon();
            $rows = $model->listForShop($shopId, null, $status, $limit);
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] list error: ' . $e->getMessage());
            $this->jsonOut(['success' => false, 'message' => 'Erreur lors du chargement des brouillons.'], 500);
            return;
        }

        // On ne renvoie PAS le `payload` dans la liste : seules les métadonnées.
        // Le contenu n'est utile que pour une restauration ciblée.
        $data = array_map(static function (array $row): array {
            return [
                'id'           => (int)$row['id'],
                'libelle'      => $row['libelle'],
                'auteur'       => $row['auteur'] ?? null,
                'user_id'      => (int)$row['user_id'],
                'client_id'    => $row['client_id'] !== null ? (int)$row['client_id'] : null,
                'client_nom'   => $row['nom_client'] ?? null,
                'sejour_id'    => $row['sejour_id'] !== null ? (int)$row['sejour_id'] : null,
                'invoice_type' => $row['invoice_type'],
                'total'        => (float)$row['total'],
                'nb_articles'  => (int)$row['nb_articles'],
                'status'       => $row['status'],
                'vente_id'     => $row['vente_id'] !== null ? (int)$row['vente_id'] : null,
                'created_at'   => $row['created_at'],
                'updated_at'   => $row['updated_at'],
            ];
        }, $rows);

        $this->jsonOut(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/caisse/brouillons/compteur
     *
     * Nombre de brouillons 'ouvert' de l'utilisateur courant. Sert au badge
     * affiché sur le bouton « Brouillons » de la caisse.
     */
    public function compteur(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $model = new CaisseBrouillon();
            $count = $model->countOpen($this->shopScope(), $this->currentUserId());
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] counter error: ' . $e->getMessage());
            $this->jsonOut(['success' => false, 'message' => 'Erreur.'], 500);
            return;
        }

        $this->jsonOut(['success' => true, 'count' => $count]);
    }

    /**
     * GET /api/caisse/brouillons/[i:id]
     *
     * Détail d'un brouillon, avec son `payload` décodé (pour la restauration).
     */
    public function show($params): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'ID de brouillon manquant.'], 400);
            return;
        }

        $brouillon = $this->loadScoped($id);
        if ($brouillon === null) {
            $this->jsonOut(['success' => false, 'message' => 'Brouillon introuvable.'], 404);
            return;
        }

        $payload = json_decode((string)$brouillon['payload'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $this->jsonOut([
            'success' => true,
            'data'    => [
                'id'           => (int)$brouillon['id'],
                'libelle'      => $brouillon['libelle'],
                'auteur'       => $brouillon['auteur'] ?? null,
                'user_id'      => (int)$brouillon['user_id'],
                'client_id'    => $brouillon['client_id'] !== null ? (int)$brouillon['client_id'] : null,
                'sejour_id'    => $brouillon['sejour_id'] !== null ? (int)$brouillon['sejour_id'] : null,
                'invoice_type' => $brouillon['invoice_type'],
                'total'        => (float)$brouillon['total'],
                'nb_articles'  => (int)$brouillon['nb_articles'],
                'status'       => $brouillon['status'],
                'vente_id'     => $brouillon['vente_id'] !== null ? (int)$brouillon['vente_id'] : null,
                'created_at'   => $brouillon['created_at'],
                'updated_at'   => $brouillon['updated_at'],
                'payload'      => $payload,
            ],
        ]);
    }

    /**
     * POST /api/caisse/brouillons
     *
     * Création ou mise à jour d'un brouillon (upsert).
     *
     * ⚠️ AUCUNE déduction de stock ici : le stock est déduit au moment de la
     * facturation (SaleController::create), comme pour toute vente (règle Q1).
     *
     * Corps attendu (JSON) :
     *   {
     *     "id": 12,                    // optionnel : met à jour au lieu de créer
     *     "libelle": "Coca x3 + Pain", // optionnel : auto-généré si vide
     *     "invoice_type": "FV",
     *     "articles": [ { produit_id, nom, prix, quantite, ... } ],
     *     "client": { id: 5 },         // optionnel
     *     "sejour": { id: 9 },         // optionnel
     *     "payments": [ ... ]          // optionnel
     *   }
     */
    public function store(): void
    {
        if (!$this->requireAuth() || !$this->requireCaisseAccess()) {
            return;
        }

        $data = $this->jsonInput();
        if (!is_array($data)) {
            $this->jsonOut(['success' => false, 'message' => 'Payload invalide.'], 400);
            return;
        }

        $articles = $this->sanitizeArticles($data['articles'] ?? []);
        if (empty($articles)) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Le panier est vide : impossible d\'enregistrer un brouillon.',
            ], 422);
            return;
        }

        // Libellé : celui fourni, sinon un résumé automatique des articles.
        $libelle = trim((string)($data['libelle'] ?? ''));
        if ($libelle === '') {
            $libelle = $this->autoLabel($articles);
        }
        $libelle = mb_substr($libelle, 0, self::LIBELLE_MAX);

        $total = 0.0;
        foreach ($articles as $article) {
            $total += ((float)$article['prix']) * ((float)$article['quantite']);
        }

        $payload = [
            'articles' => $articles,
            'client'   => $this->sanitizeClientRef($data['client'] ?? null),
            'sejour'   => $this->sanitizeSejourRef($data['sejour'] ?? null),
            'payments' => is_array($data['payments'] ?? null) ? array_values($data['payments']) : [],
        ];

        $row = [
            'shop_id'      => $this->shopScope(),
            'user_id'      => $this->currentUserId(),
            'libelle'      => $libelle,
            'client_id'    => $payload['client']['id'] ?? null,
            'sejour_id'    => $payload['sejour']['id'] ?? null,
            'invoice_type' => $this->sanitizeInvoiceType($data['invoice_type'] ?? 'FV'),
            'payload'      => $payload,
            'total'        => round($total, 2),
            'nb_articles'  => count($articles),
            'status'       => CaisseBrouillon::STATUS_OUVERT,
            'vente_id'     => null,
        ];

        try {
            $model = new CaisseBrouillon();
            $id = (int)($data['id'] ?? 0);

            if ($id > 0) {
                // Mise à jour : on vérifie que le brouillon existe ET qu'il
                // appartient à la boutique de l'utilisateur.
                if ($this->loadScoped($id) === null) {
                    $this->jsonOut(['success' => false, 'message' => 'Brouillon introuvable.'], 404);
                    return;
                }
                $model->update($id, $row);
            } else {
                $id = $model->create($row);
            }
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] store error: ' . $e->getMessage());
            $this->jsonOut(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du brouillon.'], 500);
            return;
        }

        $this->logAudit('create', 'caisse_brouillon', $id, [
            'libelle'      => $libelle,
            'nb_articles'  => count($articles),
            'total'        => round($total, 2),
        ]);

        $this->jsonOut([
            'success' => true,
            'id'      => $id,
            'libelle' => $libelle,
            'message' => 'Brouillon enregistré.',
        ]);
    }

    /**
     * POST /api/caisse/brouillons/[i:id]/restore
     *
     * Reprise d'un brouillon dans le panier de la caisse.
     *
     * ⚠️ Cette route NE FACTURE PAS : elle renvoie le `payload` et le front
     * réinjecte les articles dans `posCart`. La facturation reste le fait du
     * bouton « Valider la facture » (→ DGI + SaleController::create).
     *
     * Un brouillon déjà converti peut être rouvert (le caissier corrige son
     * panier) : on le repasse alors en 'ouvert'.
     */
    public function restore($params): void
    {
        if (!$this->requireAuth() || !$this->requireCaisseAccess()) {
            return;
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'ID de brouillon manquant.'], 400);
            return;
        }

        $brouillon = $this->loadScoped($id);
        if ($brouillon === null) {
            $this->jsonOut(['success' => false, 'message' => 'Brouillon introuvable.'], 404);
            return;
        }

        $payload = json_decode((string)$brouillon['payload'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $articles = $this->sanitizeArticles($payload['articles'] ?? []);
        if (empty($articles)) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Ce brouillon ne contient aucun article exploitable.',
            ], 422);
            return;
        }

        // Si le brouillon avait déjà été facturé, on le rouvre : le caissier
        // reprend un panier qu'il avait déjà validé (correction, ajout d'un
        // article). Le nouveau passage créera une NOUVELLE vente.
        try {
            $model = new CaisseBrouillon();
            if ($brouillon['status'] === CaisseBrouillon::STATUS_CONVERTI) {
                $model->update($id, [
                    'libelle'      => $brouillon['libelle'],
                    'client_id'    => $brouillon['client_id'],
                    'sejour_id'    => $brouillon['sejour_id'],
                    'invoice_type' => $brouillon['invoice_type'],
                    'payload'      => $payload,
                    'total'        => $brouillon['total'],
                    'nb_articles'  => (int)$brouillon['nb_articles'],
                    'status'       => CaisseBrouillon::STATUS_OUVERT,
                    'vente_id'     => null,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] restore error: ' . $e->getMessage());
            $this->jsonOut(['success' => false, 'message' => 'Erreur lors de la reprise du brouillon.'], 500);
            return;
        }

        $this->logAudit('restore', 'caisse_brouillon', $id, [
            'libelle'      => $brouillon['libelle'],
            'nb_articles'  => count($articles),
            're_ouverture' => $brouillon['status'] === CaisseBrouillon::STATUS_CONVERTI,
        ]);

        $this->jsonOut([
            'success' => true,
            'id'      => $id,
            'libelle' => $brouillon['libelle'],
            'payload' => [
                'articles' => $articles,
                'client'   => $payload['client'] ?? null,
                'sejour'   => $payload['sejour'] ?? null,
                'payments' => $payload['payments'] ?? [],
            ],
            'invoice_type' => $brouillon['invoice_type'],
            'message' => 'Brouillon restauré.',
        ]);
    }

    /**
     * POST /api/caisse/brouillons/[i:id]/delete
     *
     * Suppression d'un brouillon.
     *
     * Autorisation : l'AUTEUR du brouillon ou un admin de la boutique.
     * Un brouillon déjà converti reste supprimable (il ne sert plus à rien),
     * mais la VENTE associée n'est jamais touchée.
     *
     * POST et non DELETE : le projet n'utilise aucune route Router::delete
     * (cf. /api/pressing/commandes/[i:id]/delete).
     */
    public function destroy($params): void
    {
        if (!$this->requireAuth() || !$this->requireCaisseAccess()) {
            return;
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'ID de brouillon manquant.'], 400);
            return;
        }

        $brouillon = $this->loadScoped($id);
        if ($brouillon === null) {
            $this->jsonOut(['success' => false, 'message' => 'Brouillon introuvable.'], 404);
            return;
        }

        // Autorisation : auteur du brouillon OU admin de la boutique.
        $isAuthor = (int)$brouillon['user_id'] === $this->currentUserId();
        if (!$isAuthor && !$this->isAdmin()) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Seul l\'auteur du brouillon ou un administrateur peut le supprimer.',
            ], 403);
            return;
        }

        try {
            $model = new CaisseBrouillon();
            $model->delete($id);
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] delete error: ' . $e->getMessage());
            $this->jsonOut(['success' => false, 'message' => 'Erreur lors de la suppression.'], 500);
            return;
        }

        $this->logAudit('delete', 'caisse_brouillon', $id, [
            'libelle' => $brouillon['libelle'],
        ]);

        $this->jsonOut(['success' => true, 'message' => 'Brouillon supprimé.']);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Charge un brouillon en vérifiant le scoping boutique.
     *
     * Le scoping est FAIL-CLOSED : un brouillon d'une autre boutique est
     * considéré comme inexistant (404), pas comme interdit (403) — on ne
     * confirme pas l'existence d'une donnée d'une autre boutique.
     *
     * ⚠️ `Database::fetch()` renvoie `false` (et non `null`) quand aucune
     * ligne ne correspond : on normalise pour que le test `=== null` soit
     * toujours correct.
     *
     * @return array|null
     */
    private function loadScoped($id)
    {
        try {
            $model = new CaisseBrouillon();
            $row = $model->findById((int)$id, $this->shopScope());

            return $row ? $row : null;
        } catch (\Throwable $e) {
            error_log('[caisse-brouillons] load error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Boutique de l'utilisateur courant (null pour le super_admin).
     */
    private function shopScope(): ?int
    {
        $shopId = $this->getShopId();
        return $shopId !== null ? (int)$shopId : null;
    }

    /**
     * ID de l'utilisateur connecté.
     */
    private function currentUserId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    /**
     * Réponse JSON normalisée (format `success`/`message`, comme PressingController).
     */
    private function jsonOut(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Lit le body de la requête : JSON (appel AJAX) avec repli sur $_POST.
     *
     * Même pattern que HotelExtrasController::jsonInput() : le repli sert à
     * rendre la méthode testable en CLI (`php://input` n'est pas lisible
     * hors contexte HTTP, alors que `$_POST` l'est).
     *
     * @return array|null
     */
    private function jsonInput(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return !empty($_POST) ? $_POST : null;
    }

    /**
     * Nettoie et normalise la liste d'articles du panier.
     *
     * On ne garde que les champs utiles à la reconstruction du panier ET à
     * la facturation. Le prix et le taux de taxe sont conservés tels quels
     * (c'est un SNAPSHOT), mais la facturation revalidera côté serveur via
     * SaleController::create (le prix facturé reste celui de la vente).
     *
     * ⚠️ Aucun NIF, aucune adresse, aucune coordonnée bancaire n'est conservé.
     *
     * @return array
     */
    private function sanitizeArticles($articles): array
    {
        if (!is_array($articles)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($articles, 0, self::ARTICLES_MAX) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $produitId = (int)($item['produit_id'] ?? 0);
            $quantite  = (float)($item['quantite'] ?? 0);

            // Un article sans produit ou sans quantité positive est inexploitable.
            if ($produitId <= 0 || $quantite <= 0) {
                continue;
            }

            $clean[] = [
                'produit_id'             => $produitId,
                'nom'                    => mb_substr((string)($item['nom'] ?? ('Produit #' . $produitId)), 0, 190),
                'prix'                   => (float)($item['prix'] ?? 0),
                'quantite'               => $quantite,
                'maxStock'               => isset($item['maxStock']) ? (float)$item['maxStock'] : null,
                'product_type'           => (string)($item['product_type'] ?? 'unite'),
                'prod_service'           => $item['prod_service'] ?? null,
                'remise_type'            => (string)($item['remise_type'] ?? '%'),
                'remise_value'           => (float)($item['remise_value'] ?? 0),
                'taxe_specifique_type'   => (string)($item['taxe_specifique_type'] ?? '%'),
                'taxe_specifique_value'  => (float)($item['taxe_specifique_value'] ?? 0),
                'tax_rate'               => (float)($item['tax_rate'] ?? 0),
                'tax_etiquette'          => $item['tax_etiquette'] ?? null,
                'tax_groupe'             => $item['tax_groupe'] ?? null,
            ];
        }

        return $clean;
    }

    /**
     * Référence client conservée dans le brouillon.
     *
     * ⚠️ On ne conserve QUE l'identifiant (et un libellé d'affichage) : ni
     * NIF, ni adresse, ni téléphone. Ces données sont relues depuis la table
     * `clients` au moment de la restauration.
     *
     * @return array{id:int, nom:string}|null
     */
    private function sanitizeClientRef($client): ?array
    {
        if (!is_array($client)) {
            return null;
        }

        $id = (int)($client['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return [
            'id'  => $id,
            'nom' => mb_substr((string)($client['nom'] ?? ''), 0, 120),
        ];
    }

    /**
     * Référence de séjour hôtelier conservée dans le brouillon.
     *
     * @return array{id:int, chambre:string}|null
     */
    private function sanitizeSejourRef($sejour): ?array
    {
        if (!is_array($sejour)) {
            return null;
        }

        $id = (int)($sejour['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return [
            'id'      => $id,
            'chambre' => mb_substr((string)($sejour['chambre'] ?? $sejour['chambre_numero'] ?? ''), 0, 40),
        ];
    }

    /**
     * Type fiscal du brouillon.
     *
     * Liste blanche : seuls les types réellement gérés par le POS sont
     * acceptés, pour ne pas pouvoir injecter une valeur arbitraire.
     */
    private function sanitizeInvoiceType($type): string
    {
        $allowed = ['FV', 'FA', 'FT', 'EA', 'EV', 'ET', 'EX'];
        $type = strtoupper(trim((string)$type));

        return in_array($type, $allowed, true) ? $type : 'FV';
    }

    /**
     * Génère un libellé automatique à partir des articles du panier.
     *
     * Exemple : "Coca-Cola ×2, Pain ×1".
     */
    private function autoLabel(array $articles): string
    {
        $parts = [];

        foreach (array_slice($articles, 0, 3) as $article) {
            $q = (float)$article['quantite'];
            $qLabel = (abs($q - round($q)) < 0.001)
                ? (string)(int)round($q)
                : rtrim(rtrim(number_format($q, 3, ',', ''), '0'), ',');

            $parts[] = $article['nom'] . ' ×' . $qLabel;
        }

        $label = implode(', ', $parts);

        if (count($articles) > 3) {
            $label .= ' +' . (count($articles) - 3);
        }

        if ($label === '') {
            $label = 'Panier';
        }

        return mb_substr($label, 0, self::LIBELLE_MAX);
    }
}
