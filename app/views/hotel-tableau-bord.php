<?php
// Vue squelette — Sprint 1
// Le contenu réel (KPI, séjours en cours, tâches housekeeping) sera ajouté au Sprint 4.
// Référence : docs/PLAN_HOTEL_V1.md
$page = 'hotel';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord — Hôtel</title>
  <link rel="stylesheet" href="/assets/css/mobile-caisse.css">
  <style>
    .hotel-placeholder {
      max-width: 960px;
      margin: 3rem auto;
      padding: 2.5rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    .hotel-placeholder h1 { color: #2c3e50; margin-bottom: 0.5rem; }
    .hotel-placeholder .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      background: #27ae60;
      color: #fff;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
    }
    .hotel-placeholder p { color: #555; line-height: 1.6; }
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }
    .kpi-card {
      padding: 1.25rem;
      background: #f8f9fa;
      border-radius: 8px;
      text-align: center;
    }
    .kpi-card .value {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
      margin: 0.25rem 0;
    }
    .kpi-card .label { color: #777; font-size: 0.85rem; }
    .kpi-card.placeholder .value { color: #bbb; }
  </style>
</head>
<body>
  <?php require_once dirname(__DIR__) . '/layout/header.php'; ?>

  <main class="main-content">
    <div class="hotel-placeholder">
      <span class="badge">Module Hôtel — Sprint 1</span>
      <h1>📊 Tableau de bord manager</h1>
      <p>Vue d'ensemble : taux d'occupation, recettes, housekeeping. Le contenu réel arrive au <strong>Sprint 4</strong>.</p>

      <div class="kpi-grid">
        <div class="kpi-card placeholder">
          <div class="label">Chambres occupées</div>
          <div class="value">— / 20</div>
        </div>
        <div class="kpi-card placeholder">
          <div class="label">À nettoyer</div>
          <div class="value">—</div>
        </div>
        <div class="kpi-card placeholder">
          <div class="label">Recettes du jour</div>
          <div class="value">— FC</div>
        </div>
        <div class="kpi-card placeholder">
          <div class="label">Extras du jour</div>
          <div class="value">— FC</div>
        </div>
      </div>
    </div>
  </main>

  <?php require_once dirname(__DIR__) . '/layout/footer.php'; ?>
</body>
</html>
