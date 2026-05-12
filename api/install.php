<?php
// ============================================
// INSTALLATION HELPER
// Run this once to set up the database
// Delete after installation!
// ============================================

require_once __DIR__ . '/includes/config.php';

$db = getDB();

if (!$db) {
    die("Database connection failed. Please check your .env file settings:<br>
         DB_HOST, DB_NAME, DB_USER, DB_PASS<br><br>
         Make sure the database '" . (getenv('DB_NAME') ?: 'sms_portal') . "' exists.");
}

// Run schema
$schemaFile = __DIR__ . '/schema.sql';
$schema = file_get_contents($schemaFile);

// Split by semicolons and run each statement
$statements = array_filter(array_map('trim', explode(';', $schema)));
$success = 0; $errors = [];

foreach ($statements as $sql) {
    $sql = trim($sql);
    // Skip empty, comment-only, CREATE DATABASE, and USE statements (not allowed on shared hosting)
    if (empty($sql)) continue;
    if (strpos($sql, '--') === 0) continue;
    if (preg_match('/^CREATE\s+DATABASE/i', $sql)) continue;
    if (preg_match('/^USE\s+/i', $sql)) continue;
    try {
        $db->exec($sql);
        $success++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Install — <?= APP_NAME ?></title>
    <style>
        body { background:#050508; color:#e8e8f2; font-family:monospace; padding:40px; }
        .ok { color:#2ed573; } .err { color:#ff4757; } .info { color:#00f5c4; }
        pre { background:#0b0b12; padding:16px; border-radius:8px; border:1px solid #1c1c2e; overflow-x:auto; }
        a { color:#00f5c4; }
    </style>
</head>
<body>
    <h1 class="info">Installation Complete</h1>
    <p>Executed <strong class="ok"><?= $success ?></strong> statements successfully.</p>

    <?php if (!empty($errors)): ?>
    <h3 class="err">Errors (may be OK if tables already exist):</h3>
    <pre><?php foreach ($errors as $e) echo htmlspecialchars($e) . "\n"; ?></pre>
    <?php endif; ?>

    <h3>Default Admin Credentials:</h3>
    <pre>
Username: <?= ADMIN_USERNAME ?>
Password: <?= ADMIN_PASSWORD ?>
    </pre>

    <h3>Next Steps:</h3>
    <ul>
        <li>Go to <a href="/gateways.php?action=new">Gateways</a> to add your first SMS gateway</li>
        <li>Go to <a href="/keys.php?action=new">API Keys</a> to create your first key</li>
        <li><strong style="color:#ff4757;">DELETE install.php after setup!</strong></li>
    </ul>

    <p><a href="/login.php">→ Go to Login</a></p>
</body>
</html>