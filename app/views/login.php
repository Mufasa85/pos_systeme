<?php

use App\Models\CompanyInfo;

$companyInfoModel = new CompanyInfo();
$companyInfo = $companyInfoModel->get();
$companyName = $companyInfo['name'] ?? 'Mon Entreprise';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - <?= htmlspecialchars($companyName) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ── Loader Splash ────────────────────────────── */
    #splash-loader {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: #0D0552;
      transition: opacity 0.6s ease, visibility 0.6s ease;
      overflow: hidden;
    }
    #splash-loader::before, #splash-loader::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      filter: blur(60px);
      animation: orbPulse 4s ease-in-out infinite;
    }
    #splash-loader::before {
      width: 500px; height: 500px;
      top: -100px; left: -100px;
      background: radial-gradient(circle, rgba(48,233,254,0.6) 0%, rgba(0,229,255,0.2) 50%, transparent 70%);
    }
    #splash-loader::after {
      width: 450px; height: 450px;
      bottom: -80px; right: -80px;
      background: radial-gradient(circle, rgba(0,229,255,0.5) 0%, rgba(48,233,254,0.15) 50%, transparent 70%);
      animation-delay: 3s;
    }
    #splash-loader.hidden {
      opacity: 0;
      visibility: hidden;
    }
    .splash-icon {
      position: relative; z-index: 1;
      width: 80px;
      height: 80px;
      border-radius: 20px;
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
      animation: pulse-icon 1.8s ease-in-out infinite;
      border: 2px solid rgba(255,255,255,0.2);
    }
    .splash-icon svg {
      stroke: #fff;
    }
    .splash-title {
      position: relative; z-index: 1;
      font-family: 'Inter', sans-serif;
      font-size: 1.5rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 0.5rem;
      letter-spacing: -0.5px;
    }
    .splash-subtitle {
      position: relative; z-index: 1;
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
      color: rgba(255,255,255,0.6);
      margin-bottom: 2rem;
    }
    .splash-spinner {
      position: relative; z-index: 1;
      width: 40px;
      height: 40px;
      border: 3px solid rgba(255,255,255,0.15);
      border-top-color: #30E9FE;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
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

    /* ── Login page background ── */
    .login-page {
      position: relative;
      overflow: hidden;
      background: #0D0552;
    }
    .login-page::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      top: -150px; right: -150px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(48,233,254,0.5) 0%, rgba(0,229,255,0.15) 50%, transparent 70%);
      filter: blur(50px);
      animation: orbFloat1 5s ease-in-out infinite;
      z-index: 0;
    }
    .login-page::after {
      content: '';
      position: absolute;
      width: 550px; height: 550px;
      bottom: -150px; left: -150px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(26,10,110,0.9) 0%, rgba(0,229,255,0.3) 50%, transparent 70%);
      filter: blur(50px);
      animation: orbFloat2 6s ease-in-out infinite;
      z-index: 0;
    }
    .login-page .bg-pattern {
      position: absolute;
      inset: 0;
      background: url("/assets/img/pattern_h.png") center / cover no-repeat;
      opacity: 0.35;
      z-index: 1;
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
      position: relative;
      z-index: 2;
      background: rgba(255,255,255,0.95) !important;
      box-shadow: 0 20px 50px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.1) !important;
      border-radius: 16px !important;
    }
    .login-page .btn-primary {
      background: linear-gradient(135deg, #0D0552, #0891B2, #30E9FE) !important;
      border: none !important;
      color: #fff !important;
      transition: transform .2s, box-shadow .2s;
    }
    .login-page .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(48,233,254,0.3);
    }
  </style>
</head>

<body>
  <!-- Splash Loader -->
  <div id="splash-loader">
    <div class="splash-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="2">
        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
        <line x1="8" y1="21" x2="16" y2="21"></line>
        <line x1="12" y1="17" x2="12" y2="21"></line>
      </svg>
    </div>
    <div class="splash-title"><?= htmlspecialchars($companyName) ?></div>

    <div class="splash-subtitle">Chargement du système de caisse...</div>
    <div class="splash-spinner"></div>
  </div>

  <div id="login-page" class="login-page" style="opacity:0;transition:opacity .4s ease .2s">
    <div class="bg-pattern"></div>
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <h1><?= htmlspecialchars($companyName) ?></h1>

        <p>Connectez-vous pour accéder à la caisse</p>
      </div>
      <form id="login-form" class="login-form" action="/login" method="POST">
        <?= App\Core\Security::csrf_tokken(); ?>
        <div class="form-group">
          <label for="username">Nom d'utilisateur</label>
          <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required>
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" placeholder="Entrez votre mot de passe" required>
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
      <div class="login-footer" style="text-align:center;margin-top:1rem">
        <a href="/forgot-password" style="color:var(--primary);font-size:.875rem">Mot de passe oublié ?</a>
      </div>
    </div>
  </div>

  <script>
    // Loader dismiss
    window.addEventListener('load', () => {
      setTimeout(() => {
        document.getElementById('splash-loader').classList.add('hidden');
        document.getElementById('login-page').style.opacity = '1';
      }, 1800);
    });

    const APP_URL = window.location.origin;
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);

      try {
        const res = await fetch(e.target.action, {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.success && data.requires_otp) {
          window.location.href = APP_URL + '/verify-otp';
        } else if (data.success) {
          window.location.href = APP_URL + '/dashboard';
        } else {
          document.getElementById('login-error').textContent = data.message;
        }
      } catch (err) {
        document.getElementById('login-error').textContent = "Erreur de connexion";
      }
    });
  </script>
</body>

</html>