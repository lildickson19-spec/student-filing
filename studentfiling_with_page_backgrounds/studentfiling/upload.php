<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('student');
$u = me(); $pageTitle = 'Upload a document'; $err = '';
$myFolders = 'SELECT id, name AS label FROM folders WHERE student_id=' . (int)$u['id'] . ' ORDER BY name';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? ''); $cat = nn($_POST['category_id'] ?? '');
    $folder = nn($_POST['folder_id'] ?? ''); $course = nn($_POST['course_id'] ?? '');
    if ($folder && !val('SELECT 1 FROM folders WHERE id=? AND student_id=?', [$folder, $u['id']])) $folder = null;
    if ($title === '' || !$cat || !val('SELECT 1 FROM categories WHERE id=?', [$cat])) {
        $err = 'Please enter a title and choose a category.';
    } else {
        $f = save_upload($_FILES['file'] ?? [], $e2);
        if (!$f) { $err = $e2; }
        else {
            q('INSERT INTO documents(student_id,folder_id,category_id,course_id,title,description,academic_year_id,semester_id,original_name,stored_name,mime,size)
               VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
              [$u['id'], $folder, $cat, $course, $title, trim($_POST['description'] ?? ''), nn($_POST['academic_year_id'] ?? ''), nn($_POST['semester_id'] ?? ''),
               $f['original'], $f['stored'], $f['mime'], $f['size']]);
            $docId = (int)db()->lastInsertId();
            q('INSERT INTO document_versions(document_id,version,original_name,stored_name,size) VALUES(?,?,?,?,?)', [$docId, 1, $f['original'], $f['stored'], $f['size']]);
            notify_staff_of_doc($u, $course, $docId, 'New document from ' . $u['full_name'] . ': ' . $title);
            audit('upload', "#$docId $title");
            flash('Document uploaded. It is now Pending review.');
            redirect('document.php?id=' . $docId);
        }
    }
}
include __DIR__ . '/inc/header.php';
?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<div class="card">
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
  <label>Title *</label><input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>">
  <div class="two">
    <div><label>Category *</label><select name="category_id" required><?= opt_list(OPT_CATEGORIES, $_POST['category_id'] ?? null, 'Choose a category') ?></select></div>
    <div><label>Folder</label><select name="folder_id"><?= opt_list($myFolders, $_POST['folder_id'] ?? null, 'No folder') ?></select></div>
    <div><label>Course</label><select name="course_id"><?= opt_list(OPT_COURSES, $_POST['course_id'] ?? $u['course_id'], 'Not linked to a course') ?></select></div>
    <div><label>Academic year</label><select name="academic_year_id"><?= opt_list(OPT_YEARS, $_POST['academic_year_id'] ?? null, 'None') ?></select></div>
    <div><label>Semester</label><select name="semester_id"><?= opt_list(OPT_SEMESTERS, $_POST['semester_id'] ?? null, 'None') ?></select></div>
  </div>
  <label>Description</label><textarea name="description" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
  <label>File * (PDF, DOC, DOCX, JPG, JPEG or PNG, up to <?= MAX_UPLOAD_MB ?> MB)</label>
  <input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
  <p><button>Upload</button></p>
</form>
<p class="muted">The upload date is recorded automatically. Need a folder? <a href="<?= u('folders.php') ?>">Create one</a>.</p>
</div>
<?php include __DIR__ . '/inc/footer.php';
