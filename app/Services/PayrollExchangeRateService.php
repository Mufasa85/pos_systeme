<?php

namespace App\Services;

/**
 * Taux de change USD → devise locale, pour le barème IRT.
 *
 * Le barème de l'IRT du classeur `Recap paie.xlsx` est libellé en USD
 * (seuils 162 000 / 1 800 000 / 3 600 000 / 6 543 000 USD), alors que la
 * paie du POS est en francs congolais. Il faut donc convertir l'assiette
 * imposable en USD avant d'appliquer le barème, puis convertir l'impôt
 * obtenu en Fc.
 *
 * ⚠️ Le classeur fige un taux à 2 658,7148 (à sa date d'édition). Le taux
 * réel est publié par l'API OSAT (`/dgi/currency/`), déjà proxifiée par
 * `GET /api/currency` pour la facture DGI. On le récupère ici, avec un cache
 * de session court (le taux bouge peu, mais pas tous les jours).
 *
 * Repli : le taux enregistré dans `payroll_settings.exchange_rate` si l'API
 * est injoignable — le calcul ne doit jamais planter faute de réseau.
 */
class PayrollExchangeRateService
{
    private const API_URL = 'https://osat-energie.com/dgi/currency/index.php';
    private const SESSION_KEY = 'payroll_exchange_rate_cache';
    private const CACHE_TTL = 21600; // 6 heures

    /**
     * Retourne le taux USD → devise locale.
     *
     * @param float $fallback Taux de repli (celui des paramètres)
     */
    public function getRate(float $fallback = 1.0): float
    {
        $cached = $this->cachedRate();
        if ($cached !== null) {
            return $cached;
        }

        $rate = $this->fetchRate();
        if ($rate !== null) {
            $this->storeRate($rate);
            return $rate;
        }

        // Réseau indisponible : on garde le taux paramétré (jamais 0).
        return $fallback > 0 ? $fallback : 1.0;
    }

    /**
     * Taux mis en cache en session (s'il est encore valide).
     */
    private function cachedRate(): ?float
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $cache = $_SESSION[self::SESSION_KEY];
        if (!is_array($cache) || (int)($cache['expires_at'] ?? 0) < time()) {
            return null;
        }

        $rate = (float)($cache['rate'] ?? 0);

        return $rate > 0 ? $rate : null;
    }

    /**
     * Mémorise le taux en session.
     */
    private function storeRate(float $rate): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'rate'       => $rate,
            'expires_at' => time() + self::CACHE_TTL,
        ];
    }

    /**
     * Interroge l'API OSAT.
     */
    private function fetchRate(): ?float
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => 'Accept: application/json',
            ],
        ]);

        $body = @file_get_contents(self::API_URL, false, $context);
        if ($body === false || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        // La réponse est un tableau : [{"type":"USD","rate":…}, …].
        $list = array_is_list($decoded) ? $decoded : [$decoded];

        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $type = strtoupper((string)($entry['type'] ?? ''));
            if ($type !== '' && $type !== 'USD') {
                continue;
            }
            $rate = (float)($entry['rate'] ?? 0);
            if ($rate > 0) {
                return $rate;
            }
        }

        return null;
    }
}
