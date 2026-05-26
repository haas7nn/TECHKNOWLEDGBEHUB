<?php
// load the app config and helpers
require_once '../config/config.php';

// tell the browser this response is json
header('Content-Type: application/json');

// the user must be logged in to remove items from their learning history
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// read the json body sent with this request
$data = json_decode(file_get_contents('php://input'), true);

// reject the request if the csrf token is missing or wrong
if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// make sure the tutorial id is a valid positive integer
$tutorial_id = (int)($data['tutorial_id'] ?? 0);
if (!$tutorial_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid tutorial']);
    exit();
}

// get the current user id and open the database
$user_id = getCurrentUserId();
$db   = new Database();
$conn = $db->connect();

// stop here if the database is not reachable
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// make sure the tutorial exists before touching the activity records
$exists = $conn->prepare("SELECT tutorial_id FROM dbProj_tutorials WHERE tutorial_id = :id LIMIT 1");
$exists->bindParam(':id', $tutorial_id, PDO::PARAM_INT);
$exists->execute();
if (!$exists->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Tutorial not found']);
    exit();
}

// delete only the view and complete rows so favorites are left untouched
$stmt = $conn->prepare(
    "DELETE FROM dbProj_user_activity
     WHERE user_id = :uid AND tutorial_id = :tid
     AND activity_type IN ('view', 'complete')"
);
$stmt->bindParam(':uid', $user_id,    PDO::PARAM_INT);
$stmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);

// return success or an error message depending on whether the delete worked
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not remove tutorial']);
}
