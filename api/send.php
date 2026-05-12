<?php
// ============================================
// PUBLIC SMS API ENDPOINT (v2.1)
// Supports both GET and POST
// GET:  /send.php?key=KEY&phone=NUMBER&msg=MESSAGE
// POST: /send.php  body: {key, phone, msg}
//
// Routes through configured gateways with proxy support
// PROXY FALLBACK: If 1st proxy fails, automatically tries next proxy
// Keeps cycling through ALL available proxies until one succeeds
// Falls back to no-proxy as last resort
// Always returns system-formatted response
// Checks API response body, not just HTTP status
// ============================================

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$ip = getClientIP();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$method = $_SERVER['REQUEST_METHOD'];

// Support both GET and POST
if ($method === 'GET') {
    $apiKey  = trim($_GET['key']   ?? '');
    $phone   = trim($_GET['phone'] ?? '');
    $smsBody = trim($_GET['msg']   ?? '');
} elseif ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
    } else {
        $input = $_POST;
    }
    $apiKey  = trim($input['key']   ?? '');
    $phone   = trim($input['phone'] ?? '');
    $smsBody = trim($input['msg']   ?? '');
} else {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed. Use GET or POST.', 'Owner' => APP_OWNER], 405);
}

// Validate inputs
if (empty($apiKey)) {
    jsonResponse(['status' => 'error', 'message' => 'API key is required', 'Owner' => APP_OWNER], 400);
}
if (empty($phone)) {
    jsonResponse(['status' => 'error', 'message' => 'Phone number is required', 'Owner' => APP_OWNER], 400);
}
if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid phone number format', 'Owner' => APP_OWNER], 400);
}
if (empty($smsBody)) {
    jsonResponse(['status' => 'error', 'message' => 'SMS message body (msg) is required', 'Owner' => APP_OWNER], 400);
}
if (mb_strlen($smsBody) > 1000) {
    jsonResponse(['status' => 'error', 'message' => 'SMS message too long (max 1000 chars)', 'Owner' => APP_OWNER], 400);
}

$db = getDB();

// ---- DEMO MODE (no DB) ----
if (!$db) {
    logSMS(['api_key'=>$apiKey,'owner'=>'demo','phone'=>$phone,'ip'=>$ip,'gateway'=>'demo','proxy'=>'none','status'=>'demo','msg'=>$smsBody]);
    jsonResponse([
        'status'           => 'success',
        'message'          => 'SMS sent successfully (demo mode)',
        'phone'            => $phone,
        'sms_body'         => $smsBody,
        'key_owner'        => 'demo_user',
        'gateway_used'     => 'demo',
        'points_used'      => 1,
        'points_remaining' => 99,
        'sent_at'          => date('Y-m-d H:i:s'),
        'Owner'            => APP_OWNER
    ]);
}

// ---- Look up API Key ----
$stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key = ? AND status = 'active'");
$stmt->execute([$apiKey]);
$keyData = $stmt->fetch();

if (!$keyData) {
    logSMS(['api_key'=>$apiKey,'owner'=>'UNKNOWN','phone'=>$phone,'ip'=>$ip,'gateway'=>'N/A','proxy'=>'none','status'=>'invalid_key','msg'=>'N/A']);
    logSystem('SECURITY', 'INVALID_KEY', "Key: $apiKey | Phone: $phone | IP: $ip");
    jsonResponse(['status' => 'error', 'message' => 'Invalid or inactive API key', 'Owner' => APP_OWNER], 401);
}

// Check expiry
if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
    $db->prepare("UPDATE api_keys SET status='expired' WHERE id=?")->execute([$keyData['id']]);
    jsonResponse(['status' => 'error', 'message' => 'API key has expired', 'Owner' => APP_OWNER], 401);
}

// Check IP whitelist
if (!empty($keyData['allowed_ips'])) {
    $allowed = json_decode($keyData['allowed_ips'], true);
    if (!empty($allowed) && !in_array($ip, $allowed)) {
        logSystem('SECURITY', 'IP_BLOCKED', "Key: $apiKey | IP: $ip not in whitelist");
        jsonResponse(['status' => 'error', 'message' => 'Your IP is not authorized for this key', 'Owner' => APP_OWNER], 403);
    }
}

