<?php

namespace App\Services;

/**
 * Redimensionne et compresse les images uploadées (produits, profils) avec GD.
 * Objectif : réduire le poids des fichiers servis (perf) sans dépendance externe.
 */
class ImageProcessor
{
    /**
     * Vérifie que le fichier est réellement une image (contenu, pas juste l'extension/MIME client)
     * et retourne ses infos GD (0 => width, 1 => height, 2 => IMAGETYPE_*) ou null si invalide.
     */
    public static function validate(string $tmpPath): ?array
    {
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            return null;
        }

        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($info[2], $allowedTypes, true)) {
            return null;
        }

        return $info;
    }

    /**
     * Redimensionne (si nécessaire) et réencode l'image vers $destPath en JPEG compressé.
     * Conserve le ratio, ne fait jamais d'agrandissement.
     *
     * @param string $tmpPath  Fichier source (ex: $_FILES[...]['tmp_name'])
     * @param string $destPath Chemin de destination complet (extension .jpg attendue)
     * @param int    $maxWidth Largeur maximale en pixels
     * @param int    $quality  Qualité JPEG (0-100)
     */
    public static function resizeAndSave(string $tmpPath, string $destPath, int $maxWidth = 1000, int $quality = 82): bool
    {
        $info = self::validate($tmpPath);
        if (!$info) {
            return false;
        }

        [$width, $height, $type] = $info;

        $source = self::createFromType($tmpPath, $type);
        if (!$source) {
            return false;
        }

        $ratio = $width > $maxWidth ? $maxWidth / $width : 1;
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Fond blanc pour les PNG/GIF transparents convertis en JPEG
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $success = imagejpeg($canvas, $destPath, $quality);

        imagedestroy($source);
        imagedestroy($canvas);

        return $success;
    }

    private static function createFromType(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            default:
                return false;
        }
    }
}
