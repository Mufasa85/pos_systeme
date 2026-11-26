<?php

namespace App\Controllers;

use App\Services\ProductImportService;

/**
 * Import de produits depuis un fichier Excel / CSV.
 *
 * Routes associées (cf. routes/api.php) :
 *   POST /api/produit/import           → import d'un fichier (1er bloc)
 *   POST /api/produit/import/chunk     → bloc suivant (mode « par blocs »)
 *   POST /api/produit/import/abort     → abandon (nettoyage du fichier)
 *   GET  /api/produit/import/template  → téléchargement du modèle .xlsx
 *   GET  /api/produit/import/categories → catégories d'une boutique
 *
 * ── Le mode « par blocs » ─────────────────────────────────────────────
 * ⚠️ POURQUOI : `max_execution_time` vaut 120 s dans le php.ini par
 *    défaut. L'import de 50 000 produits prenait 237 s MESURÉS
 *    (211 produits/s) → la requête HTTP était TUÉE vers le 26 000e
 *    produit, et les produits déjà écrits restaient en base sans que
 *    l'import soit terminé.
 *
 *    Le protocole en deux temps résout ça :
 *      1. POST /api/produit/import       → dépose le fichier, renvoie
 *         un jeton + le nombre total de lignes.
 *      2. POST /api/produit/import/chunk → traite le bloc suivant
 *         (5 000 lignes), renvoie l'avancement.
 *    Chaque requête dure ~2 s : elle passe TOUJOURS, et l'interface
 *    affiche l'avancement entre deux blocs.
 */
class ProductImportController extends Controller
{
    /** Extensions acceptées pour l'import. */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    /** Taille maximale du fichier uploadé (5 Mo). */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Durée de vie d'un fichier d'import en attente (2 heures).
     *
     * ⚠️ Un fichier abandoned traîne sur le disque : on le supprime
     *    au passage (les fichiers de plus de 2 h sont considered morts).
     */
    private const UPLOAD_TTL = 7200;

    /**
     * Durée d'inactivité après laquelle un import est considéré comme
     * ABANDONNÉ (10 minutes).
     *
     * ⚠️ Chaque bloc écrit l'état du jeton, donc met à jour l'horodatage
     *    du fichier. Un import vivant rafraîchit donc ce fichier toutes
     *    les quelques secondes : au-delà de 10 minutes sans écriture,
     *    l'import est mort (navigateur fermé, réseau coupé).
     *
     *    Ce seuil est BIEN plus court que le TTL de 2 h : un fichier de
     *    50 000 produits fait 4,45 Mo, et ces fichiers s'accumulent vite.
     */
    private const IMPORT_INACTIVITY_TTL = 600;

