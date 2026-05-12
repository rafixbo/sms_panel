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
        $name        = trim($_POST['name'] ?? '');
        $method      = $_POST['method'] ?? 'POST';
        $url         = trim($_POST['url'] ?? '');
        $headers     = trim($_POST['headers'] ?? '{}');
        $bodyType    = $_POST['body_type'] ?? 'json';
        $bodyTemplate= trim($_POST['body_template'] ?? '{}');
        $paramPhone  = trim($_POST['param_phone'] ?? 'phone');
        $paramMessage= trim($_POST['param_message'] ?? 'hash');
        $extraParams = trim($_POST['extra_params'] ?? '{}');
        $responseCheck = trim($_POST['response_check'] ?? '{}');
        $successHttpCodes = trim($_POST['success_http_codes'] ?? '[200,201,202]');
        $timeout     = (int)($_POST['timeout'] ?? 15);
        $proxyEnabled= (int)($_POST['proxy_enabled'] ?? 0);
        $priority    = (int)($_POST['priority'] ?? 0);
        $isDefault   = (int)($_POST['is_default'] ?? 0);

        if (!$name || !$url) {
            $msg = 'Name and URL are required.'; $msgType = 'danger';
        } else {
            // Validate JSON fields
            $hDec = json_decode($headers);
            $bDec = json_decode($bodyTemplate);
            $eDec = json_decode($extraParams);
            $rDec = json_decode($responseCheck);
            $sDec = json_decode($successHttpCodes);

            if ($hDec === null && $headers !== '{}') { $msg = 'Headers JSON is invalid.'; $msgType = 'danger'; }
            elseif ($bDec === null && $bodyTemplate !== '{}') { $msg = 'Body Template JSON is invalid.'; $msgType = 'danger'; }
            elseif ($eDec === null && $extraParams !== '{}') { $msg = 'Extra Params JSON is invalid.'; $msgType = 'danger'; }
            else {
                if ($isDefault && $db) {
                    $db->exec("UPDATE gateways SET is_default=0 WHERE is_default=1");
                }

                if ($db) {
                    $db->prepare("
                        INSERT INTO gateways (name, method, url, headers, body_type, body_template, param_phone, param_message, extra_params, response_check, success_http_codes, timeout, proxy_enabled, priority, is_default, status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ")->execute([$name, $method, $url, $headers, $bodyType, $bodyTemplate, $paramPhone, $paramMessage, $extraParams, $responseCheck, $successHttpCodes, $timeout, $proxyEnabled, $priority, $isDefault, 'active']);
                    logSystem('INFO','GATEWAY_CREATED',"Gateway: $name | URL: $url | Method: $method | BodyType: $bodyType");
                    $msg = 'Gateway created successfully!'; $msgType = 'success';
                    $action = 'list';
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>$msgType!=='danger', 'message'=>$msg ?: 'Done']);
            exit;
        }
    }

    if ($postAction === 'update') {
        $id          = (int)$_POST['id'];
        $name        = trim($_POST['name'] ?? '');
        $method      = $_POST['method'] ?? 'POST';
        $url         = trim($_POST['url'] ?? '');
        $headers     = trim($_POST['headers'] ?? '{}');
        $bodyType    = $_POST['body_type'] ?? 'json';
        $bodyTemplate= trim($_POST['body_template'] ?? '{}');
        $paramPhone  = trim($_POST['param_phone'] ?? 'phone');
        $paramMessage= trim($_POST['param_message'] ?? 'hash');
        $extraParams = trim($_POST['extra_params'] ?? '{}');
        $responseCheck = trim($_POST['response_check'] ?? '{}');
        $successHttpCodes = trim($_POST['success_http_codes'] ?? '[200,201,202]');
        $timeout     = (int)($_POST['timeout'] ?? 15);
        $proxyEnabled= (int)($_POST['proxy_enabled'] ?? 0);
        $priority    = (int)($_POST['priority'] ?? 0);
        $isDefault   = (int)($_POST['is_default'] ?? 0);
        $status      = $_POST['status'] ?? 'active';

        // Validate JSON
        if (json_decode($headers) === null && $headers !== '{}') { $msg = 'Headers JSON invalid.'; $msgType = 'danger'; }
        elseif (json_decode($bodyTemplate) === null && $bodyTemplate !== '{}') { $msg = 'Body Template JSON invalid.'; $msgType = 'danger'; }
        else {
            if ($isDefault && $db) {
                $db->exec("UPDATE gateways SET is_default=0 WHERE is_default=1");
            }
            if ($db) {
                $db->prepare("
                    UPDATE gateways SET name=?,method=?,url=?,headers=?,body_type=?,body_template=?,param_phone=?,param_message=?,extra_params=?,response_check=?,success_http_codes=?,timeout=?,proxy_enabled=?,priority=?,is_default=?,status=?
                    WHERE id=?
                ")->execute([$name, $method, $url, $headers, $bodyType, $bodyTemplate, $paramPhone, $paramMessage, $extraParams, $responseCheck, $successHttpCodes, $timeout, $proxyEnabled, $priority, $isDefault, $status, $id]);
                $msg = 'Gateway updated.'; $msgType = 'success';
            }
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>$msgType!=='danger', 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'delete') {
        $id = (int)$_POST['id'];
        if ($db) {
            $g = $db->query("SELECT name FROM gateways WHERE id=$id")->fetch();
            $db->prepare("DELETE FROM gateways WHERE id=?")->execute([$id]);
            logSystem('INFO','GATEWAY_DELETED',"Gateway: ".($g['name']??'?'));
            $msg = 'Gateway deleted.'; $msgType = 'success';
        }
        $action = 'list';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>true, 'message'=>$msg]);
            exit;
        }
    }

    if ($postAction === 'parse_curl') {
        $curlText = $_POST['curl_text'] ?? '';
        $parsed = parseCurlCommand($curlText);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true, 'parsed'=>$parsed]);
        exit;
    }

    if ($postAction === 'test_gateway') {
        $gwId = (int)($_POST['gateway_id'] ?? 0);
        $testPhone = trim($_POST['test_phone'] ?? '01700000000');
        $testMsg = trim($_POST['test_msg'] ?? 'Test SMS from Portal');

        if ($db && $gwId) {
            $gw = getGatewayById($db, $gwId);
            if ($gw) {
                $response = callGateway($gw, $testPhone, $testMsg);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'http_code' => $response['http_code'],
                    'body' => $response['raw'],
                    'error' => $response['error'],
                    'sent_body' => $response['debug_body'] ?? null,
                    'sent_headers' => $response['debug_headers'] ?? [],
                    'sent_url' => $response['debug_url'] ?? null,
                ]);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success'=>false, 'message'=>'Gateway not found']);
        exit;
    }

    if ($postAction === 'preview_request') {
        // Build a preview of what will be sent without actually sending
        $name        = trim($_POST['name'] ?? '');
        $method      = $_POST['method'] ?? 'POST';
        $url         = trim($_POST['url'] ?? '');
        $headers     = trim($_POST['headers'] ?? '{}');
        $bodyType    = $_POST['body_type'] ?? 'json';
        $bodyTemplate= trim($_POST['body_template'] ?? '{}');
        $paramPhone  = trim($_POST['param_phone'] ?? 'phone');
        $paramMessage= trim($_POST['param_message'] ?? 'hash');
        $extraParams = trim($_POST['extra_params'] ?? '{}');

        $hDec = json_decode($headers, true) ?: [];
        $bDec = json_decode($bodyTemplate, true) ?: [];
        $eDec = json_decode($extraParams, true) ?: [];

        // Build body like callGateway does
        $bodyData = is_array($bDec) ? $bDec : [];
        if (is_array($eDec)) {
            foreach ($eDec as $k => $v) $bodyData[$k] = $v;
        }
        $bodyData[$paramPhone] = '01700000000';
        $bodyData[$paramMessage] = 'Test message here';

        $previewBody = '';
        if ($bodyType === 'json') {
            $previewBody = json_encode($bodyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hDec['Content-Type'] = 'application/json';
        } elseif ($bodyType === 'form') {
            $previewBody = http_build_query($bodyData);
            $hDec['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            $previewBody = http_build_query($bodyData);
        }

        $previewHeaders = [];
        foreach ($hDec as $k => $v) $previewHeaders[] = "$k: $v";

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'method'  => $method,
            'url'     => $url,
            'headers' => $previewHeaders,
            'body'    => $previewBody,
            'body_type' => $bodyType,
        ]);
        exit;
    }
}

