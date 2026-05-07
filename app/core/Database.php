<?php

namespace App\Core;

// app/core/Database.php


$host = 'localhost';
$dbname = 'u138949110_a';
$user = 'u138949110_b';
$pass = 'Cesarrandybernice2013@';

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=localhost;port=3306;dbname=pos_system;charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new \PDO($dsn, 'root', 'root', $options);
        } catch (\PDOException $e) {
            die("Database Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    // Facilite les requêtes
    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Retourne true si la requête s'est exécutée sans erreur (indépendamment du nombre de lignes affectées)
    public function execute($sql, $params = [])
    {
        error_log("Database execute - SQL: $sql");
        error_log("Database execute - params: " . print_r($params, true));

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        $rowCount = $stmt->rowCount();
        error_log("Database execute - rowCount: $rowCount");

        return true;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetch($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    // 🔹 Gestion des transactions
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
