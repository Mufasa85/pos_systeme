<?php

// Test de flux Partie A — Pressing
// Lancement : php scripts/test_pressing_part_a.php
// ATTENTION : ce test insère et supprime des données dans une transaction, mais il a besoin que les migrations soient appliquées.

require __DIR__ . '/../vendor/autoload.php';

$ok = 0;
$fail = 0;

function assertTrue($cond, $msg, &$ok, &$fail) {
    if ($cond) {
        echo "✅ $msg\n";
        $ok++;
    } else {
        echo "❌ $msg\n";
        $fail++;
    }
}

function assertEquals($expected, $actual, $msg, &$ok, &$fail) {
    if ($expected == $actual) {
        echo "✅ $msg ($actual)\n";
        $ok++;
    } else {
        echo "❌ $msg (attendu: $expected, obtenu: $actual)\n";
        $fail++;
    }
}

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    $db->beginTransaction();

    // Recherche d'une boutique et d'un client existants
    $shop = $db->query("SELECT id FROM shops LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $client = $db->query("SELECT id FROM clients LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $user = $db->query("SELECT id FROM utilisateurs LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    assertTrue($shop !== false, "Boutique trouvée", $ok, $fail);
    assertTrue($client !== false, "Client trouvé", $ok, $fail);
    assertTrue($user !== false, "Utilisateur trouvé", $ok, $fail);

    if (!$shop || !$client || !$user) {
        throw new Exception("Données de test insuffisantes dans la BDD.");
    }

    $shopId = $shop['id'];
    $clientId = $client['id'];
    $userId = $user['id'];

    // 1. Service
    $serviceModel = new \App\Models\PressingService();
    $serviceId = $serviceModel->create([
        'shop_id' => $shopId,
        'nom' => 'TEST Lavage',
        'description' => 'Test service',
        'duree_estimee' => 60,
    ]);
    assertTrue($serviceId > 0, "Service créé", $ok, $fail);

    // 2. Tarif
    $tarifModel = new \App\Models\PressingTarif();
    $tarifId = $tarifModel->create([
        'shop_id' => $shopId,
        'service_id' => $serviceId,
        'article_type' => 'Chemise',
        'prix_unitaire' => 1500,
    ]);
    assertTrue($tarifId > 0, "Tarif créé", $ok, $fail);

    $tarif = $tarifModel->findByServiceAndType($serviceId, 'Chemise', $shopId);
    assertTrue($tarif !== false, "Tarif retrouvé", $ok, $fail);

    // 3. Dépôt
    $depotModel = new \App\Models\PressingDepot();
    $numero = $depotModel->generateNumero();
    $depotId = $depotModel->create([
        'shop_id' => $shopId,
        'numero' => $numero,
        'client_id' => $clientId,
        'sous_total' => 3000,
        'remise' => 0,
        'total' => 3000,
        'date_prevue' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'adresse_livraison' => '123 rue Test',
        'date_retour_prevue' => date('Y-m-d H:i:s', strtotime('+4 days')),
        'created_by' => $userId,
    ]);
    assertTrue($depotId > 0, "Dépôt créé", $ok, $fail);

    $articleModel = new \App\Models\PressingArticle();
    $articleId = $articleModel->create([
        'depot_id' => $depotId,
        'nom_article' => 'Chemise',
        'quantite' => 2,
        'etat_initial' => 'Bon état',
        'commentaire' => 'Test',
        'service' => 'lavage',
        'service_id' => $serviceId,
        'prix_unitaire' => 1500,
        'prix_total' => 3000,
    ]);
    assertTrue($articleId > 0, "Article créé", $ok, $fail);

    $depot = $depotModel->findById($depotId, $shopId);
    assertTrue($depot !== false, "Dépôt retrouvé", $ok, $fail);
    assertEquals('123 rue Test', $depot['adresse_livraison'], "Adresse de livraison enregistrée", $ok, $fail);

    // 4. Paiement partiel
    $paymentModel = new \App\Models\PressingPayment();
    $paymentId = $paymentModel->create([
        'depot_id' => $depotId,
        'montant' => 1000,
        'mode_paiement' => 'cash',
        'reference' => 'Test ref',
        'created_by' => $userId,
    ]);
    assertTrue($paymentId > 0, "Paiement partiel créé", $ok, $fail);
    assertEquals(1000, $depotModel->getPaidAmount($depotId), "Montant payé mis à jour", $ok, $fail);
    assertEquals(2000, $depotModel->getSolde($depotId), "Solde restant correct", $ok, $fail);
    assertEquals(false, $depotModel->isPaid($depotId), "Dépôt non totalement payé", $ok, $fail);

    // Paiement du solde
    $paymentModel->create([
        'depot_id' => $depotId,
        'montant' => 2000,
        'mode_paiement' => 'mobile_money',
        'reference' => 'Test ref 2',
        'created_by' => $userId,
    ]);
    assertEquals(true, $depotModel->isPaid($depotId), "Dépôt totalement payé", $ok, $fail);

    // 5. Historique des statuts
    $depotModel->updateStatut($depotId, 'en_lavage', $userId);
    $depotModel->updateStatut($depotId, 'pret', $userId);

    $historyModel = new \App\Models\PressingStatusHistory();
    $history = $historyModel->getByDepot($depotId);
    assertEquals(2, count($history), "2 changements de statut enregistrés", $ok, $fail);

    // 6. Photo
    $photoModel = new \App\Models\PressingPhoto();
    $photoId = $photoModel->create([
        'depot_id' => $depotId,
        'article_id' => $articleId,
        'chemin' => 'assets/img/pressing/test.jpg',
        'type' => 'etat_initial',
    ]);
    assertTrue($photoId > 0, "Photo enregistrée", $ok, $fail);

    $photos = $photoModel->getByDepot($depotId);
    assertEquals(1, count($photos), "1 photo retrouvée", $ok, $fail);

    // 7. Consommable
    $consumableModel = new \App\Models\PressingConsumable();
    $consId = $consumableModel->create([
        'shop_id' => $shopId,
        'nom' => 'TEST Détergent',
        'quantite' => 10,
        'unite' => 'L',
        'stock_minimum' => 2,
    ]);
    assertTrue($consId > 0, "Consommable créé", $ok, $fail);

    $consumableModel->consume($consId, $depotId, 2, $userId);
    $cons = $consumableModel->findById($consId, $shopId);
    assertEquals(8, (float)$cons['quantite'], "Stock consommable décrémenté", $ok, $fail);

    echo "\n";
    echo "=== RÉSULTAT ===\n";
    echo "✅ Réussis : $ok\n";
    echo "❌ Échecs : $fail\n";

    $db->rollBack();
    echo "ℹ️ Transaction annulée — aucune donnée de test conservée.\n";
} catch (\Throwable $e) {
    echo "\n💥 ERREUR : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
