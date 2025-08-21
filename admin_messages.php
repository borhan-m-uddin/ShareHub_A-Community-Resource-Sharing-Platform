<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$all_messages = [];

// Fetch all messages from the system
$sql = "SELECT m.message_id, m.subject, m.message_content, m.sent_date, m.is_read, m.request_id,
               u_sender.username as sender_username, u_sender.email as sender_email,
               u_receiver.username as receiver_username, u_receiver.email as receiver_email
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.user_id
        JOIN users u_receiver ON m.receiver_id = u_receiver.user_id
        ORDER BY m.sent_date DESC";

if($stmt = $conn->prepare($sql)){
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_messages[] = $row;
        }
        $result->free();
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Handle message deletion
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_message_id"])){
    $message_id = $_POST["delete_message_id"];
    
    $delete_sql = "DELETE FROM messages WHERE message_id = ?";
    if($stmt = $conn->prepare($delete_sql)){
        $stmt->bind_param("i", $message_id);
        if($stmt->execute()){
            header("location: admin_messages.php");
            exit;
        }
        $stmt->close();
    }
}

// Handle marking all messages as read
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mark_all_read"])){
    $update_sql = "UPDATE messages SET is_read = 1 WHERE is_read = 0";
    if($stmt = $conn->prepare($update_sql)){
        if($stmt->execute()){
            header("location: admin_messages.php");
            exit;
        }
        $stmt->close();
    }
}

// Calculate statistics
$stats = [
    'total' => count($all_messages),
    'unread' => 0,
    'today' => 0,
    'this_week' => 0
];

