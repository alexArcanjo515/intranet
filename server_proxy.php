<?php
session_start();
@set_time_limit(200);
require_once __DIR__ . '/includes/auth.php';
require_login();
require_any(['manage_settings'], ['admin']);

$pdo = require __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$path = isset($_GET['path']) ? (string)$_GET['path'] : '/';
if ($id <= 0) { http_response_code(400); exit('invalid'); }

$stmt = $pdo->prepare('SELECT protocol, host, ip, port FROM servers WHERE id = :id');
$stmt->execute([':id' => $id]);
$s = $stmt->fetch();
if (!$s) { http_response_code(404); exit('not found'); }

$protocol = in_array($s['protocol'], ['http','https'], true) ? $s['protocol'] : 'http';
$host = $s['host'] ?: $s['ip'];
$port = (int)$s['port'];

// Normalize path
if ($path === '' || $path[0] !== '/') { $path = '/' . $path; }

// Extract query string for target: merge any extra GET params (excluding id, path)
$targetQuery = '';
// If path itself contains a query, split and preserve
$parsed = parse_url($path);
if ($parsed && isset($parsed['query'])) {
    $path = $parsed['path'] ?? '/';
    $targetQuery = $parsed['query'];
}
// Append remaining GET params
$extra = $_GET;
unset($extra['id'], $extra['path']);
if (!empty($extra)) {
    $extraQs = http_build_query($extra);
    $targetQuery = $targetQuery ? ($targetQuery . '&' . $extraQs) : $extraQs;
}

$url = $protocol . '://' . $host . (($protocol==='http' && $port!=80) || ($protocol==='https' && $port!=443) ? (":".$port) : '') . $path . ($targetQuery ? ('?' . $targetQuery) : '');

$ch = curl_init($url);
// Forward limited headers
$headers = [];
if (!empty($_SERVER['HTTP_ACCEPT'])) $headers[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
if (!empty($_SERVER['HTTP_USER_AGENT'])) $headers[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false, // avoid redirect loops
    CURLOPT_TIMEOUT => 180,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: text/plain');
    echo 'Proxy error: ' . curl_error($ch);
    curl_close($ch);
    exit();
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = null;
foreach (explode("\r\n", $header) as $hline) {
    if (stripos($hline, 'Content-Type:') === 0) { $contentType = trim(substr($hline, 13)); break; }
}
curl_close($ch);

http_response_code($code);
if ($contentType) header('Content-Type: ' . $contentType);

// If HTML, rewrite links to stay within proxy
if ($contentType && stripos($contentType, 'text/html') !== false) {
    $basePath = rtrim(dirname($path), '/');
    $proxyBase = 'server_proxy.php?id=' . urlencode((string)$id) . '&path=';
    // Replace href/src attributes
    $body = preg_replace_callback('/\b(href|src)=("|\')([^"\']+)(\2)/i', function($m) use ($proxyBase, $protocol, $host, $port, $basePath) {
        $attr = $m[1]; $q = $m[2]; $val = $m[3];
        // Skip mailto:, javascript:, data:
        if (preg_match('#^(mailto:|javascript:|data:)#i', $val)) return $m[0];
        // Absolute http(s)
        if (preg_match('#^https?://#i', $val)) {
            // Only rewrite if same host
            $h = parse_url($val, PHP_URL_HOST);
            if ($h && strcasecmp($h, $host) === 0) {
                $p = parse_url($val, PHP_URL_PATH) ?: '/';
                $qstr = parse_url($val, PHP_URL_QUERY);
                $np = $p . ($qstr ? ('?' . $qstr) : '');
                return $attr . '=' . $q . $proxyBase . urlencode($np) . $q;
            }
            return $m[0];
        }
        // Root-relative
        if (strpos($val, '/') === 0) {
            return $attr . '=' . $q . $proxyBase . urlencode($val) . $q;
        }
        // Relative: resolve against basePath
        $resolved = ($basePath ? ($basePath . '/') : '/') . $val;
        // Normalize ../ and ./ minimally
        $resolved = preg_replace('#/(?:\./)+#', '/', $resolved);
        while (strpos($resolved, '/../') !== false) {
            $resolved = preg_replace('#/[^/]+/\.\./#', '/', $resolved);
        }
        return $attr . '=' . $q . $proxyBase . urlencode($resolved) . $q;
    }, $body);
}

echo $body;
