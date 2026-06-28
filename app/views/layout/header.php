<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title id="page-title-base">Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?></title>

  <!-- Favicon & Icons -->
  <link rel="icon" type="image/svg+xml" href="./assets/img/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="./assets/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="./assets/img/favicon-16x16.png">
  <link rel="apple-touch-icon" href="./assets/img/apple-touch-icon.png">

  <!-- Meta Tags -->
  <meta name="description" content="Système de caisse POS - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?> - Gestion des ventes, recharges et factures">
  <meta name="keywords" content="caisse, POS, vente, facturation, <?= htmlspecialchars($storeName ?? 'magasin') ?>">
  <meta name="author" content="<?= htmlspecialchars($storeName ?? 'Mon Magasin') ?>">
  <meta name="robots" content="noindex, nofollow">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'] ?>">
  <meta property="og:title" content="Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?>">
  <meta property="og:description" content="Système de caisse POS - Gestion des ventes, recharges Electricité/Eau et factures">
  <meta property="og:image" content="./assets/img/og-image.png">
  <meta property="og:locale" content="fr_CD">
  <meta property="og:site_name" content="<?= htmlspecialchars($storeName ?? 'POS System') ?>">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?>">
  <meta name="twitter:description" content="Système de caisse POS - Gestion des ventes et factures">
  <meta name="twitter:image" content="./assets/img/og-image.png">

  <!-- Theme Color -->
  <meta name="theme-color" content="#0B5E88">
  <meta name="msapplication-TileColor" content="#0B5E88">
  <meta name="msapplication-config" content="./assets/img/browserconfig.xml">

  <link rel="stylesheet" href="./assets/css/styles.css?v=208999999999999">
  <link rel="stylesheet" href="./assets/css/mobile-caisse.css?v=999999999999999999999999999">
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
                            'agentCode' => $_SESSION['agent_code'] ?? ''
                          ]) ?>;
  </script>
  <script src="./assets/js/service-bill-fetcher.js"></script>
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
      <h1 id="mobile-store-name"><?= htmlspecialchars($storeName ?? 'Mon Magasin') ?></h1>
      <div id="mobile-user-info" class="mobile-user-info"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
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
        <span id="sidebar-store-name"><?= htmlspecialchars($storeName ?? 'Mon Magasin') ?></span>
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
          <span>Caisse</span>
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

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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

        <a href="/historique" class="nav-item <?= $currentPage == 'historique' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Historique</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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
          <a href="/parametres" class="nav-item <?= $currentPage == 'parametres' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span>Parametres</span>
          </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <div class="user-avatar" id="user-avatar"><?= substr(htmlspecialchars($_SESSION['full_name'] ?? 'U'), 0, 1) ?></div>
          <div class="user-details">
            <span class="user-name" id="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <span class="user-role" id="user-role"><?= ($_SESSION['role'] ?? '') === 'admin' ? 'Administrateur' : 'Vendeur' ?></span>
          </div>
        </div>
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
      </style>