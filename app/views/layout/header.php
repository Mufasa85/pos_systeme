<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System - Caisse Professionnelle</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/mobile-caisse.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script>
    const APP_URL = window.location.origin;
    const CURRENT_USER = <?= json_encode([
                            'id' => $_SESSION['user_id'] ?? null,
                            'username' => $_SESSION['username'] ?? '',
                            'fullName' => $_SESSION['full_name'] ?? '',
                            'role' => $_SESSION['role'] ?? 'vendeur'
                          ]) ?>;
  </script>
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
      <h1>POS System</h1>
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
        <span>POS System</span>
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
        <a href="/produits" class="nav-item <?= $currentPage == 'produits' ? 'active' : '' ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span>Produits</span>
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
            <span>Catégories</span>
          </a>
          <a href="/parametres" class="nav-item <?= $currentPage == 'parametres' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span>Paramètres</span>
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