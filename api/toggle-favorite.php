<?php
// api endpoint that adds or removes a tutorial from the user's favorites
// uses a transaction to prevent duplicate rows if two clicks arrive at the same time

require_once '../config/config.php';

// tell the browser this response is json
header('Content-Type: application/json');

// the user must be logged in to use the favorites feature
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/auth/login.php']);
    exit();
}

// read the json body that the browser sent with this request
$data = json_decode(file_get_contents('php://input'), true);

// csrf check
if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
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

// confirm the tutorial actually exists and is published before touching favorites
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
    // wrap everything in a transaction so two simultaneous clicks cannot create duplicate rows
    $conn->beginTransaction();

    // lock the row while we check it so no other request can change it at the same time
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
        // the user already favorited this tutorial so remove it
        $delStmt = $conn->prepare(
            "DELETE FROM dbProj_user_activity
             WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'favorite'"
        );
        $delStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $delStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $delStmt->execute();

        $conn->commit();
        // tell the browser the tutorial is no longer favorited
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        // the user has not favorited this yet so insert a new row
        $insStmt = $conn->prepare(
            "INSERT INTO dbProj_user_activity (user_id, tutorial_id, activity_type, activity_date)
             VALUES (:uid, :tid, 'favorite', NOW())"
        );
        $insStmt->bindParam(':uid', $user_id,     PDO::PARAM_INT);
        $insStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
        $insStmt->execute();

        $conn->commit();
        // tell the browser the tutorial is now favorited
        echo json_encode(['success' => true, 'favorited' => true]);
    }

} catch (PDOException $e) {
    // something went wrong so roll back to keep the data clean
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
