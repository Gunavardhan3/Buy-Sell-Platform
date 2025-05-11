<?php
session_start();
require 'config.php';

if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$uid = $_SESSION['userid'];

// Handle delete request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item_id'])) {
    $delete_id = (int) $_POST['delete_item_id'];
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ? AND seller_id = ? AND item_id NOT IN (SELECT item_id FROM purchases)");
    $stmt->bind_param("ii", $delete_id, $uid);
    $stmt->execute();
    $stmt->close();
    header("Location: my_products.php?filter=" . ($_GET['filter'] ?? 'all'));
    exit();
}

// Filter logic
$allowed = ['all','direct','auction'];
$filter  = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowed)) {
    $filter = 'all';
}

// Fetch seller’s items
$sql = "
  SELECT 
    i.item_id,
    i.name         AS item_name,
    i.photo_url,
    i.price,
    i.status,
    p.purchase_type,
    p.buyer_id,
    u.name         AS buyer_name,
    u.phone        AS buyer_phone
  FROM items i
  LEFT JOIN purchases p ON p.item_id = i.item_id
  LEFT JOIN users u ON u.user_id = p.buyer_id
  WHERE i.seller_id = ?
";
if ($filter !== 'all') {
    $sql .= " AND p.purchase_type = ?";
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
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
  <title>My Products</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap -->
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
      position: relative;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .card-img-top {
      height: 220px;
      object-fit: cover;
      border-top-left-radius: 12px;
      border-top-right-radius: 12px;
    }
    .card-title {
      font-size: 1.2rem;
      font-weight: 500;
      color: #333;
    }
    .dropdown-toggle::after {
      display: none;
    }
    .delete-btn {
      color: red;
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<div class="container">
  <h2>My Products</h2>

  <!-- Filter Pills -->
  <ul class="nav nav-pills justify-content-center mb-4">
    <li class="nav-item"><a class="nav-link <?= $filter==='all' ? 'active':'' ?>" href="?filter=all">All</a></li>
    <li class="nav-item"><a class="nav-link <?= $filter==='direct' ? 'active':'' ?>" href="?filter=direct">Direct</a></li>
    <li class="nav-item"><a class="nav-link <?= $filter==='auction' ? 'active':'' ?>" href="?filter=auction">Auction</a></li>
  </ul>

  <?php if ($res->num_rows > 0): ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class="col">
          <div class="card h-100 shadow-sm d-flex flex-column">
            <!-- Dropdown menu for unsold items -->
            <?php if (!$row['purchase_type']): ?>
              <div class="dropdown position-absolute top-0 end-0 m-2">
                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this item?');">
                      <input type="hidden" name="delete_item_id" value="<?= $row['item_id'] ?>">
                      <button type="submit" class="dropdown-item delete-btn">Delete</button>
                    </form>
                  </li>
                </ul>
              </div>
            <?php endif; ?>

            <img src="<?= htmlspecialchars($row['photo_url']) ?>" class="card-img-top" alt="Product Image">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?= htmlspecialchars($row['item_name']) ?></h5>
              <p class="card-text mb-1"><strong>Price:</strong> ₹<?= number_format($row['price'],2) ?></p>
              <p class="card-text mb-1"><strong>Status:</strong> <?= ucfirst(htmlspecialchars($row['status'])) ?></p>

              <?php if ($row['purchase_type']): ?>
                <p class="card-text mb-1"><strong>Sold via <?= ucfirst(htmlspecialchars($row['purchase_type'])) ?></strong></p>
                <p class="card-text mb-1"><strong>Buyer:</strong> <?= htmlspecialchars($row['buyer_name']) ?></p>
                <p class="card-text mb-3"><strong>Phone:</strong> <?= htmlspecialchars($row['buyer_phone']) ?></p>
              <?php else: ?>
                <p class="card-text text-muted mb-3"><em>No sale yet</em></p>
              <?php endif; ?>

              <a href="product_detail.php?item_id=<?= $row['item_id'] ?>" class="btn btn-outline-primary mt-auto">View Product</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-warning mt-4">
      No <?= ucfirst($filter) ?> products to display.
    </div>
  <?php endif; ?>
</div>

<!-- Bootstrap JS + icons -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>