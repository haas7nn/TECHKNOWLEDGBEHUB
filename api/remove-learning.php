<?php
// remove learning history for a tutorial
require_once '../config/config.php';

// json response header
header('Content-Type: application/json');

// require login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// parse json body
$data = json_decode(file_get_contents('php://input'), true);

// csrf check
if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// validate tutorial id
$tutorial_id = (int)($data['tutorial_id'] ?? 0);
if (!$tutorial_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid tutorial']);
    exit();
}

// get user id and connect
$user_id = getCurrentUserId();
$db   = new Database();
$conn = $db->connect();

// abort on db failure
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// confirm tutorial exists
$exists = $conn->prepare("SELECT tutorial_id FROM dbProj_tutorials WHERE tutorial_id = :id LIMIT 1");
$exists->bindParam(':id', $tutorial_id, PDO::PARAM_INT);
$exists->execute();
if (!$exists->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Tutorial not found']);
    exit();
}

// delete view and complete rows leaving favorites intact
$stmt = $conn->prepare(
    "DELETE FROM dbProj_user_activity
     WHERE user_id = :uid AND tutorial_id = :tid
     AND activity_type IN ('view', 'complete')"
);
$stmt->bindParam(':uid', $user_id,    PDO::PARAM_INT);
$stmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);

// json response based on delete result
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not remove tutorial']);
}
