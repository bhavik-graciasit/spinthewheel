<?php
$pageTitle  = 'Export';
$activePage = 'export';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requirePermission('export.csv');

$db = Database::getInstance();
[$scopeSql, $scopeParams] = orgScopeFilter('proj');

// Load scoped projects for dropdown
$projects = $db->fetchAll(
    "SELECT proj.id, proj.name FROM projects proj WHERE 1=1 $scopeSql ORDER BY proj.name",
    $scopeParams
);

// ── Handle export download ────────────────────────────
$format  = get('format');
$projF   = (int)get('project');
$dateFrom = trim(get('date_from'));
$dateTo   = trim(get('date_to'));

if (in_array($format, ['csv', 'json'])) {
    if ($format === 'json') requirePermission('export.json');

    // Scoped project IDs
    $scopedProjectIds = array_column($projects, 'id');
    if ($projF && in_array($projF, $scopedProjectIds)) {
        $filterIds = [$projF];
    } else {
        $filterIds = $scopedProjectIds;
    }

    $where  = "WHERE 1=1";
    $params = [];
    if ($filterIds) {
        $ph    = implode(',', array_fill(0, count($filterIds), '?'));
        $where .= " AND pt.project_id IN ($ph)";
        $params = array_merge($params, $filterIds);
    } else {
        $where .= " AND 1=0";
    }
    if ($dateFrom) { $where .= " AND DATE(pt.created_at) >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $where .= " AND DATE(pt.created_at) <= ?"; $params[] = $dateTo; }

    $rows = $db->fetchAll(
        "SELECT pt.id, pt.name, pt.email, pt.phone, pt.prize_won,
                pt.created_at, proj.name AS project_name,
                og.name AS group_name
         FROM participants pt
         LEFT JOIN projects proj ON proj.id = pt.project_id
         LEFT JOIN org_groups og ON og.id = proj.group_id
         $where
         ORDER BY pt.created_at DESC",
        $params
    );

    $filename = 'spinwheel_export_' . date('Ymd_His');

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Name','Email','Phone','Prize Won','Project','Group','Date']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['name'], $r['email'], $r['phone'] ?? '',
                $r['prize_won'], $r['project_name'], $r['group_name'] ?? '', $r['created_at']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
        echo json_encode(['exported_at' => date('c'), 'total' => count($rows), 'data' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="max-width:600px">
  <div class="card">
    <div class="card-hd"><span class="card-title">📤 Export Participants</span></div>
    <div class="card-body">
      <form method="GET" id="exportForm">
        <div class="fg">
          <label>Project</label>
          <select name="project">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pl): ?>
              <option value="<?= $pl['id'] ?>"><?= e($pl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Date From</label>
            <input type="date" name="date_from">
          </div>
          <div class="fg">
            <label>Date To</label>
            <input type="date" name="date_to">
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px">
          <button type="submit" name="format" value="csv" class="btn btn-primary">⬇ Download CSV</button>
          <?php if (currentUserCan('export.json')): ?>
          <button type="submit" name="format" value="json" class="btn btn-ghost">⬇ Download JSON</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Info card -->
  <div class="card">
    <div class="card-hd"><span class="card-title">ℹ️ Export Formats</span></div>
    <div class="card-body">
      <table>
        <tr>
          <td style="color:var(--t2);width:100px">CSV</td>
          <td>All roles — spreadsheet-compatible, includes all participant fields.</td>
        </tr>
        <tr>
          <td style="color:var(--t2)">JSON</td>
          <td>Manager+ only — structured data for API / developer use.</td>
        </tr>
      </table>
      <?php if (!currentUserHasFullScope()): ?>
      <div class="alert alert-info" style="margin-top:14px">
        🔒 Export is scoped to your assigned organisation groups only.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
