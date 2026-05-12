<?php
// ============================================
// ENVIRONMENT & CONFIG LOADER (v2.0)
// ============================================

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

define('MASTER_API_URL', getenv('MASTER_API_URL') ?: 'https://hsinfogdc.ct.ws/api.php');
define('APP_OWNER', getenv('APP_OWNER') ?: 'noooob_developer');
define('APP_NAME', getenv('APP_NAME') ?: 'SMS Gateway Portal');
define('APP_VERSION', getenv('APP_VERSION') ?: '2.0.0');
define('API_KEY_PREFIX', getenv('API_KEY_PREFIX') ?: 'NBD');
define('LOG_DIR', __DIR__ . '/../' . (getenv('LOG_DIR') ?: 'logs'));
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'Admin@1234');
define('SESSION_SECRET', getenv('ADMIN_SESSION_SECRET') ?: 'secret');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dhaka');

// ============================================
// DATABASE CONNECTION
// ============================================
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = getenv('DB_HOST') ?: 'sql213.infinityfree.com';
    $name = getenv('DB_NAME') ?: 'if0_39891100_sms_portal';
    $user = getenv('DB_USER') ?: 'if0_39891100';
    $pass = getenv('DB_PASS') ?: 'D9m1DP0PAz2sj';
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        return null;
    }
    return $pdo;
}

// ============================================
// FILE LOGGER
// ============================================
function logSMS($data) {
    if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);
    $file = LOG_DIR . '/sms_' . date('Y-m-d') . '.log';
    $line = date('Y-m-d H:i:s') . ' | '
        . 'KEY:' . ($data['api_key'] ?? '-') . ' | '
        . 'OWNER:' . ($data['owner'] ?? '-') . ' | '
        . 'PHONE:' . ($data['phone'] ?? '-') . ' | '
        . 'IP:' . ($data['ip'] ?? '-') . ' | '
        . 'GATEWAY:' . ($data['gateway'] ?? '-') . ' | '
        . 'PROXY:' . ($data['proxy'] ?? '-') . ' | '
        . 'STATUS:' . ($data['status'] ?? '-') . ' | '
        . 'MSG:' . mb_substr($data['msg'] ?? '-', 0, 80)
        . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function logSystem($type, $action, $desc, $ip = null) {
    if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);
    $file = LOG_DIR . '/system_' . date('Y-m-d') . '.log';
    $ip = $ip ?: getClientIP();
    $line = date('Y-m-d H:i:s') . " | [$type] | $action | $desc | IP:$ip" . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

    // Also log to DB if available
    $db = getDB();
    if ($db) {
        try {
            $db->prepare("INSERT INTO system_logs (log_type, action, description, ip_address) VALUES (?,?,?,?)")
               ->execute([$type, $action, mb_substr($desc, 0, 500), $ip ?: 'cli']);
        } catch (Exception $e) {}
    }
}

// ============================================
// IP HELPER (FIXED - no more ::1)
// ============================================
function getClientIP() {
    // Check Cloudflare first
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    // Check X-Forwarded-For
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '::1') return $ip;
        }
    }
    // Check X-Real-IP
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '::1') return $ip;
    }
    // Check REMOTE_ADDR but avoid ::1
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($ip === '::1' || $ip === '127.0.0.1') {
            // Try to get the real client IP from other sources
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $cip = trim(explode(',', $_SERVER['HTTP_CLIENT_IP'])[0]);
                if (filter_var($cip, FILTER_VALIDATE_IP)) return $cip;
            }
            return '127.0.0.1'; // Standardize localhost
        }
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

// ============================================
// API KEY GENERATOR (Multiple Format Styles)
// ============================================
function generateApiKey($format = 'xxx-xxxxxx-xxxxxx') {
    $prefix = API_KEY_PREFIX;
    $formats = [
        'xxx-xxxxxx-xxxxxx' => function($p) { return $p . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3))); },
        'xxx-xxx-xxx'       => function($p) { return $p . '-' . strtoupper(bin2hex(random_bytes(1))) . '-' . strtoupper(bin2hex(random_bytes(1))); },
        'xxxx-xxxx-xxxx-xxxx' => function($p) { return $p . strtoupper(bin2hex(random_bytes(1))) . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))); },
        'xxx-xxxx-xxxx'     => function($p) { return $p . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))); },
        'xxxxxx-xxxxxx-xxxxxx' => function($p) { return strtoupper(bin2hex(random_bytes(3))) . $p . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3))); },
        'xxx-xxxx-xxxx-xxxx' => function($p) { return $p . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))); },
    ];

    if (isset($formats[$format])) {
        return $formats[$format]($prefix);
    }
    // Default format
    return $formats['xxx-xxxxxx-xxxxxx']($prefix);
}

