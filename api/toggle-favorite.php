<?php
// toggle favorite with transaction to prevent duplicate rows

require_once '../config/config.php';

// json response header
header('Content-Type: application/json');

// require login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/auth/login.php']);
    exit();
}

// parse json body
$data = json_decode(file_get_contents('php://input'), true);

// validate csrf token
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
    // transaction guards against duplicate rows from rapid clicks
    $conn->beginTransaction();

    // row lock prevents race condition
    $checkStmt = $conn->prepare(
        "SELECT activity_id FROM dbProj_user_activity
         WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'favorite'
         FOR UPDATE"
    );
    $checkStmt->bindParam(':uid', $user_id,      PDO::PARAM_INT);
    $checkStmt->bindParam(':tid', $tutorial_id,  PDO::PARAM_INT);
    $checkStmt->execute();
    $existing = $checkStmt->fetch();

    if ($existing) {
        // already favorited — remove it
        $delStmt = $conn->prepare(
            "DELETE FROM dbProj_user_activity
             WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'favorite'"
        );
        $delStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $delStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $delStmt->execute();

        $conn->commit();
        // response: now unfavorited
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        // not yet favorited — insert new row
        $insStmt = $conn->prepare(
            "INSERT INTO dbProj_user_activity (user_id, tutorial_id, activity_type, activity_date)
             VALUES (:uid, :tid, 'favorite', NOW())"
        );
        $insStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $insStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $insStmt->execute();

        $conn->commit();
        // response: now favorited
        echo json_encode(['success' => true, 'favorited' => true]);
    }

} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
