<?php

namespace App\Controllers;

use App\Models\Client;
use App\Models\PressingArticle;
use App\Models\PressingCommande;
use App\Models\PressingPaiement;
use App\Models\Shop;
use App\Services\DgiSyncService;

/**
 * API JSON du module Pressing — suivi des commandes de pressing.
 *
 * Portage de l'appli autonome `pressing/pressing/index.php` (9 actions `?api=`)
 * vers le POS. La logique métier est reprise à l'identique :
 *   - avancement / retour d'une étape sur un vêtement ;
 *   - avancement de tous les vêtements d'un dossier ;
 *   - remise au client BLOQUÉE tant que le dossier n'est pas soldé ;
 *   - encaissement partiel ;
 *   - suppression autorisée seulement si tous les vêtements sont remis.
 *
 * Rôles :
 *   - lecture / création / avancement / encaissement : tout utilisateur
 *     authentifié dont le flag API `pressing` est actif (comme `/recharges`
 *     et `/hotel`) ;
 *   - suppression : admin et super_admin uniquement.
 *
 * Routes (routes/api.php) :
 *   GET  /api/pressing/stats
 *   GET  /api/pressing/commandes
 *   GET  /api/pressing/commandes/[i:id]
 *   POST /api/pressing/commandes
 *   POST /api/pressing/commandes/[i:id]/advance-all
 *   POST /api/pressing/commandes/[i:id]/pay
 *   POST /api/pressing/commandes/[i:id]/delete
 *   POST /api/pressing/articles/[i:id]/advance
 *   POST /api/pressing/articles/[i:id]/recede
 *   POST /api/pressing/facture/lookup
 *   GET  /api/pressing/ventes-sans-commande
 */
class PressingController extends Controller
{
    /**
     * Palette des couleurs de vêtements (identique à l'appli autonome).
     */
    private const COULEURS = [
        'Noir' => '#111827', 'Blanc' => '#f8fafc', 'Bleu' => '#3b82f6', 'Rouge' => '#ef4444',
        'Vert' => '#22c55e', 'Jaune' => '#eab308', 'Marron' => '#92400e', 'Gris' => '#9ca3af',
        'Rose' => '#ec4899', 'Violet' => '#8b5cf6', 'Orange' => '#f97316', 'Beige' => '#e7d3b0',
        'Bordeaux' => '#881337', 'Kaki' => '#6b7f3f', 'Bleu marine' => '#1e3a8a', 'Turquoise' => '#14b8a6',
    ];

