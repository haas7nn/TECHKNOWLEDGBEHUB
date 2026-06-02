<?php
// account preferences and notification toggles

require_once '../includes/auth-check.php';
require_once '../classes/User.php';

$page_title = 'Settings';

// db connect
$db   = new Database();
$conn = $db->connect();

// load user profile
$userObj = new User();
$profile = $userObj->getUserById($current_user_id);

// guard against null profile
if (!$profile) {
    setFlashMessage('Could not load profile.', 'error');
    redirect('creator/dashboard.php');
    exit;
}

$errors  = [];

// load settings from session with defaults
$settings_key = 'creator_settings_' . $current_user_id;
$settings = $_SESSION[$settings_key] ?? [
    'notify_comments'  => true,
    'notify_ratings'   => true,
    'notify_new_views' => false,
    'profile_public'   => true,
    'show_email'       => false,
    'tutorials_per_page' => 10,
    'default_difficulty' => 'beginner',
];

// handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfFromPost()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // read toggle and dropdown values
        $settings['notify_comments']    = isset($_POST['notify_comments']);
        $settings['notify_ratings']     = isset($_POST['notify_ratings']);
        $settings['notify_new_views']   = isset($_POST['notify_new_views']);
        $settings['profile_public']     = isset($_POST['profile_public']);
        $settings['show_email']         = isset($_POST['show_email']);
        // whitelist page size values
        $settings['tutorials_per_page'] = in_array((int)($_POST['tutorials_per_page'] ?? 10), [5,10,20,50])
                                            ? (int)$_POST['tutorials_per_page'] : 10;
        // whitelist difficulty values
        $settings['default_difficulty'] = in_array(clean($_POST['default_difficulty'] ?? ''), ['beginner','intermediate','advanced'])
                                            ? clean($_POST['default_difficulty']) : 'beginner';

        if (empty($errors)) {
            // persist in session no db column for settings
            $_SESSION[$settings_key] = $settings;
            setFlashMessage('Settings saved successfully!', 'success');
            redirect('creator/settings.php');
        }
    }
}

