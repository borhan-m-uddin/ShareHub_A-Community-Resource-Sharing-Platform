<?php

namespace App\Audit;

class Logger
{
    /**
     * Log an admin action to the audit_log table.
     */
    public static function log(string $action, string $targetTable, int $targetId = 0, array $meta = []): bool
    {
        if (!\function_exists('db_connected') || !db_connected()) return false;
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') return false;
        global $conn;
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) return false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        @$conn->query("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT NOT NULL,
            action VARCHAR(60) NOT NULL,
            target_table VARCHAR(60) NOT NULL,
            target_id INT NOT NULL DEFAULT 0,
            meta_json JSON NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_user_id),
            INDEX idx_target (target_table,target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $json = $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        if ($stmt = $conn->prepare('INSERT INTO audit_log (admin_user_id,action,target_table,target_id,meta_json,ip) VALUES (?,?,?,?,?,?)')) {
            $stmt->bind_param('ississ', $adminId, $action, $targetTable, $targetId, $json, $ip);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }
        return false;
    }
}
