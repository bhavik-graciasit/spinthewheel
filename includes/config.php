<?php
/**
 * SpinWheel Pro V2 — Configuration
 * Edit DB credentials below. APP_URL auto-detects.
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'kkesrrzjqz');
define('DB_USER',    'kkesrrzjqz');
define('DB_PASS',    '5Tasyc1qxY');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    3306);

// ── APP URL (auto-detected) ───────────────────────────
if (!defined('APP_URL')) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $realApp = realpath(__DIR__ . '/..');
    $realDoc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $subPath = '';
    if ($realDoc && $realApp && str_starts_with($realApp, $realDoc)) {
        $subPath = str_replace('\\', '/', substr($realApp, strlen($realDoc)));
    }
    define('APP_URL', rtrim($scheme . '://' . $host . $subPath, '/'));
}

define('APP_NAME',    'SpinWheel Pro');
define('APP_VERSION', '2.1.0');
define('APP_DIR',     realpath(__DIR__ . '/..'));

// ── SECURITY ──────────────────────────────────────────
define('SESSION_LIFETIME', 7200);      // 2 hours
define('DEBUG_MODE',       false);     // Set true only for local dev

// ── UPLOAD ────────────────────────────────────────────
define('UPLOAD_DIR', APP_DIR . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_UPLOAD', 10 * 1024 * 1024);

// ── RBAC ROLE CONSTANTS ───────────────────────────────
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_ADMIN',      'admin');
define('ROLE_MANAGER',    'manager');
define('ROLE_VIEWER',     'viewer');

// Role hierarchy: higher number = more privilege
define('ROLE_WEIGHTS', [
    ROLE_VIEWER      => 1,
    ROLE_MANAGER     => 2,
    ROLE_ADMIN       => 3,
    ROLE_SUPERADMIN  => 4,
]);

// ── PERMISSIONS MAP ───────────────────────────────────
// Each permission key maps to the minimum role required
define('PERMISSIONS', [
    // Dashboard
    'dashboard.view'          => ROLE_VIEWER,
    // Projects
    'projects.view'           => ROLE_VIEWER,
    'projects.create'         => ROLE_MANAGER,
    'projects.edit'           => ROLE_MANAGER,
    'projects.delete'         => ROLE_ADMIN,
    // Participants
    'participants.view'       => ROLE_VIEWER,
    'participants.delete'     => ROLE_MANAGER,
    // Export
    'export.csv'              => ROLE_VIEWER,
    'export.json'             => ROLE_MANAGER,
    // Organisation
    'organisation.view'       => ROLE_VIEWER,
    'organisation.manage'     => ROLE_ADMIN,
    // Users & Roles
    'users.view'              => ROLE_ADMIN,
    'users.create'            => ROLE_SUPERADMIN,
    'users.edit'              => ROLE_ADMIN,
    'users.delete'            => ROLE_SUPERADMIN,
    'roles.view'              => ROLE_ADMIN,
    'roles.manage'            => ROLE_SUPERADMIN,
    // Settings
    'settings.view'           => ROLE_ADMIN,
    'settings.edit'           => ROLE_SUPERADMIN,
]);
