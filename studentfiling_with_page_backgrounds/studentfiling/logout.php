<?php
require __DIR__ . '/inc/bootstrap.php';
if (me()) audit('logout');
$_SESSION = [];
session_destroy();
header('Location: ' . u('login.php'));
exit;
