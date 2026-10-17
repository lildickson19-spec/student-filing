<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('student', 'lecturer');
$u = me();
if ($u['role'] === 'student') {
    $pageTitle = 'My history';
    $versions = all('SELECT v.version, v.original_name, v.uploaded_at, v.document_id, d.title FROM document_versions v JOIN documents d ON d.id=v.document_id WHERE d.student_id=? ORDER BY v.id DESC LIMIT 50', [$u['id']]);
    $logs = all('SELECT action, details, created_at FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 100', [$u['id']]);
} else {
    $pageTitle = 'My review history';
    $reviews = all('SELECT cm.status_set, cm.body, cm.created_at, d.id doc_id, d.title, s.full_name FROM comments cm JOIN documents d ON d.id=cm.document_id JOIN users s ON s.id=d.student_id WHERE cm.user_id=? AND cm.status_set IS NOT NULL ORDER BY cm.id DESC LIMIT 100', [$u['id']]);
}
include __DIR__ . '/inc/header.php';
?>
<?php if ($u['role'] === 'student'): ?>
<div class="card"><h3>Uploads and replacements</h3>
  <?php if (!$versions): ?><p class="muted">No uploads yet.</p><?php else: ?>
  <div class="scroll"><table><tr><th>Document</th><th>File</th><th>Version</th><th>Date</th></tr>
  <?php foreach ($versions as $v): ?>
    <tr><td><a href="<?= u('document.php?id=' . $v['document_id']) ?>"><?= e($v['title']) ?></a></td><td><?= e($v['original_name']) ?></td><td><?= (int)$v['version'] ?></td><td><?= e($v['uploaded_at']) ?></td></tr>
  <?php endforeach; ?></table></div><?php endif; ?></div>
<div class="card"><h3>Account activity</h3>
  <div class="scroll"><table><tr><th>Date</th><th>Action</th><th>Details</th></tr>
  <?php foreach ($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['details']) ?></td></tr><?php endforeach; ?></table></div></div>
<?php else: ?>
<div class="card"><h3>Reviews and decisions</h3>
  <?php if (!$reviews): ?><p class="muted">You have not reviewed any documents yet.</p><?php else: ?>
  <div class="scroll"><table><tr><th>Date</th><th>Student</th><th>Document</th><th>Decision</th><th>Comment</th></tr>
  <?php foreach ($reviews as $r): ?>
    <tr><td><?= e($r['created_at']) ?></td><td><?= e($r['full_name']) ?></td>
      <td><a href="<?= u('document.php?id=' . $r['doc_id']) ?>"><?= e($r['title']) ?></a></td><td><?= badge($r['status_set']) ?></td><td><?= e($r['body']) ?></td></tr>
  <?php endforeach; ?></table></div><?php endif; ?></div>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php';
