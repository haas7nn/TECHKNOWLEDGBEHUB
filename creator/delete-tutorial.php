<?php
// delete tutorial handler that archives the tutorial and removes it from public view
// only accepts post requests with a valid csrf token so direct url access is blocked

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

// post only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('Invalid request method.', 'error');
    redirect('creator/my-tutorials.php');
}

// verify the csrf token before touching any data
if (!verifyCsrfFromPost()) {
    setFlashMessage('Invalid security token.', 'error');
    redirect('creator/my-tutorials.php');
}

// get tutorial id
$tutorial_id = (int)($_POST['tutorial_id'] ?? 0);

if (!$tutorial_id) {
    setFlashMessage('Invalid tutorial.', 'error');
    redirect('creator/my-tutorials.php');
}

// check tutorial exists
$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getById($tutorial_id);

if (!$tutorial) {
    setFlashMessage('Tutorial not found.', 'error');
    redirect('creator/my-tutorials.php');
}

// owner or admin only
if ($tutorial['instructor_id'] != $current_user_id && !isAdmin()) {
    setFlashMessage('Permission denied.', 'error');
    redirect('creator/my-tutorials.php');
}

// try delete
if ($tutorialObj->delete($tutorial_id)) {
    setFlashMessage('Tutorial archived and removed from public view.', 'success');
} else {
    setFlashMessage('Failed to archive tutorial.', 'error');
}
redirect('creator/my-tutorials.php');
