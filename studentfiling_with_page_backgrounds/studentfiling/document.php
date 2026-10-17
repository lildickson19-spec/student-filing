<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me();
$id = (int)($_GET['id'] ?? 0);
$d = get_doc($id);
if (!$d || !can_access_doc($d)) { http_response_code(404); exit('Document not found, or you do not have access to it.'); }
$isOwner = $u['role'] === 'student' && (int)$d['student_id'] === (int)$u['id'];
$isStaff = in_array($u['role'], ['lecturer', 'admin'], true);
$self = 'document.php?id=' . $id;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($isOwner && $act === 'update') {
        $title = trim($_POST['title'] ?? '');
        $folder = nn($_POST['folder_id'] ?? '');
        if ($folder && !val('SELECT 1 FROM folders WHERE id=? AND student_id=?', [$folder, $u['id']])) $folder = null;
        if ($title === '') { $err = 'The title is required.'; }
        elseif (!val('SELECT 1 FROM categories WHERE id=?', [(int)($_POST['category_id'] ?? 0)])) { $err = 'Choose a category.'; }
        else {
            q('UPDATE documents SET title=?, description=?, category_id=?, folder_id=?, course_id=?, academic_year_id=?, semester_id=? WHERE id=?',
              [$title, trim($_POST['description'] ?? ''), (int)$_POST['category_id'], $folder, nn($_POST['course_id'] ?? ''),
               nn($_POST['academic_year_id'] ?? ''), nn($_POST['semester_id'] ?? ''), $id]);
            audit('edit_document', "#$id $title"); flash('Document details updated.'); redirect($self);
        }
    }

    if ($isOwner && $act === 'replace') {
        $f = save_upload($_FILES['file'] ?? [], $e2);
        if (!$f) { $err = $e2; }
        else {
            $ver = (int)$d['version'] + 1;
            q("UPDATE documents SET stored_name=?, original_name=?, mime=?, size=?, version=?, status='Pending', reviewed_by=NULL, reviewed_at=NULL WHERE id=?",
              [$f['stored'], $f['original'], $f['mime'], $f['size'], $ver, $id]);
            q('INSERT INTO document_versions(document_id,version,original_name,stored_name,size) VALUES(?,?,?,?,?)', [$id, $ver, $f['original'], $f['stored'], $f['size']]);
            notify_staff_of_doc(['id' => $u['id'], 'course_id' => $u['course_id']], $d['course_id'] ? (int)$d['course_id'] : null, $id, $u['full_name'] . ' uploaded a new version of "' . $d['title'] . '"');
            audit('replace_document', "#$id v$ver"); flash('New version uploaded. The status is back to Pending.'); redirect($self);
        }
    }

    if (($isOwner || $u['role'] === 'admin') && $act === 'delete') {
        if ($isOwner && $d['status'] === 'Approved') { flash('Approved documents cannot be deleted. Ask the administrator.', 'err'); redirect($self); }
        foreach (all('SELECT stored_name FROM document_versions WHERE document_id=?', [$id]) as $v) @unlink(UPLOAD_DIR . '/' . basename($v['stored_name']));
        @unlink(UPLOAD_DIR . '/' . basename($d['stored_name']));
        q('DELETE FROM documents WHERE id=?', [$id]);
        audit('delete_document', "#$id {$d['title']} ({$d['student_name']})"); flash('Document deleted.'); redirect('documents.php');
    }

    if ($isStaff && $act === 'review') {
        $allowed = $u['role'] === 'admin' ? STATUSES
                 : (LECTURER_CAN_APPROVE ? ['Under Review', 'Reviewed', 'Approved', 'Rejected', 'Revisions Required'] : ['Under Review', 'Reviewed', 'Revisions Required']);
        $st = $_POST['status'] ?? ''; $body = trim($_POST['comment'] ?? '');
        if ($st !== '' && !in_array($st, $allowed, true)) { $err = 'You cannot set that status.'; }
        elseif ($st === '' && $body === '') { $err = 'Choose a status or write a comment.'; }
        elseif (in_array($st, ['Rejected', 'Revisions Required'], true) && $body === '') { $err = 'Please explain what must be corrected.'; }
        else {
            if ($st !== '') q('UPDATE documents SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?', [$st, $u['id'], $id]);
            q('INSERT INTO comments(document_id,user_id,body,status_set) VALUES(?,?,?,?)', [$id, $u['id'], $body, $st === '' ? null : $st]);
            notify((int)$d['student_id'], 'Your document "' . $d['title'] . '"' . ($st !== '' ? " is now: $st" : ' has a new comment'), $self);
            audit('review', "#$id " . ($st !== '' ? $st : 'comment')); flash('Saved.'); redirect($self);
        }
    }

    if ($isOwner && $act === 'comment' && trim($_POST['comment'] ?? '') !== '') {
        q('INSERT INTO comments(document_id,user_id,body) VALUES(?,?,?)', [$id, $u['id'], trim($_POST['comment'])]);
        notify_staff_of_doc(['id' => $u['id'], 'course_id' => $u['course_id']], $d['course_id'] ? (int)$d['course_id'] : null, $id, $u['full_name'] . ' replied on "' . $d['title'] . '"');
        flash('Reply sent.'); redirect($self);
    }
}

