<?php
// Services domain procedural helpers (converted from App\Domain\Services)

// (bootstrap already loads this include)

if (!function_exists('services_list')) {
    function services_list(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('db_connected') || !db_connected()) return [];
        global $conn; $where = ['1=1']; $params = []; $types = '';
        if (!empty($filters['giver_id'])) { $where[] = 'giver_id=?'; $params[] = (int)$filters['giver_id']; $types .= 'i'; }
        if (!empty($filters['availability'])) { $where[] = 'availability=?'; $params[] = (string)$filters['availability']; $types .= 's'; }
        if (!empty($filters['category'])) { $where[] = 'category=?'; $params[] = (string)$filters['category']; $types .= 's'; }
        $sql = 'SELECT * FROM services WHERE ' . implode(' AND ', $where) . ' ORDER BY posting_date DESC LIMIT ?,?';
        $params[] = $offset; $params[] = $limit; $types .= 'ii'; $rows = [];
        if ($st = $conn->prepare($sql)) { $st->bind_param($types, ...$params); if ($st->execute()) { $res=$st->get_result(); while ($r=$res->fetch_assoc()) { $rows[]=$r; } if($res){$res->free();} } $st->close(); }
        return $rows;
    }
}

if (!function_exists('service_get')) {
    function service_get(int $serviceId): ?array
    {
        if (!function_exists('db_connected') || !db_connected()) return null; global $conn; $serviceId = (int)$serviceId;
        if ($st = $conn->prepare('SELECT * FROM services WHERE service_id=? LIMIT 1')) { $st->bind_param('i', $serviceId); if ($st->execute()) { $res=$st->get_result(); $r = $res->fetch_assoc(); if($res){$res->free();} $st->close(); return $r ?: null; } $st->close(); }
        return null;
    }
}

if (!function_exists('service_create')) {
    function service_create(array $data): ?int
    {
        if (!function_exists('db_connected') || !db_connected()) return null; global $conn;
        $sql = 'INSERT INTO services (giver_id,title,description,category,availability,location,posting_date) VALUES (?,?,?,?,?,?,NOW())';
        $giver = (int)($data['giver_id'] ?? 0); $title = trim((string)($data['title'] ?? ''));
        $desc = (string)($data['description'] ?? null); $cat = (string)($data['category'] ?? null);
        $avail = (string)($data['availability'] ?? 'available'); $loc = (string)($data['location'] ?? null);
        if ($st = $conn->prepare($sql)) { $st->bind_param('isssss', $giver, $title, $desc, $cat, $avail, $loc); if ($st->execute()) { $id = (int)$st->insert_id; $st->close(); return $id; } $st->close(); }
        return null;
    }
}

if (!function_exists('service_update')) {
    function service_update(int $serviceId, array $data): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn; $serviceId = (int)$serviceId;
        $fields = []; $params = []; $types='';
        foreach (['title','description','category','availability','location'] as $f) { if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $params[]=$data[$f]; $types.='s'; } }
        if (!$fields) return true; $params[] = $serviceId; $types.='i';
        $sql = 'UPDATE services SET ' . implode(',', $fields) . ' WHERE service_id=?';
        if ($st = $conn->prepare($sql)) { $st->bind_param($types, ...$params); $ok=$st->execute(); $st->close(); return (bool)$ok; }
        return false;
    }
}

// Ownership aware update
if (!function_exists('service_update_owned')) {
    function service_update_owned(int $serviceId, int $giverId, array $data): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn;
        $fields = []; $params = []; $types='';
        foreach (['title','description','category','availability','location'] as $f) { if (array_key_exists($f,$data)) { $fields[] = "$f=?"; $params[]=$data[$f]; $types.='s'; } }
        if (!$fields) return true; $params[] = $serviceId; $params[] = $giverId; $types.='ii';
        $sql = 'UPDATE services SET ' . implode(',', $fields) . ' WHERE service_id=? AND giver_id=?';
        if ($st=$conn->prepare($sql)) { $st->bind_param($types, ...$params); $ok=$st->execute(); $st->close(); return (bool)$ok; }
        return false;
    }
}

// Ownership aware delete
if (!function_exists('service_delete')) {
    function service_delete(int $serviceId, int $giverId): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn; $serviceId=(int)$serviceId; $giverId=(int)$giverId;
        if ($st=$conn->prepare('DELETE FROM services WHERE service_id=? AND giver_id=?')) { $st->bind_param('ii',$serviceId,$giverId); $ok=$st->execute(); $st->close(); return (bool)$ok; }
        return false;
    }
}

if (!function_exists('service_change_availability')) {
    function service_change_availability(int $serviceId, string $availability): bool
    { return service_update($serviceId, ['availability' => $availability]); }
}