function getKeyFormats() {
    return [
        'xxx-xxxxxx-xxxxxx'       => 'NBD-1A2B3C-4D5E6F',
        'xxx-xxx-xxx'             => 'NBD-1A-2B',
        'xxxx-xxxx-xxxx-xxxx'     => 'NBD1-A2B3-C4D5-E6F7',
        'xxx-xxxx-xxxx'           => 'NBD-1A2B-3C4D',
        'xxxxxx-xxxxxx-xxxxxx'    => '1A2B3C-NBD4-D5E6F',
        'xxx-xxxx-xxxx-xxxx'      => 'NBD-1A2B-3C4D-5E6F',
    ];
}

// ============================================
// JSON RESPONSE HELPER
// ============================================
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// ADMIN AUTH CHECK
// ============================================
function requireAdmin() {
    session_start();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /login.php');
        exit;
    }
}

// ============================================
// GATEWAY HELPER FUNCTIONS
// ============================================
function getDefaultGateway($db) {
    if (!$db) return null;
    $gw = $db->query("SELECT * FROM gateways WHERE is_default=1 AND status='active' LIMIT 1")->fetch();
    if (!$gw) {
        $gw = $db->query("SELECT * FROM gateways WHERE status='active' ORDER BY priority DESC LIMIT 1")->fetch();
    }
    return $gw;
}

function getGatewayById($db, $id) {
    if (!$db || !$id) return null;
    $stmt = $db->prepare("SELECT * FROM gateways WHERE id=? AND status='active'");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function callGateway($gateway, $phone, $message, $proxyUrl = null) {
    $method = $gateway['method'] === 'BOTH' ? 'POST' : $gateway['method'];
    $url = $gateway['url'];
    $headers = json_decode($gateway['headers'] ?? '{}', true) ?: [];
    $bodyType = $gateway['body_type'] ?? 'json';
    $bodyTemplate = json_decode($gateway['body_template'] ?? '{}', true) ?: [];
    $paramPhone = $gateway['param_phone'] ?? 'phone';
    $paramMessage = $gateway['param_message'] ?? 'hash';
    $extraParams = json_decode($gateway['extra_params'] ?? '{}', true) ?: [];
    $timeout = $gateway['timeout'] ?? 15;

    // Build the request
    if ($method === 'GET') {
        $queryParams = array_merge($extraParams, [
            $paramPhone => $phone,
            $paramMessage => $message,
        ]);
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($queryParams);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);

    $debugBody = null;

    // Set method and build body
    if ($method === 'POST') {
        // Build body data: start with template, merge extras, then set phone/message
        $bodyData = is_array($bodyTemplate) ? $bodyTemplate : [];
        if (is_array($extraParams)) {
            foreach ($extraParams as $k => $v) {
                $bodyData[$k] = $v;
            }
        }
        $bodyData[$paramPhone] = $phone;
        $bodyData[$paramMessage] = $message;

        if ($bodyType === 'json') {
            // CRITICAL: Must send as JSON STRING, NOT as an array.
            // Passing an array to CURLOPT_POSTFIELDS makes curl send form-encoded data.
            $jsonBody = json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $debugBody = $jsonBody;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            // FORCE Content-Type to application/json — remove any conflicting one from custom headers
            $headers = array_change_key_case($headers, CASE_LOWER);
            unset($headers['content-type']);
            $headers['Content-Type'] = 'application/json';
        } elseif ($bodyType === 'form') {
            $formBody = http_build_query($bodyData);
            $debugBody = $formBody;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $formBody);
            // FORCE Content-Type for form-encoded
            $headers = array_change_key_case($headers, CASE_LOWER);
            unset($headers['content-type']);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            // query type - append to URL as query string
            $queryString = http_build_query($bodyData);
            $debugBody = $queryString;
            $url .= (strpos($url, '?') !== false ? '&' : '?') . $queryString;
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_POST, true);
    }

    // Build final header lines — normalize keys to preserve original casing
    $finalHeaders = [];
    $seen = [];
    foreach ($headers as $k => $v) {
        $lk = strtolower($k);
        if (!isset($seen[$lk])) {
            $seen[$lk] = true;
            $finalHeaders[] = "$k: $v";
        }
    }
    // Safety: ensure Content-Type is always present
    if (!isset($seen['content-type'])) {
        $finalHeaders[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

    // Set proxy
    if (!empty($proxyUrl)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
        if (strpos($proxyUrl, 'socks5://') === 0) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        } elseif (strpos($proxyUrl, 'socks4://') === 0) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
        }
    }

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);

    return [
        'raw'        => $rawResponse,
        'http_code'  => $httpCode,
        'error'      => $curlError,
        'debug_body' => $debugBody,
        'debug_headers' => $finalHeaders ?? [],
        'debug_url'  => $url,
    ];
}

