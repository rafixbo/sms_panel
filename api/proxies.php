<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';
$action = $_GET['action'] ?? 'list';

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($postAction === 'create') {
        $proxyUrl       = trim($_POST['proxy_url'] ?? '');
        $proxyType      = $_POST['proxy_type'] ?? 'http';
        $maxRequests    = (int)($_POST['max_requests'] ?? 1);
        $maxFails       = (int)($_POST['max_fails'] ?? 3);
        $cooldownMinutes= (int)($_POST['cooldown_minutes'] ?? 0);

        if (!$proxyUrl) {
            $msg = 'Proxy URL is required.'; $msgType = 'danger';
        } else {
            if ($db) {
                $db->prepare("
                    INSERT INTO proxies (proxy_url, proxy_type, max_requests, max_fails, cooldown_minutes, status)
                    VALUES (?,?,?,?,?,'active')
                ")->execute([$proxyUrl, $proxyType, $maxRequests, $maxFails, $cooldownMinutes]);
                logSystem('INFO','PROXY_ADDED',"Proxy: $proxyUrl | Type: $proxyType");
                $msg = 'Proxy added successfully!'; $msgType = 'success';
                $action = 'list';
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>$msgType!=='danger', 'message'=>$msg ?: 'Done']);
            exit;
        }
    }

    if ($postAction === 'bulk_add') {
        $proxyUrls = trim($_POST['proxy_urls'] ?? '');
        $proxyType = $_POST['proxy_type'] ?? 'http';
        $maxRequests = (int)($_POST['max_requests'] ?? 1);
        $maxFails = (int)($_POST['max_fails'] ?? 3);
        $cooldownMinutes = (int)($_POST['cooldown_minutes'] ?? 0);
        $checkLive = (int)($_POST['check_live'] ?? 0);

        $lines = array_filter(array_map('trim', explode("\n", $proxyUrls)));
        $added = 0; $skipped = 0; $results = [];

        if ($db && !empty($lines)) {
            $stmt = $db->prepare("INSERT INTO proxies (proxy_url, proxy_type, max_requests, max_fails, cooldown_minutes, status) VALUES (?,?,?,?,?,'active')");
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Basic format validation
                if (!preg_match('/^(https?|socks[45]):\/\//i', $line)) {
                    $line = 'http://' . $line;
                }

                // Check if proxy is live (if requested)
                if ($checkLive) {
                    $validation = validateProxy($line, 5);
                    if (!$validation['alive']) {
                        $skipped++;
                        $results[] = ['url' => $line, 'status' => 'dead', 'error' => $validation['error']];
                        continue;
                    }
                }

                try {
                    $stmt->execute([$line, $proxyType, $maxRequests, $maxFails, $cooldownMinutes]);
                    $added++;
                    $results[] = ['url' => $line, 'status' => 'added'];
                } catch (Exception $e) {
                    $skipped++;
                    $results[] = ['url' => $line, 'status' => 'error', 'error' => $e->getMessage()];
                }
            }
            logSystem('INFO','PROXY_BULK_ADD',"Added $added proxies, skipped $skipped");
            $msg = "$added proxies added" . ($skipped > 0 ? ", $skipped skipped (dead/duplicate)" : '') . '.';
            $msgType = $added > 0 ? 'success' : 'warning';
        } else {
            $msg = 'No valid proxy URLs provided.'; $msgType = 'danger';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>$added>0, 'message'=>$msg, 'added'=>$added, 'skipped'=>$skipped, 'results'=>$results]);
            exit;
        }
    }

    if ($postAction === 'validate_proxy') {
        $proxyUrl = trim($_POST['proxy_url'] ?? '');
        if (empty($proxyUrl)) {
            header('Content-Type: application/json');
            echo json_encode(['alive'=>false, 'error'=>'No URL provided']);
            exit;
        }
        $result = validateProxy($proxyUrl, 8);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($postAction === 'update') {
        $id             = (int)$_POST['id'];
        $proxyUrl       = trim($_POST['proxy_url'] ?? '');
        $proxyType      = $_POST['proxy_type'] ?? 'http';
        $maxRequests    = (int)($_POST['max_requests'] ?? 1);
        $maxFails       = (int)($_POST['max_fails'] ?? 3);
        $cooldownMinutes= (int)($_POST['cooldown_minutes'] ?? 0);
        $status         = $_POST['status'] ?? 'active';

        if ($db) {
            $db->prepare("UPDATE proxies SET proxy_url=?,proxy_type=?,max_requests=?,max_fails=?,cooldown_minutes=?,status=? WHERE id=?")
               ->execute([$proxyUrl, $proxyType, $maxRequests, $maxFails, $cooldownMinutes, $status, $id]);
            $msg = 'Proxy updated.'; $msgType = 'success';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'delete') {
        $id = (int)$_POST['id'];
        if ($db) {
            $db->prepare("DELETE FROM proxies WHERE id=?")->execute([$id]);
            $msg = 'Proxy deleted.'; $msgType = 'success';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'toggle') {
        $id = (int)$_POST['id'];
        $cur = $_POST['current_status'];
        $new = $cur === 'active' ? 'disabled' : 'active';
        if ($db) $db->prepare("UPDATE proxies SET status=? WHERE id=?")->execute([$new, $id]);
        $msg = "Proxy $new."; $msgType = 'success';
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'reset_all') {
        if ($db) {
            $db->exec("UPDATE proxies SET used_count=0, fail_count=0, status='active', cooldown_until=NULL");
            $msg = 'All proxies reset.'; $msgType = 'success';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'validate_all') {
        if ($db) {
            $proxies = $db->query("SELECT id, proxy_url FROM proxies")->fetchAll();
            $alive = 0; $dead = 0;
            foreach ($proxies as $p) {
                $result = validateProxy($p['proxy_url'], 5);
                if ($result['alive']) {
                    $alive++;
                    $db->prepare("UPDATE proxies SET status='active' WHERE id=?")->execute([$p['id']]);
                } else {
                    $dead++;
                    $db->prepare("UPDATE proxies SET status='disabled' WHERE id=?")->execute([$p['id']]);
                }
            }
            $msg = "Validation done: $alive alive, $dead dead."; $msgType = 'success';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }
}

// Edit data
$editProxy = null;
if ($action === 'edit' && isset($_GET['id']) && $db) {
    $editProxy = $db->prepare("SELECT * FROM proxies WHERE id=?");
    $editProxy->execute([(int)$_GET['id']]);
    $editProxy = $editProxy->fetch();
}

// List
$proxies = [];
if ($db) {
    $proxies = $db->query("SELECT * FROM proxies ORDER BY status ASC, id ASC")->fetchAll();
}

$proxyStats = ['active'=>0,'cooldown'=>0,'disabled'=>0];
if ($db) {
    $rows = $db->query("SELECT status, COUNT(*) as cnt FROM proxies GROUP BY status")->fetchAll();
    foreach ($rows as $r) $proxyStats[$r['status']] = $r['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proxies — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">Proxies</h1>
        <p class="page-sub">Manage proxy rotation for gateway requests</p>
    </div>
    <div class="header-actions">
        <a href="/proxies.php?action=new" class="btn btn-primary">+ Add Proxy</a>
        <a href="/proxies.php?action=bulk" class="btn btn-secondary">Bulk Import</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Proxy Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
    <div class="stat-card accent-green">
        <div class="stat-icon">Active</div>
        <div class="stat-info">
            <div class="stat-val"><?= $proxyStats['active'] ?></div>
            <div class="stat-label">Active Proxies</div>
        </div>
    </div>
    <div class="stat-card accent-orange">
        <div class="stat-icon">Cooldown</div>
        <div class="stat-info">
            <div class="stat-val"><?= $proxyStats['cooldown'] ?></div>
            <div class="stat-label">In Cooldown</div>
        </div>
    </div>
    <div class="stat-card accent-cyan">
        <div class="stat-icon">Disabled</div>
        <div class="stat-info">
            <div class="stat-val"><?= $proxyStats['disabled'] ?></div>
            <div class="stat-label">Disabled</div>
        </div>
    </div>
</div>

<?php if ($action === 'new' || $action === 'edit'): ?>
<!-- ======= CREATE / EDIT PROXY FORM ======= -->
<div class="card fade-in">
    <div class="card-head">
        <h3><?= $editProxy ? 'Edit Proxy' : 'Add New Proxy' ?></h3>
        <a href="/proxies.php" class="btn btn-secondary btn-sm">Back</a>
    </div>
    <form method="POST" onsubmit="return submitProxyForm(event)">
        <input type="hidden" name="action" value="<?= $editProxy ? 'update' : 'create' ?>">
        <?php if ($editProxy): ?><input type="hidden" name="id" value="<?= $editProxy['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Proxy URL *</label>
                <input type="text" name="proxy_url" id="proxyUrlInput" class="form-control mono" placeholder="http://user:pass@ip:port or socks5://ip:port" value="<?= htmlspecialchars($editProxy['proxy_url'] ?? '') ?>" required>
                <div id="proxyCheckResult" style="margin-top:8px;display:none;"></div>
            </div>
            <div class="form-group">
                <label>Proxy Type</label>
                <select name="proxy_type" class="form-control">
                    <option value="http" <?= ($editProxy['proxy_type'] ?? 'http') === 'http' ? 'selected' : '' ?>>HTTP</option>
                    <option value="https" <?= ($editProxy['proxy_type'] ?? '') === 'https' ? 'selected' : '' ?>>HTTPS</option>
                    <option value="socks4" <?= ($editProxy['proxy_type'] ?? '') === 'socks4' ? 'selected' : '' ?>>SOCKS4</option>
                    <option value="socks5" <?= ($editProxy['proxy_type'] ?? '') === 'socks5' ? 'selected' : '' ?>>SOCKS5</option>
                </select>
            </div>
            <div class="form-group">
                <label>Max Requests per Cycle</label>
                <input type="number" name="max_requests" class="form-control" value="<?= $editProxy['max_requests'] ?? 1 ?>" min="0">
                <div style="font-size:11px;color:var(--muted);margin-top:4px;">0 = unlimited until failure. 1 = rotate every request.</div>
            </div>
            <div class="form-group">
                <label>Max Consecutive Fails</label>
                <input type="number" name="max_fails" class="form-control" value="<?= $editProxy['max_fails'] ?? 3 ?>" min="1">
            </div>
            <div class="form-group">
                <label>Cooldown Minutes</label>
                <input type="number" name="cooldown_minutes" class="form-control" value="<?= $editProxy['cooldown_minutes'] ?? 0 ?>" min="0">
                <div style="font-size:11px;color:var(--muted);margin-top:4px;">0 = immediate reuse with counter reset.</div>
            </div>
            <?php if ($editProxy): ?>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $editProxy['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="cooldown" <?= $editProxy['status']==='cooldown'?'selected':'' ?>>Cooldown</option>
                    <option value="disabled" <?= $editProxy['status']==='disabled'?'selected':'' ?>>Disabled</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="submit" class="btn btn-primary" id="proxySubmitBtn"><?= $editProxy ? 'Save Changes' : 'Add Proxy' ?></button>
            <?php if (!$editProxy): ?>
            <button type="button" class="btn btn-secondary" onclick="checkProxyLive()">Check if Live</button>
            <?php endif; ?>
            <a href="/proxies.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($action === 'bulk'): ?>
<!-- ======= BULK ADD ======= -->
<div class="card fade-in">
    <div class="card-head">
        <h3>Bulk Import Proxies</h3>
        <a href="/proxies.php" class="btn btn-secondary btn-sm">Back</a>
    </div>
    <div class="form-group">
        <label>Proxy URLs (one per line)</label>
        <textarea name="proxy_urls" id="bulkProxyInput" class="form-control mono" style="min-height:200px;font-size:12px;" placeholder="http://user:pass@ip1:port
http://ip2:port
socks5://user:pass@ip3:port
https://ip4:port" required></textarea>
        <div style="font-size:11px;color:var(--muted);margin-top:4px;">Supported: http://, https://, socks4://, socks5:// — No protocol? http:// is assumed.</div>
    </div>

    <div style="margin-bottom:16px;padding:16px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;">
        <label class="checkbox-label" style="margin-bottom:12px;">
            <input type="checkbox" name="check_live" id="checkLiveToggle" value="1" checked>
            <span>Check if proxies are LIVE before importing</span>
        </label>
        <div id="checkLiveHint" style="font-size:11px;color:var(--accent);margin-left:26px;">
            Each proxy will be tested. Only working proxies will be imported.
        </div>
    </div>

    <div class="form-grid" style="max-width:600px;">
        <div class="form-group">
            <label>Proxy Type</label>
            <select name="proxy_type" class="form-control">
                <option value="http">HTTP</option>
                <option value="https">HTTPS</option>
                <option value="socks4">SOCKS4</option>
                <option value="socks5">SOCKS5</option>
            </select>
        </div>
        <div class="form-group">
            <label>Max Requests per Cycle</label>
            <input type="number" name="max_requests" class="form-control" value="1" min="0">
        </div>
        <div class="form-group">
            <label>Max Consecutive Fails</label>
            <input type="number" name="max_fails" class="form-control" value="3" min="1">
        </div>
        <div class="form-group">
            <label>Cooldown Minutes</label>
            <input type="number" name="cooldown_minutes" class="form-control" value="0" min="0">
        </div>
    </div>

    <!-- Live Check Progress & Results -->
    <div id="bulkCheckArea" style="display:none;margin-bottom:16px;">
        <div class="card" style="padding:16px;background:#060609;border:1px solid var(--border);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <h4 style="font-size:13px;color:var(--accent);font-weight:700;">Checking Proxies</h4>
                <span id="bulkCheckCounter" style="font-family:'Space Mono',monospace;font-size:12px;color:var(--muted);">0/0</span>
            </div>
            <div class="pts-bar" style="height:6px;margin-bottom:16px;">
                <div class="pts-fill" id="bulkCheckBar" style="width:0%;transition:width 0.3s;"></div>
            </div>
            <div style="display:flex;gap:20px;margin-bottom:12px;">
                <div><span style="color:var(--success);font-weight:700;font-size:16px;" id="liveCount">0</span> <span style="font-size:11px;color:var(--muted);">LIVE</span></div>
                <div><span style="color:var(--danger);font-weight:700;font-size:16px;" id="deadCount">0</span> <span style="font-size:11px;color:var(--muted);">DEAD</span></div>
            </div>
            <div id="bulkResultsContent" style="font-family:'Space Mono',monospace;font-size:11px;line-height:1.8;max-height:250px;overflow-y:auto;"></div>
        </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="button" class="btn btn-primary" id="bulkProxyBtn" onclick="startBulkImport()">Import Proxies</button>
        <a href="/proxies.php" class="btn btn-secondary">Cancel</a>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- ======= ACTIONS BAR ======= -->
<div class="card" style="padding:12px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span class="muted mono" style="font-size:11px;"><?= count($proxies) ?> proxies total</span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn btn-secondary btn-sm" onclick="ajaxValidateAllProxies()">Validate All (Check Live)</button>
        <button class="btn btn-secondary btn-sm" onclick="ajaxResetAllProxies()">Reset All</button>
    </div>
</div>

<!-- ======= PROXIES TABLE ======= -->
<div class="card fade-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Proxy URL</th><th>Type</th><th>Max Req</th>
                    <th>Used (Cycle)</th><th>Total</th><th>Fails</th>
                    <th>Cooldown</th><th>Status</th><th>Last Used</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($proxies)): ?>
                <tr><td colspan="11" class="empty-row">No proxies configured. <a href="/proxies.php?action=new">Add one</a> or <a href="/proxies.php?action=bulk">Bulk Import</a></td></tr>
            <?php else: foreach ($proxies as $p): ?>
                <tr>
                    <td class="mono muted"><?= $p['id'] ?></td>
                    <td class="mono" style="font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['proxy_url']) ?>"><?= htmlspecialchars(mb_substr($p['proxy_url'],0,40)) ?></td>
                    <td><span class="badge badge-active"><?= strtoupper($p['proxy_type']) ?></span></td>
                    <td class="mono"><?= $p['max_requests'] ?: 'inf' ?></td>
                    <td class="mono"><?= $p['used_count'] ?>/<?= $p['max_requests'] ?: 'inf' ?></td>
                    <td class="mono"><?= number_format($p['total_used']) ?></td>
                    <td class="mono" style="color:<?= $p['fail_count']>0?'var(--danger)':'var(--text2)' ?>;"><?= $p['fail_count'] ?>/<?= $p['max_fails'] ?></td>
                    <td class="mono muted" style="font-size:11px;"><?= $p['cooldown_until'] ? date('H:i:s', strtotime($p['cooldown_until'])) : '-' ?></td>
                    <td><span class="badge badge-<?= $p['status']==='active'?'active':($p['status']==='cooldown'?'pending':'suspended') ?>"><?= $p['status'] ?></span></td>
                    <td class="mono muted" style="font-size:11px;"><?= $p['last_used_at'] ? date('m-d H:i', strtotime($p['last_used_at'])) : '-' ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="/proxies.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <button class="btn btn-secondary btn-sm" onclick="ajaxToggleProxy(<?= $p['id'] ?>,'<?= $p['status'] ?>')">Toggle</button>
                            <button class="btn btn-danger btn-sm" onclick="ajaxDeleteProxy(<?= $p['id'] ?>)">Del</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function submitProxyForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('proxySubmitBtn');
    const orig = btn.textContent;
    btn.textContent = 'Saving...'; btn.disabled = true;
    const formData = new FormData(form);
    fetch('/proxies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        btn.textContent = orig; btn.disabled = false;
        showToast(data.message || 'Done', data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.href = '/proxies.php', 800);
    }).catch(() => { btn.textContent = orig; btn.disabled = false; showToast('Failed', 'error'); });
    return false;
}

// ===== BULK IMPORT WITH LIVE CHECKING =====
async function startBulkImport() {
    const btn = document.getElementById('bulkProxyBtn');
    const textarea = document.getElementById('bulkProxyInput');
    const checkLive = document.getElementById('checkLiveToggle').checked;
    const lines = textarea.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);

    if (lines.length === 0) { showToast('Enter at least one proxy URL', 'error'); return; }

    // Normalize URLs
    const urls = lines.map(line => {
        if (!/^(https?|socks[45]):\/\//i.test(line)) line = 'http://' + line;
        return line;
    });

    btn.disabled = true;
    btn.textContent = checkLive ? 'Checking & Importing...' : 'Importing...';

    const liveUrls = [];
    const deadUrls = [];

    if (checkLive) {
        // Show progress area
        document.getElementById('bulkCheckArea').style.display = 'block';
        document.getElementById('liveCount').textContent = '0';
        document.getElementById('deadCount').textContent = '0';
        document.getElementById('bulkCheckCounter').textContent = '0/' + urls.length;
        document.getElementById('bulkCheckBar').style.width = '0%';
        document.getElementById('bulkResultsContent').innerHTML = '';
        const resultsDiv = document.getElementById('bulkResultsContent');

        // Check each proxy one by one
        for (let i = 0; i < urls.length; i++) {
            const url = urls[i];
            const pct = Math.round(((i + 1) / urls.length) * 100);
            document.getElementById('bulkCheckCounter').textContent = (i + 1) + '/' + urls.length;
            document.getElementById('bulkCheckBar').style.width = pct + '%';

            try {
                const formData = new FormData();
                formData.append('action', 'validate_proxy');
                formData.append('proxy_url', url);
                const resp = await fetch('/proxies.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.alive) {
                    liveUrls.push(url);
                    document.getElementById('liveCount').textContent = liveUrls.length;
                    resultsDiv.innerHTML += '<div style="color:var(--success);">LIVE: ' + escapeHtml(url) + '</div>';
                } else {
                    deadUrls.push(url);
                    document.getElementById('deadCount').textContent = deadUrls.length;
                    resultsDiv.innerHTML += '<div style="color:var(--danger);">DEAD: ' + escapeHtml(url) + (data.error ? ' — ' + escapeHtml(data.error) : '') + '</div>';
                }
            } catch (err) {
                deadUrls.push(url);
                document.getElementById('deadCount').textContent = deadUrls.length;
                resultsDiv.innerHTML += '<div style="color:var(--danger);">ERROR: ' + escapeHtml(url) + ' — request failed</div>';
            }
            // Auto-scroll results
            resultsDiv.scrollTop = resultsDiv.scrollHeight;
        }
    } else {
        // No live check, just import all
        liveUrls.push(...urls);
    }

    // Now import the live ones
    if (liveUrls.length > 0) {
        btn.textContent = 'Importing ' + liveUrls.length + ' live proxies...';
        const formData = new FormData();
        formData.append('action', 'bulk_add');
        formData.append('proxy_urls', liveUrls.join('\n'));
        formData.append('proxy_type', document.querySelector('[name="proxy_type"]').value);
        formData.append('max_requests', document.querySelector('[name="max_requests"]').value);
        formData.append('max_fails', document.querySelector('[name="max_fails"]').value);
        formData.append('cooldown_minutes', document.querySelector('[name="cooldown_minutes"]').value);
        formData.append('check_live', '0'); // already checked

        try {
            const resp = await fetch('/proxies.php', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: formData
            });
            const data = await resp.json();
            showToast(data.message || 'Done', data.success ? 'success' : 'warning');
            if (data.success) setTimeout(() => location.href = '/proxies.php', 1500);
        } catch (err) {
            showToast('Import failed', 'error');
        }
    } else {
        showToast('No live proxies found to import', 'error');
    }

    btn.disabled = false;
    btn.textContent = 'Import Proxies';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function checkProxyLive() {
    const url = document.getElementById('proxyUrlInput').value;
    if (!url) { showToast('Enter a proxy URL first', 'error'); return; }
    const resultDiv = document.getElementById('proxyCheckResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<span style="color:var(--accent);">Checking...</span>';

    const formData = new FormData();
    formData.append('action', 'validate_proxy');
    formData.append('proxy_url', url);
    fetch('/proxies.php', { method: 'POST', body: formData })
    .then(r => r.json()).then(data => {
        if (data.alive) {
            resultDiv.innerHTML = '<span style="color:var(--success);font-weight:700;">LIVE</span> - Proxy is working (HTTP ' + data.http_code + ')';
        } else {
            resultDiv.innerHTML = '<span style="color:var(--danger);font-weight:700;">DEAD</span> - ' + (data.error || 'Connection failed');
        }
    }).catch(() => { resultDiv.innerHTML = '<span style="color:var(--danger);">Check failed</span>'; });
}

function ajaxToggleProxy(id, status) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    formData.append('current_status', status);
    fetch('/proxies.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:formData })
    .then(r=>r.json()).then(data => { showToast(data.message,'success'); setTimeout(()=>location.reload(),500); });
}

function ajaxDeleteProxy(id) {
    if (!confirm('Delete this proxy?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('/proxies.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:formData })
    .then(r=>r.json()).then(data => { showToast(data.message,'success'); setTimeout(()=>location.reload(),500); });
}

function ajaxResetAllProxies() {
    if (!confirm('Reset all proxy counters and re-activate all?')) return;
    const formData = new FormData();
    formData.append('action', 'reset_all');
    fetch('/proxies.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:formData })
    .then(r=>r.json()).then(data => { showToast(data.message,'success'); setTimeout(()=>location.reload(),500); });
}

function ajaxValidateAllProxies() {
    if (!confirm('This will test ALL proxies. It may take a while. Continue?')) return;
    showToast('Validating all proxies... this may take a while', 'info');
    const formData = new FormData();
    formData.append('action', 'validate_all');
    fetch('/proxies.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:formData, timeout:120000 })
    .then(r=>r.json()).then(data => { showToast(data.message,'success'); setTimeout(()=>location.reload(),1000); })
    .catch(() => showToast('Validation failed or timed out','error'));
}
</script>
</body>
</html>