// Reset daily counter if new day
$today = date('Y-m-d');
if ($keyData['last_reset_date'] !== $today) {
    $db->prepare("UPDATE api_keys SET daily_used=0, last_reset_date=? WHERE id=?")->execute([$today, $keyData['id']]);
    $keyData['daily_used'] = 0;
}

// Check daily limit
if ($keyData['daily_limit'] > 0 && $keyData['daily_used'] >= $keyData['daily_limit']) {
    jsonResponse([
        'status' => 'error',
        'message' => "Daily SMS limit ({$keyData['daily_limit']}) reached",
        'Owner' => APP_OWNER
    ], 429);
}

// Check monthly limit
if ($keyData['monthly_limit'] > 0 && $keyData['monthly_used'] >= $keyData['monthly_limit']) {
    jsonResponse([
        'status' => 'error',
        'message' => "Monthly SMS limit ({$keyData['monthly_limit']}) reached",
        'Owner' => APP_OWNER
    ], 429);
}

// Check points
$remaining = $keyData['total_points'] - $keyData['used_points'];
if ($remaining <= 0) {
    jsonResponse([
        'status' => 'error',
        'message' => 'Insufficient points',
        'points_remaining' => 0,
        'Owner' => APP_OWNER
    ], 402);
}

// ---- Apply Watermark ----
$watermark = $keyData['watermark'] ?? '';
if (!empty($watermark)) {
    $smsBody = $smsBody . ' ' . $watermark;
}

// ---- Get Gateway ----
$gateway = null;
$gatewayUsed = 'default';
$gatewayId = null;

if (!empty($keyData['assigned_gateway_id'])) {
    $gateway = getGatewayById($db, $keyData['assigned_gateway_id']);
}

if (!$gateway) {
    $gateway = getDefaultGateway($db);
}

// ---- Determine which gateway to use ----
// PROXY FALLBACK SYSTEM: If a proxy fails, try the next available proxy.
// Keeps cycling through all proxies until one succeeds or all are exhausted.
$proxyAttemptLog = []; // Track which proxies were tried

