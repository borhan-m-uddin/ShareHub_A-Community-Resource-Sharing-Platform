<?php
// Items domain procedural helpers (converted from App\Domain\Items static methods)

// (bootstrap already loads this include)

if (!function_exists('items_list')) {
    function items_list(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('db_connected') || !db_connected()) return [];
        global $conn;
        $where = ['1=1'];
        $params = [];
        $types = '';
        if (!empty($filters['giver_id'])) {
            $where[] = 'giver_id=?';
            $params[] = (int)$filters['giver_id'];
            $types .= 'i';
        }
        if (!empty($filters['status'])) {
            $where[] = 'availability_status=?';
            $params[] = (string)$filters['status'];
            $types .= 's';
        }
        if (!empty($filters['category'])) {
            $where[] = 'category=?';
            $params[] = (string)$filters['category'];
            $types .= 's';
        }
        $sql = 'SELECT * FROM items WHERE ' . implode(' AND ', $where) . ' ORDER BY posting_date DESC LIMIT ?,?';
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
        $rows = [];
        if ($st = $conn->prepare($sql)) {
            $st->bind_param($types, ...$params);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
                if ($res) {
                    $res->free();
                }
            }
            $st->close();
        }
        return $rows;
    }
}

if (!function_exists('item_get')) {
    function item_get(int $itemId): ?array
    {
        if (!function_exists('db_connected') || !db_connected()) return null;
        global $conn;
        $itemId = (int)$itemId;
        if ($st = $conn->prepare('SELECT * FROM items WHERE item_id=? LIMIT 1')) {
            $st->bind_param('i', $itemId);
            if ($st->execute()) {
                $res = $st->get_result();
                $r = $res->fetch_assoc();
                if ($res) {
                    $res->free();
                }
                $st->close();
                return $r ?: null;
            }
            $st->close();
        }
        return null;
    }
}

if (!function_exists('item_create')) {
    function item_create(array $data): ?int
    {
        if (!function_exists('db_connected') || !db_connected()) return null;
        global $conn;
        $sql = 'INSERT INTO items (giver_id,title,description,category,condition_status,availability_status,image_url,pickup_location,posting_date) VALUES (?,?,?,?,?,?,?,?,NOW())';
        $giver = (int)($data['giver_id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        $desc = (string)($data['description'] ?? null);
        $cat = (string)($data['category'] ?? null);
        $cond = (string)($data['condition_status'] ?? 'good');
        $status = (string)($data['availability_status'] ?? 'available');
        $img = (string)($data['image_url'] ?? null);
        $loc = (string)($data['pickup_location'] ?? null);
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('isssssss', $giver, $title, $desc, $cat, $cond, $status, $img, $loc);
            if ($st->execute()) {
                $id = (int)$st->insert_id;
                $st->close();
                return $id;
            }
            $st->close();
        }
        return null;
    }
}

if (!function_exists('item_update')) {
    function item_update(int $itemId, array $data): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false;
        global $conn;
        $itemId = (int)$itemId;
        $fields = [];
        $params = [];
        $types = '';
        foreach (['title', 'description', 'category', 'condition_status', 'availability_status', 'image_url', 'pickup_location'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f=?";
                $params[] = $data[$f];
                $types .= 's';
            }
        }
        if (!$fields) return true;
        $params[] = $itemId;
        $types .= 'i';
        $sql = 'UPDATE items SET ' . implode(',', $fields) . ' WHERE item_id=?';
        if ($st = $conn->prepare($sql)) {
            $st->bind_param($types, ...$params);
            $ok = $st->execute();
            $st->close();
            return (bool)$ok;
        }
        return false;
    }
}

// Ownership aware update
if (!function_exists('item_update_owned')) {
    function item_update_owned(int $itemId, int $giverId, array $data): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false;
        global $conn;
        $fields = [];
        $params = [];
        $types = '';
        foreach (['title', 'description', 'category', 'condition_status', 'availability_status', 'image_url', 'pickup_location'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f=?";
                $params[] = $data[$f];
                $types .= 's';
            }
        }
        if (!$fields) return true;
        $params[] = $itemId;
        $params[] = $giverId;
        $types .= 'ii';
        $sql = 'UPDATE items SET ' . implode(',', $fields) . ' WHERE item_id=? AND giver_id=?';
        if ($st = $conn->prepare($sql)) {
            $st->bind_param($types, ...$params);
            $ok = $st->execute();
            $st->close();
            return (bool)$ok;
        }
        return false;
    }
}

// Ownership aware delete
if (!function_exists('item_delete')) {
    function item_delete(int $itemId, int $giverId): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false;
        global $conn;
        $itemId = (int)$itemId;
        $giverId = (int)$giverId;
        if ($st = $conn->prepare('DELETE FROM items WHERE item_id=? AND giver_id=?')) {
            $st->bind_param('ii', $itemId, $giverId);
            $ok = $st->execute();
            $st->close();
            return (bool)$ok;
        }
        return false;
    }
}

if (!function_exists('item_change_status')) {
    function item_change_status(int $itemId, string $status): bool
    {
        return item_update($itemId, ['availability_status' => $status]);
    }
}
