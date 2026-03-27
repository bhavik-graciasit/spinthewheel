<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$db      = Database::getInstance();
$userId  = currentUserId();
$user    = $db->fetchOne(
    "SELECT u.*, r.name AS role_name, r.slug AS role
     FROM admin_users u JOIN admin_roles r ON r.id = u.role_id
     WHERE u.id = ?", [$userId]
);

$success = '';
$errors  = [];

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'profile') {
        $displayName = trim(post('display_name'));
        $email       = trim(post('email'));
        if (!$displayName) $errors[] = 'Display name is required.';
        if (!validateEmail($email)) $errors[] = 'Valid email is required.';
        // Check email uniqueness
        $existing = $db->fetchOne("SELECT id FROM admin_users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existing) $errors[] = 'Email already in use by another account.';

        if (!$errors) {
            $db->execute("UPDATE admin_users SET display_name=?, email=? WHERE id=?",
                [$displayName, $email, $userId]);
            $_SESSION['admin_display_name'] = $displayName;
            $_SESSION['admin_email']        = $email;
            $user = $db->fetchOne("SELECT u.*, r.name AS role_name, r.slug AS role FROM admin_users u JOIN admin_roles r ON r.id = u.role_id WHERE u.id = ?", [$userId]);
            flashSet('success', 'Profile updated.');
            redirect(APP_URL . '/admin/profile.php');
        }

    } elseif ($action === 'password') {
        $current  = post('current_password');
        $new      = post('new_password');
        $confirm  = post('confirm_password');

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        $pwErrors = validatePassword($new);
        $errors = array_merge($errors, $pwErrors);
        if ($new !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->execute("UPDATE admin_users SET password_hash=? WHERE id=?", [$hash, $userId]);
            flashSet('success', 'Password changed successfully.');
            redirect(APP_URL . '/admin/profile.php');
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="fg-row" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <!-- Profile Info -->
  <div class="card">
    <div class="card-hd"><span class="card-title">👤 Profile Information</span></div>
    <div class="card-body">
      <?php if ($errors): ?>
        <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="profile">
        <div class="fg">
          <label>Username (cannot change)</label>
          <input type="text" value="<?= e($user['username']) ?>" disabled style="opacity:.5">
        </div>
        <div class="fg">
          <label>Display Name</label>
          <input type="text" name="display_name" value="<?= e($user['display_name'] ?: $user['username']) ?>" required>
        </div>
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" value="<?= e($user['email']) ?>" required>
        </div>
        <div class="fg">
          <label>Role</label>
          <div style="display:flex;align-items:center;gap:8px;padding:8px 0">
            <span class="badge badge-blue role-<?= $user['role'] ?>"><?= e($user['role_name']) ?></span>
            <span style="font-size:12px;color:var(--t3)">Managed by a superadmin</span>
          </div>
        </div>
        <?php if (!currentUserHasFullScope()): ?>
        <div class="fg">
          <label>Org Scope</label>
          <div style="font-size:12px;color:var(--warning);background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);padding:8px 12px;border-radius:8px">
            🔒 Your access is restricted to specific organisation groups.
          </div>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Save Profile</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-hd"><span class="card-title">🔐 Change Password</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="password">
        <div class="fg">
          <label>Current Password</label>
          <input type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="fg">
          <label>New Password</label>
          <input type="password" name="new_password" autocomplete="new-password" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">Min 8 chars, 1 uppercase, 1 number</div>
        </div>
        <div class="fg">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit">Change Password</button>
      </form>
    </div>
  </div>

</div>

<!-- Session Info -->
<div class="card">
  <div class="card-hd"><span class="card-title">ℹ️ Session Details</span></div>
  <div class="card-body">
    <table>
      <tr><td style="color:var(--t2);width:160px">Logged in as</td><td><?= e($user['username']) ?></td></tr>
      <tr><td style="color:var(--t2)">Role</td><td class="role-<?= $user['role'] ?>"><?= e($user['role_name']) ?></td></tr>
      <tr><td style="color:var(--t2)">Last login</td><td><?= fmtDate($user['last_login']) ?></td></tr>
      <tr><td style="color:var(--t2)">IP address</td><td><?= e($_SERVER['REMOTE_ADDR'] ?? '—') ?></td></tr>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
