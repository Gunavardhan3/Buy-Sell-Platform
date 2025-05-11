<?php
session_start();
require 'config.php';
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['userid'];
$msg     = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);

$stmt = $conn->prepare("
  SELECT name, email, phone, upi_id, created_at
  FROM users
  WHERE user_id = ?
");
$stmt->bind_param('i',$user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile – IITP Sharemarket</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="profile-page">
  <?php include 'nav.php'; ?>

  <div class="profile-card">
    <h2 class="profile-heading">My Profile</h2>

    <?php if($msg): ?>
      <p class="profile-success"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <div class="profile-info">
      <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
      <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?></p>
      <p><strong>UPI ID:</strong> <?= htmlspecialchars($user['upi_id'] ?? 'Not set') ?></p>
      <p><strong>Member Since:</strong> <?= htmlspecialchars($user['created_at']) ?></p>
    </div>

    <div class="profile-actions">
      <a href="profile_edit.php" class="profile-btn">Edit Profile</a>
    
    </div>
  </div>
</body>
</html>
