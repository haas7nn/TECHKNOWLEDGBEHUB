<?php
// archives tutorial and removes it from public view

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

// reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('Invalid request method.', 'error');
    redirect('creator/my-tutorials.php');
}

// csrf check before touching data
if (!verifyCsrfFromPost()) {
    setFlashMessage('Invalid security token.', 'error');
    redirect('creator/my-tutorials.php');
}

// parse tutorial id from POST
$tutorial_id = (int)($_POST['tutorial_id'] ?? 0);

if (!$tutorial_id) {
    setFlashMessage('Invalid tutorial.', 'error');
    redirect('creator/my-tutorials.php');
}

// load tutorial or bail
$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getById($tutorial_id);

if (!$tutorial) {
    setFlashMessage('Tutorial not found.', 'error');
    redirect('creator/my-tutorials.php');
}

// block non-owners to prevent IDOR
if ($tutorial['instructor_id'] != $current_user_id && !isAdmin()) {
    setFlashMessage('Permission denied.', 'error');
    redirect('creator/my-tutorials.php');
}

// which action archive restore or permanent delete
$action = $_POST['action'] ?? 'archive';

if ($action === 'permanent') {
    // hard delete removes the tutorial and all its tags media ratings comments
    if ($tutorialObj->permanentDelete($tutorial_id)) {
        setFlashMessage('Tutorial permanently deleted.', 'success');
    } else {
        setFlashMessage('Failed to delete tutorial.', 'error');
    }
} elseif ($action === 'restore') {
    // bring an archived tutorial back as a draft
    if ($tutorialObj->restore($tutorial_id)) {
        setFlashMessage('Tutorial restored as a draft.', 'success');
    } else {
        setFlashMessage('Failed to restore tutorial.', 'error');
    }
} else {
    // default soft delete hides it from public view
    if ($tutorialObj->delete($tutorial_id)) {
        setFlashMessage('Tutorial archived and removed from public view.', 'success');
    } else {
        setFlashMessage('Failed to archive tutorial.', 'error');
    }
}
redirect('creator/my-tutorials.php');
