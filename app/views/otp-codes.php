      <!-- OTP Codes Page -->
      <div id="page-otp-codes" class="page <?= $page == 'otp-codes' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Codes OTP
          </h2>
          <button class="btn btn-secondary" onclick="loadOtpCodes()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Actualiser
          </button>
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Utilisateur</th>
                <th>Code OTP</th>
                <th>Type</th>
                <th>Canal</th>
                <th>Statut</th>
                <th>Expiration</th>
                <th>Créé le</th>
              </tr>
            </thead>
            <tbody id="otp-codes-list">
              <tr>
                <td colspan="7" style="text-align:center; padding: 2rem; color: var(--muted);">
                  Chargement...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <script>
      async function loadOtpCodes() {
        try {
          const res = await fetch(APP_URL + '/api/auth/otp-codes');
          const result = await res.json();
          const listEl = document.getElementById('otp-codes-list');
          
          if (!result.success || !result.data || result.data.length === 0) {
            listEl.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--muted);">Aucun code OTP enregistré</td></tr>';
            return;
          }
          
          listEl.innerHTML = result.data.map(otp => `
            <tr style="border-bottom:1px solid #eee;">
              <td style="padding:0.75rem;">
                <div>
                  <div style="font-weight: 500;">${otp.nom_complet || 'Utilisateur inconnu'}</div>
                  <div style="font-size: 0.75rem; color: var(--muted);">@${otp.nom_utilisateur || ''}</div>
                </div>
              </td>
              <td style="padding:0.75rem; font-family: monospace; font-size: 1rem; font-weight: 600; letter-spacing: 2px;">${otp.code}</td>
              <td style="padding:0.75rem;">
                <span class="badge ${otp.type === 'login' ? 'badge-primary' : 'badge-warning'}">${otp.type === 'login' ? 'Connexion' : 'Récupération'}</span>
              </td>
              <td style="padding:0.75rem;">
                <span class="badge ${otp.channel === 'email' ? 'badge-success' : 'badge-info'}">${otp.channel === 'email' ? 'Email' : 'SMS'}</span>
              </td>
              <td style="padding:0.75rem;">
                <span class="badge ${otp.used ? 'badge-danger' : 'badge-success'}">${otp.used ? 'Utilisé' : 'Actif'}</span>
              </td>
              <td style="padding:0.75rem; font-size: 0.85rem;">${new Date(otp.expires_at).toLocaleString('fr-FR')}</td>
              <td style="padding:0.75rem; font-size: 0.85rem;">${new Date(otp.created_at).toLocaleString('fr-FR')}</td>
            </tr>
          `).join('');
        } catch (err) {
          console.error('Erreur chargement codes OTP:', err);
          document.getElementById('otp-codes-list').innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #DC2626;">Erreur lors du chargement</td></tr>';
        }
      }

      // Load OTP codes on page load
      if (document.getElementById('otp-codes-list')) {
        loadOtpCodes();
      }
      </script>
