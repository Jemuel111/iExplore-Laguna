<?php
// ============================================================
// IEXPLORE LAGUNA — Friendly error page
// includes/error-page.php
// Shown instead of a raw PHP error / blank screen when
// DEBUG_MODE is false and something throws. The real error
// details go to logs/php-error.log, never to the visitor.
// ============================================================
$appName = defined('APP_NAME') ? APP_NAME : 'IExplore Laguna';
$appUrl  = defined('APP_URL')  ? APP_URL  : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Something went wrong — <?= htmlspecialchars($appName) ?></title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #1b4332, #2d6a4f);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      padding: 1.5rem;
    }
    .card {
      background: #fff; border-radius: 16px; padding: 2.5rem 2rem;
      max-width: 420px; width: 100%; text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }
    .icon { font-size: 2.75rem; margin-bottom: .5rem; }
    h1 {
      font-size: 1.3rem; margin: .25rem 0 .5rem;
      color: #1b4332; font-family: Georgia, serif;
    }
    p { color: #555; font-size: .92rem; line-height: 1.6; margin: 0 0 1.5rem; }
    a.btn {
      display: inline-block; background: #2d6a4f; color: #fff;
      text-decoration: none; padding: .65rem 1.5rem; border-radius: 30px;
      font-size: .9rem; font-weight: 600;
    }
    a.btn:hover { background: #1b4332; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🌴</div>
    <h1>Something went wrong on our end</h1>
    <p>
      Sorry about that — the page hit an unexpected error. It's been logged
      and we'll take a look. In the meantime, try heading back home.
    </p>
    <a class="btn" href="<?= htmlspecialchars($appUrl) ?>">Back to Home</a>
  </div>
</body>
</html>
