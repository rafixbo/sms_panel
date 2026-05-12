<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();

$db = getDB();

// Stats
$totalKeys = 0; $activeKeys = 0; $totalSMS = 0; $todaySMS = 0;
$recentLogs = []; $topKeys = [];
$totalGateways = 0; $activeProxies = 0;

if ($db) {
    $totalKeys  = $db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
    $activeKeys = $db->query("SELECT COUNT(*) FROM api_keys WHERE status='active'")->fetchColumn();
    $totalSMS   = $db->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn();
    $todaySMS   = $db->query("SELECT COUNT(*) FROM sms_logs WHERE DATE(sent_at)=CURDATE()")->fetchColumn();
    $successSMS = $db->query("SELECT COUNT(*) FROM sms_logs WHERE status='success'")->fetchColumn();
    $failedSMS  = $db->query("SELECT COUNT(*) FROM sms_logs WHERE status='failed'")->fetchColumn();
    $recentLogs = $db->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10")->fetchAll();
    $topKeys    = $db->query("
        SELECT k.key_name, k.owner_username, k.api_key,
               COUNT(l.id) as send_count, k.total_points, k.used_points, k.status
        FROM api_keys k
        LEFT JOIN sms_logs l ON k.id = l.api_key_id
        GROUP BY k.id ORDER BY send_count DESC LIMIT 5
    ")->fetchAll();
    $totalGateways = $db->query("SELECT COUNT(*) FROM gateways WHERE status='active'")->fetchColumn();
    $activeProxies = $db->query("SELECT COUNT(*) FROM proxies WHERE status='active'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
<style>
.dash-hero {
    background: linear-gradient(135deg, #5e35b1 0%, #7c4dff 50%, #2196f3 100%);
    border-radius: 16px;
    padding: 32px 36px;
    margin-bottom: 24px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(94,53,177,0.3);
}
.dash-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.dash-hero::after {
    content: '';
    position: absolute;
    bottom: -60%; right: 20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.dash-hero-content { position: relative; z-index: 1; }
.dash-hero h1 { font-size: 28px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
.dash-hero p { font-size: 14px; opacity: 0.8; font-weight: 400; }
.dash-hero-stats {
    display: flex; gap: 28px;
    position: relative; z-index: 1;
}
.dash-hero-stat { text-align: center; }
.dash-hero-stat .num { font-size: 32px; font-weight: 800; line-height: 1; }
.dash-hero-stat .label { font-size: 11px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

.stat-card-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.dash-quick-actions {
    display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;
}
.dash-action-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text);
    font-size: 13px; font-weight: 600;
    transition: all 0.2s;
    box-shadow: var(--shadow-sm);
}
.dash-action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}
.dash-action-btn .icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
}

@media (max-width: 900px) {
    .dash-hero { flex-direction: column; gap: 20px; text-align: center; }
    .dash-hero-stats { justify-content: center; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="page-body">

        <!-- Hero Section -->
        <div class="dash-hero fade-in">
            <div class="dash-hero-content">
                <h1>Welcome back, Admin</h1>
                <p>Your SMS gateway is running. Here's today's overview.</p>
            </div>
            <div class="dash-hero-stats">
                <div class="dash-hero-stat">
                    <div class="num"><?= number_format($todaySMS) ?></div>
                    <div class="label">SMS Today</div>
                </div>
                <div class="dash-hero-stat">
                    <div class="num"><?= number_format($totalSMS) ?></div>
                    <div class="label">Total Sent</div>
                </div>
                <div class="dash-hero-stat">
                    <div class="num"><?= $totalSMS > 0 ? round(($successSMS ?? 0)/$totalSMS*100) : 0 ?>%</div>
                    <div class="label">Success</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dash-quick-actions fade-in">
            <a href="/keys.php?action=new" class="dash-action-btn">
                <div class="icon" style="background:var(--primary-bg);color:var(--primary);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                Create API Key
            </a>
            <a href="/gateways.php?action=new" class="dash-action-btn">
                <div class="icon" style="background:var(--secondary-bg);color:var(--secondary);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                Add Gateway
            </a>
            <a href="/proxies.php?action=bulk" class="dash-action-btn">
                <div class="icon" style="background:var(--accent-bg);color:var(--accent);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                Bulk Import Proxies
            </a>
            <a href="/logs.php" class="dash-action-btn">
                <div class="icon" style="background:var(--green-bg);color:var(--green);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                View Logs
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card accent-green">
                <div class="stat-icon stat-card-icon" style="background:var(--green-bg);color:var(--green);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?= number_format($totalKeys) ?></div>
                    <div class="stat-label">Total API Keys</div>
                    <div class="stat-sub"><?= $activeKeys ?> active</div>
                </div>
            </div>
            <div class="stat-card accent-purple">
                <div class="stat-icon stat-card-icon" style="background:var(--primary-bg2);color:var(--primary);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?= number_format($totalSMS) ?></div>
                    <div class="stat-label">Total SMS Sent</div>
                    <div class="stat-sub"><?= $todaySMS ?> today</div>
                </div>
            </div>
            <div class="stat-card accent-cyan">
                <div class="stat-icon stat-card-icon" style="background:var(--secondary-bg);color:var(--secondary);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?= $totalSMS > 0 ? round(($successSMS ?? 0)/$totalSMS*100) : 0 ?>%</div>
                    <div class="stat-label">Success Rate</div>
                    <div class="stat-sub"><?= $successSMS ?? 0 ?> successful</div>
                </div>
            </div>
            <div class="stat-card accent-orange">
                <div class="stat-icon stat-card-icon" style="background:var(--orange-bg);color:var(--orange);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?= $totalGateways ?></div>
                    <div class="stat-label">Active Gateways</div>
                    <div class="stat-sub"><?= $activeProxies ?> proxies</div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-col">
            <!-- Recent SMS Log -->
            <div class="card card-wide fade-in">
                <div class="card-head">
                    <h3>Recent SMS Activity</h3>
                    <a href="logs.php" class="btn-link">View All &rarr;</a>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Time</th><th>Phone</th><th>Owner</th><th>Gateway</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="5" class="empty-row">No SMS logs yet</td></tr>
                        <?php else: foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="mono" style="font-size:12px;"><?= date('H:i:s', strtotime($log['sent_at'])) ?></td>
                                <td class="mono" style="font-size:12px;"><?= htmlspecialchars($log['phone_number']) ?></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($log['key_owner']) ?></td>
                                <td class="mono muted" style="font-size:11px;"><?= htmlspecialchars($log['gateway_used'] ?? '-') ?></td>
                                <td><span class="badge badge-<?= $log['status'] ?>"><?= $log['status'] ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top API Keys -->
            <div class="card fade-in">
                <div class="card-head">
                    <h3>Top API Keys</h3>
                    <a href="keys.php" class="btn-link">Manage &rarr;</a>
                </div>
                <?php if (empty($topKeys)): ?>
                    <div class="empty-state">No keys created yet.<br><a href="keys.php?action=new">Create one &rarr;</a></div>
                <?php else: foreach ($topKeys as $k):
                    $pts = $k['total_points'] - $k['used_points'];
                    $pct = $k['total_points'] > 0 ? round($pts/$k['total_points']*100) : 0;
                ?>
                    <div class="key-item">
                        <div class="key-meta">
                            <span class="key-name"><?= htmlspecialchars($k['key_name']) ?></span>
                            <span class="badge badge-<?= $k['status'] ?>"><?= $k['status'] ?></span>
                        </div>
                        <div class="key-owner muted mono"><?= htmlspecialchars($k['owner_username']) ?> &middot; <?= $k['send_count'] ?> sends</div>
                        <div class="pts-bar-wrap">
                            <div class="pts-bar"><div class="pts-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="pts-label"><?= $pts ?> pts</span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- API Usage Example -->
        <div class="card fade-in">
            <div class="card-head"><h3>API Usage Examples</h3></div>
            <div class="api-example">
                <div class="api-url">
                    <span class="method-get">GET</span>
                    <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?>/send.php?key=<span class="hl">YOUR_API_KEY</span>&phone=<span class="hl">01XXXXXXXXX</span>&msg=<span class="hl">Your+message</span></code>
                </div>
                <div class="api-url">
                    <span class="method-post">POST</span>
                    <code>POST /send.php  {"key":"<span class="hl">YOUR_KEY</span>","phone":"<span class="hl">01XXXXXXXXX</span>","msg":"<span class="hl">Your message</span>"}</code>
                </div>
                <div class="api-resp">
<pre>{
  "status": "success",
  "message": "SMS sent successfully",
  "phone": "01XXXXXXXXX",
  "sms_body": "Your message",
  "key_owner": "username",
  "gateway_used": "default",
  "points_used": 1,
  "points_remaining": 99,
  "sent_at": "<?= date('Y-m-d H:i:s') ?>"
}</pre>
                </div>
            </div>
        </div>

    </div>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