// ============================================
// RESPONSE CHECKING (checks API body, not just HTTP code)
// ============================================
function checkGatewayResponse($gateway, $rawResponse, $httpCode) {
    $result = [
        'is_success'   => false,
        'api_code'     => $httpCode,
        'api_message'  => null,
    ];

    // Parse response check config
    $responseCheck = json_decode($gateway['response_check'] ?? '{}', true) ?: [];
    $successHttpCodes = json_decode($gateway['success_http_codes'] ?? '[200,201,202]', true) ?: [200, 201, 202];

    // First check HTTP code
    $httpSuccess = in_array($httpCode, $successHttpCodes);

    // Try to decode JSON response
    $jsonResp = json_decode($rawResponse, true);

    if ($jsonResp && !empty($responseCheck)) {
        // Check specific field for status
        $statusField = $responseCheck['status_field'] ?? null;
        $successValues = $responseCheck['success_values'] ?? ['success', true, 200];
        $codeField = $responseCheck['code_field'] ?? null;
        $errorField = $responseCheck['error_field'] ?? null;

        // Check the status field
        if ($statusField && isset($jsonResp[$statusField])) {
            $statusValue = $jsonResp[$statusField];
            if (in_array($statusValue, $successValues, true)) {
                $result['is_success'] = true;
            } else {
                $result['is_success'] = false;
            }
        } elseif ($httpSuccess) {
            // No status field check, rely on HTTP code
            $result['is_success'] = true;
        }

        // Get API code from body
        if ($codeField && isset($jsonResp[$codeField])) {
            $result['api_code'] = (int)$jsonResp[$codeField];
        }

        // Get error message
        if ($errorField) {
            // Support nested fields like "response.message"
            $parts = explode('.', $errorField);
            $val = $jsonResp;
            foreach ($parts as $part) {
                if (isset($val[$part])) {
                    $val = $val[$part];
                } else {
                    $val = null;
                    break;
                }
            }
            if ($val) $result['api_message'] = $val;
        }
    } else {
        // No response check config or not JSON
        // Smart detection: check common patterns
        if ($jsonResp) {
            // Check for common status fields
            if (isset($jsonResp['status'])) {
                $sv = $jsonResp['status'];
                if ($sv === true || $sv === 'success' || $sv === 200 || $sv === 'ok' || $sv === 1) {
                    $result['is_success'] = true;
                } elseif ($sv === false || $sv === 'failed' || $sv === 'error' || $sv === 'fail') {
                    $result['is_success'] = false;
                } else {
                    $result['is_success'] = $httpSuccess;
                }
            } elseif (isset($jsonResp['code'])) {
                $result['api_code'] = (int)$jsonResp['code'];
                $result['is_success'] = in_array((int)$jsonResp['code'], $successHttpCodes);
            } elseif (isset($jsonResp['response']['error'])) {
                $result['is_success'] = ($jsonResp['response']['error'] === false || $jsonResp['response']['error'] === 0);
                if (isset($jsonResp['response']['message'])) {
                    $result['api_message'] = $jsonResp['response']['message'];
                }
            } elseif (isset($jsonResp['error']) && $jsonResp['error'] === true) {
                $result['is_success'] = false;
                if (isset($jsonResp['message'])) $result['api_message'] = $jsonResp['message'];
            } else {
                $result['is_success'] = $httpSuccess;
            }

            // Check for code field in various locations
            if (isset($jsonResp['code'])) {
                $result['api_code'] = (int)$jsonResp['code'];
            }
            if (!$result['api_message']) {
                if (isset($jsonResp['message'])) $result['api_message'] = $jsonResp['message'];
                elseif (isset($jsonResp['msg'])) $result['api_message'] = $jsonResp['msg'];
            }
        } else {
            $result['is_success'] = $httpSuccess;
        }
    }

    return $result;
}

