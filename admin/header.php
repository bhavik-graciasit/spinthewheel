<?php
/**
 * SpinWheel Pro V2 — Admin Header / Sidebar
 * Expects: $pageTitle, $activePage already set by including page.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$db = Database::getInstance();

// ── Sidebar badge counts ──────────────────────────────
$totalProjects    = (int)$db->fetchValue("SELECT COUNT(*) FROM projects");
$totalParticipants= (int)$db->fetchValue("SELECT COUNT(*) FROM participants");

// Apply org scope for badge counts
[$scopeSql, $scopeParams] = orgScopeFilter('p');
if ($scopeSql) {
    $totalProjects = (int)$db->fetchValue(
        "SELECT COUNT(*) FROM projects p WHERE 1=1 $scopeSql", $scopeParams
    );
}

$flashes = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
<style>
:root {
  --bg:       #0d1117;
  --bg2:      #161b22;
  --bg3:      #21262d;
  --border:   #30363d;
  --accent:   #6366f1;
  --accent2:  #818cf8;
  --danger:   #ef4444;
  --success:  #22c55e;
  --warning:  #f59e0b;
  --t1:       #f0f6fc;
  --t2:       #8b949e;
  --t3:       #484f58;
  --sidebar-w: 220px;
  --radius:   8px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;font-size:14px}
a{color:var(--accent2);text-decoration:none}
a:hover{text-decoration:underline}
input,select,textarea{background:var(--bg3);border:1px solid var(--border);color:var(--t1);border-radius:var(--radius);padding:8px 12px;font-size:14px;width:100%;outline:none;transition:border .2s}
input:focus,select:focus,textarea:focus{border-color:var(--accent)}
select option{background:var(--bg2);color:var(--t1)}
button,.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius);border:none;cursor:pointer;font-size:13px;font-weight:500;transition:all .15s;text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2);text-decoration:none}
.btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{opacity:.9;text-decoration:none}
.btn-ghost{background:var(--bg3);color:var(--t1);border:1px solid var(--border)}.btn-ghost:hover{border-color:var(--accent);text-decoration:none}
.btn-sm{padding:5px 10px;font-size:12px}
/* Sidebar */
#sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100}
.sb-logo{padding:18px 16px;border-bottom:1px solid var(--border);font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px;color:var(--t1)}
.sb-logo span{font-size:20px}
.sb-section{padding:14px 16px 4px;font-size:10px;font-weight:700;letter-spacing:.08em;color:var(--t3);text-transform:uppercase}
.sb-item{display:flex;align-items:center;gap:9px;padding:7px 16px;color:var(--t2);border-radius:0;transition:all .15s;cursor:pointer;text-decoration:none;font-size:13px}
.sb-item:hover{background:var(--bg3);color:var(--t1);text-decoration:none}
.sb-item.active{background:rgba(99,102,241,.15);color:var(--accent2);border-right:2px solid var(--accent)}
.sb-item .icon{font-size:15px;width:18px;text-align:center}
.sb-badge{margin-left:auto;background:var(--bg3);border:1px solid var(--border);color:var(--t2);border-radius:20px;padding:1px 7px;font-size:10px}
.sb-footer{margin-top:auto;padding:14px 16px;border-top:1px solid var(--border)}
.sb-user{display:flex;align-items:center;gap:9px}
.sb-avatar{width:30px;height:30px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sb-user-info{flex:1;min-width:0}
.sb-user-name{font-size:12px;font-weight:600;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-role-badge{font-size:10px;color:var(--t3);display:flex;align-items:center;gap:4px;margin-top:1px}
/* Main */
#main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-size:17px;font-weight:600}
.topbar-actions{display:flex;align-items:center;gap:10px}
.content{padding:28px;flex:1}
/* Cards */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.card-hd{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-weight:600;font-size:14px}
.card-body{padding:18px}
/* Table */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 14px;text-align:left;font-size:13px}
th{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--t2);border-bottom:1px solid var(--border)}
tr:not(:last-child) td{border-bottom:1px solid var(--border)}
tr:hover td{background:rgba(255,255,255,.02)}
/* Badge */
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.badge-green{background:rgba(34,197,94,.15);color:var(--success)}
.badge-red{background:rgba(239,68,68,.15);color:var(--danger)}
.badge-yellow{background:rgba(245,158,11,.15);color:var(--warning)}
.badge-blue{background:rgba(99,102,241,.15);color:var(--accent2)}
.badge-gray{background:var(--bg3);color:var(--t2)}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:var(--bg2);border:1px solid var(--border);border-radius:12px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto}
.modal-hd{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-weight:600;font-size:15px}
.modal-close{background:none;border:none;color:var(--t2);cursor:pointer;font-size:18px;padding:0;line-height:1}
.modal-close:hover{color:var(--t1)}
.modal-body{padding:20px}
/* Form */
.fg{margin-bottom:14px}
.fg label{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:6px}
.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
/* Alerts */
.alert{padding:10px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:var(--success)}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--danger)}
.alert-info{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);color:var(--accent2)}
/* Stat grid */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px}
.stat-num{font-size:26px;font-weight:700;color:var(--t1)}
.stat-label{font-size:11px;color:var(--t2);margin-top:2px;text-transform:uppercase;letter-spacing:.05em}
.stat-sub{font-size:11px;color:var(--t3);margin-top:4px}
/* Responsive */
@media(max-width:700px){
  #sidebar{transform:translateX(-100%)}
  #main{margin-left:0}
}
/* Role colors */
.role-superadmin{color:#f59e0b}
.role-admin{color:#6366f1}
.role-manager{color:#22c55e}
.role-viewer{color:#8b949e}
</style>
</head>
<body>

<nav id="sidebar">
  <div class="sb-logo"><span>🎡</span><?= APP_NAME ?></div>

  <?php if (currentUserCan('dashboard.view')): ?>
  <div class="sb-section">Main</div>
  <a href="<?= APP_URL ?>/admin/dashboard.php" class="sb-item <?= $activePage==='dashboard'?'active':'' ?>">
    <span class="icon">📊</span>Dashboard
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('projects.view')): ?>
  <a href="<?= APP_URL ?>/admin/projects.php" class="sb-item <?= $activePage==='projects'?'active':'' ?>">
    <span class="icon">🎯</span>Projects
    <?php if ($totalProjects): ?>
      <span class="sb-badge"><?= $totalProjects ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('organisation.view')): ?>
  <a href="<?= APP_URL ?>/admin/organisation.php" class="sb-item <?= $activePage==='organisation'?'active':'' ?>">
    <span class="icon">🏢</span>Organisation
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('participants.view')): ?>
  <div class="sb-section">Data</div>
  <a href="<?= APP_URL ?>/admin/participants.php" class="sb-item <?= $activePage==='participants'?'active':'' ?>">
    <span class="icon">👥</span>Participants
    <?php if ($totalParticipants): ?>
      <span class="sb-badge"><?= number_format($totalParticipants) ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('export.csv')): ?>
  <a href="<?= APP_URL ?>/admin/export.php" class="sb-item <?= $activePage==='export'?'active':'' ?>">
    <span class="icon">📤</span>Export
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('users.view')): ?>
  <div class="sb-section">Access</div>
  <a href="<?= APP_URL ?>/admin/users.php" class="sb-item <?= $activePage==='users'?'active':'' ?>">
    <span class="icon">👤</span>Users
  </a>
  <?php endif; ?>

  <?php if (currentUserCan('roles.view')): ?>
  <a href="<?= APP_URL ?>/admin/roles.php" class="sb-item <?= $activePage==='roles'?'active':'' ?>">
    <span class="icon">🔑</span>Roles
  </a>
  <?php endif; ?>

  <div class="sb-section">Account</div>
  <a href="<?= APP_URL ?>/admin/profile.php" class="sb-item <?= $activePage==='profile'?'active':'' ?>">
    <span class="icon">👤</span>Profile
  </a>
  <?php if (currentUserCan('settings.view')): ?>
  <a href="<?= APP_URL ?>/admin/settings.php" class="sb-item <?= $activePage==='settings'?'active':'' ?>">
    <span class="icon">⚙️</span>Settings
  </a>
  <?php endif; ?>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr(currentUserName(), 0, 1)) ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?= e(currentUserName()) ?></div>
        <div class="sb-role-badge">
          <span class="role-<?= currentUserRole() ?>"><?= e(currentUserRoleName()) ?></span>
        </div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/admin/logout.php" style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t2);margin-top:10px">
      🚪 Sign out
    </a>
  </div>
</nav>

<div id="main">
  <div class="topbar">
    <h1><?= e($pageTitle ?? 'Admin') ?></h1>
    <div class="topbar-actions">
      <?php if (!currentUserHasFullScope()): ?>
        <span style="font-size:11px;color:var(--warning);background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);padding:3px 10px;border-radius:20px">
          🔒 Scoped View
        </span>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/profile.php" style="font-size:12px;color:var(--t2)"><?= e(currentUserName()) ?></a>
    </div>
  </div>

  <div class="content">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : ($f['type'] === 'error' ? 'error' : 'info') ?>">
        <?= e($f['msg']) ?>
      </div>
    <?php endforeach; ?>
