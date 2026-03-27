<?php
/**
 * SpinWheel Pro V2 — RBAC Authentication
 */
require_once __DIR__ . '/config.php';

// ── SESSION ───────────────────────────────────────────────────────────────────

function startSecureSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── AUTH CHECKS ───────────────────────────────────────────────────────────────

function isAdminLoggedIn(): bool {
    startSecureSession();
    if (empty($_SESSION['admin_logged_in'])) return false;
    if ($_SESSION['admin_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_destroy(); return false;
    }
    if ((time() - ($_SESSION['admin_last_activity'] ?? 0)) > SESSION_LIFETIME) {
        session_destroy(); return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return true;
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}

function requirePermission(string $permission): void {
    requireAdmin();
    if (!currentUserCan($permission)) {
        http_response_code(403);
        include __DIR__ . '/../admin/403.php';
        exit;
    }
}

// ── LOGIN / LOGOUT ────────────────────────────────────────────────────────────

function adminLogin(string $username, string $password): array {
    startSecureSession();
    require_once __DIR__ . '/Database.php';
    $db  = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT u.id, u.username, u.password_hash, u.display_name, u.email,
                u.status, r.slug AS role, r.name AS role_name,
                u.org_scope_ids
         FROM   admin_users u
         JOIN   admin_roles r ON r.id = u.role_id
         WHERE  u.username = ? LIMIT 1",
        [$username]
    );

    if (!$row)                                       return ['ok' => false, 'error' => 'Invalid credentials.'];
    if ($row['status'] !== 'active')                 return ['ok' => false, 'error' => 'Account is disabled.'];
    if (!password_verify($password, $row['password_hash']))
                                                     return ['ok' => false, 'error' => 'Invalid credentials.'];

    session_regenerate_id(true);
    $_SESSION['admin_logged_in']    = true;
    $_SESSION['admin_id']           = (int)$row['id'];
    $_SESSION['admin_username']     = $row['username'];
    $_SESSION['admin_display_name'] = $row['display_name'] ?: $row['username'];
    $_SESSION['admin_email']        = $row['email'];
    $_SESSION['admin_role']         = $row['role'];
    $_SESSION['admin_role_name']    = $row['role_name'];
    $_SESSION['admin_org_scope']    = $row['org_scope_ids']
        ? array_map('intval', explode(',', $row['org_scope_ids']))
        : [];
    $_SESSION['admin_ip']           = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['admin_last_activity'] = time();

    $db->execute("UPDATE admin_users SET last_login = NOW() WHERE id = ?", [$row['id']]);

    return ['ok' => true];
}

function adminLogout(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CURRENT USER HELPERS ──────────────────────────────────────────────────────

function currentUserId(): int          { return (int)($_SESSION['admin_id'] ?? 0); }
function currentUserRole(): string     { return $_SESSION['admin_role'] ?? ROLE_VIEWER; }
function currentUserName(): string     { return $_SESSION['admin_display_name'] ?? 'Unknown'; }
function currentUserEmail(): string    { return $_SESSION['admin_email'] ?? ''; }
function currentUserRoleName(): string { return $_SESSION['admin_role_name'] ?? 'Viewer'; }

/**
 * Org scope: list of group IDs this user can access (empty = all groups).
 * Superadmin always has full access.
 */
function currentUserOrgScope(): array {
    if (currentUserRole() === ROLE_SUPERADMIN) return [];
    return $_SESSION['admin_org_scope'] ?? [];
}

function currentUserHasFullScope(): bool {
    return currentUserRole() === ROLE_SUPERADMIN || empty(currentUserOrgScope());
}

// ── RBAC PERMISSION CHECK ─────────────────────────────────────────────────────

function currentUserCan(string $permission): bool {
    $role        = currentUserRole();
    $roleWeights = ROLE_WEIGHTS;
    $permissions = PERMISSIONS;

    if ($role === ROLE_SUPERADMIN) return true;               // superadmin can do everything
    if (!isset($permissions[$permission])) return false;      // unknown permission = deny

    $required = $permissions[$permission];
    $userW    = $roleWeights[$role]     ?? 0;
    $reqW     = $roleWeights[$required] ?? 99;

    return $userW >= $reqW;
}

function roleWeight(string $role): int {
    return ROLE_WEIGHTS[$role] ?? 0;
}

function isAtLeast(string $minRole): bool {
    return roleWeight(currentUserRole()) >= roleWeight($minRole);
}

// ── ORG SCOPE FILTERING ───────────────────────────────────────────────────────

/**
 * Returns SQL fragment + params to filter projects to the current user's org scope.
 * Usage: [$sql, $params] = orgScopeFilter('p');
 *   $query = "SELECT * FROM projects p WHERE 1=1 $sql";
 *   $stmt->execute($params);
 */
function orgScopeFilter(string $tableAlias = 'p'): array {
    if (currentUserHasFullScope()) return ['', []];

    $scope = currentUserOrgScope();
    if (empty($scope)) return ['', []]; // no scope restriction

    // Expand scope to include all descendant groups
    require_once __DIR__ . '/helpers.php';
    $allIds = [];
    foreach ($scope as $gid) {
        foreach (getDescendantGroupIds($gid) as $did) {
            $allIds[] = $did;
        }
    }
    $allIds = array_unique($allIds);

    if (empty($allIds)) {
        // User has a scope set but it resolves to nothing — deny all
        return ['AND 1=0', []];
    }

    $placeholders = implode(',', array_fill(0, count($allIds), '?'));
    return ["AND {$tableAlias}.group_id IN ({$placeholders})", $allIds];
}

// ── PASSWORD RESET TOKEN ──────────────────────────────────────────────────────

function generateResetToken(string $email): ?string {
    require_once __DIR__ . '/Database.php';
    $db  = Database::getInstance();
    $row = $db->fetchOne("SELECT id FROM admin_users WHERE email = ? AND status = 'active'", [$email]);
    if (!$row) return null;

    $token  = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 3600);

    $db->execute(
        "UPDATE admin_users SET reset_token = ?, reset_expires = ? WHERE id = ?",
        [$token, $expiry, $row['id']]
    );
    return $token;
}

function validateResetToken(string $token): ?array {
    require_once __DIR__ . '/Database.php';
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT id, username, email FROM admin_users
         WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'",
        [$token]
    );
}

function consumeResetToken(int $userId, string $newPasswordHash): void {
    require_once __DIR__ . '/Database.php';
    Database::getInstance()->execute(
        "UPDATE admin_users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
        [$newPasswordHash, $userId]
    );
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}
