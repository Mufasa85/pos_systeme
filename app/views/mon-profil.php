      <!-- Mon Profil Page -->
      <div id="page-mon-profil" class="page <?= $page == 'mon-profil' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Mon Profil
          </h2>
        </div>
        
        <div class="settings-grid">
          <div class="card" style="padding: 2rem; max-width: 500px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
              <?php if (!empty($currentUser['profile_image'])): ?>
                <img src="<?= htmlspecialchars($currentUser['profile_image']) ?>" alt="Photo de profil" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border); margin-bottom: 1rem;">
              <?php else: ?>
                <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 3rem; color: white; border: 4px solid var(--border);">
                  <?= substr(htmlspecialchars($currentUser['nom_complet']), 0, 1) ?>
                </div>
              <?php endif; ?>
              <h3 style="margin: 0 0 0.5rem 0;"><?= htmlspecialchars($currentUser['nom_complet']) ?></h3>
              <p style="color: var(--muted); margin: 0;">@<?= htmlspecialchars($currentUser['nom_utilisateur']) ?></p>
              <span class="badge <?= $currentUser['role'] === 'super_admin' ? 'badge-warning' : ($currentUser['role'] === 'admin' ? 'badge-primary' : 'badge-success') ?>" style="margin-top: 0.5rem; display: inline-block;">
                <?= $currentUser['role'] === 'super_admin' ? 'Super Admin' : ($currentUser['role'] === 'admin' ? 'Admin' : 'Vendeur') ?>
              </span>
            </div>

            <div style="display:grid; gap:0.9rem; margin-bottom:1.5rem;">
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;background:var(--background);border-radius:var(--radius);border:1px solid rgba(0,0,0,.05);">
                <div style="font-size:.85rem;color:var(--muted);">Nom complet</div>
                <div style="font-weight:600;"><?= htmlspecialchars($currentUser['nom_complet'] ?: '-') ?></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;background:var(--background);border-radius:var(--radius);border:1px solid rgba(0,0,0,.05);">
                <div style="font-size:.85rem;color:var(--muted);">Email</div>
                <div style="font-weight:600;"><?= htmlspecialchars($currentUser['email'] ?? '-') ?: '-' ?></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;background:var(--background);border-radius:var(--radius);border:1px solid rgba(0,0,0,.05);">
                <div style="font-size:.85rem;color:var(--muted);">Téléphone</div>
                <div style="font-weight:600;"><?= htmlspecialchars($currentUser['telephone'] ?? '-') ?: '-' ?></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;background:var(--background);border-radius:var(--radius);border:1px solid rgba(0,0,0,.05);">
                <div style="font-size:.85rem;color:var(--muted);">Code Agent</div>
                <div style="font-weight:600;"><?= htmlspecialchars($currentUser['agent_code'] ?? '-') ?: '-' ?></div>
              </div>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem;">
              <button class="btn btn-primary" type="button" onclick="if(typeof openProfileModal === 'function'){ openProfileModal(); } else { alert('Modal de modification du profil indisponible.'); }" style="flex:1;min-width:140px;">
                Modifier mon profil
              </button>
              <button class="btn btn-secondary" type="button" onclick="if(typeof openClotureModal === 'function'){ openClotureModal(); } else { alert('Modal de rapport de clôture indisponible.'); }" style="flex:1;min-width:140px;">
                Rapport de clôture
              </button>
            </div>

            <div style="margin-bottom: 1.5rem;">
              <label for="profile-image-upload" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 8px;">Changer la photo de profil</label>
              <input type="file" id="profile-image-upload" accept="image/jpeg,image/png,image/gif" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--background);">
              <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Formats acceptés: JPG, PNG, GIF (max 2MB)</div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
              <button class="btn btn-primary" onclick="uploadProfileImage()" style="flex: 1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="17 8 12 3 7 8"></polyline>
                  <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Mettre à jour
              </button>
              <?php if (!empty($currentUser['profile_image'])): ?>
              <button class="btn btn-secondary" onclick="removeProfileImage()" style="flex: 1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Supprimer
              </button>
              <?php endif; ?>
            </div>

            <div id="upload-status" style="margin-top: 1rem; font-size: 0.85rem; text-align: center;"></div>
          </div>
        </div>
      </div>

      <script>
      async function uploadProfileImage() {
        const fileInput = document.getElementById('profile-image-upload');
        const statusEl = document.getElementById('upload-status');
        
        if (!fileInput.files || fileInput.files.length === 0) {
          statusEl.textContent = 'Veuillez sélectionner une image';
          statusEl.style.color = '#DC2626';
          return;
        }

        const formData = new FormData();
        formData.append('profile_image', fileInput.files[0]);

        statusEl.textContent = 'Téléchargement...';
        statusEl.style.color = 'var(--muted)';

        try {
          const res = await fetch(APP_URL + '/api/user/upload-profile-image', {
            method: 'POST',
            body: formData
          });
          const result = await res.json();
          
          if (result.success) {
            statusEl.textContent = '✓ Photo de profil mise à jour avec succès';
            statusEl.style.color = '#16A34A';
            setTimeout(() => location.reload(), 1500);
          } else {
            statusEl.textContent = '✗ ' + (result.message || 'Erreur lors du téléchargement');
            statusEl.style.color = '#DC2626';
          }
        } catch (err) {
          console.error('Erreur upload:', err);
          statusEl.textContent = '✗ Erreur réseau';
          statusEl.style.color = '#DC2626';
        }
      }

      async function removeProfileImage() {
        if (!confirm('Êtes-vous sûr de vouloir supprimer votre photo de profil ?')) return;

        const statusEl = document.getElementById('upload-status');
        statusEl.textContent = 'Suppression...';
        statusEl.style.color = 'var(--muted)';

        try {
          const res = await fetch(APP_URL + '/api/user/update-profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ profile_image: null })
          });
          const result = await res.json();
          
          if (result.success) {
            statusEl.textContent = '✓ Photo de profil supprimée';
            statusEl.style.color = '#16A34A';
            setTimeout(() => location.reload(), 1500);
          } else {
            statusEl.textContent = '✗ Erreur lors de la suppression';
            statusEl.style.color = '#DC2626';
          }
        } catch (err) {
          console.error('Erreur suppression:', err);
          statusEl.textContent = '✗ Erreur réseau';
          statusEl.style.color = '#DC2626';
        }
      }
      </script>
