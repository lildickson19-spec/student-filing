<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');
$u = me(); $pageTitle = 'Users'; $err = '';

function uaction(int $id, string $act, string $label, string $cls = 'sec', string $new = ''): string {
    return '<form class="inline" method="post"' . ($act === 'reset' ? ' data-confirm="Reset this user\'s password?"' : '') . '>' . csrf_field()
         . '<input type="hidden" name="action" value="' . $act . '"><input type="hidden" name="id" value="' . $id . '">'
         . ($new !== '' ? '<input type="hidden" name="new" value="' . e($new) . '">' : '')
         . '<button class="small ' . $cls . '">' . e($label) . '</button></form> ';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $uid = (int)($_POST['id'] ?? 0);
    if ($act === 'create') {
        $role = $_POST['role'] ?? ''; $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
        $adm = trim($_POST['admission_no'] ?? ''); $pw = $_POST['password'] ?? ''; $course = nn($_POST['course_id'] ?? ''); $dept = nn($_POST['department_id'] ?? '');
        if ($role !== 'student') $adm = '';
        if (!in_array($role, ['student', 'lecturer', 'admin'], true) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Choose a role and enter a name and valid email.';
        elseif (strlen($pw) < 8) $err = 'The password must be at least 8 characters.';
        elseif ($role === 'student' && $adm === '') $err = 'Students need an admission number.';
        elseif (val('SELECT 1 FROM users WHERE email=? OR (admission_no IS NOT NULL AND admission_no=?)', [$email, $adm])) $err = 'That email or admission number already exists.';
        else {
            if ($course && !$dept) $dept = val('SELECT department_id FROM courses WHERE id=?', [$course]) ?: null;
            q("INSERT INTO users(role,admission_no,full_name,email,password_hash,department_id,course_id,status) VALUES(?,?,?,?,?,?,?,'active')",
              [$role, $adm !== '' ? $adm : null, $name, $email, password_hash($pw, PASSWORD_DEFAULT), $dept, $course]);
            audit('create_user', "$role $email"); flash(ucfirst($role) . ' account created.'); redirect('users.php');
        }
    } elseif ($uid && $act === 'approve') {
        q("UPDATE users SET status='active' WHERE id=? AND status='pending'", [$uid]); audit('approve_user', "#$uid"); flash('Account approved.'); redirect('users.php');
    } elseif ($uid && $act === 'status') {
        $new = $_POST['new'] ?? '';
        if ($uid === (int)$u['id']) flash('You cannot change your own account status.', 'err');
        elseif (in_array($new, ['active', 'suspended', 'inactive'], true)) { q('UPDATE users SET status=? WHERE id=?', [$new, $uid]); audit('user_status', "#$uid -> $new"); flash("Account is now $new."); }
        redirect('users.php');
    } elseif ($uid && $act === 'reset') {
        $tmp = substr(bin2hex(random_bytes(8)), 0, 10);
        q('UPDATE users SET password_hash=?, failed_logins=0, locked_until=NULL WHERE id=?', [password_hash($tmp, PASSWORD_DEFAULT), $uid]);
        audit('reset_password', "#$uid"); flash("Temporary password: $tmp  (give it to the user and ask them to change it after logging in)."); redirect('users.php');
    }
}

$g = $_GET; $w = ['1=1']; $p = [];
if (trim($g['q'] ?? '') !== '') { $w[] = '(x.full_name LIKE ? OR x.email LIKE ? OR x.admission_no LIKE ?)'; $l = '%' . trim($g['q']) . '%'; array_push($p, $l, $l, $l); }
if (!empty($g['role']))   { $w[] = 'x.role=?';   $p[] = $g['role']; }
if (!empty($g['status'])) { $w[] = 'x.status=?'; $p[] = $g['status']; }
if (!empty($g['course_id'])) { $w[] = 'x.course_id=?'; $p[] = (int)$g['course_id']; }
$where = implode(' AND ', $w);
$total = (int)val("SELECT COUNT(*) FROM users x WHERE $where", $p);
[$page, $pages, $off, $per] = paginate($total, 25);
$rows = all("SELECT x.*, co.code course FROM users x LEFT JOIN courses co ON co.id=x.course_id WHERE $where ORDER BY x.id DESC LIMIT $per OFFSET $off", $p);
include __DIR__ . '/inc/header.php';
?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<details class="card" <?= $err ? 'open' : '' ?>><summary><b>Create an account</b> (student, lecturer or administrator)</summary>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="two">
      <div><label>Role</label><select name="role"><option value="lecturer">Lecturer</option><option value="student">Student</option><option value="admin">Administrator</option></select></div>
      <div><label>Full name</label><input type="text" name="full_name" required></div>
      <div><label>Email</label><input type="email" name="email" required></div>
      <div><label>Admission number (students only)</label><input type="text" name="admission_no"></div>
      <div><label>Department</label><select name="department_id"><?= opt_list(OPT_DEPARTMENTS, null, 'None') ?></select></div>
      <div><label>Course (students)</label><select name="course_id"><?= opt_list(OPT_COURSES, null, 'None') ?></select></div>
      <div><label>Initial password (at least 8 characters)</label><input type="password" name="password" required></div>
    </div>
    <p><button>Create account</button></p>
  </form></details>

<div class="card"><form method="get" class="filters">
  <div><label>Search</label><input type="text" name="q" value="<?= e($g['q'] ?? '') ?>" placeholder="Name, email, admission no."></div>
  <div><label>Role</label><select name="role"><option value="">All</option>
    <?php foreach (['student', 'lecturer', 'admin'] as $r): ?><option <?= ($g['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
  <div><label>Status</label><select name="status"><option value="">All</option>
    <?php foreach (['pending', 'active', 'suspended', 'inactive'] as $r): ?><option <?= ($g['status'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
  <div><label>Course</label><select name="course_id"><?= opt_list(OPT_COURSES, $g['course_id'] ?? null) ?></select></div>
  <div><button>Filter</button> <a class="btn sec" href="<?= u('users.php') ?>">Clear</a></div></form></div>

<div class="card"><p class="muted"><?= $total ?> account(s)</p>
  <div class="scroll"><table>
  <tr><th>Name</th><th>Role</th><th>Adm. no.</th><th>Course</th><th>Status</th><th>Last login</th><th>Actions</th></tr>
  <?php foreach ($rows as $r): $self = (int)$r['id'] === (int)$u['id']; ?>
    <tr><td><?= e($r['full_name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
      <td><?= e($r['role']) ?></td><td><?= e($r['admission_no'] ?? '-') ?></td><td><?= e($r['course'] ?? '-') ?></td>
      <td><span class="badge"><?= e($r['status']) ?></span></td><td><?= e($r['last_login'] ?? 'never') ?></td>
      <td>
        <?php if ($r['status'] === 'pending'): ?>
          <?= uaction((int)$r['id'], 'approve', 'Approve', 'ok') ?><?= uaction((int)$r['id'], 'status', 'Reject', 'bad', 'inactive') ?>
        <?php elseif (!$self): ?>
          <?php if ($r['status'] === 'active'): ?>
            <?= uaction((int)$r['id'], 'status', 'Suspend', 'sec', 'suspended') ?><?= uaction((int)$r['id'], 'status', 'Deactivate', 'sec', 'inactive') ?>
          <?php else: ?><?= uaction((int)$r['id'], 'status', 'Activate', 'ok', 'active') ?><?php endif; ?>
          <?= uaction((int)$r['id'], 'reset', 'Reset password', 'sec') ?>
        <?php else: ?><span class="muted">(you)</span><?php endif; ?>
      </td></tr>
  <?php endforeach; ?></table></div>
  <?= pager($page, $pages) ?>
</div>
<?php include __DIR__ . '/inc/footer.php';
