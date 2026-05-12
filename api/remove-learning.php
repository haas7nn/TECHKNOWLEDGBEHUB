<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$tutorial_id = (int)($data['tutorial_id'] ?? 0);
if (!$tutorial_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid tutorial']);
    exit();
}

$user_id = getCurrentUserId();
$db   = new Database();
$conn = $db->connect();

// Bug 16 fix: verify tutorial exists before touching activity records
$exists = $conn->prepare("SELECT tutorial_id FROM dbProj_tutorials WHERE tutorial_id = :id LIMIT 1");
$exists->bindParam(':id', $tutorial_id, PDO::PARAM_INT);
$exists->execute();
if (!$exists->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Tutorial not found']);
    exit();
}

$stmt = $conn->prepare(
    "DELETE FROM dbProj_user_activity WHERE user_id = :uid AND tutorial_id = :tid"
);
$stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not remove tutorial']);
}
