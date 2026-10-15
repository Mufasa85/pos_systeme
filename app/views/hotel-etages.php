<?php
// Vue squelette — Sprint 1
// Le contenu réel (liste chambres à nettoyer, boutons gros) sera ajouté au Sprint 3.
// Référence : docs/PLAN_HOTEL_V1.md
$page = 'hotel';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Étages — Hôtel</title>
  <link rel="stylesheet" href="/assets/css/mobile-caisse.css">
  <style>
    body { background: #1a1a2e; color: #fff; }
    .hotel-mobile {
      max-width: 480px;
      margin: 0 auto;
      padding: 1.5rem 1rem;
    }
    .hotel-mobile h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
    .hotel-mobile .badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      background: #f1c40f;
      color: #1a1a2e;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
    }
    .hotel-mobile .info-card {
      background: #16213e;
      border-radius: 12px;
      padding: 1.25rem;
      margin-top: 1rem;
    }
    .hotel-mobile p { color: #c0c0c0; line-height: 1.5; font-size: 0.95rem; }
    .hotel-mobile .sprint-info {
      margin-top: 1.5rem;
      padding: 0.75rem;
      background: #0f3460;
      border-left: 4px solid #f1c40f;
      border-radius: 4px;
      text-align: left;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>
  <main class="hotel-mobile">
    <span class="badge">Module Hôtel — Sprint 1</span>
    <h1>🧹 Étages</h1>
    <p>Vue mobile pour la gouvernante — gros boutons, mode sombre économe en batterie.</p>
    <div class="info-card">
      <p>
        La liste des chambres à nettoyer, le bouton "fait" en un clic, et le
        signalement de problème seront ajoutés au <strong>Sprint 3</strong>.
      </p>
      <div class="sprint-info">
        <strong>État actuel :</strong><br>
        — Tables <code>hotel_housekeeping</code> prêtes<br>
        — Route <code>/hotel/etages</code> active<br>
        — Vue responsive mobile testée
      </div>
    </div>
  </main>
</body>
</html>