// Edit data
$editGw = null;
if ($action === 'edit' && isset($_GET['id']) && $db) {
    $editGw = $db->prepare("SELECT * FROM gateways WHERE id=?");
    $editGw->execute([(int)$_GET['id']]);
    $editGw = $editGw->fetch();
}

// List
$gateways = [];
if ($db) {
    $gateways = $db->query("SELECT * FROM gateways ORDER BY priority DESC, name ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gateways — <?= APP_NAME ?></title>
<?php include 'includes/head.php'; ?>
<style>
.form-control,
.code-area {
    background: rgba(255,255,255,0.96);
    border: 1px solid rgba(255,255,255,0.4);
    color: #111827;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 13px;
    transition: all 0.25s ease;
    backdrop-filter: blur(10px);
    box-shadow:
        0 4px 12px rgba(0,0,0,0.06),
        inset 0 1px 0 rgba(255,255,255,0.8);

    font-family: 'Space Mono', monospace;
    line-height: 1.6;
    width: 100%;
    min-height: 100px;
    resize: vertical;
    outline: none;
    tab-size: 2;
}

.form-control::placeholder,
.code-area::placeholder {
    color: #9ca3af;
}

.form-control:focus,
.code-area:focus {
    background: #ffffff;
    border-color: #d1d5db;
    box-shadow:
        0 0 0 4px rgba(255,255,255,0.35),
        0 8px 24px rgba(255,255,255,0.15);
    outline: none;
    color: #000;
}
.hint-box {
    background: rgba(0,245,196,0.04);
    border: 1px solid rgba(0,245,196,0.12);
    border-radius: 8px;
    padding: 14px 16px;
    margin-top: 8px;
    font-size: 12px;
    line-height: 1.7;
}
.hint-box code {
    background: var(--surface2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    color: var(--accent);
}
.hint-box .hint-title {
    font-weight: 700;
    color: var(--accent);
    margin-bottom: 6px;
    font-size: 12px;
}
.tab-group { display: flex; gap: 0; margin-bottom: 16px; }
.tab-btn {
    padding: 8px 18px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--muted);
    font-family: 'Syne', sans-serif;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.tab-btn:first-child { border-radius: 8px 0 0 8px; }
.tab-btn:last-child { border-radius: 0 8px 8px 0; }
.tab-btn.active { background: linear-gradient(135deg, var(--accent2), var(--accent)); color: #000; border-color: transparent; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.gw-test-result {
    background: #060609;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    line-height: 1.7;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/topbar.php'; ?>
<div class="page-body">

<div class="page-header">
    <div>
        <h1 class="page-title">Gateways</h1>
        <p class="page-sub">Configure API gateways for SMS delivery</p>
    </div>
    <div class="header-actions">
        <a href="/gateways.php?action=new" class="btn btn-primary">+ Add Gateway</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<!-- ======= CREATE / EDIT GATEWAY FORM ======= -->
<div class="card fade-in">
    <div class="card-head">
        <h3><?= $editGw ? 'Edit Gateway: '.htmlspecialchars($editGw['name']) : 'Add New Gateway' ?></h3>
        <a href="/gateways.php" class="btn btn-secondary btn-sm">Back</a>
    </div>

    <!-- CURL PARSER SECTION -->
    <?php if (!$editGw): ?>
    <div style="margin-bottom:24px;padding:20px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h4 style="font-size:14px;color:var(--accent);">Quick Import from cURL</h4>
            <span class="muted" style="font-size:11px;">Paste any curl command to auto-fill the form below</span>
        </div>
        <textarea id="curlInput" class="code-area" style="min-height:140px;" placeholder='Paste your curl command here, for example:

curl -X POST "https://example.com/api/auth/login-v2" \
  -H "User-Agent: okhttp/4.12.0" \
  -H "Accept: application/json, text/plain, */*" \
  -H "Content-Type: application/json" \
  --data &#039;{"hash":"MSG","phone":"0181111111","otp":"","token":"","version":"2.0.3"}&#039;'></textarea>
        <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="parseCurl()">Parse cURL & Auto-Fill</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('curlInput').value=''">Clear</button>
        </div>
    </div>
    <?php endif; ?>

    <form id="gatewayForm" method="POST" onsubmit="return submitGatewayForm(event)">
        <input type="hidden" name="action" value="<?= $editGw ? 'update' : 'create' ?>">
        <?php if ($editGw): ?><input type="hidden" name="id" value="<?= $editGw['id'] ?>"><?php endif; ?>

        <!-- Basic Info -->
        <div style="margin-bottom:20px;">
            <h4 style="font-size:13px;color:var(--accent);margin-bottom:14px;font-weight:700;">Basic Configuration</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Gateway Name *</label>
                    <input type="text" name="name" id="gw_name" class="form-control" placeholder="e.g. A1, HisabKhata, Robi SMS" value="<?= htmlspecialchars($editGw['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>API URL *</label>
                    <input type="text" name="url" id="gw_url" class="form-control mono" placeholder="https://example.com/api/endpoint" value="<?= htmlspecialchars($editGw['url'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>HTTP Method</label>
                    <select name="method" id="gw_method" class="form-control">
                        <option value="POST" <?= ($editGw['method'] ?? 'POST') === 'POST' ? 'selected' : '' ?>>POST only</option>
                        <option value="GET" <?= ($editGw['method'] ?? '') === 'GET' ? 'selected' : '' ?>>GET only</option>
                        <option value="BOTH" <?= ($editGw['method'] ?? '') === 'BOTH' ? 'selected' : '' ?>>BOTH (accept GET + POST)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Body Encoding Type</label>
                    <select name="body_type" id="gw_body_type" class="form-control">
                        <option value="json" <?= ($editGw['body_type'] ?? 'json') === 'json' ? 'selected' : '' ?>>JSON (most APIs)</option>
                        <option value="form" <?= ($editGw['body_type'] ?? '') === 'form' ? 'selected' : '' ?>>Form URL-encoded</option>
                        <option value="query" <?= ($editGw['body_type'] ?? '') === 'query' ? 'selected' : '' ?>>Query String (in URL)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone Parameter Name</label>
                    <input type="text" name="param_phone" id="gw_param_phone" class="form-control mono" placeholder="phone" value="<?= htmlspecialchars($editGw['param_phone'] ?? 'phone') ?>">
                    <div style="font-size:10px;color:var(--muted);margin-top:4px;">The JSON key / URL param name that holds the phone number</div>
                </div>
                <div class="form-group">
                    <label>Message Parameter Name</label>
                    <input type="text" name="param_message" id="gw_param_message" class="form-control mono" placeholder="hash" value="<?= htmlspecialchars($editGw['param_message'] ?? 'hash') ?>">
                    <div style="font-size:10px;color:var(--muted);margin-top:4px;">The JSON key / URL param name that holds the SMS text</div>
                </div>
            </div>
        </div>

        <!-- Headers -->
        <div style="margin-bottom:20px;">
            <h4 style="font-size:13px;color:var(--accent);margin-bottom:14px;font-weight:700;">Headers</h4>
            <div class="form-group">
                <label>Custom Headers (JSON object)</label>
                <textarea name="headers" id="gw_headers" class="code-area" style="min-height:80px;"><?= htmlspecialchars($editGw['headers'] ?? '{}') ?></textarea>
                <div class="hint-box">
                    <div class="hint-title">Example:</div>
                    <code>{"Content-Type":"application/json","User-Agent":"okhttp/4.12.0","Accept":"application/json"}</code>
                    <br><br>These headers are sent with every request to this gateway. <strong>Content-Type is auto-set</strong> based on Body Type above, so you usually don't need to add it here.
                </div>
            </div>
        </div>

        <!-- Body Template -->
        <div style="margin-bottom:20px;">
            <h4 style="font-size:13px;color:var(--accent);margin-bottom:14px;font-weight:700;">Request Body Template</h4>
            <div class="form-group">
                <label>Body Template (JSON)</label>
                <textarea name="body_template" id="gw_body_template" class="code-area" style="min-height:120px;"><?= htmlspecialchars($editGw['body_template'] ?? '{}') ?></textarea>
                <div class="hint-box">
                    <div class="hint-title">What is Body Template?</div>
                    This is the exact JSON structure the target API expects in the request body. Write it like a normal JSON object. The system will automatically replace the values for your <strong>Phone Param</strong> and <strong>Message Param</strong> keys with the actual phone number and SMS text when sending.<br><br>
                    <div class="hint-title">Example: Your cURL sends this body:</div>
                    <code>{"hash":"gOYywZZiheI","phone":"0188123456","otp":"","token":"","version":"2.0.3"}</code><br><br>
                    Your Body Template should be the <strong>same structure</strong> but with <strong>placeholder values</strong>:<br>
                    <code>{"hash":"","phone":"","otp":"","token":"","version":"2.0.3"}</code><br><br>
                    The system sees <code>phone</code> (your Phone Param) and fills it with the real number. It sees <code>hash</code> (your Message Param) and fills it with the real SMS text. All other fields like <code>otp</code>, <code>token</code>, <code>version</code> keep their static values as-is.<br><br>
                    <div class="hint-title">Quick Rules:</div>
                    - If the API only needs phone + message with no other fields, just leave as <code>{}</code><br>
                    - Put ALL fields from the cURL body here, including static ones (version, deviceToken, etc.)<br>
                    - The <strong>Phone Param</strong> and <strong>Message Param</strong> names above MUST match the keys in this JSON<br>
                    - For <code>"version":"2.0.3"</code> type fields — just keep them as-is, they won't be touched
                </div>
            </div>

            <!-- Live Preview Button -->
            <div style="margin-bottom:16px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="previewRequest()" id="previewBtn">Preview What Will Be Sent</button>
            </div>
            <div id="requestPreview" style="display:none;margin-bottom:16px;">
                <div class="card" style="padding:16px;background:#060609;border:1px solid var(--border);">
                    <h4 style="font-size:12px;color:var(--accent);margin-bottom:12px;font-weight:700;">Request Preview (with test data: phone=01700000000, msg="Test message here")</h4>
                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">URL & Method</div>
                        <div id="previewUrl" style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2);"></div>
                    </div>
                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Headers</div>
                        <div id="previewHeaders" style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2);white-space:pre-wrap;"></div>
                    </div>
                    <div>
                        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Body</div>
                        <div id="previewBody" style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text2);white-space:pre-wrap;word-break:break-all;"></div>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Extra Static Params (JSON, optional)</label>
                    <textarea name="extra_params" id="gw_extra_params" class="code-area" style="min-height:80px;"><?= htmlspecialchars($editGw['extra_params'] ?? '{}') ?></textarea>
                    <div class="hint-box">
                        <div class="hint-title">These are merged into the body on every request:</div>
                        <code>{"version":"2.0.3","deviceToken":"abc123"}</code><br>
                        Use this for values that are always the same (not phone/message).<br>
                        <strong>Note:</strong> If you already put static fields in Body Template above, you don't need to duplicate them here.
                    </div>
                </div>
                <div class="form-group">
                    <label>Response Check Config (JSON, optional)</label>
                    <textarea name="response_check" class="code-area" style="min-height:80px;"><?= htmlspecialchars($editGw['response_check'] ?? '{}') ?></textarea>
                    <div class="hint-box">
                        <div class="hint-title">Tell the system how to check if the API succeeded:</div>
                        <code>{"status_field":"status","success_values":["success",true],"code_field":"code","error_field":"response.message"}</code><br><br>
                        - <code>status_field</code>: Which key holds the status<br>
                        - <code>success_values</code>: What values mean success<br>
                        - <code>code_field</code>: Which key holds HTTP-like code<br>
                        - <code>error_field</code>: Where to find error msg (supports <code>response.message</code> nested paths)<br><br>
                        If left empty, smart auto-detection is used.
                    </div>
                </div>
            </div>
        </div>

        <!-- Options -->
        <div style="margin-bottom:20px;">
            <h4 style="font-size:13px;color:var(--accent);margin-bottom:14px;font-weight:700;">Options</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Timeout (seconds)</label>
                    <input type="number" name="timeout" class="form-control" value="<?= $editGw['timeout'] ?? 15 ?>" min="5" max="60">
                </div>
                <div class="form-group">
                    <label>Priority (higher = preferred)</label>
                    <input type="number" name="priority" class="form-control" value="<?= $editGw['priority'] ?? 0 ?>" min="0" max="100">
                </div>
                <div class="form-group">
                    <label>Success HTTP Codes (JSON array)</label>
                    <input type="text" name="success_http_codes" class="form-control mono" value="<?= htmlspecialchars($editGw['success_http_codes'] ?? '[200,201,202]') ?>">
                </div>
                <div class="form-group">
                    <label>Settings</label>
                    <div style="display:flex;gap:24px;padding-top:6px;flex-wrap:wrap;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="proxy_enabled" value="1" <?= ($editGw['proxy_enabled'] ?? 0) ? 'checked' : '' ?>>
                            <span>Use Proxy</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_default" value="1" <?= ($editGw['is_default'] ?? 0) ? 'checked' : '' ?>>
                            <span>Default Gateway</span>
                        </label>
                    </div>
                </div>
            </div>
            <?php if ($editGw): ?>
            <div class="form-group" style="max-width:200px;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $editGw['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $editGw['status']==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="submit" class="btn btn-primary" id="gwSubmitBtn"><?= $editGw ? 'Save Changes' : 'Add Gateway' ?></button>
            <?php if ($editGw): ?>
            <button type="button" class="btn btn-secondary" onclick="testGateway(<?= $editGw['id'] ?>)">Test Gateway</button>
            <?php endif; ?>
            <a href="/gateways.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<!-- Test Gateway Result -->
<div id="testResultCard" class="card" style="display:none;">
    <div class="card-head">
        <h3>Gateway Test Result</h3>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('testResultCard').style.display='none'">Close</button>
    </div>
    <div id="testResultContent" class="gw-test-result"></div>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- ======= GATEWAYS LIST ======= -->
<div class="card fade-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>URL</th><th>Method</th>
                    <th>Body</th><th>Phone</th><th>Msg</th>
                    <th>Proxy</th><th>Default</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($gateways)): ?>
                <tr><td colspan="11" class="empty-row">No gateways configured. <a href="/gateways.php?action=new">Add one</a></td></tr>
            <?php else: foreach ($gateways as $g): ?>
                <tr>
                    <td class="mono muted"><?= $g['id'] ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($g['name']) ?></td>
                    <td class="mono muted" style="font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($g['url']) ?>"><?= htmlspecialchars(mb_substr($g['url'],0,45)) ?></td>
                    <td><span class="badge badge-<?= $g['method']==='GET'?'active':($g['method']==='BOTH'?'pending':'success') ?>"><?= $g['method'] ?></span></td>
                    <td class="mono" style="font-size:11px;"><?= strtoupper($g['body_type']) ?></td>
                    <td class="mono" style="font-size:11px;"><?= htmlspecialchars($g['param_phone']) ?></td>
                    <td class="mono" style="font-size:11px;"><?= htmlspecialchars($g['param_message']) ?></td>
                    <td><?= $g['proxy_enabled'] ? '<span class="badge badge-active">ON</span>' : '<span class="badge badge-expired">OFF</span>' ?></td>
                    <td><?= $g['is_default'] ? '<span style="color:var(--accent);font-weight:700;">YES</span>' : '-' ?></td>
                    <td><span class="badge badge-<?= $g['status'] ?>"><?= $g['status'] ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="/gateways.php?action=edit&id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <button class="btn btn-secondary btn-sm" onclick="testGateway(<?= $g['id'] ?>)">Test</button>
                            <button class="btn btn-danger btn-sm" onclick="ajaxDeleteGw(<?= $g['id'] ?>)">Del</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Test Gateway Result -->
