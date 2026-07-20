<?php
use App\Models\Settings;
$settingsModel = new Settings();
$storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mot de passe oublié - <?= htmlspecialchars($storeName) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    #splash-loader {
      position: fixed; inset: 0; z-index: 9999;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      background: #0D0552;
      transition: opacity .6s ease, visibility .6s ease;
      overflow: hidden;
    }
    #splash-loader::before, #splash-loader::after {
      content: ''; position: absolute; border-radius: 50%; filter: blur(60px);
      animation: orbPulse 4s ease-in-out infinite;
    }
    #splash-loader::before {
      width: 500px; height: 500px; top: -100px; left: -100px;
      background: radial-gradient(circle, rgba(48,233,254,0.6) 0%, rgba(0,229,255,0.2) 50%, transparent 70%);
    }
    #splash-loader::after {
      width: 450px; height: 450px; bottom: -80px; right: -80px;
      background: radial-gradient(circle, rgba(0,229,255,0.5) 0%, rgba(48,233,254,0.15) 50%, transparent 70%);
      animation-delay: 3s;
    }
    #splash-loader.hidden { opacity: 0; visibility: hidden; }
    .splash-icon {
      position: relative; z-index: 1;
      width: 80px; height: 80px; border-radius: 20px;
      background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.5rem; animation: pulse-icon 1.8s ease-in-out infinite;
      border: 2px solid rgba(255,255,255,0.2);
    }
    .splash-icon svg { stroke: #fff; }
    .splash-title { position: relative; z-index: 1; font-family:'Inter',sans-serif; font-size:1.5rem; font-weight:700; color:#fff; margin-bottom:.5rem; }
    .splash-subtitle { position: relative; z-index: 1; font-family:'Inter',sans-serif; font-size:.875rem; color:rgba(255,255,255,0.6); margin-bottom:2rem; }
    .splash-spinner {
      position: relative; z-index: 1;
      width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.15);
      border-top-color: #30E9FE; border-radius: 50%; animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes pulse-icon {
      0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(48,233,254,0.4); }
      50% { transform: scale(1.05); box-shadow: 0 0 30px 10px rgba(48,233,254,0.2); }
    }
    @keyframes orbPulse {
      0% { opacity: 0.5; transform: scale(1) translate(0, 0); }
      25% { opacity: 0.8; transform: scale(1.1) translate(30px, 20px); }
      50% { opacity: 1; transform: scale(1.2) translate(-20px, 40px); }
      75% { opacity: 0.7; transform: scale(1.05) translate(-30px, -10px); }
      100% { opacity: 0.5; transform: scale(1) translate(0, 0); }
    }
    .login-page { position: relative; overflow: hidden; background: #0D0552; }
    .login-page::before {
      content: ''; position: absolute;
      width: 600px; height: 600px; top: -150px; right: -150px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(48,233,254,0.5) 0%, rgba(0,229,255,0.15) 50%, transparent 70%);
      filter: blur(50px); animation: orbFloat1 5s ease-in-out infinite; z-index: 0;
    }
    .login-page::after {
      content: ''; position: absolute;
      width: 550px; height: 550px; bottom: -150px; left: -150px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(26,10,110,0.9) 0%, rgba(0,229,255,0.3) 50%, transparent 70%);
      filter: blur(50px); animation: orbFloat2 6s ease-in-out infinite; z-index: 0;
    }
    .login-page .bg-pattern {
      position: absolute; inset: 0;
      background: url("/assets/img/pattern_h.png") center / cover no-repeat;
      opacity: 0.35; z-index: 1;
    }
    @keyframes orbFloat1 {
      0% { opacity: 0.7; transform: scale(1) translate(0, 0); }
      25% { opacity: 0.9; transform: scale(1.05) translate(-40px, 30px); }
      50% { opacity: 1; transform: scale(1.15) translate(-60px, 60px); }
      75% { opacity: 0.8; transform: scale(1.1) translate(-20px, 40px); }
      100% { opacity: 0.7; transform: scale(1) translate(0, 0); }
    }
    @keyframes orbFloat2 {
      0% { opacity: 0.6; transform: scale(1) translate(0, 0); }
      25% { opacity: 0.8; transform: scale(1.1) translate(40px, -20px); }
      50% { opacity: 1; transform: scale(1.2) translate(60px, -50px); }
      75% { opacity: 0.9; transform: scale(1.05) translate(30px, -30px); }
      100% { opacity: 0.6; transform: scale(1) translate(0, 0); }
    }
    .login-card {
      position: relative; z-index: 2;
      background: rgba(255,255,255,0.95) !important;
      box-shadow: 0 20px 50px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.1) !important;
      border-radius: 16px !important;
    }
    .login-page .btn-primary {
      background: linear-gradient(135deg, #0D0552, #0891B2, #30E9FE) !important;
      border: none !important; color: #fff !important;
      transition: transform .2s, box-shadow .2s;
    }
    .login-page .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(48,233,254,0.3);
    }
  </style>
