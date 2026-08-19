<?php if (isset($error)): ?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Erreur</title></head>
<body style="font-family:sans-serif;padding:2rem;text-align:center">
  <h2>Erreur</h2>
  <p><?= htmlspecialchars($error) ?></p>
</body>
</html>
<?php return; endif; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket <?= htmlspecialchars($depot['numero'] ?? '') ?></title>
  <style>
    @page { size: 80mm 297mm; margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'JetBrains Mono', monospace; width: 80mm; margin: 0 auto; padding: 8mm; font-size: 12px; color: #000; background: #fff; }
    .ticket-center { text-align: center; margin-bottom: 10px; }
    .ticket-title { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
    .ticket-numero { font-size: 14px; margin-bottom: 10px; }
    .ticket-line { display: flex; justify-content: space-between; border-bottom: 1px dashed #ccc; padding: 4px 0; }
    .ticket-total { font-size: 14px; font-weight: bold; margin-top: 10px; border-top: 2px solid #000; padding-top: 8px; }
    .no-print { margin-top: 20px; text-align: center; }
    .no-print button { padding: 8px 16px; cursor: pointer; border-radius: 4px; border: 1px solid #0B5E88; background: #0B5E88; color: #fff; }
    .no-print button:hover { background: #074968; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="ticket-center">
    <div class="ticket-title"><?= htmlspecialchars($storeName) ?></div>
    <div style="font-size:11px">Pressing</div>
    <div class="ticket-numero"><?= htmlspecialchars($depot['numero'] ?? '') ?></div>
    <div><?= htmlspecialchars($depot['nom_client'] ?? 'Client') ?> · <?= htmlspecialchars($depot['client_numero'] ?? '') ?></div>
    <div style="font-size:10px;color:#666"><?= !empty($depot['date_reception']) ? date('d/m/Y H:i', strtotime($depot['date_reception'])) : '' ?></div>
  </div>

  <?php foreach ($depot['articles'] ?? [] as $a): ?>
    <div class="ticket-line">
      <span><?= (int)$a['quantite'] ?>x <?= htmlspecialchars($a['nom_article']) ?> (<?= htmlspecialchars($a['service']) ?>)</span>
      <span><?= number_format((float)$a['prix_total'], 2) ?> Fc</span>
    </div>
  <?php endforeach; ?>

  <div class="ticket-line">
    <span>Sous-total</span>
    <span><?= number_format((float)($depot['sous_total'] ?? 0), 2) ?> Fc</span>
  </div>
  <?php if (($depot['remise'] ?? 0) > 0): ?>
    <div class="ticket-line">
      <span>Remise</span>
      <span>-<?= number_format((float)$depot['remise'], 2) ?> Fc</span>
    </div>
  <?php endif; ?>
  <div class="ticket-line ticket-total">
    <span>Total</span>
    <span><?= number_format((float)($depot['total'] ?? 0), 2) ?> Fc</span>
  </div>
  <div class="ticket-line">
    <span>Payé</span>
    <span><?= number_format((float)($depot['paid_amount'] ?? 0), 2) ?> Fc</span>
  </div>
  <div class="ticket-line">
    <span>Solde</span>
    <span><?= number_format(max(0, (float)($depot['total'] ?? 0) - (float)($depot['paid_amount'] ?? 0)), 2) ?> Fc</span>
  </div>

  <div style="margin-top: 15px; text-align: center; font-size: 10px; color: #333;">
    <?php if (!empty($depot['adresse_livraison'])): ?>
      Livraison : <?= htmlspecialchars($depot['adresse_livraison']) ?><br>
    <?php else: ?>
      Retrait au pressing<br>
    <?php endif; ?>
    <?php if (!empty($depot['date_retour_prevue'])): ?>
      Retour prévu : <?= date('d/m/Y', strtotime($depot['date_retour_prevue'])) ?><br>
    <?php endif; ?>
  </div>

  <div class="no-print">
    <button onclick="window.print()">Imprimer le ticket</button>
  </div>
</body>
</html>
