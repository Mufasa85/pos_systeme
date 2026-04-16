      <!-- Settings Page -->
      <div class="page active" style="display:block;">
        <div class="page-header">
          <h2>Paramètres</h2>
        </div>
        <div class="settings-grid">
          <div class="card">
            <div class="card-header">
              <h3>Informations magasin</h3>
            </div>
            <div class="card-body">
              <form id="store-form" class="settings-form" onsubmit="event.preventDefault(); alert('Sauvegardé avec succès')">
                <div class="form-group">
                  <label>Nom du magasin</label>
                  <input type="text" id="store-name" value="SuperMarché Express">
                </div>
                <div class="form-group">
                  <label>Adresse</label>
                  <input type="text" id="store-address" value="123 Rue Mohammed V, Casablanca">
                </div>
                <div class="form-group">
                  <label>Téléphone</label>
                  <input type="text" id="store-phone" value="+212 522 123 456">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Enregistrer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
