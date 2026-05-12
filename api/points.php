<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

$transactions = [];
if ($db) {
    $transactions = $db->query("
        SELECT pt.*, ak.key_name, ak.owner_username
        FROM points_transactions pt
        JOIN api_keys ak ON pt.api_key_id = ak.id
        ORDER BY pt.created_at DESC LIMIT 200
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Points History — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">Points History</h1>
        <p class="page-sub">All credit/debit transactions</p>
    </div>
</div>

<div class="card fade-in" style="padding:0;">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>Time</th><th>Key Name</th><th>Owner</th><th>Type</th><th>Points</th><th>Balance After</th><th>Description</th></tr>
            </thead>
            <tbody>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="8" class="empty-row">No transactions yet.</td></tr>
            <?php else: foreach ($transactions as $t): ?>
                <tr>
                    <td class="mono muted"><?= $t['id'] ?></td>
                    <td class="mono" style="font-size:11px;"><?= date('m-d H:i', strtotime($t['created_at'])) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($t['key_name']) ?></td>
                    <td><?= htmlspecialchars($t['owner_username']) ?></td>
                    <td>
                        <span class="badge <?= $t['transaction_type']==='credit'?'badge-success':'badge-failed' ?>">
                            <?= $t['transaction_type']==='credit' ? '+' : '-' ?><?= $t['transaction_type'] ?>
                        </span>
                    </td>
                    <td class="mono" style="color:<?= $t['transaction_type']==='credit'?'var(--success)':'var(--danger)' ?>;font-weight:700;">
                        <?= $t['transaction_type']==='credit' ? '+' : '-' ?><?= $t['points'] ?>
                    </td>
                    <td class="mono"><?= $t['balance_after'] ?></td>
                    <td class="muted" style="font-size:12px;"><?= htmlspecialchars($t['description']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>