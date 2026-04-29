<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Magic\Bootstrap;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Go — planning poker for distributed scrum teams</title>
    <meta name="description" content="Real-time sprint estimation. Spin up a session, share a link, align on story points before standup ends.">
    <link rel="icon" type="image/svg+xml" href="/img/favicon.svg">
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-alt: #162032;
            --accent: #38bdf8;
            --accent-soft: rgba(56, 189, 248, 0.12);
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --border: #475569;
            --red: #f87171;
            --green: #4ade80;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; line-height: 1.55;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ------- nav ------- */
        .nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.25rem 2rem; max-width: 1100px; margin: 0 auto;
        }
        .brand {
            display: flex; align-items: center; gap: 0.6rem;
            font-weight: 700; font-size: 1.1rem; color: var(--text);
        }
        .brand:hover { text-decoration: none; }
        .brand img { width: 28px; height: 28px; }
        .nav-links { display: flex; gap: 1.5rem; align-items: center; font-size: 0.9rem; }
        .nav-links a { color: var(--text-muted); }
        .nav-links a:hover { color: var(--text); text-decoration: none; }
        .nav-cta {
            background: var(--accent); color: var(--bg) !important;
            border-radius: 8px; padding: 0.5rem 1rem; font-weight: 600;
        }
        .nav-cta:hover { opacity: 0.9; text-decoration: none; }

        /* ------- hero ------- */
        .hero {
            max-width: 880px; margin: 4rem auto 5rem; padding: 0 2rem;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.6rem; line-height: 1.15; font-weight: 700;
            margin-bottom: 1.25rem; letter-spacing: -0.02em;
        }
        .hero h1 span { color: var(--accent); }
        .hero p {
            font-size: 1.1rem; color: var(--text-muted); max-width: 620px;
            margin: 0 auto 2rem;
        }
        .hero-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: 0.7rem 1.4rem; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600; cursor: pointer;
            font-family: inherit; border: none; text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: var(--bg); }
        .btn-primary:hover { opacity: 0.9; text-decoration: none; }
        .btn-ghost {
            background: transparent; color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { border-color: var(--accent); text-decoration: none; }

        /* ------- example cards (planning-poker visual) ------- */
        .poker-row {
            display: flex; gap: 0.75rem; justify-content: center; margin-top: 3rem;
            flex-wrap: wrap;
        }
        .poker-card {
            width: 64px; height: 92px; border-radius: 8px;
            background: var(--surface); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 700; color: var(--text);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: transform 0.15s, border-color 0.15s;
        }
        .poker-card:hover { transform: translateY(-4px); border-color: var(--accent); }
        .poker-card.accent { background: var(--accent); color: var(--bg); border-color: var(--accent); }

        /* ------- sections ------- */
        section { padding: 4rem 2rem; }
        .section-inner { max-width: 1000px; margin: 0 auto; }
        .section-eyebrow {
            text-align: center; font-size: 0.75rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 0.75rem;
        }
        .section-title {
            text-align: center; font-size: 1.8rem; font-weight: 700;
            margin-bottom: 3rem;
        }

        .features {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .feature {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 1.5rem;
        }
        .feature-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 9px;
            background: var(--accent-soft); color: var(--accent);
            font-size: 1.1rem; margin-bottom: 0.85rem;
        }
        .feature h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: 0.4rem; }
        .feature p { font-size: 0.9rem; color: var(--text-muted); }

        .steps {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }
        .step { text-align: center; padding: 0 0.75rem; }
        .step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--accent); color: var(--bg);
            font-weight: 700; font-size: 1.05rem; margin-bottom: 0.85rem;
        }
        .step h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: 0.4rem; }
        .step p { font-size: 0.9rem; color: var(--text-muted); }

        /* ------- sign-in card ------- */
        .signin-section { background: var(--surface-alt); }
        .signin-box {
            max-width: 380px; margin: 0 auto;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 2rem;
        }
        .signin-box h2 { font-size: 1.2rem; margin-bottom: 0.4rem; text-align: center; }
        .signin-box .sub {
            text-align: center; font-size: 0.85rem; color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.05em; }
        input[type="email"], input[type="password"] {
            width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.6rem 0.75rem; font-size: 0.95rem; color: var(--text);
            outline: none; font-family: inherit; margin-bottom: 1rem;
        }
        input:focus { border-color: var(--accent); }
        .signin-box button {
            width: 100%; background: var(--accent); color: var(--bg); border: none; border-radius: 8px;
            padding: 0.7rem; font-size: 0.95rem; font-weight: 600; cursor: pointer;
            font-family: inherit;
        }
        .signin-box button:hover { opacity: 0.9; }
        .error {
            background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.4);
            color: var(--red); font-size: 0.85rem; text-align: center;
            padding: 0.5rem; border-radius: 6px; margin-bottom: 1rem;
        }

        /* ------- footer ------- */
        footer {
            text-align: center; padding: 2rem; font-size: 0.8rem;
            color: var(--text-muted); border-top: 1px solid var(--border);
        }
        footer a { color: var(--text-muted); }

        @media (max-width: 600px) {
            .hero h1 { font-size: 2rem; }
            .nav { padding: 1rem; }
            section { padding: 3rem 1rem; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="/" class="brand">
            <img src="/img/favicon.svg" alt="">
            Magic Go
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#how">How it works</a>
            <a href="#signin" class="nav-cta">Sign in</a>
        </div>
    </nav>

    <header class="hero">
        <h1>Estimate sprints in seconds, <span>not arguments</span>.</h1>
        <p>
            Magic Go is a real-time planning poker tool for distributed scrum teams.
            Spin up a session, share a link, and align on story points before standup ends.
        </p>
        <div class="hero-actions">
            <a href="#signin" class="btn btn-primary">Sign in to your team</a>
            <a href="#how" class="btn btn-ghost">See how it works</a>
        </div>
        <div class="poker-row">
            <div class="poker-card">1</div>
            <div class="poker-card">2</div>
            <div class="poker-card">3</div>
            <div class="poker-card accent">5</div>
            <div class="poker-card">8</div>
            <div class="poker-card">13</div>
            <div class="poker-card">21</div>
            <div class="poker-card">?</div>
        </div>
    </header>

    <section id="features">
        <div class="section-inner">
            <div class="section-eyebrow">Features</div>
            <h2 class="section-title">Everything you need to estimate, nothing you don't.</h2>
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">&#9733;</div>
                    <h3>Planning Poker</h3>
                    <p>Fibonacci, T-shirt sizes, or your own custom decks. Switch mid-session, no setup needed.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">&#9889;</div>
                    <h3>Real-time</h3>
                    <p>Everyone votes simultaneously and reveals together. No more waiting for the slowest typer.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">&#128279;</div>
                    <h3>Just a link</h3>
                    <p>No installs, no signups for your team. Share the room link in chat and they're in.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">&#128202;</div>
                    <h3>Sprint history</h3>
                    <p>Velocity tracking and burndown charts across sprints. Plan the next one with real numbers.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">&#128172;</div>
                    <h3>Discussion mode</h3>
                    <p>When estimates diverge, the highest and lowest voters explain. Built into the flow.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">&#128737;</div>
                    <h3>Anonymous voting</h3>
                    <p>Reveal numbers, not names — until the team is ready to discuss. Avoids anchoring bias.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how">
        <div class="section-inner">
            <div class="section-eyebrow">How it works</div>
            <h2 class="section-title">Three steps from refinement to commit.</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <h3>Start a session</h3>
                    <p>Name your sprint, pick your deck, you're done in 10 seconds.</p>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <h3>Invite the team</h3>
                    <p>Drop the room link in Slack or your standup invite. They join with one click.</p>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <h3>Estimate together</h3>
                    <p>Discuss the story, vote, reveal, commit. Move to the next ticket.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="signin" class="signin-section">
        <div class="signin-box">
            <h2>Sign in</h2>
            <div class="sub">Continue to your team's planning workspace.</div>
            <form method="post" action="/#signin">
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="submit">Sign in</button>
            </form>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> Magic Go &middot; planning poker for scrum teams
    </footer>

    <?php if ($error): ?>
        <script>document.getElementById('signin').scrollIntoView({ behavior: 'smooth' });</script>
    <?php endif; ?>
</body>
</html>
