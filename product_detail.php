<?php
session_start();
require 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}

$uid = $_SESSION['userid'];
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

try {
    // Fetch item details
    $stmt = $conn->prepare("
        SELECT
            i.item_id,
            i.name AS item_name,
            i.photo_url,
            i.details AS item_details,
            i.price AS item_price,
            i.status,
            u.user_id AS seller_id,
            u.name AS seller_name,
            u.phone AS seller_phone
        FROM items i
        JOIN users u ON u.user_id = i.seller_id
        WHERE i.item_id = ? AND i.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $item_id);
    if (!$stmt->execute()) {
        throw new Exception("Error executing item query");
    }
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        throw new Exception("Item not found");
    }

    $is_owner = ($item['seller_id'] === $uid);

    // Check purchase status
    $pu = $conn->prepare("
        SELECT 1 
        FROM purchases 
        WHERE item_id = ? AND buyer_id = ? 
        LIMIT 1
    ");
    $pu->bind_param('ii', $item_id, $uid);
    if (!$pu->execute()) {
        throw new Exception("Error checking purchase status");
    }
    $is_buyer = (bool)$pu->get_result()->fetch_assoc();
    $pu->close();

    // Availability check
    if ($item['status'] !== 'available' && !$is_owner && !$is_buyer) {
        throw new Exception("This item is no longer available");
    }

    // Build back link
    $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
    $back_link = 'purchase.php' . ($cat ? "?cat={$cat}" : '');

} catch (Exception $e) {
    die("<div class='error-container'><h2>Error: " . htmlspecialchars($e->getMessage()) . "</h2><a href='purchase.php' class='back-button'>← Return to Marketplace</a></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['item_name']) ?> – IITP Marketplace</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .detail-container {
            display: flex;
            gap: 2rem;
            padding: 2rem;
            max-width: 1200px;
            margin: 80px auto 2rem;
        }

        .product-section {
            flex: 2;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .seller-section {
            flex: 1;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 1.5rem;
            padding: 0.5rem 1rem;
            background: #007bff;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            transition: opacity 0.3s;
        }

        .back-button:hover {
            opacity: 0.9;
        }

        .product-image {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            border-radius: 8px;
            margin: 1.5rem 0;
            background: #f8f9fa;
        }

        .product-title {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .product-description {
            color: #4a5568;
            line-height: 1.6;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .product-price {
            font-size: 1.8rem;
            color: #2ecc71;
            font-weight: 700;
            margin: 1.5rem 0;
        }

        .seller-info {
            margin-bottom: 2rem;
        }

        .seller-title {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .seller-detail {
            margin: 0.8rem 0;
            color: #4a5568;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem 1.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,123,255,0.3);
        }

        .status-message {
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .status-success {
            background: #2ecc71;
            color: white;
        }

        .status-error {
            background: #e74c3c;
            color: white;
        }

        @media (max-width: 768px) {
            .detail-container {
                flex-direction: column;
                padding: 1rem;
            }

            .seller-section {
                position: static;
            }

            .product-image {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="detail-container">
        <!-- Product Details Section -->
        <div class="product-section">
            <a href="<?= $back_link ?>" class="back-button">← Back to Listings</a>
            <h1 class="product-title"><?= htmlspecialchars($item['item_name']) ?></h1>
            
            <?php if (!empty($item['photo_url'])): ?>
                <img src="<?= htmlspecialchars($item['photo_url']) ?>" 
                     alt="<?= htmlspecialchars($item['item_name']) ?>" 
                     class="product-image">
            <?php endif; ?>

            <p class="product-description">
                <?= nl2br(htmlspecialchars($item['item_details'] ?? 'No description provided')) ?>
            </p>

            <div class="product-price">
                ₹<?= number_format($item['item_price'], 2) ?>
                <?php if ($item['status'] !== 'available'): ?>
                    <span style="font-size: 1rem; color: #e74c3c;">
                        (<?= ucfirst(htmlspecialchars($item['status'])) ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seller & Actions Section -->
        <div class="seller-section">
            <div class="seller-info">
                <h2 class="seller-title">Seller Information</h2>
                <p class="seller-detail">
                    <strong>Name:</strong> <?= htmlspecialchars($item['seller_name']) ?>
                </p>
                <?php if (!empty($item['seller_phone'])): ?>
                    <p class="seller-detail">
                        <strong>Contact:</strong> <?= htmlspecialchars($item['seller_phone']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <!-- Bookmark Button -->
                <a href="product_detail.php?item_id=<?= $item_id ?>&bookmark=1" 
                   class="action-button">
                    🔖 Bookmark Item
                </a>

                <?php if ($is_owner): ?>
                    <div class="status-message status-error">
                        ❌ You are the owner of this item
                    </div>

                <?php elseif ($item['status'] === 'available'): ?>
                    <a href="messages.php?user2=<?= $item['seller_id'] ?>" 
                       class="action-button">
                        💬 Contact Seller
                    </a>
                    <a href="payment.php?item_id=<?= $item_id ?>" 
                       class="action-button">
                        💳 Purchase Now
                    </a>

                <?php elseif ($is_buyer): ?>
                    <div class="status-message status-success">
                        ✅ You purchased this item
                    </div>

                <?php else: ?>
                    <div class="status-message status-error">
                        ❌ Item no longer available
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>