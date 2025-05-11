<?php
session_start();
require_once 'config.php';
include 'nav.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}

// Fetch all categories for dropdowns
$catRes = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
$categories = $catRes->fetch_all(MYSQLI_ASSOC);

// Determine active view
$view = $_GET['view'] ?? 'live';
$selectedCat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Auto transition auction statuses
$conn->query("UPDATE auctions SET status = 'live' WHERE status = 'upcoming' AND start_time <= NOW()");
$conn->query("UPDATE auctions SET status = 'ended' WHERE status IN ('upcoming','live') AND end_time <= NOW()");

try {
    // Set ORDER BY clause based on view
    if ($view === 'live') {
        $orderBy = 'a.end_time ASC';
    } elseif ($view === 'upcoming') {
        $orderBy = 'a.start_time ASC';
    } else {
        $orderBy = 'a.end_time ASC';
    }
    // Build WHERE clause for category filter
    $where = ['a.status = ?'];
    $params = [$view];
    if ($selectedCat) {
        $where[] = 'i.category_id = ?';
        $params[] = $selectedCat;
    }
    $whereSql = implode(' AND ', $where);
    // Fetch auctions based on view and category
    $sql = "
        SELECT a.*, i.name AS title, i.photo_url AS item_image, i.category_id,
               s.name AS seller_name, 
               (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        LEFT JOIN users s ON a.seller_id = s.user_id
        WHERE $whereSql
        ORDER BY $orderBy
    ";
    $stmt = $conn->prepare($sql);
    if (count($params) === 2) {
        $stmt->bind_param('si', $params[0], $params[1]);
    } else {
        $stmt->bind_param('s', $params[0]);
    }
    $stmt->execute();
    $auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Error in auction.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auctions</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="auction_custom.css">
    <style>
      @media (min-width: 1000px) {
        .auction-grid {
          grid-template-columns: repeat(3, minmax(var(--auction-card-width), 1fr));
        }
      }
    </style>
</head>
<body>
<div class="dashboard-page">
  <div class="main-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-title">Auctions</div>
      <?php
        $tabs = [
          'live'     => 'Live Auctions',
          'create'   => 'Create Auction',
          'upcoming' => 'Upcoming Auctions',
          'past'     => 'Past Auctions'
        ];
        foreach($tabs as $key => $label):
          $cls = $view === $key ? 'active' : '';
      ?>
        <a href="auction.php?view=<?=$key?>" class="<?=$cls?>"><?=htmlspecialchars($label)?></a>
      <?php endforeach; ?>
    </aside>

    <!-- Main Area -->
    <section class="main-area">

    <?php if($view === 'live'): ?>
      <!-- Live Auctions -->
      <h2>Live Auctions</h2>
      <form method="get" class="auction-form" style="margin-bottom:1em;">
        <input type="hidden" name="view" value="live">
        <label>Category:
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>" <?= $selectedCat == $cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <noscript><button type="submit">Filter</button></noscript>
      </form>
      <?php if (isset($error)): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <?php if (empty($auctions)): ?>
        <p class="info-message">No live auctions found.</p>
      <?php else: ?>
        <div class="auction-grid">
          <?php foreach($auctions as $a): ?>
            <div class="auction-card">
              <div class="auction-thumb">
                <img src="<?=htmlspecialchars($a['item_image'])?>" alt="">
              </div>
              <div class="auction-title"><?=htmlspecialchars($a['title'])?></div>
              <div class="auction-seller">Seller: <?=htmlspecialchars($a['seller_name'])?></div>
              <div class="auction-price">
                <?php
                  if (isset($a['current_bid'])) {
                    echo '₹' . number_format($a['current_bid'], 2);
                  } elseif (isset($a['initial_price'])) {
                    echo '₹' . number_format($a['initial_price'], 2);
                  } else {
                    echo '₹0.00';
                  }
                ?>
              </div>
              <div class="auction-bids">Bids: <?= $a['bid_count'] ?></div>
              <div class="auction-info">Ends: <?=date('Y-m-d H:i', strtotime($a['end_time']))?></div>
              <a href="auction_detail.php?auction_id=<?= $a['auction_id'] ?>" class="auction-btn">View Details</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif($view === 'create'): ?>
      <!-- Create Auction -->
      <h2>Create Auction</h2>
      <?php
      // Fetch user's UPI ID from profile (no qr_url)
      $user_id = $_SESSION['userid'];
      $stmt = $conn->prepare("SELECT upi_id FROM users WHERE user_id = ?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $userProfile = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $profile_upi = $userProfile['upi_id'] ?? '';

      $errors = [];
      $upi_id = $profile_upi;
      $qr_url = '';
      if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $title      = trim($_POST['title']);
        $sd         = $_POST['start_time'];
        $ed         = $_POST['end_time'];
        $sb         = $_POST['start_bid'];
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $seller_id  = $_SESSION['userid'];
        $upi_id     = trim($_POST['upi_id'] ?? $profile_upi);
        // Validate UPI
        if(empty($upi_id) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z]+$/', $upi_id)) {
          $errors[] = 'Valid UPI ID is required';
        }
        // Handle QR upload (for auction only)
        if(isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
          $ext = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
          $qrName = uniqid('qr_') . '.' . $ext;
          move_uploaded_file($_FILES['qr_image']['tmp_name'], 'uploads/' . $qrName);
          $qr_url = 'uploads/' . $qrName;
        }
        if(empty($title)) $errors[] = 'Title is required';
        if(!$category_id) $errors[] = 'Category is required';
        if(strtotime($ed) <= strtotime($sd)) $errors[] = 'End time must be after start time';
        if(empty($errors)){
          $imgPath = 'uploads/'.basename($_FILES['item_image']['name']);
          move_uploaded_file($_FILES['item_image']['tmp_name'], $imgPath);
          // Insert item with category
          $stmt = $conn->prepare("INSERT INTO items (name, photo_url, seller_id, category_id, status, is_active) VALUES (?, ?, ?, ?, 'auction', 1)");
          $stmt->bind_param('ssii', $title, $imgPath, $seller_id, $category_id);
          if (!$stmt->execute()) {
            $errors[] = 'Error creating item: ' . $stmt->error;
          }
          $item_id = $conn->insert_id;
          $stmt->close();
          // Insert auction with UPI only (no QR)
          $stmt = $conn->prepare("INSERT INTO auctions (item_id, seller_id, start_time, end_time, initial_price, status, upi_id) VALUES (?, ?, ?, ?, ?, 'upcoming', ?)");
          $stmt->bind_param('iissds', $item_id, $seller_id, $sd, $ed, $sb, $upi_id);
          if (!$stmt->execute()) {
            $errors[] = 'Error creating auction: ' . $stmt->error;
          } else {
            echo '<p class="success">Auction created.</p>';
          }
          $stmt->close();
        }
      }
      ?>
      <?php if($errors): foreach($errors as $e): ?><p class="error"><?=htmlspecialchars($e)?></p><?php endforeach; endif; ?>
      <form method="post" enctype="multipart/form-data" class="auction-form">
        <label>Title:<input type="text" name="title" required></label>
        <label>Image:<input type="file" name="item_image" accept="image/*" required></label>
        <label>Category:
          <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>" <?= isset($category_id) && $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Start Time:<input type="datetime-local" name="start_time" required></label>
        <label>End Time:<input type="datetime-local" name="end_time" required></label>
        <label>Starting Bid:<input type="number" name="start_bid" step="0.01" required></label>
        <label>UPI ID:<input type="text" name="upi_id" value="<?= htmlspecialchars($upi_id ?? '') ?>" required placeholder="e.g. user@bank"></label>
        <label>QR Code (optional):<input type="file" name="qr_image" accept="image/*"></label>
        <button type="submit">Create</button>
      </form>

    <?php elseif($view === 'upcoming'): ?>
      <!-- Upcoming Auctions -->
      <h2>Upcoming Auctions</h2>
      <form method="get" class="auction-form" style="margin-bottom:1em;">
        <input type="hidden" name="view" value="upcoming">
        <label>Category:
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>" <?= $selectedCat == $cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <noscript><button type="submit">Filter</button></noscript>
      </form>
      <?php if (isset($error)): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <?php if (empty($auctions)): ?>
        <p class="info-message">No upcoming auctions found.</p>
      <?php else: ?>
        <div class="auction-grid">
          <?php foreach($auctions as $a): ?>
            <div class="auction-card">
              <div class="auction-thumb">
                <img src="<?=htmlspecialchars($a['item_image'])?>" alt="">
              </div>
              <div class="auction-title"><?=htmlspecialchars($a['title'])?></div>
              <div class="auction-seller">Seller: <?=htmlspecialchars($a['seller_name'])?></div>
              <div class="auction-info">Starts: <?=date('Y-m-d H:i', strtotime($a['start_time']))?></div>
              <div class="auction-bids">Bids: <?= $a['bid_count'] ?></div>
              <a href="auction_detail.php?auction_id=<?= $a['auction_id'] ?>" class="auction-btn">View Details</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif($view === 'past'): ?>
      <!-- Past Auctions -->
      <h2>Past Auctions</h2>
      <?php
      $where = ["a.status = ?"];
      $params = ['closed'];
      if(isset($_GET['date']) && $_GET['date'] !== ''){
        $where[] = "DATE(a.end_time)=?";
        $params[] = $_GET['date'];
      }
      elseif(isset($_GET['month']) && $_GET['month'] !== ''){
        $where[] = "DATE_FORMAT(a.end_time,'%Y-%m')=?";
        $params[] = $_GET['month'];
      }
      elseif(isset($_GET['preset']) && $_GET['preset'] !== ''){
        if($_GET['preset']==='week')  $where[]="a.end_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        if($_GET['preset']==='month') $where[]="a.end_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
      }
      if(isset($selectedCat) && $selectedCat) {
        $where[] = 'i.category_id = ?';
        $params[] = $selectedCat;
      }
      $sql = "SELECT a.auction_id, i.photo_url AS item_image, i.name AS title, a.final_price, a.end_time,
                     s.name AS seller
              FROM auctions a
              JOIN items i ON a.item_id = i.item_id
              LEFT JOIN users s ON a.seller_id = s.user_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY a.end_time DESC";
      $stmt = $conn->prepare($sql);
      if (count($params) > 0) {
          $types = str_repeat('s', count($params));
          $stmt->bind_param($types, ...$params);
      }
      $stmt->execute();
      $auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      ?>
      <form method="get" class="auction-form" style="margin-bottom:1em;">
        <input type="hidden" name="view" value="past">
        <label>Category:
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>" <?= $selectedCat == $cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Preset:
          <select name="preset">
            <option value="">--Select--</option>
            <option value="week" <?= isset($_GET['preset']) && $_GET['preset']==='week' ? 'selected' : '' ?>>Last Week</option>
            <option value="month" <?= isset($_GET['preset']) && $_GET['preset']==='month' ? 'selected' : '' ?>>Last Month</option>
          </select>
        </label>
        <label>Date: <input type="date" name="date" value="<?= isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '' ?>"></label>
        <label>Month: <input type="month" name="month" value="<?= isset($_GET['month']) ? htmlspecialchars($_GET['month']) : '' ?>"></label>
        <button type="submit">Filter</button>
      </form>
      <?php if(!$auctions): ?><p>No past auctions found.</p><?php else: ?>
        <div class="auction-grid">
          <?php foreach($auctions as $a): ?>
            <div class="auction-card">
              <div class="auction-thumb">
                <img src="<?=htmlspecialchars($a['item_image'])?>" alt="">
              </div>
              <div class="auction-title"><?=htmlspecialchars($a['title'])?></div>
              <div class="auction-seller">Seller: <?=htmlspecialchars($a['seller'])?></div>
              <div class="auction-price">₹<?=number_format($a['final_price'],2)?></div>
              <div class="auction-info">Date: <?=date('Y-m-d', strtotime($a['end_time']))?></div>
              <a href="auction_detail.php?auction_id=<?= $a['auction_id'] ?>" class="auction-btn">View Details</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <p>Invalid section.</p>
    <?php endif; ?>

    </section>
  </div>
</div>
</body>
</html>
