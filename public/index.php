<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\View;

$boot = Bootstrap::init();

// Already logged in — straight to the collection.
if ($boot->user()) {
    header('Location: /cards/');
    exit;
}

$auth = new AuthService($boot->pdo());
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($auth->login($email, $password)) {
        header('Location: /cards/');
        exit;
    }
    $error = 'Invalid email or password.';
}

View::display('landing.html.twig', ['error' => $error]);