$info = one('SELECT c.name cat, co.code course, ay.label yr, se.label sem, f.name folder, rv.full_name reviewer
             FROM documents d JOIN categories c ON c.id=d.category_id
             LEFT JOIN courses co ON co.id=d.course_id LEFT JOIN academic_years ay ON ay.id=d.academic_year_id
             LEFT JOIN semesters se ON se.id=d.semester_id LEFT JOIN folders f ON f.id=d.folder_id
             LEFT JOIN users rv ON rv.id=d.reviewed_by WHERE d.id=?', [$id]);
$comments = all('SELECT cm.*, x.full_name, x.role FROM comments cm JOIN users x ON x.id=cm.user_id WHERE cm.document_id=? ORDER BY cm.id', [$id]);
$versions = all('SELECT * FROM document_versions WHERE document_id=? ORDER BY version DESC', [$id]);
$ext = strtolower(pathinfo($d['stored_name'], PATHINFO_EXTENSION));
$myFolders = 'SELECT id, name AS label FROM folders WHERE student_id=' . (int)$d['student_id'] . ' ORDER BY name';
$statusChoices = $u['role'] === 'admin' ? STATUSES : (LECTURER_CAN_APPROVE ? ['Under Review', 'Reviewed', 'Approved', 'Rejected', 'Revisions Required'] : ['Under Review', 'Reviewed', 'Revisions Required']);
$pageTitle = $d['title'];
include __DIR__ . '/inc/header.php';
?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<p><a href="<?= u('documents.php') ?>">&larr; Back to documents</a></p>

<div class="two">
<div class="card">
  <h3>Details</h3>
  <table>
    <tr><th>Status</th><td><?= badge($d['status']) ?></td></tr>
    <?php if ($isStaff): ?><tr><th>Student</th><td><?= e($d['student_name']) ?> (<?= e($d['admission_no']) ?>)</td></tr><?php endif; ?>
    <tr><th>Category</th><td><?= e($info['cat']) ?></td></tr>
    <tr><th>Course</th><td><?= e($info['course'] ?? '-') ?></td></tr>
    <tr><th>Year / Semester</th><td><?= e(($info['yr'] ?? '-') . ' / ' . ($info['sem'] ?? '-')) ?></td></tr>
    <?php if ($info['folder']): ?><tr><th>Folder</th><td><?= e($info['folder']) ?></td></tr><?php endif; ?>
    <tr><th>Description</th><td><?= nl2br(e($d['description'] ?? '')) ?: '-' ?></td></tr>
    <tr><th>File</th><td><?= e($d['original_name']) ?> (<?= fmt_size($d['size']) ?>, version <?= (int)$d['version'] ?>)</td></tr>
    <tr><th>Uploaded</th><td><?= e($d['created_at']) ?></td></tr>
    <tr><th>Last updated</th><td><?= e($d['updated_at']) ?></td></tr>
    <?php if ($info['reviewer']): ?><tr><th>Reviewed by</th><td><?= e($info['reviewer']) ?> on <?= e($d['reviewed_at']) ?></td></tr><?php endif; ?>
  </table>
  <p><a class="btn" href="<?= u('download.php?id=' . $id) ?>">Download</a></p>
</div>

<div class="card">
  <h3>Preview</h3>
  <?php if ($ext === 'pdf'): ?>
    <iframe class="preview" src="<?= u('download.php?id=' . $id . '&inline=1') ?>"></iframe>
  <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)): ?>
    <img class="preview" src="<?= u('download.php?id=' . $id . '&inline=1') ?>" alt="Document preview">
  <?php else: ?>
    <p class="muted">Word documents cannot be previewed in the browser. Please download the file to open it.</p>
  <?php endif; ?>
