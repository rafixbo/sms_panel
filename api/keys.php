<?php
require_once __DIR__ . '/includes/config.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';
$action = $_GET['action'] ?? 'list';
$newlyCreatedKey = null;

// ---- HANDLE AJAX ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($postAction === 'create') {
        $name      = trim($_POST['key_name'] ?? '');
        $owner     = trim($_POST['owner_username'] ?? '');
        $points    = (int)($_POST['total_points'] ?? 100);
        $daily     = (int)($_POST['daily_limit'] ?? 50);
        $monthly   = (int)($_POST['monthly_limit'] ?? 1000);
        $ips       = trim($_POST['allowed_ips'] ?? '');
        $expires   = trim($_POST['expires_at'] ?? '');
        $watermark = trim($_POST['watermark'] ?? '');
        $keyFormat = $_POST['key_format'] ?? 'xxx-xxxxxx-xxxxxx';
        $gatewayId = (int)($_POST['assigned_gateway_id'] ?? 0) ?: null;

        if (!$name || !$owner) {
            $msg = 'Name and owner are required.'; $msgType = 'danger';
        } else {
            $newKey = generateApiKey($keyFormat);
            $allowedIps = $ips ? json_encode(array_filter(array_map('trim', explode(',', $ips)))) : null;
            $expiresAt  = $expires ?: null;
            $watermarkVal = $watermark ?: null;
            if ($db) {
                $db->prepare("
                    INSERT INTO api_keys (key_name, api_key, owner_username, total_points, daily_limit, monthly_limit, allowed_ips, expires_at, watermark, key_format, assigned_gateway_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([$name, $newKey, $owner, $points, $daily, $monthly, $allowedIps, $expiresAt, $watermarkVal, $keyFormat, $gatewayId]);
                logSystem('INFO','KEY_CREATED',"Key: $newKey | Owner: $owner | Points: $points | Format: $keyFormat");
                $newlyCreatedKey = $newKey;
                $msg = 'API Key created successfully!'; $msgType = 'success';
                $action = 'list';

                if ($isAjax) {
                    $keyData = $db->query("SELECT * FROM api_keys WHERE api_key=" . $db->quote($newKey))->fetch();
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>true, 'message'=>$msg, 'key'=>$newKey, 'key_data'=>$keyData]);
                    exit;
                }
            } else {
                $msg = 'Database not connected. (Demo: Key would be: ' . $newKey . ')'; $msgType = 'warning';
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'update') {
        $id      = (int)$_POST['id'];
        $points  = (int)($_POST['total_points'] ?? 100);
        $daily   = (int)($_POST['daily_limit'] ?? 50);
        $monthly = (int)($_POST['monthly_limit'] ?? 1000);
        $status  = $_POST['status'] ?? 'active';
        $ips     = trim($_POST['allowed_ips'] ?? '');
        $expires = trim($_POST['expires_at'] ?? '');
        $watermark = trim($_POST['watermark'] ?? '');
        $gatewayId = (int)($_POST['assigned_gateway_id'] ?? 0) ?: null;
        $allowedIps = $ips ? json_encode(array_filter(array_map('trim', explode(',', $ips)))) : null;
        $watermarkVal = $watermark ?: null;
        if ($db) {
            $db->prepare("UPDATE api_keys SET total_points=?,daily_limit=?,monthly_limit=?,status=?,allowed_ips=?,expires_at=?,watermark=?,assigned_gateway_id=? WHERE id=?")
               ->execute([$points, $daily, $monthly, $status, $allowedIps, $expires ?: null, $watermarkVal, $gatewayId, $id]);
            $msg = 'Key updated.'; $msgType = 'success';
        }
        $action = 'list';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'add_points') {
        $id = (int)$_POST['id'];
        $pts = (int)$_POST['add_points'];
        if ($db && $pts > 0) {
            $db->prepare("UPDATE api_keys SET total_points = total_points + ? WHERE id=?")->execute([$pts, $id]);
            $k = $db->query("SELECT api_key, owner_username, total_points, used_points FROM api_keys WHERE id=$id")->fetch();
            $bal = $k['total_points'] - $k['used_points'];
            $db->prepare("INSERT INTO points_transactions (api_key_id, api_key, transaction_type, points, balance_after, description) VALUES (?,?,'credit',?,?,?)")
               ->execute([$id, $k['api_key'], $pts, $bal, "Admin credit $pts points"]);
            $msg = "$pts points added."; $msgType = 'success';
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
            $k = $db->query("SELECT api_key FROM api_keys WHERE id=$id")->fetch();
            $db->prepare("DELETE FROM api_keys WHERE id=?")->execute([$id]);
            logSystem('INFO','KEY_DELETED',"Key: ".($k['api_key']??'?'));
            $msg = 'Key deleted.'; $msgType = 'success';
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
        $new = $cur === 'active' ? 'suspended' : 'active';
        if ($db) $db->prepare("UPDATE api_keys SET status=? WHERE id=?")->execute([$new, $id]);
        $msg = "Key $new."; $msgType = 'success';
        $action = 'list';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }
}

// Edit data
$editKey = null;
if ($action === 'edit' && isset($_GET['id']) && $db) {
    $editKey = $db->prepare("SELECT * FROM api_keys WHERE id=?");
    $editKey->execute([(int)$_GET['id']]);
    $editKey = $editKey->fetch();
}

// Get gateways for dropdown
$gateways = [];
if ($db) {
    $gateways = $db->query("SELECT id, name FROM gateways WHERE status='active' ORDER BY name")->fetchAll();
}

// List
$keys = [];
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
if ($db) {
    $where = '1=1';
    $params = [];
    if ($search) { $where .= " AND (key_name LIKE ? OR owner_username LIKE ? OR api_key LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
    if ($filter !== 'all') { $where .= " AND status=?"; $params[] = $filter; }
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $keys = $stmt->fetchAll();
}

$keyFormats = getKeyFormats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Keys — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">API Keys</h1>
        <p class="page-sub">Create and manage access keys for the SMS API</p>
    </div>
    <div class="header-actions">
        <a href="/keys.php?action=new" class="btn btn-primary">+ Create New Key</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<!-- ======= CREATE / EDIT FORM ======= -->
<div class="card fade-in">
    <div class="card-head">
        <h3><?= $editKey ? 'Edit Key: '.htmlspecialchars($editKey['key_name']) : 'Create New API Key' ?></h3>
        <a href="/keys.php" class="btn btn-secondary btn-sm">Back</a>
    </div>
    <form id="keyForm" method="POST" onsubmit="return submitKeyForm(event)">
        <input type="hidden" name="action" value="<?= $editKey ? 'update' : 'create' ?>">
        <?php if ($editKey): ?><input type="hidden" name="id" value="<?= $editKey['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <?php if (!$editKey): ?>
            <div class="form-group">
                <label>Key Name *</label>
                <input type="text" name="key_name" class="form-control" placeholder="e.g. Production Key" required>
            </div>
            <div class="form-group">
                <label>Owner Username *</label>
                <input type="text" name="owner_username" class="form-control" placeholder="e.g. john_doe" required>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Total Points</label>
                <input type="number" name="total_points" class="form-control" value="<?= $editKey['total_points'] ?? 100 ?>" min="0" max="100000">
            </div>
            <div class="form-group">
                <label>Daily Send Limit (0=unlimited)</label>
                <input type="number" name="daily_limit" class="form-control" value="<?= $editKey['daily_limit'] ?? 50 ?>" min="0">
            </div>
            <div class="form-group">
                <label>Monthly Send Limit (0=unlimited)</label>
                <input type="number" name="monthly_limit" class="form-control" value="<?= $editKey['monthly_limit'] ?? 1000 ?>" min="0">
            </div>
            <div class="form-group">
                <label>Expires At (leave blank = never)</label>
                <input type="datetime-local" name="expires_at" class="form-control" value="<?= $editKey ? substr($editKey['expires_at']??'',0,16) : '' ?>">
            </div>
            <div class="form-group">
                <label>Key Format Style</label>
                <select name="key_format" class="form-control" id="keyFormatSelect" onchange="updateKeyPreview()">
                    <?php foreach ($keyFormats as $fmt => $example): ?>
                    <option value="<?= $fmt ?>" <?= ($editKey['key_format'] ?? 'xxx-xxxxxx-xxxxxx') === $fmt ? 'selected' : '' ?>><?= $example ?> (<?= $fmt ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div id="keyPreview" class="mono" style="margin-top:8px;color:var(--accent);font-size:13px;padding:8px;background:var(--surface2);border-radius:6px;">Preview: <span id="keyPreviewText"><?= API_KEY_PREFIX ?>-XXXXXX-XXXXXX</span></div>
            </div>
            <div class="form-group">
                <label>Assigned Gateway</label>
                <select name="assigned_gateway_id" class="form-control">
                    <option value="0">Auto (Default)</option>
                    <?php foreach ($gateways as $gw): ?>
                    <option value="<?= $gw['id'] ?>" <?= ($editKey['assigned_gateway_id'] ?? 0) == $gw['id'] ? 'selected' : '' ?>><?= htmlspecialchars($gw['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($editKey): ?>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $editKey['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="suspended" <?= $editKey['status']==='suspended'?'selected':'' ?>>Suspended</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Watermark Message (appended to every SMS sent with this key)</label>
            <input type="text" name="watermark" class="form-control mono" placeholder="e.g. - Sent via PrimeCode" value="<?= htmlspecialchars($editKey['watermark'] ?? '') ?>" maxlength="200">
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">This text will be automatically added with space to the end of every user's message body.</div>
        </div>
        <div class="form-group">
            <label>IP Whitelist (comma-separated, blank = allow all)</label>
            <input type="text" name="allowed_ips" class="form-control mono" placeholder="e.g. 192.168.1.1, 10.0.0.5" value="<?= $editKey ? htmlspecialchars(implode(', ', json_decode($editKey['allowed_ips']??'[]', true) ?: [])) : '' ?>">
        </div>
        <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="submit" class="btn btn-primary" id="submitBtn"><?= $editKey ? 'Save Changes' : 'Generate API Key' ?></button>
            <a href="/keys.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- ======= FILTER BAR ======= -->
<div class="card" style="padding:16px 24px;">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;">
            <div class="search-bar" style="flex:1;min-width:200px;">
                <span>?</span>
                <input type="text" name="q" placeholder="Search keys, owners..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="filter" class="form-control" style="width:140px;">
                <option value="all" <?= $filter==='all'?'selected':'' ?>>All Status</option>
                <option value="active" <?= $filter==='active'?'selected':'' ?>>Active</option>
                <option value="suspended" <?= $filter==='suspended'?'selected':'' ?>>Suspended</option>
                <option value="expired" <?= $filter==='expired'?'selected':'' ?>>Expired</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($search||$filter!=='all'): ?><a href="/keys.php" class="btn btn-secondary">Clear</a><?php endif; ?>
        </form>
        <span class="muted mono" style="font-size:11px;"><?= count($keys) ?> keys</span>
    </div>
</div>

<!-- ======= KEYS TABLE ======= -->
<div class="card fade-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Name / Owner</th><th>API Key</th>
                    <th>Points</th><th>Daily</th><th>Gateway</th>
                    <th>Watermark</th><th>Status</th><th>Last Used</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($keys)): ?>
                <tr><td colspan="10" class="empty-row">No API keys found. <a href="/keys.php?action=new">Create one</a></td></tr>
            <?php else: foreach ($keys as $k):
                $pts_rem = $k['total_points'] - $k['used_points'];
                $pct = $k['total_points'] > 0 ? round($pts_rem/$k['total_points']*100) : 0;
                $gwName = 'Auto';
                if ($k['assigned_gateway_id'] && $db) {
                    $gw = $db->query("SELECT name FROM gateways WHERE id={$k['assigned_gateway_id']}")->fetch();
                    if ($gw) $gwName = $gw['name'];
                }
            ?>
                <tr>
                    <td class="mono muted"><?= $k['id'] ?></td>
                    <td>
                        <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($k['key_name']) ?></div>
                        <div class="mono muted" style="font-size:11px;"><?= htmlspecialchars($k['owner_username']) ?></div>
                    </td>
                    <td>
                        <div class="key-display" style="font-size:11px;padding:6px 10px;">
                            <span><?= substr($k['api_key'],0,20) ?>...</span>
                            <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($k['api_key']) ?>',this)">Copy</button>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?= $pts_rem ?><span class="muted">/<?= $k['total_points'] ?></span></div>
                        <div class="pts-bar" style="margin-top:4px;"><div class="pts-fill" style="width:<?= $pct ?>%"></div></div>
                    </td>
                    <td class="mono"><?= $k['daily_used'] ?>/<?= $k['daily_limit']?:('inf') ?></td>
                    <td class="mono" style="font-size:11px;"><?= htmlspecialchars($gwName) ?></td>
                    <td style="font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($k['watermark'] ?? '') ?>"><?= $k['watermark'] ? htmlspecialchars(mb_substr($k['watermark'],0,20)) : '<span class="muted">-</span>' ?></td>
                    <td><span class="badge badge-<?= $k['status'] ?>"><?= $k['status'] ?></span></td>
                    <td class="mono muted" style="font-size:11px;"><?= $k['last_used_at'] ? date('m-d H:i',strtotime($k['last_used_at'])) : '-' ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a href="/keys.php?action=edit&id=<?= $k['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <button class="btn btn-secondary btn-sm" onclick="ajaxToggleKey(<?= $k['id'] ?>,'<?= $k['status'] ?>')">Toggle</button>
                            <button class="btn btn-secondary btn-sm" onclick="toggleAddPts(<?= $k['id'] ?>)">+Pts</button>
                            <button class="btn btn-danger btn-sm" onclick="ajaxDeleteKey(<?= $k['id'] ?>)">Del</button>
                        </div>
                        <!-- Add Points sub-form -->
                        <div id="addpts-<?= $k['id'] ?>" style="display:none;margin-top:8px;">
                            <form onsubmit="return ajaxAddPoints(event, <?= $k['id'] ?>)" style="display:flex;gap:6px;">
                                <input type="number" name="add_points" class="form-control mono" style="width:80px;padding:5px 8px;font-size:12px;" placeholder="pts" min="1">
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
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

<!-- ======= KEY CREATED POPUP MODAL ======= -->
<div id="keyCreatedModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>API Key Created Successfully!</h3>
            <button class="modal-close" onclick="closeKeyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:16px;">
                <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;display:block;margin-bottom:8px;">Your API Key</label>
                <div class="key-display" style="font-size:14px;padding:14px 16px;">
                    <span id="modalApiKey" style="word-break:break-all;"></span>
                    <button class="copy-btn" onclick="copyText(document.getElementById('modalApiKey').textContent,this)" style="font-size:12px;padding:6px 14px;">Copy</button>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;display:block;margin-bottom:8px;">How to Use</label>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;font-family:'Space Mono',monospace;font-size:11px;line-height:1.8;">
                    <div style="color:var(--accent);margin-bottom:8px;">GET Request:</div>
                    <div style="color:var(--text2);word-break:break-all;" id="modalGetExample"></div>
                    <br>
                    <div style="color:var(--accent);margin-bottom:8px;">POST Request:</div>
                    <div style="color:var(--text2);" id="modalPostExample"></div>
                    <br>
                    <div style="color:var(--accent);margin-bottom:8px;">cURL Example:</div>
                    <div style="color:var(--text2);word-break:break-all;" id="modalCurlExample"></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-primary" onclick="copyFullInstructions()" style="flex:1;">Copy Full Instructions</button>
                <button class="btn btn-secondary" onclick="closeKeyModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
function toggleAddPts(id) {
    const el = document.getElementById('addpts-'+id);
    el.style.display = el.style.display==='none' ? 'block' : 'none';
}

function updateKeyPreview() {
    const sel = document.getElementById('keyFormatSelect');
    const preview = document.getElementById('keyPreviewText');
    const fmt = sel.value;
    const p = '<?= API_KEY_PREFIX ?>';
    const formats = {
        'xxx-xxxxxx-xxxxxx': p+'-A1B2C3-D4E5F6',
        'xxx-xxx-xxx': p+'-A1-B2',
        'xxxx-xxxx-xxxx-xxxx': p+'A1-B2C3-D4E5-F6A7',
        'xxx-xxxx-xxxx': p+'-A1B2-C3D4',
        'xxxxxx-xxxxxx-xxxxxx': 'A1B2C3-'+p+'-D4E5F6',
        'xxx-xxxx-xxxx-xxxx': p+'-A1B2-C3D4-E5F6',
    };
    preview.textContent = formats[fmt] || p+'-XXXXXX-XXXXXX';
}

// AJAX form submission
function submitKeyForm(e) {
    e.preventDefault();
    const form = document.getElementById('keyForm');
    const btn = document.getElementById('submitBtn');
    const origText = btn.textContent;
    btn.textContent = 'Creating...';
    btn.disabled = true;

    const formData = new FormData(form);

    fetch('/keys.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = origText;
        btn.disabled = false;
        if (data.success) {
            showToast(data.message, 'success');
            if (data.key) {
                showKeyCreatedModal(data.key);
            } else {
                setTimeout(() => window.location.href = '/keys.php', 1000);
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        btn.textContent = origText;
        btn.disabled = false;
        showToast('Request failed', 'error');
    });
    return false;
}

function showKeyCreatedModal(key) {
    document.getElementById('modalApiKey').textContent = key;
    const host = window.location.host;
    const proto = window.location.protocol;
    document.getElementById('modalGetExample').textContent =
        proto + '//' + host + '/send.php?key=' + key + '&phone=01XXXXXXXXX&msg=Your+message';
    document.getElementById('modalPostExample').innerHTML =
        'curl -X POST ' + proto + '//' + host + '/send.php \\<br>' +
        '&nbsp;&nbsp;-H "Content-Type: application/json" \\<br>' +
        '&nbsp;&nbsp;-d \'{"key":"' + key + '","phone":"01XXXXXXXXX","msg":"Your message"}\'';
    document.getElementById('modalCurlExample').textContent =
        'curl "' + proto + '//' + host + '/send.php?key=' + key + '&phone=01XXXXXXXXX&msg=Hello"';
    document.getElementById('keyCreatedModal').style.display = 'flex';
}

function closeKeyModal() {
    document.getElementById('keyCreatedModal').style.display = 'none';
    window.location.href = '/keys.php';
}

function copyFullInstructions() {
    const key = document.getElementById('modalApiKey').textContent;
    const host = window.location.host;
    const proto = window.location.protocol;
    const text = `SMS API Key Usage Instructions\n` +
        `================================\n\n` +
        `Your API Key: ${key}\n\n` +
        `GET Request:\n${proto}//${host}/send.php?key=${key}&phone=01XXXXXXXXX&msg=Your+message\n\n` +
        `POST Request (JSON):\n` +
        `curl -X POST ${proto}//${host}/send.php \\\n` +
        `  -H "Content-Type: application/json" \\\n` +
        `  -d '{"key":"${key}","phone":"01XXXXXXXXX","msg":"Your message"}'\n\n` +
        `GET Request (cURL):\n` +
        `curl "${proto}//${host}/send.php?key=${key}&phone=01XXXXXXXXX&msg=Hello"\n\n` +
        `Response Format:\n` +
        `{"status":"success","message":"SMS sent successfully","phone":"...","sms_body":"...","key_owner":"...","gateway_used":"...","points_used":1,"points_remaining":99,"sent_at":"...","Owner":"<?= APP_OWNER ?>"}`;
    navigator.clipboard.writeText(text).then(() => showToast('Full instructions copied!', 'success'));
}

function ajaxToggleKey(id, currentStatus) {
    if (!confirm('Toggle this key?')) return;
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    formData.append('current_status', currentStatus);
    fetch('/keys.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        setTimeout(() => location.reload(), 500);
    });
}

function ajaxDeleteKey(id) {
    if (!confirm('Delete this key permanently?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('/keys.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        setTimeout(() => location.reload(), 500);
    });
}

function ajaxAddPoints(e, id) {
    e.preventDefault();
    const form = e.target;
    const pts = form.querySelector('[name=add_points]').value;
    const formData = new FormData();
    formData.append('action', 'add_points');
    formData.append('id', id);
    formData.append('add_points', pts);
    fetch('/keys.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        setTimeout(() => location.reload(), 500);
    });
    return false;
}

<?php if ($newlyCreatedKey): ?>
document.addEventListener('DOMContentLoaded', function() {
    showKeyCreatedModal('<?= htmlspecialchars($newlyCreatedKey) ?>');
});
<?php endif; ?>
</script>
</body>
</html>
