<?php
/**
 * SpinWheel Pro V2 — Installer
 * Run once at: https://yourdomain.com/install.php
 * DELETE THIS FILE immediately after successful install.
 */
require_once __DIR__ . '/includes/config.php';

// Optional protection key
$INSTALL_KEY = 'swp_install_2024';
if (($_GET['key'] ?? '') !== $INSTALL_KEY) {
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>Access: <code>/install.php?key=swp_install_2024</code></p>');
}

$steps  = [];
$errors = [];

try {
    // Connect to existing DB (Cloudways pre-creates the DB)
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $steps[] = ['ok', 'Connected to database: ' . DB_NAME];

    // ── TABLES ─────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username      VARCHAR(60) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email         VARCHAR(191),
        role          ENUM('owner','admin','viewer') NOT NULL DEFAULT 'admin',
        status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
        last_login    DATETIME,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: admin_users'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(200) NOT NULL,
        description TEXT,
        color       VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        token       VARCHAR(100) NOT NULL UNIQUE,
        status      ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: projects'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_options (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id   INT UNSIGNED NOT NULL,
        name         VARCHAR(200) NOT NULL,
        probability  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        color        VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        text_color   VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
        success_msg  TEXT,
        status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
        sort_order   SMALLINT NOT NULL DEFAULT 0,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: wheel_options'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS form_config (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id   INT UNSIGNED NOT NULL UNIQUE,
        form_name    VARCHAR(200) NOT NULL DEFAULT 'Entry Form',
        description  TEXT,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: form_config'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS form_questions (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id    INT UNSIGNED NOT NULL,
        question_text VARCHAR(500) NOT NULL,
        field_type    ENUM('short','paragraph','email','radio','checkbox','dropdown','file','rating','date','ranking') NOT NULL DEFAULT 'short',
        is_required   TINYINT(1) NOT NULL DEFAULT 0,
        sort_order    SMALLINT NOT NULL DEFAULT 0,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_proj (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: form_questions'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS form_options (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id INT UNSIGNED NOT NULL,
        option_text VARCHAR(300) NOT NULL,
        sort_order  SMALLINT NOT NULL DEFAULT 0,
        FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: form_options'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS participants (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id  INT UNSIGNED NOT NULL,
        result_id   INT UNSIGNED,
        result_name VARCHAR(200),
        ip_address  VARCHAR(45),
        user_agent  TEXT,
        spun_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_proj (project_id),
        INDEX idx_date (spun_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: participants'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS participant_answers (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        participant_id INT UNSIGNED NOT NULL,
        question_id    INT UNSIGNED NOT NULL,
        answer_text    TEXT,
        file_path      VARCHAR(500),
        file_name      VARCHAR(300),
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id)    REFERENCES form_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: participant_answers'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT,
        updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: platform_settings'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        api_key    VARCHAR(100) NOT NULL UNIQUE,
        label      VARCHAR(100),
        status     ENUM('active','revoked') NOT NULL DEFAULT 'active',
        last_used  DATETIME,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Table: api_keys'];

    // ── SEED ADMIN USER ────────────────────────────────
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if (!$exists) {
        $hash = password_hash(ADMIN_PASSWORD_PLAIN, PASSWORD_BCRYPT, ['cost'=>12]);
        $pdo->prepare("INSERT INTO admin_users (username,password_hash,role) VALUES (?,?,'owner')")
            ->execute([ADMIN_USERNAME, $hash]);
        $steps[] = ['ok', 'Admin user created: ' . ADMIN_USERNAME];
    } else {
        // Update password hash in case it changed
        $hash = password_hash(ADMIN_PASSWORD_PLAIN, PASSWORD_BCRYPT, ['cost'=>12]);
        $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE username=?")
            ->execute([$hash, ADMIN_USERNAME]);
        $steps[] = ['ok', 'Admin password refreshed for: ' . ADMIN_USERNAME];
    }

    // ── SEED PLATFORM SETTINGS ──────────────────────────
    $defaults = [
        ['tenant_company',  TENANT_COMPANY ?: 'SpinWheel Pro'],
        ['tenant_domain',   TENANT_DOMAIN  ?: ''],
        ['tenant_plan',     TENANT_PLAN],
        ['plan_expires',    TENANT_PLAN_EXPIRES],
        ['max_projects',    (string)TENANT_MAX_PROJECTS],
        ['max_spins_month', (string)TENANT_MAX_SPINS],
        ['timezone',        'UTC+0'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO platform_settings (setting_key,setting_value) VALUES (?,?)");
    foreach ($defaults as $d) $ins->execute($d);
    $steps[] = ['ok', 'Platform settings seeded'];

    // ── SEED API KEY ───────────────────────────────────
    $keyExists = (int)$pdo->query("SELECT COUNT(*) FROM api_keys WHERE status='active'")->fetchColumn();
    if (!$keyExists) {
        $apiKey = API_KEY_PREFIX . bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO api_keys (api_key,label) VALUES (?,'Default')")->execute([$apiKey]);
        $steps[] = ['ok', 'API key generated: ' . $apiKey];
    } else {
        $steps[] = ['ok', 'API key already exists'];
    }

    // ── UPLOADS DIR ───────────────────────────────────
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (!file_exists(UPLOAD_DIR . '.htaccess')) {
        file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n");
    }
    $steps[] = ['ok', 'Uploads directory ready: ' . UPLOAD_DIR];

    $steps[] = ['ok', '✅ ALL DONE! Delete install.php now.'];

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SpinWheel Install</title>
<style>body{font-family:system-ui,sans-serif;background:#04060c;color:#e2e8f0;padding:40px 20px;max-width:700px;margin:0 auto}
h1{color:#818cf8;margin-bottom:4px}p{color:#64748b;margin-bottom:24px}
.step{padding:9px 14px;margin:5px 0;border-radius:7px;display:flex;align-items:flex-start;gap:10px;font-size:13px}
.ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#34d399}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;padding:16px;border-radius:10px;margin-top:22px;font-size:13px;line-height:1.8}
a{color:#818cf8}</style></head><body>
<h1>🎡 SpinWheel Pro V2 — Installer</h1>
<p>Database: <strong><?= DB_NAME ?></strong> &nbsp;|&nbsp; App URL: <strong><?= htmlspecialchars(APP_URL) ?></strong></p>
<?php foreach($steps as [$type,$msg]): ?>
  <div class="step <?= $type ?>"><span><?= $type==='ok'?'✓':'✗' ?></span><?= htmlspecialchars($msg) ?></div>
<?php endforeach; ?>
<?php foreach($errors as $e): ?>
  <div class="step err"><span>✗</span><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if(empty($errors)): ?>
<div class="warn">
  <strong>⚠️ Next steps — do these NOW:</strong><br>
  1. <strong>Delete install.php</strong> from your server<br>
  2. <a href="<?= APP_URL ?>/admin/">Login to Admin Panel →</a><br>
  3. Username: <strong><?= ADMIN_USERNAME ?></strong> &nbsp; Password: <strong><?= ADMIN_PASSWORD_PLAIN ?></strong><br>
  4. Create your first project and test the spin page<br>
  5. Set DEBUG_MODE = false in includes/config.php when everything works
</div>
<?php endif; ?>
</body></html>
