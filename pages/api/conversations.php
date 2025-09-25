<?php
// Unified Conversations API endpoint
// Actions: list, fetch, send, mark_read, user_search
// Security: requires login, CSRF for state-changing (all POST). All responses JSON.

require_once __DIR__ . '/../../bootstrap.php';
require_login();
verification_require();

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'method']);
    exit;
}
if (!csrf_verify()) {
    echo json_encode(['ok'=>false,'error'=>'csrf']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
if (function_exists('conversations_ensure_schema')) { conversations_ensure_schema(); }
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $list = conversation_list($userId, 50, 0);
        echo json_encode(['ok'=>true,'conversations'=>$list]);
        break;
    case 'fetch':
        $conversationId=(int)($_POST['conversation_id']??0);
        $before=(int)($_POST['before']??0);
        $limit=max(1,min(100,(int)($_POST['limit']??30)));
        $data = conversation_fetch($userId,$conversationId,$limit,$before);
        echo json_encode($data);
        break;
    case 'send':
        $conversationId=(int)($_POST['conversation_id']??0);
        $otherUserId=(int)($_POST['other_user_id']??0);
        $body=trim((string)($_POST['body']??''));
        $subject=trim((string)($_POST['subject']??''));
        if($body===''){ echo json_encode(['ok'=>false,'error'=>'empty']); break; }
        if($conversationId<=0 && $otherUserId>0){
            if(!function_exists('can_message') || !can_message($userId,$otherUserId)) { echo json_encode(['ok'=>false,'error'=>'not_allowed']); break; }
            $cid = conversation_start([$userId,$otherUserId]);
            if(!$cid){ echo json_encode(['ok'=>false,'error'=>'start']); break; }
            $conversationId=$cid;
        } elseif($conversationId>0 && function_exists('conversation_other_participant') && function_exists('can_message')) {
            $other=conversation_other_participant($conversationId,$userId);
            if($other && !can_message($userId,$other)){ echo json_encode(['ok'=>false,'error'=>'not_allowed']); break; }
        }
        if($conversationId>0){
            $mid=conversation_send($conversationId,$userId,$body,$subject?:null);
            if($mid){ echo json_encode(['ok'=>true,'conversation_id'=>$conversationId,'message_id'=>$mid]); break; }
        }
        $err= $GLOBALS['conversation_send_error'] ?? 'send';
        echo json_encode(['ok'=>false,'error'=>$err]);
        break;
    case 'mark_read':
        $conversationId=(int)($_POST['conversation_id']??0);
        $count=conversation_mark_read($userId,$conversationId);
        echo json_encode(['ok'=>true,'updated'=>$count]);
        break;
    case 'user_search':
        $q=trim((string)($_POST['q']??'')); $results=[];
        if($q!=='' && function_exists('db_connected') && db_connected()){
            global $conn; $like='%'.$q.'%';
            // 1) Find prior contacts via requests
            $sql1="SELECT DISTINCT u.user_id,u.username,u.first_name,u.last_name FROM users u JOIN requests r ON ((r.requester_id=? AND r.giver_id=u.user_id) OR (r.giver_id=? AND r.requester_id=u.user_id)) WHERE u.status=1 AND r.status IN ('pending','approved','completed') AND (LOWER(u.username) LIKE LOWER(?) OR LOWER(u.first_name) LIKE LOWER(?) OR LOWER(u.last_name) LIKE LOWER(?) OR LOWER(CONCAT(u.first_name,' ',u.last_name)) LIKE LOWER(?)) ORDER BY u.username LIMIT 10";
            if($st1=$conn->prepare($sql1)) { $st1->bind_param('iissss',$userId,$userId,$like,$like,$like,$like); if($st1->execute()){ $res=$st1->get_result(); while($r=$res->fetch_assoc()){ if((int)$r['user_id']!==$userId) $results[(int)$r['user_id']]=$r; } if($res){$res->free();} } $st1->close(); }
            // 2) Always include admins matching query
            $sql2="SELECT u.user_id,u.username,u.first_name,u.last_name FROM users u WHERE u.status=1 AND u.role='admin' AND (LOWER(u.username) LIKE LOWER(?) OR LOWER(u.first_name) LIKE LOWER(?) OR LOWER(u.last_name) LIKE LOWER(?) OR LOWER(CONCAT(u.first_name,' ',u.last_name)) LIKE LOWER(?)) ORDER BY u.username LIMIT 10";
            if($st2=$conn->prepare($sql2)) { $st2->bind_param('ssss',$like,$like,$like,$like); if($st2->execute()){ $res2=$st2->get_result(); while($r=$res2->fetch_assoc()){ if((int)$r['user_id']!==$userId) $results[(int)$r['user_id']]=$r; } if($res2){$res2->free();} } $st2->close(); }
            // Collapse keyed array to list
            $results=array_values($results);
        }
        echo json_encode(['ok'=>true,'users'=>$results]);
        break;
    default:
        echo json_encode(['ok'=>false,'error'=>'unknown']);
}
