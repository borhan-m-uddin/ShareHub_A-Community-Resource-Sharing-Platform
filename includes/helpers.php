<?php
// Core helper & utility functions extracted from bootstrap for simpler structure.

// Settings (JSON-backed)
if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = null)
    {
        static $cache = null; // in-request cache
        $file = __DIR__ . '/../storage/settings.json';
        if ($cache === null) {
            if (is_file($file)) {
                $json = @file_get_contents($file);
                $data = json_decode((string)$json, true);
                $cache = is_array($data) ? $data : [];
            } else {
                $cache = [];
            }
        }
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }
}
if (!function_exists('save_setting')) {
    function save_setting(string $key, $value): bool
    {
        static $cache = null;
        $file = __DIR__ . '/../storage/settings.json';
        if ($cache === null) {
            if (is_file($file)) {
                $json = @file_get_contents($file);
                $data = json_decode((string)$json, true);
                $cache = is_array($data) ? $data : [];
            } else {
                $cache = [];
            }
        }
        $cache[$key] = $value;
        if (!is_dir(dirname($file))) {@mkdir(dirname($file), 0777, true);}        
        return (bool)@file_put_contents($file, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
if (!function_exists('get_settings_all')) {
    function get_settings_all(): array
    {
        $file = __DIR__ . '/../storage/settings.json';
        if (is_file($file)) {
            $json = @file_get_contents($file);
            $data = json_decode((string)$json, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }
}
if (!function_exists('save_settings')) {
    function save_settings(array $data, bool $merge = true): bool
    {
        $file = __DIR__ . '/../storage/settings.json';
        $existing = $merge ? get_settings_all() : [];
        $merged = $merge ? array_merge($existing, $data) : $data;
        if (!is_dir(dirname($file))) {@mkdir(dirname($file), 0777, true);}        
        return (bool)@file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

// Flash messages
if (!function_exists('flash_set')) {
    function flash_set(string $key, $value): void { $_SESSION['__flash'][$key] = $value; }
}
if (!function_exists('flash_get')) {
    function flash_get(string $key, $default = null)
    {
        if (!isset($_SESSION['__flash'][$key])) return $default;
        $val = $_SESSION['__flash'][$key];
        unset($_SESSION['__flash'][$key]);
        return $val;
    }
}

// HTML escape helper
if (!function_exists('e')) {
    function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string { return '/' . ltrim($path, '/'); }
}
// Normalize image URL from DB (absolute URL, site-absolute, or stored relative like uploads/items/..)
if (!function_exists('image_src')) {
    function image_src(?string $path): string {
        $path = (string)$path;
        if ($path === '') return '';
        if (strpos($path,'http://')===0 || strpos($path,'https://')===0) return $path;
        return asset_url($path);
    }
}
if (!function_exists('site_href')) {
    function site_href(string $path): string { return asset_url($path); }
}

// Pagination query merge
if (!function_exists('build_query')) {
    function build_query(array $updates = [], ?array $base = null): string
    {
        $params = $base ?? (isset($_GET) && is_array($_GET) ? $_GET : []);
        foreach ($updates as $k => $v) {
            if ($v === null) { unset($params[$k]); } else { $params[$k] = $v; }
        }
        return http_build_query($params);
    }
}

if (!function_exists('render_pagination')) {
    function render_pagination(int $page, int $perPage, int $shownCount, int $total, array $extraParams = []): void
    {
        if ($total <= $perPage) return;
        $offset = ($page - 1) * $perPage;
        echo '<div style="margin-top:10px;display:flex;gap:8px;">';
        if ($page > 1) {
            $qs = build_query(array_merge($extraParams, ['page' => $page - 1]));
            echo '<a class="btn btn-default" href="?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . '">Prev</a>';
        }
        if ($offset + $shownCount < $total) {
            $qs = build_query(array_merge($extraParams, ['page' => $page + 1]));
            echo '<a class="btn btn-default" href="?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . '">Next</a>';
        }
        echo '</div>';
    }
}

// Constants (ensure defined once)
if (!defined('ROLES')) { define('ROLES', ['admin','giver','seeker']); }
if (!defined('ITEM_STATUSES')) { define('ITEM_STATUSES', ['available','pending','unavailable']); }
if (!defined('SERVICE_AVAILABILITIES')) { define('SERVICE_AVAILABILITIES', ['available','busy','unavailable']); }
if (!defined('REQUEST_STATUSES')) { define('REQUEST_STATUSES', ['pending','approved','rejected','completed']); }

// Simple header/footer rendering pass-throughs (kept for compatibility)
if (!function_exists('render_header')) {
    function render_header(): void {
        $path = ROOT_DIR . '/partials/header.php';
        if (file_exists($path)) { include $path; } else { echo '<header><h1>ShareHub</h1></header><main class="main-content">'; }
    }
}
if (!function_exists('render_footer')) {
    function render_footer(): void {
        $path = ROOT_DIR . '/partials/footer.php';
        if (file_exists($path)) { include $path; } else { echo '</main></body></html>'; }
    }
}
