<?php
/**
 * SpinWheel Pro V2 — RBAC Database Migration
 * Run once via browser after uploading.
 * Requires existing admin to be logged in, or use the ?key= bypass.
 */
$SECRET_KEY = 'swp_rbac_migrate_2025';  // Change this, delete file after running

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

// Auth: either logged in admin or secret key
$authed = false;
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    startSecureSession();
    if (isAdminLoggedIn()) $authed = true;
}
if (!$authed && ($_GET['key'] ?? '') !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Add ?key=YOUR_SECRET_KEY or log in as admin first.');
}

$db  = Database::getInstance();
$pdo = $db->getConnection();

$results = [];
$errors  = [];

function step(string $label, callable $fn) use (&$results, &$errors): void {
    try {
        $fn();
        $results[] = ['ok', $label];
    } catch (Throwable $e) {
        $errors[] = "$label — " . $e->getMessage();
    }
}

// ── 1. admin_roles table ──────────────────────────────
step('Create admin_roles table', function() use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_roles (
        id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(32) NOT NULL UNIQUE,
        name        VARCHAR(80) NOT NULL,
        description TEXT,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

// ── 2. Seed roles ─────────────────────────────────────
step('Seed default roles', function() use ($pdo) {
    $roles = [
        ['superadmin', 'Super Admin', 'Full system access including user management'],
        ['admin',      'Admin',       'Manage organisation, users, and all data'],
        ['manager',    'Manager',     'Create and manage projects and participants'],
        ['viewer',     'Viewer',      'Read-only access to all data'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_roles (slug,name,description) VALUES (?,?,?)");
    foreach ($roles as $r) $stmt->execute($r);
});

// ── 3. admin_users table ──────────────────────────────
step('Create admin_users table', function() use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username        VARCHAR(64) NOT NULL UNIQUE,
        display_name    VARCHAR(120),
        email           VARCHAR(180) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        role_id         TINYINT UNSIGNED NOT NULL DEFAULT 4,
        org_scope_ids   VARCHAR(500) DEFAULT NULL COMMENT 'CSV of org_group IDs, NULL = full access',
        status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
        reset_token     VARCHAR(64) DEFAULT NULL,
        reset_expires   DATETIME DEFAULT NULL,
        last_login      DATETIME DEFAULT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES admin_roles(id),
        INDEX idx_status (status),
        INDEX idx_reset  (reset_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

// ── 4. Migrate existing admin accounts ───────────────
step('Migrate legacy admin credentials to admin_users', function() use ($pdo, $db) {
    // Only if no users exist yet
    $count = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($count > 0) return; // Already migrated

    // Get superadmin role ID
    $roleId = $pdo->query("SELECT id FROM admin_roles WHERE slug='superadmin'")->fetchColumn();

    // Attempt to pull credentials from legacy config constants
    $username = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
    $email    = defined('ADMIN_EMAIL')    ? ADMIN_EMAIL    : 'admin@localhost';

    // Check if password hash exists in a legacy table
    $legacyPw = null;
    try {
        $row = $db->fetchOne("SELECT password_hash FROM admin_accounts LIMIT 1");
        if ($row) $legacyPw = $row['password_hash'];
    } catch (Throwable $e) {}

    // Fallback: create with a new random password
    if (!$legacyPw) {
        $tempPw   = bin2hex(random_bytes(8));
        $legacyPw = password_hash($tempPw, PASSWORD_BCRYPT);
        file_put_contents(__DIR__ . '/../../TEMP_ADMIN_PW.txt',
            "Temporary admin password (delete this file!): $tempPw\n");
    }

    $stmt = $pdo->prepare("INSERT INTO admin_users (username,display_name,email,password_hash,role_id,status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$username, 'Super Admin', $email, $legacyPw, $roleId, 'active']);
});

// ── 5. org_groups table ───────────────────────────────
step('Create org_groups table', function() use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_groups (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(200) NOT NULL,
        slug         VARCHAR(140) NOT NULL UNIQUE,
        level_label  VARCHAR(100) DEFAULT NULL,
        parent_id    INT UNSIGNED DEFAULT NULL,
        color        VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        sort_order   SMALLINT NOT NULL DEFAULT 0,
        status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES org_groups(id) ON DELETE SET NULL,
        INDEX idx_parent (parent_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

// ── 6. projects: add group_id + spin_duration_ms ──────
step('Add projects.group_id column', function() use ($pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM projects LIKE 'group_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE projects
            ADD COLUMN group_id INT UNSIGNED DEFAULT NULL,
            ADD CONSTRAINT fk_proj_group FOREIGN KEY (group_id) REFERENCES org_groups(id) ON DELETE SET NULL,
            ADD INDEX idx_proj_group (group_id)");
    }
});

step('Add projects.spin_duration_ms column', function() use ($pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM projects LIKE 'spin_duration_ms'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN spin_duration_ms INT NOT NULL DEFAULT 5000");
    }
});

// ── 7. participants table safety check ────────────────
step('Verify participants table exists', function() use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS participants (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id  INT UNSIGNED NOT NULL,
        name        VARCHAR(200) NOT NULL,
        email       VARCHAR(200) NOT NULL,
        phone       VARCHAR(50) DEFAULT NULL,
        prize_won   VARCHAR(300) DEFAULT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project (project_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

// ── Render results ────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RBAC Migration — <?= APP_NAME ?></title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0d1117;color:#f0f6fc;padding:40px;max-width:700px;margin:0 auto}
h1{font-size:20px;margin-bottom:8px}p{color:#8b949e;font-size:13px;margin-bottom:24px}
.step{display:flex;align-items:flex-start;gap:12px;padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:13px}
.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25)}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444}
.icon{flex-shrink:0;margin-top:1px}
.warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);color:#f59e0b;border-radius:8px;padding:12px 16px;margin-top:20px;font-size:13px}
a{color:#818cf8}
.btn{display:inline-block;background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;margin-top:20px}
</style>
</head>
<body>
<h1>🎡 <?= APP_NAME ?> — RBAC Migration</h1>
<p>Running database migration for multi-role access control system.</p>

<?php foreach ($results as [$type, $msg]): ?>
  <div class="step ok"><span class="icon">✅</span><?= htmlspecialchars($msg) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
  <div class="step err"><span class="icon">❌</span><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if (empty($errors)): ?>
  <div class="step ok" style="margin-top:14px;font-weight:600">
    <span class="icon">🎉</span>Migration complete! All RBAC tables are ready.
  </div>
  <div class="warn">
    ⚠️ <strong>Delete this file immediately</strong> — it should not be accessible in production.<br>
    <code style="font-family:monospace;font-size:12px">rm admin/migrate_rbac.php</code>
    <br><br>
    Next: <a href="<?= APP_URL ?>/admin/login.php">Sign in</a> →
    <a href="<?= APP_URL ?>/admin/users.php">Manage users</a>
  </div>
<?php else: ?>
  <div class="warn">
    ⚠️ Some steps failed. Check your database connection and permissions, then refresh to retry.
  </div>
<?php endif; ?>

<a href="<?= APP_URL ?>/admin/dashboard.php" class="btn">→ Go to Dashboard</a>
</body>
</html>
