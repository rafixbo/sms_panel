<?php
require_once __DIR__ . '/includes/config.php';
session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    if ($user === ADMIN_USERNAME && $pass === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        $_SESSION['login_time'] = time();
        logSystem('INFO', 'ADMIN_LOGIN', "Admin logged in | IP: " . getClientIP());
        header('Location: /dashboard.php'); exit;
    } else {
        $error = 'Invalid credentials';
        logSystem('SECURITY', 'FAILED_LOGIN', "User: $user | IP: " . getClientIP());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #050508;
    --surface: #0d0d14;
    --border: #1a1a2e;
    --accent: #00f5c4;
    --accent2: #7b5ea7;
    --text: #e8e8f0;
    --muted: #6b6b80;
    --danger: #ff4757;
    --glow: rgba(0,245,196,0.15);
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    background: var(--bg);
    font-family: 'Syne', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.bg-grid {
    position: fixed; inset: 0; z-index: 0;
    background-image:
        linear-gradient(rgba(0,245,196,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,245,196,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.bg-glow {
    position: fixed;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(123,94,167,0.12) 0%, transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    z-index: 0;
    animation: pulse 4s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:0.6;transform:translate(-50%,-50%) scale(1)} 50%{opacity:1;transform:translate(-50%,-50%) scale(1.1)} }
.login-wrap {
    position: relative; z-index: 10;
    width: 100%; max-width: 420px;
    padding: 20px;
}
.login-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 48px 40px;
    box-shadow: 0 0 60px rgba(0,245,196,0.06), 0 0 120px rgba(123,94,167,0.06);
    animation: fadeUp 0.6s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.logo-area { text-align: center; margin-bottom: 40px; }
.logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--accent2), var(--accent));
    border-radius: 16px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 28px; margin-bottom: 16px;
    box-shadow: 0 0 30px var(--glow);
}
.logo-title {
    font-size: 22px; font-weight: 800;
    color: var(--text);
    letter-spacing: -0.5px;
}
.logo-sub {
    font-family: 'Space Mono', monospace;
    font-size: 11px; color: var(--muted);
    margin-top: 4px;
    text-transform: uppercase; letter-spacing: 2px;
}
.form-group { margin-bottom: 20px; }
label {
    display: block;
    font-size: 11px; font-weight: 700;
    color: var(--muted); text-transform: uppercase;
    letter-spacing: 1.5px; margin-bottom: 8px;
}
input[type=text], input[type=password] {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
    color: var(--text);
    font-family: 'Space Mono', monospace;
    font-size: 14px;
    outline: none;
    transition: all 0.2s;
}
input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--glow);
}
.btn-login {
    width: 100%;
    background: linear-gradient(135deg, var(--accent2), var(--accent));
    border: none; border-radius: 10px;
    padding: 16px;
    color: #000; font-family: 'Syne', sans-serif;
    font-size: 14px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}
.btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 30px var(--glow); }
.error-msg {
    background: rgba(255,71,87,0.1);
    border: 1px solid rgba(255,71,87,0.3);
    border-radius: 8px;
    padding: 12px 16px;
    color: var(--danger);
    font-size: 13px;
    margin-bottom: 20px;
    text-align: center;
}
.footer-note {
    text-align: center;
    margin-top: 24px;
    font-family: 'Space Mono', monospace;
    font-size: 10px; color: var(--muted);
}
.footer-note span { color: var(--accent); }
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="bg-glow"></div>
<div class="login-wrap">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon">📡</div>
            <div class="logo-title"><?= APP_NAME ?></div>
            <div class="logo-sub">Admin Control Panel</div>
        </div>
        <?php if ($error): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="admin" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Access Portal</button>
        </form>
        <div class="footer-note">Owner: <span><?= APP_OWNER ?></span> · v<?= APP_VERSION ?></div>
    </div>
</div>
</body>
</html>
