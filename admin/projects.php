<?php
$pageTitle  = 'Projects';
$activePage = 'projects';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('projects.view');

$db = Database::getInstance();
[$scopeSql, $scopeParams] = orgScopeFilter('p');

// ── Handle POST actions ───────────────────────────────
if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if (in_array($action, ['create', 'edit']) && currentUserCan('projects.' . ($action === 'create' ? 'create' : 'edit'))) {
        $name         = trim(post('name'));
        $desc         = trim(post('description'));
        $color        = preg_replace('/[^#a-fA-F0-9]/', '', post('color')) ?: '#6366f1';
        $status       = post('status') === 'active' ? 'active' : 'inactive';
        $groupId      = (int)post('group_id') ?: null;
        $spinDuration = max(3000, min(30000, (int)post('spin_duration_ms') ?: 5000));
        $errors       = [];

        if (!$name) $errors[] = 'Project name is required.';

        if (!$errors) {
            if ($action === 'create') {
                $token = generateToken(12);
                $db->insert(
                    "INSERT INTO projects (name,description,color,token,status,group_id,spin_duration_ms)
                     VALUES (?,?,?,?,?,?,?)",
                    [$name, $desc, $color, $token, $status, $groupId, $spinDuration]
                );
                flashSet('success', "Project "$name" created.");
            } else {
                $id = (int)post('project_id');
                $db->execute(
                    "UPDATE projects SET name=?,description=?,color=?,status=?,group_id=?,spin_duration_ms=? WHERE id=?",
                    [$name, $desc, $color, $status, $groupId, $spinDuration, $id]
                );
                flashSet('success', "Project "$name" updated.");
            }
            redirect(APP_URL . '/admin/projects.php');
        }
    }

    if ($action === 'delete' && currentUserCan('projects.delete')) {
        $id = (int)post('project_id');
        $db->execute("DELETE FROM projects WHERE id=?", [$id]);
        flashSet('success', 'Project deleted.');
        redirect(APP_URL . '/admin/projects.php');
    }

    if ($action === 'toggle_status' && currentUserCan('projects.edit')) {
        $id = (int)post('project_id');
        $db->execute("UPDATE projects SET status = IF(status='active','inactive','active') WHERE id=?", [$id]);
        redirect(APP_URL . '/admin/projects.php');
    }
}

// ── Load projects ─────────────────────────────────────
$search  = trim(get('q'));
$groupF  = (int)(get('group') ?: 0);
$page    = max(1, (int)(get('page') ?: 1));
$perPage = 20;

$where  = "WHERE 1=1 $scopeSql";
$params = $scopeParams;

