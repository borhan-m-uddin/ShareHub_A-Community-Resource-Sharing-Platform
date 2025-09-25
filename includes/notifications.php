<?php
// Notification helper wrappers (delegating to existing class if present)

if (!function_exists('notify_user')) {
    /**
     * Create an in-app notification (and optional email) for a user.
     */
    function notify_user(int $userId, string $type, string $subject, string $body, ?string $relatedType = null, ?int $relatedId = null, bool $alsoEmail = false): bool
    {
        if (class_exists('App\\Notifications\\Notifier')) {
            return App\Notifications\Notifier::notify($userId, $type, $subject, $body, $relatedType, $relatedId, $alsoEmail);
        }
        return false;
    }
}

if (!function_exists('notifications_fetch_unread')) {
    function notifications_fetch_unread(int $userId, int $limit = 10): array
    {
        if (class_exists('App\\Notifications\\Notifier')) {
            return App\Notifications\Notifier::fetchUnread($userId, $limit);
        }
        return [];
    }
}

if (!function_exists('notifications_mark_read')) {
    /** Mark specific notifications (ids array) or all unread if empty */
    function notifications_mark_read(int $userId, array $ids = []): int
    {
        if (class_exists('App\\Notifications\\Notifier')) {
            return App\Notifications\Notifier::markRead($userId, $ids);
        }
        return 0;
    }
}
