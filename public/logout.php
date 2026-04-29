<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Magic\Bootstrap;

$boot = Bootstrap::init();
(new AuthService($boot->pdo()))->logout();

header('Location: /');
exit;
