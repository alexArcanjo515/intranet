<?php
// Simple encryption helpers using OpenSSL. Requires ENCRYPTION_KEY in environment or settings.
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

function crypto_key(): string {
    $k = getenv('ENCRYPTION_KEY');
    if (!$k && function_exists('get_setting_value')) {
        $k = (string) get_setting_value('encryption_key', '');
    }
    if (!$k) {
        return '';
    }
    // Normalize to 32 bytes
    return hash('sha256', $k, true);
}

function encrypt_secret(string $plain): string {
    $key = crypto_key();
    if ($plain === '' || $key === '') { return $plain; }
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return $plain;
    $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return 'enc:' . base64_encode($iv . $hmac . $cipher);
}

function decrypt_secret(string $stored): string {
    $key = crypto_key();
    if ($stored === '' || $key === '' || strncmp($stored, 'enc:', 4) !== 0) { return $stored; }
    $data = base64_decode(substr($stored, 4), true);
    if ($data === false || strlen($data) < 16 + 32) { return ''; }
    $iv = substr($data, 0, 16);
    $hmac = substr($data, 16, 32);
    $cipher = substr($data, 48);
    $calc = hash_hmac('sha256', $iv . $cipher, $key, true);
    if (!hash_equals($hmac, $calc)) { return ''; }
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '';
}
