<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

$dailyData = []; $statusData = ['success'=>0,'failed'=>0,'pending'=>0];
$topPhones = []; $topOwners = []; $gatewayStats = [];

if ($db) {
    // Last 14 days
    $rows = $db->query("
        SELECT DATE(sent_at) as d, COUNT(*) as cnt, SUM(status='success') as ok, SUM(status='failed') as fail
        FROM sms_logs WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(sent_at) ORDER BY d ASC
    ")->fetchAll();
    foreach ($rows as $r) {
        $dailyData[] = ['date'=>$r['d'],'total'=>$r['cnt'],'success'=>$r['ok'],'failed'=>$r['fail']];
    }

    $sRows = $db->query("SELECT status, COUNT(*) as cnt FROM sms_logs GROUP BY status")->fetchAll();
    foreach ($sRows as $r) $statusData[$r['status']] = $r['cnt'];

    $topPhones = $db->query("SELECT phone_number, COUNT(*) as cnt FROM sms_logs GROUP BY phone_number ORDER BY cnt DESC LIMIT 10")->fetchAll();
    $topOwners = $db->query("SELECT key_owner, COUNT(*) as cnt, SUM(status='success') as ok FROM sms_logs GROUP BY key_owner ORDER BY cnt DESC LIMIT 10")->fetchAll();

    // Gateway stats
    $gatewayStats = $db->query("
        SELECT gateway_used, COUNT(*) as cnt, SUM(status='success') as ok, SUM(status='failed') as fail
        FROM sms_logs WHERE gateway_used IS NOT NULL GROUP BY gateway_used ORDER BY cnt DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistics — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">Statistics</h1>
        <p class="page-sub">Analytics and usage breakdown</p>
    </div>
</div>

<!-- Daily Activity Chart -->
<div class="card">
    <div class="card-head"><h3>Daily SMS Activity (Last 14 Days)</h3></div>
    <canvas id="dailyChart" height="90"></canvas>
</div>

<div class="two-col">
    <!-- Status Donut -->
    <div class="card">
        <div class="card-head"><h3>SMS Status Breakdown</h3></div>
        <canvas id="statusChart" height="200"></canvas>
        <div style="display:flex;gap:20px;justify-content:center;margin-top:16px;">
            <?php foreach ($statusData as $s => $c): ?>
            <div style="text-align:center;">
                <div style="font-size:20px;font-weight:800;"><?= number_format($c) ?></div>
                <div class="mono muted" style="font-size:11px;"><?= strtoupper($s) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gateway Stats -->
    <div class="card">
        <div class="card-head"><h3>Gateway Usage</h3></div>
        <?php if (empty($gatewayStats)): ?>
            <div class="empty-state">No gateway data yet.</div>
        <?php else: foreach ($gatewayStats as $g):
            $rate = $g['cnt'] > 0 ? round($g['ok']/$g['cnt']*100) : 0;
        ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-weight:700;font-size:13px;"><?= htmlspecialchars($g['gateway_used']) ?></span>
                <span class="mono" style="font-size:12px;"><?= $g['cnt'] ?> sends (<?= $rate ?>%)</span>
            </div>
            <div class="pts-bar-wrap">
                <div class="pts-bar"><div class="pts-fill" style="width:<?= $rate ?>%"></div></div>
                <span class="pts-label"><?= $g['ok'] ?> ok</span>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="two-col">
    <!-- Top Owners -->
    <div class="card">
        <div class="card-head"><h3>Top Key Owners</h3></div>
        <?php if (empty($topOwners)): ?>
            <div class="empty-state">No data yet.</div>
        <?php else: foreach ($topOwners as $o):
            $rate = $o['cnt'] > 0 ? round($o['ok']/$o['cnt']*100) : 0;
        ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-weight:700;font-size:13px;"><?= htmlspecialchars($o['key_owner']) ?></span>
                <span class="mono" style="font-size:12px;"><?= $o['cnt'] ?> sends</span>
            </div>
            <div class="pts-bar-wrap">
                <div class="pts-bar"><div class="pts-fill" style="width:<?= $rate ?>%"></div></div>
                <span class="pts-label"><?= $rate ?>%</span>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Top Phone Numbers -->
    <div class="card">
        <div class="card-head"><h3>Most Contacted Numbers</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Phone</th><th>Total Sends</th><th>Bar</th></tr></thead>
                <tbody>
                <?php if (empty($topPhones)): ?>
                    <tr><td colspan="3" class="empty-row">No data</td></tr>
                <?php else:
                    $maxPh = $topPhones[0]['cnt'];
                    foreach ($topPhones as $ph): $pct = round($ph['cnt']/$maxPh*100); ?>
                    <tr>
                        <td class="mono" style="color:var(--accent);"><?= htmlspecialchars($ph['phone_number']) ?></td>
                        <td class="mono"><?= number_format($ph['cnt']) ?></td>
                        <td style="width:50%;"><div class="pts-bar"><div class="pts-fill" style="width:<?= $pct ?>%"></div></div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const dailyData = <?= json_encode($dailyData) ?>;
const statusData = <?= json_encode($statusData) ?>;

// Daily Chart
const labels = dailyData.map(d => d.date);
const totals = dailyData.map(d => d.total);
const successes = dailyData.map(d => d.success);
const failures = dailyData.map(d => d.failed);

new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Success', data: successes, backgroundColor: 'rgba(0,245,196,0.6)', borderRadius: 4 },
            { label: 'Failed', data: failures, backgroundColor: 'rgba(255,71,87,0.6)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#a0a0b8' } } },
        scales: {
            x: { stacked: true, ticks: { color: '#5a5a70' }, grid: { color: '#1c1c2e' } },
            y: { stacked: true, ticks: { color: '#5a5a70' }, grid: { color: '#1c1c2e' } }
        }
    }
});

// Status Donut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Success', 'Failed', 'Pending'],
        datasets: [{
            data: [statusData.success||0, statusData.failed||0, statusData.pending||0],
            backgroundColor: ['rgba(0,245,196,0.7)','rgba(255,71,87,0.7)','rgba(255,165,2,0.7)'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '65%',
        plugins: { legend: { labels: { color: '#a0a0b8' } } }
    }
});
</script>
</body>
</html>
