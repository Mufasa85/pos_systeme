<?php
// Vue PARTIELLE — Sprint 1
// Le contenu réel (KPI dynamiques, séjours en cours, tâches housekeeping)
// sera branché au Sprint 4 via /api/hotel/dashboard.
// Référence : docs/PLAN_HOTEL_V1.md
//
// Cette vue est chargée par App\Controllers\PageController::render(),
// qui enveloppe déjà le contenu avec app/views/layout/header.php et
// app/views/layout/footer.php. Elle ne doit donc PAS être un document
// HTML complet ni tenter d'inclure le layout elle-même.
$page = 'hotel';

// Date du jour formatée FR pour les bandeaux KPI (Sprint 4 réutilisera cette variable).
$todayFr = date('l d F Y');
$daysFr  = [
    'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
    'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche',
    'January' => 'janvier', 'February' => 'février', 'March' => 'mars', 'April' => 'avril',
    'May' => 'mai', 'June' => 'juin', 'July' => 'juillet', 'August' => 'août',
    'September' => 'septembre', 'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre',
];
foreach ($daysFr as $en => $fr) {
    $todayFr = str_replace($en, $fr, $todayFr);
}

// Petite bibliothèque d'icônes SVG (Feather-style 24×24, stroke 2, fill none).
// Helper local : hôtel_icon(NAME, SIZE, EXTRA_ATTRS).
if (!function_exists('hotel_icon')) {
    function hotel_icon(string $name, int $size = 18, string $extra = ''): string
    {
        $common = "width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 24 24\" fill=\"none\" "
                . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
                . "stroke-linejoin=\"round\" {$extra}";
        $paths = [
            'dashboard'    => '<rect x="3" y="3" width="7" height="7" rx="1"></rect>'
                            . '<rect x="14" y="3" width="7" height="7" rx="1"></rect>'
                            . '<rect x="14" y="14" width="7" height="7" rx="1"></rect>'
                            . '<rect x="3" y="14" width="7" height="7" rx="1"></rect>',
            'hotel'        => '<path d="M3 21V8l9-5 9 5v13"></path>'
                            . '<path d="M9 21V12h6v9"></path>'
                            . '<line x1="3" y1="21" x2="21" y2="21"></line>',
            'bed'          => '<path d="M2 17v-5a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v5"></path>'
                            . '<path d="M2 17h20"></path>'
                            . '<path d="M6 12V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"></path>',
            'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2"></rect>'
                            . '<line x1="16" y1="2" x2="16" y2="6"></line>'
                            . '<line x1="8"  y1="2" x2="8"  y2="6"></line>'
                            . '<line x1="3"  y1="10" x2="21" y2="10"></line>',
            'sparkle'      => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"></path>'
                            . '<circle cx="12" cy="12" r="3"></circle>',
            'wallet'       => '<path d="M20 7H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"></path>'
                            . '<path d="M16 12h.01"></path>'
                            . '<path d="M2 9V7a2 2 0 0 1 2-2h14"></path>',
            'trend-up'     => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>'
                            . '<polyline points="16 7 22 7 22 13"></polyline>',
            'star'         => '<polygon points="12 2 15.1 8.6 22 9.3 17 14 18.5 21 12 17.8 5.5 21 7 14 2 9.3 8.9 8.6"></polygon>',
            'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>'
                            . '<circle cx="12" cy="7" r="4"></circle>',
            'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>'
                            . '<circle cx="9" cy="7" r="4"></circle>'
                            . '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>'
                            . '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'activity'     => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
            'bell'         => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>'
                            . '<path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
            'check'        => '<polyline points="20 6 9 17 4 12"></polyline>',
            'arrow-up'     => '<line x1="12" y1="19" x2="12" y2="5"></line>'
                            . '<polyline points="5 12 12 5 19 12"></polyline>',
            'refresh'      => '<polyline points="23 4 23 10 17 10"></polyline>'
                            . '<polyline points="1 20 1 14 7 14"></polyline>'
                            . '<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
        ];
        $path = $paths[$name] ?? '<circle cx="12" cy="12" r="9"></circle>';
        return "<svg {$common}>{$path}</svg>";
    }
}
?>
<style>
  /* =========================================================
     Hôtel > Tableau de bord — styles présentation uniquement.
     Aucune logique métier, aucune dépendance hors du bloc.
     Utilise les variables globales déjà définies dans styles.css.
     ========================================================= */
  .hotel-dash-wrap {
    max-width: 100%;
    margin: 0;
    padding: 1.75rem 1.5rem 3rem 0;
  }
  /* Le .main-content global impose un padding: 1.5rem (24px) sur les
     4 côtés. Pour cette page on SUPPRIME le padding gauche (et haut
     pour resserrer contre la sidebar) sur le main-content qui contient
     notre dashboard. Ciblé via :has() — supporté sur tous les
     navigateurs modernes (Chrome 105+, Safari 15.4+, Firefox 121+).
     Override scopé à cette vue uniquement (pas d'effet sur les
     autres pages). */
  main.main-content:has(> .hotel-dash-wrap) {
    padding-left: 0 !important;
    padding-right: 1.5rem !important;
    padding-top: 0 !important;
  }

  /* ── Page header ─────────────────────────────────────── */
  .hotel-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border);
  }
  .hotel-page-header__title-block { display: flex; align-items: center; gap: 1rem; min-width: 0; }
  .hotel-page-header__icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: #e0f2fa;
    color: #0B5E88;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(15,23,42,0.06);
  }
  .hotel-page-header__icon svg { width: 24px; height: 24px; }
  .hotel-page-header__title {
    font-size: 1.55rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.2;
    letter-spacing: -0.01em;
  }
  .hotel-page-header__sub {
    font-size: 0.875rem;
    color: #64748b;
    margin-top: 0.25rem;
    font-weight: 400;
  }
  .hotel-page-header__meta {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8rem; color: #64748b;
    background: var(--card, #fff);
    border: 1px solid var(--border);
    padding: 0.5rem 0.85rem;
    border-radius: 999px;
  }
  .hotel-page-header__meta svg { width: 14px; height: 14px; }

  /* ── KPI grid ────────────────────────────────────────── */
  .hotel-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    justify-content: start;
    margin-bottom: 1.75rem;
  }
  .hotel-kpi {
    background: var(--card, #fff);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem 1.5rem 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15,23,42,0.06);
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    position: relative;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    min-height: 168px;
  }
  .hotel-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px -4px rgba(15,23,42,0.10), 0 3px 6px -2px rgba(15,23,42,0.06);
    border-color: transparent;
  }
  .hotel-kpi__head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.75rem;
  }
  .hotel-kpi__icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .hotel-kpi__icon svg { width: 24px; height: 24px; }
  .hotel-kpi--blue   .hotel-kpi__icon { background: #e0f2fa; color: #0B5E88; }
  .hotel-kpi--green  .hotel-kpi__icon { background: #d1fae5; color: #059669; }
  .hotel-kpi--amber  .hotel-kpi__icon { background: #fef3c7; color: #d97706; }
  .hotel-kpi--indigo .hotel-kpi__icon { background: #e0e7ff; color: #6366f1; }

  .hotel-kpi__label {
    font-size: 0.85rem;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    line-height: 1.15;
    margin-top: auto;
  }
  .hotel-kpi__value {
    font-size: 2.35rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.05;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.025em;
  }
  .hotel-kpi__value-suffix {
    font-size: 1.1rem;
    color: #64748b;
    font-weight: 500;
    margin-left: 0.3rem;
  }
  .hotel-kpi__hint {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem;
    color: #64748b;
    margin-top: 0.2rem;
  }
  .hotel-kpi__hint svg { width: 13px; height: 13px; }
  .hotel-kpi__pulse {
    position: absolute;
    top: 12px; right: 12px;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 0 #10b981;
    animation: hotel-kpi-pulse 2.4s ease-out infinite;
  }
  @keyframes hotel-kpi-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(16,185,129,0.45); }
    70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
    100% { box-shadow: 0 0 0 0   rgba(16,185,129,0); }
  }

  /* ── Section panel ───────────────────────────────────── */
  .hotel-section {
    background: var(--card, #fff);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 2px rgba(15,23,42,0.06);
    overflow: hidden;
  }
  .hotel-section__head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
  }
  .hotel-section__head h3 {
    margin: 0; font-size: 1rem; font-weight: 600;
    color: #0f172a;
    display: flex; align-items: center; gap: 0.55rem;
  }
  .hotel-section__head h3 svg { width: 18px; height: 18px; color: #0B5E88; }
  .hotel-section__count {
    background: #e0f2fa;
    color: #0B5E88;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
  }
  .hotel-section__body { padding: 1.25rem; }

  /* ── Pills ───────────────────────────────────────────── */
  .hotel-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: 1px solid transparent;
  }
  .hotel-pill svg { width: 12px; height: 12px; }
  .hotel-pill--info    { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
  .hotel-pill--neutral { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

  /* ── Empty state ─────────────────────────────────────── */
  .hotel-empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #64748b;
  }
  .hotel-empty__icon {
    width: 64px; height: 64px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #e0f2fa;
    color: #0B5E88;
  }
  .hotel-empty__icon svg { width: 28px; height: 28px; }
  .hotel-empty__title {
    font-size: 0.95rem; font-weight: 600;
    color: #0f172a;
    margin: 0 0 0.35rem 0;
  }
  .hotel-empty__hint { font-size: 0.82rem; line-height: 1.5; }
  .hotel-empty__hint a { color: #0B5E88; text-decoration: none; font-weight: 600; }
  .hotel-empty__hint a:hover { text-decoration: underline; }

  /* ── Task row (placeholder structure) ────────────────── */
  .hotel-task {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
    transition: background .15s ease;
  }
  .hotel-task:last-child { border-bottom: none; }
  .hotel-task:hover { background: #f8fafc; }
  .hotel-task__avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: #e0f2fa;
    color: #0B5E88;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
  }
  .hotel-task__avatar svg { width: 18px; height: 18px; }
  .hotel-task__body { flex: 1; min-width: 0; }
  .hotel-task__title { font-size: 0.9rem; font-weight: 600; color: #0f172a; margin: 0; }
  .hotel-task__sub   { font-size: 0.78rem; color: #64748b; margin: 2px 0 0; }

  /* ── Two columns layout ─────────────────────────────── */
  .hotel-dash-cols {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1rem;
    justify-content: start;
  }
  @media (max-width: 980px) {
    .hotel-dash-cols { grid-template-columns: 1fr; }
    .hotel-dash-wrap { padding: 1rem 1rem 1rem 0; margin-left: 0; }
    .hotel-page-header__title { font-size: 1.25rem; }
    .hotel-kpi__value { font-size: 1.5rem; }
  }

</style>

<div class="main-content">
  <div class="hotel-dash-wrap">

    <header class="hotel-page-header">
      <div class="hotel-page-header__title-block">
        <div class="hotel-page-header__icon" aria-hidden="true">
          <?= hotel_icon('dashboard', 24) ?>
        </div>
        <div>
          <h1 class="hotel-page-header__title">Tableau de bord &mdash; Manager</h1>
          <p class="hotel-page-header__sub">
            Vue d&rsquo;ensemble en temps r&eacute;el : occupation, housekeeping et performance du jour.
          </p>
        </div>
      </div>
      <div class="hotel-page-header__meta">
        <?= hotel_icon('refresh', 14) ?>
        <span>Donn&eacute;es en direct &middot; <?= htmlspecialchars(ucfirst($todayFr)) ?></span>
      </div>
    </header>

    <section class="hotel-kpi-grid" aria-label="Indicateurs cl&eacute;s du jour">

      <article class="hotel-kpi hotel-kpi--blue">
        <div class="hotel-kpi__head">
          <div class="hotel-kpi__icon"><?= hotel_icon('bed', 20) ?></div>
        </div>
        <div class="hotel-kpi__label">Chambres occup&eacute;es</div>
        <div class="hotel-kpi__value">&mdash; <span class="hotel-kpi__value-suffix">/ 20</span></div>
        <div class="hotel-kpi__hint"><?= hotel_icon('trend-up', 12) ?> Taux d&rsquo;occupation en temps r&eacute;el</div>
      </article>

      <article class="hotel-kpi hotel-kpi--amber">
        <div class="hotel-kpi__head">
          <div class="hotel-kpi__icon"><?= hotel_icon('sparkle', 20) ?></div>
        </div>
        <div class="hotel-kpi__label">&Agrave; nettoyer</div>
        <div class="hotel-kpi__value">&mdash;</div>
        <div class="hotel-kpi__hint"><?= hotel_icon('user', 12) ?> T&acirc;ches housekeeping en attente</div>
      </article>

      <article class="hotel-kpi hotel-kpi--green">
        <div class="hotel-kpi__head">
          <div class="hotel-kpi__icon"><?= hotel_icon('wallet', 20) ?></div>
          <span class="hotel-kpi__pulse" aria-hidden="true"></span>
        </div>
        <div class="hotel-kpi__label">Recettes du jour</div>
        <div class="hotel-kpi__value">&mdash; <span class="hotel-kpi__value-suffix">FC</span></div>
        <div class="hotel-kpi__hint"><?= hotel_icon('trend-up', 12) ?> S&eacute;jours factur&eacute;s aujourd&rsquo;hui</div>
      </article>

      <article class="hotel-kpi hotel-kpi--indigo">
        <div class="hotel-kpi__head">
          <div class="hotel-kpi__icon"><?= hotel_icon('star', 20) ?></div>
        </div>
        <div class="hotel-kpi__label">Extras &amp; services</div>
        <div class="hotel-kpi__value">&mdash; <span class="hotel-kpi__value-suffix">FC</span></div>
        <div class="hotel-kpi__hint"><?= hotel_icon('activity', 12) ?> Services additionnels du jour</div>
      </article>

    </section>

    <!-- ============== Two columns ====================== -->
    <div class="hotel-dash-cols">

      <section class="hotel-section" aria-labelledby="sejours-cours-title">
        <header class="hotel-section__head">
          <h3 id="sejours-cours-title">
            <?= hotel_icon('users', 18) ?>
            S&eacute;jours en cours
          </h3>
          <span class="hotel-section__count" id="sejours-cours-count">0</span>
        </header>
        <div class="hotel-section__body" id="sejours-cours-body">
          <div class="hotel-empty">
            <div class="hotel-empty__icon"><?= hotel_icon('bed', 28) ?></div>
            <p class="hotel-empty__title">Aucun s&eacute;jour en cours</p>
            <p class="hotel-empty__hint">
              Les check-ins effectu&eacute;s appara&icirc;tront ici automatiquement.<br>
              Vue compl&egrave;te disponible sur <a href="/hotel/reception">R&eacute;ception</a>.
            </p>
          </div>
        </div>
      </section>

      <section class="hotel-section" aria-labelledby="housekeeping-title">
        <header class="hotel-section__head">
          <h3 id="housekeeping-title">
            <?= hotel_icon('sparkle', 18) ?>
            Housekeeping du jour
          </h3>
          <span class="hotel-section__count" id="housekeeping-count">0</span>
        </header>
        <div class="hotel-section__body" id="housekeeping-body">
          <div class="hotel-empty">
            <div class="hotel-empty__icon"><?= hotel_icon('check', 28) ?></div>
            <p class="hotel-empty__title">Aucune t&acirc;che en attente</p>
            <p class="hotel-empty__hint">
              Les m&eacute;nages &agrave; effectuer appara&icirc;tront ici d&egrave;s qu&rsquo;une chambre passe en <em>&agrave; nettoyer</em>.<br>
              Suivi d&eacute;taill&eacute; sur <a href="/hotel/etages">&Eacute;tages</a>.
            </p>
          </div>
        </div>
      </section>

    </div>

    </div>
</div>
