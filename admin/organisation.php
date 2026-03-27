<?php
$pageTitle  = 'Organisation';
$activePage = 'organisation';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('organisation.view');

$db = Database::getInstance();

// ── POST actions ──────────────────────────────────────
if (isPost() && currentUserCan('organisation.manage')) {
    verifyCsrf();
    $action = post('action');

    if (in_array($action, ['create', 'edit'])) {
        $name       = trim(post('name'));
        $levelLabel = trim(post('level_label'));
        $parentId   = (int)post('parent_id') ?: null;
        $color      = preg_replace('/[^#a-fA-F0-9]/', '', post('color')) ?: '#6366f1';
        $sortOrder  = (int)post('sort_order');
        $status     = post('status') === 'inactive' ? 'inactive' : 'active';
        $errors     = [];

        if (!$name) $errors[] = 'Group name is required.';

        // Circular reference check (on edit)
        if ($action === 'edit' && $parentId) {
            $editId = (int)post('group_id');
            $ancestors = getDescendantGroupIds($editId);
            if (in_array($parentId, $ancestors)) {
                $errors[] = 'Cannot set a descendant as the parent (circular reference).';
            }
        }

        if (!$errors) {
            $slug = slugify($name) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if ($action === 'create') {
                $db->insert(
                    "INSERT INTO org_groups (name, slug, level_label, parent_id, color, sort_order, status)
                     VALUES (?,?,?,?,?,?,?)",
                    [$name, $slug, $levelLabel ?: null, $parentId, $color, $sortOrder, $status]
                );
                flashSet('success', "Group "$name" created.");
            } else {
                $gid = (int)post('group_id');
                $db->execute(
                    "UPDATE org_groups SET name=?, level_label=?, parent_id=?, color=?, sort_order=?, status=? WHERE id=?",
                    [$name, $levelLabel ?: null, $parentId, $color, $sortOrder, $status, $gid]
                );
                flashSet('success', "Group "$name" updated.");
            }
            redirect(APP_URL . '/admin/organisation.php');
        }
    }

    if ($action === 'delete') {
        $gid = (int)post('group_id');
        // Unlink children and projects — do not cascade delete
        $db->execute("UPDATE org_groups SET parent_id = NULL WHERE parent_id = ?", [$gid]);
        $db->execute("UPDATE projects SET group_id = NULL WHERE group_id = ?", [$gid]);
        $db->execute("UPDATE admin_users SET org_scope_ids = NULL WHERE org_scope_ids = ?", [(string)$gid]);
        $db->execute("DELETE FROM org_groups WHERE id = ?", [$gid]);
        flashSet('success', 'Group deleted. Children and projects have been unlinked.');
        redirect(APP_URL . '/admin/organisation.php');
    }
}

// ── Load tree ─────────────────────────────────────────
$allGroups = $db->fetchAll(
    "SELECT g.*,
       (SELECT COUNT(*) FROM projects p WHERE p.group_id = g.id) AS project_count,
       (SELECT COUNT(*) FROM participants pt JOIN projects p ON p.id = pt.project_id WHERE p.group_id = g.id) AS spin_count
     FROM org_groups g ORDER BY g.parent_id IS NULL DESC, g.sort_order, g.name"
);
$tree      = buildGroupTree($allGroups);
$flatList  = getFlatGroupList(false);

require_once __DIR__ . '/header.php';

// ── Recursive tree renderer ───────────────────────────
function renderTree(array $nodes, int $depth = 0): void {
    foreach ($nodes as $node):
        $indent = $depth * 24;
        $crumbs = getGroupBreadcrumb($node['id']);
        $path   = implode(' › ', array_map(fn($c) => htmlspecialchars($c['name'], ENT_QUOTES), $crumbs));
?>
<div style="border-left:2px solid <?= e($node['color']) ?>3a;margin-left:<?= $depth?($indent-12).'px':0 ?>;padding-left:<?= $depth?'12px':0 ?>">
  <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg3);border-radius:8px;margin-bottom:6px;border:1px solid var(--border)">
    <div style="width:10px;height:10px;border-radius:50%;background:<?= e($node['color']) ?>;flex-shrink:0"></div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-weight:600"><?= e($node['name']) ?></span>
        <?php if ($node['level_label']): ?>
          <span class="badge badge-gray" style="font-size:10px"><?= e($node['level_label']) ?></span>
        <?php endif; ?>
        <?php if ($node['status'] === 'inactive'): ?>
          <span class="badge badge-red" style="font-size:10px">inactive</span>
        <?php endif; ?>
      </div>
      <div style="font-size:11px;color:var(--t2);margin-top:2px">
        <?= $node['project_count'] ?> project<?= $node['project_count']!=1?'s':'' ?> ·
        <?= number_format($node['spin_count']) ?> spin<?= $node['spin_count']!=1?'s':'' ?>
        <?php if ($depth > 0): ?>· <span style="color:var(--t3)"><?= e($path) ?></span><?php endif; ?>
      </div>
    </div>
    <?php if (currentUserCan('organisation.manage')): ?>
    <div style="display:flex;gap:6px;flex-shrink:0">
      <button class="btn btn-ghost btn-sm"
        onclick='openAddChild(<?= $node["id"] ?>, <?= htmlspecialchars(json_encode($node["name"]), ENT_QUOTES) ?>)'>+ Child</button>
      <button class="btn btn-ghost btn-sm"
        onclick='openEdit(<?= htmlspecialchars(json_encode($node), ENT_QUOTES) ?>)'>Edit</button>
      <form method="POST" style="display:inline"
        onsubmit="return confirm('Delete «<?= e($node['name']) ?>»? Children and projects will be unlinked.')">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="group_id" value="<?= $node['id'] ?>">
        <button class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php if (!empty($node['children'])): ?>
    <?php renderTree($node['children'], $depth + 1); ?>
  <?php endif; ?>
