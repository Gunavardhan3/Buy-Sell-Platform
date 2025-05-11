<?php
// bookmarks.php
session_start();
require 'config.php';
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$uid = $_SESSION['userid'];

// Remove bookmark?
if (isset($_GET['remove'])) {
    $bid = (int)$_GET['remove'];
    $conn->query("DELETE FROM bookmarks WHERE bookmark_id = $bid AND user_id = $uid");
    header("Location: bookmarks.php");
    exit();
}

// Fetch bookmarked items + seller + category
$stmt = $conn->prepare("
  SELECT 
    b.bookmark_id, 
    i.item_id, 
    i.name        AS item_name, 
    i.photo_url, 
    i.price, 
    i.status, 
    u.name        AS seller_name,
    c.name        AS category_name
  FROM bookmarks b
  JOIN items     i ON i.item_id      = b.item_id
  JOIN users     u ON u.user_id      = i.seller_id
  JOIN categories c ON c.category_id = i.category_id
 WHERE b.user_id = ?
 ORDER BY b.bookmark_id DESC
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Bookmarks</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="bookmarks-page">

  <?php include 'nav.php'; ?>

  <div class="container">
    <h1>📑 My Bookmarks</h1>

    <?php if ($rows->num_rows): ?>
      <div class="bookmark-grid">
        <?php while($r = $rows->fetch_assoc()): ?>
          <div class="bookmark-card">
            <a href="product_detail.php?item_id=<?= $r['item_id'] ?>">
              <div class="thumb">
                <img src="<?= htmlspecialchars($r['photo_url']) ?>" alt="">
              </div>
              <h2><?= htmlspecialchars($r['item_name']) ?></h2>
              <p class="category">Category: <?= htmlspecialchars($r['category_name']) ?></p>
              <p class="seller">Sold by: <?= htmlspecialchars($r['seller_name']) ?></p>
              <p class="price">₹<?= number_format($r['price'],2) ?></p>
              <p class="status"><?= ucfirst($r['status']) ?></p>
            </a>
            <a href="bookmarks.php?remove=<?= $r['bookmark_id'] ?>" class="btn-remove" title="Remove bookmark">×</a>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p class="no-bookmarks">You have no bookmarks yet.</p>
    <?php endif; ?>
  </div>

</body>
</html>
