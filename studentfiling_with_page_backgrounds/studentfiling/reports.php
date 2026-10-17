<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('lecturer', 'admin');
$u = me(); $g = $_GET;
[$where, $params] = doc_filter_sql($g);

function csv_safe($v) { // stop spreadsheet formulas typed into titles from running
    return (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}
$export = $g['export'] ?? '';
if ($export === 'csv' || $export === 'xls') {
    $rows = all('SELECT d.id, s.full_name, s.admission_no, d.title, c.name cat, co.code course, ay.label yr, se.label sem, d.status, d.version, d.created_at '
              . DOC_FROM . " WHERE $where ORDER BY d.id DESC LIMIT 5000", $params);
    audit('export', strtoupper($export) . ' ' . count($rows) . ' records');
    $head = ['ID', 'Student', 'Admission no', 'Title', 'Category', 'Course', 'Academic year', 'Semester', 'Status', 'Version', 'Uploaded'];
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="records_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head);
        foreach ($rows as $r) fputcsv($out, array_map('csv_safe', array_values($r)));
        fclose($out);
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="records_' . date('Ymd') . '.xls"');
        echo '<html><head><meta charset="utf-8"></head><body><table border="1"><tr>';
        foreach ($head as $h) echo '<th>' . e($h) . '</th>';
        echo '</tr>';
        foreach ($rows as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . e(csv_safe($v)) . '</td>'; echo '</tr>'; }
        echo '</table></body></html>';
    }
    exit;
}

$byStatus = all('SELECT d.status, COUNT(*) n ' . DOC_FROM . " WHERE $where GROUP BY d.status", $params);
$byCourse = all("SELECT COALESCE(co.code, '-') course, COUNT(*) n " . DOC_FROM . " WHERE $where GROUP BY course ORDER BY n DESC LIMIT 15", $params);
$total = (int)val('SELECT COUNT(*) ' . DOC_FROM . " WHERE $where", $params);
$rows = all('SELECT d.id, d.title, d.status, d.created_at, s.full_name, s.admission_no, c.name cat, co.code course ' . DOC_FROM . " WHERE $where ORDER BY d.id DESC LIMIT 200", $params);
$qs = $g; unset($qs['page'], $qs['export']);
$pageTitle = 'Reports';
include __DIR__ . '/inc/header.php';
?>
<div class="card noprint"><form method="get" class="filters">
  <div><label>Search</label><input type="text" name="q" value="<?= e($g['q'] ?? '') ?>"></div>
  <div><label>Category</label><select name="category_id"><?= opt_list(OPT_CATEGORIES, $g['category_id'] ?? null) ?></select></div>
  <div><label>Status</label><select name="status"><option value="">All</option>
    <?php foreach (STATUSES as $st): ?><option <?= ($g['status'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select></div>
  <div><label>Course</label><select name="course_id"><?= opt_list(OPT_COURSES, $g['course_id'] ?? null) ?></select></div>
  <div><label>Department</label><select name="department_id"><?= opt_list(OPT_DEPARTMENTS, $g['department_id'] ?? null) ?></select></div>
  <div><label>Academic year</label><select name="academic_year_id"><?= opt_list(OPT_YEARS, $g['academic_year_id'] ?? null) ?></select></div>
  <div><label>Semester</label><select name="semester_id"><?= opt_list(OPT_SEMESTERS, $g['semester_id'] ?? null) ?></select></div>
  <div><label>From</label><input type="date" name="from" value="<?= e($g['from'] ?? '') ?>"></div>
  <div><label>To</label><input type="date" name="to" value="<?= e($g['to'] ?? '') ?>"></div>
  <div><button>Generate</button></div></form>
  <p>
    <a class="btn sec small" href="?<?= e(http_build_query($qs + ['export' => 'csv'])) ?>">Export CSV</a>
    <a class="btn sec small" href="?<?= e(http_build_query($qs + ['export' => 'xls'])) ?>">Export Excel</a>
    <button type="button" class="sec small" onclick="window.print()">Print / Save as PDF</button></p></div>

<div class="card">
  <h3>Report: <?= $total ?> record(s)</h3>
  <p class="muted">Generated <?= e(date('Y-m-d H:i')) ?> by <?= e($u['full_name']) ?></p>
  <div class="two">
    <div><b>By status</b><table><?php foreach ($byStatus as $r): ?><tr><td><?= badge($r['status']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table></div>
    <div><b>By course</b><table><?php foreach ($byCourse as $r): ?><tr><td><?= e($r['course']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table></div>
  </div>
</div>
<div class="card"><div class="scroll"><table>
  <tr><th>Student</th><th>Title</th><th>Category</th><th>Course</th><th>Status</th><th>Uploaded</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['full_name']) ?> <span class="muted">(<?= e($r['admission_no']) ?>)</span></td><td><?= e($r['title']) ?></td><td><?= e($r['cat']) ?></td>
      <td><?= e($r['course'] ?? '-') ?></td><td><?= e($r['status']) ?></td><td><?= e($r['created_at']) ?></td></tr>
  <?php endforeach; ?></table></div>
  <?php if ($total > 200): ?><p class="muted">Showing the latest 200. The CSV and Excel exports include up to 5,000 records.</p><?php endif; ?></div>
<?php include __DIR__ . '/inc/footer.php';