</div>
<?php
    endforeach;
}
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <div style="font-size:13px;color:var(--t2)">
      <?= count($allGroups) ?> group<?= count($allGroups)!=1?'s':'' ?> total
    </div>
  </div>
  <?php if (currentUserCan('organisation.manage')): ?>
  <button class="btn btn-primary" onclick="openCreate()">+ New Top-level Group</button>
  <?php endif; ?>
</div>

<!-- Tree -->
<div class="card">
  <div class="card-hd"><span class="card-title">🏢 Organisation Tree</span></div>
  <div class="card-body">
    <?php if (empty($tree)): ?>
      <div style="color:var(--t2);text-align:center;padding:28px">
        No groups yet.
        <?php if (currentUserCan('organisation.manage')): ?>
          <button class="btn btn-primary" style="margin-left:12px" onclick="openCreate()">Create First Group</button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php renderTree($tree); ?>
    <?php endif; ?>
  </div>
</div>

<!-- Create / Edit modal -->
<?php if (currentUserCan('organisation.manage')): ?>
<div class="modal-bg" id="orgModal">
  <div class="modal">
    <div class="modal-hd">
      <span class="modal-title" id="orgModalTitle">New Group</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create" id="orgAction">
        <input type="hidden" name="group_id" value="" id="orgGroupId">

        <div class="fg">
          <label>Group Name *</label>
          <input type="text" name="name" id="orgName" required>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Level Label <span style="color:var(--t3)">(e.g. Theatre, Region)</span></label>
            <input type="text" name="level_label" id="orgLabel" placeholder="optional">
          </div>
          <div class="fg">
            <label>Accent Colour</label>
            <input type="color" name="color" id="orgColor" value="#6366f1" style="height:40px;padding:4px">
          </div>
        </div>
        <div class="fg">
          <label>Parent Group</label>
          <select name="parent_id" id="orgParent">
            <option value="">— Top level (no parent) —</option>
            <?php foreach ($flatList as $fl): ?>
              <option value="<?= $fl['id'] ?>">
                <?= str_repeat('· ', $fl['_depth']) . e($fl['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Sort Order</label>
            <input type="number" name="sort_order" id="orgSort" value="0" min="0" style="width:100%">
          </div>
          <div class="fg">
            <label>Status</label>
            <select name="status" id="orgStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
          <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Group</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function openCreate() {
  document.getElementById('orgModalTitle').textContent = 'New Top-level Group';
  document.getElementById('orgAction').value   = 'create';
  document.getElementById('orgGroupId').value  = '';
  document.getElementById('orgName').value     = '';
  document.getElementById('orgLabel').value    = '';
  document.getElementById('orgColor').value    = '#6366f1';
  document.getElementById('orgParent').value   = '';
  document.getElementById('orgSort').value     = '0';
  document.getElementById('orgStatus').value   = 'active';
  document.getElementById('orgModal').classList.add('open');
}
function openAddChild(parentId, parentName) {
  document.getElementById('orgModalTitle').textContent = 'New Child of ' + parentName;
  document.getElementById('orgAction').value   = 'create';
  document.getElementById('orgGroupId').value  = '';
  document.getElementById('orgName').value     = '';
  document.getElementById('orgLabel').value    = '';
  document.getElementById('orgColor').value    = '#6366f1';
  document.getElementById('orgParent').value   = parentId;
  document.getElementById('orgSort').value     = '0';
  document.getElementById('orgStatus').value   = 'active';
  document.getElementById('orgModal').classList.add('open');
}
function openEdit(g) {
  document.getElementById('orgModalTitle').textContent = 'Edit: ' + g.name;
  document.getElementById('orgAction').value   = 'edit';
  document.getElementById('orgGroupId').value  = g.id;
  document.getElementById('orgName').value     = g.name;
  document.getElementById('orgLabel').value    = g.level_label || '';
  document.getElementById('orgColor').value    = g.color || '#6366f1';
  document.getElementById('orgParent').value   = g.parent_id || '';
  document.getElementById('orgSort').value     = g.sort_order || 0;
  document.getElementById('orgStatus').value   = g.status || 'active';
  document.getElementById('orgModal').classList.add('open');
}
function closeModal() {
  document.getElementById('orgModal').classList.remove('open');
}
document.getElementById('orgModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
