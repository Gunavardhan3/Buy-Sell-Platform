<?php
session_start();
require 'config.php';
include 'nav.php';

// 1) Redirect if not logged in
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['userid'];

// 2) Fetch categories for dropdown
$catRes = $conn->query("SELECT category_id, name FROM categories ORDER BY name");

// 3) Ensure upload folder exists
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4) Sanitize inputs
    $name       = $conn->real_escape_string($_POST['name']);
    $details    = $conn->real_escape_string($_POST['details']);
    $price      = (float) $_POST['price'];
    $catId      = (int)   $_POST['category'];
    $seller_upi = trim($_POST['seller_upi']);

    // 5) Validate UPI ID server‑side
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z]+$/', $seller_upi)) {
        die("<div class='container'>
               <p class='error-message'>Invalid UPI ID format.</p>
               <p><a href='sell.php'>← Back</a></p>
             </div>");
    }

    // 6) Update user's UPI in users table
    $up = $conn->prepare("UPDATE users SET upi_id=? WHERE user_id=?");
    $up->bind_param('si', $seller_upi, $user_id);
    $up->execute();
    $up->close();

    // 7) Handle photo upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        die("Photo upload failed.");
    }
    $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $photoName = uniqid('item_') . '.' . $ext;
    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoName);
    $photo_url = 'uploads/' . $photoName;

    // 8) Handle optional QR upload
    $qr_url = null;
    if (isset($_FILES['qr']) && $_FILES['qr']['error'] === UPLOAD_ERR_OK) {
        $ext    = pathinfo($_FILES['qr']['name'], PATHINFO_EXTENSION);
        $qrName = uniqid('qr_') . '.' . $ext;
        move_uploaded_file($_FILES['qr']['tmp_name'], $uploadDir . $qrName);
        $qr_url = 'uploads/' . $qrName;
    }

    // 9) Insert into items
    $stmt = $conn->prepare("
      INSERT INTO items
        (name, photo_url, details, seller_id, category_id, price, status, is_active, qr_url)
      VALUES
        (?, ?, ?, ?, ?, ?, 'available', 1, ?)
    ");
    $stmt->bind_param(
      'sssidds',
      $name,
      $photo_url,
      $details,
      $user_id,
      $catId,
      $price,
      $qr_url
    );
    $stmt->execute();
    $stmt->close();

    // 10) Redirect to My Products
    header("Location: my_products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sell a New Product</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h2>Sell a New Product</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="text" name="name" placeholder="Item Name" required>
      <textarea name="details" placeholder="Description" required></textarea>
      <input type="number" step="0.01" name="price" placeholder="Price (₹)" required>

      <select name="category" required>
        <option value="">-- Select Category --</option>
        <?php while ($c = $catRes->fetch_assoc()): ?>
          <option value="<?= $c['category_id'] ?>">
            <?= htmlspecialchars($c['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>

      <!-- NEW: Seller’s UPI ID -->
      <input
        type="text"
        name="seller_upi"
        placeholder="Your UPI ID (e.g. name@bank)"
        required
        pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z]+"
        title="Format: localpart@bankname (letters only after @)"
      >

      <label>Upload Photo:
        <input type="file" name="photo" accept="image/*" required>
      </label>
      <label>Upload QR Code (optional):
        <input type="file" name="qr" accept="image/*">
      </label>

      <button type="submit">List Product</button>
    </form>
  </div>
</body>
</html>
