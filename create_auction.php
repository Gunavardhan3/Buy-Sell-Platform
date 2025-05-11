<?php
session_start();
require 'config.php';
include 'nav.php';
date_default_timezone_set('Asia/Kolkata');

// ensure login
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['userid'];

$errors = [];

// Fetch categories for the <select>
$catRes = $conn->query("SELECT category_id, name FROM categories ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['item_name'] ?? '');
    $details      = trim($_POST['details'] ?? '');
    $category     = (int)($_POST['category_id'] ?? 0);
    $initialPrice = (float)($_POST['initial_price'] ?? 0);
    $startTime    = $_POST['start_time'] ?? '';
    $endTime      = $_POST['end_time'] ?? '';
    $upi_id       = trim($_POST['upi_id'] ?? '');

    // Validation
    if ($name === '') {
        $errors[] = 'Item name is required.';
    }
    if (!strtotime($startTime) || !strtotime($endTime) || strtotime($startTime) >= strtotime($endTime)) {
        $errors[] = 'End time must be after start time.';
    }
    if ($initialPrice <= 0) {
        $errors[] = 'Starting price must be positive.';
    }
    if ($upi_id === '') {
        $errors[] = 'UPI ID is required.';
    }
    if (empty($_FILES['photo']['name'])) {
        $errors[] = 'Item photo is required.';
    }
    if (empty($_FILES['qr_image']['name'])) {
        $errors[] = 'QR image is required.';
    }

    if (empty($errors)) {
        // Ensure upload directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Photo
        $photoExt  = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoName = uniqid('photo_') . '.' . $photoExt;
        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoName);
        $photo_url = 'uploads/' . $photoName;

        // QR
        $qrExt  = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
        $qrName = uniqid('qr_') . '.' . $qrExt;
        move_uploaded_file($_FILES['qr_image']['tmp_name'], $uploadDir . $qrName);
        $qr_url = 'uploads/' . $qrName;

        // Insert item
        $stmt = $conn->prepare(
            "INSERT INTO items
               (name, photo_url, details, seller_id, category_id, price, status, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 'auction', 1)"
        );
        $stmt->bind_param('sssidd',
            $name, $photo_url, $details,
            $user_id, $category, $initialPrice
        );
        $stmt->execute();
        $item_id = $stmt->insert_id;
        $stmt->close();

        // Insert auction
        $stmt = $conn->prepare(
            "INSERT INTO auctions
               (item_id, seller_id, start_time, end_time, initial_price, status, upi_id, qr_image)
             VALUES (?, ?, ?, ?, ?, 'upcoming', ?, ?)"
        );
        $stmt->bind_param('iissdss',
            $item_id, $user_id,
            $startTime, $endTime,
            $initialPrice,
            $upi_id, $qr_url
        );
        $stmt->execute();
        $stmt->close();

        header("Location: auction.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Auction</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auction_custom.css">
</head>
<body>
  <div class="auction-section">
    <h1 style="text-align:center;">Create New Auction</h1>
    <?php if ($errors): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p class="error"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="auction-form-custom" style="max-width:500px;margin:2rem auto;">
      <label>
        Item Name:
        <input type="text" name="item_name" required>
      </label>
      <label>
        Details:
        <textarea name="details" required></textarea>
      </label>
      <label>
        Category:
        <select name="category_id" required>
          <option value="">-- Select --</option>
          <?php while ($c = $catRes->fetch_assoc()): ?>
            <option value="<?= $c['category_id'] ?>">
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </label>
      <label>
        Start Time:
        <input type="datetime-local" name="start_time" required>
      </label>
      <label>
        End Time:
        <input type="datetime-local" name="end_time" required>
      </label>
      <label>
        Starting Price (₹):
        <input type="number" name="initial_price" step="0.01" required>
      </label>
      <label>
        Item Photo:
        <input type="file" name="photo" accept="image/*" required>
      </label>
      <label>
        Seller UPI ID:
        <input type="text" name="upi_id" required>
      </label>
      <label>
        QR Image:
        <input type="file" name="qr_image" accept="image/*" required>
      </label>
      <button type="submit" class="auction-btn">Create Auction</button>
      <a href="auction.php"><button type="button" class="auction-btn" style="background:var(--auction-secondary);margin-left:1em;">Cancel</button></a>
    </form>
  </div>
</body>
</html>
