<?php
// Audit logging wrapper

if (!function_exists('audit_log')) {
    function audit_log(string $action, string $targetTable, int $targetId = 0, array $meta = []): bool
    {
        if (class_exists('App\\Audit\\Logger')) {
            return App\Audit\Logger::log($action, $targetTable, $targetId, $meta);
        }
        return false;
    }
}
