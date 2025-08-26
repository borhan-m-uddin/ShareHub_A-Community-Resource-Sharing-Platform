<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

$messages = [];
$user_id = $_SESSION["user_id"];

// Handle sending a new message
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_message"])){
    $receiver_id = $_POST["receiver_id"];
    $subject = $_POST["subject"];
    $message_content = $_POST["message_content"];
    $request_id = !empty($_POST["request_id"]) ? $_POST["request_id"] : null;
    
    $sql = "INSERT INTO messages (sender_id, receiver_id, request_id, subject, message_content) VALUES (?, ?, ?, ?, ?)";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("iiiss", $user_id, $receiver_id, $request_id, $subject, $message_content);
        if($stmt->execute()){
            $success_message = "Message sent successfully!";
        } else {
            $error_message = "Error sending message.";
        }
        $stmt->close();
    }
}

// Mark messages as read
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mark_read"])){
    $message_id = $_POST["message_id"];
    $sql = "UPDATE messages SET is_read = 1 WHERE message_id = ? AND receiver_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("ii", $message_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get all users for the message recipient dropdown
$users = [];
$sql = "SELECT user_id, username, first_name, last_name FROM users WHERE user_id != ? ORDER BY username";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $users[] = $row;
        }
    }
    $stmt->close();
}

// Fetch messages (both sent and received)
$sql = "SELECT m.message_id, m.sender_id, m.receiver_id, m.subject, m.message_content, m.sent_date, m.is_read, m.request_id,
               CASE 
                   WHEN m.sender_id = ? THEN 'sent'
                   WHEN m.receiver_id = ? THEN 'received'
               END as message_type,
               CASE 
                   WHEN m.sender_id = ? THEN u_receiver.username
                   WHEN m.receiver_id = ? THEN u_sender.username
               END as other_user
        FROM messages m
        LEFT JOIN users u_sender ON m.sender_id = u_sender.user_id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.sent_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $messages[] = $row;
        }
    }
    $stmt->close();
}

// Keep connection open
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>

    <div class="wrapper">
        <h2>💬 Messages</h2>
        <p>Send and receive messages with other community members.</p>
        <p>
            <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary">← Back to Dashboard</a>
        </p>

        <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="grid grid-2">
            <!-- Compose Message Section -->
            <div class="card">
                <div class="card-body">
                <h3>✍️ Compose Message</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="receiver_id">To:</label>
                        <select name="receiver_id" id="receiver_id" class="form-control" required>
                            <option value="">Select recipient...</option>
                            <?php foreach($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if($user['first_name'] || $user['last_name']): ?>
                                        (<?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" name="subject" id="subject" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="message_content">Message:</label>
                        <textarea name="message_content" id="message_content" class="form-control" rows="6" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="request_id">Related Request (optional):</label>
                        <input type="number" name="request_id" id="request_id" class="form-control" placeholder="Request ID if this message is about a specific request">
                    </div>

                    <div class="form-group">
                        <input type="submit" name="send_message" value="📤 Send Message" class="btn btn-primary">
                    </div>
                </form>
                </div>
            </div>

            <!-- Messages List Section -->
            <div class="card" style="max-height: 640px; overflow-y:auto;">
                <div class="card-body" style="border-bottom:1px solid var(--border);">
                    <h3>📬 Your Messages</h3>
                    <p class="muted" style="margin:0;">
                        <?php 
                        $unread_count = count(array_filter($messages, function($msg) { 
                            return $msg['message_type'] == 'received' && $msg['is_read'] == 0; 
                        }));
                        echo "Total: " . count($messages) . " messages";
                        if($unread_count > 0) {
                            echo " | Unread: " . $unread_count;
                        }
                        ?>
                    </p>
                </div>

                <?php if (!empty($messages)): ?>
                    <div class="list">
                    <?php foreach($messages as $message): ?>
                        <div class="list-item" onclick="toggleMessage(<?php echo $message['message_id']; ?>)">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                                <span class="badge <?php echo $message['message_type'] == 'sent' ? 'badge-success' : 'badge-primary'; ?>">
                                    <?php echo ucfirst($message['message_type']); ?>
                                </span>
                                <span class="muted" style="font-size:0.85rem;">
                                    <?php echo date('M j, Y g:i A', strtotime($message['sent_date'])); ?>
                                </span>
                            </div>
                            <div style="font-weight:800; color:#0f172a;">
                                <?php echo htmlspecialchars($message['subject']); ?>
                            </div>
                            <div class="muted" style="margin-top:3px;">
                                <?php echo $message['message_type'] == 'sent' ? 'To: ' : 'From: '; ?>
                                <strong><?php echo htmlspecialchars($message['other_user']); ?></strong>
                                <?php if($message['request_id']): ?>
                                    | Request #<?php echo $message['request_id']; ?>
                                <?php endif; ?>
                            </div>
                            <div class="muted" style="font-size:0.95rem; margin-top:6px;">
                                <?php echo htmlspecialchars(substr($message['message_content'], 0, 160)); ?>
                                <?php echo strlen($message['message_content']) > 160 ? '...' : ''; ?>
                            </div>
                            
                            <!-- Full message content (hidden by default) -->
                            <div id="message-full-<?php echo $message['message_id']; ?>" style="display: none; margin-top: 10px; padding: 10px; background-color: var(--card); border-radius: 8px; border: 1px solid var(--border);">
                                <strong>Full Message:</strong><br>
                                <?php echo nl2br(htmlspecialchars($message['message_content'])); ?>
                                
                                <?php if($message['message_type'] == 'received' && $message['is_read'] == 0): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="margin-top: 10px;">
                                        <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                        <input type="submit" name="mark_read" value="Mark as Read" class="btn btn-success btn-sm">
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No messages yet</h3>
                        <p>Start a conversation by sending a message to another community member!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleMessage(messageId) {
            var fullMessage = document.getElementById('message-full-' + messageId);
            if (fullMessage.style.display === 'none') {
                fullMessage.style.display = 'block';
            } else {
                fullMessage.style.display = 'none';
            }
        }
    </script>
</main>
</body>
</html>
