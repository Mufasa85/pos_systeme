<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System - Login</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div id="login-page" class="login-page">
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <h1>POS System</h1>
        <p>Connectez-vous pour accéder à la caisse</p>
      </div>
      <form id="login-form" class="login-form" action="<?= APP_URL ?>/login" method="POST">
        <div class="form-group">
          <label for="username">Nom d'utilisateur</label>
          <input type="text" id="username" name="nom_utilisateur" placeholder="Entrez votre identifiant" required>
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="mot_de_passe" placeholder="Entrez votre mot de passe" required>
        </div>
        <div id="login-error" class="login-error"></div>
        <button type="submit" class="btn btn-primary btn-full">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
          Se connecter
        </button>
      </form>
      <div class="login-footer">
        <p>Comptes démo:</p>
        <p><strong>admin</strong> / admin123 | <strong>vendeur1</strong> / vendeur123</p>
      </div>
    </div>
  </div>

  <script>
    const APP_URL = "<?= APP_URL ?>";
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        
        try {
            const res = await fetch(e.target.action, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.location.href = APP_URL + '/dashboard';
            } else {
                document.getElementById('login-error').textContent = data.message;
            }
        } catch(err) {
            document.getElementById('login-error').textContent = "Erreur de connexion";
        }
    });
  </script>
</body>
</html>
