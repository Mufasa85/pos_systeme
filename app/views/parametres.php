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
                  <input type="text" id="store-name" value="SuperMarche Express">
                </div>
                <div class="form-group">
                  <label>Adresse</label>
                  <input type="text" id="store-address" value="123 Rue Mohammed V, Casablanca">
                </div>
                <div class="form-group">
                  <label>Telephone</label>
                  <input type="text" id="store-phone" value="+212 522 123 456">
                </div>
                <div class="form-group">
                  <label>ICE</label>
                  <input type="text" id="store-ice" value="001234567890123">
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
                  <input type="number" id="tax-rate" value="20" min="0" max="100">
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
