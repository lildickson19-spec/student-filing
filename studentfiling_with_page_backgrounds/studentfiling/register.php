<?php
require __DIR__ . '/inc/bootstrap.php';
if (me()) redirect('dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? ''); $adm = trim($_POST['admission_no'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? '');
    $course = nn($_POST['course_id'] ?? ''); $pw = $_POST['password'] ?? '';
    if ($name === '' || $adm === '' || $email === '' || !$course) $err = 'Please fill in all required fields.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
    elseif (strlen($pw) < 8) $err = 'Password must be at least 8 characters.';
    elseif ($pw !== ($_POST['confirm'] ?? '')) $err = 'The two passwords do not match.';
    elseif (val('SELECT 1 FROM users WHERE email=? OR admission_no=?', [$email, $adm])) $err = 'That email or admission number is already registered.';
    else {
        $dept = val('SELECT department_id FROM courses WHERE id=?', [$course]);
        $status = REQUIRE_APPROVAL ? 'pending' : 'active';
        q("INSERT INTO users(role,admission_no,full_name,email,phone,password_hash,department_id,course_id,status) VALUES('student',?,?,?,?,?,?,?,?)",
          [$adm, $name, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $dept ?: null, $course, $status]);
        $newId = (int)db()->lastInsertId();
        audit('register', "$adm $name", $newId);
        if (REQUIRE_APPROVAL) {
            q("INSERT INTO notifications(user_id,message,link) SELECT id, ?, ? FROM users WHERE role='admin' AND status='active'",
              ["New student registration waiting for approval: $name ($adm)", 'users.php?status=pending']);
        }
        flash(REQUIRE_APPROVAL ? 'Registered! An administrator must approve your account before you can log in.' : 'Registered! You can now log in.');
        redirect('login.php');
    }
}
$bare = true; $pageTitle = 'Register';
include __DIR__ . '/inc/header.php';
?>
<h3>Student registration</h3>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>Full name *</label><input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
  <label>Admission number *</label><input type="text" name="admission_no" required value="<?= e($_POST['admission_no'] ?? '') ?>">
  <label>Email *</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
  <label>Phone</label><input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
  <label>Course *</label>
  <select name="course_id" required><?= opt_list(OPT_COURSES, $_POST['course_id'] ?? null, 'Select your course') ?></select>
  <label>Password * (at least 8 characters)</label><input type="password" name="password" required>
  <label>Confirm password *</label><input type="password" name="confirm" required>
  <p><button>Register</button></p>
</form>
<p class="muted"><a href="<?= u('login.php') ?>">Back to login</a></p>
<?php include __DIR__ . '/inc/footer.php';
