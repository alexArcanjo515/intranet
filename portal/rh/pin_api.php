<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/settings.php';

if (!isset($_SESSION['portal_user']['id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? '';

function jerr($m='bad_request', $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function require_csrf(){ if(($_POST['csrf'] ?? '') !== csrf_token()){ jerr('csrf', 403); } }

if ($method !== 'POST') { jerr('method', 405); }
if ($action !== 'verify_pin') { jerr('unknown_action', 400); }
require_csrf();

$LOCK_SECONDS = 3600; // 1 hora
$MAX_ATTEMPTS = 3;
$PIN = '39814280';

$now = time();
$lockedUntil = (int)($_SESSION['rh_pin_lock_until'] ?? 0);
if ($lockedUntil > $now) {
  echo json_encode(['ok'=>false, 'error'=>'locked', 'locked'=>true, 'until'=>$lockedUntil]);
  exit;
}

$pin = (string)($_POST['pin'] ?? '');
$pin = preg_replace('/\D+/', '', $pin);

if ($pin === $PIN) {
  $_SESSION['rh_pin_ok'] = true;
  $_SESSION['rh_pin_attempts'] = 0;
  $_SESSION['rh_pin_lock_until'] = 0;
  $_SESSION['rh_pin_at'] = $now;
  $_SESSION['rh_pin_last'] = $now;
  echo json_encode(['ok'=>true]);
  exit;
}

$attempts = (int)($_SESSION['rh_pin_attempts'] ?? 0);
$attempts++;
$_SESSION['rh_pin_attempts'] = $attempts;
if ($attempts >= $MAX_ATTEMPTS) {
  $_SESSION['rh_pin_lock_until'] = $now + $LOCK_SECONDS;
  echo json_encode(['ok'=>false, 'error'=>'locked', 'locked'=>true, 'remaining_attempts'=>0]);
  exit;
}
$remaining = $MAX_ATTEMPTS - $attempts;
echo json_encode(['ok'=>false, 'error'=>'invalid_pin', 'locked'=>false, 'remaining_attempts'=>$remaining]);