// ============================================
// PROXY HELPER FUNCTIONS
// ============================================
function getAvailableProxy($db, $gatewayId = null) {
    if (!$db) return null;

    // Check if proxy is enabled for this gateway
    if ($gatewayId) {
        $gw = $db->prepare("SELECT proxy_enabled FROM gateways WHERE id=?");
        $gw->execute([$gatewayId]);
        $gwData = $gw->fetch();
        if (!$gwData || !$gwData['proxy_enabled']) return null;
    }

    $now = date('Y-m-d H:i:s');
    // Get an active proxy that's not in cooldown
    $stmt = $db->prepare("SELECT * FROM proxies WHERE status='active' AND (cooldown_until IS NULL OR cooldown_until <= ?) ORDER BY used_count ASC, id ASC LIMIT 1");
    $stmt->execute([$now]);
    $proxy = $stmt->fetch();

    if (!$proxy) {
        // Try to activate proxies that came out of cooldown
        $db->exec("UPDATE proxies SET status='active' WHERE status='cooldown' AND cooldown_until <= NOW()");
        $stmt->execute([$now]);
        $proxy = $stmt->fetch();
    }

    return $proxy ?: null;
}

function getAllAvailableProxies($db, $gatewayId = null) {
    if (!$db) return [];

    // Check if proxy is enabled for this gateway
    if ($gatewayId) {
        $gw = $db->prepare("SELECT proxy_enabled FROM gateways WHERE id=?");
        $gw->execute([$gatewayId]);
        $gwData = $gw->fetch();
        if (!$gwData || !$gwData['proxy_enabled']) return [];
    }

    $now = date('Y-m-d H:i:s');
    // Try to activate proxies that came out of cooldown first
    $db->exec("UPDATE proxies SET status='active' WHERE status='cooldown' AND cooldown_until <= NOW()");

    // Get ALL active proxies that are not in cooldown, ordered by least-used first
    $stmt = $db->prepare("SELECT * FROM proxies WHERE status='active' AND (cooldown_until IS NULL OR cooldown_until <= ?) ORDER BY used_count ASC, total_used ASC, id ASC");
    $stmt->execute([$now]);
    $proxies = $stmt->fetchAll();

    return $proxies ?: [];
}

function markProxyUsed($db, $proxyId) {
    if (!$db || !$proxyId) return;
    $db->prepare("UPDATE proxies SET used_count = used_count + 1, total_used = total_used + 1, fail_count = 0, last_used_at = NOW() WHERE id=?")->execute([$proxyId]);

    // Check if proxy needs cooldown
    $proxy = $db->prepare("SELECT * FROM proxies WHERE id=?");
    $proxy->execute([$proxyId]);
    $proxy = $proxy->fetch();
    if ($proxy && $proxy['max_requests'] > 0 && $proxy['used_count'] >= $proxy['max_requests']) {
        if ($proxy['cooldown_minutes'] > 0) {
            $db->prepare("UPDATE proxies SET status='cooldown', cooldown_until=DATE_ADD(NOW(), INTERVAL ? MINUTE), used_count=0 WHERE id=?")
               ->execute([$proxy['cooldown_minutes'], $proxyId]);
        } else {
            // Reset counter for next round
            $db->prepare("UPDATE proxies SET used_count=0 WHERE id=?")->execute([$proxyId]);
        }
    }
}

function markProxyFailed($db, $proxyId) {
    if (!$db || !$proxyId) return;
    $db->prepare("UPDATE proxies SET fail_count = fail_count + 1, last_used_at = NOW() WHERE id=?")->execute([$proxyId]);
    $proxy = $db->prepare("SELECT * FROM proxies WHERE id=?");
    $proxy->execute([$proxyId]);
    $proxy = $proxy->fetch();
    if ($proxy && $proxy['fail_count'] >= $proxy['max_fails']) {
        $db->prepare("UPDATE proxies SET status='disabled' WHERE id=?")->execute([$proxyId]);
        logSystem('WARNING', 'PROXY_DISABLED', "Proxy ID $proxyId disabled after {$proxy['max_fails']} consecutive fails");
    }
}

