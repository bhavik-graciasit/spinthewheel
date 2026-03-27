<?php
/**
 * SpinWheel Pro V2 — Helper Functions
 */

// ── STRING / FORMAT ───────────────────────────────────────────────────────────

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function formatNumber(int|float $n): string {
    return number_format($n);
}

function timeAgo(\DateTimeInterface|string|null $dt): string {
    if (!$dt) return 'Never';
    $ts  = $dt instanceof \DateTimeInterface ? $dt->getTimestamp() : strtotime((string)$dt);
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', $ts);
}

function truncate(string $s, int $len = 60): string {
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

function generateToken(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

// ── HTTP / REQUEST ────────────────────────────────────────────────────────────

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function isAjax(): bool { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

// ── FLASH MESSAGES ────────────────────────────────────────────────────────────

function flashSet(string $type, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashGet(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

// ── PAGINATION ────────────────────────────────────────────────────────────────

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return compact('total', 'perPage', 'totalPages', 'currentPage', 'offset');
}

// ── ORGANISATION / GROUP HELPERS ──────────────────────────────────────────────

/**
 * Get all descendant group IDs including the root group itself.
 * Used for org-scoped project filtering.
 */
function getDescendantGroupIds(int $groupId): array {
    require_once __DIR__ . '/Database.php';
    $db   = Database::getInstance();
    $ids  = [$groupId];
    $queue = [$groupId];
    $seen  = [];
    while (!empty($queue)) {
        $cur = array_shift($queue);
        if (isset($seen[$cur])) continue;
        $seen[$cur] = true;
        $children = $db->fetchAll("SELECT id FROM org_groups WHERE parent_id = ?", [$cur]);
        foreach ($children as $c) {
            $cid = (int)$c['id'];
            $ids[]   = $cid;
            $queue[] = $cid;
        }
    }
    return array_unique($ids);
}

/**
 * Get breadcrumb path for a group (root → leaf).
 */
function getGroupBreadcrumb(?int $groupId): array {
    if (!$groupId) return [];
    require_once __DIR__ . '/Database.php';
    $db   = Database::getInstance();
    $path = [];
    $id   = $groupId;
    $seen = [];
    while ($id && !isset($seen[$id])) {
        $seen[$id] = true;
        $row = $db->fetchOne("SELECT id, name, level_label, color, parent_id FROM org_groups WHERE id = ?", [$id]);
        if (!$row) break;
        array_unshift($path, [
            'id'    => (int)$row['id'],
            'name'  => $row['name'],
            'label' => $row['level_label'] ?? 'Group',
            'color' => $row['color'] ?? '#6366f1',
        ]);
        $id = $row['parent_id'] ? (int)$row['parent_id'] : 0;
    }
    return $path;
}

/**
 * Flat ordered list of groups with depth for select dropdowns.
 */
function getFlatGroupList(bool $activeOnly = true): array {
    require_once __DIR__ . '/Database.php';
    $db = Database::getInstance();
    $where = $activeOnly ? "WHERE status = 'active'" : "";
    $all   = $db->fetchAll("SELECT * FROM org_groups $where ORDER BY sort_order, name");

    // Index by id
    $indexed = [];
    foreach ($all as $g) $indexed[(int)$g['id']] = $g;

    // Build tree order via DFS
    $result = [];
    $visited = [];

    $walk = function(array &$nodes, ?int $parentId, int $depth) use (&$walk, &$result, &$visited): void {
        foreach ($nodes as $g) {
            $pid = $g['parent_id'] ? (int)$g['parent_id'] : null;
            if ($pid !== $parentId) continue;
            $id = (int)$g['id'];
            if (isset($visited[$id])) continue;
            $visited[$id] = true;
            $g['_depth'] = $depth;
            $result[] = $g;
            $walk($nodes, $id, $depth + 1);
        }
    };
    $walk($all, null, 0);
    return $result;
}

/**
 * Build a nested tree array from flat org_groups rows.
 */
function buildGroupTree(array $groups, ?int $parentId = null): array {
    $tree = [];
    foreach ($groups as $g) {
        $pid = $g['parent_id'] ? (int)$g['parent_id'] : null;
        if ($pid === $parentId) {
            $g['children'] = buildGroupTree($groups, (int)$g['id']);
            $tree[] = $g;
        }
    }
    return $tree;
}

// ── VALIDATION ────────────────────────────────────────────────────────────────

function validateEmail(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword(string $pw): array {
    $errors = [];
    if (strlen($pw) < 8)                              $errors[] = 'At least 8 characters.';
    if (!preg_match('/[A-Z]/', $pw))                  $errors[] = 'At least one uppercase letter.';
    if (!preg_match('/[0-9]/', $pw))                  $errors[] = 'At least one number.';
    return $errors;
}

// ── DATE ──────────────────────────────────────────────────────────────────────

function fmtDate(?string $dt, string $format = 'd M Y H:i'): string {
    if (!$dt) return '—';
    return date($format, strtotime($dt));
}

// ── COLOUR ────────────────────────────────────────────────────────────────────

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

function contrastColor(string $hex): string {
    [$r,$g,$b] = hexToRgb($hex);
    $lum = (0.299*$r + 0.587*$g + 0.114*$b) / 255;
    return $lum > 0.5 ? '#111827' : '#ffffff';
}
