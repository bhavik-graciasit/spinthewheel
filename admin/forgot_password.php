<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
if (isAdminLoggedIn()) redirect(APP_URL . '/admin/dashboard.php');

$sent = false;
$error = '';

if (isPost()) {
    verifyCsrf();
    $email = trim(post('email'));
    if (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always show "sent" to prevent email enumeration
        generateResetToken($email);
        $sent = true;
        // In production: send email with link APP_URL/admin/reset_password.php?token=TOKEN
        // For now the token is shown in a dev hint below
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password — <?= APP_NAME ?></title>
<style>
:root{--bg:#0d1117;--bg2:#161b22;--bg3:#21262d;--border:#30363d;--accent:#6366f1;--accent2:#818cf8;--danger:#ef4444;--t1:#f0f6fc;--t2:#8b949e}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;align-items:center;justify-content:center}
.wrap{width:380px;padding:20px}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{font-size:40px;display:block;margin-bottom:8px}
.logo-name{font-size:20px;font-weight:700}.logo-sub{font-size:13px;color:var(--t2);margin-top:3px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:28px}
h2{font-size:17px;font-weight:600;margin-bottom:8px}
p{font-size:13px;color:var(--t2);margin-bottom:18px}
.fg{margin-bottom:14px}.fg label{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:6px}
input{background:var(--bg3);border:1px solid var(--border);color:var(--t1);border-radius:8px;padding:10px 14px;font-size:14px;width:100%;outline:none;transition:border .2s}
input:focus{border-color:var(--accent)}
.btn-submit{width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:600;cursor:pointer;margin-top:6px}
.btn-submit:hover{background:var(--accent2)}
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
    <div class="logo-sub">Password Reset</div>
  </div>
  <div class="card">
    <?php if ($sent): ?>
      <div class="success">
        ✅ If that email is in our system, a reset link has been sent.<br>
        <span style="font-size:12px;opacity:.8">Check your inbox (and spam folder).</span>
      </div>
      <?php if (DEBUG_MODE):
        // Dev helper: show token directly
        require_once __DIR__ . '/../includes/Database.php';
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT reset_token FROM admin_users WHERE email=? AND reset_token IS NOT NULL", [post('email')]);
        if ($row):
      ?>
        <div style="margin-top:12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px;font-size:11px;color:var(--t2)">
          🔧 Dev mode reset link:<br>
          <a href="<?= APP_URL ?>/admin/reset_password.php?token=<?= $row['reset_token'] ?>" style="color:var(--accent2);word-break:break-all">
            <?= APP_URL ?>/admin/reset_password.php?token=<?= $row['reset_token'] ?>
          </a>
        </div>
      <?php endif; endif; ?>
    <?php else: ?>
      <h2>Forgot your password?</h2>
      <p>Enter your account email and we'll send you a reset link.</p>
      <?php if ($error): ?><div class="error">⚠️ <?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="fg">
          <label>Email address</label>
          <input type="email" name="email" value="<?= e(post('email')) ?>" autofocus required>
        </div>
        <button type="submit" class="btn-submit">Send Reset Link</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="back"><a href="<?= APP_URL ?>/admin/login.php">← Back to sign in</a></div>
</div>
</body>
</html>
