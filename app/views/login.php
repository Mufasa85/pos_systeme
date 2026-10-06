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

    /* ── Password field with toggle ── */
    .password-field {
      position: relative;
      display: block;
    }
    .password-field input[type="password"],
    .password-field input[type="text"] {
      width: 100%;
      padding-right: 3rem; /* réserve la place pour le bouton */
    }
    .password-toggle {
      position: absolute;
      top: 50%;
      right: 0.5rem;
      transform: translateY(-50%);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      padding: 0;
      border: none;
      background: transparent;
      color: #6b7280;
      border-radius: 8px;
      cursor: pointer;
      transition: color .2s, background-color .2s;
    }
    .password-toggle:hover {
      color: #0D0552;
      background-color: rgba(48, 233, 254, 0.12);
    }
    .password-toggle:focus-visible {
      outline: 2px solid #30E9FE;
      outline-offset: 2px;
      color: #0D0552;
    }
    .password-toggle:active {
      transform: translateY(-50%) scale(0.95);
    }
    .password-toggle .icon-eye-off { display: none; }
    .password-toggle.is-visible .icon-eye { display: none; }
    .password-toggle.is-visible .icon-eye-off { display: block; }

    /* ── Terms & conditions checkbox ── */
    .terms-group {
      margin-top: -0.25rem;
      margin-bottom: 0.25rem;
    }
    .terms-label {
      display: flex;
      align-items: flex-start;
      gap: 0.55rem;
      font-size: 0.85rem;
      line-height: 1.4;
      color: #374151;
      cursor: pointer;
      user-select: none;
    }
    .terms-label input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin-top: 2px;
      flex-shrink: 0;
      accent-color: #30E9FE;
      cursor: pointer;
    }
    .terms-text { flex: 1; }
    .terms-link {
      color: #0891B2;
      font-weight: 500;
      text-decoration: underline;
      text-underline-offset: 2px;
    }
    .terms-link:hover {
      color: #0D0552;
    }
    .terms-link:focus-visible {
      outline: 2px solid #30E9FE;
      outline-offset: 2px;
      border-radius: 2px;
    }
    /* Bouton "Se connecter" désactivé tant que la case n'est pas cochée */
    .btn-primary[disabled] {
      opacity: 0.55;
      cursor: not-allowed;
      filter: grayscale(0.25);
    }
    .btn-primary[disabled]:hover {
      transform: none;
      box-shadow: none;
    }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#1a1e64">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker
          .register('/sw.js', { scope: '/' })
          .then(function (registration) {
            console.log('[PWA] Service Worker registered, scope:', registration.scope);
            registration.addEventListener('updatefound', function () {
              const newWorker = registration.installing;
              if (!newWorker) return;
              newWorker.addEventListener('statechange', function () {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  console.log('[PWA] Nouvelle version disponible, activation…');
                  newWorker.postMessage('SKIP_WAITING');
                }
              });
            });
          })
          .catch(function (err) {
            console.error('[PWA] Service Worker registration failed:', err);
          });
      });
    }
  </script>
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

        <?php if (!empty($shopDisabledReason)): ?>
          <div class="login-shop-disabled" role="alert" style="
            background:#fee2e2;
            border:1px solid #fecaca;
            color:#991b1b;
            padding:.75rem 1rem;
            border-radius:8px;
            margin-bottom:1rem;
            font-size:.85rem;
            display:flex;
            align-items:flex-start;
            gap:.5rem;
          ">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div>
              <strong style="display:block;margin-bottom:2px">Boutique désactivée</strong>
              Votre boutique a été désactivée par le super administrateur. Vous ne pouvez plus accéder à l'application. Veuillez le contacter pour réactiver votre boutique.
            </div>
          </div>
        <?php endif; ?>
        <div class="form-group">
          <label for="username">Nom d'utilisateur</label>
          <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required>
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <div class="password-field">
            <input type="password" id="password" name="password" placeholder="Entrez votre mot de passe" autocomplete="current-password" required>
            <button type="button" id="toggle-password" class="password-toggle" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher / masquer le mot de passe">
              <!-- Icône œil (par défaut : mot de passe masqué) -->
              <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <!-- Icône œil barré (mot de passe visible) -->
              <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </button>
          </div>
        </div>
        <div class="form-group terms-group">
          <label class="terms-label" for="accept-terms">
            <input type="checkbox" id="accept-terms" name="accept_terms" required>
            <span class="terms-text">
              J'accepte les
              <a href="https://osat-energie.com/dgi/terme_et_condition/index.php"
                 target="_blank"
                 rel="noopener noreferrer"
                 class="terms-link">termes et conditions</a>
            </span>
          </label>
        </div>
        <div id="login-error" class="login-error"></div>
        <button type="submit" id="login-submit" class="btn btn-primary btn-full" disabled aria-disabled="true">
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

    // ── Activation du bouton selon acceptation des termes ──
    (function () {
      const termsCheckbox = document.getElementById('accept-terms');
      const submitBtn = document.getElementById('login-submit');
      const errorBox = document.getElementById('login-error');
      if (!termsCheckbox || !submitBtn) return;

      const syncSubmitState = () => {
        const accepted = termsCheckbox.checked;
        submitBtn.disabled = !accepted;
        submitBtn.setAttribute('aria-disabled', accepted ? 'false' : 'true');
      };
      syncSubmitState();
      termsCheckbox.addEventListener('change', () => {
        syncSubmitState();
        // Si l'utilisateur coche après une erreur, on l'efface
        if (errorBox && errorBox.textContent) {
          errorBox.textContent = '';
        }
      });
    })();

    // ── Afficher / masquer le mot de passe ──
    (function () {
      const pwdInput = document.getElementById('password');
      const toggleBtn = document.getElementById('toggle-password');
      if (!pwdInput || !toggleBtn) return;

      toggleBtn.addEventListener('click', function () {
        const isHidden = pwdInput.getAttribute('type') === 'password';
        pwdInput.setAttribute('type', isHidden ? 'text' : 'password');
        toggleBtn.classList.toggle('is-visible', isHidden);
        toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        toggleBtn.setAttribute(
          'aria-label',
          isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
        );
        // Garder le focus sur le champ après le clic
        pwdInput.focus();
        // Placer le curseur en fin de texte
        const len = pwdInput.value.length;
        try { pwdInput.setSelectionRange(len, len); } catch (e) { /* noop */ }
      });
    })();

    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      // Garde-fou : exiger l'acceptation des termes avant l'envoi
      const termsCheckbox = document.getElementById('accept-terms');
      const errorBox = document.getElementById('login-error');
      if (termsCheckbox && !termsCheckbox.checked) {
        if (errorBox) {
          errorBox.textContent = "Vous devez accepter les termes et conditions pour vous connecter.";
        }
        termsCheckbox.focus();
        return;
      }

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