    private function jsonOut(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Garde de lecture : authentifié + boutique active + accès Pressing.
     */
    private function guardRead(): bool
    {
        if (!$this->requireAuth()) {
            $this->jsonOut(['success' => false, 'message' => 'Authentification requise'], 401);
            return false;
        }
        if (!$this->requireShopPressingAccess()) {
            return false;
        }
        return true;
    }

    /**
     * Garde d'écriture : comme la lecture, mais vérifie en plus que la
     * boutique est active (protection contre une boutique désactivée en
     * cours de session).
     */
    private function guardWrite(): bool
    {
        if (!$this->guardRead()) {
            return false;
        }
        if (!$this->isShopActive()) {
            $this->jsonOut([
                'success' => false,
                'error'   => 'shop_disabled',
                'message' => 'Votre boutique a été désactivée.',
            ], 403);
            return false;
        }
        return true;
    }

    /**
     * Garde de suppression : réservée à l'administration.
     */
    private function guardDelete(): bool
    {
        if (!$this->guardWrite()) {
            return false;
        }
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Seul un administrateur peut supprimer un dossier de pressing.',
            ], 403);
            return false;
        }
        return true;
    }

    /**
     * Lit le body de la requête : JSON (appel AJAX) avec repli sur $_POST.
     *
     * Le repli rend la méthode testable en CLI (php://input n'est pas
     * lisible hors contexte HTTP) — même pattern que
     * HotelReservationController::jsonInput().
     */
    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return !empty($_POST) ? $_POST : [];
    }

    /**
     * Périmètre boutique de la requête.
     *
     * Pour un super_admin sans boutique, on ne filtre pas (il voit tout) —
     * c'est le seul cas où le périmètre est NULL.
     */
    private function shopScope(): ?int
    {
        $shopId = $this->getShopId();

        if ($this->isSuperAdmin() && !$shopId) {
            return null;
        }

        return $shopId ? (int)$shopId : 0;
    }

    /**
     * GET /api/pressing/stats — compteurs du tableau de bord.
     */
    public function stats(): void
    {
        if (!$this->guardRead()) {
            return;
        }

        $stats = (new PressingCommande())->stats($this->shopScope());

        $this->jsonOut(['success' => true, 'stats' => $stats]);
    }

    /**
     * GET /api/pressing/commandes — liste + recherche + filtre d'état.
     *
     * Query : q (recherche), f (filtre : encours|prete|remise|retard|all)
     */
    public function index(): void
    {
        if (!$this->guardRead()) {
            return;
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $f = (string)($_GET['f'] ?? 'all');
        if (!in_array($f, ['encours', 'prete', 'remise', 'retard', 'all'], true)) {
            $f = 'all';
        }

        $resultat = (new PressingCommande())->listWithEtat($this->shopScope(), $q, $f);

        $this->jsonOut([
            'success'   => true,
            'data'      => $resultat['commandes'],
            'compteurs' => $resultat['compteurs'],
            'filtre'    => $f,
        ]);
    }

    /**
     * GET /api/pressing/commandes/[i:id] — détail d'un dossier.
     */
    public function show($id): void
    {
        if (!$this->guardRead()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if ($id <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'Identifiant invalide'], 422);
            return;
        }

        $commande = (new PressingCommande())->findWithDetail($id, $this->shopScope());
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Dossier introuvable'], 404);
            return;
        }

        $this->jsonOut(['success' => true, 'data' => $commande]);
    }

    /**
     * POST /api/pressing/commandes — création d'un dossier de pressing.
     *
     * Body : {
     *   facture     : "2026/000849",   (n° de facture DGI)
     *   client_name : "KLO",          (optionnel : repris de la facture)
     *   telephone   : "0842223333",   (optionnel)
     *   date_depot  : "2026-11-04 08:30:00" (optionnel, défaut = maintenant)
     *   articles    : [ { type_vetement, couleur, quantite, prix_unitaire,
     *                      coefficient, observation, delai_type, delai_heures } ]
     * }
     *
     * Le dossier est rattaché à la vente POS portant le même numéro de
     * facture (`vente_id`) s'il en existe une dans la boutique.
     */
    public function store(): void
    {
        if (!$this->guardWrite()) {
            return;
        }

        $input = $this->jsonInput();

        $facture = trim((string)($input['facture'] ?? ''));
        $clientName = trim((string)($input['client_name'] ?? ''));
        $telephone = trim((string)($input['telephone'] ?? ''));
        $dateDepot = trim((string)($input['date_depot'] ?? ''));

        $articles = $input['articles'] ?? [];
        if (!is_array($articles) || count($articles) === 0) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Au moins un vêtement est requis.',
            ], 422);
            return;
        }

        // Nettoyage des articles : on retire les lignes vides.
        $articles = array_values(array_filter(array_map(function ($a) {
            if (!is_array($a)) {
                return null;
            }
            $type = trim((string)($a['type_vetement'] ?? ''));
            if ($type === '') {
                return null;
            }
            return [
                'type_vetement' => $type,
                'couleur'       => $this->couleurValide($a['couleur'] ?? 'Gris'),
                'quantite'      => max(1, (int)($a['quantite'] ?? 1)),
                'prix_unitaire' => max(0, (float)($a['prix_unitaire'] ?? 0)),
                'coefficient'   => max(0, (float)($a['coefficient'] ?? 1)),
                'observation'   => substr(trim((string)($a['observation'] ?? '')), 0, 255),
                'delai_type'    => $this->delaiValide($a['delai_type'] ?? 'normal'),
                'delai_heures'  => max(1, (int)($a['delai_heures'] ?? PressingCommande::DELAIS['normal'])),
            ];
        }, $articles)));

        if (count($articles) === 0) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Aucun article valide.',
            ], 422);
            return;
        }

        if ($clientName === '' && $telephone === '') {
            $this->jsonOut([
                'success' => false,
                'message' => 'Nom du client ou téléphone requis.',
            ], 422);
            return;
        }

        $shopId = $this->shopScope();
        $db = \App\Core\Database::getInstance();
        $pdo = $db->getConnection();

        // L'utilisateur connecté (peut etre absent : la session a expire).
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // Le dossier est rattaché à la boutique de l'utilisateur connecté.
        // Un super_admin SANS boutique ne peut pas créer de dossier : le
        // `shop_id` serait NULL et le dossier serait invisible dans le POS
        // (le scoping filtre dessus). Il doit choisir une boutique.
        if (!$shopId) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Aucune boutique sélectionnée : impossible de rattacher le dossier. '
                             . 'Choisissez une boutique avant de créer une commande de pressing.',
            ], 422);
            return;
        }

        try {
            $pdo->beginTransaction();

            // ── Client ─────────────────────────────────────────────────────
            // Le client vient de la table `clients` du POS : on le recherche
            // par téléphone normalisé, sinon on le crée.
            $clientId = 0;
            if ($telephone !== '') {
                $existant = (new Client())->findByNumero($telephone);
                if ($existant) {
                    $clientId = (int)$existant['id'];
                    if ($clientName !== '' && $clientName !== ($existant['nom_client'] ?? '')) {
                        $db->execute(
                            'UPDATE clients SET nom_client = ? WHERE id = ?',
                            [$clientName, $clientId]
                        );
                    }
                }
            }

            if ($clientId === 0) {
                $clientModel = new Client();
                $clientId = (int)$clientModel->create([
                    'nom_client' => $clientName !== '' ? $clientName : 'Client pressing',
                    'numero'     => $telephone,
                    'nif'        => '',
                    'adresse'    => '',
                    'shop_id'    => $shopId,
                ]);
            }

            // ── Vente POS liée ─────────────────────────────────────────────
            $venteId = null;
            if ($facture !== '') {
                $vente = (new PressingCommande())->findVenteParFacture($facture, $shopId);
                if ($vente) {
                    $venteId = (int)$vente['id'];
                    // Le client de la vente prime : on évite deux fiches pour
                    // la même personne.
                    if (!empty($vente['client_id'])) {
                        $clientId = (int)$vente['client_id'];
                    }
                }
            }

            // ── Dossier ────────────────────────────────────────────────────
            $depotDt = $dateDepot !== ''
                ? date('Y-m-d H:i:s', strtotime($dateDepot))
                : date('Y-m-d H:i:s');

            $db->execute(
                'INSERT INTO pressing_commandes
                    (vente_id, numero, facture, client_id, date_depot, shop_id, user_id, created_at)
                 VALUES (:vente, :numero, :facture, :client, :depot, :shop, :user, :created)',
                [
                    ':vente'   => $venteId,
                    ':numero'  => 'TMP',
                    ':facture' => substr($facture, 0, 30),
                    ':client'  => $clientId,
                    ':depot'   => $depotDt,
                    ':shop'    => $shopId,
                    // user_id = 0 violerait la FK vers `utilisateurs` : on le
                    // laisse NULL (colonne nullable, ON DELETE SET NULL).
                    ':user'    => $userId > 0 ? $userId : null,
                    ':created' => date('Y-m-d H:i:s'),
                ]
            );
            $commandeId = (int)$pdo->lastInsertId();

            // Numéro lisible : PR-00001 (comme l'appli autonome)
            $numero = 'PR-' . str_pad((string)$commandeId, 5, '0', STR_PAD_LEFT);
            $db->execute(
                'UPDATE pressing_commandes SET numero = ? WHERE id = ?',
                [$numero, $commandeId]
            );

            // ── Vêtements ──────────────────────────────────────────────────
            $articleModel = new PressingArticle();
            foreach ($articles as $a) {
                $delaiType = $a['delai_type'];
                $heures = $delaiType === 'perso'
                    ? max(1, (int)$a['delai_heures'])
                    : (PressingCommande::DELAIS[$delaiType] ?? 48);

                // Le coefficient MULTIPLIE le prix unitaire.
                //
                // La source autonome applique la majoration dans le JS
                // (`app.js` : `prix.value = base * coef` à la saisie), d'où
                // les données réelles : `COTIS` à 69 000 avec un coef de
                // 1.25 est stocké à 86 250 (= 69 000 × 1.25).
                //
                // Contrat de l'API POS : `prix_unitaire` est le prix de
                // BASE et `coefficient` le multiplicateur. La majoration est
                // calculée ICI, côté serveur, qui est la seule source de
                // vérité — le total facturé ne doit pas dépendre de ce que
                // le client HTTP a envoyé.
                $prix = (float)$a['prix_unitaire'];
                $coef = (float)$a['coefficient'];
                if ($coef <= 0) {
                    $coef = 1;
                }
                $prix = round($prix * $coef, 2);
                $articleModel->create($commandeId, [
                    'type_vetement' => $a['type_vetement'],
                    'couleur'       => $a['couleur'],
                    'quantite'      => $a['quantite'],
                    'prix_unitaire' => $prix,
                    'coefficient'   => $a['coefficient'],
                    'observation'   => $a['observation'],
                    'delai_type'    => $delaiType,
                    'delai_heures'  => $heures,
                    'date_dispo'    => date('Y-m-d H:i:s', strtotime($depotDt) + $heures * 3600),
                    'etape'         => 0,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[pressing] store error: ' . $e->getMessage());
            $this->jsonOut([
                'success' => false,
                'message' => 'Erreur lors de la création du dossier : ' . $e->getMessage(),
            ], 500);
            return;
        }

        $this->logAudit('create', 'pressing_commande', $commandeId, [
            'numero'   => $numero,
            'facture'  => $facture,
            'vente_id' => $venteId,
            'articles' => count($articles),
        ]);

        $commande = (new PressingCommande())->findWithDetail($commandeId, $shopId);

        $this->jsonOut([
            'success' => true,
            'message' => "Commande $numero enregistrée (" . count($articles) . ' article(s)).',
            'data'    => $commande,
        ]);
    }

    /**
     * POST /api/pressing/articles/[i:id]/advance — avance un vêtement d'une étape.
     *
     * Règle métier : la remise au client (étape 6) est refusée si le dossier
     * n'est pas entièrement payé.
     */
    public function advance($id): void
    {
        if (!$this->guardWrite()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();

        $articleModel = new PressingArticle();
        $article = $articleModel->findById($id);
        if (!$article) {
            $this->jsonOut(['success' => false, 'message' => 'Vêtement introuvable'], 404);
            return;
        }

        $commandeModel = new PressingCommande();
        $shopId = $this->shopScope();

        // Vérifie que le dossier appartient bien à la boutique de l'utilisateur.
        $commande = $commandeModel->findWithDetail((int)$article['commande_id'], $shopId);
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Vêtement introuvable'], 404);
            return;
        }

        if ((int)$article['etape'] >= PressingCommande::ETAPE_REMISE) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Vêtement déjà remis au client',
            ], 422);
            return;
        }

        $nouvelle = (int)$article['etape'] + 1;

        // Remise : le dossier doit être soldé
        if ($nouvelle === PressingCommande::ETAPE_REMISE) {
            $solde = $commandeModel->solde((int)$article['commande_id']);
            if ($solde > 0.001) {
                $this->jsonOut([
                    'success' => false,
                    'message' => 'Remise impossible : la commande n\'est pas entièrement payée (solde '
                                 . PressingCommande::formatMontant($solde) . ').',
                ], 422);
                return;
            }
        }

        $articleModel->setEtape($id, $nouvelle);
        $this->journaliserEtape($id, $nouvelle, $input);

        [$code, $label] = PressingCommande::etatCommande(
            $commandeModel->articlesEtapes((int)$article['commande_id'])
        );

        $this->jsonOut([
            'success'     => true,
            'etape'       => $nouvelle,
            'etape_label' => PressingCommande::ETAPES[$nouvelle] ?? '?',
            'etat_code'   => $code,
            'etat'        => $label,
        ]);
    }

    /**
     * POST /api/pressing/articles/[i:id]/recede — revient en arrière d'une étape.
     *
     * Correction d'erreur : l'historique n'est pas perdu, le retour est
     * journalisé comme les avancements.
     */
    public function recede($id): void
    {
        if (!$this->guardWrite()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();

        $articleModel = new PressingArticle();
        $article = $articleModel->findById($id);
        if (!$article) {
            $this->jsonOut(['success' => false, 'message' => 'Vêtement introuvable'], 404);
            return;
        }

        $commandeModel = new PressingCommande();
        $commande = $commandeModel->findWithDetail((int)$article['commande_id'], $this->shopScope());
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Vêtement introuvable'], 404);
            return;
        }

        if ((int)$article['etape'] <= 0) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Vêtement déjà à l\'étape initiale',
            ], 422);
            return;
        }

        $nouvelle = (int)$article['etape'] - 1;

        $articleModel->setEtape($id, $nouvelle);
        $this->journaliserEtape($id, $nouvelle, $input);

        [$code, $label] = PressingCommande::etatCommande(
            $commandeModel->articlesEtapes((int)$article['commande_id'])
        );

        $this->jsonOut([
            'success'     => true,
            'etape'       => $nouvelle,
            'etape_label' => PressingCommande::ETAPES[$nouvelle] ?? '?',
            'etat_code'   => $code,
            'etat'        => $label,
        ]);
    }

    /**
     * POST /api/pressing/commandes/[i:id]/advance-all — avance TOUS les
     * vêtements d'un dossier d'une étape.
     *
     * Les remises (étape 6) sont bloquées tant que le solde n'est pas réglé,
     * mais les autres vêtements avancent normalement.
     */
    public function advanceAll($id): void
    {
        if (!$this->guardWrite()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();

        $commandeModel = new PressingCommande();
        $commande = $commandeModel->findWithDetail($id, $this->shopScope());
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Dossier introuvable'], 404);
            return;
        }

        $articles = $commandeModel->articlesEtapes($id);
        $aAvancer = array_values(array_filter(
            $articles,
            fn ($a) => (int)$a['etape'] < PressingCommande::ETAPE_REMISE
        ));

        if (count($aAvancer) === 0) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Tous les vêtements sont déjà remis.',
            ], 422);
            return;
        }

        $solde = $commandeModel->solde($id);

        $articleModel = new PressingArticle();
        $avances = 0;
        $bloques = 0;

        foreach ($aAvancer as $a) {
            $nouvelle = (int)$a['etape'] + 1;

            if ($nouvelle === PressingCommande::ETAPE_REMISE && $solde > 0.001) {
                $bloques++;
                continue;
            }

            $articleModel->setEtape((int)$a['id'], $nouvelle);
            $this->journaliserEtape((int)$a['id'], $nouvelle, $input);
            $avances++;
        }

        [$code, $label] = PressingCommande::etatCommande(
            $commandeModel->articlesEtapes($id)
        );

        $message = "$avances vêtement(s) avancé(s).";
        if ($bloques > 0) {
            $message .= " $bloques remise(s) bloquée(s) : solde "
                        . PressingCommande::formatMontant($solde) . ' non payé.';
        }

        $this->jsonOut([
            'success'  => $avances > 0,
            'message'  => $message,
            'avances'  => $avances,
            'bloques'  => $bloques,
            'etat_code' => $code,
            'etat'     => $label,
        ]);
    }

    /**
     * POST /api/pressing/commandes/[i:id]/pay — encaisse un paiement.
     *
     * Body : { montant, mode, valide_par? }
     */
    public function pay($id): void
    {
        if (!$this->guardWrite()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();

        $commandeModel = new PressingCommande();
        $commande = $commandeModel->findWithDetail($id, $this->shopScope());
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Dossier introuvable'], 404);
            return;
        }

        $montant = (float)($input['montant'] ?? 0);
        if ($montant <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'Montant invalide'], 422);
            return;
        }

        $mode = (string)($input['mode'] ?? 'ESPECES');
        $paiementModel = new PressingPaiement();
        $result = $paiementModel->create(
            $id,
            $montant,
            $mode,
            (int)($_SESSION['user_id'] ?? 0)
        );

        if (empty($result['success'])) {
            $this->jsonOut($result, 422);
            return;
        }

        $this->logAudit('pay', 'pressing_commande', $id, [
            'montant' => $montant,
            'mode'    => $mode,
            'solde'   => $result['solde'],
        ]);

        $this->jsonOut([
            'success' => true,
            'paye'    => $result['paye'],
            'total'   => $result['total'],
            'solde'   => $result['solde'],
        ]);
    }

    /**
     * POST /api/pressing/commandes/[i:id]/delete — supprime un dossier.
     *
     * La suppression n'est autorisée que si TOUS les vêtements ont été
     * remis au client (règle de l'appli autonome).
     */
    public function destroy($id): void
    {
        if (!$this->guardDelete()) {
            return;
        }

        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;

        $commandeModel = new PressingCommande();
        $commande = $commandeModel->findWithDetail($id, $this->shopScope());
        if (!$commande) {
            $this->jsonOut(['success' => false, 'message' => 'Dossier introuvable'], 404);
            return;
        }

        $enCours = 0;
        foreach ($commande['articles'] as $a) {
            if ((int)$a['etape'] < PressingCommande::ETAPE_REMISE) {
                $enCours++;
            }
        }

        if ($enCours > 0) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Suppression impossible : le dossier n\'est pas entièrement remis au client.',
            ], 422);
            return;
        }

        // Les paiements et les vêtements sont supprimés en cascade (FK).
        \App\Core\Database::getInstance()->execute(
            'DELETE FROM pressing_commandes WHERE id = ?',
            [$id]
        );

        $this->logAudit('delete', 'pressing_commande', $id, [
            'numero' => $commande['numero'] ?? '',
        ]);

        $this->jsonOut(['success' => true]);
    }

    /**
     * POST /api/pressing/facture/lookup — relit une facture DGI auprès d'OSAT.
     *
     * Remplace la lecture directe de la base `u138949110_osat.facture_dgi_ok`
     * de l'appli autonome (absente de ce projet).
     *
     * Body : { facture, isf? }
     */
    public function lookupFacture(): void
    {
        if (!$this->guardRead()) {
            return;
        }

        $input = $this->jsonInput();
        $facture = trim((string)($input['facture'] ?? ''));

        if ($facture === '') {
            $this->jsonOut([
                'success' => false,
                'message' => 'Numéro de facture requis.',
            ], 422);
            return;
        }

        // ISF : celui de la boutique, sauf si la vente est déjà connue
        // (son ISF figé est plus fiable).
        $isf = trim((string)($input['isf'] ?? ''));
        $vente = null;

        if ($isf === '') {
            $vente = (new PressingCommande())->findVenteParFacture(
                $facture,
                $this->shopScope() ?: null
            );
            if ($vente && !empty($vente['store_isf'])) {
                $isf = trim((string)$vente['store_isf']);
            } else {
                $isf = $this->resolverIsfBoutique();
            }
        }

        if ($isf === '') {
            $this->jsonOut([
                'success' => false,
                'message' => 'ISF de la boutique introuvable : renseignez-le dans /shops.',
            ], 422);
            return;
        }

        $result = (new DgiSyncService())->lookupFacture(
            $facture,
            $isf,
            $vente ?? []
        );

        if (empty($result['success'])) {
            $this->jsonOut([
                'success' => false,
                'message' => $result['message'],
            ], 422);
            return;
        }

        $d = $result['data'] ?? [];

        // OSAT renvoie `articles` et `payment_type` sous forme de chaînes
        // JSON : il faut les décoder.
        $articles = json_decode((string)($d['articles'] ?? '[]'), true);
        if (!is_array($articles)) {
            $articles = [];
        }

        $liste = array_map(fn ($a) => [
            'name'     => (string)($a['name'] ?? ''),
            'price'    => (float)($a['price'] ?? 0),
            'quantity' => (int)($a['quantity'] ?? 1),
        ], $articles);

        $this->jsonOut([
            'success'       => true,
            'client_name'   => (string)($d['client_name'] ?? ''),
            'client_number' => (string)($d['client_number'] ?? ''),
            'client_nif'    => (string)($d['client_nif'] ?? ''),
            'invoice'       => (string)($d['invoice_number'] ?? $facture),
            'total'         => (string)($d['total'] ?? ''),
            'isf'           => $isf,
            'articles'      => $liste,
        ]);
    }

    /**
     * GET /api/pressing/ventes-sans-commande — ventes POS qui n'ont pas
     * encore de dossier de pressing.
     *
     * C'est la vue « assistance » : elle permet de voir, depuis le POS,
     * quelles factures attendent un pressing, et de le démarrer en un clic.
     */
    public function ventesSansCommande(): void
    {
        if (!$this->guardRead()) {
            return;
        }

        $ventes = (new PressingCommande())->ventesSansCommande($this->shopScope());

        $this->jsonOut([
            'success' => true,
            'count'   => count($ventes),
            'data'    => $ventes,
        ]);
    }

    /**
     * Journalise un changement d'étape dans `audit_logs`.
     *
     * L'appli autonome avait une table dédiée `suivi_articles_pressing` ;
     * elle a été supprimée au profit de `audit_logs`, dont c'est exactement
     * la fonction (qui / quoi / quand).
     *
     * @param int    $articleId
     * @param int    $etape
     * @param array  $input Corps de la requête (pour `valide_par`)
     */
    private function journaliserEtape(int $articleId, int $etape, array $input): void
    {
        $validePar = trim((string)($input['valide_par'] ?? ''));
        if ($validePar === '') {
            $validePar = (string)($_SESSION['nom_complet'] ?? '');
        }

        $this->logAudit(
            'pressing_step',
            'pressing_article',
            $articleId,
            [
                'etape'      => $etape,
                'label'      => PressingCommande::ETAPES[$etape] ?? '?',
                'valide_par' => $validePar,
            ]
        );
    }

    /**
     * Résout l'ISF de l'utilisateur connecté.
     *
     * L'ISF est une propriété de la BOUTIQUE : on prend donc toujours
     * celui de la boutique en session (`$_SESSION['shop_id'] → shops.isf`),
     * y compris pour le super_admin qui a une boutique rattachée.
     *
     * Repli (super_admin sans boutique) : `company_info.isf`, l'ISF société.
     *
     * @return string ISF, ou '' si aucun n'est configuré
     */
    private function resolverIsfBoutique(): string
    {
        $shopId = $this->getShopId();
        if ($shopId) {
            $shop = (new Shop())->findById($shopId);
            if ($shop && !empty($shop['isf'])) {
                return trim((string)$shop['isf']);
            }
        }

        // Repli : super_admin sans boutique rattachée → ISF de la société.
        if ($this->isSuperAdmin()) {
            try {
                $info = (new \App\Models\CompanyInfo())->get();
                if (!empty($info['isf'])) {
                    return trim((string)$info['isf']);
                }
            } catch (\Throwable $e) {
                error_log('[pressing] company_info error: ' . $e->getMessage());
            }
        }

        return '';
    }

    /**
     * Valide une couleur de vêtement.
     */
    private function couleurValide($couleur): string
    {
        $couleur = trim((string)$couleur);
        if (isset(self::COULEURS[$couleur])) {
            return $couleur;
        }
        // Une couleur hexadécimale est acceptée telle quelle.
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $couleur)) {
            return $couleur;
        }
        return 'Gris';
    }

    /**
     * Valide un type de délai.
     */
    private function delaiValide($delai): string
    {
        return in_array($delai, ['normal', 'express', 'perso'], true) ? $delai : 'normal';
    }
}
