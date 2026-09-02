<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php $headerTitleName = ($_SESSION['role'] ?? '') === 'super_admin' ? ($companyName ?? ($companyInfo['name'] ?? ($storeName ?? 'Mon Magasin'))) : ($storeName ?? 'Mon Magasin'); ?>
  <title id="page-title-base">Caisse - <?= htmlspecialchars($headerTitleName) ?></title>


  <!-- Favicon & Icons -->
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png">
  <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

  <!-- Meta Tags -->
  <?php $headerTitleName = ($_SESSION['role'] ?? '') === 'super_admin' ? ($companyName ?? ($companyInfo['name'] ?? ($storeName ?? 'Mon Magasin'))) : ($storeName ?? 'Mon Magasin'); ?>
  <meta name="description" content="Système de caisse POS - <?= htmlspecialchars($headerTitleName) ?> - Gestion des ventes, recharges et factures">
  <meta name="keywords" content="caisse, POS, vente, facturation, <?= htmlspecialchars($headerTitleName) ?>">
  <meta name="author" content="<?= htmlspecialchars($headerTitleName) ?>">

  <meta name="robots" content="noindex, nofollow">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'] ?>">
  <meta property="og:title" content="Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?>">
  <meta property="og:description" content="Système de caisse POS - Gestion des ventes, recharges Electricité/Eau et factures">
  <meta property="og:image" content="/assets/img/og-image.png">
  <meta property="og:locale" content="fr_CD">
  <meta property="og:site_name" content="<?= htmlspecialchars($storeName ?? 'POS System') ?>">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?>">
  <meta name="twitter:description" content="Système de caisse POS - Gestion des ventes et factures">
  <meta name="twitter:image" content="/assets/img/og-image.png">

  <!-- Theme Color -->
  <meta name="theme-color" content="#0B5E88">
  <meta name="msapplication-TileColor" content="#0B5E88">
  <meta name="msapplication-config" content="/assets/img/browserconfig.xml">

  <link rel="stylesheet" href="/assets/css/styles.css?v=208999999999999">
  <link rel="stylesheet" href="/assets/css/mobile-caisse.css?v=999999999999999999999999999">
  <?php if (($page ?? '') === 'payroll'): ?>
    <link rel="stylesheet" href="/assets/css/payroll.css?v=1">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script>
    const APP_URL = window.location.origin;
    const CURRENT_USER = <?= json_encode([
                            'id' => $_SESSION['user_id'] ?? null,
                            'username' => $_SESSION['nom_utilisateur'] ?? '',
                            'fullName' => $_SESSION['nom_complet'] ?? '',
                            'role' => $_SESSION['role'] ?? 'vendeur',
                            'agentCode' => $_SESSION['agent_code'] ?? '',
                            'shopId' => $_SESSION['shop_id'] ?? null,
                          ]) ?>;
  </script>
  <script src="/assets/js/service-bill-fetcher.js"></script>
  <script src="/assets/js/theme.js?v=1"></script>
</head>

