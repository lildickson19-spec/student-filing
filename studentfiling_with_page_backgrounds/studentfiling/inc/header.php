<?php
$user   = me();
$bare   = $bare ?? false;
$unread = $user ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$user['id']]) : 0;
$nav = [
  'student'  => [['dashboard.php','Dashboard'],['documents.php','My documents'],['upload.php','Upload'],['folders.php','Folders'],['history.php','History'],['announcements.php','Announcements'],['profile.php','Profile']],
  'lecturer' => [['dashboard.php','Dashboard'],['students.php','My students'],['documents.php','Records'],['reports.php','Reports'],['history.php','My reviews'],['announcements.php','Announcements'],['profile.php','Profile']],
  'admin'    => [['dashboard.php','Dashboard'],['users.php','Users'],['assignments.php','Assignments'],['documents.php','Records'],['reports.php','Reports'],['manage.php','Manage data'],['announcements.php','Announcements'],['audit.php','Audit log'],['backup.php','Backup'],['profile.php','Profile']],
];
$here = basename($_SERVER['SCRIPT_NAME']);
$bodyClass = $bare ? (($here === 'login.php') ? 'login-page' : 'auth-page') : (($here === 'dashboard.php') ? 'dashboard-page' : 'navigation-page');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? APP_NAME) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="<?= e(trim(($bare ? 'bare ' : '') . $bodyClass)) ?>">
<?php if ($bare || !$user): ?>
<div class="auth"><div class="auth-card">
<h1><?= e(APP_NAME) ?></h1>
<?php else: ?>
<div class="app">
  <aside class="side" id="side">
    <div class="brand"><?= e(APP_NAME) ?></div>
    <nav>
      <?php foreach ($nav[$user['role']] as [$file, $label]): ?>
        <a href="<?= u($file) ?>" class="<?= $here === $file ? 'on' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="me">
      <strong><?= e($user['full_name']) ?></strong>
      <span class="role"><?= e(ucfirst($user['role'])) ?></span>
      <a href="<?= u('logout.php') ?>">Log out</a>
    </div>
  </aside>
  <div class="main">
    <div class="top">
      <button type="button" id="menuBtn" aria-label="Menu">&#9776;</button>
      <h2><?= e($pageTitle ?? '') ?></h2>
      <a class="bell" href="<?= u('notifications.php') ?>">Notifications<?php if ($unread): ?> <span class="count"><?= $unread ?></span><?php endif; ?></a>
    </div>
    <main class="content">
<?php endif; ?>
<?php foreach ($_SESSION['flash'] ?? [] as [$ft, $fm]): ?>
  <div class="flash <?= e($ft) ?>"><?= e($fm) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?>
