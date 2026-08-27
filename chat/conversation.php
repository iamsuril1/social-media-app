<?php
$pageTitle = "Chat";
$pageCss = "chat.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$friend_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($friend_id <= 0 || $friend_id == $user_id) {
    setFlash("Invalid conversation.");
    redirect('/social-media-app/chat/inbox.php');
}

$friend_check = mysqli_prepare($conn, "SELECT id FROM friends 
                                        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
                                        AND status = 'accepted'");
mysqli_stmt_bind_param($friend_check, "iiii", $user_id, $friend_id, $friend_id, $user_id);
mysqli_stmt_execute($friend_check);
mysqli_stmt_store_result($friend_check);

if (mysqli_stmt_num_rows($friend_check) === 0) {
    mysqli_stmt_close($friend_check);
    setFlash("You can only chat with friends.");
    redirect('/social-media-app/chat/inbox.php');
}
mysqli_stmt_close($friend_check);

$friend_stmt = mysqli_prepare($conn, "SELECT id, name, profile_pic FROM users WHERE id = ?");
mysqli_stmt_bind_param($friend_stmt, "i", $friend_id);
mysqli_stmt_execute($friend_stmt);
$friend = mysqli_fetch_assoc(mysqli_stmt_get_result($friend_stmt));
mysqli_stmt_close($friend_stmt);

if (!$friend) {
    setFlash("User not found.");
    redirect('/social-media-app/chat/inbox.php');
}

$mark_read_stmt = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($mark_read_stmt, "ii", $friend_id, $user_id);
mysqli_stmt_execute($mark_read_stmt);
mysqli_stmt_close($mark_read_stmt);

$messages_stmt = mysqli_prepare($conn, "SELECT id, sender_id, message, created_at
                                         FROM messages
                                         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                                         ORDER BY created_at ASC");
mysqli_stmt_bind_param($messages_stmt, "iiii", $user_id, $friend_id, $friend_id, $user_id);
mysqli_stmt_execute($messages_stmt);
$messages = mysqli_fetch_all(mysqli_stmt_get_result($messages_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($messages_stmt);

$last_message_id = !empty($messages) ? end($messages)['id'] : 0;
?>

<div class="chat-window-card">

    <div class="chat-window-header">
        <a href="/social-media-app/chat/inbox.php" class="chat-back-btn">←</a>
        <div class="chat-header-avatar">
            <?php echo renderAvatar($friend['name'], $friend['profile_pic'], 'convo-avatar-img'); ?>
        </div>
        <span class="chat-header-name"><?php echo htmlspecialchars($friend['name']); ?></span>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if (empty($messages)): ?>
            <div class="chat-empty-state" id="emptyState">
                <p>No messages yet</p>
                <span class="placeholder-subtext">Send the first message to <?php echo htmlspecialchars($friend['name']); ?>!</span>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php $is_mine = $msg['sender_id'] == $user_id; ?>
                <div class="message-row <?php echo $is_mine ? 'mine' : 'theirs'; ?>">
                    <div class="message-bubble">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        <span class="message-time"><?php echo date("g:i A", strtotime($msg['created_at'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form id="chatForm" class="chat-input-row">
        <input type="text" name="message" id="messageInput" placeholder="Type a message..." autocomplete="off" required maxlength="1000">
        <button type="submit" class="chat-send-btn" id="sendBtn">Send</button>
    </form>

</div>

<script>
const friendId = <?php echo $friend_id; ?>;
let lastMessageId = <?php echo (int) $last_message_id; ?>;

const chatBox = document.getElementById('chatMessages');
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');

chatBox.scrollTop = chatBox.scrollHeight;

function appendMessage(text, isMine, time) {
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.remove();

    const row = document.createElement('div');
    row.className = 'message-row ' + (isMine ? 'mine' : 'theirs');

    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>') + '<span class="message-time">' + time + '</span>';

    row.appendChild(bubble);
    chatBox.appendChild(row);
    chatBox.scrollTop = chatBox.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

let pollTimer = null;
let conversationEnded = false;

function lockConversation(message) {
    conversationEnded = true;
    if (pollTimer) clearInterval(pollTimer);

    input.disabled = true;
    sendBtn.disabled = true;
    input.placeholder = message;

    const notice = document.createElement('div');
    notice.className = 'chat-ended-notice';
    notice.textContent = message;
    chatBox.appendChild(notice);
    chatBox.scrollTop = chatBox.scrollHeight;
}

form.addEventListener('submit', function(e) {
    e.preventDefault();
    if (conversationEnded) return;

    const text = input.value.trim();
    if (text === '') return;

    sendBtn.disabled = true;

    fetch('/social-media-app/chat/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'friend_id=' + encodeURIComponent(friendId) + '&message=' + encodeURIComponent(text)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            appendMessage(data.message, true, data.time);
            lastMessageId = data.message_id;
            input.value = '';
            sendBtn.disabled = false;
            input.focus();
        } else if (data.forbidden) {
            lockConversation(data.message);
        } else {
            alert(data.message || 'Could not send message.');
            sendBtn.disabled = false;
        }
    })
    .catch(() => {
        sendBtn.disabled = false;
    });
});

function pollMessages() {
    if (conversationEnded) return;

    fetch('/social-media-app/chat/poll.php?friend_id=' + friendId + '&after_id=' + lastMessageId)
        .then(r => r.json())
        .then(data => {
            if (data.forbidden) {
                lockConversation('You are no longer friends with this user.');
                return;
            }
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendMessage(msg.message, msg.is_mine, msg.time);
                    lastMessageId = msg.id;
                });
            }
        })
        .catch(() => {});
}

pollTimer = setInterval(pollMessages, 3000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>