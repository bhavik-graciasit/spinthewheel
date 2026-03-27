<?php
/**
 * GET /api/v1/participant.php?id=N
 * Returns all form answers for a participant (admin only)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { jsonResponse(['success' => false, 'message' => 'Missing id']); }

$db = Database::getInstance();
$participant = $db->fetchOne("SELECT pt.*, p.name AS project_name FROM participants pt JOIN projects p ON p.id = pt.project_id WHERE pt.id = ?", [$id]);
if (!$participant) { jsonResponse(['success' => false, 'message' => 'Not found'], 404); }

$answers = $db->fetchAll("
    SELECT pa.answer_text, pa.file_path, pa.file_name, fq.question_text, fq.field_type
    FROM participant_answers pa
    JOIN form_questions fq ON fq.id = pa.question_id
    WHERE pa.participant_id = ?
    ORDER BY fq.sort_order
", [$id]);

jsonResponse([
    'success'  => true,
    'result'   => $participant['result_name'],
    'project'  => $participant['project_name'],
    'spun_at'  => $participant['spun_at'],
    'answers'  => array_map(fn($a) => [
        'question'  => $a['question_text'],
        'type'      => $a['field_type'],
        'answer'    => $a['answer_text'],
        'file_path' => $a['file_path'],
        'file_name' => $a['file_name'],
    ], $answers),
]);
