<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

// Also read file logs
$fileLogs = [];
$logDir = LOG_DIR;
$today = date('Y-m-d');
$smsLogFile = "$logDir/sms_$today.log";
$sysLogFile = "$logDir/system_$today.log";

$smsLines = file_exists($smsLogFile) ? array_reverse(file($smsLogFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)) : [];
$sysLines = file_exists($sysLogFile) ? array_reverse(file($sysLogFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)) : [];

// DB system logs
$dbLogs = [];
if ($db) {
    $dbLogs = $db->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Logs — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
<style>
.log-terminal {
    background: #060609;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    line-height: 1.8;
    max-height: 400px;
    overflow-y: auto;
    color: var(--text2);
}
.log-line-sms { color: #00f5c4; }
.log-line-sys { color: #a0a0b8; }
.log-line-sec { color: #ff4757; }
.log-line-warn { color: #ffa502; }
.tab-btn {
    padding: 8px 20px; border-radius: 8px;
    font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
    border: 1px solid var(--border); background: var(--surface2);
    color: var(--muted); cursor: pointer; transition: all 0.15s;
}
.tab-btn.active { background: var(--border); color: var(--accent); border-color: rgba(0,245,196,0.2); }
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">System Logs</h1>
        <p class="page-sub">File-based and database logs for <?= $today ?></p>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
    <button class="tab-btn active" onclick="showTab('sms',this)">📨 SMS Logs</button>
    <button class="tab-btn" onclick="showTab('sys',this)">🔍 System Logs</button>
    <button class="tab-btn" onclick="showTab('db',this)">💾 DB Events</button>
</div>

<!-- SMS File Logs -->
<div id="tab-sms" class="card fade-in">
    <div class="card-head">
        <h3>Today's SMS File Log</h3>
        <span class="mono muted" style="font-size:11px;"><?= count($smsLines) ?> entries</span>
    </div>
    <div class="log-terminal" id="smsTerminal">
        <?php if (empty($smsLines)): ?>
            <span class="muted">No SMS logs for today yet.</span>
        <?php else: foreach (array_slice($smsLines,0,200) as $line):
            $cls = 'log-line-sms';
            if (strpos($line,'failed')!==false||strpos($line,'error')!==false) $cls='log-line-sec';
        ?>
            <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- System File Logs -->
<div id="tab-sys" class="card fade-in" style="display:none;">
    <div class="card-head">
        <h3>System File Log</h3>
        <span class="mono muted" style="font-size:11px;"><?= count($sysLines) ?> entries</span>
    </div>
    <div class="log-terminal">
        <?php if (empty($sysLines)): ?>
            <span class="muted">No system logs for today yet.</span>
        <?php else: foreach (array_slice($sysLines,0,200) as $line):
            $cls = 'log-line-sys';
            if (strpos($line,'SECURITY')!==false||strpos($line,'ERROR')!==false) $cls='log-line-sec';
            elseif (strpos($line,'WARNING')!==false) $cls='log-line-warn';
        ?>
            <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- DB Logs -->
<div id="tab-db" class="card fade-in" style="display:none;padding:0;">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Time</th><th>Type</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (empty($dbLogs)): ?>
                <tr><td colspan="6" class="empty-row">No DB system logs. (DB may not be connected)</td></tr>
            <?php else: foreach ($dbLogs as $l): ?>
                <tr>
                    <td class="mono muted"><?= $l['id'] ?></td>
                    <td class="mono" style="font-size:11px;"><?= date('H:i:s',strtotime($l['created_at'])) ?></td>
                    <td><span class="badge badge-<?= strtolower($l['log_type'])==='error'?'failed':(strtolower($l['log_type'])==='security'?'suspended':'active') ?>"><?= $l['log_type'] ?></span></td>
                    <td class="mono" style="font-size:12px;"><?= htmlspecialchars($l['action']) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($l['description']) ?></td>
                    <td class="mono muted" style="font-size:11px;"><?= htmlspecialchars($l['ip_address']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function showTab(name, btn) {
    document.querySelectorAll('[id^=tab-]').forEach(el => el.style.display='none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-'+name).style.display='block';
    btn.classList.add('active');
}
</script>
</body>
</html>