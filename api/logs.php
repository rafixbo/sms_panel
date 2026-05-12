<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $db) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sms_logs_' . date('Y-m-d') . '.csv"');
    $search  = trim($_GET['q'] ?? '');
    $status  = $_GET['status'] ?? '';
    $owner   = trim($_GET['owner'] ?? '');
    $dateFrom = $_GET['from'] ?? '';
    $dateTo   = $_GET['to'] ?? '';

    $where = '1=1'; $params = [];
    if ($search) { $where .= " AND (phone_number LIKE ? OR ip_address LIKE ? OR api_key LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
    if ($status) { $where .= " AND status=?"; $params[] = $status; }
    if ($owner)  { $where .= " AND key_owner LIKE ?"; $params[] = "%$owner%"; }
    if ($dateFrom) { $where .= " AND DATE(sent_at) >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $where .= " AND DATE(sent_at) <= ?"; $params[] = $dateTo; }

    $stmt = $db->prepare("SELECT * FROM sms_logs WHERE $where ORDER BY sent_at DESC LIMIT 5000");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','Time','Phone','Owner','API Key','IP','Gateway','Proxy','API Code','Points','Status','Error']);
    foreach ($logs as $l) {
        fputcsv($output, [
            $l['id'], $l['sent_at'], $l['phone_number'], $l['key_owner'],
            $l['api_key'], $l['ip_address'], $l['gateway_used'] ?? '-',
            $l['proxy_used'] ?? '-', $l['api_response_code'] ?? $l['response_status'],
            $l['points_deducted'], $l['status'], $l['error_message'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$owner   = trim($_GET['owner'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';

$where = '1=1'; $params = [];
if ($search) { $where .= " AND (phone_number LIKE ? OR ip_address LIKE ? OR api_key LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status) { $where .= " AND status=?"; $params[] = $status; }
if ($owner)  { $where .= " AND key_owner LIKE ?"; $params[] = "%$owner%"; }
if ($dateFrom) { $where .= " AND DATE(sent_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where .= " AND DATE(sent_at) <= ?"; $params[] = $dateTo; }

$logs = []; $total = 0;
if ($db) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM sms_logs WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $stmt = $db->prepare("SELECT * FROM sms_logs WHERE $where ORDER BY sent_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
}
$totalPages = max(1, ceil($total / $limit));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Logs — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">SMS Logs</h1>
        <p class="page-sub"><?= number_format($total) ?> total records</p>
    </div>
    <div class="header-actions">
        <a href="/logs.php?export=csv<?= $search?"&q=$search":'' ?><?= $status?"&status=$status":'' ?>" class="btn btn-secondary">Export CSV</a>
    </div>
</div>

<!-- Filters -->
<div class="card" style="padding:16px 24px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div class="search-bar" style="flex:1;min-width:180px;">
            <span>?</span>
            <input type="text" name="q" placeholder="Phone, IP, key..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div><select name="status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="success" <?= $status==='success'?'selected':'' ?>>Success</option>
            <option value="failed" <?= $status==='failed'?'selected':'' ?>>Failed</option>
            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
        </select></div>
        <div><input type="text" name="owner" class="form-control" placeholder="Owner" value="<?= htmlspecialchars($owner) ?>" style="width:130px;"></div>
        <div><input type="date" name="from" class="form-control" value="<?= $dateFrom ?>" style="width:140px;"></div>
        <div><input type="date" name="to" class="form-control" value="<?= $dateTo ?>" style="width:140px;"></div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="/logs.php" class="btn btn-secondary">Clear</a>
    </form>
</div>

<!-- Logs Table -->
<div class="card fade-in" style="padding:0;">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Time</th><th>Phone</th>
                    <th>Owner</th><th>API Key</th><th>IP Address</th>
                    <th>Gateway</th><th>Proxy</th><th>API Code</th>
                    <th>Pts</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="11" class="empty-row">No logs found for current filter.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td class="mono muted"><?= $l['id'] ?></td>
                    <td class="mono" style="font-size:11px;white-space:nowrap;"><?= date('m-d H:i:s', strtotime($l['sent_at'])) ?></td>
                    <td class="mono" style="color:var(--accent);"><?= htmlspecialchars($l['phone_number']) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($l['key_owner']) ?></td>
                    <td>
                        <span class="mono muted" style="font-size:10px;"><?= substr($l['api_key'],0,16) ?>...</span>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($l['api_key']) ?>',this)">Copy</button>
                    </td>
                    <td class="mono muted" style="font-size:11px;"><?= htmlspecialchars($l['ip_address']) ?></td>
                    <td class="mono" style="font-size:11px;"><?= htmlspecialchars($l['gateway_used'] ?? '-') ?></td>
                    <td class="mono muted" style="font-size:11px;"><?= htmlspecialchars($l['proxy_used'] ?? '-') ?></td>
                    <td class="mono" style="font-size:11px;color:<?= ($l['api_response_code'] ?? 200) >= 400 ? 'var(--danger)' : 'var(--success)' ?>;"><?= $l['api_response_code'] ?? $l['response_status'] ?></td>
                    <td class="mono"><?= $l['points_deducted'] ?></td>
                    <td><span class="badge badge-<?= $l['status'] ?>"><?= $l['status'] ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap;">
    <?php for ($p=1;$p<=$totalPages;$p++):
        $q = http_build_query(array_merge($_GET, ['page'=>$p]));
    ?>
        <a href="?<?= $q ?>" class="btn <?= $p==$page?'btn-primary':'btn-secondary' ?> btn-sm"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</div>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
