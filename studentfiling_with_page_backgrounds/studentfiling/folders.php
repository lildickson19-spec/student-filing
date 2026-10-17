<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('student');
$u = me(); $pageTitle = 'My folders';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') flash('Enter a folder name.', 'err');
        elseif (val('SELECT 1 FROM folders WHERE student_id=? AND name=?', [$u['id'], $name])) flash('You already have a folder with that name.', 'err');
        else { q('INSERT INTO folders(student_id,name) VALUES(?,?)', [$u['id'], $name]); audit('create_folder', $name); flash('Folder created.'); }
    } elseif ($act === 'delete') {
        $fid = (int)($_POST['id'] ?? 0);
        if (val('SELECT 1 FROM folders WHERE id=? AND student_id=?', [$fid, $u['id']])) {
            q('DELETE FROM folders WHERE id=?', [$fid]); audit('delete_folder', "#$fid");
            flash('Folder deleted. Its documents were kept and now have no folder.');
        }
    }
    redirect('folders.php');
}
$folders = all('SELECT f.*, (SELECT COUNT(*) FROM documents d WHERE d.folder_id=f.id) AS n FROM folders f WHERE f.student_id=? ORDER BY f.name', [$u['id']]);
include __DIR__ . '/inc/header.php';
?>
<div class="two">
<div class="card"><h3>New folder</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Folder name (e.g. Year 1, Attachment)</label><input type="text" name="name" required maxlength="100">
    <p><button>Create folder</button></p></form></div>
<div class="card"><h3>My folders</h3>
  <?php if (!$folders): ?><p class="muted">No folders yet.</p><?php else: ?>
  <table><tr><th>Folder</th><th>Documents</th><th></th></tr>
  <?php foreach ($folders as $f): ?>
    <tr><td><a href="<?= u('documents.php?folder_id=' . $f['id']) ?>"><?= e($f['name']) ?></a></td><td><?= (int)$f['n'] ?></td>
      <td><a class="small" href="<?= u('download_zip.php?folder_id=' . $f['id']) ?>">ZIP</a>
        <form class="inline" method="post" data-confirm="Delete this folder? Its documents are kept."><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="bad small">Delete</button></form></td></tr>
  <?php endforeach; ?></table><?php endif; ?></div>
</div>
<?php include __DIR__ . '/inc/footer.php';
