<?php
// Settings helpers to fetch brand/name and logo. Assumes $pdo available or will create its own PDO.
function settings_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = require __DIR__ . '/../config/db.php';
    }
    return $pdo;
}
function get_setting_value(string $key, $default = ''): string {
    $pdo = settings_pdo();
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string)$v : (string)$default;
    } catch (Throwable $e) {
        return (string)$default;
    }
}
function brand_name(): string { return get_setting_value('site_name', 'Intranet'); }
function brand_logo_url(): string { return get_setting_value('logo_url', ''); }
function project_root(): string { return dirname(__DIR__); }
function fs_to_url(string $path): string {
    $root = rtrim(project_root(), '/');
    $real = @realpath($path) ?: $path;
    if (strpos($real, $root) === 0) {
        $rel = substr($real, strlen($root));
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
        return $rel === '' ? '/' : $rel;
    }
    // if it's already a URL-like or absolute web path
    if (preg_match('~^https?://~i', $path)) return $path;
    if (strpos($path, '/') === 0) return $path;
    return '/'.$path;
}
function default_logo_url_if_exists(): string {
    $candidate = project_root().'/assets/logotipo.png';
    if (is_file($candidate)) { return fs_to_url($candidate); }
    return '';
}
function render_brand_html(?string $logoOverride = null, array $imgAttrs = []): string {
    $name = htmlspecialchars(brand_name());
    $logo = '';
    if ($logoOverride) {
        // Accept either filesystem path or URL/path
        if (is_file($logoOverride) || @realpath($logoOverride)) {
            $logo = fs_to_url($logoOverride);
        } else {
            $logo = $logoOverride;
        }
    }
    if ($logo === '') { $logo = brand_logo_url(); }
    if ($logo === '') { $logo = default_logo_url_if_exists(); }
    if ($logo) {
        $logoEsc = htmlspecialchars($logo);
        $attrs = '';
        $hasHeight = false; $hasStyle = false; $hasAlt = false;
        foreach ($imgAttrs as $k=>$v) {
            if ($k==='height') $hasHeight = true;
            if ($k==='style') $hasStyle = true;
            if ($k==='alt') $hasAlt = true;
            $attrs .= ' '.htmlspecialchars((string)$k).'="'.htmlspecialchars((string)$v).'"';
        }
        if (!$hasAlt) { $attrs .= ' alt="logo"'; }
        if (!$hasHeight && !$hasStyle) { $attrs .= ' style="height:24px;width:auto;border-radius:4px;"'; }
        return '<span class="d-flex align-items-center gap-2"><img src="'.$logoEsc.'"'.$attrs.'/><span>'.$name.'</span></span>';
    }
    return '<span>'.$name.'</span>';
}