function markProxySuccess($db, $proxyId) {
    if (!$db || !$proxyId) return;
    $db->prepare("UPDATE proxies SET fail_count = 0 WHERE id=?")->execute([$proxyId]);
}

// ============================================
// CURL PARSER (parse curl commands for gateway)
// ============================================
function parseCurlCommand($curlText) {
    $result = [
        'method'     => 'POST',
        'url'        => '',
        'headers'    => [],
        'body'       => [],
        'body_type'  => 'json',
    ];

    // Clean up the input - handle line continuations
    $text = str_replace(["\\\n", "\\\r\n", "\\\r"], ' ', $curlText);
    $text = trim($text);

    // Extract method
    if (preg_match('/-X\s+(GET|POST|PUT|DELETE|PATCH)/i', $text, $m)) {
        $result['method'] = strtoupper($m[1]);
    }

    // Extract URL - try multiple patterns
    if (preg_match('/["\']?(https?:\/\/[^"\'\s\\\\]+)["\']?/i', $text, $m)) {
        $result['url'] = $m[1];
    }

    // Extract headers
    if (preg_match_all('/-H\s+["\']([^"\']+)["\']/i', $text, $matches)) {
        foreach ($matches[1] as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $result['headers'][$key] = $val;
            }
        }
    }

    // Extract data/body - try --data-raw, --data, -d
    $bodyStr = null;
    if (preg_match('/--data-raw\s+["\'](.+?)["\']/si', $text, $m)) {
        $bodyStr = $m[1];
    } elseif (preg_match('/--data\s+["\'](.+?)["\']/si', $text, $m)) {
        $bodyStr = $m[1];
    } elseif (preg_match('/-d\s+["\'](.+?)["\']/si', $text, $m)) {
        $bodyStr = $m[1];
    } elseif (preg_match('/--data-raw\s+\$(.+?)$/si', $text, $m)) {
        $bodyStr = $m[1]; // variable reference
    }

    if ($bodyStr !== null) {
        $decoded = json_decode($bodyStr, true);
        if ($decoded !== null && is_array($decoded)) {
            $result['body'] = $decoded;
            $result['body_type'] = 'json';
        } else {
            parse_str($bodyStr, $parsed);
            if (!empty($parsed)) {
                $result['body'] = $parsed;
                $result['body_type'] = 'form';
            }
        }
    }

    return $result;
}

// ============================================
// PROXY VALIDATION HELPER
// ============================================
function validateProxy($proxyUrl, $timeout = 5) {
    // Test proxy by making a request through it
    $ch = curl_init('http://httpbin.org/ip');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_PROXY          => $proxyUrl,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    // Detect proxy type
    if (strpos($proxyUrl, 'socks5://') === 0) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
    } elseif (strpos($proxyUrl, 'socks4://') === 0) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return [
        'alive'     => ($httpCode >= 200 && $httpCode < 400),
        'http_code' => $httpCode,
        'error'     => $error,
    ];
}

// ============================================
// BUILD SYSTEM FORMATTED RESPONSE
// ============================================
function buildSystemResponse($isSuccess, $phone, $smsBody, $keyOwner, $pointsUsed, $pointsRemaining, $gatewayUsed, $apiMessage = null) {
    if ($isSuccess) {
        return [
            'status'           => 'success',
            'message'          => 'SMS sent successfully',
            'phone'            => $phone,
            'sms_body'         => $smsBody,
            'key_owner'        => $keyOwner,
            'gateway_used'     => $gatewayUsed,
            'points_used'      => $pointsUsed,
            'points_remaining' => max(0, $pointsRemaining),
            'sent_at'          => date('Y-m-d H:i:s'),
            'Owner'            => APP_OWNER,
        ];
    } else {
        return [
            'status'           => 'failed',
            'message'          => $apiMessage ?: 'SMS sending failed',
            'phone'            => $phone,
            'sms_body'         => $smsBody,
            'key_owner'        => $keyOwner,
            'gateway_used'     => $gatewayUsed,
            'points_remaining' => max(0, $pointsRemaining),
            'Owner'            => APP_OWNER,
        ];
    }
}
