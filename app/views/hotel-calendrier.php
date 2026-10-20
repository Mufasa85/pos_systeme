<?php
/**
 * Page Hôtel > Calendrier (manager + super_admin).
 *
 * Vue planning : lignes = chambres (triées par numéro, 10 simples + 10 doubles),
 * colonnes = jours de la semaine débutant à $calFrom.
 *
 * Pour chaque séjour chevauchant la semaine, on dessine un bandeau coloré
 * couvrant sa durée (date_arrivee -> date_depart_prevue) avec :
 *   - reserve   → vert
 *   - en_cours  → bleu
 *   - termine   → gris
 *   - annule    → rouge clair
 *   - no_show   → rouge clair
 *   - chambre hors_service (sans séjour) → hachuré rouge
 *
 * Lecture seule. Le clic sur un bandeau ouvre un drawer avec le détail.
 * Auto-refresh via /api/hotel/calendrier toutes les 60 s.
 * Sur mobile (< 768px), bascule en liste verticale par chambre.
 *
 * Sprint 4 — Référence : docs/PLAN_HOTEL_V1.md §10.
 */
$page = 'hotel-calendrier';

$calFrom     = $calFrom     ?? date('Y-m-d');
$calTo       = $calTo       ?? date('Y-m-d', strtotime($calFrom . ' +6 days'));
$calChambres = $calChambres ?? [];
$calSejours  = $calSejours  ?? [];

// Couleurs par statut (cohérent avec la charte du module hôtel)
$statusColors = [
    'reserve'  => ['bg' => '#dcfce7', 'border' => '#16a34a', 'text' => '#14532d', 'label' => 'Réservé'],
    'en_cours' => ['bg' => '#dbeafe', 'border' => '#2563eb', 'text' => '#1e3a8a', 'label' => 'En cours'],
    'termine'  => ['bg' => '#e5e7eb', 'border' => '#6b7280', 'text' => '#1f2937', 'label' => 'Terminé'],
    'annule'   => ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#7f1d1d', 'label' => 'Annulé'],
    'no_show'  => ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#7f1d1d', 'label' => 'No-show'],
];

// Construction des 7 jours de la semaine (objets DateTime)
$days = [];
$cursor = new DateTimeImmutable($calFrom);
for ($i = 0; $i < 7; $i++) {
    $days[] = $cursor->modify("+{$i} days");
}
$todayYmd = date('Y-m-d');

