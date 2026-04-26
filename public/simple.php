<?php
// Page de test simple - Aucune redirection, juste affichage direct
session_start();

echo "<html><head><title>Test Simple</title></head><body>";
echo "<h1>=== Test Simple - Pas de redirection ===</h1>";
echo "<h2>Session:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "<h2>URL actuelle:</h2>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";
echo "<h2>Liens:</h2>";
echo '<a href="/simple.php">Cette page</a> | ';
echo '<a href="/">Page principale</a> | ';
echo '<a href="/dashboard">Dashboard</a>';
echo "<p>Si vous voyez cette page quand vous allez sur /, le routage ne fonctionne pas.</p>";
echo "<p>Si vous voyez une boucle de redirection, le problème est dans le PHP.</p>";
echo "</body></html>";
