<?php

namespace App\Core;

use App\App;

class Router
{
    private static string $route_name = '';

    /**
     * Politique associee a chaque route, indexee par le NOM de route.
     *
     * La cle DOIT etre exactement la valeur retournee par
     * `AltoRouter::match()` dans `match['name']`, sinon la garde ne s'execute
     * jamais.
     *
     * @var array<string, array{key: string, mode: string}>
     */
    private static array $routePolicies = [];

    /**
     * Compteur technique : permet de donner un nom UNIQUE a chaque route
     * (AltoRouter interdit les noms dupliques), y compris quand aucune
     * politique n'est posee.
     */
    private static int $routeCounter = 0;

    public static function getInstance(): \AltoRouter
    {
        return \App\App::getInstanceRouter();
    }

    public static function name(string $name)
    {
        self::$route_name = $name;
    }
    public static function get(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive')
    {
        self::mapRoute('GET', $route, $target, $policyKey, $policyMode);
    }

    public static function post(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive')
    {
        self::mapRoute('POST', $route, $target, $policyKey, $policyMode);
    }

    public static function delete(string $route, $target, string $name = '', string $policyKey = '', string $policyMode = 'exclusive')
    {
        self::mapRoute('DELETE', $route, $target, $policyKey, $policyMode);
    }

    public static function put(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive')
    {
        self::mapRoute('PUT', $route, $target, $policyKey, $policyMode);
    }

    /**
     * Enregistre une route et, si besoin, sa politique.
     *
     * @param string $policyKey  Cle de page (ou liste `a|b` en mode shared_read).
     * @param string $policyMode 'exclusive' | 'shared_read' | 'shared_write'
     */
    private static function mapRoute(
        string $method,
        string $route,
        $target,
        string $policyKey = '',
        string $policyMode = 'exclusive'
    ): void {
        // Nom AltoRouter de la route : le nom public s'il existe, sinon un
        // identifiant technique unique (AltoRouter interdit les doublons).
        $routeName = self::$route_name !== ''
            ? self::$route_name
            : '__route_' . (++self::$routeCounter);

        if ($policyKey !== '') {
            self::$routePolicies[$routeName] = [
                'key'  => $policyKey,
                'mode' => $policyMode,
            ];
        }

        App::getInstanceRouter()->map($method, $route, $target, $routeName);

        // Le nom public ne vaut que pour la route suivante.
        self::$route_name = '';
    }

    public function origin($path)
    {
        App::getInstanceRouter()->setBasePath($path);
    }

    public static function matcher()
    {
        $match = App::getInstanceRouter()->match();

        // ── Garde de politique ───────────────────────────────────────────
        // Elle s'execute AVANT l'instanciation du controleur : une page
        // bloquee ne charge aucune donnee metier.
        if (is_array($match) && isset($match['name'])) {
            $policy = self::$routePolicies[$match['name']] ?? null;
            if ($policy !== null && !self::policyAllows($policy)) {
                self::respondPolicyDenied();
                return;
            }
        }

        if ($match && is_callable($match['target'])) {
            call_user_func($match['target'], $match['params']);
        } elseif (is_array($match) && is_array($match['target'])) {
            $controller = $match['target'][0];
            $method = $match['target'][1];
            $controller = new $controller();
            $controller->$method($match['params']);
        } else {
            self::respondNotFound();
        }
    }


    /**
     * La politique autorise-t-elle la requete courante ?
     *
     * @param array{key: string, mode: string} $policy
     */
    private static function policyAllows(array $policy): bool
    {
        // Un utilisateur non authentifie n'est pas concerne : le controle
        // d'authentification du controleur reste prioritaire.
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return true;
        }

        $service = new \App\Services\UserPageAccessService();

        try {
            if ($policy['mode'] === 'shared_read') {
                // Autorise si AU MOINS UNE page lectrice est accessible.
                foreach (explode('|', $policy['key']) as $pageKey) {
                    $pageKey = trim($pageKey);
                    if ($pageKey === '') {
                        continue;
                    }
                    if ($service->isPageAllowedForUser($pageKey, $userId)) {
                        return true;
                    }
                }
                return false;
            }

            if ($policy['mode'] === 'shared_write') {
                // Le contexte metier est resolu par le controleur : la garde
                // ne bloque pas ici (cf. Controller::requirePagePolicy()).
                return true;
            }

            return $service->isPageAllowedForUser($policy['key'], $userId);
        } catch (\Throwable $e) {
            error_log('[Router] policyAllows error: ' . $e->getMessage());
            // Fail-closed : une erreur d'infrastructure ferme la porte.
            return false;
        }
    }

    /**
     * Reponse 403 : page HTML pour une page, JSON pour une API.
     */
    private static function respondPolicyDenied(): void
    {
        http_response_code(403);

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'page_not_allowed',
                'message' => "Cette page n'est pas accessible pour votre rôle.",
            ]);
            return;
        }

        require_once dirname(__DIR__) . '/views/403.php';
    }

    private static function respondNotFound()
    {
        http_response_code(404);
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'message' => 'Route introuvable']);
        } else {
            require_once dirname(__DIR__) . '/views/404.php';
        }
    }

}
