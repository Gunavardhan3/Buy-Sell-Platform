<?php
// auction_payment.php
session_start();
require 'config.php';
include 'nav.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id    = $_SESSION['userid'];
$auction_id = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
if (!$auction_id) {
    echo "<div class='container'><p class='error-message'>No auction specified.</p></div>";
    exit();
}

// 1) Fetch the winning purchase + auction QR for this auction
$stmt = $conn->prepare("
  SELECT 
    p.purchase_id,
    p.item_id,
    p.amount,
    p.seller_id,
    u.upi_id     AS seller_upi,
    a.qr_image   AS auction_qr,      -- QR from auctions table
    i.name       AS item_name
  FROM purchases p
  JOIN auctions a ON a.auction_id = p.auction_id
  JOIN users   u ON u.user_id    = p.seller_id
  JOIN items   i ON i.item_id    = p.item_id
  WHERE p.auction_id    = ?
    AND p.buyer_id      = ?
    AND p.purchase_type = 'auction'
  LIMIT 1
");
$stmt->bind_param('ii', $auction_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<div class='container'><p class='error-message'>You have no winning purchase for this auction.</p></div>";
    exit();
}

// extract into variables
$purchase_id = (int)$data['purchase_id'];
$item_id     = (int)$data['item_id'];
$amount      = (float)$data['amount'];
$seller_id   = (int)$data['seller_id'];
$seller_upi  = htmlspecialchars($data['seller_upi']);
$auction_qr  = htmlspecialchars($data['auction_qr']);   // QR URL
$item_name   = htmlspecialchars($data['item_name']);

// 2) Handle the POST (payment confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // a) Record payment
        $pay = $conn->prepare("
          INSERT INTO payments
            (purchase_id, item_id, buyer_id, seller_id, amount, upi_id, status)
          VALUES
            (?, ?, ?, ?, ?, ?, 'completed')
        ");
        $pay->bind_param('iiiids', $purchase_id, $item_id, $user_id, $seller_id, $amount, $seller_upi);
        $pay->execute();
        $pay->close();

        // b) Delete auction & its bids
        $del = $conn->prepare("DELETE FROM auctions WHERE auction_id = ?");
        $del->bind_param('i', $auction_id);
        $del->execute();
        $del->close();

        // c) Mark item sold
        $upd = $conn->prepare("UPDATE items SET status = 'sold' WHERE item_id = ?");
        $upd->bind_param('i', $item_id);
        $upd->execute();
        $upd->close();

        $conn->commit();
        header("Location: my_purchases.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<div class='container'><p class='error-message'>Payment failed: "
           . htmlspecialchars($e->getMessage())
           . "</p></div>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay for "<?= $item_name ?>"</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auction_custom.css">
</head>
<body>
<div class="auction-section">
  <div class="auction-card" style="max-width:400px;margin:2rem auto;">
    <div class="auction-title">Pay for "<?= $item_name ?>"</div>
    <div class="auction-price">Amount: ₹<?= number_format($amount,2) ?></div>
    <div class="auction-info"><strong>Seller's UPI ID:</strong> <?= $seller_upi ?></div>

    <!-- ONLY show the auction’s QR code if it exists -->
    <?php if ($auction_qr): ?>
      <div class="auction-thumb" style="height:200px;max-width:100%;margin:1em auto;">
        <img src="<?= $auction_qr ?>" alt="Payment QR Code" style="height:100%;object-fit:contain;">
      </div>
    <?php endif; ?>

    <form method="post">
      <button type="submit" class="auction-btn" style="width:100%;margin-top:1em;">
        I've Paid
      </button>
    </form>
  </div>
</div>
</body>
</html>
