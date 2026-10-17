<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me(); $pageTitle = 'Notifications';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    q('DELETE FROM notifications WHERE user_id=?', [$u['id']]);
    flash('Notifications cleared.'); redirect('notifications.php');
}
$rows = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$u['id']]);
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);   // mark as read once shown
include __DIR__ . '/inc/header.php';
?>
<div class="card">
  <?php if (!$rows): ?><p class="muted">You have no notifications.</p><?php else: ?>
  <table>
  <?php foreach ($rows as $n): ?>
    <tr class="<?= $n['is_read'] ? '' : 'unread' ?>"><td><?= $n['link'] ? '<a href="' . e(u($n['link'])) . '">' . e($n['message']) . '</a>' : e($n['message']) ?></td><td class="muted"><?= e($n['created_at']) ?></td></tr>
  <?php endforeach; ?></table>
  <form method="post" data-confirm="Clear all notifications?"><?= csrf_field() ?><p><button class="sec">Clear all</button></p></form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/inc/footer.php';
