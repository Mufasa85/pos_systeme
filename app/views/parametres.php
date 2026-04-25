      <!-- Settings Page -->
      <div id="page-settings" class="page <?= $page == 'parametres' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Paramètres</h2>
        </div>
        <div class="settings-grid">
          <div class="card">
            <div class="card-header">
              <h3>Informations magasin</h3>
            </div>
            <div class="card-body">
              <form id="store-form" class="settings-form">
                <div class="form-group">
                  <label>Nom du magasin</label>
                  <input type="text" id="store-name" name="store_name" value="">
                </div>
                <div class="form-group">
                  <label>Adresse</label>
                  <input type="text" id="store-address" name="store_address" value="">
                </div>
                <div class="form-group">
                  <label>Telephone</label>
                  <input type="text" id="store-phone" name="store_phone" value="">
                </div>
                <div class="form-group">
                  <label>ICE</label>
                  <input type="text" id="store-ice" name="store_ice" value="">
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
              </form>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h3>Parametres TVA</h3>
            </div>
            <div class="card-body">
              <form id="tax-form" class="settings-form">
                <div class="form-group">
                  <label>Taux TVA (%)</label>
                  <input type="number" id="tax-rate" name="tax_rate" value="16" min="0" max="100">
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
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