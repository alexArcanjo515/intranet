<?php
// Auth helpers. Assumem que session_start() já foi chamado no script que inclui.

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function require_login(): void {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit();
    }
}

// Exige pelo menos uma permissão OU um papel dentre os informados.
// $wantPerms: array de nomes de permissões. $wantRoles: array de nomes de papéis.
function require_any(array $wantPerms = [], array $wantRoles = []): void {
    $u = current_user();
    $roles = $u['roles'] ?? [];
    $perms = $u['perms'] ?? [];

    $ok = false;
    if (!empty($wantRoles)) {
        foreach ($wantRoles as $r) {
            if (in_array($r, $roles, true)) { $ok = true; break; }
        }
    }
    if (!$ok && !empty($wantPerms)) {
        foreach ($wantPerms as $p) {
            if (in_array($p, $perms, true)) { $ok = true; break; }
        }
    }

    if (!$ok) {
        http_response_code(403);
        echo 'Acesso negado.';
        exit();
    }
}
