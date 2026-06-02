<?php
// creator profile page for name bio avatar and password

require_once '../includes/auth-check.php';
require_once '../classes/User.php';

$page_title = 'My Profile';

// fetch current profile data
$userObj = new User();
$profile = $userObj->getUserById($current_user_id);

$errors   = [];
$success  = '';

// handle profile update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCsrfFromPost()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // sanitize submitted fields
        $full_name = clean($_POST['full_name'] ?? '');
        $bio       = clean($_POST['bio']       ?? '');

        // name length check
        if (strlen($full_name) < 3) {
            $errors[] = 'Full name must be at least 3 characters.';
        }
        // bio length check
        if (strlen($bio) > 500) {
            $errors[] = 'Bio must not exceed 500 characters.';
        }

        if (empty($errors)) {
            $data = ['full_name' => $full_name, 'bio' => $bio];

            // handle avatar upload if provided
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                if ($file['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'Profile picture must be under 2 MB.';
                } else {
                    // magic byte check not just extension
                    $imageInfo = getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $errors[] = 'Invalid image file.';
                    } else {
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!in_array($imageInfo['mime'], $allowedMimes)) {
                            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
                        } else {
                            // use mime type not user filename for extension
                            $mimeExtMap = [
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/gif'  => 'gif',
                                'image/webp' => 'webp',
                            ];
                            $ext      = $mimeExtMap[$imageInfo['mime']];
                            $filename = 'avatar_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                            $avatarsDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'avatars';
                            $dest       = $avatarsDir . DIRECTORY_SEPARATOR . $filename;

                            // create avatars dir if missing
                            if (!is_dir($avatarsDir)) {
                                mkdir($avatarsDir, 0755, true);
                            }

                            // block PHP execution in avatars dir
                            $blocker = $avatarsDir . '/index.php';
                            if (!file_exists($blocker)) {
                                file_put_contents($blocker, '<?php // Silence is golden');
                            }
                            $htaccess = $avatarsDir . '/.htaccess';
                            if (!file_exists($htaccess)) {
                                file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes\n");
                            }

                            // move upload to avatars folder
                            if (move_uploaded_file($file['tmp_name'], $dest)) {
                                $data['profile_picture'] = 'avatars/' . $filename;
                            } else {
                                $errors[] = 'Failed to save profile picture. Please try again.';
                            }
                        }
                    }
                }
            }

            if (empty($errors)) {
                if ($userObj->updateProfile($current_user_id, $data)) {
                    // sync session name for nav bar
                    $_SESSION['full_name'] = $full_name;
                    $current_user_name     = $full_name;
                    setFlashMessage('Profile updated successfully!', 'success');
                    redirect('creator/profile.php');
                } else {
                    $errors[] = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
    // refetch so form shows fresh data
    $profile = $userObj->getUserById($current_user_id);
}

// handle password change form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCsrfFromPost()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // read password fields
        $old_pass  = $_POST['old_password']   ?? '';
        $new_pass  = $_POST['new_password']   ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        // all fields required
        if (empty($old_pass) || empty($new_pass) || empty($conf_pass)) {
            $errors[] = 'All password fields are required.';
        } elseif ($new_pass !== $conf_pass) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            // check password strength first
            $pwCheck = validatePassword($new_pass);
            if (!$pwCheck['valid']) {
                $errors[] = $pwCheck['message'];
            } else {
                $result = $userObj->changePassword($current_user_id, $old_pass, $new_pass);
                if ($result['success']) {
                    setFlashMessage('Password changed successfully!', 'success');
                    redirect('creator/profile.php');
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

// build avatar url if set
$avatar_url = !empty($profile['profile_picture'])
    ? SITE_URL . '/uploads/' . ltrim($profile['profile_picture'], '/')
    : '';
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
        .profile-grid { display: grid; grid-template-columns: 280px 1fr; gap: 28px; }
        @media(max-width:800px){ .profile-grid{ grid-template-columns:1fr; } }
        .profile-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.07); padding: 32px; text-align: center; height: fit-content; }
        .avatar-wrap { position: relative; display: inline-block; margin-bottom: 16px; }
        .avatar-img  { width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
                       border: 4px solid #667eea; background: #f0f2f5; }
        .avatar-placeholder { width: 110px; height: 110px; border-radius: 50%;
                              background: linear-gradient(135deg,#667eea,#764ba2);
                              display: flex; align-items: center; justify-content: center;
                              font-size: 42px; color: #fff; font-weight: 700; border: 4px solid #eee; }
        .profile-name  { font-size: 20px; font-weight: 700; color: #2c3e50; margin: 0 0 4px; }
        .profile-role  { font-size: 13px; color: #7f8c8d; margin: 0 0 12px; }
        .profile-email { font-size: 13px; color: #667eea; }
        .profile-joined{ font-size: 12px; color: #bbb; margin-top: 8px; }
        .form-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.07); padding: 28px; margin-bottom: 24px; }
        .form-card h2 { font-size: 17px; font-weight: 600; color: #2c3e50; margin: 0 0 20px; padding-bottom: 14px; border-bottom: 1px solid #f0f0f0; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1.5px solid #dee2e6; border-radius: 8px;
            font-size: 14px; font-family: inherit; transition: border-color .2s;
            background: #fafafa; box-sizing: border-box;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: #667eea; background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 90px; }
        .form-group .hint { font-size: 12px; color: #aaa; margin-top: 4px; }
        .btn-save { background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; border: none;
                    padding: 11px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;
                    cursor: pointer; transition: opacity .2s; }
        .btn-save:hover { opacity: .88; }
        .alert-error   { background: #fdecea; color: #c0392b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
        .char-counter  { font-size: 12px; color: #aaa; text-align: right; margin-top: 2px; }
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
                <h1><i class="fas fa-user" style="color:#667eea;"></i> My Profile</h1>
                <p>Manage your public profile and account settings</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>
        <!-- show any validation errors that were collected above -->
        <?php foreach ($errors as $err): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <div class="profile-grid">

            <!-- left column showing the profile summary card with avatar, name, email, bio and join date -->
            <div class="profile-card">
                <div class="avatar-wrap">
                    <?php if ($avatar_url): ?>
                        <img src="<?= e($avatar_url) ?>" class="avatar-img" alt="Profile Picture">
                    <?php else: ?>
                        <!-- fallback showing the first letter of the user's name -->
                        <div class="avatar-placeholder">
                            <?= strtoupper(substr($profile['full_name'] ?? 'C', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h2 class="profile-name"><?= e($profile['full_name'] ?? '') ?></h2>
                <p class="profile-role"><i class="fas fa-chalkboard-teacher"></i> Content Creator</p>
                <p class="profile-email"><i class="fas fa-envelope"></i> <?= e($profile['email'] ?? '') ?></p>
                <?php if (!empty($profile['bio'])): ?>
                    <p style="font-size:13px; color:#555; margin-top:12px; line-height:1.5;"><?= e($profile['bio']) ?></p>
                <?php endif; ?>
                <p class="profile-joined">
                    <i class="fas fa-calendar-alt"></i>
                    Joined <?= formatDate($profile['created_at'] ?? '', 'F Y') ?>
                </p>
            </div>

            <!-- right column with the edit profile form and change password form -->
            <div>
                <!-- update profile form for name, bio and profile picture -->
                <div class="form-card">
                    <h2><i class="fas fa-edit" style="color:#667eea; margin-right:8px;"></i> Edit Profile</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <?php csrfField(); ?>
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:#e74c3c;">*</span></label>
                            <input type="text" id="full_name" name="full_name"
                                   value="<?= e($profile['full_name'] ?? '') ?>"
                                   placeholder="Your full name" maxlength="100" required>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio / About Me</label>
                            <textarea id="bio" name="bio" maxlength="500"
                                      placeholder="Tell learners a bit about yourself..."><?= e($profile['bio'] ?? '') ?></textarea>
                            <div class="char-counter"><span id="bio-count">0</span>/500</div>
                        </div>

                        <div class="form-group">
                            <label for="profile_picture">Profile Picture</label>
                            <input type="file" id="profile_picture" name="profile_picture"
                                   accept=".jpg,.jpeg,.png,.gif,.webp">
                            <div class="hint">Max 2 MB. JPG, PNG, GIF or WEBP.</div>
                        </div>

                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Profile
                        </button>
                    </form>
                </div>

                <!-- change password form requiring current password and two entries of the new one -->
                <div class="form-card">
                    <h2><i class="fas fa-lock" style="color:#667eea; margin-right:8px;"></i> Change Password</h2>
                    <form method="POST" id="pwForm">
                        <?php csrfField(); ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="old_password">Current Password <span style="color:#e74c3c;">*</span></label>
                            <input type="password" id="old_password" name="old_password" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password <span style="color:#e74c3c;">*</span></label>
                            <input type="password" id="new_password" name="new_password"
                                   placeholder="Min. 8 chars, uppercase, number" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password <span style="color:#e74c3c;">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Repeat new password" required>
                            <!-- live match hint shown while the user types the confirmation -->
                            <div class="hint" id="pw-match-hint" style="display:none;"></div>
                        </div>

                        <button type="submit" class="btn-save">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div><!-- /right col -->
        </div><!-- /profile-grid -->
    </main>
</div>

<script>
// bio character counter
const bioTxt   = document.getElementById('bio');
const bioCount = document.getElementById('bio-count');
function updateCount() { bioCount.textContent = bioTxt.value.length; }
bioTxt.addEventListener('input', updateCount);
updateCount();

// live password match hint
const newPw  = document.getElementById('new_password');
const confPw = document.getElementById('confirm_password');
const hint   = document.getElementById('pw-match-hint');
function checkMatch() {
    if (!confPw.value) { hint.style.display = 'none'; return; }
    hint.style.display = 'block';
    if (newPw.value === confPw.value) {
        hint.style.color = '#27ae60';
        hint.textContent = '✓ Passwords match';
    } else {
        hint.style.color = '#e74c3c';
        hint.textContent = '✗ Passwords do not match';
    }
}
newPw.addEventListener('input', checkMatch);
confPw.addEventListener('input', checkMatch);

// block submit if passwords mismatch
document.getElementById('pwForm').addEventListener('submit', function(e) {
    if (newPw.value !== confPw.value) {
        e.preventDefault();
        alert('New password and confirmation do not match.');
    }
});
</script>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