</div>
</div>

<div class="card">
  <h3>Comments and feedback</h3>
  <?php if (!$comments): ?><p class="muted">No comments yet.</p><?php endif; ?>
  <?php foreach ($comments as $c): ?>
    <div class="comment">
      <b><?= e($c['full_name']) ?></b> <span class="muted">(<?= e($c['role']) ?>, <?= e($c['created_at']) ?>)</span>
      <?php if ($c['status_set']): ?> &rarr; <?= badge($c['status_set']) ?><?php endif; ?>
      <?php if ($c['body'] !== ''): ?><br><?= nl2br(e($c['body'])) ?><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($isStaff): ?>
  <hr>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review">
    <div class="two">
      <div><label>Set status</label><select name="status"><option value="">(comment only)</option>
        <?php foreach ($statusChoices as $st): ?><option <?= $d['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select></div>
    </div>
    <label>Comment / feedback (required for Rejected and Revisions Required)</label>
    <textarea name="comment" rows="3"></textarea>
    <p><button>Save review</button></p>
  </form>
  <?php elseif ($isOwner): ?>
  <hr>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="comment">
    <label>Reply</label><textarea name="comment" rows="2" required></textarea>
    <p><button class="sec">Send reply</button></p>
  </form>
  <?php endif; ?>
</div>

<?php if ($versions): ?>
<div class="card">
  <h3>Version history</h3>
  <table><tr><th>Version</th><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr>
  <?php foreach ($versions as $v): ?>
    <tr><td><?= (int)$v['version'] ?></td><td><?= e($v['original_name']) ?></td><td><?= fmt_size($v['size']) ?></td><td><?= e($v['uploaded_at']) ?></td>
        <td><a href="<?= u('download.php?id=' . $id . '&v=' . (int)$v['version']) ?>">Download</a></td></tr>
  <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($isOwner): ?>
<div class="two">
<div class="card">
  <h3>Edit details</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
    <label>Title</label><input type="text" name="title" required value="<?= e($d['title']) ?>">
    <label>Description</label><textarea name="description" rows="3"><?= e($d['description'] ?? '') ?></textarea>
    <label>Category</label><select name="category_id"><?= opt_list(OPT_CATEGORIES, $d['category_id'], null) ?></select>
    <label>Folder</label><select name="folder_id"><?= opt_list($myFolders, $d['folder_id'], 'No folder') ?></select>
    <label>Course</label><select name="course_id"><?= opt_list(OPT_COURSES, $d['course_id'], 'Not linked to a course') ?></select>
    <label>Academic year</label><select name="academic_year_id"><?= opt_list(OPT_YEARS, $d['academic_year_id'], 'None') ?></select>
    <label>Semester</label><select name="semester_id"><?= opt_list(OPT_SEMESTERS, $d['semester_id'], 'None') ?></select>
    <p><button>Save changes</button></p>
  </form>
</div>
<div class="card">
  <h3>Replace the file</h3>
  <p class="muted">Uploading a new file keeps the old version in the history and sets the status back to Pending.</p>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="replace">
    <input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
    <p><button>Upload new version</button></p>
  </form>
  <h3>Delete</h3>
  <form method="post" data-confirm="Delete this document and all its versions? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button class="bad" <?= $d['status'] === 'Approved' ? 'disabled' : '' ?>>Delete document</button>
    <?php if ($d['status'] === 'Approved'): ?><span class="muted">Approved records can only be removed by an administrator.</span><?php endif; ?>
  </form>
</div>
</div>
<?php elseif ($u['role'] === 'admin'): ?>
<div class="card">
  <h3>Administrator actions</h3>
  <form method="post" data-confirm="Permanently delete this document and all its versions?"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button class="bad">Delete document</button></form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php';
