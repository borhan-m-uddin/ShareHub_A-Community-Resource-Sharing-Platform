<?php
// Conversation / messaging wrappers

if (!function_exists('conversations_ensure_schema')) {
    function conversations_ensure_schema(): void
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            App\Messaging\Conversations::ensureSchema();
        }
    }
}

if (!function_exists('conversation_start')) {
    function conversation_start(array $userIds): ?int
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::start($userIds);
        }
        return null;
    }
}

if (!function_exists('conversation_lazy_backfill_for_user')) {
    function conversation_lazy_backfill_for_user(int $userId, int $limitPairs = 100): void
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            App\Messaging\Conversations::lazyBackfillForUser($userId, $limitPairs);
        }
    }
}

if (!function_exists('conversation_send')) {
    function conversation_send(int $conversationId, int $senderId, string $body, ?string $subject = null, ?int $requestId = null): ?int
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::send($conversationId, $senderId, $body, $subject, $requestId);
        }
        return null;
    }
}

if (!function_exists('conversation_list')) {
    function conversation_list(int $userId, int $limit = 20, int $offset = 0): array
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::listForUser($userId, $limit, $offset);
        }
        return [];
    }
}

if (!function_exists('conversation_fetch')) {
    function conversation_fetch(int $userId, int $conversationId, int $limit = 200, int $beforeMessageId = 0): array
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::fetch($userId, $conversationId, $limit, $beforeMessageId);
        }
        return ['ok' => false, 'messages' => []];
    }
}

if (!function_exists('conversation_mark_read')) {
    function conversation_mark_read(int $userId, int $conversationId): int
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::markRead($userId, $conversationId);
        }
        return 0;
    }
}

if (!function_exists('conversation_other_participant')) {
    function conversation_other_participant(int $conversationId, int $selfUserId): ?int
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::otherParticipant($conversationId, $selfUserId);
        }
        return null;
    }
}

if (!function_exists('can_message')) {
    function can_message(int $userId, int $otherUserId): bool
    {
        if (class_exists('App\\Messaging\\Conversations')) {
            return App\Messaging\Conversations::canMessage($userId, $otherUserId);
        }
        return false;
    }
}
