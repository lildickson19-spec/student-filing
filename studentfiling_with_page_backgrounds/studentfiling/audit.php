<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');
$pageTitle = 'Audit log'; $g = $_GET; $w = ['1=1']; $p = [];
if (trim($g['q'] ?? '') !== '') { $w[] = '(l.details LIKE ? OR x.full_name LIKE ? OR x.email LIKE ?)'; $l = '%' . trim($g['q']) . '%'; array_push($p, $l, $l, $l); }
if (!empty($g['action'])) { $w[] = 'l.action=?'; $p[] = $g['action']; }
if (!empty($g['from'])) { $w[] = 'DATE(l.created_at)>=?'; $p[] = $g['from']; }
if (!empty($g['to']))   { $w[] = 'DATE(l.created_at)<=?'; $p[] = $g['to']; }
$where = implode(' AND ', $w);
$total = (int)val("SELECT COUNT(*) FROM audit_logs l LEFT JOIN users x ON x.id=l.user_id WHERE $where", $p);
[$page, $pages, $off, $per] = paginate($total, 30);
$rows = all("SELECT l.*, x.full_name, x.role FROM audit_logs l LEFT JOIN users x ON x.id=l.user_id WHERE $where ORDER BY l.id DESC LIMIT $per OFFSET $off", $p);
$actions = array_column(all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');
include __DIR__ . '/inc/header.php';
?>
<div class="card"><form method="get" class="filters">
  <div><label>Search</label><input type="text" name="q" value="<?= e($g['q'] ?? '') ?>" placeholder="User or details"></div>
  <div><label>Action</label><select name="action"><option value="">All</option>
    <?php foreach ($actions as $a): ?><option <?= ($g['action'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div><label>From</label><input type="date" name="from" value="<?= e($g['from'] ?? '') ?>"></div>
  <div><label>To</label><input type="date" name="to" value="<?= e($g['to'] ?? '') ?>"></div>
  <div><button>Filter</button> <a class="btn sec" href="<?= u('audit.php') ?>">Clear</a></div></form></div>
<div class="card"><p class="muted"><?= $total ?> entries</p>
  <div class="scroll"><table><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['created_at']) ?></td><td><?= e($r['full_name'] ?? '(none)') ?> <span class="muted"><?= e($r['role'] ?? '') ?></span></td>
      <td><?= e($r['action']) ?></td><td><?= e($r['details']) ?></td><td><?= e($r['ip']) ?></td></tr>
  <?php endforeach; ?></table></div>
  <?= pager($page, $pages) ?></div>
<?php include __DIR__ . '/inc/footer.php';
