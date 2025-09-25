<?php
// Requests domain procedural helpers (converted from App\Domain\Requests)

// (bootstrap already loads this include)

if (!function_exists('requests_list_for_user')) {
    function requests_list_for_user(int $userId, string $role = 'any', int $limit = 20, int $offset = 0): array
    {
        if (!function_exists('db_connected') || !db_connected()) return [];
        global $conn; $userId = (int)$userId; $where = [];
        if ($role === 'requester') { $where[] = 'requester_id=' . $userId; }
        elseif ($role === 'giver') { $where[] = 'giver_id=' . $userId; }
        else { $where[] = '(requester_id=' . $userId . ' OR giver_id=' . $userId . ')'; }
        $sql = 'SELECT * FROM requests WHERE ' . implode(' AND ', $where) . ' ORDER BY request_date DESC LIMIT ?,?';
        $rows = []; if ($st=$conn->prepare($sql)) { $st->bind_param('ii',$offset,$limit); if($st->execute()){ $res=$st->get_result(); while($r=$res->fetch_assoc()){ $rows[]=$r;} if($res){$res->free();}} $st->close(); }
        return $rows;
    }
}

if (!function_exists('request_create')) {
    function request_create(array $data): ?int
    {
        if (!function_exists('db_connected') || !db_connected()) return null; global $conn;
        $sql = 'INSERT INTO requests (requester_id,giver_id,item_id,service_id,request_type,message,status,request_date) VALUES (?,?,?,?,?,?,?,NOW())';
        $req = (int)($data['requester_id'] ?? 0); $giver = (int)($data['giver_id'] ?? null);
        $item = (int)($data['item_id'] ?? null); $service = (int)($data['service_id'] ?? null);
        $type = (string)($data['request_type'] ?? 'item'); $msg = (string)($data['message'] ?? null); $status = (string)($data['status'] ?? 'pending');
        if ($st = $conn->prepare($sql)) { $st->bind_param('iiiisss', $req, $giver, $item, $service, $type, $msg, $status); if ($st->execute()) { $id=(int)$st->insert_id; $st->close(); return $id; } $st->close(); }
        return null;
    }
}

if (!function_exists('request_update_status')) {
    function request_update_status(int $requestId, string $status): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn; $requestId = (int)$requestId;
        if ($st = $conn->prepare('UPDATE requests SET status=?, response_date=NOW() WHERE request_id=?')) { $st->bind_param('si',$status,$requestId); $ok=$st->execute(); $st->close(); return (bool)$ok; }
        return false;
    }
}

if (!function_exists('pair_can_message')) {
    function pair_can_message(int $userA, int $userB): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn; $userA=(int)$userA; $userB=(int)$userB;
        if ($userA<=0 || $userB<=0 || $userA===$userB) return false;
        $sql = "SELECT 1 FROM requests WHERE ((requester_id=? AND giver_id=?) OR (requester_id=? AND giver_id=?)) AND status IN ('pending','approved','completed') LIMIT 1";
        if ($st = $conn->prepare($sql)) { $st->bind_param('iiii',$userA,$userB,$userB,$userA); if($st->execute()){ $f=$st->get_result()->fetch_row(); $st->close(); return (bool)$f; } $st->close(); }
        return false;
    }
}
