<?php

namespace App\Services;

use App\Models\Product;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
     * Export du catalogue produits vers un fichier Excel (.xlsx).
 *
 * ── Format « round-trip » ────────────────────────────────────────────
 * Le fichier produit utilise EXACTEMENT les mêmes en-têtes que le modèle
 * d'import (cf. ProductImportService::HEADER_ALIASES). Conséquence :
 * un catalogue exporté peut être ré-importé tel quel après modification
 * (mode « update »), sans renommage de colonnes.
 *
 * Deux feuilles :
 *   - « Produits »  : les données, prêtes à ré-import
 *   - « Détail »    : informations complémentaires (boutique, stocks,
 *                     dates d'expiration) — informative, non ré-importable
 */
class ProductExportService
{
    /** En-têtes de la feuille « Détail » (informationnel, non ré-importable). */
    private const DETAIL_HEADERS = [
        'code_barres',
        'nom',
        'boutique',
        'prix',
        'stock_total',
        'stock_disponible',
        'taxe_taux',
        'date_expiration',
        'date_creation_produit',
    ];

    private $productModel;

    public function __construct()
    {
        $this->productModel = new Product();
    }

    /**
     * En-têtes de la feuille « Produits ».
     *
     * Ils sont lus depuis le service d'import : c'est la source de vérité
     * unique, et garantit que le round-trip export → import fonctionne.
     */
    public function buildHeaders(): array
    {
        $reflection = new \ReflectionClass(ProductImportService::class);

        return array_keys($reflection->getConstant('HEADER_ALIASES'));
    }

    /**
     * Construit le fichier Excel et l'envoie en sortie HTTP.
     *
     * @param int|string|null $shopId  null = toutes les boutiques (super_admin)
     * @param array $filters  search, category, shop_id, include_inactive
     * @param array $options  include_details (bool)
     */
    public function export($shopId, array $filters = [], array $options = []): void
    {
        $includeInactive = !empty($filters['include_inactive']);
        $products = $this->productModel->getAllFiltered($shopId, $includeInactive, $filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        // Quand le catalogue couvre plusieurs boutiques, on ajoute une
        // colonne « boutique » : sans elle, on ne sait pas d'où vient
        // chaque produit (et le ré-import deviendrait ambigu).
        // Un filtre boutique explicite = export mono-boutique → pas de colonne.
        $multiShop = ($shopId === null && empty($filters['shop_id']));

        $this->fillProductsSheet($sheet, $products, $multiShop);

        if (!empty($options['include_details'])) {
            $this->addDetailSheet($spreadsheet, $products);
        }

        // `createSheet()` bascule la feuille active : on revient sur « Produits ».
        $spreadsheet->setActiveSheetIndex(0);

        $filename = $this->buildFilename($filters);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Remplit la feuille « Produits » (format ré-importable).
     *
     * @param bool $multiShop  true si le catalogue couvre plusieurs boutiques
     *                         (on ajoute alors une colonne « boutique »)
     */
    private function fillProductsSheet(Worksheet $sheet, array $products, bool $multiShop = false): void
    {
        $headers = $this->buildHeaders();

        // La colonne « boutique » n'est PAS reconnue par l'import : elle est
        // purely informative. On l'ajoute en fin de ligne, pour que les
        // premières colonnes restent alignées sur le modèle d'import.
        if ($multiShop) {
            $headers[] = 'boutique';
        }

        $sheet->fromArray($headers, null, 'A1');

        // Colonnes numériques : le format doit laisser la valeur INTACTE
        // (le round-trip export → import dépend de la valeur brute).
        $numericColumns = ['prix', 'stock', 'stock_minimum', 'remise_value', 'taxe_specifique_value'];

        foreach ($products as $i => $product) {
            $row = [
                (string)($product['code_barres'] ?? ''),
                (string)($product['nom'] ?? ''),
                (string)($product['categorie'] ?? ''),
                (float)($product['prix'] ?? 0),
                (float)($product['total_stock'] ?? $product['stock'] ?? 0),
                (float)($product['stock_minimum'] ?? 0),
                (string)($product['tax_etiquette'] ?? ''),
                (string)($product['product_type'] ?? 'unite'),
                (string)($product['prod_service'] ?? ''),
                (string)($product['remise_type'] ?? '%'),
                (float)($product['remise_value'] ?? 0),
                (string)($product['taxe_specifique_type'] ?? '%'),
                (float)($product['taxe_specifique_value'] ?? 0),
                (string)($product['date_expiration'] ?? ''),
            ];

            if ($multiShop) {
                $row[] = (string)($product['shop_name'] ?? '');
            }

            $rowNumber = $i + 2;
            foreach ($headers as $j => $header) {
                $coordinate = Coordinate::stringFromColumnIndex($j + 1) . $rowNumber;
                $value = $row[$j] ?? '';

                // ⚠️ `fromArray()` PERD la valeur 0.0 (elle est écrite comme
                // une cellule vide). Pour les colonnes numériques, on écrit
                // donc explicitement un nombre → le 0 est conservé.
                if (in_array($header, $numericColumns, true)) {
                    $sheet->setCellValueExplicit($coordinate, (float)$value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string)$value, DataType::TYPE_STRING);
                }
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        // En-tête mis en forme + figée + filtre automatique.
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0B5E88');
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->freezePane('A2');

        if (!empty($products)) {
            $sheet->setAutoFilter('A1:' . $lastColumn . (count($products) + 1));
        }

        // Largeurs de colonnes lisibles.
        $widths = [18, 32, 20, 14, 12, 16, 10, 16, 16, 14, 16, 22, 24, 18];
        if ($multiShop) {
            $widths[] = 24; // colonne « boutique »
        }
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }

        // Format d'AFFICHAGE uniquement (la valeur brute reste intacte).
        $lastRow = count($products) + 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('D2:D' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('E2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getStyle('K2:K' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('M2:M' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    /**
     * Ajoute la feuille « Détail » (informations complémentaires).
     */
    private function addDetailSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Détail');
        $sheet->fromArray(self::DETAIL_HEADERS, null, 'A1');

        $numericColumns = ['prix', 'stock_total', 'stock_disponible', 'taxe_taux'];

        foreach ($products as $i => $product) {
            $row = [
                (string)($product['code_barres'] ?? ''),
                (string)($product['nom'] ?? ''),
                (string)($product['shop_name'] ?? '—'),
                (float)($product['prix'] ?? 0),
                (float)($product['total_stock'] ?? 0),
                (float)($product['available_stock'] ?? 0),
                (float)($product['tax_rate'] ?? 0),
                (string)($product['nearest_expiration_date'] ?? ''),
                (string)($product['created_at'] ?? ''),
            ];

            $rowNumber = $i + 2;
            foreach (self::DETAIL_HEADERS as $j => $header) {
                $coordinate = Coordinate::stringFromColumnIndex($j + 1) . $rowNumber;
                $value = $row[$j] ?? '';

                // Même règle que la feuille « Produits » : le 0 doit survivre.
                if (in_array($header, $numericColumns, true)) {
                    $sheet->setCellValueExplicit($coordinate, (float)$value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string)$value, DataType::TYPE_STRING);
                }
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::DETAIL_HEADERS));
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF64748B');
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->freezePane('A2');

        $widths = [18, 32, 22, 14, 16, 20, 14, 20, 22];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
    }

    /**
     * Construit un nom de fichier lisible : produits_2026-11-11.xlsx
     * (suffixé si des filtres sont appliqués).
     */
    private function buildFilename(array $filters): string
    {
        $parts = ['produits'];

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $parts[] = $this->slugify($filters['category']);
        }
        if (!empty($filters['shop_id'])) {
            $parts[] = 'boutique' . (int)$filters['shop_id'];
        }
        if (!empty($filters['search'])) {
            $parts[] = $this->slugify($filters['search']);
        }

        $parts[] = date('Y-m-d');

        return implode('_', $parts) . '.xlsx';
    }

    /** Nettoie une chaîne pour l'utiliser dans un nom de fichier. */
    private function slugify(string $value): string
    {
        // Supprime les accents (translittération ASCII).
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'æ' => 'ae',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        ]);

        $value = preg_replace('/[^a-zA-Z0-9]+/', '_', $value);
        $value = trim((string)$value, '_');

        return strtolower(substr($value, 0, 30)) ?: 'filtre';
    }
}