<body>
  <!-- Main App -->
  <div id="main-app" class="main-app">
    <!-- Mobile Header -->
    <header class="mobile-header">
      <button id="menu-toggle" class="menu-toggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
      <?php $headerTitleName = ($_SESSION['role'] ?? '') === 'super_admin' ? ($companyName ?? ($companyInfo['name'] ?? ($storeName ?? 'Mon Magasin'))) : ($storeName ?? 'Mon Magasin'); ?>
      <h1 id="mobile-store-name"><?= htmlspecialchars($headerTitleName) ?></h1>

      <div style="display:flex;align-items:center;gap:8px">
        <a href="#" id="mobile-notif-bell" class="notif-bell-trigger" style="position:relative;color:inherit;text-decoration:none" title="Notifications">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="notif-badge" id="notif-badge-mobile" style="<?= (empty($unreadNotifications) || $unreadNotifications <= 0) ? 'display:none' : '' ?>"><?= $unreadNotifications ?? 0 ?></span>
        </a>
        <!-- Notification Dropdown -->
        <div id="notif-dropdown" class="notif-dropdown" style="display:none">
          <div class="notif-dropdown-header">
            <strong>Notifications</strong>
            <a href="#" id="notif-mark-all">Tout marquer lu</a>
          </div>
          <div id="notif-dropdown-list" class="notif-dropdown-list">
            <div class="notif-empty">Aucune notification</div>
          </div>
        </div>
      </div>
    </header>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <?php
        $role = $_SESSION['role'] ?? '';
  if ($role === 'super_admin') {
      // Utiliser exactement la même requête que dans le header/login (companyName / companyInfo / storeName)
      $sidebarStoreName = ($companyName ?? ($companyInfo['name'] ?? ($storeName ?? 'Mon Magasin')));
  } else {
      $sidebarStoreName = ($storeName ?? 'Mon Magasin');
  }
  ?>
        <span id="sidebar-store-name"><?= htmlspecialchars($sidebarStoreName) ?></span>

        <button id="close-sidebar" class="close-sidebar">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <nav class="sidebar-nav">
        <?php $currentPage = $page ?? 'dashboard'; ?>
        <a href="/dashboard" class="nav-item <?= $currentPage == 'dashboard' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span>Tableau de bord</span>
        </a>
        <a href="/caisse" class="nav-item <?= $currentPage == 'caisse' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
          <span><?= htmlspecialchars($serviceType ?? 'Caisse') ?></span>
        </a>
        <a href="/recharges" class="nav-item <?= $currentPage == 'recharges' ? 'active' : '' ?>">
          <div class="nav-icon-split">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
            </svg>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
            </svg>
          </div>
          <span>ELECTRICITE/EAU</span>
        </a>
        <a href="/produits" class="nav-item <?= $currentPage == 'produits' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span>Produits</span>
        </a>

        <a href="/analytics" class="nav-item <?= $currentPage == 'analytics' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 3v18h18"></path>
            <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
          </svg>
          <span>Analytics</span>
        </a>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
          <a href="/utilisateurs" class="nav-item <?= $currentPage == 'utilisateurs' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Utilisateurs</span>
          </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
          <a href="/otp-codes" class="nav-item <?= $currentPage == 'otp-codes' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>Codes OTP</span>
          </a>
        <?php endif; ?>

        <a href="/historique" class="nav-item <?= $currentPage == 'historique' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Historique</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'vendeur'): ?>
          <a href="/payroll/mypayslips" class="nav-item <?= $currentPage == 'payroll' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <span>Mes bulletins</span>
          </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
          <a href="/categories" class="nav-item <?= $currentPage == 'categories' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 6h16M4 10h16M4 14h7M4 18h10"></path>
            </svg>
            <span>Categories</span>
          </a>
          <a href="/taxes" class="nav-item <?= $currentPage == 'taxes' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="19" y1="5" x2="5" y2="19"></line>
              <circle cx="6.5" cy="6.5" r="2.5"></circle>
              <circle cx="17.5" cy="17.5" r="2.5"></circle>
            </svg>
            <span>Taxes</span>
          </a>
          <a href="/payroll" class="nav-item <?= $currentPage == 'payroll' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span>Paie</span>
          </a>
          <a href="/payroll/settings" class="nav-item <?= $currentPage == 'payroll' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span>Parametres paie</span>
          </a>
          <a href="/parametres" class="nav-item <?= $currentPage == 'parametres' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span>Parametres</span>
          </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
          <a href="/shops" class="nav-item <?= $currentPage == 'shops' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Boutiques</span>
          </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <a href="/mon-profil" class="user-info" style="text-decoration:none;color:inherit;cursor:pointer" title="Mon profil">
          <?php if (!empty($currentUserProfileImage)): ?>
            <img src="<?= htmlspecialchars($currentUserProfileImage) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
          <?php else: ?>
            <div class="user-avatar" id="user-avatar"><?= substr(htmlspecialchars($_SESSION['nom_complet'] ?? 'U'), 0, 1) ?></div>
          <?php endif; ?>
          <div class="user-details">
            <span class="user-name" id="user-name"><?= htmlspecialchars($_SESSION['nom_complet'] ?? '') ?></span>
            <span class="user-role" id="user-role"><?php
        $r = $_SESSION['role'] ?? '';
  echo $r === 'super_admin' ? 'Super Admin' : ($r === 'admin' ? 'Administrateur' : 'Vendeur');
  ?></span>
          </div>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;opacity:.5">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </a>
        <a href="/logout" class="btn btn-logout" style="text-decoration: none; display: flex; align-items: center;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span>Deconnexion</span>
        </a>
      </div>
    </aside>

    <!-- Overlay for mobile -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

    <!-- Main Content -->
    <main class="main-content">

      <style>
        .nav-icon-split {
          display: flex;
          align-items: center;
          gap: 4px;
        }
        .notif-badge {
          position: absolute;
          top: -6px;
          right: -6px;
          background: #e53e3e;
          color: #fff;
          font-size: .65rem;
          font-weight: 700;
          min-width: 16px;
          height: 16px;
          line-height: 16px;
          text-align: center;
          border-radius: 50%;
          padding: 0 4px;
        }
        .notif-dropdown {
          position: absolute;
          top: 48px;
          right: 8px;
          width: 340px;
          max-height: 420px;
          background: var(--card-bg, #fff);
          border-radius: 12px;
          box-shadow: 0 8px 32px rgba(0,0,0,.18);
          z-index: 9999;
          overflow: hidden;
          border: 1px solid var(--border, #e2e8f0);
        }
        .notif-dropdown-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 16px;
          border-bottom: 1px solid var(--border, #e2e8f0);
          font-size: .85rem;
        }
        .notif-dropdown-header a {
          font-size: .75rem;
          color: var(--primary, #0B5E88);
          text-decoration: none;
        }
        .notif-dropdown-list {
          max-height: 350px;
          overflow-y: auto;
        }
        .notif-item {
          padding: 10px 16px;
          border-bottom: 1px solid var(--border, #f1f5f9);
          cursor: pointer;
          transition: background .15s;
          font-size: .82rem;
        }
        .notif-item:hover { background: var(--hover-bg, #f8fafc); }
        .notif-item.unread { background: rgba(11,94,136,.05); }
        .notif-item .notif-title { font-weight: 600; margin-bottom: 2px; }
        .notif-item .notif-msg { color: var(--muted, #64748b); font-size: .78rem; }
        .notif-item .notif-time { color: var(--muted, #94a3b8); font-size: .7rem; margin-top: 2px; }
        .notif-empty { padding: 24px; text-align: center; color: var(--muted, #94a3b8); font-size: .85rem; }
      </style>

      <script>
      (function() {
        const badge = document.getElementById('notif-badge-mobile');
        const dropdown = document.getElementById('notif-dropdown');
        const listEl = document.getElementById('notif-dropdown-list');
        const markAllBtn = document.getElementById('notif-mark-all');
        let dropdownOpen = false;

        // Toggle dropdown
        document.querySelectorAll('.notif-bell-trigger').forEach(bell => {
          bell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownOpen = !dropdownOpen;
            dropdown.style.display = dropdownOpen ? 'block' : 'none';
            if (dropdownOpen) loadNotifications();
          });
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
          if (dropdownOpen && !dropdown.contains(e.target) && !e.target.closest('.notif-bell-trigger')) {
            dropdownOpen = false;
            dropdown.style.display = 'none';
          }
        });

        // Load notifications
        async function loadNotifications() {
          try {
            const res = await fetch(APP_URL + '/api/notifications');
            const data = await res.json();
            const notifs = data.data || data || [];
            if (!Array.isArray(notifs) || notifs.length === 0) {
              listEl.innerHTML = '<div class="notif-empty">Aucune notification</div>';
              return;
            }
            listEl.innerHTML = notifs.slice(0, 15).map(n => `
              <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                <div class="notif-title">${escHtml(n.title)}</div>
                <div class="notif-msg">${escHtml(n.message)}</div>
                <div class="notif-time">${timeAgo(n.created_at)}</div>
              </div>
            `).join('');
            // Click to mark read
            listEl.querySelectorAll('.notif-item.unread').forEach(el => {
              el.addEventListener('click', async () => {
                await fetch(APP_URL + '/api/notifications/read/' + el.dataset.id, { method: 'POST' });
                el.classList.remove('unread');
                pollUnread();
              });
            });
          } catch(e) { console.error('Notif load error', e); }
        }

        // Mark all read
        if (markAllBtn) {
          markAllBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            await fetch(APP_URL + '/api/notifications/read-all', { method: 'POST' });
            listEl.querySelectorAll('.unread').forEach(el => el.classList.remove('unread'));
            updateBadge(0);
          });
        }

        // Poll unread count
        async function pollUnread() {
          try {
            const res = await fetch(APP_URL + '/api/notifications/unread');
            const data = await res.json();
            const count = data.count ?? data.data ?? 0;
            updateBadge(count);
          } catch(e) {}
        }

        function updateBadge(count) {
          if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
          }
        }

        function escHtml(s) {
          const d = document.createElement('div');
          d.textContent = s || '';
          return d.innerHTML;
        }

        function timeAgo(dateStr) {
          if (!dateStr) return '';
          const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
          if (diff < 60) return 'à l\'instant';
          if (diff < 3600) return Math.floor(diff/60) + ' min';
          if (diff < 86400) return Math.floor(diff/3600) + ' h';
          return Math.floor(diff/86400) + ' j';
        }

        // Poll every 30 seconds
        pollUnread();
        setInterval(pollUnread, 30000);
      })();
      </script>