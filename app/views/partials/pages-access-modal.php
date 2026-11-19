<?php

/**
 * Modale de gestion des pages bloquees (vue PARTIELLE).
 *
 * Partagée par :
 *   - app/views/hotel-roles.php      (colonne « Pages bloquées »)
 *   - app/views/pages-bloquees.php   (écran dédié, sidebar super_admin)
 *
 * Variables attendues :
 *   - $pagesCatalog   : [pageKey => ['label' => ..., 'family' => ...]]
 *   - $blockedByUser  : [userId => [pageKey, ...]]  (état initial)
 *   - $pagesEndpoint  : endpoint de sauvegarde
 *
 * Le catalogue est rendu en JSON : le JS pilote la modale à partir de cette
 * seule source de vérité (aucune liste dupliquée côté client).
 */
$pagesCatalog  = $pagesCatalog  ?? [];
$blockedByUser = $blockedByUser ?? [];
$pagesEndpoint = $pagesEndpoint ?? '/api/user/page-access/update';
// Boutique ciblée : le super_admin peut en changer via ?shop_id=.
// Elle est envoyée avec chaque POST (scoping multi-tenant côté serveur).
$pagesShopId   = $pagesShopId   ?? 0;
?>
<style>
  /* ── Badges « Pages bloquées » (blocage individuel) ─────────── */
  .hotel-roles-pages {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
    min-width: 0;
    align-items: center;
  }

  .hotel-roles-page {
    font: inherit;
    font-size: .7rem;
    font-weight: 600;
    padding: .18rem .45rem;
    border-radius: 999px;
    cursor: pointer;
    border: 1px solid transparent;
    white-space: nowrap;
    line-height: 1.4;
    transition: transform .1s ease, filter .12s ease;
  }

  .hotel-roles-page:hover {
    transform: translateY(-1px);
    filter: brightness(.96);
  }

  /* Page BLOQUÉE : fond rouge + texte barré. */
  .hotel-roles-page.is-blocked {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fca5a5;
    text-decoration: line-through;
  }

  /* Page AUTORISÉE : fond vert (état par défaut, aucun blocage). */
  .hotel-roles-page.is-open {
    background: #dcfce7;
    color: #166534;
    border-color: #86efac;
  }

  .hotel-roles-page.is-saving {
    opacity: .6;
    cursor: wait;
  }

  .hotel-roles-pages-empty {
    font-size: .75rem;
    color: #94a3b8;
    font-style: italic;
  }

  /* Bouton ouvrant la modale de sélection des pages. */
  .hotel-roles-pages-manage {
    font: inherit;
    font-size: .7rem;
    font-weight: 600;
    padding: .2rem .5rem;
    border-radius: 6px;
    cursor: pointer;
    background: #0B5E88;
    color: #fff;
    border: 1px solid #0B5E88;
    white-space: nowrap;
    line-height: 1.4;
  }

  .hotel-roles-pages-manage:hover {
    filter: brightness(1.1);
  }

  /* ── Modale de sélection des pages bloquées ────────────────── */
  .hotel-pages-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
  }

  .hotel-pages-modal[hidden] {
    display: none;
  }

  .hotel-pages-modal__box {
    background: #fff;
    border-radius: 12px;
    max-width: 720px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    padding: 1.5rem;
    box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .35);
  }

  .hotel-pages-modal__title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 .25rem;
  }

  .hotel-pages-modal__subtitle {
    font-size: .8rem;
    color: #64748b;
    margin: 0 0 1rem;
  }

  .hotel-pages-modal__family {
    margin-bottom: 1rem;
  }

  .hotel-pages-modal__family h4 {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    margin: 0 0 .5rem;
  }

  .hotel-pages-modal__list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: .4rem;
  }

  .hotel-pages-modal__item {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-size: .82rem;
    padding: .35rem .5rem;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    cursor: pointer;
  }

  .hotel-pages-modal__item:hover {
    background: #f8fafc;
  }

  .hotel-pages-modal__item input {
    cursor: pointer;
  }

  .hotel-pages-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border, #e2e8f0);
  }

  .hotel-pages-modal__btn {
    font: inherit;
    font-size: .85rem;
    font-weight: 600;
    padding: .45rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
  }

  .hotel-pages-modal__btn.is-primary {
    background: #0B5E88;
    border-color: #0B5E88;
    color: #fff;
  }

  .hotel-pages-modal__status {
    font-size: .8rem;
    margin-right: auto;
    align-self: center;
  }
</style>

