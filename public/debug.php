<?php
// Diagnostic complet
session_start();

echo "<h1>=== Diagnostic de redirection ===</h1>";
echo "<p>Ce fichier vous aide à comprendre pourquoi la boucle de redirection se produit.</p>";

// Afficher toutes les variables de session
echo "<h2>Session actuelle:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Simuler le flux exact
echo "<h2>Flux de redirection simulé:</h2>";

echo "<h3>1. Accès à / (racine)</h3>";
echo "Route: / → AuthController::showLogin()<br>";
echo "check: isset(\$_SESSION['user_id']) = " . (isset($_SESSION['user_id']) ? 'TRUE' : 'FALSE') . "<br>";

if (isset($_SESSION['user_id'])) {
    echo "→ REDIRECTION vers /dashboard<br>";
    echo "<p style='color:red'><strong>PROBLÈME: Vous avez une session active!</strong></p>";
    echo "<p>Cela signifie que votre navigateur a une session PHP active d'une installation précédente.</p>";
    echo "<h3>Solution:</h3>";
    echo "<ol>";
    echo "<li>Cliquez sur ce lien: <a href='debug.php?action=logout' style='color:red'>Détruire la session</a></li>";
    echo "<li>Ou ouvrez les outils développeur Chrome → Application → Cookies → Supprimer les cookies de shop.osat-energie.com</li>";
    echo "<li>Ensuite, rafraîchissez cette page</li>";
    echo "</ol>";
} else {
    echo "→ AFFICHER login.php<br>";
    echo "<p style='color:green'><strong>OK: Pas de session, la page de login devrait s'afficher.</strong></p>";
}

// Action de logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo "<meta http-equiv='refresh' content='0;url=debug.php'>";
    exit;
}