if ($gateway) {
    $gatewayUsed = $gateway['name'];
    $gatewayId = $gateway['id'];

    // Check if gateway method matches
    $gwMethod = $gateway['method'];
    if ($gwMethod !== 'BOTH' && $gwMethod !== $method) {
        $method = $gwMethod;
    }

    // ---- PROXY FALLBACK LOOP ----
    // Get ALL available proxies if proxy is enabled for this gateway
    $proxyUrl = null;
    $proxyUsed = 'none';
    $isSuccess = false;
    $apiCode = null;
    $apiMessage = null;
    $response = null;
    $usedProxy = null;

    if ($gateway['proxy_enabled']) {
        $allProxies = getAllAvailableProxies($db, $gatewayId);
        $totalProxies = count($allProxies);

        if ($totalProxies > 0) {
            logSystem('INFO', 'PROXY_FALLBACK_START', "Gateway: $gatewayUsed | $totalProxies proxies available | Phone: $phone");

            foreach ($allProxies as $idx => $proxy) {
                $proxyUrl = $proxy['proxy_url'];
                $proxyUsed = "proxy_{$proxy['id']}";
                $proxyAttemptLog[] = "proxy_{$proxy['id']}";

                logSystem('INFO', 'PROXY_ATTEMPT', "Gateway: $gatewayUsed | Trying proxy " . ($idx + 1) . "/$totalProxies (ID:{$proxy['id']}) | Phone: $phone");

                // Call the gateway with this proxy
                $response = callGateway($gateway, $phone, $smsBody, $proxyUrl);

                if ($response['error']) {
                    // cURL error — this proxy is bad, mark it failed and try next
                    markProxyFailed($db, $proxy['id']);
                    logSystem('WARNING', 'PROXY_FAILED_CURL', "Proxy ID:{$proxy['id']} | cURL Error: {$response['error']} | Trying next proxy...");
                    $proxyAttemptLog[count($proxyAttemptLog) - 1] .= " FAILED(curl:{$response['error']})";
                    continue; // Try next proxy
                }

                // Check the actual API response body
                $checkResult = checkGatewayResponse($gateway, $response['raw'], $response['http_code']);
                $isSuccess = $checkResult['is_success'];
                $apiCode = $checkResult['api_code'];
                $apiMessage = $checkResult['api_message'];

                if ($isSuccess) {
                    // This proxy worked!
                    markProxySuccess($db, $proxy['id']);
                    markProxyUsed($db, $proxy['id']);
                    $usedProxy = $proxy;
                    logSystem('INFO', 'PROXY_SUCCESS', "Proxy ID:{$proxy['id']} SUCCEEDED on attempt " . ($idx + 1) . "/$totalProxies | Phone: $phone");
                    $proxyAttemptLog[count($proxyAttemptLog) - 1] .= " OK";
                    break; // Success! Stop trying more proxies
                } else {
                    // API returned failure — mark proxy failed and try next
                    markProxyFailed($db, $proxy['id']);
                    $failReason = $apiMessage ?: "HTTP {$response['http_code']}/API code:$apiCode";
                    logSystem('WARNING', 'PROXY_FAILED_API', "Proxy ID:{$proxy['id']} | API failure: $failReason | Trying next proxy...");
                    $proxyAttemptLog[count($proxyAttemptLog) - 1] .= " FAILED(api:$failReason)";
                    continue; // Try next proxy
                }
            }

            // After trying all proxies — check if any succeeded
            if (!$isSuccess && $response) {
                // All proxies failed
                $triedList = implode(' -> ', $proxyAttemptLog);
                logSystem('ERROR', 'ALL_PROXIES_FAILED', "Gateway: $gatewayUsed | All $totalProxies proxies exhausted | Tried: $triedList | Phone: $phone");
                logSMS(['api_key'=>$apiKey,'owner'=>$keyData['owner_username'],'phone'=>$phone,'ip'=>$ip,'gateway'=>$gatewayUsed,'proxy'=>"all_failed($totalProxies)",'status'=>'all_proxies_failed','msg'=>$smsBody]);
                // Fall through — $response holds the last attempt's data, $isSuccess is false
            }
        } else {
            // No proxies available at all — try without proxy as last resort
            logSystem('WARNING', 'NO_PROXIES_AVAILABLE', "Gateway: $gatewayUsed | No active proxies found | Trying without proxy | Phone: $phone");
            $response = callGateway($gateway, $phone, $smsBody, null);
            $proxyUsed = 'none_no_proxies';

            if (!$response['error']) {
                $checkResult = checkGatewayResponse($gateway, $response['raw'], $response['http_code']);
                $isSuccess = $checkResult['is_success'];
                $apiCode = $checkResult['api_code'];
                $apiMessage = $checkResult['api_message'];
            }
        }
    } else {
        // Proxy not enabled for this gateway — direct call
        $response = callGateway($gateway, $phone, $smsBody, null);
        $proxyUsed = 'none';
    }

    // Handle cURL error (only possible if proxy not enabled or no proxies + direct call failed)
    if ($response && $response['error'] && !$isSuccess) {
        logSMS(['api_key'=>$apiKey,'owner'=>$keyData['owner_username'],'phone'=>$phone,'ip'=>$ip,'gateway'=>$gatewayUsed,'proxy'=>$proxyUsed,'status'=>'curl_error','msg'=>$smsBody]);
        jsonResponse(['status' => 'error', 'message' => 'Gateway unreachable: ' . $response['error'], 'Owner' => APP_OWNER], 503);
    }

    // If no response at all (shouldn't happen but safety check)
    if (!$response) {
        logSMS(['api_key'=>$apiKey,'owner'=>$keyData['owner_username'],'phone'=>$phone,'ip'=>$ip,'gateway'=>$gatewayUsed,'proxy'=>'none','status'=>'no_response','msg'=>$smsBody]);
        jsonResponse(['status' => 'error', 'message' => 'No gateway response received', 'Owner' => APP_OWNER], 503);
    }

} else {
    // ---- Fallback: Use master API URL from .env ----
    $gatewayUsed = 'default';
    $gatewayId = null;

    // Try with proxy rotation first, then without
    $allProxies = getAllAvailableProxies($db);
    $isSuccess = false;
    $apiCode = null;
    $apiMessage = null;
    $response = null;
    $proxyUsed = 'none';

    if (!empty($allProxies)) {
        // Try each proxy with the default gateway
        foreach ($allProxies as $idx => $proxy) {
            $masterUrl = MASTER_API_URL . '?hash=' . urlencode($smsBody) . '&phone=' . urlencode($phone);
            $proxyUsed = "proxy_{$proxy['id']}";

            logSystem('INFO', 'DEFAULT_PROXY_ATTEMPT', "Default gateway | Trying proxy " . ($idx + 1) . "/" . count($allProxies) . " (ID:{$proxy['id']}) | Phone: $phone");

            $ch = curl_init($masterUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'SMSPortal/' . APP_VERSION,
                CURLOPT_PROXY          => $proxy['proxy_url'],
            ]);
            // Detect proxy type
            if (strpos($proxy['proxy_url'], 'socks5://') === 0) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            } elseif (strpos($proxy['proxy_url'], 'socks4://') === 0) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
            }

            $rawResponse = curl_exec($ch);
            $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError   = curl_error($ch);
            curl_close($ch);

            if ($curlError || !$rawResponse) {
                markProxyFailed($db, $proxy['id']);
                logSystem('WARNING', 'DEFAULT_PROXY_FAILED', "Proxy ID:{$proxy['id']} | cURL: $curlError | Trying next...");
                continue;
            }

            // Smart check for default gateway
            $jsonResp = json_decode($rawResponse, true);
            $isSuccess = ($httpCode == 200 || $httpCode == 201);
            $apiCode = $httpCode;
            $apiMessage = null;

            if ($jsonResp) {
                if (isset($jsonResp['status'])) {
                    $sv = $jsonResp['status'];
                    if ($sv === false || $sv === 'error' || $sv === 'failed') {
                        $isSuccess = false;
                    } elseif ($sv === true || $sv === 'success') {
                        $isSuccess = true;
                    }
                }
                if (isset($jsonResp['code'])) {
                    $apiCode = (int)$jsonResp['code'];
                    if ($apiCode >= 400) $isSuccess = false;
                }
                if (isset($jsonResp['response']['error']) && $jsonResp['response']['error'] === true) {
                    $isSuccess = false;
                    $apiMessage = $jsonResp['response']['message'] ?? null;
                }
                if (isset($jsonResp['message']) && !$apiMessage) {
                    $apiMessage = $jsonResp['message'];
                }
            }

            if ($isSuccess) {
                markProxySuccess($db, $proxy['id']);
                markProxyUsed($db, $proxy['id']);
                logSystem('INFO', 'DEFAULT_PROXY_SUCCESS', "Proxy ID:{$proxy['id']} SUCCEEDED | Phone: $phone");
                $response = ['raw' => $rawResponse, 'http_code' => $httpCode, 'error' => null];
                break;
            } else {
                markProxyFailed($db, $proxy['id']);
                logSystem('WARNING', 'DEFAULT_PROXY_API_FAIL', "Proxy ID:{$proxy['id']} | API failure | Trying next...");
                $response = ['raw' => $rawResponse, 'http_code' => $httpCode, 'error' => null];
                continue;
            }
        }

        // If all proxies failed, try without proxy as final fallback
        if (!$isSuccess) {
            logSystem('WARNING', 'DEFAULT_ALL_PROXIES_FAILED', "All proxies failed for default gateway | Trying direct (no proxy) | Phone: $phone");
        }
    }

    // If no proxies available OR all proxies failed, try direct (no proxy)
    if (!$isSuccess) {
        $masterUrl = MASTER_API_URL . '?hash=' . urlencode($smsBody) . '&phone=' . urlencode($phone);
        $proxyUsed = 'none';

        $ch = curl_init($masterUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'SMSPortal/' . APP_VERSION,
        ]);
        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError || !$rawResponse) {
            logSMS(['api_key'=>$apiKey,'owner'=>$keyData['owner_username'],'phone'=>$phone,'ip'=>$ip,'gateway'=>$gatewayUsed,'proxy'=>'none','status'=>'curl_error','msg'=>$smsBody]);
            jsonResponse(['status' => 'error', 'message' => 'Gateway unreachable: ' . $curlError, 'Owner' => APP_OWNER], 503);
        }

        // Smart check for default gateway
        $jsonResp = json_decode($rawResponse, true);
        $isSuccess = ($httpCode == 200 || $httpCode == 201);
        $apiCode = $httpCode;
        $apiMessage = null;

        if ($jsonResp) {
            if (isset($jsonResp['status'])) {
                $sv = $jsonResp['status'];
                if ($sv === false || $sv === 'error' || $sv === 'failed') {
                    $isSuccess = false;
                } elseif ($sv === true || $sv === 'success') {
                    $isSuccess = true;
                }
            }
            if (isset($jsonResp['code'])) {
                $apiCode = (int)$jsonResp['code'];
                if ($apiCode >= 400) $isSuccess = false;
            }
            if (isset($jsonResp['response']['error']) && $jsonResp['response']['error'] === true) {
                $isSuccess = false;
                $apiMessage = $jsonResp['response']['message'] ?? null;
            }
            if (isset($jsonResp['message']) && !$apiMessage) {
                $apiMessage = $jsonResp['message'];
            }
        }

        $response = ['raw' => $rawResponse, 'http_code' => $httpCode, 'error' => null];
    }
}

