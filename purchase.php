<?php
session_start();
require 'config.php';
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['userid'];

// 1) Fetch all categories
$catRes = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
$selectedCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

// 2) Load items
if ($selectedCat > 0) {
    $stmt = $conn->prepare("SELECT i.item_id, i.name AS item_name, i.photo_url, i.price, u.name AS seller_name 
                          FROM items i
                          JOIN users u ON i.seller_id = u.user_id
                          WHERE i.category_id = ? AND i.status = 'available' AND i.is_active = 1
                          ORDER BY i.created_at DESC");
    $stmt->bind_param('i', $selectedCat);
    $stmt->execute();
    $items = $stmt->get_result();
    $stmt->close();
} else {
    $items = $conn->query("SELECT i.item_id, i.name AS item_name, i.photo_url, i.price, u.name AS seller_name 
                         FROM items i
                         JOIN users u ON i.seller_id = u.user_id
                         WHERE i.status = 'available' AND i.is_active = 1
                         ORDER BY i.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse &amp; Buy</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: #f5f5f5;
    }

    .main-wrapper {
      display: flex;
      gap: 20px;
      padding: 20px;
      max-width: 1200px;
      margin: 80px auto 20px;
    }

    .sidebar {
      width: 250px;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      position: sticky;
      top: 80px;
      height: fit-content;
    }

    .sidebar-title {
      font-weight: bold;
      color: #333;
      margin-bottom: 15px;
      font-size: 1.2em;
    }

    .sidebar a {
      display: block;
      padding: 10px;
      margin: 5px 0;
      text-decoration: none;
      color: #555;
      border-radius: 5px;
      transition: all 0.3s;
    }

    .sidebar a:hover, .sidebar a.active {
      background: #007bff;
      color: white;
    }

    .main-area {
      flex: 1;
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .item-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 25px;
      margin-top: 20px;
    }

    .item-card {
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      transition: transform 0.3s;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .item-card:hover {
      transform: translateY(-5px);
    }

    .item-card a {
      text-decoration: none;
      color: #333;
    }

    .thumb {
      width: 100%;
      height: 200px;
      overflow: hidden;
      background: #f8f9fa;
    }

    .thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s;
    }

    .item-card:hover img {
      transform: scale(1.05);
    }

    .item-card h4 {
      padding: 15px 15px 0;
      margin: 0;
      font-size: 1.1em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .seller, .price {
      padding: 5px 15px 15px;
      margin: 0;
      font-size: 0.9em;
    }

    .price {
      color: #007bff;
      font-weight: bold;
      font-size: 1.1em;
    }

    .no-items {
      text-align: center;
      color: #666;
      padding: 40px;
      font-size: 1.2em;
    }

    h2 {
      color: #333;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .main-wrapper {
        flex-direction: column;
        padding: 15px;
      }

      .sidebar {
        width: 100%;
        position: static;
      }

      .item-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="dashboard-page">
  <?php include 'nav.php'; ?>

  <div class="main-wrapper">
    <aside class="sidebar">
      <p class="sidebar-title">Categories</p>
      <a href="purchase.php" class="<?= $selectedCat === 0 ? 'active' : '' ?>">
        All Categories
      </a>
      <?php while ($cat = $catRes->fetch_assoc()): ?>
        <a href="purchase.php?cat=<?= $cat['category_id'] ?>" 
           class="<?= $selectedCat === (int)$cat['category_id'] ? 'active' : '' ?>">
          <?= htmlspecialchars($cat['name']) ?>
        </a>
      <?php endwhile; ?>
    </aside>

    <main class="main-area">
      <h2>Browse &amp; Buy</h2>
      <?php if ($items && $items->num_rows): ?>
        <div class="item-grid">
          <?php while ($item = $items->fetch_assoc()): ?>
            <div class="item-card">
              <a href="product_detail.php?item_id=<?= $item['item_id'] ?>">
                <div class="thumb">
                  <img src="<?= htmlspecialchars($item['photo_url']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                </div>
                <h4><?= htmlspecialchars($item['item_name']) ?></h4>
                <p class="seller">Seller: <?= htmlspecialchars($item['seller_name']) ?></p>
                <p class="price">₹<?= number_format($item['price'], 2) ?></p>
              </a>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p class="no-items">No items available in this category</p>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>