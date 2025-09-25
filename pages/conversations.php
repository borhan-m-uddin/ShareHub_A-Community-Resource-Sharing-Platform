<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
verification_require();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . site_href('pages/index.php')); exit; }
if (function_exists('conversations_ensure_schema')) { conversations_ensure_schema(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Conversations</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body class="page-conversations">
<?php render_header(); ?>
<div class="wrapper">
    <div class="card" style="margin-bottom:14px;">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <h1 style="margin:0 0 4px 0;">Messages</h1>
                <p class="muted" style="margin:0;">Chat with other users. Start a new conversation from the sidebar or the New Message button.</p>
            </div>
            <div><button class="btn btn-primary" id="openNewMessage" type="button">New Message</button></div>
        </div>
    </div>
    <div class="conversations-grid" style="min-height:560px;">
        <div class="convo-sidebar" role="navigation" aria-label="Conversation list">
            <div class="card" style="flex:1;display:flex;flex-direction:column;">
                <div class="card-body" style="display:flex;flex-direction:column;flex:1;gap:10px;">
                    <div class="convo-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                        <span>Your Conversations</span>
                        <button class="btn btn-default btn-sm" id="refreshList" type="button" title="Refresh">↻</button>
                    </div>
                    <div class="start-box" style="flex-direction:column;gap:6px;align-items:stretch;">
                        <input type="text" id="userSearch" placeholder="Search user by name..." />
                        <ul id="userSearchResults" style="list-style:none;margin:0;padding:0;max-height:160px;overflow:auto;border:1px solid var(--border);border-radius:6px;display:none;background:var(--card);"></ul>
                    </div>
                    <ul class="convo-list" id="convoItems" role="list" aria-live="polite" aria-label="Your conversations"></ul>
                    <div id="convoEmpty" class="muted" style="display:none;text-align:center;padding:10px 0;" role="status">No conversations yet. Start one!</div>
                </div>
            </div>
        </div>
        <div class="thread-card" id="threadPanel" style="display:none;" aria-live="polite" aria-label="Conversation thread">
            <div class="card" style="flex:1;display:flex;flex-direction:column;">
                <div class="card-body" style="display:flex;flex-direction:column;flex:1;">
                    <div class="thread-head">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="avatar-chip" id="threadAvatar">👤</span>
                            <div><div id="threadTitle" style="font-weight:700;">Thread</div><small id="threadMeta" class="muted"></small></div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;">
                            <button id="btnMarkRead" class="btn btn-default btn-sm" type="button">Mark Read</button>
                        </div>
                    </div>
                    <div class="messages" id="messagesList" role="log" aria-live="polite" aria-relevant="additions" aria-label="Messages"></div>
                    <form class="composer" id="composeForm" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="conversation_id" id="conversation_id" value="0" />
                        <div class="composer-shell">
                            <textarea name="body" id="body" placeholder="Write a message... (Enter to send, Shift+Enter for newline)" required></textarea>
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
 const listEl=document.getElementById('convoItems');
 const threadPanel=document.getElementById('threadPanel');
 const messagesList=document.getElementById('messagesList');
 const convoIdInput=document.getElementById('conversation_id');
 const form=document.getElementById('composeForm');
 const bodyInput=document.getElementById('body');
 const subjectInput=document.getElementById('subject');
 const btnSend=document.getElementById('btnSend');
 const userSearch=document.getElementById('userSearch');
 const userSearchResults=document.getElementById('userSearchResults');
 const btnMarkRead=document.getElementById('btnMarkRead');
 const threadTitle=document.getElementById('threadTitle');
 const threadAvatar=document.getElementById('threadAvatar');
 const threadMeta=document.getElementById('threadMeta');
 const btnOpenNew=document.getElementById('openNewMessage');
 const btnRefreshList=document.getElementById('refreshList');
 const convoEmpty=document.getElementById('convoEmpty');
 let activeConversation=0; let loadingMessages=false; let convoMeta={}; let lastMsgId=0; let pollTimer=null;
 function qs(o){return new URLSearchParams(Object.entries(o).map(([k,v])=>[k,String(v)])).toString();}
 function post(action,data){data.action=action; data.csrf_token='<?= e(csrf_token()) ?>'; return fetch('<?= site_href('pages/api/conversations.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:qs(data)}).then(r=>r.json());}
 function loadList(){post('list',{}).then(res=>{ if(!res.ok) return; listEl.innerHTML=''; const items=res.conversations||[]; convoEmpty.style.display=items.length?'none':'block'; items.forEach(c=>{ convoMeta[c.conversation_id]=c; const li=document.createElement('li'); li.className='list-item'; li.dataset.cid=c.conversation_id; const name=c.other_username?c.other_username:('#'+c.conversation_id); const initials=(name||'U').split(/\s+/).map(p=>p[0]).slice(0,2).join('').toUpperCase(); const time=c.last_msg_at? new Date(c.last_msg_at.replace(' ','T')).toLocaleString():''; li.innerHTML=`<div style="display:flex;align-items:center;gap:10px;"><span class="avatar-chip">${initials}</span><div style="flex:1;min-width:0;"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center;"><strong>${name}</strong>${c.unread_count>0?`<span class=\"badge-unread\" title=\"Unread\">${c.unread_count}</span>`:''}</div><div class="subtitle" title="${time}">${(c.last_msg_body||'').replace(/</g,'&lt;')}</div></div></div>`; li.addEventListener('click',()=>activateConversation(parseInt(c.conversation_id))); listEl.appendChild(li); }); if(!activeConversation){ const params=new URLSearchParams(window.location.search); const openId=parseInt(params.get('open')||'0',10); if(openId>0){ const exists=[...listEl.children].some(li=>parseInt(li.dataset.cid)===openId); if(exists) activateConversation(openId); else if(items.length>0) activateConversation(parseInt(items[0].conversation_id)); } }
 });}
 function formatDateLabel(d){const today=new Date(); const dd=new Date(d.getFullYear(),d.getMonth(),d.getDate()); const td=new Date(today.getFullYear(),today.getMonth(),today.getDate()); const diff=(dd-td)/86400000; if(diff===0)return'Today'; if(diff===-1)return'Yesterday'; return d.toLocaleDateString(); }
 function renderDateSeparator(label){const sep=document.createElement('div'); sep.style.textAlign='center'; sep.style.margin='8px 0'; sep.innerHTML=`<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted);font-size:12px;">${label}</span>`; sep.dataset.dateLabel=label; return sep; }
 function renderMessage(m){const mine=m.sender_id==<?= (int)$userId ?>; const div=document.createElement('div'); div.className='msg'+(mine?' mine':''); const date=new Date(m.sent_date.replace(' ','T')); const subject=(m.subject||'').trim(); div.innerHTML=`<div style=\"white-space:pre-wrap;\">${subject?`<div style='font-size:12px;opacity:.7;margin-bottom:4px;'><strong>${subject.replace(/</g,'&lt;')}</strong></div>`:''}${(m.message_content||'').replace(/</g,'&lt;')}</div><small>${date.toLocaleString()}</small>`; return div; }
 function loadMessages(initial=false){
     if(loadingMessages||!activeConversation)return;
     loadingMessages=true;
     const since=initial?0:lastMsgId;
     post('fetch',{conversation_id:activeConversation,limit:200,before:since}).then(res=>{
         loadingMessages=false;
         if(!res.ok)return;
         let list=res.messages||[];
         // Ensure chronological order: oldest -> newest
         list.sort((a,b)=> new Date(a.sent_date.replace(' ','T')) - new Date(b.sent_date.replace(' ','T')));
         if(initial){ messagesList.innerHTML=''; lastMsgId=0; }
         let lastDateLabel=null;
         list.forEach(m=>{
             lastMsgId=Math.max(lastMsgId,parseInt(m.message_id));
             const d=new Date(m.sent_date.replace(' ','T'));
             const label=formatDateLabel(d);
             // avoid duplicate separators if same label is already last child
             if(label!==lastDateLabel){ messagesList.appendChild(renderDateSeparator(label)); lastDateLabel=label; }
             messagesList.appendChild(renderMessage(m));
         });
         if(initial || list.length){
             // scroll to bottom; use rAF to ensure DOM drawn
             requestAnimationFrame(()=>{ messagesList.scrollTop=messagesList.scrollHeight; });
         }
     });
 }
 function activateConversation(cid){ if(activeConversation===cid)return; activeConversation=cid; messagesList.innerHTML=''; lastMsgId=0; convoIdInput.value=cid; threadPanel.style.display='flex'; [...listEl.children].forEach(li=>li.classList.toggle('active',parseInt(li.dataset.cid)===cid)); const name=(convoMeta[cid]&&convoMeta[cid].other_username)?convoMeta[cid].other_username:('Conversation #'+cid); threadTitle.textContent=name; const initials=name.split(/\s+/).map(p=>p[0]).slice(0,2).join('').toUpperCase(); threadAvatar.textContent=initials; threadMeta.textContent=convoMeta[cid]&&convoMeta[cid].last_msg_at?('Last activity '+new Date(convoMeta[cid].last_msg_at.replace(' ','T')).toLocaleString()):''; loadMessages(true); setTimeout(()=>{ post('mark_read',{conversation_id:cid}).then(()=>loadList()); },400); startPolling(); setTimeout(()=>{ bodyInput.focus({preventScroll:false}); },60); }
 form.addEventListener('submit',e=>{
     e.preventDefault();
     const body=bodyInput.value.trim();
     if(!body)return;
     btnSend.disabled=true;
     post('send',{conversation_id:activeConversation,body:body,subject:subjectInput.value}).then(res=>{
         btnSend.disabled=false;
         if(res.ok){
             bodyInput.value=''; subjectInput.value='';
             loadMessages(); loadList();
             requestAnimationFrame(()=>{ messagesList.scrollTop=messagesList.scrollHeight; });
             bodyInput.focus({preventScroll:true});
         } else alert('Send failed: '+(res.error||''));
     });
 });
 let searchTimer=null; userSearch.addEventListener('input',()=>{ const q=userSearch.value.trim(); if(searchTimer)clearTimeout(searchTimer); if(q===''){ userSearchResults.style.display='none'; userSearchResults.innerHTML=''; return; } searchTimer=setTimeout(()=>{ post('user_search',{q}).then(res=>{ if(!res.ok)return; userSearchResults.innerHTML=''; if(!res.users.length){ userSearchResults.style.display='none'; return; } res.users.forEach(u=>{ const li=document.createElement('li'); li.style.padding='6px 8px'; li.style.cursor='pointer'; li.style.borderBottom='1px solid var(--border)'; const fn=(u.first_name||'').trim(); const ln=(u.last_name||'').trim(); const initials=(fn||ln)?(fn.charAt(0)+ln.charAt(0)).toUpperCase():u.username.substring(0,2).toUpperCase(); li.innerHTML=`<span class=\"avatar-chip\">${initials}</span><strong style=\"margin-left:6px;\">${u.username}</strong>${fn?` <span style=\"opacity:.65;\">(${fn}${ln?' '+ln:''})</span>`:''}`; li.addEventListener('click',()=>{ userSearchResults.innerHTML=''; userSearchResults.style.display='none'; post('send',{conversation_id:0,other_user_id:u.user_id,body:'(started conversation)'}).then(r=>{ if(r.ok){ loadList(); activateConversation(r.conversation_id); } }); }); userSearchResults.appendChild(li); }); userSearchResults.lastChild && (userSearchResults.lastChild.style.borderBottom='0'); userSearchResults.style.display='block'; }); },280); });
 btnMarkRead.addEventListener('click',()=>{ if(!activeConversation)return; post('mark_read',{conversation_id:activeConversation}).then(()=>loadList()); });
 bodyInput.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); form.requestSubmit(); } });
 function startPolling(){ if(pollTimer)clearInterval(pollTimer); pollTimer=setInterval(()=>{ if(document.hidden)return; if(activeConversation) loadMessages(false); loadList(); },5000); }
 btnRefreshList && btnRefreshList.addEventListener('click',()=>loadList());
 btnOpenNew && btnOpenNew.addEventListener('click',()=>userSearch.focus());
 loadList(); startPolling();
})();
</script>
<?php render_footer(); ?>
</body>
</html>