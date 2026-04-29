<?php
require_once __DIR__ . '/../src/Magic/Bootstrap.php';
require_once __DIR__ . '/../src/Service/AuthService.php';

use Magic\Bootstrap;

$boot = Bootstrap::init();
(new AuthService($boot->pdo()))->logout();

header('Location: /');
exit;
