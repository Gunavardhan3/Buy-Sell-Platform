<?php
session_start();
require_once 'config.php';

function isValidIITPEmail($email) {
    return preg_match("/^[a-z]+_[0-9]{4}[a-z]{2}[0-9]{2}@iitp\.ac\.in$/", $email);
}

function isValidPhone($phone) {
    return preg_match("/^[0-9]{10}$/", $phone);
}

// ----- REGISTER -----
if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];

    // Validate IITP email
    if (!isValidIITPEmail($email)) {
        $_SESSION['register_error'] = "Use your IIT‑Patna email (e.g. name_rollno@iitp.ac.in)";
        $_SESSION['active_form']    = 'register';
        header("Location: index.php");
        exit();
    }
    // Validate phone
    if (!isValidPhone($phone)) {
        $_SESSION['register_error'] = "Phone must be exactly 10 digits.";
        $_SESSION['active_form']    = 'register';
        header("Location: index.php");
        exit();
    }

    // Check duplicate email
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['register_error'] = "Email is already registered.";
        $_SESSION['active_form']    = 'register';
        $stmt->close();
        header("Location: index.php");
        exit();
    }
    $stmt->close();

    // Hash & Insert
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins  = $conn->prepare("
      INSERT INTO users (name, email, password_hash, phone)
      VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param("ssss", $name, $email, $hash, $phone);
    if (!$ins->execute()) {
        $_SESSION['register_error'] = "Registration failed; please try again.";
        $_SESSION['active_form']    = 'register';
        $ins->close();
        header("Location: index.php");
        exit();
    }
    $ins->close();

    // Auto‑login
    $_SESSION['userid'] = $conn->insert_id;
    $_SESSION['name']   = $name;
    $_SESSION['email']  = $email;
    header("Location: user_page.php");
    exit();
}

// ----- LOGIN -----
if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
      SELECT user_id, name, password_hash
        FROM users
       WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['userid'] = $user['user_id'];
            $_SESSION['name']   = $user['name'];
            $_SESSION['email']  = $email;
            header("Location: user_page.php");
            exit();
        }
    }
    $_SESSION['login_error']  = "Incorrect email or password.";
    $_SESSION['active_form']  = 'login';
    header("Location: index.php");
    exit();
}
?>
