<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Models\Product;
use App\Models\User;

/**
 * Sert les images uploadées (produits, profils) stockées hors du dossier public/,
 * avec vérification d'authentification et isolation multi-boutique.
 */
class MediaController extends Controller
{
    private function storageDir(string $sub): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $sub;
    }

    private function stream(string $filepath): void
    {
        if (!is_file($filepath)) {
            $this->status(404)->json(['error' => 'Image introuvable']);
            return;
        }

        $mime = function_exists('mime_content_type') ? (mime_content_type($filepath) ?: 'application/octet-stream') : 'application/octet-stream';
        $etag = '"' . md5_file($filepath) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT';

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if (trim($ifNoneMatch) === $etag) {
            http_response_code(304);
            return;
        }

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=86400');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $lastModified);
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
    }

    // GET /media/product/[*:filename]
    public function product($params)
    {
        if (!$this->requireAuth()) return;

        $filename = basename($params['filename'] ?? '');
        if ($filename === '') {
            $this->status(400)->json(['error' => 'Nom de fichier manquant']);
            return;
        }

        if (!$this->isSuperAdmin()) {
            $productModel = new Product();
            $product = $productModel->findByImage('media/product/' . $filename);
            if (!$product || (string)$product['shop_id'] !== (string)$this->getShopId()) {
                $this->status(403)->json(['error' => 'Accès refusé']);
                return;
            }
        }

        $this->stream($this->storageDir('products') . DIRECTORY_SEPARATOR . $filename);
    }

    // GET /media/profile/[*:filename]
    public function profile($params)
    {
        if (!$this->requireAuth()) return;

        $filename = basename($params['filename'] ?? '');
        if ($filename === '') {
            $this->status(400)->json(['error' => 'Nom de fichier manquant']);
            return;
        }

        if (!$this->isSuperAdmin()) {
            $userModel = new User();
            $owner = $userModel->findByProfileImage('/media/profile/' . $filename);
            $isSelf = $owner && (string)$owner['id'] === (string)($_SESSION['user_id'] ?? '');
            $sameShop = $owner && $owner['shop_id'] !== null && (string)$owner['shop_id'] === (string)$this->getShopId();
            if (!$owner || (!$isSelf && !$sameShop)) {
                $this->status(403)->json(['error' => 'Accès refusé']);
                return;
            }
        }

        $this->stream($this->storageDir('profiles') . DIRECTORY_SEPARATOR . $filename);
    }
}
