<?php
use App\Models\Settings;
$settingsModel = new Settings();
$storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';

// Vérifier que la session de récupération est active
if (empty($_SESSION['reset_verified'])) {
    header('Location: /forgot-password');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nouveau mot de passe - <?= htmlspecialchars($storeName) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="login-page">
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <h1>Nouveau mot de passe</h1>
        <p>Choisissez un nouveau mot de passe (min. 6 caractères)</p>
      </div>
      <form id="reset-form" class="login-form">
        <div class="form-group">
          <label for="password">Nouveau mot de passe</label>
          <input type="password" id="password" name="password" placeholder="Min. 6 caractères" minlength="6" required autofocus>
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirmer le mot de passe</label>
          <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirmez le mot de passe" minlength="6" required>
        </div>
        <div id="reset-error" class="login-error"></div>
        <div id="reset-success" style="color:green;font-size:.875rem;margin-bottom:.5rem;display:none"></div>
        <button type="submit" class="btn btn-primary btn-full">Mettre à jour</button>
      </form>
    </div>
  </div>

  <script>
    const APP_URL = window.location.origin;

    document.getElementById('reset-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const password = document.getElementById('password').value;
      const password_confirm = document.getElementById('password_confirm').value;
      const errEl = document.getElementById('reset-error');
      const successEl = document.getElementById('reset-success');
      errEl.textContent = '';
      successEl.style.display = 'none';

      if (password !== password_confirm) {
        errEl.textContent = 'Les mots de passe ne correspondent pas';
        return;
      }

      try {
        const res = await fetch(APP_URL + '/api/auth/reset-password', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ password, password_confirm })
        });
        const data = await res.json();
        if (data.success) {
          successEl.textContent = data.message + ' Redirection...';
          successEl.style.display = 'block';
          setTimeout(() => window.location.href = APP_URL + '/', 2000);
        } else {
          errEl.textContent = data.message;
        }
      } catch (err) {
        errEl.textContent = 'Erreur de connexion';
      }
    });
  </script>
</body>
</html>
