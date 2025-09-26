<?php
// Procedural mail helper replacing class-based Mailer usage.
// Provides send_email($to,$subject,$htmlOrText,$from=null): bool

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $message, ?string $from = null): bool
    {
        // Prefer existing Mailer class for consistency
        if (class_exists('App\\Mail\\Mailer')) {
            return \App\Mail\Mailer::send($to, $subject, $message, $from);
        }
        // Minimal fallback: append to storage/mail.log
        $root = defined('ROOT_DIR') ? ROOT_DIR : __DIR__ . '/..';
        $storageDir = $root . '/storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $logFile = $storageDir . '/mail.log';
        $entry = '[' . date('Y-m-d H:i:s') . "] Fallback mail\nTO: {$to}\nSUBJECT: {$subject}\nMESSAGE:\n{$message}\n----\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
        return true; // treat as sent for UX
    }
}
