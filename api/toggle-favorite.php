<?php
// API endpoint — adds or removes a tutorial from the user's favorites
// uses a transaction to prevent race conditions if two requests hit at the same time
// Hasan Fardan - 202301686

require_once '../config/config.php';

header('Content-Type: application/json');

// have to be logged in to use favorites
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/auth/login.php']);
    exit();
}

// read the JSON body they sent
$data = json_decode(file_get_contents('php://input'), true);

// check the CSRF token first — rejects requests from other sites
if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// make sure the tutorial ID is a real number
$tutorial_id = (int)($data['tutorial_id'] ?? 0);
if (!$tutorial_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid tutorial']);
    exit();
}

$user_id = getCurrentUserId();
$db      = new Database();
$conn    = $db->connect();

// check the database connection worked
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// make sure the tutorial actually exists and is published
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
    // wrap everything in a transaction so two simultaneous clicks
    // don't create duplicate favorite rows (race condition fix)
    $conn->beginTransaction();

    // lock the row while we read it so no other request can sneak in
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
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        // not favorited yet — add it
        $insStmt = $conn->prepare(
            "INSERT INTO dbProj_user_activity (user_id, tutorial_id, activity_type, activity_date)
             VALUES (:uid, :tid, 'favorite', NOW())"
        );
        $insStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $insStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $insStmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'favorited' => true]);
    }

} catch (PDOException $e) {
    // something went wrong — roll back so we don't leave dirty data
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
