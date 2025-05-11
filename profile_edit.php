<?php
session_start();
require 'config.php';
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['userid'];

$errors = [];
$name   = $phone = $upi_id = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name   = trim($_POST['name']);
    $phone  = trim($_POST['phone']);
    $upi_id = trim($_POST['upi_id']);

    if ($name==='') {
        $errors[] = 'Name is required.';
    }
    if (!preg_match('/^[0-9]{10}$/',$phone)) {
        $errors[] = 'Phone must be exactly 10 digits.';
    }
    if ($upi_id !== '' && !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z]+$/',$upi_id)) {
        $errors[] = 'UPI ID format is invalid.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
          UPDATE users 
             SET name=?, phone=?, upi_id=?
           WHERE user_id=?
        ");
        $stmt->bind_param('sssi',$name,$phone,$upi_id,$user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['profile_message'] = 'Profile updated successfully.';
        header("Location: profile.php");
        exit();
    }
} else {
    $stmt = $conn->prepare("
      SELECT name,email,phone,upi_id
        FROM users
       WHERE user_id=?
    ");
    $stmt->bind_param('i',$user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $name   = $u['name'];
    $email  = $u['email'];
    $phone  = $u['phone'];
    $upi_id = $u['upi_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile – IITP Sharemarket</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="profile-page">
  <?php include 'nav.php'; ?>

  <div class="profile-card">
    <h2 class="profile-heading">Edit Profile</h2>

    <?php if($errors): ?>
      <div class="profile-errors">
        <?php foreach($errors as $e): ?>
          <p class="profile-error"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="profile-form">
      <label>Name:
        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
      </label>

      <label>Email (cannot change):
        <input type="email" value="<?= htmlspecialchars($email) ?>" disabled>
      </label>

      <label>Phone:
        <input type="text" name="phone" pattern="\d{10}" 
               value="<?= htmlspecialchars($phone) ?>" required>
      </label>

      <label>UPI ID:
        <input type="text" name="upi_id" 
               value="<?= htmlspecialchars($upi_id) ?>" 
               placeholder="e.g. user@bank">
      </label>

      <div class="profile-actions">
        <button type="submit" class="profile-btn">Save Changes</button>
        <a href="profile.php" class="profile-btn profile-btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>