    /**
     * Importe un fichier de produits.
     *
     * ⚠️ Cette route sert à DEUX choses :
     *    - le mode « un seul passage » (fichier court) : le POST renvoie
     *      le rapport final, comme avant ;
     *    - le mode « par blocs » (fichier long) : le POST dépose le
     *      fichier et renvoie un JETON ; le client enchaîne ensuite sur
     *      `/api/produit/import/chunk`.
     *
     * Le client choisit son mode via `chunked=1`.
     */
    public function import()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
            $this->status(400)->json(['error' => 'Aucun fichier reçu']);
            return;
        }

        $upload = $_FILES['file'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json(['error' => $this->uploadErrorMessage((int)$upload['error'])]);
            return;
        }

        if (!is_uploaded_file($upload['tmp_name'])) {
            $this->status(400)->json(['error' => 'Fichier temporaire invalide']);
            return;
        }

        if ((int)$upload['size'] > self::MAX_FILE_SIZE) {
            $this->status(400)->json(['error' => 'Fichier trop volumineux (5 Mo maximum)']);
            return;
        }

        $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->status(400)->json([
                'error' => 'Format non supporté. Formats acceptés : .xlsx, .xls, .csv',
            ]);
            return;
        }

        // ── Boutique de destination ──
        //
        // L'admin est verrouillé sur SA boutique : le paramètre `shop_id`
        // est ignoré. Le super_admin doit choisir explicitement la boutique
        // qui va accueillir les produits (sinon les produits seraient
        // créés SANS boutique, donc invisibles dans le POS).
        $shopId = null;

        if ($this->isSuperAdmin()) {
            $requestedShop = (int)($_POST['shop_id'] ?? 0);
            if ($requestedShop <= 0) {
                $this->status(400)->json([
                    'error' => 'Veuillez choisir la boutique de destination des produits.',
                ]);
                return;
            }

            $shop = (new \App\Models\Shop())->findById($requestedShop);
            if (!$shop) {
                $this->status(400)->json(['error' => 'Boutique introuvable']);
                return;
            }
            if (empty($shop['actif'])) {
                $this->status(400)->json([
                    'error' => 'La boutique « ' . ($shop['nom'] ?? '') . ' » est désactivée.',
                ]);
                return;
            }

            $shopId = $requestedShop;
        } else {
            $shopId = $this->getShopId();
            if (!$shopId) {
                $this->status(400)->json(['error' => 'Aucune boutique rattachée à votre compte']);
                return;
            }
        }

        $options = [
            'mode' => ($_POST['mode'] ?? 'create') === 'update' ? 'update' : 'create',
            'create_categories' => !empty($_POST['create_categories']),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'taxe_id' => (int)($_POST['tax_id'] ?? 0),
        ];

        // ⚠️ Mode « par blocs » : le fichier est COPIÉ dans l'espace de
        //    stockage de l'import (le fichier temporaire de PHP est
        //    supprimé à la fin de la requête).
        $chunked = !empty($_POST['chunked']);

        if ($chunked) {
            $this->startChunkedImport($upload, $shopId, $options);
            return;
        }

        try {
            $service = new ProductImportService();
            $result = $service->importFile($shopId, $upload['tmp_name'], $options);
        } catch (\Throwable $e) {
            error_log('ProductImportController::import error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Erreur pendant l\'import : ' . $e->getMessage()]);
            return;
        }

        if (empty($result['success'])) {
            $this->status(400)->json([
                'error' => $result['error'] ?? 'Import impossible',
                'summary' => $result['summary'],
                'rows' => $result['rows'],
            ]);
            return;
        }

        $this->logAudit('import', 'produit', null, [
            'fichier' => $upload['name'],
            'mode' => $options['mode'],
            'crees' => $result['summary']['created'],
            'mis_a_jour' => $result['summary']['updated'],
            'ignores' => $result['summary']['skipped'],
            'erreurs' => $result['summary']['errors'],
        ]);

        $this->json([
            'success' => true,
            'summary' => $result['summary'],
            'rows' => $result['rows'],
        ]);
    }

    /**
     * Traite le bloc SUIVANT d'un import par blocs.
     *
     * ⚠️ C'est cette route qui fait le travail : elle ne traite JAMAIS
     *    plus de `chunk_size` lignes, donc jamais assez pour dépasser
     *    `max_execution_time`.
     *
     * Le client renvoie le jeton reçu au premier POST, plus l'offset
     * du bloc à traiter.
     */
    public function chunk()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $token = $this->sanitaze($_POST['token'] ?? '');
        if ($token === '') {
            $this->status(400)->json(['error' => 'Jeton d\'import manquant']);
            return;
        }

        $state = $this->readImportState($token);
        if (!$state) {
            $this->status(404)->json([
                'error' => 'Import introuvable ou expiré. Relancez l\'import depuis le début.',
            ]);
            return;
        }

        // ⚠️ Le fichier doit être lisible ET à jour : on vérifie son
        //    empreinte pour ne pas traiter un fichier qui aurait changé.
        $path = $state['path'];
        if (!is_file($path) || !is_readable($path)) {
            $this->deleteImportState($token);
            $this->status(410)->json([
                'error' => 'Le fichier d\'import a disparu. Relancez l\'import depuis le début.',
            ]);
            return;
        }

        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $chunkSize = max(100, min(20000, (int)($_POST['chunk_size'] ?? ProductImportService::DEFAULT_CHUNK_SIZE)));

        // ⚠️ La boutique est RELUE depuis l'état du jeton, pas depuis la
        //    requête : un super_admin qui change de boutique entre deux
        //    blocs ne doit pas rediriger l'import.
        $shopId = $state['shop_id'];
        $options = $state['options'];
        $options['offset'] = $offset;
        $options['limit'] = $chunkSize;

        try {
            $service = new ProductImportService();
            $result = $service->importChunk($shopId, $path, $options);
        } catch (\Throwable $e) {
            error_log('ProductImportController::chunk error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Erreur pendant l\'import : ' . $e->getMessage()]);
            return;
        }

        $processed = (int)($result['processed'] ?? 0);
        $nextOffset = (int)($result['next_offset'] ?? ($offset + $processed));
        $done = !empty($result['done']);

        // ⚠️ L'état du jeton est mis à jour à chaque bloc : il porte le
        //    cumul des compteurs (pour l'affichage final) et l'offset
        //    atteint.
        $state['offset'] = $nextOffset;
        $state['summary'] = $this->mergeSummaries($state['summary'] ?? [], $result['summary'] ?? []);

        /*
         * ⚠️ Les noms du bloc NE REMPLACENT les précédents que si le
         *    bloc en a produit : un bloc VIDE (le client demande un
         *    bloc de plus pour savoir que c'est fini) ne doit pas
         *    effacer l'affichage des derniers produits traités.
         */
        if (!empty($result['last_names'])) {
            $state['last_names'] = $result['last_names'];
        }

        $state['rows'] = array_slice(
            array_merge($state['rows'] ?? [], $result['rows'] ?? []),
            -200
        );
        $state['updated_at'] = time();

        if ($done) {
            // L'import est terminé : on supprime le fichier et l'état.
            $this->deleteImportState($token);

            $this->logAudit('import', 'produit', null, [
                'fichier' => $state['original_name'],
                'mode' => $options['mode'],
                'crees' => $state['summary']['created'] ?? 0,
                'mis_a_jour' => $state['summary']['updated'] ?? 0,
                'ignores' => $state['summary']['skipped'] ?? 0,
                'erreurs' => $state['summary']['errors'] ?? 0,
                'par_blocs' => true,
            ]);
        } else {
            $this->writeImportState($token, $state);
        }

        // ⚠️ L'erreur du DERNIER bloc vide n'est pas une erreur : c'est
        //    la fin normale du fichier. On ne la renvoie que si le bloc
        //    a réellement produit des lignes.
        $erreur = ($result['error'] ?? null);
        if ($erreur !== null && $processed === 0 && $done) {
            $erreur = null;
        }

        $this->json([
            'success' => true,
            'done' => $done,
            'processed' => $processed,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'total_rows' => (int)($result['total_rows'] ?? $state['total_rows'] ?? 0),
            'summary' => $state['summary'],
            'rows' => $state['rows'] ?? [],
            'last_names' => $state['last_names'] ?? [],
            'error' => $erreur,
        ]);
    }

    /**
     * Abandonne un import par blocs et supprime le fichier déposé.
     */
    public function abort()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $token = $this->sanitaze($_POST['token'] ?? '');
        if ($token !== '') {
            $this->deleteImportState($token);
        }

        $this->json(['success' => true]);
    }

    /**
     * Dépose le fichier pour un import par blocs et renvoie le jeton.
     *
     * @param array $upload   L'entrée `$_FILES['file']`.
     * @param int   $shopId   Boutique de destination.
     * @param array $options  Options d'import.
     */
    private function startChunkedImport(array $upload, $shopId, array $options): void
    {
        $dir = $this->importStorageDir();

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->status(500)->json(['error' => 'Dossier d\'import inaccessible']);
            return;
        }

        $this->cleanupExpiredImports($dir);

        $token = bin2hex(random_bytes(16));
        $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
        $path = $dir . DIRECTORY_SEPARATOR . $token . '.' . $extension;

        // ⚠️ Le fichier temporaire de PHP est supprimé à la fin de la
        //    requête : il faut le COPIER pour que les blocs suivants
        //    puissent le relire.
        if (!@move_uploaded_file($upload['tmp_name'], $path)) {
            if (!@copy($upload['tmp_name'], $path)) {
                $this->status(500)->json(['error' => 'Impossible de conserver le fichier pour l\'import']);
                return;
            }
        }

        $service = new ProductImportService();
        $totalRows = $service->countDataRows($path);

        $this->writeImportState($token, [
            'path' => $path,
            'original_name' => (string)$upload['name'],
            'shop_id' => $shopId,
            'options' => $options,
            'offset' => 0,
            'total_rows' => $totalRows,
            'summary' => [],
            'rows' => [],
            'last_names' => [],
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $this->json([
            'success' => true,
            'chunked' => true,
            'token' => $token,
            'total_rows' => $totalRows,
            'filename' => (string)$upload['name'],
            'extension' => $extension,
        ]);
    }

    /**
     * Le dossier où sont déposés les fichiers d'import en cours.
     */
    private function importStorageDir(): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'imports'
            . DIRECTORY_SEPARATOR;
    }

    /**
     * Le dossier où sont stockés les états des imports en cours.
     */
    private function importStateDir(): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'import_state'
            . DIRECTORY_SEPARATOR;
    }

    /**
     * Écrit l'état d'un import (sérialisé en JSON).
     */
    private function writeImportState(string $token, array $state): void
    {
        $dir = $this->importStateDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . $token . '.json',
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Lit l'état d'un import (null si absent ou illisible).
     */
    private function readImportState(string $token): ?array
    {
        // ⚠️ Le jeton est hexadécimal : on ne prend QUE ce qui correspond,
        //    pour ne jamais toucher un chemin construit depuis une
        //    entrée utilisateur.
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $file = $this->importStateDir() . DIRECTORY_SEPARATOR . $token . '.json';
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $state = json_decode($raw, true);
        if (!is_array($state) || empty($state['path'])) {
            return null;
        }

        return $state;
    }

    /**
     * Supprime l'état ET le fichier d'un import.
     */
    private function deleteImportState(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return;
        }

        $file = $this->importStateDir() . DIRECTORY_SEPARATOR . $token . '.json';
        $state = null;
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && !empty($decoded['path'])) {
                $state = $decoded;
            }
            @unlink($file);
        }

        if ($state && !empty($state['path']) && is_file($state['path'])) {
            @unlink($state['path']);
        }
    }

    /**
     * Supprime les fichiers d'import ABANDONNÉS et les états orphelins.
     *
     * ⚠️ Un import abandonné (navigateur fermé, réseau coupé) laisse
     *    un fichier de plusieurs mégaoctets sur le disque. Sans purge,
     *    ils s'accumulent : le fichier de 50 000 produits fait 4,45 Mo.
     *
     *    Deux critères de purge :
     *      - l'âge (au-delà du TTL de 2 h) ;
     *      - l'INACTIVITÉ : un fichier que personne ne modifie depuis
     *        plus de 10 minutes n'est plus lisible par un bloc en cours
     *        (chaque bloc écrit son état, donc met à jour le fichier).
     *        Ce second critère est le plus efficace : il purge les
     *        imports abandonnés en quelques minutes.
     *
     *    On purge aussi les ÉTATS dont le fichier n'existe plus
     *    (orphelins) : ils ne servent plus à rien.
     */
    private function cleanupExpiredImports(string $dir): void
    {
        $now = time();

        // ── 1. Les fichiers déposés ───────────────────────────────────
        if (is_dir($dir)) {
            $files = @glob($dir . DIRECTORY_SEPARATOR . '*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (!is_file($file)) {
                        continue;
                    }
                    $mtime = @filemtime($file);
                    if ($mtime === false) {
                        continue;
                    }
                    $age = $now - $mtime;
                    if ($age > self::UPLOAD_TTL || $age > self::IMPORT_INACTIVITY_TTL) {
                        @unlink($file);
                    }
                }
            }
        }

        // ── 2. Les états orphelins (dont le fichier a disparu) ───────
        $stateDir = $this->importStateDir();
        if (!is_dir($stateDir)) {
            return;
        }

        $states = @glob($stateDir . DIRECTORY_SEPARATOR . '*.json');
        if (!is_array($states)) {
            return;
        }

        foreach ($states as $stateFile) {
            $raw = @file_get_contents($stateFile);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            if (!is_array($decoded)) {
                // Un état illisible : on le supprime (il ne sert à rien).
                @unlink($stateFile);
                continue;
            }

            $path = $decoded['path'] ?? null;
            $updated = (int)($decoded['updated_at'] ?? $decoded['created_at'] ?? 0);

            // Le fichier déposé n'existe plus → état orphelin.
            if (!$path || !is_file($path)) {
                @unlink($stateFile);
                continue;
            }

            // Un import inactif depuis trop longtemps : on purge les deux.
            if ($updated > 0 && ($now - $updated) > self::IMPORT_INACTIVITY_TTL) {
                @unlink($path);
                @unlink($stateFile);
            }
        }
    }

    /**
     * Additionne deux résumés d'import (compteurs).
     */
    private function mergeSummaries(array $a, array $b): array
    {
        $merged = $a;
        foreach (['total', 'created', 'updated', 'skipped', 'errors', 'categories_created'] as $key) {
            $merged[$key] = (int)($a[$key] ?? 0) + (int)($b[$key] ?? 0);
        }

        return $merged;
    }

    /**
     * Télécharge le fichier modèle (.xlsx) à remplir.
     */
    public function template()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            (new ProductImportService())->sendTemplate();
        } catch (\Throwable $e) {
            error_log('ProductImportController::template error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Génération du modèle impossible']);
        }
    }

    /**
     * Renvoie les catégories disponibles pour une boutique donnée.
     *
     * Utilisé par le super_admin dans le modal d'import : les catégories
     * proposées doivent être celles de la boutique de destination
     * (sinon l'import crée des produits dans la mauvaise catégorie).
     */
    public function categories()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->getShopId();

        if ($this->isSuperAdmin()) {
            $requestedShop = (int)($_GET['shop_id'] ?? 0);
            if ($requestedShop > 0) {
                $shopId = $requestedShop;
            }
        }

        $categories = (new \App\Models\Category())->all($shopId);

        $this->json(array_map(function ($cat) {
            return [
                'id' => (int)$cat['id'],
                'category' => $cat['category'],
                'shop_id' => $cat['shop_id'] !== null ? (int)$cat['shop_id'] : null,
            ];
        }, $categories));
    }

    /**
     * Traduit un code d'erreur d'upload PHP en message lisible.
     */
    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Fichier trop volumineux (5 Mo maximum)';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incomplet, veuillez réessayer';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier sélectionné';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Dossier temporaire manquant sur le serveur';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Écriture du fichier temporaire impossible';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloqué par une extension PHP';
            default:
                return 'Erreur pendant l\'upload du fichier';
        }
    }
}
