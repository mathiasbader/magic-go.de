<?php
require_once __DIR__ . '/../../src/Service/DbService.php';
require_once __DIR__ . '/../../src/Service/AuthService.php';

$db = new DbService();
$auth = new AuthService($db->getConnection());
$auth->logout();

header('Location: /sal/');
exit;
