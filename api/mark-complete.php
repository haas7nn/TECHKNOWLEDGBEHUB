<?php
// api endpoint that marks a tutorial as complete for the current user
// inserts a new complete row or ignores the request if the record already exists

require_once '../config/config.php';

// tell the browser this response is json
header('Content-Type: application/json');

// only allow post requests to reach this endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// the user must be logged in and must have the viewer role
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/auth/login.php']);
    exit();
}

if (!isViewer()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// read the json body if the browser sent one otherwise fall back to post fields
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    // fall back to regular formencoded post body
    $data = $_POST;
}

// accept the csrf token from the xcsrftoken header first then fall back to the body/post field
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

// reject the request if the csrf token is missing or does not match the session token
if (empty($csrf_token) || !verifyCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// make sure the tutorial id is a positive integer
$tutorial_id = (int)($data['tutorial_id'] ?? 0);
if (!$tutorial_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid tutorial']);
    exit();
}

// get the current user id and db connect
$user_id = getCurrentUserId();
$db      = new Database();
$conn    = $db->connect();

// stop here if the database could not be reached
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// confirm the tutorial actually exists and is published before recording progress
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
    // wrap the upsert in a transaction so two simultaneous requests cannot create duplicate rows
    $conn->beginTransaction();

    // lock the row while we check it so no other request can race with this one
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
        // the tutorial is already marked complete so nothing more needs to be done
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        // insert a new complete record for this user and tutorial
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
    // something went wrong so roll back to keep the data clean
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
