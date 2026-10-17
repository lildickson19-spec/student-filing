<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me(); $pageTitle = 'Dashboard';
$ann = all('SELECT a.*, x.full_name FROM announcements a JOIN users x ON x.id=a.admin_id WHERE ' . ann_audience_sql() . ' ORDER BY a.id DESC LIMIT 3');

if ($u['role'] === 'student') {
    $counts = [];
    foreach (all('SELECT status, COUNT(*) n FROM documents WHERE student_id=? GROUP BY status', [$u['id']]) as $r) $counts[$r['status']] = $r['n'];
    $recent = all('SELECT d.id, d.title, d.status, d.created_at, c.name cat FROM documents d JOIN categories c ON c.id=d.category_id WHERE d.student_id=? ORDER BY d.id DESC LIMIT 6', [$u['id']]);
} elseif ($u['role'] === 'lecturer') {
    $lid = (int)$u['id'];
    $nStudents = val("SELECT COUNT(*) FROM users s WHERE s.role='student' AND s.status='active' AND " . lec_student_scope($lid));
    $nPending  = val("SELECT COUNT(*) FROM documents d JOIN users s ON s.id=d.student_id WHERE d.status IN ('Pending','Under Review') AND " . lec_scope($lid));
    $nReviews  = val('SELECT COUNT(*) FROM comments WHERE user_id=? AND status_set IS NOT NULL', [$lid]);
    $recent = all('SELECT d.id, d.title, d.status, d.created_at, s.full_name FROM documents d JOIN users s ON s.id=d.student_id WHERE ' . lec_scope($lid) . ' ORDER BY d.id DESC LIMIT 8');
} else {
    $stats = [
        'Total users'      => val('SELECT COUNT(*) FROM users'),
        'Active students'  => val("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'"),
        'Lecturers'        => val("SELECT COUNT(*) FROM users WHERE role='lecturer' AND status='active'"),
        'Documents'        => val('SELECT COUNT(*) FROM documents'),
        'Pending reviews'  => val("SELECT COUNT(*) FROM documents WHERE status IN ('Pending','Under Review')"),
        'Approved records' => val("SELECT COUNT(*) FROM documents WHERE status='Approved'"),
    ];
    $pendingUsers = val("SELECT COUNT(*) FROM users WHERE status='pending'");
    $recent = all('SELECT d.id, d.title, d.status, d.created_at, s.full_name FROM documents d JOIN users s ON s.id=d.student_id ORDER BY d.id DESC LIMIT 8');
}
include __DIR__ . '/inc/header.php';
?>
<p>Welcome, <b><?= e($u['full_name']) ?></b>.</p>

<?php if ($u['role'] === 'student'): ?>
  <div class="grid">
    <?php foreach (STATUSES as $st): ?>
      <a class="stat" style="text-decoration:none;color:inherit" href="<?= u('documents.php?status=' . urlencode($st)) ?>"><b><?= (int)($counts[$st] ?? 0) ?></b><span><?= e($st) ?></span></a>
    <?php endforeach; ?>
  </div>
  <p><a class="btn" href="<?= u('upload.php') ?>">Upload a document</a></p>
<?php elseif ($u['role'] === 'lecturer'): ?>
  <div class="grid">
    <div class="stat"><b><?= (int)$nStudents ?></b><span>My students</span></div>
    <div class="stat"><b><?= (int)$nPending ?></b><span>Waiting for review</span></div>
    <div class="stat"><b><?= (int)$nReviews ?></b><span>Reviews I have made</span></div>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($stats as $label => $n): ?><div class="stat"><b><?= (int)$n ?></b><span><?= e($label) ?></span></div><?php endforeach; ?>
  </div>
  <?php if ($pendingUsers): ?>
    <div class="flash err"><?= (int)$pendingUsers ?> registration(s) waiting for approval. <a href="<?= u('users.php?status=pending') ?>">Review them</a></div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <h3><?= $u['role'] === 'student' ? 'My recent documents' : 'Recently uploaded documents' ?></h3>
  <?php if (!$recent): ?><p class="muted">Nothing here yet.</p><?php else: ?>
  <div class="scroll"><table>
    <tr><th>Title</th><?= $u['role'] === 'student' ? '<th>Category</th>' : '<th>Student</th>' ?><th>Status</th><th>Date</th></tr>
    <?php foreach ($recent as $r): ?>
      <tr><td><a href="<?= u('document.php?id=' . $r['id']) ?>"><?= e($r['title']) ?></a></td>
          <td><?= e($u['role'] === 'student' ? $r['cat'] : $r['full_name']) ?></td>
          <td><?= badge($r['status']) ?></td><td><?= e($r['created_at']) ?></td></tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>

<div class="card">
  <h3>Announcements</h3>
  <?php if (!$ann): ?><p class="muted">No announcements.</p><?php endif; ?>
  <?php foreach ($ann as $a): ?>
    <p><b><?= e($a['title']) ?></b> <span class="muted">- <?= e($a['created_at']) ?></span><br><?= nl2br(e($a['body'])) ?></p>
  <?php endforeach; ?>
  <a href="<?= u('announcements.php') ?>">All announcements</a>
</div>
<?php include __DIR__ . '/inc/footer.php';
