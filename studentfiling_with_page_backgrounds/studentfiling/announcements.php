<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me(); $pageTitle = 'Announcements';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'admin') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $title = trim($_POST['title'] ?? ''); $body = trim($_POST['body'] ?? ''); $aud = $_POST['audience'] ?? 'all';
        $roles = ['all' => "role IN ('student','lecturer')", 'students' => "role='student'", 'lecturers' => "role='lecturer'"];
        if ($title === '' || $body === '' || !isset($roles[$aud])) flash('Enter a title and a message.', 'err');
        else {
            q('INSERT INTO announcements(admin_id,title,body,audience) VALUES(?,?,?,?)', [$u['id'], $title, $body, $aud]);
            q("INSERT INTO notifications(user_id,message,link) SELECT id, ?, 'announcements.php' FROM users WHERE status='active' AND " . $roles[$aud], ["New announcement: $title"]);
            audit('announcement', $title); flash('Announcement published.');
        }
    } elseif ($act === 'delete') {
        q('DELETE FROM announcements WHERE id=?', [(int)($_POST['id'] ?? 0)]); audit('announcement_delete', '#' . (int)$_POST['id']); flash('Announcement deleted.');
    }
    redirect('announcements.php');
}
$rows = all('SELECT a.*, x.full_name FROM announcements a JOIN users x ON x.id=a.admin_id WHERE ' . ann_audience_sql() . ' ORDER BY a.id DESC LIMIT 50');
include __DIR__ . '/inc/header.php';
?>
<?php if ($u['role'] === 'admin'): ?>
<div class="card"><h3>Publish an announcement</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Title</label><input type="text" name="title" required maxlength="200">
    <label>Message</label><textarea name="body" rows="4" required></textarea>
    <label>Who should see it?</label>
    <select name="audience"><option value="all">Everyone (students and lecturers)</option><option value="students">Students only</option><option value="lecturers">Lecturers only</option></select>
    <p><button>Publish</button></p></form></div>
<?php endif; ?>
<?php if (!$rows): ?><div class="card"><p class="muted">No announcements yet.</p></div><?php endif; ?>
<?php foreach ($rows as $a): ?>
<div class="card">
  <h3><?= e($a['title']) ?></h3>
  <p class="muted"><?= e($a['created_at']) ?> by <?= e($a['full_name']) ?><?= $u['role'] === 'admin' ? ' &middot; for ' . e($a['audience']) : '' ?></p>
  <p><?= nl2br(e($a['body'])) ?></p>
  <?php if ($u['role'] === 'admin'): ?>
  <form method="post" data-confirm="Delete this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="bad small">Delete</button></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/inc/footer.php';
