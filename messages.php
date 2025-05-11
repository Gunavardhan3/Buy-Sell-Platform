<?php
session_start();
require 'config.php';

// 1) Authentication
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user1 = $_SESSION['userid'];

// 2) If a chat partner is selected → show the chat box
if (isset($_GET['user2'])) {
    $user2 = (int)$_GET['user2'];

    // 2a) Find or create the conversation
    $q = $conn->prepare("
      SELECT conversation_id
        FROM conversations
       WHERE (user1_id=? AND user2_id=?)
          OR (user1_id=? AND user2_id=?)
    ");
    $q->bind_param('iiii', $user1, $user2, $user2, $user1);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows) {
        $conv = $r->fetch_assoc()['conversation_id'];
    } else {
        $ins = $conn->prepare("
          INSERT INTO conversations(user1_id,user2_id)
          VALUES(?,?)
        ");
        $ins->bind_param('ii', $user1, $user2);
        $ins->execute();
        $conv = $ins->insert_id;
        $ins->close();
    }
    $q->close();

    // 2b) Handle sending a new message
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['msg'])) {
        $txt = $conn->real_escape_string($_POST['msg']);
        $insm = $conn->prepare("
          INSERT INTO messages(conversation_id,sender_id,content)
          VALUES(?,?,?)
        ");
        $insm->bind_param('iis', $conv, $user1, $txt);
        $insm->execute();
        $insm->close();
        header("Location: messages.php?user2=$user2");
        exit();
    }

    // 2c) Fetch all messages in this conversation
    $mRes = $conn->prepare("
      SELECT m.content,
             m.sent_at,
             u.name    AS sender,
             u.user_id
        FROM messages m
        JOIN users u ON u.user_id = m.sender_id
       WHERE m.conversation_id=?
       ORDER BY m.sent_at
    ");
    $mRes->bind_param('i', $conv);
    $mRes->execute();
    $msgs = $mRes->get_result();
    $mRes->close();

    // 2d) Fetch partner’s name
    $uq = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $uq->bind_param('i', $user2);
    $uq->execute();
    $ur = $uq->get_result();
    $u  = $ur->fetch_assoc()['name'];
    $uq->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Chat with <?= htmlspecialchars($u) ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="style.css">
      <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    </head>
    <body class="dashboard-page messages-page">

      <?php include 'nav.php'; ?>

      <div class="container">
        <h2>Chat with <?= htmlspecialchars($u) ?></h2>

        <div class="messages-page__chat-box">
          <?php while ($m = $msgs->fetch_assoc()): ?>
            <div class="messages-page__chat-message <?= $m['user_id']==$user1 ? 'outgoing' : 'incoming' ?>">
              <p class="messages-page__sender"><?= htmlspecialchars($m['sender']) ?>:</p>
              <p class="messages-page__content"><?= nl2br(htmlspecialchars($m['content'])) ?></p>
              <span class="messages-page__time">
                <?= date('H:i, M j', strtotime($m['sent_at'])) ?>
              </span>
            </div>
          <?php endwhile; ?>
        </div>

        <form class="messages-page__chat-form" method="post">
          <textarea name="msg" placeholder="Type your message…" required></textarea>
          <button type="submit" class="btn">Send</button>
        </form>
      </div>

    </body>
    </html>
    <?php
    exit();
}

// 3) Otherwise → show the conversation list
$cRes = $conn->prepare("
  SELECT c.conversation_id,
         IF(c.user1_id=?,c.user2_id,c.user1_id) AS other_id,
         u.name
    FROM conversations c
    JOIN users u 
      ON u.user_id = IF(c.user1_id=?,c.user2_id,c.user1_id)
   WHERE c.user1_id=? OR c.user2_id=?
   ORDER BY c.conversation_id DESC
");
$cRes->bind_param('iiii', $user1, $user1, $user1, $user1);
$cRes->execute();
$chats = $cRes->get_result();
$cRes->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Conversations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
</head>
<body class="dashboard-page messages-page">

  <?php include 'nav.php'; ?>

  <div class="container">
    <h2>Your Conversations</h2>

    <?php if ($chats->num_rows): ?>
      <ul class="messages-page__conversation-list">
        <?php while ($c = $chats->fetch_assoc()): ?>
          <li>
            <a href="messages.php?user2=<?= $c['other_id'] ?>">
              <i class="ti ti-user-circle"></i>
              <span><?= htmlspecialchars($c['name']) ?></span>
            </a>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p class="messages-page__no-conv">No conversations yet.</p>
    <?php endif; ?>
  </div>

</body>
</html>
