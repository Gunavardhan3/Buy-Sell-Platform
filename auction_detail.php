<?php
session_start();
require 'config.php';

date_default_timezone_set('Asia/Kolkata'); // ← Set timezone

// 1) Auth check
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id    = $_SESSION['userid'];
$auction_id = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

// 2) Auto‑transition statuses
$conn->query("
  UPDATE auctions
     SET status = 'live'
   WHERE auction_id = $auction_id
     AND status = 'upcoming'
     AND start_time <= NOW()
     AND end_time   > NOW()
");
// $conn->query("
//   UPDATE auctions
//      SET status = 'closed'
//    WHERE auction_id = $auction_id
//      AND status = 'live'
//      AND end_time <= NOW()
// ");
// Move any auction whose end_time has passed to 'closed'
$conn->query(
    "UPDATE auctions
     SET status = 'closed'
   WHERE status IN ('upcoming','live')
     AND end_time <= NOW()"
);

// 3) Fetch auction + item + seller (now including phone, upi_id) + price
$stmt = $conn->prepare("
  SELECT
    a.*,
    i.item_id,
    i.name        AS item_name,
    i.photo_url,
    i.details     AS item_details,
    u.user_id     AS seller_id,
    u.name        AS seller_name,
    u.phone       AS seller_phone,
    u.upi_id      AS seller_upi_id,
    COALESCE(a.final_price, a.initial_price) AS current_price
  FROM auctions a
  JOIN items i   ON i.item_id   = a.item_id
  JOIN users u   ON u.user_id   = a.seller_id
  WHERE a.auction_id = ?
");
$stmt->bind_param('i', $auction_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    die("<div class='container'><p class='error-message'>Auction not found.</p></div>");
}

// 4) Time markers
$now_ts   = time();
$start_ts = strtotime($data['start_time']);
$end_ts   = strtotime($data['end_time']);

// 5) Get highest bid (to determine winner)
$hb = $conn->prepare("
  SELECT b.user_id, b.amount, u.name AS buyer_name, u.phone AS buyer_phone
    FROM bids b
    JOIN users u ON u.user_id = b.user_id
   WHERE b.auction_id = ?
   ORDER BY b.amount DESC
   LIMIT 1
");
$hb->bind_param('i', $auction_id);
$hb->execute();
$result = $hb->get_result();
$highest = $result->fetch_assoc();
$hb->close();

$hasBids    = (bool)$highest;
$winner_id  = $hasBids ? (int)$highest['user_id'] : null;
$winningBid = $hasBids ? (float)$highest['amount'] : null;
$buyer_name = $hasBids ? $highest['buyer_name'] : null;
$buyer_phone = $hasBids ? $highest['buyer_phone'] : null;

// 6) Handle bid submission
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bid_amount'])) {
    $bidAmt = (float)$_POST['bid_amount'];
    if ($data['status']==='live' && $now_ts >= $start_ts && $now_ts < $end_ts) {
        try {
            $b = $conn->prepare("CALL place_bid(?,?,?)");
            $b->bind_param('iid', $auction_id, $user_id, $bidAmt);
            $b->execute();
            $b->close();
            header("Location: auction_detail.php?auction_id=$auction_id");
            exit();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "Bidding is not allowed at this time.";
    }
}

// 7) If auction is closed and user is the winner, insert purchase if not present
if ($data['status'] === 'closed' && $hasBids && $winner_id === $user_id) {
    // Check if purchase already exists
    $check = $conn->prepare("SELECT purchase_id FROM purchases WHERE auction_id = ? AND buyer_id = ?");
    $check->bind_param('ii', $auction_id, $user_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        // Insert purchase record
        $ins = $conn->prepare("INSERT INTO purchases (item_id, buyer_id, seller_id, auction_id, purchase_type, amount) VALUES (?, ?, ?, ?, 'auction', ?)");
        $ins->bind_param('iiiid', $data['item_id'], $user_id, $data['seller_id'], $auction_id, $winningBid);
        $ins->execute();
        $ins->close();
    }
    $check->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($data['item_name']) ?> — Auction</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auction_custom.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="auction-section">
  <div class="auction-card" style="max-width:500px;margin:2rem auto;">
    <div class="auction-thumb">
      <img src="<?= htmlspecialchars($data['photo_url']) ?>" alt="">
    </div>
    <div class="auction-title"><?= htmlspecialchars($data['item_name']) ?></div>
    <div class="auction-seller">Seller: <?= htmlspecialchars($data['seller_name']) ?> (📞 <?= htmlspecialchars($data['seller_phone']) ?>)</div>
    <div class="auction-info">Seller UPI ID: <?= htmlspecialchars($data['seller_upi_id']) ?></div>
    <div class="auction-price">₹<?= number_format($data['current_price'],2) ?></div>
    <div class="auction-info">Starts: <?= $data['start_time'] ?> <br> Ends: <?= $data['end_time'] ?></div>
    <a href="messages.php?user2=<?= $data['seller_id'] ?>" class="auction-btn" style="margin-bottom:1em;">💬 Chat with Seller</a>
    <div class="auction-info" style="margin-bottom:1em;"> <?= nl2br(htmlspecialchars($data['item_details'])) ?> </div>
    <?php if ($error): ?>
      <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($data['status']==='closed' && $hasBids && $winner_id === $user_id): ?>
      <div class="auction-success">
        <p style="font-size:1.5em;animation:winpop 1s infinite alternate;">🎉 You have won the auction! 🎉</p>
        <style>@keyframes winpop { 0%{transform:scale(1);} 100%{transform:scale(1.1);color:#e67e22;} }</style>
        <p>Final Bid: ₹<?= number_format($winningBid,2) ?></p>
        <a href="auction_payment.php?auction_id=<?= $auction_id ?>" class="auction-btn">Pay Now</a>
      </div>
    <?php elseif ($data['status']==='upcoming'): ?>
      <div class="auction-info"><em>Auction starts at <?= $data['start_time'] ?>.</em></div>
    <?php elseif ($data['status']==='live'): ?>
      <form method="post" class="auction-form-custom">
        <input 
          type="number" 
          name="bid_amount" 
          step="0.01" 
          min="<?= $data['current_price'] + 0.01 ?>" 
          placeholder="Enter ≥ ₹<?= $data['current_price'] + 0.01 ?>" 
          required>
        <button type="submit">Place Bid</button>
      </form>
    <?php elseif ($data['status']==='closed' && !$hasBids): ?>
      <div class="auction-info"><em>No bids placed. Item unsold.</em></div>
    <?php elseif ($data['status']==='closed'): ?>
      <div class="auction-info"><em>Auction ended. You did not win.</em></div>
    <?php endif; ?>
    <?php if ($data['status']==='closed' && $hasBids): ?>
      <div class="auction-info" style="background:#f8f8f8;border-radius:8px;">
        <h3>Buyer Information</h3>
        <p><strong>Name:</strong> <?= htmlspecialchars($buyer_name) ?></p>
        <?php if ($buyer_phone): ?><p><strong>Phone:</strong> <?= htmlspecialchars($buyer_phone) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
