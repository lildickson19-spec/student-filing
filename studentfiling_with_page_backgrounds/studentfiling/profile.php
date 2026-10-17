<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me(); $pageTitle = 'My profile'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email.';
        elseif (val('SELECT 1 FROM users WHERE email=? AND id<>?', [$email, $u['id']])) $err = 'That email is used by another account.';
        else {
            q('UPDATE users SET full_name=?, email=?, phone=? WHERE id=?', [$name, $email, $phone, $u['id']]);
            audit('profile_update'); flash('Profile updated.'); redirect('profile.php');
        }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $new = $_POST['new'] ?? '';
        if (!password_verify($_POST['current'] ?? '', $u['password_hash'])) $err = 'Your current password is wrong.';
        elseif (strlen($new) < 8) $err = 'The new password must be at least 8 characters.';
        elseif ($new !== ($_POST['confirm'] ?? '')) $err = 'The new passwords do not match.';
        else {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            audit('password_change'); flash('Password changed.'); redirect('profile.php');
        }
    }
}
$info = one('SELECT d.name dept, c.name course FROM users x LEFT JOIN departments d ON d.id=x.department_id LEFT JOIN courses c ON c.id=x.course_id WHERE x.id=?', [$u['id']]);
include __DIR__ . '/inc/header.php';
?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<div class="two">
<div class="card"><h3>Personal details</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <?php if ($u['admission_no']): ?><label>Admission number</label><input type="text" value="<?= e($u['admission_no']) ?>" disabled><?php endif; ?>
    <label>Full name</label><input type="text" name="full_name" required value="<?= e($u['full_name']) ?>">
    <label>Email</label><input type="email" name="email" required value="<?= e($u['email']) ?>">
    <label>Phone</label><input type="text" name="phone" value="<?= e($u['phone']) ?>">
    <?php if ($info && ($info['dept'] || $info['course'])): ?><p class="muted">Department: <?= e($info['dept'] ?? '-') ?><br>Course: <?= e($info['course'] ?? '-') ?></p><?php endif; ?>
    <p><button>Save</button></p>
  </form></div>
<div class="card"><h3>Change password</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Current password</label><input type="password" name="current" required>
    <label>New password (at least 8 characters)</label><input type="password" name="new" required>
    <label>Confirm new password</label><input type="password" name="confirm" required>
    <p><button>Change password</button></p>
  </form></div>
</div>
<?php include __DIR__ . '/inc/footer.php';
