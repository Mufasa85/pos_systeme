/* ============================================================
   pressing.js — module Pressing du POS
   Portage de l'appli autonome `pressing/assets/app.js` vers
   l'API REST du POS (/api/pressing/*).

   Les sélecteurs sont préfixés « pr- » pour ne jamais entrer en
   collision avec les classes du POS.
   ============================================================ */
(function () {
  'use strict';

  const API = '/api/pressing';

  const $ = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  }[m]));

  const fc = (n) => new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(n) || 0) + ' FC';

  const dtfmt = (s) => (s
    ? new Date(String(s).replace(' ', 'T')).toLocaleString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
    : '—');

  /* Palette des couleurs (identique à l'appli autonome) */
  const COULEURS = {
    'Noir': '#111827', 'Blanc': '#f8fafc', 'Bleu': '#3b82f6', 'Rouge': '#ef4444',
    'Vert': '#22c55e', 'Jaune': '#eab308', 'Marron': '#92400e', 'Gris': '#9ca3af',
    'Rose': '#ec4899', 'Violet': '#8b5cf6', 'Orange': '#f97316', 'Beige': '#e7d3b0',
    'Bordeaux': '#881337', 'Kaki': '#6b7f3f', 'Bleu marine': '#1e3a8a', 'Turquoise': '#14b8a6',
  };
  const coulDot = (c) => COULEURS[c] || (String(c).startsWith('#') ? c : '#cbd5e1');

  const TYPES = [
    'Chemise', 'Pantalon', 'Veste', 'Costume', 'Robe', 'Jupe', 'Manteau', 'T-shirt',
    'Drap', 'Couverture', 'Rideau', "Taie d'oreiller",
  ];

  const ETAPES = [
    'Déposé / En attente',
    'Lavage commencé',
    'Lavage terminé',
    'Repassage commencé',
    'Repassage terminé',
    'Prêt pour récupération',
    'Remis au client',
  ];

  const DELAIS = { normal: 48, express: 24 };

  const FILTRES = [
    { code: 'encours', label: 'En cours' },
    { code: 'prete', label: 'Prêtes' },
    { code: 'retard', label: 'En retard' },
    { code: 'remise', label: 'Remises' },
    { code: 'all', label: 'Toutes' },
  ];

  /* ---------- État ---------- */
  let filtreCourant = 'encours';
  let rechercheCourante = '';
  let dossierCourant = 0;

  /* ---------- Utilitaires ---------- */
  async function api(url, body) {
    const opts = body
      ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
      : {};
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({ success: false, message: 'Réponse invalide' }));
    if (!res.ok && data.success !== false) {
      data.success = false;
      data.message = data.message || ('Erreur HTTP ' + res.status);
    }
    return data;
  }

  function toast(msg, type) {
    const t = document.createElement('div');
    t.className = 'pr-toast ' + (type || 'warn');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => {
      t.style.transition = 'opacity .4s, transform .4s';
      t.style.opacity = '0';
      t.style.transform = 'translate(-50%, -12px)';
      setTimeout(() => t.remove(), 400);
    }, 4000);
  }

  function alerte(msg, type) {
    const box = $('#pr-alert');
    box.className = 'pr-alert show ' + (type || 'error');
    box.textContent = msg;
    if (type === 'success') {
      setTimeout(() => { box.className = 'pr-alert'; }, 4000);
    }
  }

  function ouvrirDrawer(id) {
    $('#' + id).classList.add('open');
    $('#' + id.replace('drawer', 'backdrop')).classList.add('open');
  }

  function fermerDrawer(id) {
    $('#' + id).classList.remove('open');
    $('#' + id.replace('drawer', 'backdrop')).classList.remove('open');
  }

  /* ---------- Chargement de la liste ---------- */
  async function chargerStats() {
    const d = await api(API + '/stats');
    if (!d.success) return;
    const s = d.stats || {};
    $$('#pr-stats [data-k]').forEach((el) => {
      const k = el.dataset.k;
      if (k === 'encaisse') {
        el.textContent = fc(s.encaisse);
      } else {
        el.textContent = s[k] ?? 0;
      }
    });
  }

  function rendreChips(compteurs) {
    const box = $('#pr-chips');
    box.innerHTML = FILTRES.map((f) => {
      const n = (compteurs && compteurs[f.code]) ?? 0;
      return '<button type="button" class="pr-chip' + (filtreCourant === f.code ? ' on' : '')
        + '" data-f="' + f.code + '">' + esc(f.label)
        + ' <span class="cnt">' + n + '</span></button>';
    }).join('');

    $$('.pr-chip', box).forEach((b) => {
      b.onclick = () => {
        filtreCourant = b.dataset.f;
        chargerListe();
      };
    });
  }

  async function chargerListe() {
    const params = new URLSearchParams({ f: filtreCourant });
    if (rechercheCourante) params.set('q', rechercheCourante);

    const d = await api(API + '/commandes?' + params.toString());
    if (!d.success) {
      $('#pr-tbody').innerHTML = '<tr><td colspan="10" class="pr-empty">'
        + esc(d.message || 'Erreur de chargement') + '</td></tr>';
      return;
    }

    rendreChips(d.compteurs);

    const rows = d.data || [];
    if (rows.length === 0) {
      $('#pr-tbody').innerHTML = '<tr><td colspan="10" class="pr-empty">'
        + 'Aucune commande trouvée.</td></tr>';
      return;
    }

    $('#pr-tbody').innerHTML = rows.map((c) => {
      const solde = Number(c.solde) || 0;
      const ref = c.facture && c.facture !== '' ? c.facture : c.numero;
      const late = c.etat_code === 'retard' ? ' <span class="pr-badge-late">EN RETARD</span>' : '';
      return '<tr class="pr-row-' + esc(c.etat_code) + '">'
        + '<td class="mono">' + esc(ref) + '</td>'
        + '<td>' + esc(c.nom_client || '—') + '</td>'
        + '<td class="mono">' + esc(c.telephone || '—') + '</td>'
        + '<td>' + esc(dtfmt(c.date_depot)) + '</td>'
        + '<td class="num">' + (c.nb_art || 0) + '</td>'
        + '<td class="num">' + esc(fc(c.total)) + '</td>'
        + '<td class="num">' + esc(fc(c.paye)) + '</td>'
        + '<td class="num ' + (solde > 0 ? 'pr-solde' : 'pr-zero') + '">' + esc(fc(solde)) + '</td>'
        + '<td><span class="pr-etat ' + esc(c.etat_code) + '">' + esc(c.etat) + '</span>' + late + '</td>'
        + '<td><button type="button" class="btn btn-secondary btn-small js-voir" data-id="'
          + c.id + '">Voir</button></td>'
        + '</tr>';
    }).join('');

    $$('.js-voir').forEach((b) => {
      b.onclick = () => ouvrirDossier(parseInt(b.dataset.id, 10));
    });
  }

  /* ---------- Détail d'un dossier ---------- */
  async function ouvrirDossier(id) {
    dossierCourant = id;
    ouvrirDrawer('pr-drawer');
    $('#pr-drawer-body').innerHTML = '<div class="pr-empty">Chargement…</div>';
    $('#pr-drawer-foot').innerHTML = '';

    const d = await api(API + '/commandes/' + id);
    if (!d.success) {
      $('#pr-drawer-body').innerHTML = '<div class="pr-empty">'
        + esc(d.message || 'Dossier introuvable') + '</div>';
      return;
    }

    const c = d.data;
    $('#pr-drawer-title').innerHTML = esc(c.facture && c.facture !== '' ? c.facture : c.numero)
      + ' <span class="pr-etat ' + esc(c.etat_code) + '" style="font-size:.65rem;vertical-align:middle;">'
      + esc(c.etat) + '</span>';

    /* -- Bloc client -- */
    let html = '<div class="pr-kv">'
      + '<div class="pr-kv-item"><span>Client</span><b>' + esc(c.nom_client || '—') + '</b></div>'
      + '<div class="pr-kv-item"><span>Téléphone</span><b>' + esc(c.telephone || '—') + '</b></div>'
      + '<div class="pr-kv-item"><span>Dépôt</span><b>' + esc(dtfmt(c.date_depot)) + '</b></div>'
      + '<div class="pr-kv-item"><span>Retrait prévu</span><b>' + esc(dtfmt(c.retrait_prevu)) + '</b></div>'
      + '</div>';

    if (c.vente_id) {
      html += '<div class="pr-serie" style="margin-bottom:.8rem;">Rattaché à la vente POS '
        + '<a href="/facture/' + c.vente_id + '" target="_blank" style="color:var(--primary);">'
        + esc(c.vente_facture || ('#' + c.vente_id)) + '</a></div>';
    }

    /* -- Vêtements -- */
    html += '<div class="pr-section"><span>Vêtements (' + (c.articles || []).length + ')</span>'
      + '<button type="button" class="btn btn-secondary btn-small" id="pr-btn-avancer-tout">'
      + '▶ Avancer tout</button></div>';

    if (!c.articles || c.articles.length === 0) {
      html += '<div class="pr-empty">Aucun vêtement.</div>';
    } else {
      html += '<table class="pr-table"><thead><tr><th>Article</th><th class="num">Prix</th>'
        + '<th>Délai</th><th>Progression</th></tr></thead><tbody>';

      c.articles.forEach((a) => {
        const steps = ETAPES.map((lbl, i) => '<div class="step '
          + (i < a.etape ? 'done' : i === a.etape ? 'now' : '') + '" title="' + esc(lbl) + '"></div>').join('');

        const late = a.retard ? ' <span class="pr-badge-late">EN RETARD</span>' : '';
        const coef = Number(a.coefficient) !== 1 ? '<br><small>coef ×' + esc(a.coefficient) + '</small>' : '';

        // La couleur est soit un nom de la palette, soit un hexadécimal :
        // dans ce cas on affiche la pastille seule (le code hex n'est pas
        // lisible pour l'utilisateur).
        const coulNom = String(a.couleur || '').startsWith('#') ? '' : esc(a.couleur);
        const coulLabel = coulNom ? '<span>' + coulNom + '</span>' : '';

        let serie = '';
        (a.suivi || []).forEach((s) => {
          const agent = s.valide_par || s.agent || '';
          serie += '<div class="pr-serie">✓ ' + esc(ETAPES[s.etape] ?? s.etape)
            + ' — ' + esc(dtfmt(s.date_heure)) + (agent ? ' · ' + esc(agent) : '') + '</div>';
        });

        const btnPrev = a.etape > 0
          ? '<button type="button" class="btn btn-secondary btn-small js-reculer" data-id="' + a.id
            + '" style="font-size:.68rem;padding:.2rem .45rem;">◀</button>' : '';
        const btnNext = a.etape < 6
          ? '<button type="button" class="btn btn-secondary btn-small js-avancer" data-id="' + a.id
            + '" style="font-size:.68rem;padding:.2rem .45rem;">▶</button>' : '';

        html += '<tr>'
          + '<td><b>' + esc(a.type_vetement) + '</b> ×' + (a.quantite || 1)
          + '<br><span class="pr-dot" style="background:' + coulDot(a.couleur) + '"></span>'
          + coulLabel
          + (a.observation ? '<br><small>' + esc(a.observation) + '</small>' : '')
          + '</td>'
          + '<td class="num">' + esc(fc(a.prix_unitaire)) + coef + '</td>'
          + '<td>' + esc(a.delai_type === 'perso' ? 'Perso.' : a.delai_type === 'express' ? 'Express' : 'Normal')
          + ' — ' + esc(a.delai_heures) + ' h<br><small>Retrait : ' + esc(dtfmt(a.date_dispo)) + '</small>'
          + late
          + (a.remise_le ? '<div class="pr-serie" style="color:#16a34a;">Retiré le ' + esc(dtfmt(a.remise_le)) + '</div>' : '')
          + '</td>'
          + '<td><div class="pr-prog">' + steps + '</div>'
          + '<div style="font-size:.72rem;margin-top:.25rem;"><b>' + esc(a.etape_label) + '</b></div>'
          + serie
          + '<div style="margin-top:.35rem;display:flex;gap:.3rem;">' + btnPrev + btnNext + '</div>'
          + '</td>'
          + '</tr>';
      });

      html += '</tbody></table>';
    }

    /* -- Paiements -- */
    const etatPaye = c.paye <= 0
      ? '<span class="pr-etat retard">Non payé</span>'
      : (c.solde > 0
        ? '<span class="pr-etat retard">Partiellement payé</span>'
        : '<span class="pr-etat prete">Payé intégralement</span>');

    html += '<div class="pr-section"><span>Paiements — ' + etatPaye + '</span></div>';

    if (!c.paiements || c.paiements.length === 0) {
      html += '<div class="pr-empty" style="padding:1rem;">Aucun paiement.</div>';
    } else {
      html += '<table class="pr-table"><thead><tr><th>Date</th><th>Mode</th><th class="num">Montant</th>'
        + '</tr></thead><tbody>';
      c.paiements.forEach((p) => {
        html += '<tr><td>' + esc(dtfmt(p.date_paiement)) + '</td><td>' + esc(p.mode)
          + '</td><td class="num">' + esc(fc(p.montant)) + '</td></tr>';
      });
      html += '</tbody></table>';
    }

    html += '<div class="pr-totaux"><span><b>Total ' + esc(fc(c.total)) + '</b></span>'
      + '<span class="' + (c.solde > 0 ? 'pr-solde' : 'pr-zero') + '">Solde ' + esc(fc(c.solde)) + '</span></div>';

    /* -- Formulaire de paiement -- */
    if (c.solde > 0) {
      html += '<div class="pr-pay-row">'
        + '<input type="number" name="montant" min="0.01" step="0.01" required placeholder="Montant">'
        + '<select name="mode">'
        + '<option value="ESPECES">Espèces</option>'
        + '<option value="MOBILEMONEY">Mobile Money</option>'
        + '<option value="VIREMENT">Virement</option>'
        + '</select>'
        + '<input type="text" name="valide_par" placeholder="Agent (optionnel)">'
        + '<button type="button" class="btn btn-primary" id="pr-btn-payer">Encaisser</button>'
        + '</div>';
    } else {
      html += '<div class="pr-pay-row"><input type="text" id="pr-agent-nom" placeholder="Agent (optionnel)"></div>';
    }

    $('#pr-drawer-body').innerHTML = html;

    /* -- Pied du drawer -- */
    let foot = '';
    if (c.etat_code === 'remise') {
      foot += '<button type="button" class="btn btn-danger" id="pr-btn-supprimer">Supprimer le dossier</button>';
    }
    foot += '<button type="button" class="btn btn-secondary" id="pr-btn-fermer">Fermer</button>';
    $('#pr-drawer-foot').innerHTML = foot;

    /* -- Actions -- */
    const bp = $('#pr-btn-payer');
    if (bp) {
      bp.onclick = async () => {
        const row = bp.closest('.pr-pay-row');
        const montant = parseFloat(row.querySelector('[name=montant]').value) || 0;
        const mode = row.querySelector('[name=mode]').value;
        const validePar = row.querySelector('[name=valide_par]').value;

        if (montant <= 0) {
          toast('Montant invalide.', 'err');
          return;
        }

        bp.disabled = true;
        const d2 = await api(API + '/commandes/' + dossierCourant + '/pay', {
          montant, mode, valide_par: validePar,
        });
        bp.disabled = false;

        if (!d2.success) {
          toast(d2.message || 'Encaissement refusé.', 'err');
          return;
        }
        toast('Paiement enregistré.', 'ok');
        ouvrirDossier(dossierCourant);
        chargerStats();
      };
    }

    $$('.js-avancer').forEach((b) => {
      b.onclick = async () => {
        b.disabled = true;
        const d2 = await api(API + '/articles/' + b.dataset.id + '/advance', {});
        if (!d2.success) {
          toast(d2.message || 'Action refusée.', 'err');
          b.disabled = false;
          return;
        }
        ouvrirDossier(dossierCourant);
        chargerStats();
      };
    });

    $$('.js-reculer').forEach((b) => {
      b.onclick = async () => {
        b.disabled = true;
        const d2 = await api(API + '/articles/' + b.dataset.id + '/recede', {});
        if (!d2.success) {
          toast(d2.message || 'Action refusée.', 'err');
          b.disabled = false;
          return;
        }
        ouvrirDossier(dossierCourant);
        chargerStats();
      };
    });

    const bat = $('#pr-btn-avancer-tout');
    if (bat) {
      bat.onclick = async () => {
        bat.disabled = true;
        const agent = $('#pr-agent-nom') ? $('#pr-agent-nom').value : '';
        const d2 = await api(API + '/commandes/' + dossierCourant + '/advance-all', { valide_par: agent });
        bat.disabled = false;

        if (d2.bloques > 0) {
          toast(d2.message || 'Remise bloquée (solde non payé).', 'warn');
        } else if (d2.success) {
          toast('Avancement effectué.', 'ok');
        } else {
          toast(d2.message || 'Action refusée.', 'err');
          return;
        }
        ouvrirDossier(dossierCourant);
        chargerStats();
      };
    }

    const bsup = $('#pr-btn-supprimer');
    if (bsup) {
      bsup.onclick = async () => {
        if (!confirm('Supprimer définitivement le dossier ' + (c.numero || '') + ' ?')) return;
        const d2 = await api(API + '/commandes/' + dossierCourant + '/delete', {});
        if (!d2.success) {
          toast(d2.message || 'Suppression refusée.', 'err');
          return;
        }
        toast('Dossier supprimé.', 'ok');
        fermerDrawer('pr-drawer');
        chargerListe();
        chargerStats();
      };
    }

    const bf = $('#pr-btn-fermer');
    if (bf) bf.onclick = () => fermerDrawer('pr-drawer');
  }

  /* ---------- Nouvelle commande ---------- */
  function ajouterLigneArticle(preset) {
    const p = preset || {};
    const row = document.createElement('div');
    row.className = 'pr-art-row';

    const optionsType = TYPES.map((t) => '<option value="' + esc(t) + '">' + esc(t) + '</option>').join('');
    const optionsCouleur = Object.keys(COULEURS).map((c) =>
      '<option value="' + esc(c) + '">' + esc(c) + '</option>').join('');

    row.innerHTML = '<label>Type'
      + '<select class="pr-f-type">' + optionsType + '</select></label>'
      + '<label>Couleur'
      + '<select class="pr-f-couleur">' + optionsCouleur + '</select></label>'
      + '<label>Qté'
      + '<input type="number" class="pr-f-qte" value="1" min="1"></label>'
      + '<label>Prix unit.'
      + '<input type="number" class="pr-f-prix" value="0" min="0" step="0.01"></label>'
      + '<label>Coef.'
      + '<input type="number" class="pr-f-coef" value="1" min="0" step="0.01" title="Multiplicateur de prix">'
      + '</label>'
      + '<label class="pr-f-total" title="Prix après coefficient">0</label>'
      + '<label>Délai'
      + '<select class="pr-f-delai">'
      + '<option value="normal">Normal (48 h)</option>'
      + '<option value="express">Express (24 h)</option>'
      + '<option value="perso">Perso…</option>'
      + '</select></label>'
      + '<label>&nbsp;<button type="button" class="pr-art-del" title="Retirer">×</button></label>';

    $('#pr-articles').appendChild(row);

    const type = $('.pr-f-type', row);
    if (p.name) {
      const found = Array.from(type.options).find((o) => o.value.toLowerCase() === String(p.name).toLowerCase());
      if (found) {
        type.value = found.value;
      } else {
        type.add(new Option(p.name, p.name, true, true));
      }
    }
    $('.pr-f-qte', row).value = p.quantity || 1;
    $('.pr-f-prix', row).value = p.price || 0;

    // Coefficient : n'affiche PAS le prix majoré dans le champ (on garde
    // le prix de BASE) mais affiche le total après coefficient, comme la
    // source autonome. La majoration définitive est recalculée côté
    // serveur — le total affiché ici n'est qu'un aperçu.
    const prix = $('.pr-f-prix', row);
    const coef = $('.pr-f-coef', row);
    const affTotal = $('.pr-f-total', row);
    const majore = () => {
      const c = parseFloat(coef.value) || 0;
      const b = parseFloat(prix.value) || 0;
      const t = c > 0 ? b * c : b;
      affTotal.textContent = fc(t);
      affTotal.title = c > 0 && c !== 1
        ? `${fc(b)} × ${c} = ${fc(t)}`
        : fc(t);
    };
    coef.oninput = majore;
    prix.oninput = majore;
    majore();

    const delai = $('.pr-f-delai', row);
    delai.onchange = () => {
      if (delai.value === 'perso') {
        const h = parseInt(prompt('Délai en heures', '48'), 10);
        if (!isNaN(h) && h > 0) {
          $('.pr-f-coef', row).dataset.delai = h;
        }
      }
    };

    $('.pr-art-del', row).onclick = () => row.remove();
  }

  function ouvrirNouvelleCommande() {
    $('#pr-f-depot').value = new Date().toISOString().slice(0, 16);
    $('#pr-articles').innerHTML = '';
    ajouterLigneArticle();
    ouvrirDrawer('pr-drawer2');
  }

  /* ---------- Lookup facture ---------- */
  async function lookupFacture() {
    const facture = $('#pr-f-facture').value.trim();
    if (!facture) {
      toast('Saisissez le numéro de facture.', 'warn');
      return;
    }

    const btn = $('#pr-btn-lookup');
    btn.disabled = true;
    btn.textContent = 'Recherche…';

    const d = await api(API + '/facture/lookup', { facture });

    btn.disabled = false;
    btn.textContent = 'Récupérer la facture';

    if (!d.success) {
      toast(d.message || 'Facture introuvable.', 'err');
      return;
    }

    if (d.client_name) $('#pr-f-nom').value = d.client_name;
    if (d.client_number) $('#pr-f-tel').value = d.client_number;

    $('#pr-articles').innerHTML = '';
    const arts = d.articles || [];
    if (arts.length === 0) {
      ajouterLigneArticle();
    } else {
      arts.forEach((a) => ajouterLigneArticle(a));
    }
    toast('Facture récupérée.', 'ok');
  }

  async function enregistrerCommande() {
    const btn = $('#pr-btn-enregistrer');
    const nom = $('#pr-f-nom').value.trim();
    const tel = $('#pr-f-tel').value.trim();

    if (!nom && !tel) {
      toast('Nom du client ou téléphone requis.', 'warn');
      return;
    }

    const articles = $$('.pr-art-row').map((row) => ({
      type_vetement: $('.pr-f-type', row).value,
      couleur: $('.pr-f-couleur', row).value,
      quantite: parseInt($('.pr-f-qte', row).value, 10) || 1,
      prix_unitaire: parseFloat($('.pr-f-prix', row).value) || 0,
      coefficient: parseFloat($('.pr-f-coef', row).value) || 1,
      observation: '',
      delai_type: $('.pr-f-delai', row).value,
      delai_heures: DELAIS[$('.pr-f-delai', row).value] || 48,
    })).filter((a) => a.type_vetement !== '');

    if (articles.length === 0) {
      toast('Ajoutez au moins un vêtement.', 'warn');
      return;
    }

    btn.disabled = true;
    const d = await api(API + '/commandes', {
      facture: $('#pr-f-facture').value.trim(),
      client_name: nom,
      telephone: tel,
      date_depot: $('#pr-f-depot').value ? $('#pr-f-depot').value.replace('T', ' ') : '',
      articles,
    });
    btn.disabled = false;

    if (!d.success) {
      toast(d.message || 'Erreur de création.', 'err');
      return;
    }

    toast(d.message || 'Commande enregistrée.', 'ok');
    fermerDrawer('pr-drawer2');
    filtreCourant = 'encours';
    chargerListe();
    chargerStats();
  }

  /* ---------- Factures POS sans pressing ---------- */
  async function ouvrirVentesSansCommande() {
    ouvrirDrawer('pr-drawer3');
    $('#pr-drawer3-body').innerHTML = '<div class="pr-empty">Chargement…</div>';

    const d = await api(API + '/ventes-sans-commande');
    if (!d.success) {
      $('#pr-drawer3-body').innerHTML = '<div class="pr-empty">'
        + esc(d.message || 'Erreur') + '</div>';
      return;
    }

    const rows = d.data || [];
    if (rows.length === 0) {
      $('#pr-drawer3-body').innerHTML = '<div class="pr-empty">'
        + 'Toutes les factures ont déjà un dossier de pressing.</div>';
      return;
    }

    let html = '<div class="pr-serie" style="margin-bottom:.7rem;">'
      + rows.length + ' facture(s) sans dossier de pressing. '
      + 'Cliquez sur « Démarrer » pour créer le dossier depuis cette facture.</div>'
      + '<table class="pr-table"><thead><tr><th>Facture</th><th>Client</th>'
      + '<th class="num">Montant</th><th>Articles</th><th></th></tr></thead><tbody>';

    rows.forEach((v) => {
      const arts = v.articles || [];
      const nbArt = arts.length
        ? arts.length + ' art. — ' + arts.map((a) => `${esc(a.name)} ×${a.quantity}`).join(', ')
        : 'aucun article';
      html += '<tr>'
        + '<td class="mono">' + esc(v.numero_facture) + '</td>'
        + '<td>' + esc(v.nom_client || '—') + '</td>'
        + '<td class="num">' + esc(fc(v.total)) + '</td>'
        + '<td class="pr-serie">' + nbArt + '</td>'
        + '<td><button type="button" class="btn btn-secondary btn-small js-demarrer" data-fact="'
          + esc(v.numero_facture) + '" data-nom="' + esc(v.nom_client || '')
          + '" data-tel="' + esc(v.telephone || '') + '" data-articles="'
          + esc(JSON.stringify(arts)) + '">Démarrer</button></td>'
        + '</tr>';
    });

    html += '</tbody></table>';
    $('#pr-drawer3-body').innerHTML = html;

    $$('.js-demarrer').forEach((b) => {
      b.onclick = () => {
        fermerDrawer('pr-drawer3');
        $('#pr-f-facture').value = b.dataset.fact;
        $('#pr-f-nom').value = b.dataset.nom;
        $('#pr-f-tel').value = b.dataset.tel;

        // Pré-remplit les articles de la vente : sans ça, le dossier
        // serait créé avec une ligne vide (prix 0) et son total à 0.
        let arts = [];
        try {
          arts = JSON.parse(b.dataset.articles || '[]');
        } catch (e) {
          arts = [];
        }

        ouvrirNouvelleCommande();

        if (Array.isArray(arts) && arts.length) {
          $('#pr-articles').innerHTML = '';
          arts.forEach((a) => ajouterLigneArticle(a));
        }
      };
    });
  }

  /* ---------- Branchement ---------- */
  function init() {
    if (!$('#pr-tbody')) return; // page non pertinente

    $('#pr-btn-nouvelle').onclick = ouvrirNouvelleCommande;
    $('#pr-btn-annuler').onclick = () => fermerDrawer('pr-drawer2');
    $('#pr-drawer2-close').onclick = () => fermerDrawer('pr-drawer2');
    $('#pr-backdrop2').onclick = () => fermerDrawer('pr-drawer2');

    $('#pr-drawer-close').onclick = () => fermerDrawer('pr-drawer');
    $('#pr-backdrop').onclick = () => fermerDrawer('pr-drawer');

    $('#pr-btn-ventes').onclick = ouvrirVentesSansCommande;
    $('#pr-btn-ventes-fermer').onclick = () => fermerDrawer('pr-drawer3');
    $('#pr-drawer3-close').onclick = () => fermerDrawer('pr-drawer3');
    $('#pr-backdrop3').onclick = () => fermerDrawer('pr-drawer3');

    $('#pr-btn-lookup').onclick = lookupFacture;
    $('#pr-btn-ajouter').onclick = () => ajouterLigneArticle();
    $('#pr-btn-enregistrer').onclick = enregistrerCommande;

    $('#pr-btn-search').onclick = () => {
      rechercheCourante = $('#pr-search').value.trim();
      chargerListe();
    };
    $('#pr-search').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        rechercheCourante = $('#pr-search').value.trim();
        chargerListe();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        fermerDrawer('pr-drawer');
        fermerDrawer('pr-drawer2');
        fermerDrawer('pr-drawer3');
      }
    });

    chargerStats();
    chargerListe();

    // Rafraîchissement automatique (comme les autres pages du POS)
    setInterval(() => {
      chargerStats();
      if (!$('#pr-drawer').classList.contains('open')) {
        chargerListe();
      }
    }, 30000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
