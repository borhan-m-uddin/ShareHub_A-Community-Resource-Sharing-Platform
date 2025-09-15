<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
verification_require(); // enforce verified email if system uses it

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: index.php'); exit; }

// Ensure schema so page can function even if migration not yet run.
if (function_exists('conversations_ensure_schema')) { conversations_ensure_schema(); }

// Handle AJAX endpoints inline (simple approach)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!csrf_verify()) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'send') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $otherUserId     = (int)($_POST['other_user_id'] ?? 0); // used when starting new
        $body            = trim((string)($_POST['body'] ?? ''));
        $subject         = trim((string)($_POST['subject'] ?? ''));
        if ($body === '') { echo json_encode(['ok'=>false,'error'=>'empty']); exit; }
        if ($conversationId <= 0 && $otherUserId > 0) {
            $cid = conversation_start([$userId,$otherUserId]);
            if (!$cid) { echo json_encode(['ok'=>false,'error'=>'start']); exit; }
            $conversationId = $cid;
        }
        if ($conversationId > 0) {
            $mid = conversation_send($conversationId, $userId, $body, $subject ?: null);
            if ($mid) {
                // (Notification for other participant will be added in later task.)
                echo json_encode(['ok'=>true,'conversation_id'=>$conversationId,'message_id'=>$mid]);
                exit;
            }
        }
        $errDetail = isset($GLOBALS['conversation_send_error']) && $GLOBALS['conversation_send_error'] ? $GLOBALS['conversation_send_error'] : 'send';
        echo json_encode(['ok'=>false,'error'=>$errDetail]); exit;
    } elseif ($action === 'fetch') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $before         = (int)($_POST['before'] ?? 0);
        $limit          = max(1, min(100, (int)($_POST['limit'] ?? 30)));
        $data = conversation_fetch($userId, $conversationId, $limit, $before);
        echo json_encode($data); exit;
    } elseif ($action === 'list') {
        $list = conversation_list($userId, 50, 0);
        echo json_encode(['ok'=>true,'conversations'=>$list]); exit;
    } elseif ($action === 'mark_read') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $count = conversation_mark_read($userId,$conversationId);
        echo json_encode(['ok'=>true,'updated'=>$count]); exit;
    } elseif ($action === 'user_search') {
        $q = trim((string)($_POST['q'] ?? ''));
        $results=[];
        if ($q !== '' && db_connected()) {
            global $conn; $like = '%'.$q.'%';
            // Fuzzy substring + combined name: case-insensitive using LOWER
            $sql = "SELECT user_id, username, first_name, last_name
                    FROM users
                    WHERE status=1 AND (
                        LOWER(username) LIKE LOWER(?) OR
                        LOWER(first_name) LIKE LOWER(?) OR
                        LOWER(last_name) LIKE LOWER(?) OR
                        LOWER(CONCAT(first_name,' ',last_name)) LIKE LOWER(?)
                    )
                    ORDER BY username LIMIT 10";
            if ($st = $conn->prepare($sql)) {
                $st->bind_param('ssss',$like,$like,$like,$like);
                if($st->execute()){ $res=$st->get_result(); while($r=$res->fetch_assoc()){ if((int)$r['user_id']!==$userId){ $results[]=$r; } } $res->free(); }
                $st->close();
            }
        }
        echo json_encode(['ok'=>true,'users'=>$results]); exit;
    }
    echo json_encode(['ok'=>false,'error'=>'unknown']); exit;
}

