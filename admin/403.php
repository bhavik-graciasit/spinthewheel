<?php
/**
 * SpinWheel Pro V2 — 403 Forbidden
 * Shown when a logged-in user lacks permission for a page/action.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

http_response_code(403);

// If called via requirePermission() the user IS logged in — show styled page.
// If somehow hit directly without a session, just redirect to login.
startSecureSession();
$loggedIn = isAdminLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Denied — <?= APP_NAME ?></title>
<style>
:root{--bg:#0d1117;--bg2:#161b22;--bg3:#21262d;--border:#30363d;--accent:#6366f1;--accent2:#818cf8;--danger:#ef4444;--t1:#f0f6fc;--t2:#8b949e;--t3:#484f58}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{text-align:center;max-width:440px}
.code{font-size:96px;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--danger),#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:12px}
.title{font-size:22px;font-weight:700;margin-bottom:10px}
.desc{font-size:14px;color:var(--t2);line-height:1.6;margin-bottom:28px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:24px;text-align:left}
.card-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}
.card-row:last-child{border-bottom:none}
.card-label{color:var(--t2);width:110px;flex-shrink:0}
.card-val{color:var(--t1)}
.role-badge{display:inline-flex;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(99,102,241,.15);color:var(--accent2)}
.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:500;text-decoration:none;transition:all .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2)}
.btn-ghost{background:var(--bg3);color:var(--t1);border:1px solid var(--border)}.btn-ghost:hover{border-color:var(--accent)}
</style>
</head>
<body>
<div class="wrap">
  <div class="code">403</div>
  <div class="title">Access Denied</div>
  <div class="desc">
    You don't have permission to access this page or perform this action.
    Contact a Super Admin if you think this is a mistake.
  </div>

  <?php if ($loggedIn): ?>
  <div class="card">
    <div class="card-row">
      <span class="card-label">Signed in as</span>
      <span class="card-val"><?= e(currentUserName()) ?></span>
    </div>
    <div class="card-row">
      <span class="card-label">Your role</span>
      <span class="card-val">
        <span class="role-badge role-<?= currentUserRole() ?>"><?= e(currentUserRoleName()) ?></span>
      </span>
    </div>
    <div class="card-row">
      <span class="card-label">Requested</span>
      <span class="card-val" style="font-family:monospace;font-size:12px;color:var(--t2);word-break:break-all">
        <?= e($_SERVER['REQUEST_URI'] ?? '—') ?>
      </span>
    </div>
  </div>

  <div class="actions">
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
    <a href="javascript:history.back()" class="btn btn-ghost">Go Back</a>
  </div>

  <?php else: ?>
  <div class="actions">
    <a href="<?= APP_URL ?>/admin/login.php" class="btn btn-primary">Sign In</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
