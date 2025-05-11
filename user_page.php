<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$name = htmlspecialchars($_SESSION['name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>IITP SHAREMARKET – Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">

  <!-- Top Navbar -->
  <header class="topbar">
    <div class="brand">
      <img src="assets/logo.jpg" alt="Logo" class="logo">
      <span>IITP SHAREMARKET</span>
    </div>
    <!-- Profile icon -->
    <a href="profile.php" class="profile-icon">
      <i class="ti ti-user"></i>
    </a>
  </header>

  <!-- Main Wrapper -->
  <div class="main-wrapper">
    
    <!-- Sidebar -->
    <aside class="sidebar">
      <p class="sidebar-title">Dashboard</p>
      <a href="purchase.php"><i class="ti ti-shopping-cart"></i> Purchase Product</a>
      <a href="sell.php"><i class="ti ti-box"></i> Sell Product</a>
      <a href="auction.php"><i class="ti ti-gavel"></i> Auction</a>
      <a href="my_purchases.php"><i class="ti ti-list"></i> My Purchases</a>
      <a href="my_products.php"><i class="ti ti-folder"></i> My Products</a>
      <a href="bookmarks.php"><i class="ti ti-bookmark"></i> Bookmarks</a>
      <a href="messages.php"><i class="ti ti-message"></i> Messages</a>
      <a href="logout.php" class="logout"><i class="ti ti-logout"></i> Logout</a>
    </aside>

    <!-- Main Content -->
    <main class="main-area">
      <div class="content-wrapper">
        <h1>Welcome, <?= $name ?> 👋</h1>
        <img src="assets/iitp.jpg" alt="IITP" class="iitp-image">
      </div>
    </main>

  </div>

<!-- Footer -->
<footer style="
  background-color: #f1f5f9;
  text-align: center;
  padding: 20px;
  font-size: 14px;
  color: #334155;
  border-top: 1px solid #e2e8f0;
  margin-top: 40px;
">
  <div style="max-width: 1200px; margin: auto;">
    <p style="margin: 0;">&copy; <?= date("Y") ?> IITP Sharemarket. All rights reserved.</p>
    <p style="margin: 4px 0;">Made with ❤ by students of IIT Patna.</p>
    <div style="margin-top: 10px;">
      <a href="https://www.instagram.com" target="_blank" style="margin: 0 10px; display: inline-block;">
        <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram" style="vertical-align: middle;">
      </a>
      <a href="https://www.twitter.com" target="_blank" style="margin: 0 10px; display: inline-block;">
        <img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" alt="Twitter" style="vertical-align: middle;">
      </a>
      <a href="https://www.linkedin.com" target="_blank" style="margin: 0 10px; display: inline-block;">
        <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn" style="vertical-align: middle;">
      </a>
    </div>
  </div>
</footer>



</body>
</html>