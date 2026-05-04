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
        <div class="settings-grid">
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
                <input type="text" id="store-address" name="store_address" value="" placeholder="Ex: 123 Rue Mohammed V, Casablanca" readonly>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    Téléphone
                  </label>
                  <input type="text" id="store-phone" name="store_phone" value="" placeholder="Ex: +212 522 123 456" readonly>
                </div>
                <div class="form-group">
                  <label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                      <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    ICE
                  </label>
                  <input type="text" id="store-ice" name="store_ice" value="" placeholder="Ex: 001234567890123" style="font-family: 'JetBrains Mono', monospace;" readonly>
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
                  <input type="text" id="store-isf" name="store_isf" value="" placeholder="Ex: ISF123456" readonly>
                </div>
              </div>
            </div>
          </div>

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
      </div>

      <style>
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
            document.getElementById('store-phone').value = data.store_phone || '';
            document.getElementById('store-ice').value = data.store_ice || '';
            document.getElementById('store-rccm').value = data.store_rccm || '';
            document.getElementById('store-isf').value = data.store_isf || '';
          } catch (e) {
            console.error('Erreur chargement settings:', e);
          }
        }

        // Recharger l'abonnement avec un code d'activation
        async function reloadSubscription() {
          const codeInput = document.getElementById('activation-code');
          const btn = document.getElementById('btn-reload-subscription');
          
          if (!codeInput || !codeInput.value.trim()) {
            alert('Veuillez entrer un code d\'activation');
            return;
          }

          // Désactiver le bouton pendant le traitement
          btn.disabled = true;
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> Traitement en cours...';

          try {
            const res = await fetch(APP_URL + '/api/subscription/reload', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ activation_code: codeInput.value.trim() })
            });
            
            const data = await res.json();
            
            if (data.success) {
              alert('Abonnement rechargé avec succès !');
              codeInput.value = '';
            } else {
              alert(data.message || 'Erreur lors du rechargement');
            }
          } catch (e) {
            console.error('Erreur reloadSubscription:', e);
            alert('Erreur de connexion');
          }

          // Réactiver le bouton
          btn.disabled = false;
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> Recharger abonnement';
        }

        // Charger au démarrage
        loadSettings();
      </script>
