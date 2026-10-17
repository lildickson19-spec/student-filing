<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');
$u = me(); $pageTitle = 'Backup and restore';
const MARK = "\n-- ---END---\n";

// Writes every table (structure + data) as SQL text
function make_dump(): string {
    $pdo = db();
    $out = 'SET FOREIGN_KEY_CHECKS=0;' . MARK;
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $out .= "DROP TABLE IF EXISTS `$t`;" . MARK . $create . ';' . MARK;
        $rows = $pdo->query("SELECT * FROM `$t`");
        while ($r = $rows->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string)$v); }, $r);
            $out .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ');' . MARK;
        }
    }
    return $out . 'SET FOREIGN_KEY_CHECKS=1;' . MARK;
}
// Puts a backup zip back: database first, then the uploaded files. Returns '' on success or an error message.
function restore_zip(string $path): string {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return 'That is not a valid backup file.';
    $sql = $zip->getFromName('database.sql');
    if ($sql === false) return 'This zip does not contain database.sql, so it is not one of our backups.';
    try {
        foreach (explode(MARK, $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') db()->exec($stmt);
        }
    } catch (PDOException $ex) { return 'Restore failed: ' . $ex->getMessage(); }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        $b = basename($n);
        if (strpos($n, 'uploads/') === 0 && preg_match('/^[a-f0-9]{32}\.(pdf|docx?|jpe?g|png)$/', $b)) {
            file_put_contents(UPLOAD_DIR . '/' . $b, $zip->getFromIndex($i));
        }
    }
    $zip->close();
    return '';
}

if (!class_exists('ZipArchive')) { include __DIR__ . '/inc/header.php'; echo '<div class="flash err">The PHP zip extension is not enabled. In XAMPP open php.ini and remove the ; before extension=zip, then restart Apache.</div>'; include __DIR__ . '/inc/footer.php'; exit; }

if (isset($_GET['get'])) {
    $f = basename($_GET['get']);
    if (!preg_match('/^backup-\d{8}-\d{6}\.zip$/', $f) || !is_file(BACKUP_DIR . '/' . $f)) { http_response_code(404); exit('Not found.'); }
    audit('backup_download', $f);
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize(BACKUP_DIR . '/' . $f));
    header('Content-Disposition: attachment; filename="' . $f . '"');
    readfile(BACKUP_DIR . '/' . $f); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $name = 'backup-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        $zip->open(BACKUP_DIR . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', make_dump());
        foreach (glob(UPLOAD_DIR . '/*') ?: [] as $file) { if (is_file($file)) $zip->addFile($file, 'uploads/' . basename($file)); }
        $zip->close();
        audit('backup_create', $name); flash("Backup created: $name");
    } elseif ($act === 'delete') {
        $f = basename($_POST['file'] ?? '');
        if (preg_match('/^backup-\d{8}-\d{6}\.zip$/', $f)) { @unlink(BACKUP_DIR . '/' . $f); audit('backup_delete', $f); flash('Backup deleted.'); }
    } elseif ($act === 'restore') {
        if (empty($_POST['sure'])) { flash('Tick the box to confirm the restore.', 'err'); redirect('backup.php'); }
        $src = '';
        if (!empty($_FILES['upload']['tmp_name']) && ($_FILES['upload']['error'] ?? 1) === UPLOAD_ERR_OK) $src = $_FILES['upload']['tmp_name'];
        else {
            $f = basename($_POST['file'] ?? '');
            if (preg_match('/^backup-\d{8}-\d{6}\.zip$/', $f) && is_file(BACKUP_DIR . '/' . $f)) $src = BACKUP_DIR . '/' . $f;
        }
        if ($src === '') { flash('Choose a saved backup or upload a backup zip.', 'err'); redirect('backup.php'); }
        $msg = restore_zip($src);
        if ($msg !== '') { flash($msg, 'err'); redirect('backup.php'); }
        try { audit('restore', 'Backup restored'); } catch (Throwable $ex) { }
        unset($_SESSION['uid']);
        flash('Restore complete. Please log in again.');
        redirect('login.php');
    }
    redirect('backup.php');
}
$files = array_reverse(array_values(array_filter(scandir(BACKUP_DIR), function ($f) { return preg_match('/^backup-\d{8}-\d{6}\.zip$/', $f); })));
include __DIR__ . '/inc/header.php';
?>
<div class="two">
<div class="card"><h3>Create a backup</h3>
  <p class="muted">Saves the whole database and every uploaded document into one zip file in <code>storage/backups</code>. Copy these zips to a flash drive or another computer regularly.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><button>Back up now</button></form></div>
<div class="card"><h3>Restore</h3>
  <p class="muted">Replaces ALL current data with the backup. Log in again afterwards.</p>
  <form method="post" enctype="multipart/form-data" data-confirm="This replaces all current data. Continue?"><?= csrf_field() ?><input type="hidden" name="action" value="restore">
    <label>A saved backup</label><select name="file"><option value="">Choose...</option><?php foreach ($files as $f): ?><option><?= e($f) ?></option><?php endforeach; ?></select>
    <label>...or upload a backup zip</label><input type="file" name="upload" accept=".zip">
    <label style="font-weight:400"><input type="checkbox" name="sure" value="1"> I understand the current data will be replaced</label>
    <p><button class="bad">Restore</button></p></form></div>
</div>
<div class="card"><h3>Saved backups</h3>
  <?php if (!$files): ?><p class="muted">No backups yet.</p><?php else: ?>
  <table><tr><th>File</th><th>Size</th><th></th></tr>
  <?php foreach ($files as $f): ?>
    <tr><td><?= e($f) ?></td><td><?= fmt_size(filesize(BACKUP_DIR . '/' . $f)) ?></td>
      <td><a class="btn sec small" href="?get=<?= urlencode($f) ?>">Download</a>
        <form class="inline" method="post" data-confirm="Delete this backup?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="<?= e($f) ?>"><button class="bad small">Delete</button></form></td></tr>
  <?php endforeach; ?></table><?php endif; ?></div>
<?php include __DIR__ . '/inc/footer.php';
