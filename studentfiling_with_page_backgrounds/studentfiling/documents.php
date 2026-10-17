<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$u = me(); $staff = $u['role'] !== 'student';
$pageTitle = $staff ? 'Records' : 'My documents';
$g = $_GET;
[$where, $params] = doc_filter_sql($g);
$total = (int)val('SELECT COUNT(*) ' . DOC_FROM . " WHERE $where", $params);
[$page, $pages, $off, $per] = paginate($total);
$rows = all('SELECT d.id, d.title, d.status, d.version, d.created_at, s.full_name, s.admission_no, c.name cat, co.code course, ay.label yr, se.label sem '
          . DOC_FROM . " WHERE $where ORDER BY d.id DESC LIMIT $per OFFSET $off", $params);
include __DIR__ . '/inc/header.php';
?>
<div class="card">
<form method="get" class="filters">
  <?php if (!empty($g['student_id'])): ?><input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>"><?php endif; ?>
  <div><label>Search</label><input type="text" name="q" value="<?= e($g['q'] ?? '') ?>" placeholder="<?= $staff ? 'Title, student name, admission no.' : 'Title or description' ?>"></div>
  <div><label>Category</label><select name="category_id"><?= opt_list(OPT_CATEGORIES, $g['category_id'] ?? null) ?></select></div>
  <div><label>Status</label><select name="status"><option value="">All</option>
    <?php foreach (STATUSES as $st): ?><option <?= ($g['status'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select></div>
  <div><label>Academic year</label><select name="academic_year_id"><?= opt_list(OPT_YEARS, $g['academic_year_id'] ?? null) ?></select></div>
  <div><label>Semester</label><select name="semester_id"><?= opt_list(OPT_SEMESTERS, $g['semester_id'] ?? null) ?></select></div>
  <?php if ($staff): ?>
    <div><label>Department</label><select name="department_id"><?= opt_list(OPT_DEPARTMENTS, $g['department_id'] ?? null) ?></select></div>
    <div><label>Course</label><select name="course_id"><?= opt_list(OPT_COURSES, $g['course_id'] ?? null) ?></select></div>
    <div><label>From date</label><input type="date" name="from" value="<?= e($g['from'] ?? '') ?>"></div>
    <div><label>To date</label><input type="date" name="to" value="<?= e($g['to'] ?? '') ?>"></div>
  <?php else: ?>
    <div><label>Folder</label><select name="folder_id"><?= opt_list('SELECT id, name AS label FROM folders WHERE student_id=' . (int)$u['id'] . ' ORDER BY name', $g['folder_id'] ?? null) ?></select></div>
  <?php endif; ?>
  <div><button>Filter</button> <a class="btn sec" href="<?= u('documents.php') ?>">Clear</a></div>
</form>
</div>

<div class="card">
  <p class="muted"><?= $total ?> record(s)
    <?php if (!$staff): ?> &middot; <a href="<?= u('upload.php') ?>">Upload new</a> &middot; <a href="<?= u('download_zip.php') ?>">Download all as ZIP</a><?php endif; ?></p>
  <?php if (!$rows): ?><p class="muted">No documents match.</p><?php else: ?>
  <div class="scroll"><table>
    <tr><th>Title</th><?php if ($staff): ?><th>Student</th><?php endif; ?><th>Category</th><th>Course</th><th>Year / Sem</th><th>Status</th><th>Ver.</th><th>Uploaded</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= u('document.php?id=' . $r['id']) ?>"><?= e($r['title']) ?></a></td>
        <?php if ($staff): ?><td><?= e($r['full_name']) ?><br><span class="muted"><?= e($r['admission_no']) ?></span></td><?php endif; ?>
        <td><?= e($r['cat']) ?></td><td><?= e($r['course'] ?? '-') ?></td>
        <td><?= e(($r['yr'] ?? '-') . ' / ' . ($r['sem'] ?? '-')) ?></td>
        <td><?= badge($r['status']) ?></td><td><?= (int)$r['version'] ?></td><td><?= e($r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?= pager($page, $pages) ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/inc/footer.php';
