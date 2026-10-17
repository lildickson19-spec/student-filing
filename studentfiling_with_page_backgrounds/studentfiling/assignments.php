<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');
$u = me(); $pageTitle = 'Lecturer assignments';
$lecturers = all("SELECT id, full_name, email FROM users WHERE role='lecturer' AND status='active' ORDER BY full_name");
$lid = (int)($_GET['lecturer_id'] ?? $_POST['lecturer_id'] ?? 0);
$lec = null;
foreach ($lecturers as $l) { if ((int)$l['id'] === $lid) $lec = $l; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lec) {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_courses') {
        q('DELETE FROM lecturer_courses WHERE lecturer_id=?', [$lid]);
        foreach ((array)($_POST['courses'] ?? []) as $cid) {
            if (val('SELECT 1 FROM courses WHERE id=?', [(int)$cid])) q('INSERT IGNORE INTO lecturer_courses(lecturer_id,course_id) VALUES(?,?)', [$lid, (int)$cid]);
        }
        audit('assign_courses', $lec['full_name']); flash('Courses saved.');
    } elseif ($act === 'add_student') {
        $stu = one("SELECT id, full_name FROM users WHERE role='student' AND admission_no=?", [trim($_POST['admission_no'] ?? '')]);
        if (!$stu) flash('No student has that admission number.', 'err');
        else { q('INSERT IGNORE INTO lecturer_students(lecturer_id,student_id) VALUES(?,?)', [$lid, $stu['id']]); audit('assign_student', $lec['full_name'] . ' <- ' . $stu['full_name']); flash('Student assigned.'); }
    } elseif ($act === 'remove_student') {
        q('DELETE FROM lecturer_students WHERE lecturer_id=? AND student_id=?', [$lid, (int)($_POST['student_id'] ?? 0)]); audit('unassign_student', "#" . (int)$_POST['student_id']); flash('Student removed.');
    }
    redirect('assignments.php?lecturer_id=' . $lid);
}
if ($lec) {
    $mine = array_column(all('SELECT course_id FROM lecturer_courses WHERE lecturer_id=?', [$lid]), 'course_id');
    $courses = all("SELECT id, CONCAT(code, ' - ', name) AS label FROM courses ORDER BY code");
    $direct = all('SELECT s.id, s.full_name, s.admission_no FROM lecturer_students ls JOIN users s ON s.id=ls.student_id WHERE ls.lecturer_id=? ORDER BY s.full_name', [$lid]);
    $visible = (int)val("SELECT COUNT(*) FROM users s WHERE s.role='student' AND s.status='active' AND " . lec_student_scope($lid));
}
include __DIR__ . '/inc/header.php';
?>
<div class="card"><form method="get" class="filters">
  <div><label>Choose a lecturer</label><select name="lecturer_id" onchange="this.form.submit()"><option value="">Select...</option>
    <?php foreach ($lecturers as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $lid === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['full_name']) ?> (<?= e($l['email']) ?>)</option><?php endforeach; ?></select></div>
  <div><button>Open</button></div></form>
  <?php if (!$lecturers): ?><p class="muted">There are no lecturer accounts yet. Create one under Users.</p><?php endif; ?></div>

<?php if ($lec): ?>
<p class="muted"><?= e($lec['full_name']) ?> can currently see <b><?= $visible ?></b> student(s).
   A lecturer only sees students in their assigned courses, students assigned directly, and documents linked to their courses.</p>
<div class="two">
<div class="card"><h3>Courses / classes taught</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_courses"><input type="hidden" name="lecturer_id" value="<?= $lid ?>">
    <?php foreach ($courses as $c): ?>
      <label style="font-weight:400"><input type="checkbox" name="courses[]" value="<?= (int)$c['id'] ?>" <?= in_array($c['id'], $mine) ? 'checked' : '' ?>> <?= e($c['label']) ?></label>
    <?php endforeach; ?>
    <p><button>Save courses</button></p></form></div>
<div class="card"><h3>Individual students</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_student"><input type="hidden" name="lecturer_id" value="<?= $lid ?>">
    <label>Add a student by admission number</label><input type="text" name="admission_no" required>
    <p><button>Assign student</button></p></form>
  <?php if ($direct): ?><table><tr><th>Student</th><th></th></tr>
  <?php foreach ($direct as $s): ?>
    <tr><td><?= e($s['full_name']) ?> <span class="muted">(<?= e($s['admission_no']) ?>)</span></td>
      <td><form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_student"><input type="hidden" name="lecturer_id" value="<?= $lid ?>"><input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>"><button class="small bad">Remove</button></form></td></tr>
  <?php endforeach; ?></table><?php endif; ?></div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php';
