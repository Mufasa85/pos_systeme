<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SuperAdmin - POS System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0B5E88;
      --primary-dark: #003B5C;
      --primary-light: #E8F4F8;
      --accent: #2AB7E6;
      --background: #F3F6FA;
      --foreground: #1a1a2e;
      --card: #FFFFFF;
      --card-foreground: #1a1a2e;
      --secondary: #e2e8f0;
      --secondary-foreground: #1a1a2e;
      --muted: #64748b;
      --muted-foreground: #64748b;
      --border: #e2e8f0;
      --input: #e2e8f0;
      --success: #10b981;
      --success-light: #D1FAE5;
      --warning: #f59e0b;
      --warning-light: #FEF3C7;
      --destructive: #ef4444;
      --destructive-light: #FEE2E2;
      --sidebar-bg: #003B5C;
      --sidebar-text: #FFFFFF;
      --sidebar-hover: #0B5E88;
      --radius: 8px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--background); color: var(--foreground); min-height: 100vh; }
    
    /* Login */
    .login-container { display: flex; min-height: 100vh; }
    .login-left { flex: 1; background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--accent) 100%); display: flex; align-items: center; justify-content: center; padding: 40px; }
    .login-left-content { text-align: center; color: white; max-width: 400px; }
    .login-left-content h1 { font-size: 48px; font-weight: 700; margin-bottom: 16px; }
    .login-left-content p { font-size: 18px; opacity: 0.9; }
    .login-right { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; }
    .login-box { width: 100%; max-width: 400px; background: var(--card); padding: 40px; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .login-box h2 { font-size: 28px; margin-bottom: 8px; color: var(--primary); }
    .login-box p { color: var(--muted); margin-bottom: 32px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--foreground); }
    .form-group input { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s; }
    .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,136,0.1); }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .btn-secondary { background: var(--secondary); color: var(--foreground); }
    .btn-secondary:hover { background: #d1d5db; }
    .btn-danger { background: var(--destructive); color: white; }
    .btn-danger:hover { background: #dc2626; }
    .btn-success { background: var(--success); color: white; }
    .btn-success:hover { background: #059669; }
    .btn-block { width: 100%; justify-content: center; }
    .forgot-password { text-align: right; margin-bottom: 24px; }
    .forgot-password a { color: var(--primary); font-size: 14px; text-decoration: none; }
    .forgot-password a:hover { text-decoration: underline; }
    .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
    .alert-error { background: var(--destructive-light); color: var(--destructive); }
    .alert-success { background: var(--success-light); color: var(--success); }

    /* Sidebar */
    .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 260px; background: var(--sidebar-bg); color: var(--sidebar-text); transition: all 0.3s ease; z-index: 50; display: flex; flex-direction: column; }
    .sidebar.collapsed { width: 72px; }
    .sidebar-header { display: flex; align-items: center; justify-content: space-between; height: 64px; padding: 0 16px; border-bottom: 1px solid var(--sidebar-hover); }
    .sidebar-logo { display: flex; align-items: center; gap: 12px; }
    .logo-icon { width: 36px; height: 36px; background: var(--accent); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
    .logo-text { font-weight: 600; font-size: 18px; white-space: nowrap; }
    .sidebar.collapsed .logo-text { display: none; }
    .sidebar-nav { flex: 1; padding: 16px 8px; overflow-y: auto; }
    .nav-section { margin-bottom: 16px; }
    .nav-section-title { font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.5); padding: 8px 12px; letter-spacing: 0.5px; white-space: nowrap; }
    .sidebar.collapsed .nav-section-title { display: none; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.8); margin-bottom: 4px; }
    .nav-item:hover { background: rgba(11,94,136,0.5); color: white; }
    .nav-item.active { background: var(--sidebar-hover); color: white; }
    .nav-item svg { width: 20px; height: 20px; flex-shrink: 0; }
    .nav-item .nav-label { font-weight: 500; font-size: 14px; white-space: nowrap; }
    .sidebar.collapsed .nav-label { display: none; }
    .nav-item .badge-count { margin-left: auto; background: var(--destructive); color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; }
    .sidebar-toggle { display: flex; align-items: center; justify-content: center; height: 48px; border-top: 1px solid var(--sidebar-hover); cursor: pointer; transition: background 0.2s; }
    .sidebar-toggle:hover { background: var(--sidebar-hover); }

    /* Main Content */
    .main-content { margin-left: 260px; transition: margin-left 0.3s ease; min-height: 100vh; }
    .main-content.sidebar-collapsed { margin-left: 72px; }
    
    /* Header */
    .header { position: fixed; top: 0; right: 0; height: 64px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 40; transition: left 0.3s ease; }
    .header.with-sidebar { left: 260px; }
    .header.with-sidebar.collapsed { left: 72px; }
    .header-left { display: flex; align-items: center; gap: 16px; }
    .header-search { position: relative; }
    .header-search input { width: 300px; padding: 8px 12px 8px 40px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--background); outline: none; }
    .header-search input:focus { border-color: var(--primary); }
    .header-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); width: 16px; height: 16px; }
    .header-right { display: flex; align-items: center; gap: 12px; }
    .notification-btn, .user-btn { position: relative; background: none; border: none; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; color: var(--muted); }
    .notification-btn:hover, .user-btn:hover { background: var(--secondary); }
    .notification-badge { position: absolute; top: 4px; right: 4px; width: 18px; height: 18px; background: var(--destructive); color: white; font-size: 10px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .user-info { display: flex; align-items: center; gap: 10px; padding: 6px 12px; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
    .user-info:hover { background: var(--secondary); }
    .user-avatar { width: 36px; height: 36px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
    .user-details { text-align: left; }
    .user-name { font-size: 14px; font-weight: 500; }
    .user-role { font-size: 12px; color: var(--muted); }

    /* Page Content */
    .page-content { padding: 24px; margin-top: 64px; }
    .page { display: none; }
    .page.active { display: block; animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .page-title { font-size: 28px; font-weight: 700; color: var(--foreground); }
    .page-subtitle { color: var(--muted); margin-top: 4px; }
    .page-header-actions { display: flex; gap: 12px; }

    /* Stats Cards */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .stat-card { background: var(--card); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; transition: all 0.2s; }
    .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .stat-card.primary { background: var(--primary); color: white; border: none; }
    .stat-card.success { background: var(--success); color: white; border: none; }
    .stat-card.warning { background: var(--warning); color: white; border: none; }
    .stat-card.danger { background: var(--destructive); color: white; border: none; }
    .stat-content h3 { font-size: 14px; font-weight: 500; opacity: 0.8; }
    .stat-card:not(.primary):not(.success):not(.warning):not(.danger) h3 { color: var(--muted); }
    .stat-value { font-size: 32px; font-weight: 700; margin-top: 8px; }
    .stat-trend { font-size: 12px; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
    .stat-card:not(.primary):not(.success):not(.warning):not(.danger) .stat-trend { color: var(--muted); }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .stat-card:not(.primary):not(.success):not(.warning):not(.danger) .stat-icon { background: rgba(11,94,136,0.1); color: var(--primary); }
    .stat-card.primary .stat-icon, .stat-card.success .stat-icon, .stat-card.warning .stat-icon, .stat-card.danger .stat-icon { background: rgba(255,255,255,0.2); color: white; }

    /* Content Grid */
    .content-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
    .content-card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .content-card-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--border); }
    .content-card-title { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 16px; }
    .content-card-title svg { color: var(--primary); }
    .view-all { color: var(--primary); font-size: 14px; cursor: pointer; background: none; border: none; }
    .view-all:hover { text-decoration: underline; }
    .activity-item, .alert-item { padding: 16px 24px; display: flex; justify-content: space-between; align-items: flex-start; transition: background 0.2s; cursor: pointer; border-bottom: 1px solid var(--border); }
    .activity-item:last-child, .alert-item:last-child { border-bottom: none; }
    .activity-item:hover, .alert-item:hover { background: rgba(243,246,250,0.5); }
    .activity-content h4 { font-weight: 500; margin-bottom: 4px; }
    .activity-content p { font-size: 14px; color: var(--muted); }
    .activity-meta { text-align: right; }
    .activity-meta .time { font-size: 12px; color: var(--muted); }
    .activity-meta .user { font-size: 12px; color: var(--primary); margin-top: 4px; }
    .alert-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
    .alert-dot.warning { background: var(--warning); }
    .alert-dot.danger { background: var(--destructive); }
    .alert-dot.success { background: var(--success); }

    /* Table */
    .table-container { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 24px; margin-top: 24px; }
    .table-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
    .table-search { position: relative; max-width: 300px; }
    .table-search input { width: 100%; padding: 10px 12px 10px 40px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--background); outline: none; }
    .table-search input:focus { border-color: var(--primary); }
    .table-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); width: 16px; height: 16px; }
    .table-filters { display: flex; gap: 12px; flex-wrap: wrap; }
    .table-filters select { padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--card); outline: none; min-width: 150px; }
    .table-filters select:focus { border-color: var(--primary); }
    .table-wrapper { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border); }
    table { width: 100%; border-collapse: collapse; }
    thead { background: var(--background); }
    th { text-align: left; padding: 14px 16px; font-size: 13px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 16px; font-size: 14px; border-top: 1px solid var(--border); vertical-align: middle; }
    tbody tr { background: var(--card); transition: background 0.2s; }
    tbody tr:hover { background: rgba(243,246,250,0.5); }
    .action-btns { display: flex; gap: 8px; justify-content: flex-end; }
    .action-btns .btn { padding: 8px; border-radius: 6px; }
    .action-btns .btn:hover { background: var(--secondary); }
    .action-btns .btn.danger:hover { background: var(--destructive-light); color: var(--destructive); }
    .action-btns .btn.warning:hover { background: var(--warning-light); color: var(--warning); }
    .action-btns .btn.success:hover { background: var(--success-light); color: var(--success); }
    .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 16px; }
    .pagination-info { font-size: 14px; color: var(--muted); }
    .pagination-btns { display: flex; gap: 4px; }
    .pagination-btns .btn { min-width: 36px; justify-content: center; }
    .pagination-btns .btn.active { background: var(--primary); color: white; }

    /* Badges */
    .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .badge-success { background: var(--success-light); color: var(--success); }
    .badge-warning { background: var(--warning-light); color: var(--warning); }
    .badge-danger { background: var(--destructive-light); color: var(--destructive); }
    .badge-info { background: rgba(42,183,230,0.1); color: var(--accent); }
    .badge-primary { background: var(--primary-light); color: var(--primary); }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; visibility: hidden; transition: all 0.3s; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal { background: var(--card); border-radius: 16px; width: 100%; max-width: 560px; margin: 0 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); transform: scale(0.9); transition: transform 0.3s; max-height: 90vh; overflow-y: auto; }
    .modal-overlay.active .modal { transform: scale(1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--card); z-index: 1; }
    .modal-header h2 { font-size: 20px; font-weight: 600; }
    .modal-close { background: none; border: none; cursor: pointer; color: var(--muted); padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .modal-close:hover { background: var(--secondary); }
    .modal-body { padding: 24px; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 24px; border-top: 1px solid var(--border); position: sticky; bottom: 0; background: var(--card); }
    .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s; background: var(--card); }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,136,0.1); }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .form-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }

    /* Dropdown */
    .dropdown { position: relative; }
    .dropdown-content { position: absolute; top: 100%; right: 0; background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); min-width: 200px; z-index: 100; display: none; transform: translateY(10px); opacity: 0; transition: all 0.2s; }
    .dropdown.active .dropdown-content { display: block; transform: translateY(0); opacity: 1; }
    .dropdown-item { padding: 12px 16px; display: flex; align-items: center; gap: 10px; font-size: 14px; cursor: pointer; transition: background 0.2s; }
    .dropdown-item:hover { background: var(--background); }
    .dropdown-item.danger { color: var(--destructive); }
    .dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }

    /* License cards */
    .license-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
    .license-card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 20px; transition: all 0.2s; }
    .license-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .license-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
    .license-type { font-size: 14px; font-weight: 600; color: var(--primary); }
    .license-info { margin-bottom: 12px; }
    .license-info p { font-size: 14px; color: var(--muted); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .license-info strong { color: var(--foreground); }
    .license-features { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }

    /* Empty state */
    .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
    .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
    .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: var(--foreground); }
    .empty-state p { font-size: 14px; }

    /* Mobile */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main-content { margin-left: 0; }
      .header { left: 0 !important; }
      .login-left { display: none; }
      .stats-grid { grid-template-columns: 1fr; }
      .content-grid { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; }
      .table-toolbar { flex-direction: column; align-items: stretch; }
      .table-search { max-width: none; }
    }

    /* Charts placeholder */
    .chart-container { height: 300px; display: flex; align-items: center; justify-content: center; background: var(--background); border-radius: 8px; }
  </style>
