<?php
$pageTitle  = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('settings.view');

$db = Database::getInstance();

// ── Ensure settings table exists & seed defaults ──────
$db->execute("CREATE TABLE IF NOT EXISTS app_settings (
    `key`       VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`     TEXT,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$defaults = [
    'app_name'         => APP_NAME,
    'timezone'         => 'UTC+8',
    'date_format'      => 'd M Y H:i',
    'smtp_host'        => '',
    'smtp_port'        => '587',
    'smtp_user'        => '',
    'smtp_pass'        => '',
    'smtp_from_name'   => APP_NAME,
    'smtp_from_email'  => '',
    'api_key'          => bin2hex(random_bytes(20)),
    'webhook_secret'   => bin2hex(random_bytes(16)),
    'maintenance_mode' => '0',
    'debug_mode'       => '0',
    'blocked_domains'  => '',
    'max_spins_per_ip' => '0',
    'tenant_name'      => APP_NAME,
    'tenant_plan'      => 'pro',
    'tenant_expires'   => '2026-12-31',
];

// Load all settings into a key-value array
$rows = $db->fetchAll("SELECT `key`, `value` FROM app_settings");
$s    = array_column($rows, 'value', 'key');

// Seed missing defaults without overwriting existing
foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $s)) {
        $db->execute("INSERT IGNORE INTO app_settings (`key`,`value`) VALUES (?,?)", [$k, $v]);
        $s[$k] = $v;
    }
}

// ── Handle POST ───────────────────────────────────────
$saved  = false;
$errors = [];

if (isPost() && currentUserCan('settings.edit')) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'general') {
        $updates = [
            'app_name'         => trim(post('app_name')) ?: APP_NAME,
            'timezone'         => post('timezone'),
            'date_format'      => trim(post('date_format')) ?: 'd M Y H:i',
            'maintenance_mode' => post('maintenance_mode') === '1' ? '1' : '0',
            'debug_mode'       => post('debug_mode') === '1' ? '1' : '0',
            'blocked_domains'  => trim(post('blocked_domains')),
            'max_spins_per_ip' => max(0, (int)post('max_spins_per_ip')),
        ];
        foreach ($updates as $k => $v) {
            $db->execute("INSERT INTO app_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?", [$k, $v, $v]);
            $s[$k] = $v;
        }
        flashSet('success', 'General settings saved.');
        redirect(APP_URL . '/admin/settings.php#general');
    }

    if ($action === 'smtp') {
        $updates = [
            'smtp_host'       => trim(post('smtp_host')),
            'smtp_port'       => (int)post('smtp_port') ?: 587,
            'smtp_user'       => trim(post('smtp_user')),
            'smtp_from_name'  => trim(post('smtp_from_name')) ?: APP_NAME,
            'smtp_from_email' => trim(post('smtp_from_email')),
        ];
        // Only update password if a new one was entered
        $newPass = post('smtp_pass');
        if ($newPass) $updates['smtp_pass'] = $newPass;

        foreach ($updates as $k => $v) {
            $db->execute("INSERT INTO app_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?", [$k, $v, $v]);
            $s[$k] = $v;
        }
        flashSet('success', 'SMTP settings saved.');
        redirect(APP_URL . '/admin/settings.php#smtp');
    }

    if ($action === 'rotate_api_key') {
        $newKey = bin2hex(random_bytes(20));
        $db->execute("INSERT INTO app_settings (`key`,`value`) VALUES ('api_key',?) ON DUPLICATE KEY UPDATE `value`=?", [$newKey, $newKey]);
        $s['api_key'] = $newKey;
        flashSet('success', 'API key rotated.');
        redirect(APP_URL . '/admin/settings.php#api');
    }

    if ($action === 'rotate_webhook_secret') {
        $newSec = bin2hex(random_bytes(16));
        $db->execute("INSERT INTO app_settings (`key`,`value`) VALUES ('webhook_secret',?) ON DUPLICATE KEY UPDATE `value`=?", [$newSec, $newSec]);
        $s['webhook_secret'] = $newSec;
        flashSet('success', 'Webhook secret rotated.');
        redirect(APP_URL . '/admin/settings.php#api');
    }
}

// ── Stats for system info ─────────────────────────────
$totalProjects    = (int)$db->fetchValue("SELECT COUNT(*) FROM projects");
$totalParticipants= (int)$db->fetchValue("SELECT COUNT(*) FROM participants");
$totalUsers       = (int)$db->fetchValue("SELECT COUNT(*) FROM admin_users");
$dbSize           = $db->fetchValue(
    "SELECT ROUND(SUM(data_length + index_length) / 1024, 1)
     FROM information_schema.tables
     WHERE table_schema = DATABASE()"
) ?? '?';

require_once __DIR__ . '/header.php';

$timezones = [
    'UTC-12'=>'UTC-12','UTC-11'=>'UTC-11','UTC-10'=>'UTC-10 (Hawaii)',
    'UTC-8' =>'UTC-8 (Los Angeles)','UTC-7'=>'UTC-7 (Denver)',
    'UTC-6' =>'UTC-6 (Chicago)','UTC-5'=>'UTC-5 (New York)',
    'UTC-4' =>'UTC-4 (Atlantic)','UTC-3'=>'UTC-3 (São Paulo)',
    'UTC+0' =>'UTC+0 (London)','UTC+1'=>'UTC+1 (Paris/Berlin)',
    'UTC+2' =>'UTC+2 (Cairo/Johannesburg)','UTC+3'=>'UTC+3 (Riyadh/Nairobi)',
    'UTC+4' =>'UTC+4 (Dubai/Baku)','UTC+5'=>'UTC+5 (Karachi)',
    'UTC+5:30'=>'UTC+5:30 (Mumbai/Delhi)','UTC+6'=>'UTC+6 (Dhaka)',
    'UTC+7' =>'UTC+7 (Bangkok/Jakarta)','UTC+8'=>'UTC+8 (Singapore/HK/KL)',
    'UTC+9' =>'UTC+9 (Tokyo/Seoul)','UTC+10'=>'UTC+10 (Sydney)',
    'UTC+12'=>'UTC+12 (Auckland)',
];
?>

<div style="display:flex;gap:20px;align-items:flex-start">

<!-- Left column: settings forms -->
<div style="flex:1;min-width:0">

<!-- ── GENERAL ────────────────────────────────────── -->
<div class="card" id="general">
  <div class="card-hd"><span class="card-title">⚙️ General</span></div>
  <div class="card-body">
    <?php if (!currentUserCan('settings.edit')): ?>
      <div class="alert alert-info">🔒 View-only. Super Admin required to edit settings.</div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="general">
      <div class="fg-row">
        <div class="fg">
          <label>Application Name</label>
          <input type="text" name="app_name" value="<?= e($s['app_name']) ?>"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
        <div class="fg">
          <label>Timezone</label>
          <select name="timezone" <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
            <?php foreach ($timezones as $v => $l): ?>
              <option value="<?= $v ?>" <?= $s['timezone']===$v?'selected':'' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="fg">
        <label>Date Format</label>
        <input type="text" name="date_format" value="<?= e($s['date_format']) ?>"
          placeholder="d M Y H:i"
          <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        <div style="font-size:11px;color:var(--t3);margin-top:4px">
          PHP date() format — current output: <strong><?= date($s['date_format'] ?: 'd M Y H:i') ?></strong>
        </div>
      </div>
      <div class="fg">
        <label>Blocked Email Domains <span style="color:var(--t3)">(comma-separated, e.g. spam.com,fake.net)</span></label>
        <input type="text" name="blocked_domains" value="<?= e($s['blocked_domains']) ?>"
          placeholder="spam.com, throwaway.io"
          <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
      </div>
      <div class="fg">
        <label>Max Spins per IP <span style="color:var(--t3)">(0 = unlimited)</span></label>
        <input type="number" name="max_spins_per_ip" value="<?= (int)$s['max_spins_per_ip'] ?>"
          min="0" style="max-width:140px"
          <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
      </div>
      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:14px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="maintenance_mode" value="1"
            <?= $s['maintenance_mode']==='1'?'checked':'' ?>
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>
            style="width:auto;margin:0">
          <span>Maintenance Mode <span style="color:var(--t3);font-size:11px">(spin page shows "Coming Soon")</span></span>
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="debug_mode" value="1"
            <?= $s['debug_mode']==='1'?'checked':'' ?>
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>
            style="width:auto;margin:0">
          <span>Debug Mode <span style="color:var(--danger);font-size:11px">(shows errors — disable in production!)</span></span>
        </label>
      </div>
      <?php if (currentUserCan('settings.edit')): ?>
      <button class="btn btn-primary" type="submit">Save General Settings</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ── SMTP ───────────────────────────────────────── -->
<div class="card" id="smtp">
  <div class="card-hd"><span class="card-title">📧 Email / SMTP</span></div>
  <div class="card-body">
    <div class="alert alert-info" style="margin-bottom:16px">
      Used for password reset emails. Supports any SMTP provider (Gmail, Mailgun, SendGrid, etc.)
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="smtp">
      <div class="fg-row">
        <div class="fg">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= e($s['smtp_host']) ?>"
            placeholder="smtp.mailgun.org"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
        <div class="fg">
          <label>Port</label>
          <input type="number" name="smtp_port" value="<?= e($s['smtp_port']) ?>"
            placeholder="587"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
      </div>
      <div class="fg-row">
        <div class="fg">
          <label>SMTP Username</label>
          <input type="text" name="smtp_user" value="<?= e($s['smtp_user']) ?>"
            autocomplete="off"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
        <div class="fg">
          <label>SMTP Password</label>
          <input type="password" name="smtp_pass" placeholder="Leave blank to keep current"
            autocomplete="new-password"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
      </div>
      <div class="fg-row">
        <div class="fg">
          <label>From Name</label>
          <input type="text" name="smtp_from_name" value="<?= e($s['smtp_from_name']) ?>"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
        <div class="fg">
          <label>From Email</label>
          <input type="email" name="smtp_from_email" value="<?= e($s['smtp_from_email']) ?>"
            placeholder="noreply@yourdomain.com"
            <?= !currentUserCan('settings.edit') ? 'disabled' : '' ?>>
        </div>
      </div>
      <?php if (currentUserCan('settings.edit')): ?>
      <button class="btn btn-primary" type="submit">Save SMTP Settings</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ── API & WEBHOOKS ─────────────────────────────── -->
<div class="card" id="api">
  <div class="card-hd"><span class="card-title">🔌 API & Webhooks</span></div>
  <div class="card-body">
    <div class="fg">
      <label>API Key <span style="color:var(--t3);font-size:11px">(send as X-API-Key header)</span></label>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="text" value="<?= e($s['api_key']) ?>" readonly
          onclick="this.select()" style="font-family:monospace;font-size:12px;cursor:pointer">
        <?php if (currentUserCan('settings.edit')): ?>
        <form method="POST" style="flex-shrink:0"
          onsubmit="return confirm('Rotate API key? Any integrations using the old key will break.')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="rotate_api_key">
          <button class="btn btn-ghost btn-sm" type="submit">🔄 Rotate</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="fg">
      <label>Webhook Secret <span style="color:var(--t3);font-size:11px">(HMAC-SHA256 signature key)</span></label>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="text" value="<?= e($s['webhook_secret']) ?>" readonly
          onclick="this.select()" style="font-family:monospace;font-size:12px;cursor:pointer">
        <?php if (currentUserCan('settings.edit')): ?>
        <form method="POST" style="flex-shrink:0"
          onsubmit="return confirm('Rotate webhook secret? Update all your webhook endpoints.')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="rotate_webhook_secret">
          <button class="btn btn-ghost btn-sm" type="submit">🔄 Rotate</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:12px;color:var(--t2)">
      <div style="font-weight:600;color:var(--t1);margin-bottom:8px">REST API endpoints (V3 Roadmap)</div>
      <div style="display:grid;gap:5px;font-family:monospace">
        <div><span style="color:var(--accent2)">GET</span>  <?= APP_URL ?>/api/v1/projects</div>
        <div><span style="color:var(--accent2)">GET</span>  <?= APP_URL ?>/api/v1/projects/{id}/participants</div>
        <div><span style="color:#f59e0b">POST</span> <?= APP_URL ?>/api/v1/spin</div>
        <div><span style="color:#22c55e">WH</span>   <?= APP_URL ?>/api/v1/webhook (outbound on each spin)</div>
      </div>
    </div>
  </div>
</div>

</div><!-- /left column -->

<!-- Right column: system info + tenant -->
<div style="width:300px;flex-shrink:0">

<!-- ── TENANT / PLAN ──────────────────────────────── -->
<div class="card">
  <div class="card-hd"><span class="card-title">🏷️ Tenant / Plan</span></div>
  <div class="card-body">
    <table style="width:100%">
      <tr>
        <td style="color:var(--t2);font-size:12px;padding:6px 0;width:110px">Tenant Name</td>
        <td style="font-size:13px"><?= e($s['tenant_name']) ?></td>
      </tr>
      <tr>
        <td style="color:var(--t2);font-size:12px;padding:6px 0">Plan</td>
        <td>
          <span class="badge badge-blue" style="text-transform:uppercase;font-size:10px">
            <?= e($s['tenant_plan']) ?>
          </span>
        </td>
      </tr>
      <tr>
        <td style="color:var(--t2);font-size:12px;padding:6px 0">Expires</td>
        <td style="font-size:13px"><?= fmtDate($s['tenant_expires'], 'd M Y') ?></td>
      </tr>
      <tr>
        <td style="color:var(--t2);font-size:12px;padding:6px 0">App URL</td>
        <td style="font-size:11px;font-family:monospace;word-break:break-all;color:var(--t2)"><?= APP_URL ?></td>
      </tr>
    </table>
    <div style="margin-top:12px;font-size:11px;color:var(--t3)">
      Billing management UI coming in V3. To update your plan contact support.
    </div>
  </div>
</div>

<!-- ── SYSTEM INFO ────────────────────────────────── -->
<div class="card">
  <div class="card-hd"><span class="card-title">ℹ️ System Info</span></div>
  <div class="card-body">
    <?php
    $info = [
      'Version'       => APP_VERSION,
      'PHP'           => PHP_VERSION,
      'DB Engine'     => $db->fetchValue("SELECT VERSION()") ?? '?',
      'DB Size'       => $dbSize . ' KB',
      'Projects'      => number_format($totalProjects),
      'Participants'  => number_format($totalParticipants),
      'Admin Users'   => $totalUsers,
      'Session'       => session_id() ? 'Active' : 'None',
      'HTTPS'         => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '✓ Yes' : '✗ No',
      'Debug Mode'    => $s['debug_mode']==='1' ? '<span style="color:var(--danger)">ON</span>' : '<span style="color:var(--success)">OFF</span>',
      'Maintenance'   => $s['maintenance_mode']==='1' ? '<span style="color:var(--warning)">ON</span>' : 'Off',
    ];
    foreach ($info as $k => $v): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:12px">
      <span style="color:var(--t2)"><?= $k ?></span>
      <span><?= $v ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── QUICK LINKS ────────────────────────────────── -->
<div class="card">
  <div class="card-hd"><span class="card-title">🔗 Quick Links</span></div>
  <div class="card-body" style="display:grid;gap:8px">
    <?php
    $links = [
      ['Admin Dashboard',    APP_URL . '/admin/dashboard.php',    '📊'],
      ['Manage Users',       APP_URL . '/admin/users.php',        '👤'],
      ['Organisation',       APP_URL . '/admin/organisation.php', '🏢'],
      ['View Roles',         APP_URL . '/admin/roles.php',        '🔑'],
      ['Export Data',        APP_URL . '/admin/export.php',       '📤'],
    ];
    foreach ($links as [$label, $url, $icon]): ?>
    <a href="<?= $url ?>" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;font-size:13px;color:var(--t1);text-decoration:none;transition:border .15s"
       onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
      <span><?= $icon ?></span><?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

</div><!-- /right column -->
</div><!-- /flex wrapper -->

<?php require_once __DIR__ . '/footer.php'; ?>
