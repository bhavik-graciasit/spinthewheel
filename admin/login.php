<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
if (isAdminLoggedIn()) redirect(APP_URL . '/admin/dashboard.php');

$error = '';
if (isPost()) {
    verifyCsrf();
    $username = trim(post('username'));
    $password = post('password');

    if (!$username || !$password) {
        $error = 'Please enter username and password.';
    } else {
        $result = adminLogin($username, $password);
        if ($result['ok']) {
            redirect(APP_URL . '/admin/dashboard.php');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?= APP_NAME ?></title>
<style>
:root{--bg:#0d1117;--bg2:#161b22;--bg3:#21262d;--border:#30363d;--accent:#6366f1;--accent2:#818cf8;--danger:#ef4444;--t1:#f0f6fc;--t2:#8b949e;--t3:#484f58}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;align-items:center;justify-content:center}
.wrap{width:380px;padding:20px}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{font-size:42px;display:block;margin-bottom:8px}
.logo-name{font-size:22px;font-weight:700;color:var(--t1)}
.logo-sub{font-size:13px;color:var(--t2);margin-top:3px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:28px}
.card h2{font-size:17px;font-weight:600;margin-bottom:20px}
.fg{margin-bottom:14px}
.fg label{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:6px}
input{background:var(--bg3);border:1px solid var(--border);color:var(--t1);border-radius:8px;padding:10px 14px;font-size:14px;width:100%;outline:none;transition:border .2s}
input:focus{border-color:var(--accent)}
.btn-submit{width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s;margin-top:6px}
.btn-submit:hover{background:var(--accent2)}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px}
.forgot{text-align:center;margin-top:14px;font-size:13px;color:var(--t2)}
.forgot a{color:var(--accent2)}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <span class="logo-icon">🎡</span>
    <div class="logo-name"><?= APP_NAME ?></div>
    <div class="logo-sub">Admin Portal</div>
  </div>

  <div class="card">
    <h2>Sign in to your account</h2>

    <?php if ($error): ?>
      <div class="error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="fg">
        <label>Username</label>
        <input type="text" name="username" value="<?= e(post('username')) ?>" autocomplete="username" autofocus required>
      </div>
      <div class="fg">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-submit">Sign In</button>
    </form>
  </div>

  <div class="forgot">
    <a href="<?= APP_URL ?>/admin/forgot_password.php">Forgot password?</a>
  </div>
</div>
</body>
</html>
