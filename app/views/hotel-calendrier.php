<?php
/**
 * Page Hôtel > Calendrier
 * Sprint en cours : placeholder. Le calendrier des séjours (vue par jour /
 * semaine / mois), des arrivées, des départs, et de l'occupation par chambre
 * sera ajouté au prochain sprint.
 *
 * Visible pour les managers uniquement (et super_admin).
 */
$page = 'hotel-calendrier';
?>
<style>
  .hotel-placeholder {
    max-width: 960px;
    margin: 2.5rem auto;
    padding: 2.5rem;
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    text-align: center;
  }
  .hotel-placeholder h1 { color: #2c3e50; margin-bottom: 0.5rem; font-size: 1.5rem; }
  .hotel-placeholder .badge {
    display: inline-block;
    padding: .25rem .75rem;
    background: #ede9fe;
    color: #5b21b6;
    border-radius: 999px;
    font-size: .8rem;
    font-weight: 600;
    margin-bottom: 1.25rem;
  }
  .hotel-placeholder p { color: #555; line-height: 1.6; margin: .25rem 0; }
  .hotel-placeholder .sprint-info {
    margin-top: 1.5rem;
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border-left: 4px solid #5b21b6;
    border-radius: 6px;
    text-align: left;
    font-size: .85rem;
  }
</style>

<main class="main-content">
  <div class="hotel-placeholder">
    <span class="badge">Module Hôtel — Calendrier (manager)</span>
    <h1>📅 Calendrier des séjours</h1>
    <p>Vue jour / semaine / mois des séjours, arrivées, départs, occupation par chambre.</p>
    <p><em>Le contenu sera ajouté au prochain sprint.</em></p>
    <div class="sprint-info">
      <strong>Accès :</strong> réservé au manager (et super_admin). Les réceptionnistes et
      gouvernantes n'ont pas accès au calendrier global ; ils utilisent Réception / Étages.
    </div>
  </div>
</main>
