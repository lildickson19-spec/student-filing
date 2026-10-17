<?php
require __DIR__ . '/inc/bootstrap.php';
if (me()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['identity'] ?? '');
    $pw = $_POST['password'] ?? '';
    $row = one('SELECT * FROM users WHERE email=? OR admission_no=?', [$id, $id]);
    if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } elseif ($row && password_verify($pw, $row['password_hash'])) {
        if ($row['status'] === 'pending') {
            $error = 'Your account is waiting for administrator approval.';
        } elseif ($row['status'] !== 'active') {
            $error = 'This account is ' . $row['status'] . '. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id'];
            q('UPDATE users SET failed_logins=0, locked_until=NULL, last_login=NOW() WHERE id=?', [$row['id']]);
            audit('login', '', (int)$row['id']);
            redirect('dashboard.php');
        }
    } else {
        if ($row) {
            $fails = $row['failed_logins'] + 1;
            if ($fails >= 5) q('UPDATE users SET failed_logins=0, locked_until=? WHERE id=?', [date('Y-m-d H:i:s', time() + 900), $row['id']]);
            else q('UPDATE users SET failed_logins=? WHERE id=?', [$fails, $row['id']]);
        }
        audit('login_failed', $id, $row ? (int)$row['id'] : null);
        $error = 'Wrong login details.';
    }
}
$bare = true; $pageTitle = 'Log in';
include __DIR__ . '/inc/header.php';
?>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>Admission number or email</label>
  <input type="text" name="identity" required autofocus value="<?= e($_POST['identity'] ?? '') ?>">
  <label>Password</label>
  <input type="password" name="password" required>
  <p><button>Log in</button></p>
</form>
<p class="muted">New student? <a href="<?= u('register.php') ?>">Create an account</a></p>
<?php include __DIR__ . '/inc/footer.php';
