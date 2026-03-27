<?php
$pageTitle  = 'Roles';
$activePage = 'roles';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('roles.view');

$db    = Database::getInstance();
$roles = $db->fetchAll("SELECT r.*, (SELECT COUNT(*) FROM admin_users u WHERE u.role_id = r.id) AS user_count FROM admin_roles r ORDER BY r.id");

require_once __DIR__ . '/header.php';

// Permissions grouped for display
$permGroups = [
    'Dashboard' => ['dashboard.view'],
    'Projects'  => ['projects.view','projects.create','projects.edit','projects.delete'],
    'Participants' => ['participants.view','participants.delete'],
    'Export'    => ['export.csv','export.json'],
    'Organisation' => ['organisation.view','organisation.manage'],
    'Users'     => ['users.view','users.create','users.edit','users.delete'],
    'Roles'     => ['roles.view','roles.manage'],
    'Settings'  => ['settings.view','settings.edit'],
];
$perms = PERMISSIONS;
$weights = ROLE_WEIGHTS;

function hasPermission(string $role, string $permission): bool {
    global $perms, $weights;
    if ($role === ROLE_SUPERADMIN) return true;
    if (!isset($perms[$permission])) return false;
    $userW = $weights[$role]                  ?? 0;
    $reqW  = $weights[$perms[$permission]]    ?? 99;
    return $userW >= $reqW;
}
?>

<!-- Role cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px">
  <?php foreach ($roles as $r): ?>
  <div class="card" style="border-color:<?= $r['slug']==='superadmin'?'#f59e0b':($r['slug']==='admin'?'#6366f1':($r['slug']==='manager'?'#22c55e':'var(--border)')) ?>40">
    <div class="card-body">
      <div class="role-<?= e($r['slug']) ?>" style="font-size:15px;font-weight:700;text-transform:capitalize"><?= e($r['name']) ?></div>
      <div style="font-size:12px;color:var(--t2);margin-top:4px"><?= e($r['description'] ?? '') ?></div>
      <div style="font-size:11px;color:var(--t3);margin-top:10px"><?= $r['user_count'] ?> user<?= $r['user_count']!=1?'s':'' ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Permissions matrix -->
<div class="card">
  <div class="card-hd"><span class="card-title">Permissions Matrix</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="min-width:200px">Permission</th>
          <?php foreach ($roles as $r): ?>
            <th style="text-align:center" class="role-<?= e($r['slug']) ?>"><?= e($r['name']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($permGroups as $group => $permKeys): ?>
        <tr>
          <td colspan="<?= count($roles)+1 ?>"
            style="background:var(--bg3);font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--t3);padding:6px 14px">
            <?= e($group) ?>
          </td>
        </tr>
        <?php foreach ($permKeys as $pkey): ?>
        <tr>
          <td style="color:var(--t2);font-family:monospace;font-size:12px"><?= e($pkey) ?></td>
          <?php foreach ($roles as $r): ?>
          <td style="text-align:center">
            <?php if (hasPermission($r['slug'], $pkey)): ?>
              <span style="color:var(--success)">✓</span>
            <?php else: ?>
              <span style="color:var(--t3)">—</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
