<?php
session_start();
require 'config.php';

// 1) Auth check
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$buyer_id = $_SESSION['userid'];

// 2) Read item_id
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// 3) Fetch item + seller UPI/QR
$stmt = $conn->prepare("
  SELECT 
    i.name    AS item_name,
    i.price   AS amount,
    i.status,
    i.seller_id,
    u.upi_id,
    i.qr_url
  FROM items i
  JOIN users u ON u.user_id = i.seller_id
  WHERE i.item_id = ?
");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4) Not found?
if (!$data) {
    die("<p style='text-align:center;margin-top:2rem;'>Invalid item.</p>");
}

// 5) If not available show error
if ($data['status'] !== 'available') {
    die("
      <div style='max-width:400px;margin:3rem auto;text-align:center;'>
        <p class='error-message'>Sorry, this item is no longer available.</p>
        <a href='purchase.php' class='back-button'>← Back to Products</a>
      </div>
    ");
}

$item_name = htmlspecialchars($data['item_name']);
$amount    = (float)$data['amount'];
$seller_id = (int)$data['seller_id'];
$upi_id    = htmlspecialchars($data['upi_id']);
$qr_url    = htmlspecialchars($data['qr_url']);

// 6) Handle POST (after HTML so we can re‑display on error)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // lock
        $lock = $conn->prepare("SELECT status FROM items WHERE item_id = ? FOR UPDATE");
        $lock->bind_param('i', $item_id);
        $lock->execute();
        $row = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$row || $row['status'] !== 'available') {
            throw new Exception("Item went unavailable.");
        }
        // purchase
        $ins = $conn->prepare("
          INSERT INTO purchases
            (item_id,buyer_id,seller_id,auction_id,purchase_type,amount)
          VALUES
            (?, ?, ?, NULL, 'direct', ?)
        ");
        $ins->bind_param('iiid', $item_id, $buyer_id, $seller_id, $amount);
        $ins->execute();
        $purchase_id = $conn->insert_id;
        $ins->close();
        // mark sold
        $upd = $conn->prepare("UPDATE items SET status='sold' WHERE item_id=?");
        $upd->bind_param('i', $item_id);
        $upd->execute();
        $upd->close();
        // payment
        $pay = $conn->prepare("
          INSERT INTO payments
            (purchase_id,item_id,buyer_id,seller_id,amount,upi_id,status)
          VALUES
            (?, ?, ?, ?, ?, ?, 'completed')
        ");
        $pay->bind_param('iiiids',
            $purchase_id, $item_id, $buyer_id, $seller_id, $amount, $upi_id
        );
        $pay->execute();
        $pay->close();
        $conn->commit();
        header("Location: my_purchases.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay for “<?= $item_name ?>”</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="detail-bg">
  <?php include 'nav.php'; ?>

  <div class="detail-page">
    <!-- Back -->
    <a href="product_detail.php?item_id=<?= $item_id ?>" class="back-button">
      ← Back
    </a>

    <?php if (!empty($error)): ?>
      <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Title -->
    <h2>Pay for “<?= $item_name ?>”</h2>

    <!-- Price -->
    <p class="price">₹<?= number_format($amount,2) ?></p>

    <!-- UPI details -->
    <p class="description">
      <strong>UPI ID:</strong> <?= $upi_id ?>
    </p>
    <?php if ($qr_url): ?>
      <img 
        src="<?= $qr_url ?>" 
        alt="UPI QR Code" 
        class="product-image"
        style="max-height:200px;margin-bottom:1.5rem;">
    <?php endif; ?>

    <!-- Pay button -->
    <form method="post">
      <div class="action-buttons">
        <button type="submit" class="btn primary">I’ve Paid</button>
      </div>
    </form>
  </div>
</body>
</html>
