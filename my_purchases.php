<?php
session_start();
require 'config.php';

if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$uid = $_SESSION['userid'];

// 1) Determine filter
$allowed = ['all','direct','auction'];
$filter  = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowed)) {
    $filter = 'all';
}

// 2) Build SQL
// Always fetch seller phone too
$baseSQL = "
  SELECT 
    p.purchase_id,
    p.purchase_type,
    i.item_id,
    i.name        AS item_name,
    i.photo_url,
    i.price,
    u.name        AS seller_name,
    u.phone       AS seller_phone
  FROM purchases p
  JOIN items     i ON p.item_id   = i.item_id
  JOIN users     u ON i.seller_id = u.user_id
  WHERE p.buyer_id = ?
";
if ($filter !== 'all') {
    $baseSQL .= " AND p.purchase_type = ?";
}

$stmt = $conn->prepare($baseSQL);

if ($filter === 'all') {
    $stmt->bind_param('i', $uid);
} else {
    $stmt->bind_param('is', $uid, $filter);
}

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Purchases</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f9f9f9;
      font-family: 'Poppins', sans-serif;
    }
    .container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 20px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    h2 {
      text-align: center;
      color: #074c8c;
      margin-bottom: 20px;
      font-weight: 600;
    }
    .nav-pills .nav-link {
      color: #555;
      font-weight: 500;
      margin: 0 5px;
    }
    .nav-pills .nav-link.active {
      background-color: #074c8c;
      color: #fff;
    }
    .card {
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .card-img-top {
      height: 200px;
      object-fit: cover;
      border-top-left-radius: 12px;
      border-top-right-radius: 12px;
    }
    .card-title {
      font-size: 1.2rem;
      font-weight: 500;
      color: #333;
    }
    .card-text {
      font-size: 0.9rem;
      color: #555;
    }
    .btn-outline-primary {
      font-size: 0.9rem;
      margin-top: auto;
    }
    .alert-warning {
      text-align: center;
      font-size: 1.1rem;
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<div class="container">
  <h2>My Purchases</h2>

  <!-- Filter Pills -->
  <ul class="nav nav-pills justify-content-center mb-4">
    <li class="nav-item">
      <a class="nav-link <?= $filter==='all' ? 'active':'' ?>"
         href="?filter=all">All</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $filter==='direct' ? 'active':'' ?>"
         href="?filter=direct">Direct</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $filter==='auction' ? 'active':'' ?>"
         href="?filter=auction">Auction</a>
    </li>
  </ul>

  <?php if ($res->num_rows > 0): ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class="col">
          <div class="card h-100 shadow-sm d-flex flex-column">
            <img src="<?= htmlspecialchars($row['photo_url']) ?>"
                 class="card-img-top" alt="Product Image">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?= htmlspecialchars($row['item_name']) ?></h5>
              <p class="card-text mb-1">
                <strong>Seller:</strong> <?= htmlspecialchars($row['seller_name']) ?>
              </p>
              <p class="card-text mb-2">
                <strong>Phone:</strong> <?= htmlspecialchars($row['seller_phone']) ?>
              </p>
              <p class="card-text mb-3">
                <strong>Price:</strong> ₹<?= number_format($row['price'], 2) ?>
              </p>
              <p class="card-text mb-3">
                <strong>Type:</strong>
                <?= $row['purchase_type'] === 'direct'
                      ? 'Direct'
                      : 'Auction' ?>
              </p>
              <a href="product_detail.php?item_id=<?= $row['item_id'] ?>"
                 class="btn btn-outline-primary mt-auto">
                View Product
              </a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-warning mt-4">
      No <?= ucfirst($filter) ?> purchases found.
    </div>
  <?php endif; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