// Pré-calcul des séjours par chambre : map[chambre_id] = liste de séjours
// déjà triés par date_arrivee ASC (renvoyé par le modèle)
$sejoursByChambre = [];
foreach ($calSejours as $s) {
    $cid = (int)($s['chambre_id'] ?? 0);
    if ($cid > 0) {
        $sejoursByChambre[$cid][] = $s;
    }
}
?>
<style>
  .hotel-cal-wrap { max-width: 1400px; margin: 1rem auto; padding: 0 1rem; }
  .hotel-cal-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1rem; margin-bottom: 1rem; }

  /* Barre navigation semaine */
  .hotel-cal-nav { display:flex; align-items:center; gap:.5rem; flex-wrap: wrap; }
  .hotel-cal-nav .label { font-size: .85rem; color: var(--muted); }
  .hotel-cal-nav .range { font-size: 1rem; font-weight: 600; color: var(--foreground); margin: 0 .25rem; }
  .hotel-cal-nav .actions { display:flex; gap:.35rem; margin-left:auto; }
  .hotel-cal-btn {
    display:inline-flex; align-items:center; gap:.35rem;
    padding: .4rem .75rem; font-size: .8rem;
    background: var(--card); color: var(--foreground);
    border: 1px solid var(--border); border-radius: var(--radius);
    cursor: pointer; text-decoration: none;
  }
  .hotel-cal-btn:hover { background: var(--background); }
  .hotel-cal-btn.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .hotel-cal-btn.primary:hover { opacity: .9; }

  /* Légende */
  .hotel-cal-legend { display:flex; flex-wrap:wrap; gap:.65rem; font-size:.72rem; color: var(--muted); margin-top:.75rem; }
  .hotel-cal-legend span.swatch { display:inline-block; width:12px; height:12px; border-radius:3px; margin-right:.3rem; vertical-align:middle; border: 1px solid; }

  /* Grille calendrier (desktop) */
  .hotel-cal-grid {
    display: grid;
    /* 1 colonne "chambre" fixe + 7 colonnes jours */
    grid-template-columns: 130px repeat(7, minmax(120px, 1fr));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    background: var(--card);
    /* Référent pour les bandeaux séjours positionnés en absolute */
    position: relative;
  }
  .hotel-cal-cell {
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: .35rem .5rem;
    min-height: 56px;
    font-size: .78rem;
    position: relative;
    background: var(--card);
  }
  .hotel-cal-cell:last-child { border-right: 0; }
  .hotel-cal-cell.head {
    background: var(--background);
    font-weight: 600;
    color: var(--foreground);
    text-align: center;
    min-height: 44px;
    padding-top: .55rem;
    padding-bottom: .55rem;
  }
  .hotel-cal-cell.head.room { text-align: left; }
  .hotel-cal-cell.room {
    background: var(--background);
    font-weight: 600;
    color: var(--foreground);
    display:flex; flex-direction:column; justify-content:center;
    padding-left: .75rem;
  }
  .hotel-cal-cell.room .room-type { font-size: .65rem; color: var(--muted); text-transform: uppercase; font-weight: 400; }
  .hotel-cal-cell.today { background: rgba(11,94,136,.06); }
  .hotel-cal-cell .day-num { font-size: .95rem; }
  .hotel-cal-cell .day-name { font-size: .7rem; color: var(--muted); text-transform: uppercase; }
  .hotel-cal-cell.today .day-num { color: var(--primary); }

  /* Bandeau séjour */
  .hotel-cal-band {
    position: relative;
    border-radius: 4px;
    padding: 3px 6px;
    font-size: .72rem;
    line-height: 1.2;
    cursor: pointer;
    overflow: hidden;
    height: 28px;
    display:flex; align-items:center; gap:.4rem;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    transition: transform .12s, box-shadow .12s, filter .12s;
    border: 1px solid;
  }
  .hotel-cal-band:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,.1); filter: brightness(.97); }
  .hotel-cal-band .name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
  .hotel-cal-band .nights { font-size: .65rem; opacity: .8; white-space: nowrap; }

  /* Chambre hors service : hachuré rouge */
  .hotel-cal-cell.hors-service {
    background: repeating-linear-gradient(
      45deg,
      #fee2e2,
      #fee2e2 8px,
      #fecaca 8px,
      #fecaca 16px
    );
    color: #7f1d1d;
    font-size: .7rem;
    font-style: italic;
  }

  /* Vue mobile : liste verticale par chambre, un séjour par ligne */
  .hotel-cal-mobile { display: none; }
  .hotel-cal-mobile .mob-room {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .75rem .9rem;
    margin-bottom: .65rem;
  }
  .hotel-cal-mobile .mob-room-head {
    display:flex; justify-content:space-between; align-items:baseline;
    margin-bottom: .35rem;
  }
  .hotel-cal-mobile .mob-room-head .num { font-weight: 700; font-size: 1rem; color: var(--foreground); }
  .hotel-cal-mobile .mob-room-head .type { font-size: .7rem; color: var(--muted); text-transform: uppercase; }
  .hotel-cal-mobile .mob-sejour {
    display:flex; justify-content:space-between; align-items:center;
    padding: .45rem .55rem;
    border-radius: var(--radius);
    margin-bottom: .25rem;
    font-size: .82rem;
    border: 1px solid;
    cursor: pointer;
  }
  .hotel-cal-mobile .mob-sejour:hover { filter: brightness(.95); }
  .hotel-cal-mobile .mob-sejour .guest { font-weight: 600; }
  .hotel-cal-mobile .mob-sejour .dates { font-size: .72rem; opacity: .85; }
  .hotel-cal-mobile .mob-empty { font-size: .78rem; color: var(--muted); font-style: italic; padding: .35rem 0; }
  .hotel-cal-mobile .mob-hors { font-size: .75rem; color: #7f1d1d; padding: .35rem 0; }

  /* Bascule desktop → mobile */
  @media (max-width: 768px) {
    .hotel-cal-grid { display: none; }
    .hotel-cal-mobile { display: block; }
  }

  /* Drawer détail (réutilise le pattern de hotel-reception) */
  .hotel-cal-drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; display: none; }
  .hotel-cal-drawer-backdrop.open { display: block; }
  .hotel-cal-drawer {
    position: fixed; top: 0; right: 0; height: 100vh; width: 440px; max-width: 100vw;
    background: var(--card); box-shadow: var(--shadow-lg);
    transform: translateX(100%); transition: transform .25s ease; z-index: 9001;
    display:flex; flex-direction:column;
  }
  .hotel-cal-drawer.open { transform: translateX(0); }
  .hotel-cal-drawer-head {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center;
  }
  .hotel-cal-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground); }
  .hotel-cal-drawer-close { background: transparent; border: 0; cursor: pointer; color: var(--muted); padding: 4px; border-radius: var(--radius); }
  .hotel-cal-drawer-close:hover { background: var(--background); color: var(--foreground); }
  .hotel-cal-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }

  .hotel-cal-dl { margin: 0; }
  .hotel-cal-dl dt { font-size: .72rem; text-transform: uppercase; color: var(--muted); margin-top: .85rem; }
  .hotel-cal-dl dd { margin: .15rem 0 0 0; font-size: .9rem; color: var(--foreground); }
  .hotel-cal-status {
    display: inline-block; padding: 3px 9px; border-radius: 999px;
    font-size: .72rem; font-weight: 600; border: 1px solid;
  }
  .hotel-cal-error { color: #b91c1c; font-size: .85rem; padding: .75rem 0; }
</style>

<main class="hotel-cal-wrap">
  <!-- En-tête -->
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <div>
      <h2 style="margin:0;color:var(--foreground);display:flex;align-items:center;gap:.5rem;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary);">
          <rect x="3" y="4" width="18" height="18" rx="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8"  y1="2" x2="8"  y2="6"></line>
          <line x1="3"  y1="10" x2="21" y2="10"></line>
        </svg>
        Calendrier des séjours
      </h2>
      <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
        Vue semaine — chambres en lignes, jours en colonnes. Lecture seule. Actualisation toutes les 60 s.
      </div>
    </div>
    <div id="hotel-cal-clock" style="font-family:monospace;font-size:.95rem;color:var(--muted);"></div>
  </div>

  <!-- Navigation semaine -->
  <div class="hotel-cal-card">
    <div class="hotel-cal-nav">
      <a class="hotel-cal-btn" id="cal-prev" href="/hotel/calendrier?from=<?= htmlspecialchars(date('Y-m-d', strtotime($calFrom . ' -7 days'))) ?>" title="Semaine précédente"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>Précédent</a>
      <a class="hotel-cal-btn primary" id="cal-today" href="/hotel/calendrier?from=<?= htmlspecialchars($todayYmd) ?>" title="Aujourd'hui">Aujourd'hui</a>
      <a class="hotel-cal-btn" id="cal-next" href="/hotel/calendrier?from=<?= htmlspecialchars(date('Y-m-d', strtotime($calFrom . ' +7 days'))) ?>" title="Semaine suivante">Suivant<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></a>
      <span class="label">Semaine du</span>
      <span class="range" id="cal-range">
        <?= htmlspecialchars((new DateTimeImmutable($calFrom))->format('d/m/Y')) ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:middle;color:var(--muted);"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        <?= htmlspecialchars((new DateTimeImmutable($calTo))->format('d/m/Y')) ?>
      </span>
    </div>

    <!-- Légende -->
    <div class="hotel-cal-legend">
      <span><span class="swatch" style="background:#dcfce7;border-color:#16a34a;"></span> Réservé</span>
      <span><span class="swatch" style="background:#dbeafe;border-color:#2563eb;"></span> En cours</span>
      <span><span class="swatch" style="background:#e5e7eb;border-color:#6b7280;"></span> Terminé</span>
      <span><span class="swatch" style="background:#fee2e2;border-color:#dc2626;"></span> Annulé / No-show</span>
      <span><span class="swatch" style="background:repeating-linear-gradient(45deg,#fee2e2,#fee2e2 4px,#fecaca 4px,#fecaca 8px);border-color:#dc2626;"></span> Hors service</span>
    </div>
  </div>

  <!-- Grille calendrier (desktop) -->
  <div class="hotel-cal-card" style="padding:.5rem;overflow-x:auto;">
    <div class="hotel-cal-grid" id="hotel-cal-grid">
      <!-- En-tête ligne 1 : chambre + 7 jours -->
      <div class="hotel-cal-cell head room">Chambre</div>
      <?php foreach ($days as $d): ?>
        <?php $isToday = $d->format('Y-m-d') === $todayYmd; ?>
        <div class="hotel-cal-cell head<?= $isToday ? ' today' : '' ?>">
          <div class="day-name"><?= htmlspecialchars($d->format('D')) ?></div>
          <div class="day-num"><?= htmlspecialchars($d->format('d/m')) ?></div>
        </div>
      <?php endforeach; ?>

      <!-- Une ligne par chambre -->
      <?php foreach ($calChambres as $ch): ?>
        <?php
          $cid = (int)$ch['id'];
          $horsService = ($ch['etat'] ?? '') === 'hors_service';
          $roomSejours = $sejoursByChambre[$cid] ?? [];
          ?>
        <div class="hotel-cal-cell room">
          <span><?= htmlspecialchars($ch['numero']) ?></span>
          <span class="room-type"><?= htmlspecialchars($ch['type']) ?></span>
        </div>
        <?php foreach ($days as $d): ?>
          <?php
              $cellYmd = $d->format('Y-m-d');
            $isToday = $cellYmd === $todayYmd;
            $cellClasses = 'hotel-cal-cell';
            if ($isToday) {
                $cellClasses .= ' today';
            }
            // Les séjours de cette chambre : on regarde si la cellule correspond
            // à un jour où un séjour est actif. Pour la grille, on rend un
            // bandeau par séjour en position absolue par-dessus la cellule
            // du jour d'arrivée (l'API JS restituera les séjours débordant).
            // Ici on insère un placeholder ; le JS rendra tout depuis l'API.
            ?>
          <div class="<?= $cellClasses ?>"
               data-room-id="<?= $cid ?>"
               data-day="<?= htmlspecialchars($cellYmd) ?>"
               <?php if ($horsService): ?>style="background:repeating-linear-gradient(45deg,#fee2e2,#fee2e2 8px,#fecaca 8px,#fecaca 16px);"<?php endif; ?>>
          </div>
      <?php endforeach; ?>
      <?php endforeach; ?>

      <?php if (empty($calChambres)): ?>
        <div class="hotel-cal-cell" style="grid-column: 1 / -1; text-align:center; color: var(--muted); padding: 1.5rem;">
          Aucune chambre enregistrée. Créez des chambres dans la page Chambres.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Vue mobile (liste verticale par chambre) -->
  <div class="hotel-cal-mobile" id="hotel-cal-mobile">
    <?php if (empty($calChambres)): ?>
      <div class="mob-room"><div class="mob-empty">Aucune chambre enregistrée.</div></div>
    <?php else: ?>
      <?php foreach ($calChambres as $ch): ?>
        <?php
            $cid = (int)$ch['id'];
          $roomSejours = $sejoursByChambre[$cid] ?? [];
          $horsService = ($ch['etat'] ?? '') === 'hors_service';
          ?>
        <div class="mob-room">
          <div class="mob-room-head">
            <span class="num">Chambre <?= htmlspecialchars($ch['numero']) ?></span>
            <span class="type"><?= htmlspecialchars($ch['type']) ?></span>
          </div>
          <?php if ($horsService): ?>
            <div class="mob-hors"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Chambre hors service</div>
          <?php elseif (empty($roomSejours)): ?>
            <div class="mob-empty">Aucun séjour cette semaine.</div>
          <?php else: ?>
            <?php foreach ($roomSejours as $s):
                $col = $statusColors[$s['statut']] ?? $statusColors['reserve'];
                $arr = new DateTimeImmutable($s['date_arrivee']);
                $dep = new DateTimeImmutable($s['date_depart_prevue']);
                $nights = (int)$dep->diff($arr)->format('%r%a');
                if ($nights < 1) {
                    $nights = 1;
                }
                ?>
              <div class="mob-sejour"
                   data-sejour-id="<?= (int)$s['id'] ?>"
                   style="background: <?= htmlspecialchars($col['bg']) ?>; border-color: <?= htmlspecialchars($col['border']) ?>; color: <?= htmlspecialchars($col['text']) ?>;">
                <div>
                  <div class="guest"><?= htmlspecialchars(trim(($s['guest_prenom'] ?? '') . ' ' . ($s['guest_nom'] ?? ''))) ?: 'Guest #' . (int)$s['guest_id'] ?></div>
                  <div class="dates">
                    <?= htmlspecialchars($arr->format('d/m')) ?> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;opacity:.7;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg> <?= htmlspecialchars($dep->format('d/m')) ?>
                    · <?= $nights ?> nuit<?= $nights > 1 ? 's' : '' ?>
                  </div>
                </div>
                <div style="font-size:.7rem;font-weight:600;opacity:.85;"><?= htmlspecialchars($col['label']) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<!-- Données initiales pour le JS (auto-refresh) -->
<script>
  window.HOTEL_CAL = {
    from:    <?= json_encode($calFrom) ?>,
    to:      <?= json_encode($calTo) ?>,
    today:   <?= json_encode($todayYmd) ?>,
    joursLabels: <?= json_encode(array_map(fn ($d) => $d->format('Y-m-d'), $days)) ?>,
    joursDays:   <?= json_encode(array_map(fn ($d) => ['name' => $d->format('D'), 'num' => $d->format('d/m')], $days)) ?>,
    chambres:    <?= json_encode(array_map(function ($c) {
        return [
          'id'      => (int)$c['id'],
          'numero'  => $c['numero'],
          'type'    => $c['type'],
          'etat'    => $c['etat'] ?? '',
        ];
    }, $calChambres)) ?>,
    sejours: <?= json_encode(array_map(function ($s) {
        return [
          'id'                  => (int)$s['id'],
          'chambre_id'          => (int)$s['chambre_id'],
          'guest_id'            => (int)$s['guest_id'],
          'guest_nom'           => $s['guest_nom'] ?? '',
          'guest_prenom'        => $s['guest_prenom'] ?? '',
          'guest_tel'           => $s['guest_tel'] ?? '',
          'date_arrivee'        => $s['date_arrivee'],
          'date_depart_prevue'  => $s['date_depart_prevue'],
          'date_depart_reelle'  => $s['date_depart_reelle'],
          'statut'              => $s['statut'],
          'prix_par_nuit'       => (float)($s['prix_par_nuit'] ?? 0),
          'remise_pct'          => (float)($s['remise_pct'] ?? 0),
          'notes'               => $s['notes'] ?? '',
          'chambre_numero'      => $s['chambre_numero'] ?? '',
          'chambre_type'        => $s['chambre_type'] ?? '',
        ];
    }, $calSejours)) ?>,
    statusColors: <?= json_encode($statusColors) ?>
  };
</script>

<!-- Drawer détail (clic sur un bandeau) -->
<div class="hotel-cal-drawer-backdrop" id="hotel-cal-backdrop"></div>
<aside class="hotel-cal-drawer" id="hotel-cal-drawer" role="dialog" aria-modal="true" aria-labelledby="hotel-cal-drawer-title">
  <header class="hotel-cal-drawer-head">
    <h3 id="hotel-cal-drawer-title">Détail du séjour</h3>
    <button type="button" class="hotel-cal-drawer-close" id="hotel-cal-drawer-close" aria-label="Fermer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y1="18"></line><line x1="6" y1="6" x2="18" y1="18"></line>
      </svg>
    </button>
  </header>
  <div class="hotel-cal-drawer-body" id="hotel-cal-drawer-body">
    <div id="hotel-cal-drawer-content">Chargement…</div>
  </div>
</aside>

<script src="/assets/js/hotel-calendrier.js?v=1"></script>