<div id="testResultCard" class="card" style="display:none;">
    <div class="card-head">
        <h3>Gateway Test Result</h3>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('testResultCard').style.display='none'">Close</button>
    </div>
    <div id="testResultContent" class="gw-test-result"></div>
</div>
<?php endif; ?>

</div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function parseCurl() {
    const curlText = document.getElementById('curlInput').value;
    if (!curlText.trim()) { showToast('Paste a curl command first', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'parse_curl');
    formData.append('curl_text', curlText);

    fetch('/gateways.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        if (data.success && data.parsed) {
            const p = data.parsed;
            document.getElementById('gw_url').value = p.url || '';
            document.getElementById('gw_method').value = p.method || 'POST';
            document.getElementById('gw_body_type').value = p.body_type || 'json';
            if (p.headers && Object.keys(p.headers).length > 0) {
                // Remove Content-Type from custom headers (system auto-sets it based on Body Type)
                const filteredHeaders = {};
                for (const [k, v] of Object.entries(p.headers)) {
                    if (k.toLowerCase() !== 'content-type') {
                        filteredHeaders[k] = v;
                    }
                }
                document.getElementById('gw_headers').value = JSON.stringify(filteredHeaders, null, 2);
            }
            if (p.body && Object.keys(p.body).length > 0) {
                // Put the full body as template (system will auto-fill phone & message params)
                document.getElementById('gw_body_template').value = JSON.stringify(p.body, null, 2);
                // Auto-detect phone and message param names from the body keys
                const body = p.body;
                for (const key of Object.keys(body)) {
                    const val = String(body[key]).toLowerCase();
                    const keyLower = key.toLowerCase();
                    if (keyLower.includes('phone') || keyLower.includes('mobile') || keyLower.includes('number') || val.includes('018') || val.includes('017')) {
                        document.getElementById('gw_param_phone').value = key;
                    }
                    if (keyLower.includes('hash') || keyLower.includes('msg') || keyLower.includes('message') || keyLower.includes('text') || val === 'msg' || val.includes('msg')) {
                        document.getElementById('gw_param_message').value = key;
                    }
                }
            }
            showToast('cURL parsed successfully! Review and adjust param names.', 'success');
        } else {
            showToast('Could not parse cURL command', 'error');
        }
    }).catch(() => showToast('Parse failed', 'error'));
}