foreach ($all_messages as $message) {
    if ($message['is_read'] == 0) $stats['unread']++;
    if (date('Y-m-d', strtotime($message['sent_date'])) == date('Y-m-d')) $stats['today']++;
    if (strtotime($message['sent_date']) >= strtotime('-7 days')) $stats['this_week']++;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monitor Messages - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .messages-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .messages-table th, .messages-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .messages-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .message-unread {
            background-color: #e3f2fd;
            font-weight: bold;
        }
        .action-buttons form { display: inline-block; margin-right: 5px; }
        .action-buttons .btn { padding: 4px 8px; font-size: 0.8em; }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        .stat-card h4 { margin: 0 0 5px 0; color: #333; }
        .stat-card .number { font-size: 1.5em; font-weight: bold; color: #007cba; }
        .message-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .bulk-actions {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        .search-filters {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bbdefb;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-row input, .filter-row select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>💬 Admin - Monitor Messages</h2>
        <p>Administrative overview of all system messages and communications.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h4>Total Messages</h4>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Unread Messages</h4>
                <div class="number" style="color: #dc3545;"><?php echo $stats['unread']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Messages Today</h4>
                <div class="number" style="color: #28a745;"><?php echo $stats['today']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Messages This Week</h4>
                <div class="number" style="color: #007cba;"><?php echo $stats['this_week']; ?></div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <h4>🛠️ Bulk Actions</h4>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display: inline;">
                <input type="submit" name="mark_all_read" value="📧 Mark All as Read" class="btn btn-info"
                       onclick="return confirm('Mark all messages as read?');">
            </form>
            <button onclick="exportMessages()" class="btn btn-success">📄 Export Messages</button>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <h4>🔍 Search & Filter</h4>
            <div class="filter-row">
                <input type="text" id="searchSender" placeholder="Search by sender..." onkeyup="filterMessages()">
                <input type="text" id="searchReceiver" placeholder="Search by receiver..." onkeyup="filterMessages()">
                <input type="text" id="searchSubject" placeholder="Search by subject..." onkeyup="filterMessages()">
                <select id="filterRead" onchange="filterMessages()">
                    <option value="">All Messages</option>
                    <option value="unread">Unread Only</option>
                    <option value="read">Read Only</option>
                </select>
            </div>
        </div>

        <?php if (!empty($all_messages)): ?>
            <table class="messages-table" id="messagesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Sender</th>
                        <th>Receiver</th>
                        <th>Preview</th>
                        <th>Request</th>
                        <th>Status</th>
                        <th>Sent Date</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_messages as $message): ?>
                        <tr class="<?php echo $message['is_read'] == 0 ? 'message-unread' : ''; ?>" 
                            data-sender="<?php echo htmlspecialchars($message['sender_username']); ?>"
                            data-receiver="<?php echo htmlspecialchars($message['receiver_username']); ?>"
                            data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                            data-read="<?php echo $message['is_read'] == 0 ? 'unread' : 'read'; ?>">
                            <td><?php echo $message["message_id"]; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($message["subject"]); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($message["sender_username"]); ?></strong><br>
                                <small><?php echo htmlspecialchars($message["sender_email"]); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($message["receiver_username"]); ?></strong><br>
                                <small><?php echo htmlspecialchars($message["receiver_email"]); ?></small>
                            </td>
                            <td>
                                <div class="message-preview" title="<?php echo htmlspecialchars($message['message_content']); ?>">
                                    <?php echo htmlspecialchars(substr($message['message_content'], 0, 100)); ?>
                                    <?php echo strlen($message['message_content']) > 100 ? '...' : ''; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($message["request_id"]): ?>
                                    <span class="badge badge-info">Request #<?php echo $message["request_id"]; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">General</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($message['is_read'] == 0): ?>
                                    <span style="color: #dc3545; font-weight: bold;">📧 Unread</span>
                                <?php else: ?>
                                    <span style="color: #28a745;">📖 Read</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($message["sent_date"])); ?></td>
                            <td class="action-buttons">
                                <button onclick="showFullMessage(<?php echo $message['message_id']; ?>)" class="btn btn-info">👁️ View</button>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" 
                                      onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.');">
                                    <input type="hidden" name="delete_message_id" value="<?php echo $message["message_id"]; ?>">
                                    <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <strong>No messages found</strong><br>
                There are no messages in the system yet.
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for viewing full messages -->
    <div id="messageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; border-radius:8px; max-width:600px; max-height:500px; overflow-y:auto;">
            <h3>Full Message</h3>
            <div id="messageContent"></div>
            <button onclick="document.getElementById('messageModal').style.display='none'" class="btn btn-secondary" style="margin-top:15px;">Close</button>
        </div>
    </div>

    <script>
        const messages = <?php echo json_encode($all_messages); ?>;
        
        function showFullMessage(messageId) {
            const message = messages.find(m => m.message_id == messageId);
            if (message) {
                document.getElementById('messageContent').innerHTML = 
                    '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">' +
                    '<strong>From:</strong> ' + message.sender_username + ' (' + message.sender_email + ')<br>' +
                    '<strong>To:</strong> ' + message.receiver_username + ' (' + message.receiver_email + ')<br>' +
                    '<strong>Subject:</strong> ' + message.subject + '<br>' +
                    '<strong>Date:</strong> ' + new Date(message.sent_date).toLocaleString() + '<br>' +
                    '<strong>Status:</strong> ' + (message.is_read == 0 ? 'Unread' : 'Read') + '<br>' +
                    (message.request_id ? '<strong>Related Request:</strong> #' + message.request_id + '<br>' : '') +
                    '</div>' +
                    '<strong>Message:</strong><br><div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: white;">' + 
                    message.message_content.replace(/\n/g, '<br>') + '</div>';
                document.getElementById('messageModal').style.display = 'block';
            }
        }

        function filterMessages() {
            const senderFilter = document.getElementById('searchSender').value.toLowerCase();
            const receiverFilter = document.getElementById('searchReceiver').value.toLowerCase();
            const subjectFilter = document.getElementById('searchSubject').value.toLowerCase();
            const readFilter = document.getElementById('filterRead').value;
            
            const rows = document.querySelectorAll('#messagesTable tbody tr');
            
            rows.forEach(row => {
                const sender = row.getAttribute('data-sender').toLowerCase();
                const receiver = row.getAttribute('data-receiver').toLowerCase();
                const subject = row.getAttribute('data-subject').toLowerCase();
                const readStatus = row.getAttribute('data-read');
                
                const senderMatch = !senderFilter || sender.includes(senderFilter);
                const receiverMatch = !receiverFilter || receiver.includes(receiverFilter);
                const subjectMatch = !subjectFilter || subject.includes(subjectFilter);
                const readMatch = !readFilter || readStatus === readFilter;
                
                if (senderMatch && receiverMatch && subjectMatch && readMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function exportMessages() {
            // Simple CSV export
            let csv = 'ID,Subject,Sender,Receiver,Status,Date,Message\n';
            messages.forEach(msg => {
                csv += `${msg.message_id},"${msg.subject}","${msg.sender_username}","${msg.receiver_username}","${msg.is_read == 0 ? 'Unread' : 'Read'}","${msg.sent_date}","${msg.message_content.replace(/"/g, '""')}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'messages_export_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
