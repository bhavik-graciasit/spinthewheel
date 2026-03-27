<?php
/**
 * GET /api/v1/project_stats.php?project_id=N
 * Returns participant count for a project (admin only)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$projectId = (int)($_GET['project_id'] ?? 0);
if (!$projectId) { jsonResponse(['success' => false, 'message' => 'Missing project_id']); }

$db = Database::getInstance();
$stats = $db->fetchOne("SELECT COUNT(*) AS participants, MAX(spun_at) AS last_spin FROM participants WHERE project_id = ?", [$projectId]);

jsonResponse([
    'success'      => true,
    'participants' => (int)($stats['participants'] ?? 0),
    'last_spin'    => $stats['last_spin'],
]);
