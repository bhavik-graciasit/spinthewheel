<?php
$pageTitle  = 'Users';
$activePage = 'users';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('users.view');

$db = Database::getInstance();

// ── POST actions ──────────────────────────────────────
if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'create' && currentUserCan('users.create')) {
        $username    = trim(post('username'));
        $displayName = trim(post('display_name'));
        $email       = trim(post('email'));
        $password    = post('password');
        $roleId      = (int)post('role_id');
        $scopeIds    = post('org_scope_ids');   // comma-separated group IDs or empty
        $status      = post('status') === 'inactive' ? 'inactive' : 'active';
        $errors      = [];

        if (!$username) $errors[] = 'Username required.';
        if (!validateEmail($email)) $errors[] = 'Valid email required.';
        $pwErr = validatePassword($password);
        $errors = array_merge($errors, $pwErr);
        if ($db->fetchOne("SELECT id FROM admin_users WHERE username=?", [$username])) $errors[] = 'Username already taken.';
        if ($db->fetchOne("SELECT id FROM admin_users WHERE email=?", [$email])) $errors[] = 'Email already in use.';

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            // Validate scope IDs
            $cleanScope = cleanScopeIds($scopeIds);
            $db->insert(
                "INSERT INTO admin_users (username,display_name,email,password_hash,role_id,org_scope_ids,status)
                 VALUES (?,?,?,?,?,?,?)",
                [$username, $displayName ?: $username, $email, $hash, $roleId, $cleanScope, $status]
            );
            flashSet('success', "User "$username" created.");
            redirect(APP_URL . '/admin/users.php');
        } else {
            flashSet('error', implode(' ', $errors));
        }
    }

    if ($action === 'edit' && currentUserCan('users.edit')) {
        $uid         = (int)post('user_id');
        $displayName = trim(post('display_name'));
        $email       = trim(post('email'));
        $roleId      = (int)post('role_id');
        $scopeIds    = post('org_scope_ids');
        $status      = post('status') === 'inactive' ? 'inactive' : 'active';
        $errors      = [];

        // Prevent demoting/disabling yourself
        if ($uid === currentUserId()) {
            if ($status === 'inactive') $errors[] = 'Cannot disable your own account.';
        }
        if (!validateEmail($email)) $errors[] = 'Valid email required.';
        $dup = $db->fetchOne("SELECT id FROM admin_users WHERE email=? AND id!=?", [$email, $uid]);
        if ($dup) $errors[] = 'Email already in use.';

        if (!$errors) {
            $cleanScope = cleanScopeIds($scopeIds);
            // Don't let edit lower than superadmin if only one superadmin left
            $db->execute(
                "UPDATE admin_users SET display_name=?,email=?,role_id=?,org_scope_ids=?,status=? WHERE id=?",
                [$displayName, $email, $roleId, $cleanScope, $status, $uid]
            );
            flashSet('success', 'User updated.');
            redirect(APP_URL . '/admin/users.php');
        } else {
            flashSet('error', implode(' ', $errors));
        }
    }

    if ($action === 'reset_password' && currentUserCan('users.edit')) {
        $uid      = (int)post('user_id');
        $newPw    = post('new_password');
        $pwErr    = validatePassword($newPw);
        if ($pwErr) {
            flashSet('error', implode(' ', $pwErr));
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->execute("UPDATE admin_users SET password_hash=? WHERE id=?", [$hash, $uid]);
            flashSet('success', 'Password reset.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'delete' && currentUserCan('users.delete')) {
        $uid = (int)post('user_id');
        if ($uid === currentUserId()) {
            flashSet('error', 'Cannot delete your own account.');
        } else {
            $db->execute("DELETE FROM admin_users WHERE id=?", [$uid]);
            flashSet('success', 'User deleted.');
        }
        redirect(APP_URL . '/admin/users.php');
    }
}

function cleanScopeIds(string $raw): ?string {
    $ids = array_filter(array_map('intval', explode(',', $raw)));
    return $ids ? implode(',', array_unique($ids)) : null;
}

// ── Load data ─────────────────────────────────────────
$users = $db->fetchAll(
    "SELECT u.*, r.name AS role_name, r.slug AS role_slug
     FROM admin_users u
     JOIN admin_roles r ON r.id = u.role_id
     ORDER BY r.id DESC, u.username"
);
$roles     = $db->fetchAll("SELECT * FROM admin_roles ORDER BY id");
$orgGroups = getFlatGroupList(false);

require_once __DIR__ . '/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div style="font-size:13px;color:var(--t2)"><?= count($users) ?> user<?= count($users)!=1?'s':'' ?></div>
  <?php if (currentUserCan('users.create')): ?>
  <button class="btn btn-primary" onclick="openCreate()">+ New User</button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-hd"><span class="card-title">👤 Admin Users</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>User</th><th>Role</th><th>Org Scope</th><th>Status</th><th>Last Login</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div style="font-weight:500"><?= e($u['display_name'] ?: $u['username']) ?></div>
          <div style="font-size:11px;color:var(--t2)"><?= e($u['username']) ?> · <?= e($u['email']) ?></div>
        </td>
        <td><span class="badge badge-blue role-<?= e($u['role_slug']) ?>"><?= e($u['role_name']) ?></span></td>
        <td>
          <?php if ($u['org_scope_ids']): ?>
            <?php $scopeIds = array_map('intval', explode(',', $u['org_scope_ids'])); ?>
            <span style="font-size:11px;color:var(--warning)">🔒 <?= count($scopeIds) ?> group<?= count($scopeIds)!=1?'s':'' ?></span>
          <?php else: ?>
            <span style="font-size:11px;color:var(--t3)">Full access</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $u['status']==='active'?'badge-green':'badge-red' ?>"><?= $u['status'] ?></span></td>
        <td style="color:var(--t2);font-size:12px"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Never' ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if (currentUserCan('users.edit')): ?>
            <button class="btn btn-ghost btn-sm"
              onclick='openEdit(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>Edit</button>
            <button class="btn btn-ghost btn-sm"
              onclick='openResetPw(<?= $u["id"] ?>, <?= htmlspecialchars(json_encode($u["username"]), ENT_QUOTES) ?>)'>🔑</button>
            <?php endif; ?>
            <?php if (currentUserCan('users.delete') && $u['id'] !== currentUserId()): ?>
            <form method="POST" style="display:inline"
              onsubmit="return confirm('Delete user «<?= e($u['username']) ?>»?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-danger btn-sm">🗑</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Role info card -->
<div class="card">
  <div class="card-hd"><span class="card-title">🔑 Role Permissions Summary</span></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
      <?php foreach ([ROLE_SUPERADMIN=>'All permissions + user management', ROLE_ADMIN=>'Manage org, users (not roles)', ROLE_MANAGER=>'Create/edit projects, delete participants', ROLE_VIEWER=>'Read-only access to all data'] as $slug => $desc): ?>
      <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div class="role-<?= $slug ?>" style="font-weight:600;font-size:13px;text-transform:capitalize"><?= $slug ?></div>
        <div style="font-size:11px;color:var(--t2);margin-top:4px"><?= $desc ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Create / Edit / Reset Password modals -->
<div class="modal-bg" id="userModal">
  <div class="modal">
    <div class="modal-hd">
      <span class="modal-title" id="userModalTitle">New User</span>
      <button class="modal-close" onclick="closeModal('userModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="userForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create" id="uAction">
        <input type="hidden" name="user_id" value="" id="uUserId">

        <div class="fg-row">
          <div class="fg">
            <label>Username *</label>
            <input type="text" name="username" id="uUsername" autocomplete="off">
          </div>
          <div class="fg">
            <label>Display Name</label>
            <input type="text" name="display_name" id="uDisplayName">
          </div>
        </div>
        <div class="fg">
          <label>Email *</label>
          <input type="email" name="email" id="uEmail" autocomplete="off">
        </div>
        <div class="fg" id="passwordField">
          <label>Password *</label>
          <input type="password" name="password" id="uPassword" autocomplete="new-password">
          <div style="font-size:11px;color:var(--t3);margin-top:4px">Min 8 chars, 1 uppercase, 1 number</div>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Role</label>
            <select name="role_id" id="uRoleId">
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label>Status</label>
            <select name="status" id="uStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <?php if (!empty($orgGroups)): ?>
        <div class="fg">
          <label>Org Scope <span style="color:var(--t3)">(leave empty = full access)</span></label>
          <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px;max-height:160px;overflow-y:auto">
            <?php foreach ($orgGroups as $og): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:3px 0;cursor:pointer;font-size:13px">
              <input type="checkbox" class="scope-cb" value="<?= $og['id'] ?>"
                style="width:auto;margin:0">
              <?= str_repeat('· ', $og['_depth']) . e($og['name']) ?>
              <?php if ($og['level_label']): ?>
                <span style="font-size:10px;color:var(--t3)"><?= e($og['level_label']) ?></span>
              <?php endif; ?>
            </label>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="org_scope_ids" id="uScopeIds">
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
          <button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-bg" id="resetModal">
  <div class="modal" style="max-width:380px">
    <div class="modal-hd">
      <span class="modal-title" id="resetModalTitle">Reset Password</span>
      <button class="modal-close" onclick="closeModal('resetModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="resetUserId">
        <div class="fg">
          <label>New Password</label>
          <input type="password" name="new_password" autocomplete="new-password" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">Min 8 chars, 1 uppercase, 1 number</div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
          <button type="button" class="btn btn-ghost" onclick="closeModal('resetModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Set Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openCreate() {
  document.getElementById('userModalTitle').textContent = 'New User';
  document.getElementById('uAction').value     = 'create';
  document.getElementById('uUserId').value     = '';
  document.getElementById('uUsername').value   = '';
  document.getElementById('uDisplayName').value= '';
  document.getElementById('uEmail').value      = '';
  document.getElementById('uPassword').value   = '';
  document.getElementById('uStatus').value     = 'active';
  document.getElementById('passwordField').style.display = 'block';
  document.getElementById('uUsername').disabled = false;
  document.querySelectorAll('.scope-cb').forEach(cb => cb.checked = false);
  document.getElementById('userModal').classList.add('open');
}
function openEdit(u) {
  document.getElementById('userModalTitle').textContent = 'Edit: ' + u.username;
  document.getElementById('uAction').value     = 'edit';
  document.getElementById('uUserId').value     = u.id;
  document.getElementById('uUsername').value   = u.username;
  document.getElementById('uUsername').disabled = true;
  document.getElementById('uDisplayName').value= u.display_name || '';
  document.getElementById('uEmail').value      = u.email || '';
  document.getElementById('uRoleId').value     = u.role_id;
  document.getElementById('uStatus').value     = u.status;
  document.getElementById('passwordField').style.display = 'none';
  // Scope checkboxes
  const scopeIds = u.org_scope_ids ? u.org_scope_ids.split(',').map(Number) : [];
  document.querySelectorAll('.scope-cb').forEach(cb => {
    cb.checked = scopeIds.includes(parseInt(cb.value));
  });
  document.getElementById('userModal').classList.add('open');
}
function openResetPw(id, username) {
  document.getElementById('resetModalTitle').textContent = 'Reset: ' + username;
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetModal').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Collect scope checkboxes into hidden field
document.getElementById('userForm').addEventListener('submit', function() {
  const checked = [...document.querySelectorAll('.scope-cb:checked')].map(cb => cb.value);
  const el = document.getElementById('uScopeIds');
  if (el) el.value = checked.join(',');
});
['userModal','resetModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(id); });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
