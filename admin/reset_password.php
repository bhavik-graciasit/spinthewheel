<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
if (isAdminLoggedIn()) redirect(APP_URL . '/admin/dashboard.php');

$token = trim(get('token'));
$user  = $token ? validateResetToken($token) : null;
$done  = false;
$error = '';

if (!$user && $token) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($user && isPost()) {
    verifyCsrf();
    $pw1   = post('password');
    $pw2   = post('confirm');
    $pwErr = validatePassword($pw1);
    if ($pwErr)          $error = implode(' ', $pwErr);
    elseif ($pw1 !== $pw2) $error = 'Passwords do not match.';
    else {
        consumeResetToken($user['id'], password_hash($pw1, PASSWORD_BCRYPT, ['cost' => 12]));
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reset Password — <?= APP_NAME ?></title>
<style>
:root{--bg:#0d1117;--bg2:#161b22;--bg3:#21262d;--border:#30363d;--accent:#6366f1;--accent2:#818cf8;--danger:#ef4444;--t1:#f0f6fc;--t2:#8b949e}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;align-items:center;justify-content:center}
.wrap{width:380px;padding:20px}
.logo{text-align:center;margin-bottom:28px}.logo-icon{font-size:40px;display:block;margin-bottom:8px}
.logo-name{font-size:20px;font-weight:700}.logo-sub{font-size:13px;color:var(--t2);margin-top:3px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:28px}
h2{font-size:17px;font-weight:600;margin-bottom:8px}p{font-size:13px;color:var(--t2);margin-bottom:18px}
.fg{margin-bottom:14px}.fg label{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:6px}
.fg small{display:block;font-size:11px;color:var(--t2);margin-top:4px;opacity:.7}
input{background:var(--bg3);border:1px solid var(--border);color:var(--t1);border-radius:8px;padding:10px 14px;font-size:14px;width:100%;outline:none;transition:border .2s}
input:focus{border-color:var(--accent)}
.btn-submit{width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:600;cursor:pointer;margin-top:6px}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px}
.success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;padding:12px 16px;font-size:13px}
.back{text-align:center;margin-top:14px;font-size:13px;color:var(--t2)}.back a{color:var(--accent2)}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <span class="logo-icon">🎡</span>
    <div class="logo-name"><?= APP_NAME ?></div>
    <div class="logo-sub">Set New Password</div>
  </div>
  <div class="card">
    <?php if ($done): ?>
      <div class="success">✅ Password updated successfully!</div>
      <div class="back" style="margin-top:16px"><a href="<?= APP_URL ?>/admin/login.php">Sign in with new password →</a></div>
    <?php elseif (!$user): ?>
      <?php if ($error): ?><div class="error">⚠️ <?= e($error) ?></div><?php endif; ?>
      <p>Request a new reset link below.</p>
      <a href="<?= APP_URL ?>/admin/forgot_password.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:11px">Request New Link</a>
    <?php else: ?>
      <h2>Set a new password</h2>
      <p>For account: <strong><?= e($user['username']) ?></strong></p>
      <?php if ($error): ?><div class="error">⚠️ <?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="fg">
          <label>New Password</label>
          <input type="password" name="password" autocomplete="new-password" required>
          <small>Min 8 chars, 1 uppercase, 1 number</small>
        </div>
        <div class="fg">
          <label>Confirm Password</label>
          <input type="password" name="confirm" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-submit">Set Password</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$done): ?>
  <div class="back"><a href="<?= APP_URL ?>/admin/login.php">← Back to sign in</a></div>
  <?php endif; ?>
</div>
</body>
</html>
