      <!-- Page des lots — liste paginée triée par péremption -->
      <div id="page-produits-lots" class="page <?= ($page ?? '') == 'produits-lots' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Lots</h2>
        </div>

        <div class="filters-bar">
          <input type="text" id="lots-search" placeholder="Rechercher un produit ou un n° de lot...">

          <label style="display:flex; align-items:center; gap:6px; font-size:0.9rem;">
            <input type="checkbox" id="lots-with-stock" checked>
            Seulement les lots en stock
          </label>

          <span class="pagination-info" id="lots-count">Chargement…</span>
        </div>

        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Code-barres</th>
                <th>Lot</th>
                <th>Stock</th>
                <th>Péremption</th>
                <th>Réception</th>
                <th>Jours restants</th>
              </tr>
            </thead>
            <tbody id="lots-body">
              <tr>
                <td colspan="7" style="text-align:center; padding:1rem;">Chargement…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ══ PAGINATION ══════════════════════════════════════════════
             ⚠️ KEYSET : pas de « page N ». On affiche la page courante et
             on propose « Précédent » / « Suivant ».

             Raison (mesurée à 50 000 produits / 85 000 lots) :
             l'OFFSET oblige MySQL à PRODUIRE puis JETER les lignes
             précédentes — 177 ms à la page 1000, contre ~2 ms en
             keyset, quel que soit le numéro de page.

             Le prix à payer : impossible de sauter directement à la
             page 500. C'est un compromis assumé — dans une boutique, on
             cherche un lot par produit, par péremption proche, ou par
             recherche, pas en scrollant 500 pages. -->
        <div class="products-pagination">
          <div class="pagination-info" id="lots-range"></div>
          <div class="pagination-buttons">
            <button class="btn btn-ghost btn-small" id="lots-first" type="button">&laquo; Première</button>
            <button class="btn btn-ghost btn-small" id="lots-prev" type="button">&lsaquo; Précédent</button>
            <span class="pagination-page" id="lots-page">Page 1</span>
            <button class="btn btn-ghost btn-small" id="lots-next" type="button">Suivant &rsaquo;</button>
          </div>
        </div>
      </div>

      <script>
      /*
       * PAGE DES LOTS — pagination keyset.
       *
       * Le serveur renvoie la BORNE de la page suivante (`next: {exp, id}`) ;
       * on la renvoie telle quelle pour obtenir la page suivante. On
       * empile les bornes en mémoire pour le bouton « Précédent ».
       */
      (function () {
        'use strict';

        var PAGE_SIZE = 50;
        var etat = {
          bornes: [],        // empil des fins de page (pour « Précédent »)
          pageCourante: 1,
          total: 0,
          chargement: false
        };

        function urlLots(params) {
          var base = APP_URL + '/api/product-batches/page';
          var qs = [];
          if (params.limit) qs.push('limit=' + params.limit);
          if (params.exp) qs.push('exp=' + encodeURIComponent(params.exp));
          if (params.id) qs.push('id=' + encodeURIComponent(params.id));
          if (params.with_stock !== undefined) qs.push('with_stock=' + (params.with_stock ? '1' : '0'));
          if (params.shop_id) qs.push('shop_id=' + encodeURIComponent(params.shop_id));
          return base + (qs.length ? '?' + qs.join('&') : '');
        }

        function fmtDate(v) {
          if (!v) return '—';
          var d = new Date(v);
          if (isNaN(d.getTime())) return v;
          return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
        }

        function joursRestants(v) {
          if (!v) return null;
          var d = new Date(v);
          if (isNaN(d.getTime())) return null;
          var auj = new Date();
          auj.setHours(0, 0, 0, 0);
          return Math.floor((d.getTime() - auj.getTime()) / 86400000);
        }

        function rendreLigne(lot) {
          var j = joursRestants(lot.date_expiration);
          var badge = '';
          if (j === null) {
            badge = '<span style="color:var(--muted);">—</span>';
          } else if (j < 0) {
            badge = '<span class="badge badge-danger">Périmé</span>';
          } else if (j <= 30) {
            badge = '<span class="badge badge-warning">' + j + 'j</span>';
          } else {
            badge = '<span>' + j + 'j</span>';
          }

          return '<tr>' +
            '<td><strong>' + escapeHtml(lot.product_name || '') + '</strong></td>' +
            '<td><code>' + escapeHtml(lot.code_barres || '') + '</code></td>' +
            '<td>' + escapeHtml(lot.batch_number || '—') + '</td>' +
            '<td>' + Number(lot.stock || 0).toFixed(2) + '</td>' +
            '<td>' + fmtDate(lot.date_expiration) + '</td>' +
            '<td>' + fmtDate(lot.date_reception) + '</td>' +
            '<td>' + badge + '</td>' +
            '</tr>';
        }

        function escapeHtml(s) {
          return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function majBoutons(hasMore) {
          var prev = document.getElementById('lots-prev');
          var next = document.getElementById('lots-next');
          var first = document.getElementById('lots-first');
          var page = document.getElementById('lots-page');

          if (prev) prev.disabled = etat.bornes.length <= 1;
          if (first) first.disabled = etat.bornes.length <= 1;
          if (next) next.disabled = !hasMore;
          if (page) page.textContent = 'Page ' + etat.pageCourante;
        }

        function chargerPage(exp, id) {
          if (etat.chargement) return;
          etat.chargement = true;

          var body = document.getElementById('lots-body');
          if (body && !exp) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1rem;">Chargement…</td></tr>';
          }

          var url = urlLots({
            limit: PAGE_SIZE,
            exp: exp,
            id: id,
            with_stock: document.getElementById('lots-with-stock')
              ? document.getElementById('lots-with-stock').checked : true
          });

          fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
              if (!json || !json.success) throw new Error('Réponse invalide');

              var rows = json.rows || [];
              var body2 = document.getElementById('lots-body');
              if (body2) {
                if (rows.length === 0) {
                  body2.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1rem;">Aucun lot.</td></tr>';
                } else {
                  var html = '';
                  for (var i = 0; i < rows.length; i++) html += rendreLigne(rows[i]);
                  body2.innerHTML = html;
                }
              }

              etat.total = json.total || 0;

              var count = document.getElementById('lots-count');
              if (count) {
                count.textContent = rows.length
                  ? rows.length + ' lot(s) affiché(s) sur ' + etat.total
                  : 'Aucun lot';
              }

              var range = document.getElementById('lots-range');
              if (range) {
                var d = etat.pageCourante > 1 ? (etat.pageCourante - 1) * PAGE_SIZE + 1 : 0;
                range.textContent = etat.total > 0
                  ? d + '–' + (d + rows.length - 1) + ' sur ' + etat.total + ' lots'
                  : 'Aucun lot';
              }

              // La borne de la page suivante est empilée pour « Précédent ».
              if (json.next) {
                etat.bornes[etat.pageCourante] = json.next;
              }

              majBoutons(json.has_more);
              etat.chargement = false;
            })
            .catch(function (err) {
              etat.chargement = false;
              var body3 = document.getElementById('lots-body');
              if (body3) {
                body3.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1rem; color:var(--danger);">'
                  + escapeHtml(err.message || 'Erreur de chargement') + '</td></tr>';
              }
            });
        }

        function allerPageSuivante() {
          var borne = etat.bornes[etat.pageCourante];
          if (!borne) return;
          etat.pageCourante++;
          chargerPage(borne.exp, borne.id);
        }

        function allerPagePrecedente() {
          if (etat.bornes.length <= 1) return;
          etat.pageCourante--;
          var borne = etat.bornes[etat.pageCourante - 1];
          if (etat.pageCourante <= 1) {
            etat.bornes = [];
            chargerPage(null, null);
          } else {
            chargerPage(borne.exp, borne.id);
          }
        }

        function reinitialiser() {
          etat.bornes = [];
          etat.pageCourante = 1;
          chargerPage(null, null);
        }

        document.addEventListener('DOMContentLoaded', function () {
          var next = document.getElementById('lots-next');
          var prev = document.getElementById('lots-prev');
          var first = document.getElementById('lots-first');
          var avecStock = document.getElementById('lots-with-stock');
          var search = document.getElementById('lots-search');

          if (next) next.addEventListener('click', allerPageSuivante);
          if (prev) prev.addEventListener('click', allerPagePrecedente);
          if (first) first.addEventListener('click', function () {
            etat.bornes = [];
            etat.pageCourante = 1;
            chargerPage(null, null);
          });
          if (avecStock) avecStock.addEventListener('change', reinitialiser);
          if (search) search.addEventListener('input', reinitialiser);

          window.lotsPage = {
            recharger: reinitialiser,
            charger: chargerPage
          };
        });
      })();
      </script>
