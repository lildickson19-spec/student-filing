<?php
require __DIR__ . '/inc/bootstrap.php';
redirect(me() ? 'dashboard.php' : 'login.php');
