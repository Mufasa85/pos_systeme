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
          <div class="card" style="padding: 1.5rem; ">
            <div class="card-header" style="margin-bottom: 1.5rem; padding : 0px">
              <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                  <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Informations magasin
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Configurez les informations affichées sur vos factures</p>
            </div>
            <div class="card-body" style="padding: 0px;">
              <form id="store-form" class="settings-form">
                <div style="background: var(--background); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1rem; ">
                  <div class="form-group">
                    <label>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                      </svg>
                      Nom du magasin
                    </label>
                    <input type="text" id="store-name" name="store_name" value="" placeholder="Ex: SuperMarché Express">
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
                        ICE
                      </label>
                      <input type="text" id="store-ice" name="store_ice" value="" placeholder="Ex: 001234567890123" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.875rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                  </svg>
                  Enregistrer les informations
                </button>
              </form>
            </div>
          </div>

          <!-- Paramètres TVA -->
          <div class="card" style="padding:0px;">
            <div class="card-header" style="margin-bottom: 1.5rem;">
              <h3>

                Paramètres TVA
              </h3>
              <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.25rem;">Définissez le taux de TVA pour vos ventes</p>
            </div>
            <div class="card-body">
              <form id="tax-form" class="settings-form">
                <div style="background: var(--background); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1rem;">
                  <div class="form-group">
                    <label>
                      Taux TVA (%)
                    </label>
                    <div style="position: relative; display: flex; align-items: center;">
                      <input type="number" id="tax-rate" name="tax_rate" value="16" min="0" max="100" style="padding-right: 50px; text-align: center; font-size: 1.25rem; font-weight: 600;">
                      <span style="position: absolute; right: 16px; color: var(--muted); font-size: 1rem;">%</span>
                    </div>

                  </div>


                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.875rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                  </svg>
                  Enregistrer le taux
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <script>
        // Charger les paramètres au démarrage
        async function loadSettings() {
          try {
            const res = await fetch(APP_URL + '/api/settings');
            const data = await res.json();

            // Remplir les champs du formulaire
            document.getElementById('store-name').value = data.store_name || '';
            document.getElementById('store-address').value = data.store_address || '';
            document.getElementById('store-phone').value = data.store_phone || '';
            document.getElementById('store-ice').value = data.store_ice || '';
            document.getElementById('tax-rate').value = data.tax_rate || 16;
          } catch (e) {
            console.error('Erreur chargement settings:', e);
          }
        }

        // Soumettre le formulaire du magasin
        document.getElementById('store-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const formData = new FormData(e.target);

          try {
            const res = await fetch(APP_URL + '/api/settings/store', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            alert(data.message || 'Paramètres sauvegardés');
          } catch (e) {
            alert('Erreur lors de la sauvegarde');
          }
        });

        // Soumettre le formulaire TVA
        document.getElementById('tax-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const formData = new FormData(e.target);

          try {
            const res = await fetch(APP_URL + '/api/settings/tax', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            alert(data.message || 'Taux TVA sauvegardé');
          } catch (e) {
            alert('Erreur lors de la sauvegarde');
          }
        });

        // Charger au démarrage
        loadSettings();
      </script>