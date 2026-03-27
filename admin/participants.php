<?php
$pageTitle  = 'Participants';
$activePage = 'participants';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('participants.view');

$db = Database::getInstance();
[$scopeSql, $scopeParams] = orgScopeFilter('proj');

// ── DELETE action ─────────────────────────────────────
if (isPost() && currentUserCan('participants.delete')) {
    verifyCsrf();
    $action = post('action');
    if ($action === 'delete_one') {
        $id = (int)post('participant_id');
        $db->execute("DELETE FROM participants WHERE id=?", [$id]);
        flashSet('success', 'Participant deleted.');
    } elseif ($action === 'delete_all_project') {
        $pid = (int)post('project_id');
        $db->execute("DELETE FROM participants WHERE project_id=?", [$pid]);
        flashSet('success', 'All participants for project deleted.');
    }
    redirect(APP_URL . '/admin/participants.php?' . http_build_query(array_filter(['q' => get('q'), 'project' => get('project'), 'page' => get('page')])));
}

// ── Filters ───────────────────────────────────────────
$search  = trim(get('q'));
$projF   = (int)(get('project') ?: 0);
$page    = max(1, (int)(get('page') ?: 1));
$perPage = 25;

// Build scoped project sub-query
$projWhere  = "WHERE 1=1 $scopeSql";
$projParams = $scopeParams;

if ($projF) {
    $projWhere  .= " AND proj.id = ?";
    $projParams[] = $projF;
}

$scopedProjectIds = array_column(
    $db->fetchAll("SELECT proj.id FROM projects proj $projWhere", $projParams),
    'id'
);

$participantWhere  = "WHERE 1=1";
$participantParams = [];

if (!empty($scopedProjectIds)) {
    $ph = implode(',', array_fill(0, count($scopedProjectIds), '?'));
    $participantWhere  .= " AND pt.project_id IN ($ph)";
    $participantParams  = array_merge($participantParams, $scopedProjectIds);
} elseif ($scopeSql) {
    // Scope set but nothing matches
    $participantWhere  .= " AND 1=0";
}

if ($search) {
    $participantWhere  .= " AND (pt.name LIKE ? OR pt.email LIKE ? OR pt.prize_won LIKE ?)";
    $participantParams[] = "%$search%";
    $participantParams[] = "%$search%";
    $participantParams[] = "%$search%";
}

$total        = (int)$db->fetchValue("SELECT COUNT(*) FROM participants pt $participantWhere", $participantParams);
$pg           = paginate($total, $perPage, $page);
$participants = $db->fetchAll(
    "SELECT pt.*, proj.name AS project_name, proj.color AS project_color
     FROM participants pt
     LEFT JOIN projects proj ON proj.id = pt.project_id
     $participantWhere
     ORDER BY pt.created_at DESC LIMIT $perPage OFFSET {$pg['offset']}",
    $participantParams
);

// Project list for filter dropdown
$projectList = $db->fetchAll(
    "SELECT proj.id, proj.name FROM projects proj $projWhere ORDER BY proj.name",
    $projParams
);

require_once __DIR__ . '/header.php';
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, prize…" style="max-width:260px">
    <select name="project" style="max-width:200px">
      <option value="">All Projects</option>
      <?php foreach ($projectList as $pl): ?>
        <option value="<?= $pl['id'] ?>" <?= $projF===$pl['id']?'selected':'' ?>><?= e($pl['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost">Filter</button>
    <?php if ($search || $projF): ?>
      <a href="?" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>
  <?php if (currentUserCan('export.csv')): ?>
  <a href="<?= APP_URL ?>/admin/export.php?format=csv&project=<?= $projF ?>&q=<?= urlencode($search) ?>"
     class="btn btn-ghost">⬇ Export CSV</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-hd">
    <span class="card-title">Participants <span style="color:var(--t2);font-weight:400">(<?= formatNumber($total) ?>)</span></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Name</th><th>Email</th><th>Prize Won</th><th>Project</th><th>Date</th>
        <?php if (currentUserCan('participants.delete')): ?><th>Action</th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php if (empty($participants)): ?>
        <tr><td colspan="6" style="color:var(--t2);text-align:center;padding:28px">
          No participants found.
        </td></tr>
      <?php else: ?>
        <?php foreach ($participants as $pt): ?>
        <tr>
          <td style="font-weight:500"><?= e($pt['name']) ?></td>
          <td style="color:var(--t2)"><a href="mailto:<?= e($pt['email']) ?>"><?= e($pt['email']) ?></a></td>
          <td><?= e($pt['prize_won'] ?: '—') ?></td>
          <td>
            <span class="badge" style="background:<?= e($pt['project_color']) ?>25;color:<?= e($pt['project_color']) ?>">
              <?= e(truncate($pt['project_name'] ?? '?', 28)) ?>
            </span>
          </td>
          <td style="color:var(--t2);font-size:12px"><?= fmtDate($pt['created_at']) ?></td>
          <?php if (currentUserCan('participants.delete')): ?>
          <td>
            <form method="POST" style="display:inline"
              onsubmit="return confirm('Delete this participant?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete_one">
              <input type="hidden" name="participant_id" value="<?= $pt['id'] ?>">
              <button class="btn btn-danger btn-sm">🗑</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pg['totalPages'] > 1): ?>
  <div style="padding:14px 18px;display:flex;gap:8px;align-items:center;border-top:1px solid var(--border)">
    <span style="font-size:12px;color:var(--t2);flex:1">
      Showing <?= ($pg['offset']+1) ?>–<?= min($pg['offset']+$perPage, $total) ?> of <?= formatNumber($total) ?>
    </span>
    <?php for ($i = max(1,$pg['currentPage']-3); $i <= min($pg['totalPages'],$pg['currentPage']+3); $i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
         class="btn btn-ghost btn-sm"
         style="<?= $i===$pg['currentPage']?'background:var(--accent);color:#fff;border-color:var(--accent)':'' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