// ---- Update Counters ----
if ($isSuccess) {
    $db->prepare("
        UPDATE api_keys
        SET used_points = used_points + 1,
            daily_used  = daily_used + 1,
            monthly_used = monthly_used + 1,
            last_used_at = NOW()
        WHERE id = ?
    ")->execute([$keyData['id']]);

    // Log points transaction
    $newBalance = $remaining - 1;
    $db->prepare("
        INSERT INTO points_transactions (api_key_id, api_key, transaction_type, points, balance_after, description)
        VALUES (?, ?, 'debit', 1, ?, ?)
    ")->execute([$keyData['id'], $apiKey, $newBalance, "SMS to $phone: " . mb_substr($smsBody, 0, 60)]);
}

// ---- Log SMS ----
$logData = [
    'api_key' => $apiKey,
    'owner'   => $keyData['owner_username'],
    'phone'   => $phone,
    'ip'      => $ip,
    'gateway' => $gatewayUsed,
    'proxy'   => $proxyUsed ?? 'none',
    'status'  => $isSuccess ? 'success' : 'failed',
    'msg'     => $smsBody,
];
logSMS($logData);

// DB log
try {
    $db->prepare("
        INSERT INTO sms_logs (api_key_id, api_key, key_owner, phone_number, message_hash, ip_address, user_agent, gateway_used, gateway_id, proxy_used, response_status, response_body, api_response_code, points_deducted, status, error_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $keyData['id'], $apiKey, $keyData['owner_username'],
        $phone, $smsBody, $ip, substr($userAgent, 0, 500),
        $gatewayUsed, $gatewayId, $proxyUsed ?? 'none',
        $response['http_code'] ?? 0, substr($response['raw'] ?? '', 0, 2000),
        $apiCode ?? $response['http_code'],
        $isSuccess ? 1 : 0,
        $isSuccess ? 'success' : 'failed',
        $apiMessage ? substr($apiMessage, 0, 500) : null
    ]);
} catch (Exception $e) {}

// ---- Build Clean System Response (always our format, never raw API response) ----
$freshPoints = $db->query("SELECT used_points, total_points FROM api_keys WHERE id={$keyData['id']}")->fetch();
$pointsRemaining = ($freshPoints['total_points'] ?? $keyData['total_points']) - ($freshPoints['used_points'] ?? $keyData['used_points']);

$systemResponse = buildSystemResponse(
    $isSuccess,
    $phone,
    $smsBody,
    $keyData['owner_username'],
    $isSuccess ? 1 : 0,
    $pointsRemaining,
    $gatewayUsed,
    $apiMessage
);

// Add proxy info to the response
$systemResponse['proxy_used'] = $proxyUsed ?? 'none';

if ($isSuccess) {
    jsonResponse($systemResponse, 200);
} else {
    // Determine proper HTTP code based on API response body
    $returnCode = 500;
    if (isset($apiCode)) {
        if ($apiCode == 429) $returnCode = 429;
        elseif ($apiCode == 401) $returnCode = 401;
        elseif ($apiCode == 403) $returnCode = 403;
        elseif ($apiCode >= 400 && $apiCode < 500) $returnCode = $apiCode;
    }
    jsonResponse($systemResponse, $returnCode);
}
