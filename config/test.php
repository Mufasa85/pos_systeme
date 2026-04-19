<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "Connexion OK";
} catch (PDOException $e) {
    echo $e->getMessage();
}