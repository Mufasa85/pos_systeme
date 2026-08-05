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
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
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
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <h1>Vérification en deux étapes</h1>
        <p id="otp-channel-text">Un code à 6 chiffres a été envoyé</p>
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
        <div id="resend-channels" style="display:none;margin-top:.5rem;gap:.5rem;justify-content:center;flex-wrap:wrap">
          <button class="resend-channel-btn" data-channel="sms"
                  style="background:none;border:1px solid var(--primary);color:var(--primary);cursor:pointer;font-size:.8rem;padding:.4rem .8rem;border-radius:6px">
            Par SMS
          </button>
          <button class="resend-channel-btn" data-channel="email"
                  style="background:none;border:1px solid var(--primary);color:var(--primary);cursor:pointer;font-size:.8rem;padding:.4rem .8rem;border-radius:6px">
            Par Email
          </button>
        </div>
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
        const params = new URLSearchParams();
        params.set('code', code);
        const contact = new URLSearchParams(window.location.search).get('contact') || '';
        if (contact) {
          params.set('contact', contact);
        }

        const res = await fetch(APP_URL + '/api/auth/verify-otp?' + params.toString(), {
          method: 'GET'
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
      const channelsDiv = document.getElementById('resend-channels');
      const isVisible = channelsDiv.style.display !== 'none';
      channelsDiv.style.display = isVisible ? 'none' : 'flex';
    });

    document.querySelectorAll('.resend-channel-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const channel = btn.dataset.channel;
        const allBtns = document.querySelectorAll('.resend-channel-btn');
        allBtns.forEach(b => b.disabled = true);
        btn.textContent = 'Envoi en cours...';

        try {
          const params = new URLSearchParams();
          const contact = new URLSearchParams(window.location.search).get('contact') || '';
          if (contact) {
            params.set('contact', contact);
          }
          params.set('channel', channel);

          const res = await fetch(APP_URL + '/api/auth/resend-otp?' + params.toString(), {
            method: 'GET'
          });
          const data = await res.json();
          const mainBtn = document.getElementById('resend-btn');
          mainBtn.textContent = data.success ? 'Code renvoyé ✓' : data.message;
          document.getElementById('resend-channels').style.display = 'none';
        } catch (err) {
          btn.textContent = 'Erreur';
        }

        setTimeout(() => {
          allBtns.forEach(b => {
            b.disabled = false;
            b.textContent = b.dataset.channel === 'sms' ? ' Par SMS' : ' Par Email';
          });
          document.getElementById('resend-btn').textContent = 'Renvoyer le code';
        }, 30000);
      });
    });
  </script>
</body>
</html>
