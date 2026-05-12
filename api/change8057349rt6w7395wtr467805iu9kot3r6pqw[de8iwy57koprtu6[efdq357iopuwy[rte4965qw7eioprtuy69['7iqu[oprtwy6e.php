<?php
// ============================================
// ADMIN PASSWORD CHANGER (Standalone)
// Upload this file, use it, then DELETE it!
// ============================================

require_once __DIR__ . '/includes/config.php';

// Check if form submitted
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $msg = 'All fields are required.';
        $msgType = 'error';
    } elseif ($currentPass !== ADMIN_PASSWORD) {
        $msg = 'Current password is incorrect.';
        $msgType = 'error';
    } elseif (strlen($newPass) < 6) {
        $msg = 'New password must be at least 6 characters.';
        $msgType = 'error';
    } elseif ($newPass !== $confirmPass) {
        $msg = 'New password and confirmation do not match.';
        $msgType = 'error';
    } else {
        // Update .env file
        $envPath = __DIR__ . '/.env';
        if (!file_exists($envPath)) {
            $msg = '.env file not found!';
            $msgType = 'error';
        } else {
            $envContent = file_get_contents($envPath);

            // Replace ADMIN_PASSWORD line
            $envContent = preg_replace(
                '/^ADMIN_PASSWORD=.*/m',
                'ADMIN_PASSWORD=' . $newPass,
                $envContent
            );

            // Also update ADMIN_SESSION_SECRET for security
            $envContent = preg_replace(
                '/^ADMIN_SESSION_SECRET=.*/m',
                'ADMIN_SESSION_SECRET=' . bin2hex(random_bytes(16)),
                $envContent
            );

            file_put_contents($envPath, $envContent);
            $msg = 'Password changed successfully! DELETE this file now.';
            $msgType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Admin Password</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #050508;
            color: #e8e8f2;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: #0b0b14;
            border: 1px solid #1c1c2e;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        h1 {
            color: #00f5c4;
            font-size: 20px;
            margin-bottom: 8px;
        }
        .sub {
            color: #666;
            font-size: 12px;
            margin-bottom: 28px;
        }
        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 12px;
            color: #999;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .field input {
            width: 100%;
            padding: 12px 14px;
            background: #0f0f1a;
            border: 1px solid #2a2a3e;
            border-radius: 10px;
            color: #e8e8f2;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .field input:focus {
            border-color: #00f5c4;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #00f5c4, #00c9a7);
            border: none;
            border-radius: 10px;
            color: #000;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .msg {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .msg.success { background: rgba(46,213,115,0.1); border: 1px solid rgba(46,213,115,0.3); color: #2ed573; }
        .msg.error { background: rgba(255,71,87,0.1); border: 1px solid rgba(255,71,87,0.3); color: #ff4757; }
        .warn {
            margin-top: 24px;
            padding: 12px;
            background: rgba(255,165,0,0.08);
            border: 1px solid rgba(255,165,0,0.2);
            border-radius: 8px;
            font-size: 11px;
            color: #ffa500;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Change Admin Password</h1>
        <p class="sub">Update the admin login password in .env</p>

        <?php if ($msg): ?>
            <div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Current Password</label>
                <input type="password" name="current_password" required autocomplete="off">
            </div>
            <div class="field">
                <label>New Password</label>
                <input type="password" name="new_password" required autocomplete="off" minlength="6">
            </div>
            <div class="field">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required autocomplete="off" minlength="6">
            </div>
            <button type="submit" class="btn">Change Password</button>
        </form>

        <div class="warn">DELETE this file after changing your password!</div>
    </div>
</body>
</html>
