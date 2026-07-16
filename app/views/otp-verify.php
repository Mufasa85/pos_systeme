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
  <title>Vérification OTP - <?= htmlspecialchars($storeName) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    #splash-loader {
      position: fixed; inset: 0; z-index: 9999;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      background: linear-gradient(270deg, #0D0552, #1a0a6e, #00e5ff, #30E9FE, #0D0552);
      background-size: 400% 400%; animation: gradientMove 8s ease infinite;
      transition: opacity .6s ease, visibility .6s ease;
    }
    #splash-loader.hidden { opacity: 0; visibility: hidden; }
    .splash-icon {
      width: 80px; height: 80px; border-radius: 20px;
      background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.5rem; animation: pulse-icon 1.8s ease-in-out infinite;
      border: 2px solid rgba(255,255,255,0.2);
    }
    .splash-icon svg { stroke: #fff; }
    .splash-title { font-family:'Inter',sans-serif; font-size:1.5rem; font-weight:700; color:#fff; margin-bottom:.5rem; }
    .splash-subtitle { font-family:'Inter',sans-serif; font-size:.875rem; color:rgba(255,255,255,0.6); margin-bottom:2rem; }
    .splash-spinner {
      width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.15);
      border-top-color: #30E9FE; border-radius: 50%; animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes pulse-icon {
      0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(48,233,254,0.4); }
      50% { transform: scale(1.05); box-shadow: 0 0 30px 10px rgba(48,233,254,0.2); }
    }
    @keyframes gradientMove {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .login-page { position: relative; overflow: hidden; }
    .login-page::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(270deg, #0D0552cc, #1a0a6eaa, #00e5ff88, #30E9FEaa, #0D0552cc);
      background-size: 400% 400%; animation: gradientMove 10s ease infinite; z-index: 0;
    }
    .login-page::after {
      content: ''; position: absolute; inset: 0;
      background: url("/assets/img/pattern_h.png") center / cover no-repeat;
      opacity: 0.45; z-index: 1;
    }
    .login-card {
      position: relative; z-index: 2;
      background: rgba(255,255,255,0.95) !important;
      box-shadow: 0 20px 50px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.04) !important;
      border-radius: 16px !important;
    }
    .login-page .btn-primary {
      background: linear-gradient(135deg, #0D0552, #00e5ff, #30E9FE) !important;
      background-size: 200% 200% !important; animation: gradientMove 4s ease infinite !important;
      border: none !important; color: #fff !important; transition: transform .2s;
    }
    .login-page .btn-primary:hover { transform: translateY(-1px); }
  </style>
</head>
<body>
  <div id="splash-loader">
    <div class="splash-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="2">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
      </svg>
    </div>
    <div class="splash-title"><?= htmlspecialchars($storeName) ?></div>
    <div class="splash-subtitle">Chargement...</div>
    <div class="splash-spinner"></div>
  </div>

  <div class="login-page" style="opacity:0;transition:opacity .4s ease .2s">
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <h1>Vérification en deux étapes</h1>
        <p>Un code à 6 chiffres a été envoyé à votre email/téléphone</p>
      </div>
      <form id="otp-form" class="login-form">
        <div class="form-group">
          <label for="otp-code">Code OTP</label>
          <input type="text" id="otp-code" name="code" placeholder="000000"
                 maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                 style="text-align:center;font-size:1.5rem;letter-spacing:.5rem" required autofocus>
        </div>
        <div id="otp-error" class="login-error"></div>
        <button type="submit" class="btn btn-primary btn-full">Vérifier</button>
      </form>
      <div style="text-align:center;margin-top:1rem">
        <button id="resend-btn" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.875rem">
          Renvoyer le code
        </button>
      </div>
      <div style="text-align:center;margin-top:.5rem">
        <a href="/" style="color:#888;font-size:.8rem">Annuler et revenir à la connexion</a>
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

    document.getElementById('otp-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = document.getElementById('otp-code').value.trim();
      const errEl = document.getElementById('otp-error');

      try {
        const res = await fetch(APP_URL + '/api/auth/verify-otp', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ code })
        });
        const data = await res.json();
        if (data.success) {
          window.location.href = APP_URL + '/dashboard';
        } else {
          errEl.textContent = data.message;
        }
      } catch (err) {
        errEl.textContent = 'Erreur de connexion';
      }
    });

    document.getElementById('resend-btn').addEventListener('click', async () => {
      const btn = document.getElementById('resend-btn');
      btn.disabled = true;
      btn.textContent = 'Envoi en cours...';

      try {
        const res = await fetch(APP_URL + '/api/auth/resend-otp', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'}
        });
        const data = await res.json();
        btn.textContent = data.success ? 'Code renvoyé ✓' : data.message;
      } catch (err) {
        btn.textContent = 'Erreur';
      }

      setTimeout(() => {
        btn.disabled = false;
        btn.textContent = 'Renvoyer le code';
      }, 30000);
    });
  </script>
</body>
</html>
