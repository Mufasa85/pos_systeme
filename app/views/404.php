<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - Page non trouvée</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #0D0552, #1a0a6e, #30E9FE);
      color: #fff;
    }
    .container {
      text-align: center;
      padding: 2rem;
      background: url("/assets/img/pattern_h.png") center / cover no-repeat;
    }
    .error-code {
      font-size: 8rem;
      font-weight: 700;
      line-height: 1;
      background: linear-gradient(135deg, #30E9FE, #00e5ff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .error-title {
      font-size: 1.5rem;
      margin: 1rem 0 0.5rem;
      font-weight: 600;
    }
    .error-message {
      color: rgba(255,255,255,0.7);
      margin-bottom: 2rem;
      font-size: 0.95rem;
    }
    .btn-home {
      display: inline-block;
      padding: 0.75rem 2rem;
      background: linear-gradient(135deg, #0D0552, #00e5ff, #30E9FE);
      background-size: 200% 200%;
      color: #fff;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 600;
      transition: transform 0.2s;
    }
    .btn-home:hover { transform: translateY(-2px); }
  </style>
</head>
<body>
  <div class="container">
    <div class="error-code">404</div>
    <h1 class="error-title">Page non trouvée</h1>
    <p class="error-message">La page que vous cherchez n'existe pas ou a été déplacée.</p>
    <a href="/dashboard" class="btn-home">Retour au tableau de bord</a>
  </div>
</body>
</html>
