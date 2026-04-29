<?php
require_once __DIR__ . '/../../src/Service/DbService.php';
require_once __DIR__ . '/../../src/Service/AuthService.php';

ob_start();
$db = new DbService();
$db->runMigrations();
$auth = new AuthService($db->getConnection());
ob_end_clean();

$user = $auth->getAuthenticatedUser();

// Already logged in — straight to the collection.
if ($user) {
    header('Location: /cards/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($auth->login($email, $password)) {
        header('Location: /cards/');
        exit;
    }
    $error = 'Ungültige E-Mail oder Passwort.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Magic Go</title>
    <link rel="icon" href="/img/favicon.png">
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --accent: #38bdf8;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --border: #475569;
            --red: #f87171;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            background: var(--surface); border-radius: 12px; padding: 2rem;
            width: 340px; max-width: 90vw; border: 1px solid var(--border);
        }
        h1 { font-size: 1.3rem; margin-bottom: 1.5rem; text-align: center; }
        label { display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em; }
        input[type="email"], input[type="password"] {
            width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.6rem 0.75rem; font-size: 0.95rem; color: var(--text);
            outline: none; font-family: inherit; margin-bottom: 1rem;
        }
        input:focus { border-color: var(--accent); }
        button {
            width: 100%; background: var(--accent); color: var(--bg); border: none; border-radius: 8px;
            padding: 0.65rem; font-size: 0.95rem; font-weight: 600; cursor: pointer;
            font-family: inherit;
        }
        button:hover { opacity: 0.9; }
        .error { color: var(--red); font-size: 0.85rem; text-align: center; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <form class="login-box" method="post">
        <h1>Magic Go</h1>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required autofocus>
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Anmelden</button>
    </form>
</body>
</html>
