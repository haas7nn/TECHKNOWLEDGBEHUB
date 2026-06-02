<?php
// mark tutorial complete for current user

require_once '../config/config.php';

// json response header
header('Content-Type: application/json');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// require viewer login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/auth/login.php']);
    exit();
}

if (!isViewer()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// parse json body or fall back to POST fields
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    // form-encoded fallback
    $data = $_POST;
}

// csrf from header or body
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

// reject bad csrf token
if (empty($csrf_token) || !verifyCSRFToken($csrf_token)) {
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
$db      = new Database();
$conn    = $db->connect();

// abort on db failure
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// confirm tutorial exists and is published
$exists = $conn->prepare(
    "SELECT tutorial_id FROM dbProj_tutorials
     WHERE tutorial_id = :id AND status = 'published' LIMIT 1"
);
$exists->bindParam(':id', $tutorial_id, PDO::PARAM_INT);
$exists->execute();
if (!$exists->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Tutorial not found']);
    exit();
}

try {
    // transaction prevents duplicate rows from concurrent clicks
    $conn->beginTransaction();

    // row lock prevents race condition
    $checkStmt = $conn->prepare(
        "SELECT activity_id FROM dbProj_user_activity
         WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'complete'
         FOR UPDATE"
    );
    $checkStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
    $checkStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
    $checkStmt->execute();
    $existing = $checkStmt->fetch();

    if ($existing) {
        // already complete — nothing to do
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        // insert complete activity row
        $insStmt = $conn->prepare(
            "INSERT INTO dbProj_user_activity (user_id, tutorial_id, activity_type, activity_date)
             VALUES (:uid, :tid, 'complete', NOW())"
        );
        $insStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $insStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $insStmt->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
