<?php
/**
 * Delete / Archive Tutorial
 * Bug 15 fix: was GET-based with no CSRF. Now POST-only with CSRF verification.
 * Hasan Fardan - 202301686
 */
require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

// Bug 15 fix: only accept POST, never GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('Invalid request method.', 'error');
    redirect('creator/my-tutorials.php');
}

if (!verifyCsrfFromPost()) {
    setFlashMessage('Invalid security token.', 'error');
    redirect('creator/my-tutorials.php');
}

$tutorial_id = (int)($_POST['tutorial_id'] ?? 0);

if (!$tutorial_id) {
    setFlashMessage('Invalid tutorial.', 'error');
    redirect('creator/my-tutorials.php');
}

$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getById($tutorial_id);

if (!$tutorial) {
    setFlashMessage('Tutorial not found.', 'error');
    redirect('creator/my-tutorials.php');
}

// only the owner or admin can delete
if ($tutorial['instructor_id'] != $current_user_id && !isAdmin()) {
    setFlashMessage('Permission denied.', 'error');
    redirect('creator/my-tutorials.php');
}

if ($tutorialObj->delete($tutorial_id)) {
    setFlashMessage('Tutorial archived and removed from public view.', 'success');
} else {
    setFlashMessage('Failed to archive tutorial.', 'error');
}
redirect('creator/my-tutorials.php');