<div class="hotel-pages-modal" id="hotel-pages-modal" hidden
     data-catalog='<?= htmlspecialchars(json_encode($pagesCatalog, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
     data-endpoint="<?= htmlspecialchars($pagesEndpoint) ?>">
  <div class="hotel-pages-modal__box" role="dialog" aria-modal="true" aria-labelledby="hotel-pages-modal-title">
    <h3 class="hotel-pages-modal__title" id="hotel-pages-modal-title">Pages bloquées</h3>
    <p class="hotel-pages-modal__subtitle">
      Cochez les pages à bloquer pour <strong id="hotel-pages-modal-user">cet utilisateur</strong>.
      Une page cochée disparaît de son menu et devient inaccessible (URL et API).
    </p>

    <div id="hotel-pages-modal-body"></div>

    <div class="hotel-pages-modal__footer">
      <span class="hotel-pages-modal__status" id="hotel-pages-modal-status" aria-live="polite"></span>
      <button type="button" class="hotel-pages-modal__btn" id="hotel-pages-modal-cancel">Annuler</button>
      <button type="button" class="hotel-pages-modal__btn is-primary" id="hotel-pages-modal-save">Enregistrer</button>
    </div>
  </div>
</div>

<script>
  (function () {
    'use strict';

    const modal = document.getElementById('hotel-pages-modal');
    if (!modal) return;

    const modalBody = document.getElementById('hotel-pages-modal-body');
    const modalUser = document.getElementById('hotel-pages-modal-user');
    const modalStatus = document.getElementById('hotel-pages-modal-status');
    const modalSave = document.getElementById('hotel-pages-modal-save');
    const modalCancel = document.getElementById('hotel-pages-modal-cancel');
    const PAGE_ENDPOINT = modal.getAttribute('data-endpoint');

    // Boutique ciblée (scoping multi-tenant appliqué par le serveur).
    window.PAGES_ACCESS_SHOP_ID = <?= (int)$pagesShopId ?>;

    // Catalogue des pages (source de vérité : le service PHP).
    const PAGE_CATALOG = JSON.parse(modal.getAttribute('data-catalog') || '{}');

    // Pages actuellement bloquées, par utilisateur (rendu par PHP).
    const BLOCKED_PAGES = <?= json_encode(array_map(
        static function (array $keys): array {
            return array_values(array_filter($keys, static function ($k) {
                return \App\Services\UserPageAccessService::isKnownPage((string)$k);
            }));
        },
        $blockedByUser
    ), JSON_UNESCAPED_UNICODE) ?>;

    // Libellés des familles (ordre d'affichage).
    const FAMILY_LABELS = {
      general: 'Pages générales (POS)',
      hotel: 'Pages Hôtel',
    };

    let modalUserId = 0;
    let modalBlocked = new Set();

    function setModalStatus(text, isError) {
      if (!modalStatus) return;
      modalStatus.textContent = text || '';
      modalStatus.style.color = isError ? '#dc2626' : '#16a34a';
    }

    // Rend la liste des pages, regroupée par famille.
    function renderModalBody() {
      const families = {};
      Object.keys(PAGE_CATALOG).forEach(function (key) {
        const def = PAGE_CATALOG[key] || {};
        const family = def.family || 'general';
        if (!families[family]) families[family] = [];
        families[family].push({ key: key, label: def.label || key });
      });

      modalBody.innerHTML = '';
      Object.keys(families).forEach(function (family) {
        const wrap = document.createElement('div');
        wrap.className = 'hotel-pages-modal__family';

        const title = document.createElement('h4');
        title.textContent = FAMILY_LABELS[family] || family;
        wrap.appendChild(title);

        const list = document.createElement('div');
        list.className = 'hotel-pages-modal__list';

        families[family].forEach(function (page) {
          const label = document.createElement('label');
          label.className = 'hotel-pages-modal__item';

          const input = document.createElement('input');
          input.type = 'checkbox';
          input.value = page.key;
          input.checked = modalBlocked.has(page.key);
          input.addEventListener('change', function () {
            if (this.checked) {
              modalBlocked.add(page.key);
            } else {
              modalBlocked.delete(page.key);
            }
          });

          const text = document.createElement('span');
          text.textContent = page.label;

          label.appendChild(input);
          label.appendChild(text);
          list.appendChild(label);
        });

        wrap.appendChild(list);
        modalBody.appendChild(wrap);
      });
    }

    // Ouvre la modale pour un utilisateur donné.
    function openPagesModal(userId, userName) {
      modalUserId = parseInt(userId, 10) || 0;
      modalBlocked = new Set(BLOCKED_PAGES[String(modalUserId)] || []);
      if (modalUser) {
        modalUser.textContent = userName || 'cet utilisateur';
      }
      setModalStatus('', false);
      renderModalBody();
      modal.hidden = false;
    }

    function closePagesModal() {
      modal.hidden = true;
      modalUserId = 0;
    }


    // ── Enregistrement : on n'envoie que les DIFFÉRENCES ──────────
    async function savePagesModal() {
      if (!modalUserId) return;

      // État courant (rendu par PHP) : la référence du calcul.
      const before = new Set(BLOCKED_PAGES[String(modalUserId)] || []);

      // Les pages à bloquer (cochées et pas déjà bloquées).
      const toBlock = [];
      modalBlocked.forEach(function (key) {
        if (!before.has(key)) toBlock.push(key);
      });
      // Les pages à débloquer (décochées et pourtant bloquées).
      const toUnblock = [];
      before.forEach(function (key) {
        if (!modalBlocked.has(key)) toUnblock.push(key);
      });

      if (!toBlock.length && !toUnblock.length) {
        setModalStatus('Aucun changement.', false);
        closePagesModal();
        return;
      }

      if (modalSave) {
        modalSave.disabled = true;
        modalSave.textContent = 'Enregistrement...';
      }
      setModalStatus('Enregistrement...', false);

      try {
        // Les requêtes sont séquentielles : le serveur tranche pour
        // chacune, et l'affichage n'est mis à jour qu'à la fin.
        const changes = toBlock.map(function (k) { return { key: k, blocked: true }; })
          .concat(toUnblock.map(function (k) { return { key: k, blocked: false }; }));

        for (const change of changes) {
          const form = new FormData();
          form.append('user_id', String(modalUserId));
          form.append('page_key', change.key);
          form.append('blocked', change.blocked ? '1' : '0');
          form.append('shop_id', String(window.PAGES_ACCESS_SHOP_ID || 0));

          const res = await fetch(window.location.origin + PAGE_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            body: form,
          });
          const data = await res.json();
          if (!data.success) {
            throw new Error(data.message || 'Enregistrement impossible');
          }
        }

        // Mise à jour de l'état local (badges de la page).
        const next = new Set(modalBlocked);
        BLOCKED_PAGES[String(modalUserId)] = Array.from(next);
        renderBlockedBadges(modalUserId, next);

        setModalStatus('Pages enregistrées.', false);
        closePagesModal();
      } catch (e) {
        setModalStatus('✗ ' + (e && e.message ? e.message : 'Erreur réseau'), true);
      } finally {
        if (modalSave) {
          modalSave.disabled = false;
          modalSave.textContent = 'Enregistrer';
        }
      }
    }

    // Ré-affiche les badges de l'utilisateur (tableau + cards mobiles).
    function renderBlockedBadges(userId, blockedSet) {
      const labels = [];
      blockedSet.forEach(function (key) {
        const def = PAGE_CATALOG[key];
        if (def) labels.push(def.label || key);
      });

      // Les conteneurs portent l'attribut data-user-id.
      document.querySelectorAll('.hotel-roles-pages[data-user-id]').forEach(function (box) {
        if (box.getAttribute('data-user-id') !== String(userId)) return;

        // On retire les badges de pages, on garde le bouton « Gérer ».
        box.querySelectorAll('.hotel-roles-page, .hotel-roles-pages-empty').forEach(function (el) {
          el.remove();
        });

        const btn = box.querySelector('.hotel-roles-pages-manage');
        if (labels.length) {
          labels.forEach(function (label) {
            const span = document.createElement('span');
            span.className = 'hotel-roles-page is-blocked';
            span.setAttribute('data-user-id', String(userId));
            span.title = 'Accès bloqué pour cet utilisateur';
            span.textContent = label;
            box.insertBefore(span, btn);
          });
        } else {
          const empty = document.createElement('span');
          empty.className = 'hotel-roles-pages-empty';
          empty.textContent = 'Aucune page bloquée';
          box.insertBefore(empty, btn);
        }
      });
    }

    // Ouverture de la modale (boutons « Gérer »).
    document.querySelectorAll('.hotel-roles-pages-manage').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openPagesModal(this.dataset.userId, this.dataset.userName);
      });
    });

    if (modalCancel) {
      modalCancel.addEventListener('click', closePagesModal);
    }
    if (modalSave) {
      modalSave.addEventListener('click', savePagesModal);
    }
    // Clic sur l'arrière-plan : fermeture sans enregistrement.
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closePagesModal();
    });
    // Échap : fermeture sans enregistrement.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closePagesModal();
    });

    // API publique (utilisée par l'écran dédié, hors /hotel/roles).
    window.PagesAccessModal = {
      open: openPagesModal,
      close: closePagesModal,
      getBlocked: function (userId) {
        return (BLOCKED_PAGES[String(userId)] || []).slice();
      },
    };
  })();
</script>
