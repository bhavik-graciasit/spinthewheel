<?php
require_once __DIR__ . '/../includes/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><title>Campaign Not Found — <?= APP_NAME ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#04060c;color:#e2e8f0;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
h1{font-size:48px;margin-bottom:10px;color:#6366f1}
p{color:#64748b;margin-bottom:24px;font-size:15px}
a{display:inline-block;padding:10px 28px;background:#6366f1;color:#fff;border-radius:9px;font-weight:600;text-decoration:none}
</style>
</head><body>
<div>
  <div style="font-size:64px;margin-bottom:16px">🎡</div>
  <h1>404</h1>
  <p>This campaign link is invalid or has expired.<br>Please contact the organiser for the correct URL.</p>
  <a href="<?= APP_URL ?>">Go Home</a>
</div>
</body></html>