// Standard HTML page (non-AJAX)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversations</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<div class="wrapper">
    <div class="card" style="margin-bottom:14px;">
        <div class="card-body">
            <h1 style="margin:0 0 4px 0;">Messages</h1>
            <p class="muted" style="margin:0;">View all previous messages you have sent and received. Start a new conversation by entering a user ID.</p>
        </div>
    </div>
    <div class="conversations-grid" style="min-height:560px;">
        <div class="convo-sidebar">
            <div class="card" style="flex:1;display:flex;flex-direction:column;">
                <div class="card-body" style="display:flex;flex-direction:column;flex:1;">
                    <div class="convo-header">Your Conversations</div>
                    <div class="start-box" style="flex-direction:column;gap:6px;align-items:stretch;">
                        <div style="display:flex;gap:8px;">
                            <input type="number" id="startUserId" placeholder="User ID" min="1" />
                            <button id="btnStart" class="btn btn-success" type="button">Start</button>
                        </div>
                        <input type="text" id="userSearch" placeholder="Search user by name..." style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font:inherit;" />
                        <ul id="userSearchResults" style="list-style:none;margin:0;padding:0;max-height:140px;overflow:auto;border:1px solid var(--border);border-radius:6px;display:none;background:var(--card);"></ul>
                    </div>
                    <ul class="convo-list" id="convoItems"></ul>
                </div>
            </div>
        </div>
        <div class="thread-card" id="threadPanel" style="display:none;">
            <div class="card" style="flex:1;display:flex;flex-direction:column;">
                <div class="card-body" style="display:flex;flex-direction:column;flex:1;">
                    <div class="thread-head">
                        <div id="threadTitle" style="font-weight:600;">Thread</div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button id="btnMarkRead" class="btn btn-default btn-sm" type="button">Mark Read</button>
                        </div>
                    </div>
                    <div class="messages" id="messagesList"></div>
                    <form class="composer" id="composeForm" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" id="conversation_id" value="0" />
                        <div class="composer-shell">
                            <textarea name="body" id="body" placeholder="Write a message..." required></textarea>
                            <div class="composer-controls">
                                <input type="text" name="subject" id="subject" placeholder="Subject (optional)" />
                                <button type="submit" id="btnSend" class="btn">Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const listEl = document.getElementById('convoItems');
    const threadPanel = document.getElementById('threadPanel');
    const messagesList = document.getElementById('messagesList');
    const convoIdInput = document.getElementById('conversation_id');
    const form = document.getElementById('composeForm');
    const bodyInput = document.getElementById('body');
    const subjectInput = document.getElementById('subject');
    const btnSend = document.getElementById('btnSend');
    const btnStart = document.getElementById('btnStart');
    const startUserId = document.getElementById('startUserId');
    const userSearch = document.getElementById('userSearch');
    const userSearchResults = document.getElementById('userSearchResults');
    const btnMarkRead = document.getElementById('btnMarkRead');
    const threadTitle = document.getElementById('threadTitle');

    let activeConversation = 0;
    let loadingMessages = false;
    let loadedAll = false; // after first fetch
    let convoMeta = {}; // map conversation_id -> meta (other_username)

    function qs(data){
        return new URLSearchParams(Object.entries(data).map(([k,v])=>[k,String(v)])).toString();
    }
    function post(action, data){
        data.action = action;
        data.csrf_token = '<?= e(csrf_token()) ?>';
        return fetch('conversations.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:qs(data)}).then(r=>r.json());
    }

    function loadList(){
        post('list',{}).then(res=>{
            if(!res.ok) return;
            listEl.innerHTML='';
            res.conversations.forEach(c=>{
                convoMeta[c.conversation_id] = c;
                const li=document.createElement('li');
                li.className='list-item';
                li.dataset.cid=c.conversation_id;
                const name = c.other_username ? c.other_username : ('#'+c.conversation_id);
                li.innerHTML = `<div>${name}${c.unread_count>0?`<span class=\"badge-unread\">${c.unread_count}</span>`:''}</div>`+
                                `<span class=\"subtitle\">${(c.last_msg_body||'').replace(/</g,'&lt;')}</span>`;
                li.addEventListener('click', ()=>{ activateConversation(parseInt(c.conversation_id)); });
                listEl.appendChild(li);
            });
            // Auto-open conversation specified by ?open= param once
            if(!activeConversation){
                const params=new URLSearchParams(window.location.search);
                const openId=parseInt(params.get('open')||'0',10);
                if(openId>0){
                    const exists=[...listEl.children].some(li=>parseInt(li.dataset.cid)===openId);
                    if(exists){ activateConversation(openId); }
                    else if(res.conversations.length>0){ activateConversation(parseInt(res.conversations[0].conversation_id)); }
                }
            }
        });
    }

    function renderMessage(m){
        const mine = m.sender_id == <?= (int)$userId ?>;
        const div=document.createElement('div');
        div.className='msg'+(mine?' mine':'');
        const date=new Date(m.sent_date.replace(' ','T'));
        const displayName = mine ? 'You' : (m.sender_username || ('User '+m.sender_id));
        const initials = displayName.split(/\s+/).map(p=>p[0]).slice(0,2).join('').toUpperCase();
        div.innerHTML = `<div style=\"white-space:pre-wrap;\"><span class=\"avatar-chip\" data-user=\"${m.sender_id}\">${initials}</span><strong style=\"font-size:12px;opacity:.75;margin-left:6px;\">${displayName}</strong><br>${(m.message_content||'').replace(/</g,'&lt;')}</div>`+
                        `<small>${date.toLocaleString()}${(m.is_read||m.read_at?' • ✓':'')}</small>`;
        return div;
    }

    function loadMessages(){
        if(loadingMessages || loadedAll || !activeConversation) return;
        loadingMessages=true;
        post('fetch',{conversation_id:activeConversation, limit:200}).then(res=>{
            loadingMessages=false;
            if(!res.ok) return;
            messagesList.innerHTML='';
            (res.messages||[]).forEach(m=>{ messagesList.appendChild(renderMessage(m)); });
            loadedAll=true;
            messagesList.scrollTop = messagesList.scrollHeight;
        });
    }

    function activateConversation(cid){
        if(activeConversation===cid) return;
    activeConversation=cid; loadedAll=false; messagesList.innerHTML='';
        convoIdInput.value=cid;
        threadPanel.style.display='flex';
        [...listEl.children].forEach(li=>li.classList.toggle('active', parseInt(li.dataset.cid)===cid));
        threadTitle.textContent='Conversation #'+cid;
        if(convoMeta[cid] && convoMeta[cid].other_username){
            threadTitle.textContent = convoMeta[cid].other_username + ' (Conversation #'+cid+')';
        }
        loadMessages();
        // Mark read after short delay
        setTimeout(()=>{ post('mark_read',{conversation_id:cid}).then(()=>loadList()); }, 400);
    }

    // Full history is loaded once; no infinite scroll for now.

    form.addEventListener('submit', e=>{
        e.preventDefault();
        const body=bodyInput.value.trim();
        if(!body) return;
        btnSend.disabled=true;
        post('send',{conversation_id:activeConversation, body:body, subject:subjectInput.value}).then(res=>{
            btnSend.disabled=false;
            if(res.ok){
                bodyInput.value='';
                subjectInput.value='';
                // Reload messages to include the new one at end
                loadedAll=false; loadMessages();
                loadList();
            } else {
                alert('Send failed: '+(res.error||''));
            }
        });
    });

    btnStart.addEventListener('click', ()=>{
        const other=parseInt(startUserId.value,10);
        if(!other || other===<?= (int)$userId ?>) { alert('Enter another user id'); return; }
        post('send',{conversation_id:0, other_user_id:other, body:'(started conversation)'}).then(res=>{
            if(res.ok){ loadList(); activateConversation(res.conversation_id); } else { alert('Could not start: '+(res.error||'')); }
        });
    });

    // User search (debounced)
    let searchTimer=null;
    userSearch.addEventListener('input', ()=>{
        const q=userSearch.value.trim();
        if(searchTimer) clearTimeout(searchTimer);
        if(q===''){ userSearchResults.style.display='none'; userSearchResults.innerHTML=''; return; }
        searchTimer=setTimeout(()=>{
            post('user_search',{q}).then(res=>{
                if(!res.ok) return; userSearchResults.innerHTML='';
                if(res.users.length===0){ userSearchResults.style.display='none'; return; }
                res.users.forEach(u=>{
                    const li=document.createElement('li');
                    li.style.padding='6px 8px'; li.style.cursor='pointer'; li.style.borderBottom='1px solid var(--border)';
                    const fn = (u.first_name||'').trim(); const ln=(u.last_name||'').trim();
                    const initials=(fn||ln)?(fn.charAt(0)+ln.charAt(0)).toUpperCase():u.username.substring(0,2).toUpperCase();
                    li.innerHTML = `<span class=\"avatar-chip\">${initials}</span><strong style=\"margin-left:6px;\">${u.username}</strong>` + (fn?` <span style=\"opacity:.65;\">(${fn}${ln?' '+ln:''})</span>`:'');
                    li.addEventListener('click', ()=>{
                        startUserId.value=u.user_id;
                        userSearchResults.innerHTML=''; userSearchResults.style.display='none';
                        post('send',{conversation_id:0, other_user_id:u.user_id, body:'(started conversation)'}).then(r=>{
                            if(r.ok){ loadList(); activateConversation(r.conversation_id); }
                        });
                    });
                    userSearchResults.appendChild(li);
                });
                userSearchResults.lastChild && (userSearchResults.lastChild.style.borderBottom='0');
                userSearchResults.style.display='block';
            });
        }, 280);
    });

    btnMarkRead.addEventListener('click', ()=>{
        if(!activeConversation) return;
        post('mark_read',{conversation_id:activeConversation}).then(()=>loadList());
    });

    loadList();
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
