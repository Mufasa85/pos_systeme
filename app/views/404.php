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
      background: #0D0552;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    body::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      top: -150px; right: -100px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(48,233,254,0.5) 0%, rgba(0,229,255,0.15) 50%, transparent 70%);
      filter: blur(50px);
      animation: orbFloat1 5s ease-in-out infinite;
    }
    body::after {
      content: '';
      position: absolute;
      width: 550px; height: 550px;
      bottom: -150px; left: -100px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(26,10,110,0.9) 0%, rgba(0,229,255,0.3) 50%, transparent 70%);
      filter: blur(50px);
      animation: orbFloat2 6s ease-in-out infinite;
    }
    .bg-pattern {
      position: absolute; inset: 0;
      background: url("/assets/img/pattern_h.png") center / cover no-repeat;
      opacity: 0.35; z-index: 0;
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
    .container {
      text-align: center;
      padding: 2rem;
      position: relative;
      z-index: 1;
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
      background: linear-gradient(135deg, #0D0552, #0891B2, #30E9FE);
      color: #fff;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 600;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-home:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(48,233,254,0.3);
    }
  </style>
</head>
<body>
  <div class="bg-pattern"></div>
  <div class="container">
    <div class="error-code">404</div>
    <h1 class="error-title">Page non trouvée</h1>
    <p class="error-message">La page que vous cherchez n'existe pas ou a été déplacée.</p>
    <a href="/dashboard" class="btn-home">Retour au tableau de bord</a>
  </div>
</body>
</html>
