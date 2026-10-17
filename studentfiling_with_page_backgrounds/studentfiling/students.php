<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('lecturer');
$u = me(); $lid = (int)$u['id']; $pageTitle = 'My students';
$t = trim($_GET['q'] ?? '');
$w = "s.role='student' AND s.status='active' AND " . lec_student_scope($lid); $p = [];
if ($t !== '') { $w .= ' AND (s.full_name LIKE ? OR s.admission_no LIKE ?)'; $p = ["%$t%", "%$t%"]; }
$rows = all("SELECT s.id, s.full_name, s.admission_no, s.email, co.code course, dp.name dept,
    (SELECT COUNT(*) FROM documents d WHERE d.student_id=s.id) docs,
    (SELECT COUNT(*) FROM documents d WHERE d.student_id=s.id AND d.status IN ('Pending','Under Review')) pending
    FROM users s LEFT JOIN courses co ON co.id=s.course_id LEFT JOIN departments dp ON dp.id=s.department_id
    WHERE $w ORDER BY s.full_name", $p);
include __DIR__ . '/inc/header.php';
?>
<div class="card"><form method="get" class="filters">
  <div><label>Search</label><input type="text" name="q" value="<?= e($t) ?>" placeholder="Name or admission number"></div>
  <div><button>Search</button> <a class="btn sec" href="<?= u('students.php') ?>">Clear</a></div></form></div>
<div class="card">
  <?php if (!$rows): ?><p class="muted">No students are assigned to you yet. The administrator assigns courses and students to lecturers.</p><?php else: ?>
  <div class="scroll"><table><tr><th>Name</th><th>Admission no.</th><th>Course</th><th>Department</th><th>Documents</th><th>To review</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= u('documents.php?student_id=' . $r['id']) ?>"><?= e($r['full_name']) ?></a><br><span class="muted"><?= e($r['email']) ?></span></td>
      <td><?= e($r['admission_no']) ?></td><td><?= e($r['course'] ?? '-') ?></td><td><?= e($r['dept'] ?? '-') ?></td><td><?= (int)$r['docs'] ?></td><td><?= (int)$r['pending'] ?></td></tr>
  <?php endforeach; ?></table></div><?php endif; ?>
</div>
<?php include __DIR__ . '/inc/footer.php';