function previewRequest() {
    const btn = document.getElementById('previewBtn');
    const orig = btn.textContent;
    btn.textContent = 'Generating preview...'; btn.disabled = true;

    const form = document.getElementById('gatewayForm');
    const formData = new FormData(form);
    formData.set('action', 'preview_request');

    fetch('/gateways.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        btn.textContent = orig; btn.disabled = false;
        if (data.success) {
            document.getElementById('previewUrl').textContent = data.method + ' ' + data.url;
            document.getElementById('previewHeaders').textContent = data.headers.join('\n');
            document.getElementById('previewBody').textContent = data.body;
            document.getElementById('requestPreview').style.display = 'block';
            showToast('Preview generated! Check what will be sent.', 'success');
        } else {
            showToast('Failed to generate preview. Check your JSON fields.', 'error');
        }
    }).catch(() => {
        btn.textContent = orig; btn.disabled = false;
        showToast('Preview request failed', 'error');
    });
}

function submitGatewayForm(e) {
    e.preventDefault();
    const form = document.getElementById('gatewayForm');
    const btn = document.getElementById('gwSubmitBtn');
    const origText = btn.textContent;
    btn.textContent = 'Saving...'; btn.disabled = true;

    const formData = new FormData(form);
    fetch('/gateways.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        btn.textContent = origText; btn.disabled = false;
        showToast(data.message || 'Done', data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => window.location.href = '/gateways.php', 800);
    }).catch(() => {
        btn.textContent = origText; btn.disabled = false;
        showToast('Request failed', 'error');
    });
    return false;
}

