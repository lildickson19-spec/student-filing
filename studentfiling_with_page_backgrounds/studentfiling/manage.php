<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');
$types = ['categories' => ['categories', 'Document categories'], 'departments' => ['departments', 'Departments'], 'courses' => ['courses', 'Courses'],
          'years' => ['academic_years', 'Academic years'], 'semesters' => ['semesters', 'Semesters']];
$type = $_GET['type'] ?? 'categories';
if (!isset($types[$type])) $type = 'categories';
[$table, $label] = $types[$type];   // $table comes from the fixed list above
$pageTitle = 'Manage data';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'add') {
            if ($type === 'courses') {
                $code = trim($_POST['code'] ?? ''); $name = trim($_POST['name'] ?? ''); $dep = (int)($_POST['department_id'] ?? 0);
                if ($code === '' || $name === '' || !$dep) { flash('Enter a code, a name and a department.', 'err'); redirect('manage.php?type=' . $type); }
                q('INSERT INTO courses(department_id,code,name) VALUES(?,?,?)', [$dep, $code, $name]);
            } else {
                $col = in_array($type, ['years', 'semesters'], true) ? 'label' : 'name';
                $v = trim($_POST['value'] ?? '');
                if ($v === '') { flash('Enter a value.', 'err'); redirect('manage.php?type=' . $type); }
                q("INSERT INTO $table($col) VALUES(?)", [$v]);
            }
            audit('manage_add', "$type"); flash('Added.');
        } elseif ($act === 'delete') {
            q("DELETE FROM $table WHERE id=?", [(int)($_POST['id'] ?? 0)]);
            audit('manage_delete', "$type #" . (int)$_POST['id']); flash('Deleted.');
        }
    } catch (PDOException $ex) {
        flash($ex->getCode() === '23000' ? 'That entry already exists or is still in use, so it cannot be changed.' : 'Database error.', 'err');
    }
    redirect('manage.php?type=' . $type);
}
$rows = $type === 'courses'
    ? all('SELECT c.id, c.code, c.name, d.name AS dept FROM courses c JOIN departments d ON d.id=c.department_id ORDER BY c.code')
    : all("SELECT * FROM $table ORDER BY id");
include __DIR__ . '/inc/header.php';
?>
<p>
<?php foreach ($types as $k => [$t, $lab]): ?><a class="btn <?= $k === $type ? '' : 'sec' ?> small" href="<?= u('manage.php?type=' . $k) ?>"><?= e($lab) ?></a> <?php endforeach; ?>
</p>
<div class="two">
<div class="card"><h3>Add to: <?= e($label) ?></h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <?php if ($type === 'courses'): ?>
    <label>Course code</label><input type="text" name="code" required>
    <label>Course name</label><input type="text" name="name" required>
    <label>Department</label><select name="department_id" required><?= opt_list(OPT_DEPARTMENTS, null, 'Choose') ?></select>
  <?php else: ?>
    <label><?= in_array($type, ['years', 'semesters'], true) ? 'Label (e.g. 2027/2028)' : 'Name' ?></label><input type="text" name="value" required>
  <?php endif; ?>
  <p><button>Add</button></p></form></div>
<div class="card"><h3><?= e($label) ?></h3>
  <table>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= $type === 'courses' ? e($r['code'] . ' - ' . $r['name']) . ' <span class="muted">(' . e($r['dept']) . ')</span>' : e($r['name'] ?? $r['label']) ?></td>
      <td><form class="inline" method="post" data-confirm="Delete this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="small bad">Delete</button></form></td></tr>
  <?php endforeach; ?></table>
  <p class="muted">Entries that are already in use cannot be deleted.</p></div>
</div>
<?php include __DIR__ . '/inc/footer.php';