</head>
<body>
  <div id="splash-loader">
    <div class="splash-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="2">
        <circle cx="12" cy="12" r="4"></circle>
        <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path>
      </svg>
    </div>
    <div class="splash-title"><?= htmlspecialchars($storeName) ?></div>
    <div class="splash-subtitle">Chargement...</div>
    <div class="splash-spinner"></div>
  </div>

  <div class="login-page" style="opacity:0;transition:opacity .4s ease .2s">
    <div class="bg-pattern"></div>
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path>
          </svg>
        </div>
        <h1>Mot de passe oublié</h1>
        <p>Entrez votre numéro de téléphone</p>
      </div>

      <!-- Étape 1 : Saisir le contact -->
      <form id="forgot-form" class="login-form">
        <div class="form-group">
          <label for="contact">Numéro de téléphone</label>
          <input type="text" id="contact" name="contact" placeholder="0800000000" required autofocus>
        </div>
        <div id="forgot-error" class="login-error"></div>
        <div id="forgot-success" style="color:green;font-size:.875rem;margin-bottom:.5rem;display:none"></div>
        <button type="submit" class="btn btn-primary btn-full">Envoyer le code</button>
      </form>

      <!-- Étape 2 : Saisir le code OTP (caché au début) -->
      <form id="verify-form" class="login-form" style="display:none">
        <div class="form-group">
          <label for="reset-code">Code de vérification</label>
          <input type="text" id="reset-code" name="code" placeholder="000000"
                 maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                 style="text-align:center;font-size:1.5rem;letter-spacing:.5rem" required>
        </div>
        <div id="verify-error" class="login-error"></div>
        <button type="submit" class="btn btn-primary btn-full">Vérifier</button>
      </form>

      <div style="text-align:center;margin-top:1rem">
        <a href="/" style="color:#888;font-size:.8rem">Retour à la connexion</a>
      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', () => {
      setTimeout(() => {
        document.getElementById('splash-loader').classList.add('hidden');
        document.querySelector('.login-page').style.opacity = '1';
      }, 1800);
    });

    const APP_URL = window.location.origin;

    // Étape 1
    document.getElementById('forgot-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const contact = document.getElementById('contact').value.trim();
      const errEl = document.getElementById('forgot-error');
      const successEl = document.getElementById('forgot-success');
      errEl.textContent = '';
      successEl.style.display = 'none';

      try {
        const query = new URLSearchParams({ contact });
        const res = await fetch(APP_URL + '/api/auth/forgot-password?' + query.toString(), {
          method: 'GET'
        });
        const data = await res.json();
        console.log('Forgot password response:', data);
        if (data.success) {
          successEl.textContent = data.message;
          successEl.style.display = 'block';
          // Montrer l'étape 2
          document.getElementById('forgot-form').style.display = 'none';
          document.getElementById('verify-form').style.display = 'block';
        } else {
          errEl.textContent = data.message;
        }
      } catch (err) {
        errEl.textContent = 'Erreur de connexion';
      }
    });

    // Étape 2
    document.getElementById('verify-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = document.getElementById('reset-code').value.trim();
      const errEl = document.getElementById('verify-error');

      try {
        const res = await fetch(APP_URL + '/api/auth/verify-reset-code', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ code })
        });
        const data = await res.json();
        if (data.success) {
          window.location.href = APP_URL + '/reset-password';
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