function ajaxDeleteGw(id) {
    if (!confirm('Delete this gateway?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    fetch('/gateways.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    }).then(r => r.json()).then(data => {
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 500);
    });
}

function testGateway(gwId) {
    const phone = prompt('Test phone number:', '01700000000');
    if (!phone) return;
    const msg = 'Test SMS from Portal';

    const formData = new FormData();
    formData.append('action', 'test_gateway');
    formData.append('gateway_id', gwId);
    formData.append('test_phone', phone);
    formData.append('test_msg', msg);

    showToast('Testing gateway...', 'info');

    fetch('/gateways.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        const card = document.getElementById('testResultCard');
        const content = document.getElementById('testResultContent');
        card.style.display = 'block';
        let output = '';
        output += 'HTTP Code: ' + (data.http_code || 'N/A') + '\n';
        output += 'cURL Error: ' + (data.error || 'None') + '\n\n';
        if (data.sent_url) {
            output += '--- Request URL ---\n' + data.sent_url + '\n\n';
        }
        if (data.sent_headers && data.sent_headers.length > 0) {
            output += '--- Request Headers ---\n' + data.sent_headers.join('\n') + '\n\n';
        }
        if (data.sent_body) {
            output += '--- Sent Body ---\n' + data.sent_body + '\n\n';
        }
        output += '--- Response ---\n' + (data.body || 'No response');
        content.textContent = output;
        showToast(data.error ? 'Gateway test had errors' : 'Gateway test complete', data.error ? 'error' : 'success');
    }).catch(err => {
        showToast('Test request failed', 'error');
    });
}
</script>
</body>
</html>
