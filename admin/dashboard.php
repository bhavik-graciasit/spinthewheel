<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('dashboard.view');

$db = Database::getInstance();

// ── Org scope filter ──────────────────────────────────
[$scopeSql, $scopeParams] = orgScopeFilter('p');

// ── Group filter from URL ─────────────────────────────
$filterGroupId = (int)(get('group') ?: 0);
$filterGroupIds = [];
if ($filterGroupId) {
    $filterGroupIds = getDescendantGroupIds($filterGroupId);
}

// Build combined filter
function buildFilter(string $alias, string $scopeSql, array $scopeParams, array $filterGroupIds): array {
    $sql    = "WHERE 1=1 $scopeSql";
    $params = $scopeParams;
    if ($filterGroupIds) {
        $ph    = implode(',', array_fill(0, count($filterGroupIds), '?'));
        $sql  .= " AND {$alias}.group_id IN ({$ph})";
        $params = array_merge($params, $filterGroupIds);
    }
    return [$sql, $params];
}

[$where, $whereParams] = buildFilter('p', $scopeSql, $scopeParams, $filterGroupIds);

// ── Stats ─────────────────────────────────────────────
$totalProjects    = (int)$db->fetchValue("SELECT COUNT(*) FROM projects p $where", $whereParams);
$activeProjects   = (int)$db->fetchValue("SELECT COUNT(*) FROM projects p $where AND p.status='active'", $whereParams);

// Participants filtered to same projects
$participantWhere = "WHERE 1=1";
$participantParams = [];
if ($scopeSql || $filterGroupIds) {
    $projIds = $db->fetchAll("SELECT id FROM projects p $where", $whereParams);
    $pids    = array_column($projIds, 'id');
    if ($pids) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $participantWhere  = "WHERE pt.project_id IN ({$ph})";
        $participantParams = $pids;
    } else {
        $participantWhere  = "WHERE 1=0";
    }
}

$totalSpins    = (int)$db->fetchValue("SELECT COUNT(*) FROM participants pt $participantWhere", $participantParams);
$todaySpins    = (int)$db->fetchValue(
    "SELECT COUNT(*) FROM participants pt $participantWhere" .
    ($participantParams ? " AND" : " WHERE") . " DATE(pt.created_at) = CURDATE()",
    $participantParams
);

// ── Recent spins ──────────────────────────────────────
$recentSpins = $db->fetchAll(
    "SELECT pt.name, pt.email, pt.prize_won, pt.created_at, proj.name AS project_name, proj.color
     FROM participants pt
     JOIN projects proj ON proj.id = pt.project_id
     $participantWhere
     ORDER BY pt.created_at DESC LIMIT 10",
    $participantParams
);

// ── Spins per day (last 7 days) ───────────────────────
$spinsByDay = $db->fetchAll(
    "SELECT DATE(pt.created_at) AS day, COUNT(*) AS cnt
     FROM participants pt $participantWhere
     " . ($participantParams ? "AND" : "WHERE") . " pt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY day ORDER BY day",
    $participantParams
);

// ── Top projects by spins ─────────────────────────────
$topProjects = $db->fetchAll(
    "SELECT proj.name, proj.color, proj.status, COUNT(pt.id) AS spin_count
     FROM projects proj
     LEFT JOIN participants pt ON pt.project_id = proj.id
     " . str_replace('WHERE 1=1', 'WHERE', $where) . "
     GROUP BY proj.id ORDER BY spin_count DESC LIMIT 5",
    $whereParams
);

// ── Org groups for filter bar ─────────────────────────
$orgGroups = getFlatGroupList(true);

require_once __DIR__ . '/header.php';
?>

<!-- Group filter bar -->
<?php if (!empty($orgGroups)): ?>
<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:20px">
  <span style="font-size:12px;color:var(--t2)">Filter by group:</span>
  <a href="?" class="badge <?= !$filterGroupId ? 'badge-blue' : 'badge-gray' ?>" style="cursor:pointer;text-decoration:none">All</a>
  <?php foreach ($orgGroups as $og): ?>
    <?php
      $indent = str_repeat('· ', $og['_depth']);
      $active = $filterGroupId === (int)$og['id'];
    ?>
    <a href="?group=<?= $og['id'] ?>" class="badge <?= $active ? 'badge-blue' : 'badge-gray' ?>"
       style="cursor:pointer;text-decoration:none">
      <?= e($indent . $og['name']) ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Stats grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num"><?= formatNumber($totalProjects) ?></div>
    <div class="stat-label">Total Projects</div>
    <div class="stat-sub"><?= $activeProjects ?> active</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= formatNumber($totalSpins) ?></div>
    <div class="stat-label">Total Spins</div>
    <div class="stat-sub">All time</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= formatNumber($todaySpins) ?></div>
    <div class="stat-label">Spins Today</div>
    <div class="stat-sub"><?= date('d M Y') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $totalProjects > 0 ? round($totalSpins / max($totalProjects, 1)) : 0 ?></div>
    <div class="stat-label">Avg Spins / Project</div>
    <div class="stat-sub">Overall average</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

  <!-- Recent activity -->
  <div class="card">
    <div class="card-hd">
      <span class="card-title">Recent Spins</span>
      <?php if (currentUserCan('participants.view')): ?>
        <a href="<?= APP_URL ?>/admin/participants.php" class="btn btn-ghost btn-sm">View All →</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Name</th><th>Prize Won</th><th>Project</th><th>Time</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recentSpins)): ?>
          <tr><td colspan="4" style="color:var(--t2);text-align:center;padding:20px">No spins yet</td></tr>
        <?php else: ?>
          <?php foreach ($recentSpins as $s): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= e($s['name']) ?></div>
              <div style="font-size:11px;color:var(--t2)"><?= e($s['email']) ?></div>
            </td>
            <td><?= e($s['prize_won'] ?: '—') ?></td>
            <td>
              <span class="badge badge-blue" style="background:<?= e($s['color']) ?>20;color:<?= e($s['color']) ?>">
                <?= e(truncate($s['project_name'], 25)) ?>
              </span>
            </td>
            <td style="color:var(--t2);font-size:12px"><?= timeAgo($s['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top projects -->
  <div class="card">
    <div class="card-hd"><span class="card-title">Top Projects</span></div>
    <div class="card-body">
      <?php if (empty($topProjects)): ?>
        <p style="color:var(--t2);font-size:13px">No projects yet.</p>
      <?php else: ?>
        <?php foreach ($topProjects as $tp): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= e($tp['color']) ?>;flex-shrink:0"></div>
            <div style="flex:1;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($tp['name']) ?>
            </div>
            <span class="badge badge-gray"><?= formatNumber($tp['spin_count']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