</head>
<body>
  <!-- Login Screen -->
  <div id="login-screen" class="login-container">
    <div class="login-left">
      <div class="login-left-content">
        <h1>POS System</h1>
        <p>Gestion centralisee des entreprises et licences</p>
      </div>
    </div>
    <div class="login-right">
      <div class="login-box">
        <h2>Connexion SuperAdmin</h2>
        <p>Accedez a votre panneau de gestion</p>
        <div id="login-alert"></div>
        <form id="login-form">
          <div class="form-group">
            <label>Nom d'utilisateur</label>
            <input type="text" id="login-username" placeholder="Entrez votre nom d'utilisateur" required>
          </div>
          <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" id="login-password" placeholder="Entrez votre mot de passe" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Main App -->
  <div id="main-app" style="display: none;">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <div class="logo-icon">SA</div>
          <span class="logo-text">SuperAdmin</span>
        </div>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-section-title">Principal</div>
          <div class="nav-item active" data-page="dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span class="nav-label">Tableau de bord</span>
          </div>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">Gestion</div>
          <div class="nav-item" data-page="entreprises">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01"></path></svg>
            <span class="nav-label">Entreprises</span>
            <span class="badge-count" id="entreprises-count">0</span>
          </div>
          <div class="nav-item" data-page="licences">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span class="nav-label">Licences</span>
          </div>
          <div class="nav-item" data-page="utilisateurs">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span class="nav-label">Utilisateurs</span>
          </div>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">Systeme</div>
          <div class="nav-item" data-page="historique">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span class="nav-label">Historique</span>
          </div>
          <div class="nav-item" data-page="parametres">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <span class="nav-label">Parametres</span>
          </div>
        </div>
      </nav>
      <div class="sidebar-toggle" onclick="toggleSidebar()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </div>
    </aside>

    <!-- Header -->
    <header class="header with-sidebar" id="header">
      <div class="header-left">
        <div class="header-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" placeholder="Rechercher...">
        </div>
      </div>
      <div class="header-right">
        <button class="notification-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
          <span class="notification-badge">3</span>
        </button>
        <div class="dropdown" id="user-dropdown">
          <div class="user-info" onclick="toggleDropdown()">
            <div class="user-avatar" id="user-avatar">SA</div>
            <div class="user-details">
              <div class="user-name" id="user-name">SuperAdmin</div>
              <div class="user-role">Super Administrateur</div>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </div>
          <div class="dropdown-content">
            <div class="dropdown-item" onclick="showPage('profile')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              Mon Profil
            </div>
            <div class="dropdown-divider"></div>
            <div class="dropdown-item danger" onclick="logout()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
              Deconnexion
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <main class="main-content" id="main-content">
      <!-- Dashboard -->
      <div class="page active" id="page-dashboard">
        <div class="page-header">
          <div>
            <h1 class="page-title">Tableau de bord</h1>
            <p class="page-subtitle">Vue d'ensemble du systeme</p>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-secondary" onclick="exportData()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              Exporter
            </button>
          </div>
        </div>
        <div class="stats-grid">
          <div class="stat-card primary">
            <div class="stat-content">
              <h3>Entreprises actives</h3>
              <div class="stat-value" id="stat-entreprises">0</div>
              <div class="stat-trend">+2 ce mois</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01"></path></svg></div>
          </div>
          <div class="stat-card success">
            <div class="stat-content">
              <h3>Utilisateurs totaux</h3>
              <div class="stat-value" id="stat-utilisateurs">0</div>
              <div class="stat-trend">+15 cette semaine</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></div>
          </div>
          <div class="stat-card warning">
            <div class="stat-content">
              <h3>Licences actives</h3>
              <div class="stat-value" id="stat-licences">0</div>
              <div class="stat-trend">3 expirent bientot</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
          </div>
          <div class="stat-card danger">
            <div class="stat-content">
              <h3>Alertes</h3>
              <div class="stat-value" id="stat-alertes">0</div>
              <div class="stat-trend">Action requise</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>
          </div>
        </div>
        <div class="content-grid">
          <div class="content-card">
            <div class="content-card-header">
              <div class="content-card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Activites recentes
              </div>
              <button class="view-all" onclick="showPage('historique')">Voir tout</button>
            </div>
            <div id="recent-activities">
              <div class="activity-item">
                <div class="activity-content">
                  <h4>Nouvelle entreprise ajoutee</h4>
                  <p>SARL Atlas - Casablanca</p>
                </div>
                <div class="activity-meta">
                  <div class="time">Il y a 2 heures</div>
                  <div class="user">SuperAdmin</div>
                </div>
              </div>
              <div class="activity-item">
                <div class="activity-content">
                  <h4>Licence renouvelee</h4>
                  <p>SuperMarche Express - Plan Vitalice</p>
                </div>
                <div class="activity-meta">
                  <div class="time">Il y a 5 heures</div>
                  <div class="user">SuperAdmin</div>
                </div>
              </div>
            </div>
          </div>
          <div class="content-card">
            <div class="content-card-header">
              <div class="content-card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                Alertes systeme
              </div>
            </div>
            <div id="system-alerts">
              <div class="alert-item">
                <div class="alert-dot danger"></div>
                <div class="activity-content" style="flex: 1;">
                  <h4>2 licences expirent dans 7 jours</h4>
                  <p>Maroc Telecom SARL, Beta Store</p>
                </div>
              </div>
              <div class="alert-item">
                <div class="alert-dot warning"></div>
                <div class="activity-content" style="flex: 1;">
                  <h4>Stock faible detecte</h4>
                  <p>5 produits en rupture dans 3 entreprises</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Entreprises Page -->
      <div class="page" id="page-entreprises">
        <div class="page-header">
          <div>
            <h1 class="page-title">Entreprises</h1>
            <p class="page-subtitle">Gestion des entreprises enregistrees</p>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" onclick="openEntrepriseModal()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Nouvelle Entreprise
            </button>
          </div>
        </div>
        <div class="table-container">
          <div class="table-toolbar">
            <div class="table-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input type="text" placeholder="Rechercher une entreprise..." id="entreprise-search" oninput="filterEntreprises()">
            </div>
            <div class="table-filters">
              <select id="entreprise-filter-status" onchange="filterEntreprises()">
                <option value="">Tous les statuts</option>
                <option value="active">Actif</option>
                <option value="inactif">Inactif</option>
                <option value="suspendu">Suspendu</option>
              </select>
            </div>
          </div>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Entreprise</th>
                  <th>ICE</th>
                  <th>Ville</th>
                  <th>Telephone</th>
                  <th>Utilisateurs</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="entreprises-table"></tbody>
            </table>
          </div>
          <div class="pagination">
            <div class="pagination-info">Affichage <span id="entreprise-count">0</span> entreprises</div>
            <div class="pagination-btns" id="entreprise-pagination"></div>
          </div>
        </div>
      </div>

      <!-- Licences Page -->
      <div class="page" id="page-licences">
        <div class="page-header">
          <div>
            <h1 class="page-title">Licences</h1>
            <p class="page-subtitle">Gestion des licences d'entreprise</p>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" onclick="openLicenceModal()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Nouvelle Licence
            </button>
          </div>
        </div>
        <div class="stats-grid" style="margin-bottom: 24px;">
          <div class="stat-card success">
            <div class="stat-content">
              <h3>Licences Vitalice</h3>
              <div class="stat-value" id="licences-vitalice">0</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg></div>
          </div>
          <div class="stat-card warning">
            <div class="stat-content">
              <h3>Licences Annuelles</h3>
              <div class="stat-value" id="licences-annuel">0</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
          </div>
          <div class="stat-card danger">
            <div class="stat-content">
              <h3>Licences Expirees</h3>
              <div class="stat-value" id="licences-expirees">0</div>
            </div>
            <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg></div>
          </div>
        </div>
        <div class="table-container">
          <div class="table-toolbar">
            <div class="table-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input type="text" placeholder="Rechercher une licence..." id="licence-search" oninput="filterLicences()">
            </div>
            <div class="table-filters">
              <select id="licence-filter-type" onchange="filterLicences()">
                <option value="">Tous les types</option>
                <option value="essai">Essai</option>
                <option value="mensuel">Mensuel</option>
                <option value="annuel">Annuel</option>
                <option value="vitalice">Vitalice</option>
              </select>
              <select id="licence-filter-status" onchange="filterLicences()">
                <option value="">Tous les statuts</option>
                <option value="active">Active</option>
                <option value="expiree">Expiree</option>
                <option value="suspendue">Suspendue</option>
              </select>
            </div>
          </div>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Entreprise</th>
                  <th>Type</th>
                  <th>Date debut</th>
                  <th>Date fin</th>
                  <th>Statut</th>
                  <th>Modules</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="licences-table"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Utilisateurs Page -->
      <div class="page" id="page-utilisateurs">
        <div class="page-header">
          <div>
            <h1 class="page-title">Utilisateurs</h1>
            <p class="page-subtitle">Gestion des comptes super admin</p>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" onclick="openUtilisateurModal()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Nouvel Utilisateur
            </button>
          </div>
        </div>
        <div class="table-container">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Utilisateur</th>
                  <th>Email</th>
                  <th>Derniere connexion</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="utilisateurs-table"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Historique Page -->
      <div class="page" id="page-historique">
        <div class="page-header">
          <div>
            <h1 class="page-title">Historique</h1>
            <p class="page-subtitle">Journal d'activites du systeme</p>
          </div>
        </div>
        <div class="table-container">
          <div class="table-toolbar">
            <div class="table-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input type="text" placeholder="Rechercher dans l'historique...">
            </div>
          </div>
          <div id="historique-list"></div>
        </div>
      </div>

      <!-- Parametres Page -->
      <div class="page" id="page-parametres">
        <div class="page-header">
          <div>
            <h1 class="page-title">Parametres</h1>
            <p class="page-subtitle">Configuration du systeme</p>
          </div>
        </div>
        <div class="content-grid" style="grid-template-columns: 1fr;">
          <div class="content-card">
            <div class="content-card-header">
              <div class="content-card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Configuration generale
              </div>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Devise par defaut</label>
                <select id="param-devise"><option value="MAD" selected>MAD - Dirham Marocain</option><option value="EUR">EUR - Euro</option><option value="USD">USD - Dollar US</option></select>
              </div>
              <div class="form-group">
                <label>Taux TVA par defaut (%)</label>
                <input type="number" id="param-tva" value="20" step="0.1">
              </div>
              <div class="form-group">
                <label>Version de la base de donnees</label>
                <input type="text" id="param-version" value="2.0" disabled>
              </div>
              <button class="btn btn-primary" onclick="saveParams()">Enregistrer</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Entreprise Modal -->
  <div class="modal-overlay" id="entreprise-modal">
    <div class="modal">
      <div class="modal-header">
        <h2 id="entreprise-modal-title">Nouvelle Entreprise</h2>
        <button class="modal-close" onclick="closeModal('entreprise-modal')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="entreprise-id">
        <div class="form-row">
          <div class="form-group">
            <label>Nom de l'entreprise *</label>
            <input type="text" id="entreprise-nom" required>
          </div>
          <div class="form-group">
            <label>Nom commercial</label>
            <input type="text" id="entreprise-commercial">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Forme juridique</label>
            <select id="entreprise-forme">
              <option value="">Selectionner...</option>
              <option value="SARL">SARL</option>
              <option value="SA">SA</option>
              <option value="SNC">SNC</option>
              <option value="SCS">SCS</option>
              <option value="Auto-entrepreneur">Auto-entrepreneur</option>
            </select>
          </div>
          <div class="form-group">
            <label>ICE</label>
            <input type="text" id="entreprise-ice">
          </div>
        </div>
        <div class="form-group">
          <label>Adresse</label>
          <textarea id="entreprise-adresse"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Ville</label>
            <input type="text" id="entreprise-ville">
          </div>
          <div class="form-group">
            <label>Pays</label>
            <input type="text" id="entreprise-pays" value="Maroc">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Telephone</label>
            <input type="text" id="entreprise-tel">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="entreprise-email">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Nom du gerant</label>
            <input type="text" id="entreprise-gerant">
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select id="entreprise-statut">
              <option value="active">Actif</option>
              <option value="inactif">Inactif</option>
              <option value="suspendu">Suspendu</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('entreprise-modal')">Annuler</button>
        <button class="btn btn-primary" onclick="saveEntreprise()">Enregistrer</button>
      </div>
    </div>
  </div>

  <!-- Licence Modal -->
  <div class="modal-overlay" id="licence-modal">
    <div class="modal">
      <div class="modal-header">
        <h2 id="licence-modal-title">Nouvelle Licence</h2>
        <button class="modal-close" onclick="closeModal('licence-modal')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="licence-id">
        <div class="form-row">
          <div class="form-group">
            <label>Entreprise *</label>
            <select id="licence-entreprise" required></select>
          </div>
          <div class="form-group">
            <label>Type de licence</label>
            <select id="licence-type">
              <option value="essai">Essai (30 jours)</option>
              <option value="mensuel">Mensuel</option>
              <option value="annuel">Annuel</option>
              <option value="vitalice">Vitalice</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Date de debut</label>
            <input type="date" id="licence-debut">
          </div>
          <div class="form-group">
            <label>Date de fin</label>
            <input type="date" id="licence-fin">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Max utilisateurs</label>
            <input type="number" id="licence-max-users" value="10">
          </div>
          <div class="form-group">
            <label>Max points de vente</label>
            <input type="number" id="licence-max-pos" value="1">
          </div>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select id="licence-statut">
            <option value="active">Active</option>
            <option value="expiree">Expiree</option>
            <option value="suspendue">Suspendue</option>
            <option value="annulee">Annulee</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('licence-modal')">Annuler</button>
        <button class="btn btn-primary" onclick="saveLicence()">Enregistrer</button>
      </div>
    </div>
  </div>

  <!-- Utilisateur Modal -->
  <div class="modal-overlay" id="utilisateur-modal">
    <div class="modal">
      <div class="modal-header">
        <h2 id="utilisateur-modal-title">Nouvel Utilisateur</h2>
        <button class="modal-close" onclick="closeModal('utilisateur-modal')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="utilisateur-id">
        <div class="form-group">
          <label>Nom complet *</label>
          <input type="text" id="utilisateur-nom" required>
        </div>
        <div class="form-group">
          <label>Nom d'utilisateur *</label>
          <input type="text" id="utilisateur-username" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" id="utilisateur-email" required>
        </div>
        <div class="form-group">
          <label id="utilisateur-password-label">Mot de passe *</label>
          <input type="password" id="utilisateur-password">
          <div class="form-hint" id="utilisateur-password-hint" style="display:none;">Laissez vide pour ne pas modifier</div>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select id="utilisateur-actif">
            <option value="1">Actif</option>
            <option value="0">Inactif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('utilisateur-modal')">Annuler</button>
        <button class="btn btn-primary" onclick="saveUtilisateur()">Enregistrer</button>
      </div>
    </div>
  </div>

  <script>
    // =============================================
    // DATA STORE
    // =============================================
    const Store = {
      currentUser: null,
      entreprises: [],
      licences: [],
      utilisateurs: [],
      historiques: [],
      params: { devise: 'MAD', tva: 20, version: '2.0' },

      save() {
        localStorage.setItem('sa_entreprises', JSON.stringify(this.entreprises));
        localStorage.setItem('sa_licences', JSON.stringify(this.licences));
        localStorage.setItem('sa_utilisateurs', JSON.stringify(this.utilisateurs));
        localStorage.setItem('sa_historiques', JSON.stringify(this.historiques));
        localStorage.setItem('sa_params', JSON.stringify(this.params));
      },

      load() {
        const e = localStorage.getItem('sa_entreprises');
        const l = localStorage.getItem('sa_licences');
        const u = localStorage.getItem('sa_utilisateurs');
        const h = localStorage.getItem('sa_historiques');
        const p = localStorage.getItem('sa_params');
        if (e) this.entreprises = JSON.parse(e);
        if (l) this.licences = JSON.parse(l);
        if (u) this.utilisateurs = JSON.parse(u);
        if (h) this.historiques = JSON.parse(h);
        if (p) this.params = JSON.parse(p);
        this.initDefaultData();
      },

      initDefaultData() {
        if (this.utilisateurs.length === 0) {
          this.utilisateurs = [{ id: 1, username: 'superadmin', password: 'password', fullName: 'Super Administrateur', email: 'superadmin@systeme.com', actif: 1, derniereConnexion: null }];
          this.save();
        }
        if (this.entreprises.length === 0) {
          this.entreprises = [{ id: 1, nom: 'SuperMarche Express SARL', commercial: 'SuperMarche Express', forme: 'SARL', ice: '001234567890123', adresse: '123 Avenue Mohammed V', ville: 'Casablanca', pays: 'Maroc', telephone: '+212 522 123 456', email: 'contact@supermarche.ma', gerant: 'Ahmed Benali', statut: 'active', creeLe: new Date().toISOString() }];
          this.licences = [{ id: 1, entrepriseId: 1, type: 'vitalice', dateDebut: '2024-01-01', dateFin: null, statut: 'active', maxUtilisateurs: 50, maxPointsVente: 10, modules: ['caisse', 'stock', 'rapports', 'facturation'] }];
          this.save();
        }
      }
    };

    // =============================================
    // AUTH
    // =============================================
    function login(username, password) {
      const user = Store.utilisateurs.find(u => u.username === username && u.password === password);
      if (user && user.actif) {
        user.derniereConnexion = new Date().toISOString();
        Store.currentUser = user;
        sessionStorage.setItem('sa_current_user', JSON.stringify(user));
        Store.historiques.unshift({ id: Date.now(), action: 'Connexion reussie', utilisateur: user.fullName, date: new Date().toISOString() });
        Store.save();
        return true;
      }
      return false;
    }

    function logout() {
      if (Store.currentUser) {
        Store.historiques.unshift({ id: Date.now(), action: 'Deconnexion', utilisateur: Store.currentUser.fullName, date: new Date().toISOString() });
        Store.save();
      }
      Store.currentUser = null;
      sessionStorage.removeItem('sa_current_user');
      document.getElementById('login-screen').style.display = 'flex';
      document.getElementById('main-app').style.display = 'none';
    }

    function checkAuth() {
      const saved = sessionStorage.getItem('sa_current_user');
      if (saved) {
        Store.currentUser = JSON.parse(saved);
        return true;
      }
      return false;
    }

    // =============================================
    // UI HELPERS
    // =============================================
    function $(selector) { return document.querySelector(selector); }
    function $$(selector) { return document.querySelectorAll(selector); }

    function toggleSidebar() {
      $('#sidebar').classList.toggle('collapsed');
      $('#header').classList.toggle('collapsed');
      $('#main-content').classList.toggle('sidebar-collapsed');
    }

    function toggleDropdown() {
      $('#user-dropdown').classList.toggle('active');
    }

    function showPage(pageName) {
      $$('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.page === pageName));
      $$('.page').forEach(p => p.classList.toggle('active', p.id === 'page-' + pageName));
      $('#user-dropdown').classList.remove('active');
      if (typeof window['load' + capitalize(pageName)] === 'function') {
        window['load' + capitalize(pageName)]();
      }
    }

    function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function openModal(id) { $('#' + id).classList.add('active'); }
    function closeModal(id) { $('#' + id).classList.remove('active'); }

    function getBadgeClass(status) {
      const map = { active: 'badge-success', inactif: 'badge-danger', suspendu: 'badge-warning', expiree: 'badge-danger', suspendue: 'badge-warning', annulee: 'badge-danger' };
      return map[status] || 'badge-info';
    }

    function formatDate(dateStr) {
      if (!dateStr) return '-';
      return new Date(dateStr).toLocaleDateString('fr-FR');
    }

    function formatDateTime(dateStr) {
      if (!dateStr) return '-';
      return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    // =============================================
    // DASHBOARD
    // =============================================
    function loadDashboard() {
      $('#stat-entreprises').textContent = Store.entreprises.filter(e => e.statut === 'active').length;
      $('#stat-utilisateurs').textContent = Store.entreprises.reduce((sum, e) => sum + 5, 0);
      $('#stat-licences').textContent = Store.licences.filter(l => l.statut === 'active').length;
      const expiring = Store.licences.filter(l => l.statut === 'active').length;
      $('#stat-alertes').textContent = expiring > 0 ? expiring : 0;
    }

    // =============================================
    // ENTREPRISES
    // =============================================
    let entreprisePage = 1;
    const entreprisePerPage = 10;

    function loadEntreprises() {
      const active = Store.entreprises.filter(e => e.statut === 'active').length;
      $('#entreprises-count').textContent = active;
      filterEntreprises();
    }

    function filterEntreprises() {
      const search = $('#entreprise-search')?.value?.toLowerCase() || '';
      const status = $('#entreprise-filter-status')?.value || '';
      let filtered = Store.entreprises.filter(e => {
        const matchSearch = e.nom.toLowerCase().includes(search) || (e.ice || '').includes(search);
        const matchStatus = !status || e.statut === status;
        return matchSearch && matchStatus;
      });
      renderEntreprises(filtered);
    }

    function renderEntreprises(data) {
      const start = (entreprisePage - 1) * entreprisePerPage;
      const paged = data.slice(start, start + entreprisePerPage);
      $('#entreprise-count').textContent = data.length;
      
      if (paged.length === 0) {
        $('#entreprises-table').innerHTML = '<tr><td colspan="7" class="empty-state"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01"></path></svg><h3>Aucune entreprise</h3><p>Aucune entreprise ne correspond aux criteres</p></div></td></tr>';
        return;
      }

      $('#entreprises-table').innerHTML = paged.map(e => `
        <tr>
          <td><strong>${e.nom}</strong><br><span style="color:var(--muted);font-size:12px;">${e.commercial || ''}</span></td>
          <td><code>${e.ice || '-'}</code></td>
          <td>${e.ville || '-'}</td>
          <td>${e.telephone || '-'}</td>
          <td><span class="badge badge-primary">5</span></td>
          <td><span class="badge ${getBadgeClass(e.statut)}">${e.statut}</span></td>
          <td class="action-btns">
            <button class="btn" onclick="editEntreprise(${e.id})" title="Modifier"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
            <button class="btn danger" onclick="deleteEntreprise(${e.id})" title="Supprimer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
          </td>
        </tr>
      `).join('');

      renderPagination('entreprise', data.length, entreprisePage);
    }

    function renderPagination(type, total, current) {
      const container = $('#' + type + '-pagination');
      const perPage = type === 'entreprise' ? entreprisePerPage : 5;
      const pages = Math.ceil(total / perPage);
      if (pages <= 1) { container.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn ${i === current ? 'active' : ''}" onclick="goToPage('${type}', ${i})">${i}</button>`;
      }
      container.innerHTML = html;
    }

    function goToPage(type, page) {
      if (type === 'entreprise') { entreprisePage = page; filterEntreprises(); }
    }

    function openEntrepriseModal(e) {
      $('#entreprise-modal-title').textContent = e ? 'Modifier Entreprise' : 'Nouvelle Entreprise';
      $('#entreprise-id').value = e?.id || '';
      $('#entreprise-nom').value = e?.nom || '';
      $('#entreprise-commercial').value = e?.commercial || '';
      $('#entreprise-forme').value = e?.forme || '';
      $('#entreprise-ice').value = e?.ice || '';
      $('#entreprise-adresse').value = e?.adresse || '';
      $('#entreprise-ville').value = e?.ville || '';
      $('#entreprise-pays').value = e?.pays || 'Maroc';
      $('#entreprise-tel').value = e?.telephone || '';
      $('#entreprise-email').value = e?.email || '';
      $('#entreprise-gerant').value = e?.gerant || '';
      $('#entreprise-statut').value = e?.statut || 'active';
      openModal('entreprise-modal');
    }

    function editEntreprise(id) {
      const e = Store.entreprises.find(x => x.id === id);
      if (e) openEntrepriseModal(e);
    }

    function saveEntreprise() {
      const id = $('#entreprise-id').value;
      const data = {
        nom: $('#entreprise-nom').value.trim(),
        commercial: $('#entreprise-commercial').value.trim(),
        forme: $('#entreprise-forme').value,
        ice: $('#entreprise-ice').value.trim(),
        adresse: $('#entreprise-adresse').value.trim(),
        ville: $('#entreprise-ville').value.trim(),
        pays: $('#entreprise-pays').value.trim(),
        telephone: $('#entreprise-tel').value.trim(),
        email: $('#entreprise-email').value.trim(),
        gerant: $('#entreprise-gerant').value.trim(),
        statut: $('#entreprise-statut').value
      };

      if (!data.nom) { alert('Le nom est requis'); return; }

      if (id) {
        const idx = Store.entreprises.findIndex(x => x.id === parseInt(id));
        if (idx > -1) { Store.entreprises[idx] = { ...Store.entreprises[idx], ...data }; }
      } else {
        data.id = Date.now();
        data.creeLe = new Date().toISOString();
        Store.entreprises.push(data);
        Store.historiques.unshift({ id: Date.now(), action: 'Nouvelle entreprise: ' + data.nom, utilisateur: Store.currentUser?.fullName || 'Systeme', date: new Date().toISOString() });
      }
      Store.save();
      closeModal('entreprise-modal');
      filterEntreprises();
      loadDashboard();
    }

    function deleteEntreprise(id) {
      if (confirm('Supprimer cette entreprise?')) {
        Store.entreprises = Store.entreprises.filter(e => e.id !== id);
        Store.save();
        filterEntreprises();
        loadDashboard();
      }
    }

    // =============================================
    // LICENCES
    // =============================================
    function loadLicences() {
      const vitalice = Store.licences.filter(l => l.type === 'vitalice').length;
      const annuel = Store.licences.filter(l => l.type === 'annuel').length;
      const expirees = Store.licences.filter(l => l.statut === 'expiree').length;
      $('#licences-vitalice').textContent = vitalice;
      $('#licences-annuel').textContent = annuel;
      $('#licences-expirees').textContent = expirees;
      filterLicences();
    }

    function filterLicences() {
      const search = $('#licence-search')?.value?.toLowerCase() || '';
      const type = $('#licence-filter-type')?.value || '';
      const status = $('#licence-filter-status')?.value || '';
      let filtered = Store.licences.filter(l => {
        const ent = Store.entreprises.find(e => e.id === l.entrepriseId);
        const entName = ent?.nom?.toLowerCase() || '';
        const matchSearch = entName.includes(search);
        const matchType = !type || l.type === type;
        const matchStatus = !status || l.statut === status;
        return matchSearch && matchType && matchStatus;
      });
      renderLicences(filtered);
    }

    function renderLicences(data) {
      if (data.length === 0) {
        $('#licences-table').innerHTML = '<tr><td colspan="7" class="empty-state"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg><h3>Aucune licence</h3></div></td></tr>';
        return;
      }
      $('#licences-table').innerHTML = data.map(l => {
        const ent = Store.entreprises.find(e => e.id === l.entrepriseId);
        const typeLabels = { essai: 'Essai', mensuel: 'Mensuel', annuel: 'Annuel', vitalice: 'Vitalice' };
        return `
          <tr>
            <td><strong>${ent?.nom || '-'}</strong></td>
            <td><span class="badge badge-info">${typeLabels[l.type] || l.type}</span></td>
            <td>${formatDate(l.dateDebut)}</td>
            <td>${l.dateFin ? formatDate(l.dateFin) : 'Illimite'}</td>
            <td><span class="badge ${getBadgeClass(l.statut)}">${l.statut}</span></td>
            <td>${(l.modules || []).length} modules</td>
            <td class="action-btns">
              <button class="btn" onclick="editLicence(${l.id})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function openLicenceModal(l) {
      $('#licence-modal-title').textContent = l ? 'Modifier Licence' : 'Nouvelle Licence';
      $('#licence-id').value = l?.id || '';
      populateEntrepriseSelect();
      $('#licence-entreprise').value = l?.entrepriseId || '';
      $('#licence-type').value = l?.type || 'essai';
      $('#licence-debut').value = l?.dateDebut || new Date().toISOString().split('T')[0];
      $('#licence-fin').value = l?.dateFin || '';
      $('#licence-max-users').value = l?.maxUtilisateurs || 10;
      $('#licence-max-pos').value = l?.maxPointsVente || 1;
      $('#licence-statut').value = l?.statut || 'active';
      openModal('licence-modal');
    }

    function populateEntrepriseSelect() {
      const select = $('#licence-entreprise');
      select.innerHTML = '<option value="">Selectionner...</option>' + Store.entreprises.map(e => `<option value="${e.id}">${e.nom}</option>`).join('');
    }

    function editLicence(id) {
      const l = Store.licences.find(x => x.id === id);
      if (l) openLicenceModal(l);
    }

    function saveLicence() {
      const id = $('#licence-id').value;
      const data = {
        entrepriseId: parseInt($('#licence-entreprise').value),
        type: $('#licence-type').value,
        dateDebut: $('#licence-debut').value,
        dateFin: $('#licence-fin').value || null,
        maxUtilisateurs: parseInt($('#licence-max-users').value),
        maxPointsVente: parseInt($('#licence-max-pos').value),
        statut: $('#licence-statut').value,
        modules: ['caisse', 'stock', 'rapports', 'facturation']
      };

      if (!data.entrepriseId) { alert('Selectionnez une entreprise'); return; }
      if (id) {
        const idx = Store.licences.findIndex(x => x.id === parseInt(id));
        if (idx > -1) Store.licences[idx] = data;
      } else {
        data.id = Date.now();
        Store.licences.push(data);
        Store.historiques.unshift({ id: Date.now(), action: 'Nouvelle licence creee', utilisateur: Store.currentUser?.fullName || 'Systeme', date: new Date().toISOString() });
      }
      Store.save();
      closeModal('licence-modal');
      filterLicences();
      loadLicences();
    }

    // =============================================
    // UTILISATEURS
    // =============================================
    function loadUtilisateurs() {
      renderUtilisateurs();
    }

    function renderUtilisateurs() {
      if (Store.utilisateurs.length === 0) {
        $('#utilisateurs-table').innerHTML = '<tr><td colspan="5" class="empty-state"><div class="empty-state"><h3>Aucun utilisateur</h3></div></td></tr>';
        return;
      }
      $('#utilisateurs-table').innerHTML = Store.utilisateurs.map(u => `
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:12px;">
              <div class="user-avatar" style="width:40px;height:40px;">${u.fullName.charAt(0)}</div>
              <div><strong>${u.fullName}</strong><br><span style="color:var(--muted);font-size:12px;">@${u.username}</span></div>
            </div>
          </td>
          <td>${u.email}</td>
          <td>${u.derniereConnexion ? formatDateTime(u.derniereConnexion) : 'Jamais'}</td>
          <td><span class="badge ${u.actif ? 'badge-success' : 'badge-danger'}">${u.actif ? 'Actif' : 'Inactif'}</span></td>
          <td class="action-btns">
            <button class="btn" onclick="editUtilisateur(${u.id})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
            ${u.id !== Store.currentUser?.id ? `<button class="btn danger" onclick="deleteUtilisateur(${u.id})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>` : ''}
          </td>
        </tr>
      `).join('');
    }

    function openUtilisateurModal(u) {
      $('#utilisateur-modal-title').textContent = u ? 'Modifier Utilisateur' : 'Nouvel Utilisateur';
      $('#utilisateur-id').value = u?.id || '';
      $('#utilisateur-nom').value = u?.fullName || '';
      $('#utilisateur-username').value = u?.username || '';
      $('#utilisateur-email').value = u?.email || '';
      $('#utilisateur-password').value = '';
      $('#utilisateur-password-label').textContent = u ? 'Nouveau mot de passe' : 'Mot de passe *';
      $('#utilisateur-password-hint').style.display = u ? 'block' : 'none';
      $('#utilisateur-password').required = !u;
      $('#utilisateur-actif').value = u?.actif ? '1' : '0';
      openModal('utilisateur-modal');
    }

    function editUtilisateur(id) {
      const u = Store.utilisateurs.find(x => x.id === id);
      if (u) openUtilisateurModal(u);
    }

    function saveUtilisateur() {
      const id = $('#utilisateur-id').value;
      const data = {
        fullName: $('#utilisateur-nom').value.trim(),
        username: $('#utilisateur-username').value.trim(),
        email: $('#utilisateur-email').value.trim(),
        actif: parseInt($('#utilisateur-actif').value)
      };
      const password = $('#utilisateur-password').value;

      if (!data.fullName || !data.username || !data.email) { alert('Tous les champs sont requis'); return; }
      if (!id && !password) { alert('Le mot de passe est requis'); return; }

      if (id) {
        const idx = Store.utilisateurs.findIndex(x => x.id === parseInt(id));
        if (idx > -1) {
          Store.utilisateurs[idx] = { ...Store.utilisateurs[idx], ...data };
          if (password) Store.utilisateurs[idx].password = password;
        }
      } else {
        data.id = Date.now();
        data.password = password;
        data.derniereConnexion = null;
        Store.utilisateurs.push(data);
        Store.historiques.unshift({ id: Date.now(), action: 'Nouvel utilisateur: ' + data.fullName, utilisateur: Store.currentUser?.fullName || 'Systeme', date: new Date().toISOString() });
      }
      Store.save();
      closeModal('utilisateur-modal');
      renderUtilisateurs();
    }

    function deleteUtilisateur(id) {
      if (id === Store.currentUser?.id) { alert('Vous ne pouvez pas supprimer votre propre compte'); return; }
      if (confirm('Supprimer cet utilisateur?')) {
        Store.utilisateurs = Store.utilisateurs.filter(u => u.id !== id);
        Store.save();
        renderUtilisateurs();
      }
    }

    // =============================================
    // HISTORIQUE
    // =============================================
    function loadHistorique() {
      if (Store.historiques.length === 0) {
        $('#historique-list').innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg><h3>Aucune activite</h3></div>';
        return;
      }
      $('#historique-list').innerHTML = Store.historiques.map(h => `
        <div class="activity-item">
          <div class="activity-content">
            <h4>${h.action}</h4>
            <p>Par: ${h.utilisateur}</p>
          </div>
          <div class="activity-meta">
            <div class="time">${formatDateTime(h.date)}</div>
          </div>
        </div>
      `).join('');
    }

    // =============================================
    // PARAMETRES
    // =============================================
    function loadParametres() {
      $('#param-devise').value = Store.params.devise;
      $('#param-tva').value = Store.params.tva;
      $('#param-version').value = Store.params.version;
    }

    function saveParams() {
      Store.params.devise = $('#param-devise').value;
      Store.params.tva = parseFloat($('#param-tva').value);
      Store.save();
      alert('Parametres enregistres');
    }

    function exportData() {
      const data = { entreprises: Store.entreprises, licences: Store.licences, utilisateurs: Store.utilisateurs };
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'pos_export_' + new Date().toISOString().split('T')[0] + '.json';
      a.click();
    }

    // =============================================
    // EVENT LISTENERS
    // =============================================
    document.getElementById('login-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const username = $('#login-username').value;
      const password = $('#login-password').value;
      if (login(username, password)) {
        $('#login-screen').style.display = 'none';
        $('#main-app').style.display = 'block';
        $('#user-name').textContent = Store.currentUser.fullName;
        $('#user-avatar').textContent = Store.currentUser.fullName.charAt(0);
        loadDashboard();
      } else {
        $('#login-alert').innerHTML = '<div class="alert alert-error">Nom d\'utilisateur ou mot de passe incorrect</div>';
      }
    });

    $$('.nav-item').forEach(item => {
      item.addEventListener('click', () => {
        if (item.dataset.page) showPage(item.dataset.page);
      });
    });

    document.addEventListener('click', function(e) {
      if (!e.target.closest('#user-dropdown')) $('#user-dropdown').classList.remove('active');
    });

    // =============================================
    // INIT
    // =============================================
    Store.load();
    if (checkAuth()) {
      $('#login-screen').style.display = 'none';
      $('#main-app').style.display = 'block';
      $('#user-name').textContent = Store.currentUser.fullName;
      $('#user-avatar').textContent = Store.currentUser.fullName.charAt(0);
      loadDashboard();
    }
  </script>
</body>
</html>