if ($search) {
    $where  .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($groupF) {
    $gids = getDescendantGroupIds($groupF);
    $ph   = implode(',', array_fill(0, count($gids), '?'));
    $where   .= " AND p.group_id IN ($ph)";
    $params   = array_merge($params, $gids);
}

$total    = (int)$db->fetchValue("SELECT COUNT(*) FROM projects p $where", $params);
$pg       = paginate($total, $perPage, $page);
$projects = $db->fetchAll(
    "SELECT p.*, og.name AS group_name, og.color AS group_color,
            (SELECT COUNT(*) FROM participants pt WHERE pt.project_id = p.id) AS spin_count
     FROM projects p
     LEFT JOIN org_groups og ON og.id = p.group_id
     $where
     ORDER BY p.created_at DESC LIMIT $perPage OFFSET {$pg['offset']}",
    $params
);

$orgGroups = getFlatGroupList(true);

require_once __DIR__ . '/header.php';
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search projects…" style="max-width:260px">
    <?php if (!empty($orgGroups)): ?>
    <select name="group" style="max-width:180px">
      <option value="">All Groups</option>
      <?php foreach ($orgGroups as $og): ?>
        <option value="<?= $og['id'] ?>" <?= $groupF===$og['id']?'selected':'' ?>>
          <?= str_repeat('· ', $og['_depth']) . e($og['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button class="btn btn-ghost">Filter</button>
  </form>
  <?php if (currentUserCan('projects.create')): ?>
  <button class="btn btn-primary" onclick="openModal('create')">+ New Project</button>
  <?php endif; ?>
</div>

<!-- Projects table -->
<div class="card">
  <div class="card-hd">
    <span class="card-title">Projects <span style="color:var(--t2);font-weight:400">(<?= formatNumber($total) ?>)</span></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Project</th><th>Group</th><th>Spins</th><th>Status</th><th>Created</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($projects)): ?>
        <tr><td colspan="6" style="color:var(--t2);text-align:center;padding:28px">
          <?= $search ? 'No results for "'.e($search).'".' : 'No projects yet. Create one above.' ?>
        </td></tr>
      <?php else: ?>
        <?php foreach ($projects as $proj): ?>
          <?php $crumbs = getGroupBreadcrumb($proj['group_id']); ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:50%;background:<?= e($proj['color']) ?>;flex-shrink:0"></div>
                <div>
                  <div style="font-weight:500"><?= e($proj['name']) ?></div>
                  <?php if ($proj['description']): ?>
                    <div style="font-size:11px;color:var(--t2)"><?= e(truncate($proj['description'], 50)) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <?php if (!empty($crumbs)): ?>
                <div style="font-size:11px;color:var(--t2)">
                  <?= implode(' › ', array_map(fn($c) => e($c['name']), $crumbs)) ?>
                </div>
              <?php else: ?>
                <span style="color:var(--t3)">—</span>
              <?php endif; ?>
            </td>
            <td><?= formatNumber($proj['spin_count']) ?></td>
            <td>
              <span class="badge <?= $proj['status']==='active'?'badge-green':'badge-gray' ?>">
                <?= $proj['status'] ?>
              </span>
            </td>
            <td style="color:var(--t2);font-size:12px"><?= fmtDate($proj['created_at'], 'd M Y') ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="<?= APP_URL ?>/spin/?p=<?= e($proj['token']) ?>" target="_blank"
                   class="btn btn-ghost btn-sm" title="Preview spin page">🔗</a>
                <?php if (currentUserCan('projects.edit')): ?>
                <button class="btn btn-ghost btn-sm"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($proj), ENT_QUOTES) ?>)">Edit</button>
                <?php endif; ?>
                <?php if (currentUserCan('projects.edit')): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                  <button class="btn btn-ghost btn-sm" title="Toggle status">
                    <?= $proj['status']==='active' ? '⏸' : '▶' ?>
                  </button>
                </form>
                <?php endif; ?>
                <?php if (currentUserCan('projects.delete')): ?>
                <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete project «<?= e($proj['name']) ?>»? This also deletes all its participants.')">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                  <button class="btn btn-danger btn-sm">🗑</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pg['totalPages'] > 1): ?>
  <div style="padding:14px 18px;display:flex;gap:8px;align-items:center;justify-content:flex-end;border-top:1px solid var(--border)">
    <?php for ($i = 1; $i <= $pg['totalPages']; $i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
         class="btn btn-ghost btn-sm <?= $i===$pg['currentPage']?'active':'' ?>"
         style="<?= $i===$pg['currentPage']?'background:var(--accent);color:#fff;border-color:var(--accent)':'' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Create / Edit Modal -->
<?php if (currentUserCan('projects.create') || currentUserCan('projects.edit')): ?>
<div class="modal-bg" id="projectModal">
  <div class="modal">
    <div class="modal-hd">
      <span class="modal-title" id="modalTitle">New Project</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="projectForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create" id="formAction">
        <input type="hidden" name="project_id" value="" id="formProjectId">

        <div class="fg">
          <label>Project Name *</label>
          <input type="text" name="name" id="formName" required>
        </div>
        <div class="fg">
          <label>Description</label>
          <textarea name="description" id="formDesc" rows="2" style="resize:vertical"></textarea>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Accent Colour</label>
            <input type="color" name="color" id="formColor" value="#6366f1" style="height:40px;padding:4px">
          </div>
          <div class="fg">
            <label>Status</label>
            <select name="status" id="formStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <?php if (!empty($orgGroups)): ?>
        <div class="fg">
          <label>Assign to Group</label>
          <select name="group_id" id="formGroup">
            <option value="">— No group —</option>
            <?php foreach ($orgGroups as $og): ?>
              <option value="<?= $og['id'] ?>">
                <?= str_repeat('· ', $og['_depth']) . e($og['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="fg">
          <label>Spin Duration</label>
          <select name="spin_duration_ms" id="formSpin">
            <option value="3000">3 seconds (quick)</option>
            <option value="5000" selected>5 seconds (default)</option>
            <option value="8000">8 seconds</option>
            <option value="10000">10 seconds (dramatic)</option>
            <option value="15000">15 seconds (very dramatic)</option>
          </select>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
          <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Project</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(mode) {
  document.getElementById('modalTitle').textContent = 'New Project';
  document.getElementById('formAction').value    = 'create';
  document.getElementById('formProjectId').value = '';
  document.getElementById('formName').value      = '';
  document.getElementById('formDesc').value      = '';
  document.getElementById('formColor').value     = '#6366f1';
  document.getElementById('formStatus').value    = 'active';
  if (document.getElementById('formGroup')) document.getElementById('formGroup').value = '';
  document.getElementById('formSpin').value = '5000';
  document.getElementById('projectModal').classList.add('open');
}

function openEdit(p) {
  document.getElementById('modalTitle').textContent = 'Edit Project';
  document.getElementById('formAction').value    = 'edit';
  document.getElementById('formProjectId').value = p.id;
  document.getElementById('formName').value      = p.name;
  document.getElementById('formDesc').value      = p.description || '';
  document.getElementById('formColor').value     = p.color;
  document.getElementById('formStatus').value    = p.status;
  if (document.getElementById('formGroup')) document.getElementById('formGroup').value = p.group_id || '';
  document.getElementById('formSpin').value = p.spin_duration_ms || 5000;
  document.getElementById('projectModal').classList.add('open');
}

function closeModal() {
  document.getElementById('projectModal').classList.remove('open');
}

document.getElementById('projectModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
