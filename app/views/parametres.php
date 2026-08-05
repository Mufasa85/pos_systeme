      <!-- Settings Page -->
      <div id="page-settings" class="page <?= $page == 'parametres' ? 'active' : '' ?>">
        <div class="page-header" style="margin: 20px;">
          <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            Paramètres
          </h2>
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'super_admin' && !empty($shops)): ?>
        <div style="margin-bottom:1rem;padding:0 1.5rem">
          <label style="font-size:.85rem;font-weight:600;margin-right:.5rem">Boutique :</label>
          <select id="settings-shop-filter" onchange="window.location.href='/parametres?shop_id='+this.value" style="padding:.4rem .8rem;border-radius:var(--radius,8px);border:1px solid var(--border,#e2e8f0)">
            <option value="">Paramètres globaux</option>
            <?php foreach ($shops as $sh): ?>
              <option value="<?= $sh['id'] ?>" <?= (isset($_GET['shop_id']) && $_GET['shop_id'] == $sh['id']) ? 'selected' : '' ?>><?= htmlspecialchars($sh['nom']) ?> (<?= htmlspecialchars($sh['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="settings-grid">
          <!-- Informations Entreprise (super_admin only) -->
          <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
          <div class="card" style="padding: 1.5rem; grid-column: 1 / -1;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0;">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <path d="M3 21h18"/>
                  <path d="M5 21V7l8-4 8 4v14"/>
                  <path d="M17 21v-8.5a1.5 1.5 0 0 0-3 0V21"/>
                </svg>
                Informations Entreprise
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Configurez les informations de l'entreprise affichées sur toutes les factures</p>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem;">
              <div class="form-row">
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">Nom de l'entreprise</label>
                  <input type="text" id="company-name" placeholder="Ex: Ma Société SARL">
                </div>
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">Adresse</label>
                  <input type="text" id="company-address" placeholder="Ex: 123 Rue Principale, Kinshasa">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">Email</label>
                  <input type="email" id="company-email" placeholder="contact@entreprise.com">
                </div>
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">Point de vente (PDV)</label>
                  <input type="text" id="company-pdv" placeholder="Ex: PDV001">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">Téléphone</label>
                  <input type="text" id="company-phone" placeholder="Ex: +243 123 456 789">
                </div>
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">NUMERO D'IDENTIFACTION NATIONALE</label>
                  <input type="text" id="company-ice" placeholder="Numéro d'identification nationale">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">RCCM</label>
                  <input type="text" id="company-rccm" placeholder="Numéro RCCM">
                </div>
                <div class="form-group">
                  <label style="font-size:.85rem;font-weight:600">NUMERO IMPOT</label>
                  <input type="text" id="company-isf" placeholder="Numéro impôt">
                </div>
              </div>
              <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#94a3b8);margin-bottom:.75rem">Paramètres de connexion au serveur DGI</div>
                <div class="form-row">
                  <div class="form-group">
                    <label style="font-size:.85rem;font-weight:600">NID</label>
                    <input type="text" id="company-nid" placeholder="Identifiant DGI">
                  </div>
                  <div class="form-group">
                    <label style="font-size:.85rem;font-weight:600">TOKEN</label>
                    <input type="text" id="company-token" placeholder="Token DGI">
                  </div>
                  <div class="form-group">
                    <label style="font-size:.85rem;font-weight:600">PORT</label>
                    <input type="text" id="company-port" placeholder="Port">
                  </div>
                </div>
              </div>
              <div style="margin-top:1.5rem">
                <button class="btn btn-primary" onclick="saveCompanyInfo()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Enregistrer
                </button>
                <span id="company-info-status" style="font-size: 0.85rem; color: var(--muted); display: inline-flex; align-items: center; margin-left: 0.5rem;"></span>
              </div>
            </div>
          </div>
          <?php endif; ?>

<?php if (($_SESSION['role'] ?? '') !== 'super_admin'): ?>

          <!-- Informations Magasin -->
          <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0;">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                  <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Informations magasin
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Configurez les informations affichées sur vos factures</p>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem;">
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                      <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Nom du magasin
                  </label>
                  <input type="text" id="store-name" name="store_name" value="" placeholder="Ex: SuperMarché Express" readonly>
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    Adresse
                  </label>
                  <input type="text" id="store-address" name="store_address" value="" placeholder="Ex: 123 Rue Mohammed V, Casablanca">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    Email
                  </label>
                  <input type="text" id="store-email" name="store_email" value="" placeholder="Ex: contact@magasin.com">
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                      <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Point de vente (PDV)
                  </label>
                  <input type="text" id="store-pdv" name="pdv" value="" placeholder="Ex: PDV001">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    Téléphone
                  </label>
                  <input type="text" id="store-phone" name="store_phone" value="" placeholder="Ex: +212 522 123 456">
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    ID Nat
                  </label>
                  <input type="text" id="store-ice" name="store_ice" value="" placeholder="Ex: 001234567890123" style="font-family: 'JetBrains Mono', monospace;">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="16" y1="13" x2="8" y2="13"></line>
                      <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    RCCM
                  </label>
                  <input type="text" id="store-rccm" name="store_rccm" value="" placeholder="Ex: RC123456" readonly>
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="12" y1="18" x2="12" y2="12"></line>
                      <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    ISF
                  </label>
                  <input type="text" id="store-isf" name="store_isf" value="" placeholder="Ex: ISF123456">
                </div>
              </div>
            </div>
          </div>

          <!-- Informations POS -->
          <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0;">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
                  <line x1="6" y1="8" x2="6" y2="8"></line>
                  <line x1="6" y1="12" x2="18" y2="12"></line>
                  <line x1="6" y1="16" x2="18" y2="16"></line>
                </svg>
                Informations POS
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Paramètres de connexion au serveur DGI</p>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem;">
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
                      <path d="M9 9h6v6H9z"></path>
                    </svg>
                    NID
                  </label>
                  <input type="text" id="pos-nid" name="pos_nid" value="" placeholder="Ex: NID123456">
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    TOKEN
                  </label>
                  <input type="text" id="pos-token" name="pos_token" value="" placeholder="Ex: abcdef123456" style="font-family: 'JetBrains Mono', monospace;">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M5 12h14"></path>
                      <path d="M12 5v14"></path>
                    </svg>
                    PORT
                  </label>
                  <input type="text" id="pos-port" name="pos_port" value="" placeholder="Ex: 443">
                </div>
              </div>
              <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <button type="button" id="btn-save-store-pos" class="btn btn-primary"class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                  </svg>
                  Enregistrer
                </button>
                <span id="store-pos-status" style="font-size: 0.85rem; color: var(--muted); display: inline-flex; align-items: center;"></span>
              </div>
            </div>
          </div>

          <?php endif; ?>

          <!-- Format d'impression (papier) -->
          <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0;">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <polyline points="6 9 6 2 18 2 18 9"></polyline>
                  <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                  <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Format d'impression
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Choisissez le format de papier utilisé pour imprimer les tickets et factures</p>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem;">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                  </svg>
                  Format du papier
                </label>
                <select id="paper-type" name="paper_type" style="width: 100%; padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text); font-size: 0.95rem;">
                  <optgroup label="Imprimantes POS (reçus thermiques)">
                    <option value="80mm">80 mm (Ticket POS standard)</option>
                    <option value="57mm">57 mm (Mini ticket POS)</option>
                  </optgroup>
                  <optgroup label="Formats papier standard">
                    <option value="A4">A4 (210 × 297 mm)</option>
                    <!-- <option value="A5">A5 (148 × 210 mm)</option> -->
                    <!-- <option value="Letter">Letter (8.5 × 11 in)</option>
                    <option value="Legal">Legal (8.5 × 14 in)</option>
                     -->
                  </optgroup>
                </select>
                <p style="font-size: 0.75rem; color: var(--muted); margin-top: 0.5rem;">
                  Le format sera appliqué lors de l'impression des tickets et factures.
                </p>
              </div>

              <div id="receipt-padding-section" style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border); display: none;">
                <label style="margin-bottom: 0.5rem; display: block;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M21 3H3v18h18V3z"></path>
                    <path d="M9 3v18M15 3v18M3 9h18M3 15h18"></path>
                  </svg>
                  Espacement des articles (<span id="receipt-padding-format-label">80mm</span>)
                </label>
                <p style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.75rem;">
                  Ajustez l'espacement autour des lignes d'articles pour vous adapter à votre imprimante.
                </p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                  <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.8rem;">Espacement horizontal (mm)</label>
                    <input type="number" id="receipt-padding-h" min="0" max="10" step="0.5" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text);">
                  </div>
                  <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.8rem;">Espacement vertical (mm)</label>
                    <input type="number" id="receipt-padding-v" min="0" max="10" step="0.5" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text);">
                  </div>
                </div>
                <p style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">
                  Ce réglage est enregistré séparément pour 57mm et 80mm et sera appliqué au prochain enregistrement.
                </p>
              </div>
            </div>
          </div>

          <!-- Gestion des types de service (super_admin only) -->
          <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
          <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <h3>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                  </svg>
                  Types de service
                </h3>
                <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Gérez les types de service disponibles pour les boutiques</p>
              </div>
              <button class="btn btn-primary btn-small" onclick="openAddServiceTypeModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Ajouter
              </button>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem;">
              <div id="service-types-list" style="display: grid; gap: 0.75rem;">
                <!-- Service types will be loaded here -->
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Abonnement -->
          <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="margin-bottom: 1.5rem; padding: 0;">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Abonnement
              </h3>
            </div>
            <div class="settings-form-container" style="background: var(--background); border-radius: var(--radius); padding: 1.25rem; text-align: center;">
              <div style="background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 1px solid #81c784; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 0.9rem; font-weight: 600; color: #2e7d32; margin-bottom: 12px;">
                  Comment procéder au réabonnement ?
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
                  <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #333;">
                    <span style="background: #0B5E88; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.75rem;">1</span>
                    <span>Cliquez sur le bouton <strong>"Recharger abonnement"</strong></span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #333;">
                    <span style="background: #0B5E88; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.75rem;">2</span>
                    <span>Accédez à la page de paiement</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #333;">
                    <span style="background: #0B5E88; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.75rem;">3</span>
                    <span>Saisissez vos informations de paiement</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #333;">
                    <span style="background: #0B5E88; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.75rem;">4</span>
                    <span>Validez votre paiement via <strong>USSD Mobile Money PIN</strong></span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #333;">
                    <span style="background: #4caf50; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</span>
                    <span>Votre compte sera <strong>automatiquement crédité</strong></span>
                  </div>
                </div>
              </div>
              <button type="button" id="btn-reload-subscription" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="reloadSubscription()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="23 4 23 10 17 10"></polyline>
                  <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Recharger abonnement Via Mobile Money
              </button>
            </div>
          </div>
        </div>

        <!-- Apparence / Thème - Full Width Row -->
        <div class="card" style="padding: 1.5rem; margin-top: 1.5rem;">
          <div class="card-header" style="margin-bottom: 1rem; padding: 0;">
            <h3>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                <circle cx="13.5" cy="6.5" r="2.5"></circle>
                <circle cx="6.5" cy="17.5" r="2.5"></circle>
                <circle cx="17.5" cy="17.5" r="2.5"></circle>
              </svg>
              Apparence / Thème
            </h3>
            <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Personnalisez les couleurs de l'interface</p>
          </div>
          <div class="theme-selector-inline" id="theme-selector">
            <button class="theme-btn" data-theme="blue" onclick="applyTheme('blue')" title="Bleu">
              <span class="theme-preview" style="background: #0B5E88;"></span>
            </button>
            <button class="theme-btn" data-theme="green" onclick="applyTheme('green')" title="Vert">
              <span class="theme-preview" style="background: #16A34A;"></span>
            </button>
            <button class="theme-btn" data-theme="purple" onclick="applyTheme('purple')" title="Violet">
              <span class="theme-preview" style="background: #7C3AED;"></span>
            </button>
            <button class="theme-btn" data-theme="yellow" onclick="applyTheme('yellow')" title="Jaune">
              <span class="theme-preview" style="background: #EAB308;"></span>
            </button>
            <button class="theme-btn" data-theme="orange" onclick="applyTheme('orange')" title="Orange">
              <span class="theme-preview" style="background: #F97316;"></span>
            </button>
            <button class="theme-btn" data-theme="gray" onclick="applyTheme('gray')" title="Gris">
              <span class="theme-preview" style="background: #64748B;"></span>
            </button>
            <button class="theme-btn" data-theme="red" onclick="applyTheme('red')" title="Rouge">
              <span class="theme-preview" style="background: #DC2626;"></span>
            </button>
            <button class="theme-btn" data-theme="black" onclick="applyTheme('black')" title="Noir">
              <span class="theme-preview" style="background: #111827;"></span>
            </button>
            <button class="theme-btn" data-theme="cyan" onclick="applyTheme('cyan')" title="🩵 Cyan">
              <span class="theme-preview" style="background: #06B6D4;"></span>
            </button>
            <button class="theme-btn" data-theme="indigo" onclick="applyTheme('indigo')" title="🟦 Indigo">
              <span class="theme-preview" style="background: #4F46E5;"></span>
            </button>
            <button class="theme-btn" data-theme="emerald" onclick="applyTheme('emerald')" title="🟩 Émeraude">
              <span class="theme-preview" style="background: #10B981;"></span>
            </button>
            <button class="theme-btn" data-theme="gold" onclick="applyTheme('gold')" title="🥇 Gold">
              <span class="theme-preview" style="background: #EAB308;"></span>
            </button>
            <button class="theme-btn" data-theme="midnight" onclick="applyTheme('midnight')" title="🌌 Midnight">
              <span class="theme-preview" style="background: #032357" ,;"></span>
            </button>
            <button class="theme-btn" data-theme="ice" onclick="applyTheme('ice')" title="🧊 Ice">
              <span class="theme-preview" style="background: #7DD3FC;"></span>
            </button>
          </div>
        </div>
      </div>

      <style>
        #page-settings .settings-grid {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 1.5rem;
        }

        #page-settings input[readonly] {
          background-color: var(--background);
          color: var(--text);
          cursor: default;
          opacity: 0.9;
        }

        #page-settings input[readonly]:focus {
          border-color: var(--border);
          outline: none;
        }

        #page-settings .form-row {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 1rem;
        }

        #page-settings .form-group {
          min-width: 0;
        }

        #page-settings .form-group>label {
          display: inline-flex;
          align-items: center;
          flex-wrap: wrap;
          gap: 4px;
          word-break: break-word;
        }

        #page-settings .form-group input,
        #page-settings .form-group select {
          width: 100%;
          box-sizing: border-box;
          min-width: 0;
        }

        /* Responsive settings page - Tablet and below */
        @media (max-width: 900px) {
          #page-settings .form-row {
            grid-template-columns: 1fr;
          }
        }

        /* Responsive settings page - Mobile only (600px and below) */
        @media (max-width: 600px) {
          #page-settings .settings-grid {
            grid-template-columns: 1fr;
          }

          #page-settings .card {
            padding: 1rem !important;
          }

          #page-settings .settings-form-container {
            padding: 0.875rem !important;
          }

          #page-settings .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
          }

          #page-settings .form-row .form-group {
            width: 100%;
          }

          #page-settings .form-group {
            margin-bottom: 0.875rem;
          }

          #page-settings .form-group:last-child {
            margin-bottom: 0;
          }

          /* Input fields fit inside the card like Nom du magasin */
          #page-settings .form-group input,
          #page-settings input[type="text"],
          #page-settings input[type="number"] {
            width: 100%;
            box-sizing: border-box;
          }
        }

        @media (max-width: 480px) {
          #page-settings .page-header {
            margin: 12px !important;
          }

          #page-settings .page-header h2 {
            font-size: 1.25rem;
          }

          #page-settings .card {
            padding: 0.75rem !important;
            margin-bottom: 1rem;
          }

          #page-settings .settings-form-container {
            padding: 0.75rem !important;
          }

          #page-settings .form-row {
            gap: 0.5rem;
          }

          #page-settings .form-group {
            margin-bottom: 0.75rem;
          }

          #page-settings input[type="text"],
          #page-settings input[type="number"] {
            font-size: 0.875rem;
            padding: 0.5rem 0.625rem;
          }

          #page-settings label {
            font-size: 0.8rem;
          }
        }
      </style>
      <script>
        // Charger les paramètres au démarrage
        async function loadSettings() {
          try {
            const res = await fetch(APP_URL + '/api/settings');
            const data = await res.json();

            // Remplir les champs (readonly mais avec valeur pour affichage)
            document.getElementById('store-name').value = data.store_name || '';
            document.getElementById('store-address').value = data.store_address || '';
            document.getElementById('store-email').value = data.store_email || '';
            document.getElementById('store-pdv').value = data.pdv || '';
            document.getElementById('store-phone').value = data.store_phone || '';
            document.getElementById('store-ice').value = data.store_ice || '';
            document.getElementById('store-rccm').value = data.store_rccm || '';
            document.getElementById('store-isf').value = data.store_isf || '';
            document.getElementById('pos-nid').value = data.nid || '';
            document.getElementById('pos-token').value = data.token || '';
            document.getElementById('pos-port').value = data.port || '';
            const serviceTypeSel = document.getElementById('pos-service-type');
            if (serviceTypeSel) serviceTypeSel.value = data.service_type || 'Caisse';
          } catch (e) {
            console.error('Erreur chargement settings:', e);
          }
        }

        // Charger le format d'impression actuel
        async function loadPaperType() {
          try {
            const res = await fetch(APP_URL + '/api/settings/paper-type');
            const data = await res.json();
            const sel = document.getElementById('paper-type');
            if (sel && data.paper_type) {
              sel.value = data.paper_type;
            }
          } catch (e) {
            console.error('Erreur chargement paper_type:', e);
          }
          toggleReceiptPaddingSection();
        }

        // Cache des paddings chargés depuis le serveur (par format)
        let receiptPaddingCache = {
          '57mm': { h: 0, v: 1 },
          '80mm': { h: 0, v: 1 }
        };

        // Afficher/masquer la section padding selon le format sélectionné,
        // et pré-remplir les champs avec la valeur du format courant.
        function toggleReceiptPaddingSection() {
          const sel = document.getElementById('paper-type');
          const section = document.getElementById('receipt-padding-section');
          const label = document.getElementById('receipt-padding-format-label');
          if (!sel || !section) return;

          const isTicket = sel.value === '57mm' || sel.value === '80mm';
          section.style.display = isTicket ? 'block' : 'none';
          if (!isTicket) return;

          if (label) label.textContent = sel.value;
          const pad = receiptPaddingCache[sel.value] || { h: 0, v: 1 };
          const hInput = document.getElementById('receipt-padding-h');
          const vInput = document.getElementById('receipt-padding-v');
          if (hInput) hInput.value = pad.h;
          if (vInput) vInput.value = pad.v;
        }

        // Charger les paddings configurés pour 57mm et 80mm
        async function loadReceiptPaddingSettings() {
          try {
            const res = await fetch(APP_URL + '/api/settings/receipt-padding');
            const data = await res.json();
            if (data && data['57mm'] && data['80mm']) {
              receiptPaddingCache = data;
            }
          } catch (e) {
            console.error('Erreur chargement receipt-padding:', e);
          }
          toggleReceiptPaddingSection();
        }

        // Sauvegarder le padding du format actuellement sélectionné
        async function saveReceiptPaddingIfNeeded() {
          const sel = document.getElementById('paper-type');
          if (!sel) return { success: true };
          if (sel.value !== '57mm' && sel.value !== '80mm') return { success: true };

          const hInput = document.getElementById('receipt-padding-h');
          const vInput = document.getElementById('receipt-padding-v');
          const paddingH = hInput ? parseFloat(hInput.value) || 0 : 0;
          const paddingV = vInput ? parseFloat(vInput.value) || 0 : 0;

          try {
            const res = await fetch(APP_URL + '/api/settings/receipt-padding', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ paper_type: sel.value, padding_h: paddingH, padding_v: paddingV })
            });
            return await res.json();
          } catch (e) {
            console.error('Erreur sauvegarde receipt-padding:', e);
            return { success: false, error: 'Erreur réseau' };
          }
        }

        // Sauvegarder le format d'impression
        async function savePaperType() {
          const sel = document.getElementById('paper-type');
          const status = document.getElementById('paper-type-status');
          if (!sel) return;

          const paperType = sel.value;
          status.textContent = 'Enregistrement...';
          status.style.color = 'var(--muted)';

          try {
            const res = await fetch(APP_URL + '/api/settings/paper-type', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                paper_type: paperType
              })
            });
            const data = await res.json();
            if (data.success) {
              status.textContent = '✓ Enregistré';
              status.style.color = '#16A34A';
              setTimeout(() => {
                status.textContent = '';
              }, 2500);
            } else {
              status.textContent = '✗ ' + (data.error || 'Erreur');
              status.style.color = '#DC2626';
            }
          } catch (e) {
            console.error('Erreur sauvegarde paper_type:', e);
            status.textContent = '✗ Erreur réseau';
            status.style.color = '#DC2626';
          }
        }

        // Sauvegarder les informations magasin et POS
        async function saveStoreAndPosSettings() {
          const btn = document.getElementById('btn-save-store-pos');
          const status = document.getElementById('store-pos-status');
          if (btn) btn.disabled = true;
          if (status) {
            status.textContent = 'Enregistrement...';
            status.style.color = 'var(--muted)';
          }

          const payload = {
            store_name: document.getElementById('store-name')?.value || '',
            store_address: document.getElementById('store-address')?.value || '',
            store_email: document.getElementById('store-email')?.value || '',
            pdv: document.getElementById('store-pdv')?.value || '',
            store_phone: document.getElementById('store-phone')?.value || '',
            store_ice: document.getElementById('store-ice')?.value || '',
            store_rccm: document.getElementById('store-rccm')?.value || '',
            store_isf: document.getElementById('store-isf')?.value || '',
            nid: document.getElementById('pos-nid')?.value || '',
            token: document.getElementById('pos-token')?.value || '',
            port: document.getElementById('pos-port')?.value || '',
            service_type: document.getElementById('pos-service-type')?.value || 'Caisse'
          };

          try {
            const res = await fetch(APP_URL + '/api/settings', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });
            const data = await res.json();

            const paperType = document.getElementById('paper-type')?.value || '80mm';
            const paperRes = await fetch(APP_URL + '/api/settings/paper-type', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ paper_type: paperType })
            });
            const paperData = await paperRes.json();

            const paddingData = await saveReceiptPaddingIfNeeded();
            if (paddingData && paddingData.success && paddingData.paper_type) {
              receiptPaddingCache[paddingData.paper_type] = { h: paddingData.padding_h, v: paddingData.padding_v };
            }

            if (data.success && paperData.success && paddingData.success) {
              if (status) {
                status.textContent = '✓ Enregistré';
                status.style.color = '#16A34A';
              }
              // Mettre à jour STORE_INFO en mémoire
              if (typeof STORE_INFO !== 'undefined') {
                STORE_INFO.name = payload.store_name || STORE_INFO.name;
                STORE_INFO.address = payload.store_address || STORE_INFO.address;
                STORE_INFO.email = payload.store_email || STORE_INFO.email;
                STORE_INFO.pdv = payload.pdv || STORE_INFO.pdv;
                STORE_INFO.phone = payload.store_phone || STORE_INFO.phone;
                STORE_INFO.ice = payload.store_ice || STORE_INFO.ice;
                STORE_INFO.rccm = payload.store_rccm || STORE_INFO.rccm;
                STORE_INFO.isf = payload.store_isf || STORE_INFO.isf;
                STORE_INFO.service_type = payload.service_type || STORE_INFO.service_type;
              }
            } else {
              if (status) {
                status.textContent = '✗ ' + (data.error || paperData.error || 'Erreur');
                status.style.color = '#DC2626';
              }
            }
          } catch (e) {
            console.error('Erreur sauvegarde settings:', e);
            if (status) {
              status.textContent = '✗ Erreur réseau';
              status.style.color = '#DC2626';
            }
          } finally {
            if (btn) btn.disabled = false;
            setTimeout(() => { if (status) status.textContent = ''; }, 2500);
          }
        }

        // Init boutons
        document.addEventListener('DOMContentLoaded', () => {
          const storePosBtn = document.getElementById('btn-save-store-pos');
          if (storePosBtn) storePosBtn.addEventListener('click', saveStoreAndPosSettings);

          const paperSel = document.getElementById('paper-type');
          if (paperSel) paperSel.addEventListener('change', toggleReceiptPaddingSection);
        });

        // Recharger l'abonnement - redirection vers Mobile Money
        function reloadSubscription() {
          // Redirection vers la page de paiement Mobile Money
          window.open('https://osat-energie.com/money.php', '_blank');
        }

        // Charger au démarrage
        loadSettings();
        loadReceiptPaddingSettings().then(loadPaperType);
      </script>