// handle account deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    if (!verifyCsrfFromPost()) {
        $errors[] = 'Invalid security token.';
    } else {
        // verify password before allowing deletion
        $current_password = $_POST['current_password'] ?? '';

        if (!$current_password || !password_verify($current_password, $profile['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            // note request admin completes actual deletion
            setFlashMessage('Account deletion request noted. Please contact an administrator to complete this action.', 'info');
            redirect('creator/settings.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.07);
                         padding: 28px; margin-bottom: 24px; }
        .settings-card h2 { font-size: 17px; font-weight: 600; color: #2c3e50; margin: 0 0 20px;
                            padding-bottom: 14px; border-bottom: 1px solid #f0f0f0;
                            display: flex; align-items: center; gap: 10px; }
        .setting-row { display: flex; align-items: center; justify-content: space-between;
                       padding: 14px 0; border-bottom: 1px solid #f8f8f8; }
        .setting-row:last-of-type { border-bottom: none; }
        .setting-info h4 { font-size: 14px; font-weight: 600; color: #2c3e50; margin: 0 0 3px; }
        .setting-info p  { font-size: 12px; color: #7f8c8d; margin: 0; }
        /* toggle switch styles */
        .toggle-wrap { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-wrap input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #ccc; border-radius: 24px; cursor: pointer; transition: .3s; }
        .toggle-slider:before { content:''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px;
                                background: #fff; border-radius: 50%; transition: .3s; }
        .toggle-wrap input:checked + .toggle-slider { background: #667eea; }
        .toggle-wrap input:checked + .toggle-slider:before { transform: translateX(20px); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group select { width: 100%; max-width: 280px; padding: 9px 12px; border: 1.5px solid #dee2e6;
                             border-radius: 8px; font-size: 14px; background: #fafafa; cursor: pointer; }
        .form-group select:focus { outline: none; border-color: #667eea; }
        .btn-save { background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; border: none;
                    padding: 11px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;
                    cursor: pointer; transition: opacity .2s; }
        .btn-save:hover { opacity: .88; }
        .btn-danger { background: #e74c3c; color: #fff; border: none; padding: 10px 22px;
                      border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-danger:hover { background: #c0392b; }
        .danger-zone { border: 2px solid #fdecea; border-radius: 12px; padding: 20px; background: #fff9f9; }
        .danger-zone h3 { color: #e74c3c; font-size: 16px; margin: 0 0 8px; }
        .danger-zone p  { font-size: 13px; color: #7f8c8d; margin: 0 0 14px; }
        .alert-error { background: #fdecea; color: #c0392b; border-radius: 8px; padding: 12px 16px;
                       margin-bottom: 16px; font-size: 14px; }
        .account-info-row { display: flex; gap: 12px; align-items: center; padding: 10px 0;
                            border-bottom: 1px solid #f5f5f5; font-size: 14px; }
        .account-info-row:last-child { border-bottom: none; }
        .account-info-row .label { color: #7f8c8d; width: 140px; flex-shrink: 0; font-weight: 500; }
        .account-info-row .value { color: #2c3e50; font-weight: 600; }
    </style>
</head>
<body>
<?php include '../includes/creator-nav.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/creator-sidebar.php'; ?>

    <main class="dashboard-main">

        <!-- page heading -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-cog" style="color:#667eea;"></i> Settings</h1>
                <p>Manage your account preferences</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>
        <!-- show any errors collected during form handling -->
        <?php foreach ($errors as $err): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <!-- settings form wrapping all the preference cards -->
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="save_settings" value="1">

            <!-- notification preferences card with three toggles -->
            <div class="settings-card">
                <h2><i class="fas fa-bell" style="color:#f39c12;"></i> Notification Preferences</h2>

                <!-- toggle for comment notifications -->
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>New Comments</h4>
                        <p>Get notified when someone comments on your tutorial</p>
                    </div>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="notify_comments" <?= $settings['notify_comments'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- toggle for rating notifications -->
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>New Ratings</h4>
                        <p>Get notified when someone rates your tutorial</p>
                    </div>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="notify_ratings" <?= $settings['notify_ratings'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- toggle for view milestone notifications -->
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>View Milestones</h4>
                        <p>Get notified at every 100 views milestone</p>
                    </div>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="notify_new_views" <?= $settings['notify_new_views'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- privacy settings card with two toggles -->
            <div class="settings-card">
                <h2><i class="fas fa-shield-alt" style="color:#27ae60;"></i> Privacy Settings</h2>

                <!-- toggle to make the profile visible to other users -->
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>Public Profile</h4>
                        <p>Allow other users to see your creator profile</p>
                    </div>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="profile_public" <?= $settings['profile_public'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- toggle to show or hide the email address on the public profile -->
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>Show Email on Profile</h4>
                        <p>Display your email address on your public profile</p>
                    </div>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="show_email" <?= $settings['show_email'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- tutorial defaults card with dropdown for difficulty and page size -->
            <div class="settings-card">
                <h2><i class="fas fa-sliders-h" style="color:#667eea;"></i> Tutorial Defaults</h2>

                <div class="form-group">
                    <label for="default_difficulty">Default Difficulty Level</label>
                    <select id="default_difficulty" name="default_difficulty">
                        <option value="beginner"     <?= $settings['default_difficulty']==='beginner'     ? 'selected':'' ?>>Beginner</option>
                        <option value="intermediate" <?= $settings['default_difficulty']==='intermediate' ? 'selected':'' ?>>Intermediate</option>
                        <option value="advanced"     <?= $settings['default_difficulty']==='advanced'     ? 'selected':'' ?>>Advanced</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tutorials_per_page">Tutorials Shown Per Page</label>
                    <select id="tutorials_per_page" name="tutorials_per_page">
                        <?php foreach ([5,10,20,50] as $n): ?>
                        <option value="<?= $n ?>" <?= $settings['tutorials_per_page']===$n ? 'selected':'' ?>><?= $n ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-save" style="margin-bottom: 28px;">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>

        <!-- read only account information card -->
        <div class="settings-card">
            <h2><i class="fas fa-info-circle" style="color:#3498db;"></i> Account Information</h2>
            <div class="account-info-row">
                <span class="label">Account ID</span>
                <span class="value">#<?= e($profile['user_id'] ?? '') ?></span>
            </div>
            <div class="account-info-row">
                <span class="label">Email</span>
                <span class="value"><?= e($profile['email'] ?? '') ?></span>
            </div>
            <div class="account-info-row">
                <span class="label">Role</span>
                <span class="value" style="text-transform:capitalize;"><?= e($profile['role'] ?? 'creator') ?></span>
            </div>
            <div class="account-info-row">
                <span class="label">Account Status</span>
                <span class="value" style="color:<?= ($profile['status']??'')==='active'?'#27ae60':'#e74c3c'; ?>">
                    <?= ucfirst(e($profile['status'] ?? 'active')) ?>
                </span>
            </div>
            <div class="account-info-row">
                <span class="label">Member Since</span>
                <span class="value"><?= formatDate($profile['created_at'] ?? '', 'F d, Y') ?></span>
            </div>
            <div class="account-info-row">
                <span class="label">Last Login</span>
                <span class="value"><?= $profile['last_login'] ? timeAgo($profile['last_login']) : 'N/A' ?></span>
            </div>
            <p style="font-size:12px; color:#bbb; margin-top:14px;">
                To update your email address, please contact an administrator.
            </p>
        </div>

        <!-- danger zone card with an account deletion button that opens a confirmation modal -->
        <div class="settings-card">
            <h2><i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Danger Zone</h2>
            <div class="danger-zone">
                <h3>Delete Account</h3>
                <p>Once you delete your account, all your tutorials and data will be permanently removed. This action cannot be undone.</p>
                <button type="button" class="btn-danger" onclick="openModal('deleteModal')">
                    <i class="fas fa-trash-alt"></i> Request Account Deletion
                </button>
            </div>
        </div>

    </main>
</div>

<!-- confirmation modal shown when the user clicks the delete account button -->
<div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
     z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:32px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <h3 style="color:#e74c3c; margin:0 0 10px;"><i class="fas fa-exclamation-triangle"></i> Confirm Account Deletion</h3>
        <p style="font-size:14px; color:#555; margin-bottom:20px;">
            This will permanently delete your account and all your tutorials. Type your password to confirm.
        </p>
        <!-- password confirmation form posted to the same page -->
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="delete_account" value="1">
            <input type="password" name="current_password" placeholder="Enter your current password"
                   style="width:100%; padding:10px 14px; border:1.5px solid #dee2e6; border-radius:8px;
                          font-size:14px; margin-bottom:16px; box-sizing:border-box;">
            <div style="display:flex; gap:10px;">
                <!-- cancel button hides the modal without submitting -->
                <button type="button" onclick="closeModal('deleteModal')"
                        style="flex:1; padding:10px; border:1.5px solid #dee2e6; border-radius:8px;
                               background:#fff; cursor:pointer; font-weight:600;">Cancel</button>
                <button type="submit" class="btn-danger" style="flex:1; padding:10px;">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
