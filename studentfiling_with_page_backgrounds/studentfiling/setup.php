<?php
// Run once after importing database.sql: creates the first administrator. DELETE this file afterwards.
require __DIR__ . '/inc/bootstrap.php';
$bare = true; $pageTitle = 'Setup';
$dbError = ''; $hasAdmin = false; $done = false; $err = '';
try {
    $hasAdmin = (int)val("SELECT COUNT(*) FROM users WHERE role='admin'") > 0;
} catch (Throwable $ex) {
    $dbError = $ex->getMessage();
}
if (!$dbError && !$hasAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email.';
    elseif (strlen($pw) < 8) $err = 'Password must be at least 8 characters.';
    else {
        q("INSERT INTO users(role,full_name,email,password_hash,status) VALUES('admin',?,?,?,'active')", [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
        audit('setup', 'First administrator created', (int)db()->lastInsertId());
        $done = true;
    }
}
include __DIR__ . '/inc/header.php';
?>
<h3>First-time setup</h3>
<?php if ($dbError): ?>
  <div class="flash err">Cannot reach the database. Start MySQL in XAMPP, import <b>database.sql</b> in phpMyAdmin, and check the settings in <b>config.php</b>.<br><small><?= e($dbError) ?></small></div>
<?php elseif ($done): ?>
  <div class="flash ok">Administrator created. <b>Now delete setup.php</b> from the project folder.</div>
  <p><a class="btn" href="<?= u('login.php') ?>">Go to login</a></p>
<?php elseif ($hasAdmin): ?>
  <div class="flash ok">Setup is already done. Please delete setup.php.</div>
  <p><a class="btn" href="<?= u('login.php') ?>">Go to login</a></p>
<?php else: ?>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <p class="muted">Create the first administrator account.</p>
  <form method="post"><?= csrf_field() ?>
    <label>Full name</label><input type="text" name="full_name" required>
    <label>Email (used to log in)</label><input type="email" name="email" required>
    <label>Password (at least 8 characters)</label><input type="password" name="password" required>
    <p><button>Create administrator</button></p>
  </form>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php';