<!-- Modal Type de service -->
<div id="service-type-modal" class="modal">
  <div class="modal-content" style="max-width:400px">
    <div class="modal-header">
      <h3 id="service-type-modal-title">Nouveau type de service</h3>
      <button class="close-modal" onclick="closeServiceTypeModal()">&times;</button>
    </div>
    <form id="service-type-form" onsubmit="saveServiceType(event)">
      <input type="hidden" id="service-type-id" value="">
      <div style="padding:1.25rem">
        <div class="form-group" style="margin-bottom:1rem">
          <label style="font-size:.85rem;font-weight:600">Nom du type *</label>
          <input type="text" id="service-type-name" required placeholder="Ex: Restaurant">
        </div>
      </div>
      <div id="service-type-error" style="color:#e53e3e;font-size:.85rem;padding:0 1.25rem;display:none;margin-bottom:.5rem"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeServiceTypeModal()">Annuler</button>
        <button type="submit" class="btn btn-primary" id="service-type-submit-btn">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
const SERVICE_TYPES_API = window.location.origin + '/api/service-types';

// Service Types Management
async function loadServiceTypes() {
  try {
    const res = await fetch(SERVICE_TYPES_API);
    const serviceTypes = await res.json();
    const listEl = document.getElementById('service-types-list');
    if (!listEl) return;
    
    if (serviceTypes.length === 0) {
      listEl.innerHTML = '<p style="color:var(--muted);font-size:.85rem;text-align:center;padding:1rem">Aucun type de service enregistré</p>';
      return;
    }
    
    listEl.innerHTML = serviceTypes.map(st => `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem;background:var(--surface);border-radius:8px;border:1px solid var(--border)">
        <span style="font-weight:500;font-size:.9rem">${st.name}</span>
        <div style="display:flex;gap:.5rem">
          <button class="btn btn-secondary btn-small" onclick="editServiceType(${st.id}, '${st.name}')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"/></svg>
          </button>
          <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
          <button class="btn btn-small" onclick="deleteServiceType(${st.id})" style="color:#e53e3e;border-color:#fecaca">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
          <?php endif; ?>
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('Erreur chargement types de service:', err);
  }
}

function openAddServiceTypeModal() {
  document.getElementById('service-type-modal-title').textContent = 'Nouveau type de service';
  document.getElementById('service-type-id').value = '';
  document.getElementById('service-type-name').value = '';
  document.getElementById('service-type-error').style.display = 'none';
  document.getElementById('service-type-modal').classList.add('active');
}

function editServiceType(id, name) {
  document.getElementById('service-type-modal-title').textContent = 'Modifier le type de service';
  document.getElementById('service-type-id').value = id;
  document.getElementById('service-type-name').value = name;
  document.getElementById('service-type-error').style.display = 'none';
  document.getElementById('service-type-modal').classList.add('active');
}

function closeServiceTypeModal() {
  document.getElementById('service-type-modal').classList.remove('active');
}

async function saveServiceType(e) {
  e.preventDefault();
  const errEl = document.getElementById('service-type-error');
  errEl.style.display = 'none';
  const btn = document.getElementById('service-type-submit-btn');
  btn.disabled = true;

  const id = document.getElementById('service-type-id').value;
  const data = {
    name: document.getElementById('service-type-name').value.trim()
  };

  const url = id ? `${SERVICE_TYPES_API}/update/${id}` : SERVICE_TYPES_API;
  const method = 'POST';

  try {
    const res = await fetch(url, {
      method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
      closeServiceTypeModal();
      loadServiceTypes();
    } else {
      errEl.textContent = result.error || 'Erreur lors de la sauvegarde';
      errEl.style.display = 'block';
    }
  } catch (err) {
    errEl.textContent = 'Erreur réseau';
    errEl.style.display = 'block';
  }
  btn.disabled = false;
}

async function deleteServiceType(id) {
  if (!confirm('Supprimer ce type de service ?')) return;
  try {
    const res = await fetch(`${SERVICE_TYPES_API}/delete/${id}`, { method: 'POST' });
    const result = await res.json();
    if (result.success) {
      loadServiceTypes();
    } else {
      alert(result.error || 'Erreur lors de la suppression');
    }
  } catch (err) {
    alert('Erreur réseau');
  }
}

// Load service types on page load
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('service-types-list')) {
    loadServiceTypes();
  }
  // Load company info for super_admin
  if (document.getElementById('company-name')) {
    loadCompanyInfo();
  }
});

// Company Info Management (super_admin only)
async function loadCompanyInfo() {
  try {
    const res = await fetch(APP_URL + '/api/company-info');
    const data = await res.json();
    if (data) {
      document.getElementById('company-name').value = data.name || '';
      document.getElementById('company-address').value = data.address || '';
      document.getElementById('company-email').value = data.email || '';
      document.getElementById('company-pdv').value = data.pdv || '';
      document.getElementById('company-phone').value = data.phone || '';
      document.getElementById('company-ice').value = data.ice || '';
      document.getElementById('company-rccm').value = data.rccm || '';
      document.getElementById('company-isf').value = data.isf || '';
      document.getElementById('company-nid').value = data.nid || '';
      document.getElementById('company-token').value = data.token || '';
      document.getElementById('company-port').value = data.port || '';
    }
  } catch (err) {
    console.error('Erreur chargement infos entreprise:', err);
  }
}

async function saveCompanyInfo() {
  const btn = document.querySelector('button[onclick="saveCompanyInfo()"]');
  const status = document.getElementById('company-info-status');
  if (btn) btn.disabled = true;
  if (status) {
    status.textContent = 'Enregistrement...';
    status.style.color = 'var(--muted)';
  }

  const payload = {
    name: document.getElementById('company-name').value || '',
    address: document.getElementById('company-address').value || '',
    email: document.getElementById('company-email').value || '',
    pdv: document.getElementById('company-pdv').value || '',
    phone: document.getElementById('company-phone').value || '',
    ice: document.getElementById('company-ice').value || '',
    rccm: document.getElementById('company-rccm').value || '',
    isf: document.getElementById('company-isf').value || '',
    nid: document.getElementById('company-nid').value || '',
    token: document.getElementById('company-token').value || '',
    port: document.getElementById('company-port').value || ''
  };

  try {
    const res = await fetch(APP_URL + '/api/company-info', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (status) {
        status.textContent = '✓ Enregistré';
        status.style.color = '#16A34A';
      }
    } else {
      if (status) {
        status.textContent = '✗ ' + (data.error || 'Erreur');
        status.style.color = '#DC2626';
      }
    }
  } catch (e) {
    console.error('Erreur sauvegarde company info:', e);
    if (status) {
      status.textContent = '✗ Erreur réseau';
      status.style.color = '#DC2626';
    }
  } finally {
    if (btn) btn.disabled = false;
    setTimeout(() => { if (status) status.textContent = ''; }, 2500);
  }
}
</script>
