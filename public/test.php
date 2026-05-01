<?php
// Test final - Simuler le flux exact de l'application
session_start();

echo "<h2>=== Test final du flux ===</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'not set') . "<br><br>";

// Charger l'application
require_once dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/routes/web.php';

$router = \App\App::getInstanceRouter();
$match = $router->match('/');

echo "<h3>Route matchée pour '/':</h3>";
if ($match) {
    echo "Target: " . print_r($match['target'], true);

    // Vérifier si c'est AuthController::showLogin
    if (is_array($match['target']) && $match['target'][0] === 'App\Controllers\AuthController') {
        echo "<h3>→ showLogin sera appelé</h3>";
        echo "Dans showLogin():<br>";
        echo "- isset(\$_SESSION['user_id']) = " . (isset($_SESSION['user_id']) ? 'TRUE' : 'FALSE') . "<br>";

        if (isset($_SESSION['user_id'])) {
            echo "- Redirection vers /dashboard<br>";
            echo "- <a href=\"/dashboard\">Aller à /dashboard</a>";
        } else {
            echo "- Afficher login.php<br>";
            echo "- <a href=\"/login\">Page de login visible</a>";
        }
    }
} else {
    echo "ERREUR: Aucune route pour '/'";
}
?>
<br><br>
<h3>Liens de test:</h3>
<a href="test.php">Réinitialiser test</a>