<?php
// Vue squelette — Sprint 1
// Le contenu réel (grille des chambres, modals check-in/out) sera ajouté au Sprint 2.
// Référence : docs/PLAN_HOTEL_V1.md
$page = 'hotel';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réception — Hôtel</title>
  <link rel="stylesheet" href="/assets/css/mobile-caisse.css">
  <style>
    .hotel-placeholder {
      max-width: 720px;
      margin: 4rem auto;
      padding: 2.5rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      text-align: center;
    }
    .hotel-placeholder h1 { color: #2c3e50; margin-bottom: 0.5rem; }
    .hotel-placeholder .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      background: #f1c40f;
      color: #2c3e50;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
    }
    .hotel-placeholder p { color: #555; line-height: 1.6; }
    .hotel-placeholder .sprint-info {
      margin-top: 2rem;
      padding: 1rem;
      background: #f8f9fa;
      border-left: 4px solid #3498db;
      border-radius: 4px;
      text-align: left;
    }
  </style>
</head>
<body>
  <?php require_once dirname(__DIR__) . '/layout/header.php'; ?>

  <main class="main-content">
    <div class="hotel-placeholder">
      <span class="badge">Module Hôtel — Sprint 1</span>
      <h1>🏨 Réception</h1>
      <p>
        La grille des 20 chambres, les modals de check-in / check-out, et la
        gestion des extras seront ajoutés au <strong>Sprint 2</strong>.
      </p>
      <div class="sprint-info">
        <strong>État actuel :</strong> les fondations sont en place<br>
        — 6 tables créées (<code>hotel_*</code>)<br>
        — 20 chambres seedées (10 simples + 10 doubles)<br>
        — Route <code>/hotel/reception</code> active<br>
        — Authentification par rôle opérationnelle
      </div>
    </div>
  </main>

  <?php require_once dirname(__DIR__) . '/layout/footer.php'; ?>
</body>
</html>
