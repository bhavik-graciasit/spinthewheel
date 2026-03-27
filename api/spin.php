<?php
/**
 * SpinWheel Pro V2 — Spin API Endpoint
 * POST /api/spin.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ── CSRF ──────────────────────────────────────────────
$csrfToken = trim($_POST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Invalid session token. Please refresh the page.'], 403);
}

// ── PROJECT ───────────────────────────────────────────
$token   = trim($_POST['project_token'] ?? '');
$project = getProjectByToken($token);
if (!$project) {
    jsonResponse(['success' => false, 'message' => 'Campaign not found or inactive.'], 404);
}
$projectId = (int)$project['id'];

// ── LOAD QUESTIONS ────────────────────────────────────
$questions = getFormQuestions($projectId);
if (empty($questions)) {
    jsonResponse(['success' => false, 'message' => 'This campaign has no form configured. Contact the administrator.']);
}

$db      = Database::getInstance();
$answers = [];
$emailValue = null;
$fileUploads = [];

// ── VALIDATE & COLLECT ANSWERS ────────────────────────
foreach ($questions as $q) {
    $qid      = (int)$q['id'];
    $type     = $q['field_type'];
    $required = (bool)$q['is_required'];
    $key      = 'q_' . $qid;
    $value    = '';

    if ($type === 'file') {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = handleFileUpload($_FILES[$key]);
            if (!$upload['success']) {
                jsonResponse(['success' => false, 'message' => $upload['message']]);
            }
            $fileUploads[$qid] = $upload;
            $value = $upload['original_name'];
        } elseif ($required) {
            jsonResponse(['success' => false, 'message' => htmlspecialchars($q['question_text']) . ' is required.']);
        }
    } elseif ($type === 'checkbox') {
        $vals  = array_map('trim', (array)($_POST[$key] ?? []));
        $value = implode(', ', array_filter($vals));
        if ($required && empty($value)) {
            jsonResponse(['success' => false, 'message' => htmlspecialchars($q['question_text']) . ' is required.']);
        }
    } else {
        $value = trim($_POST[$key] ?? '');
        if ($required && $value === '') {
            jsonResponse(['success' => false, 'message' => htmlspecialchars($q['question_text']) . ' is required.']);
        }
    }

    // Email field validation
    if ($type === 'email' && $value !== '') {
        $emailCheck = isValidCorporateEmail($value);
        if (!$emailCheck['valid']) {
            jsonResponse(['success' => false, 'message' => $emailCheck['message']]);
        }
        $emailValue = strtolower($value);
    }

    $answers[$qid] = ['text' => $value, 'type' => $type];
}

// ── DUPLICATE CHECK ───────────────────────────────────
if ($emailValue && emailUsedInProject($emailValue, $projectId)) {
    jsonResponse(['success' => false, 'already_used' => true, 'message' => 'This email has already been used for this campaign.']);
}

// ── SPIN ──────────────────────────────────────────────
$winner = spinWheel($projectId);
if (!$winner) {
    jsonResponse(['success' => false, 'message' => 'No active prizes configured. Please contact the administrator.']);
}

// ── SAVE PARTICIPANT ──────────────────────────────────
try {
    $db->beginTransaction();

    $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip        = trim(explode(',', $ip)[0]);
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $participantId = $db->insert(
        "INSERT INTO participants (project_id, result_id, result_name, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)",
        [$projectId, $winner['id'], $winner['name'], $ip, $userAgent]
    );

    foreach ($answers as $qid => $ans) {
        $filePath = $fileUploads[$qid]['path'] ?? null;
        $fileName = $fileUploads[$qid]['original_name'] ?? null;
        $db->execute(
            "INSERT INTO participant_answers (participant_id, question_id, answer_text, file_path, file_name)
             VALUES (?, ?, ?, ?, ?)",
            [$participantId, $qid, $ans['text'], $filePath, $fileName]
        );
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    if (DEBUG_MODE) error_log('SpinWheel Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'A server error occurred. Please try again.'], 500);
}

// ── RESPONSE ──────────────────────────────────────────
jsonResponse([
    'success' => true,
    'winner'  => [
        'id'          => $winner['id'],
        'name'        => $winner['name'],
        'success_msg' => $winner['success_msg'] ?? '',
        'color'       => $winner['color'],
    ],
    'participant_id' => $participantId,
]);
