<?php
session_start();

// If user is already logged in, redirect them to the user_page.php
if (isset($_SESSION['userid'])) {
    header("Location: user_page.php");
    exit();
}

$errors = [
    'login'    => $_SESSION['login_error']    ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

// Unset only specific session variables used for errors and form state
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['active_form']);

function showError($error) {
    return !empty($error) ? "<p class='error-message'>{$error}</p>" : '';
}
function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : ''; // This adds 'active' class
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>IITP Share Market - Login/Register</title>
  <link rel="stylesheet" href="style.css"> <script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-auth-compat.js"></script>
  <script>
  const firebaseConfig = {
    apiKey: "AIzaSyB9ZY1bVAXJw0mzrr9_bn4b6ytMYWgotxA",
    authDomain: "iitp-sharemarket.firebaseapp.com",
    projectId: "iitp-sharemarket",
    appId: "1:702099992294:web:c17106d28584dc4cbed527"
  };
  firebase.initializeApp(firebaseConfig);
  </script>
  <style>
    /* Styles for the header on the forms page */
    .header {
        text-align: center;
        margin-bottom: 20px;
    }

    .header img {
        width: 150px;
        height: auto;
    }

    .header h1 {
        font-size: 2.5em;
        color: #007bff;
    }

    /* Styles for form boxes */
    .form-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        /* IMPORTANT: Your style.css or script.js needs to handle hiding non-active forms.
           If using class-based visibility, you'd have:
           display: none; 
        */
    }
    /* And for the active form:
    .form-box.active {
        display: block;
    }
    */

    .form-box h2 {
        text-align: center;
        color: #007bff;
    }

    .form-box input {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

    .form-box button {
        width: 100%;
        background-color: #007bff;
        color: white;
        padding: 14px 20px;
        margin: 8px 0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .form-box button:hover {
        background-color: #0056b3;
    }

    .form-box p {
        text-align: center;
    }

    .form-box a {
        color: #007bff;
        text-decoration: none;
    }

    .error-message {
        color: red;
        text-align: center;
    }

    .container { /* For the forms */
        width: 400px;
        margin: 50px auto;
    }

    .login-page-body { /* A specific class for this body if needed */
        background-color: #f4f4f4;
        background-image: url('assets/mainimg.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    /* Overlay over the entire background image */
.background-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); /* Black with 50% opacity */
    z-index: 0; /* Behind everything */
}

/* Overlay behind the content (like a dark container background) */
.container-overlay {
    position: relative;
    z-index: 1; /* On top of background-overlay */
    background-color: rgba(0, 0, 0, 0.5); /* Slightly dark overlay behind content */
    padding: 50px 0;
}

/* Ensure the container sits above the overlays */
.container {
    position: relative;
    z-index: 2;
}

  </style>
</head>
<body class="login-page-body"> 
<body class="login-page-body">
  <div class="background-overlay"></div> <!-- NEW: black overlay over the background -->
  <class="container-overlay"> <!-- NEW: black overlay behind content -->
    <div class="container">
      <!-- existing .header and .form-box content here -->
      
    <div class="header">
      <img src="assets/logo.jpg" alt="IITP Share Market Logo">
      <h1>IITP Share Market</h1>
    </div>

    <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
      <form action="login_register.php" method="post">
        <h2>Login</h2>
        <?= showError($errors['login']); ?>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
        <p>Don't have an account?
          <a href="#" onclick="showForm('register-form'); return false;">Register</a>
        </p>
      </form>
    </div>

    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
      <form id="registerForm" action="login_register.php" method="post">
        <h2>Register</h2>
        <?= showError($errors['register']); ?>
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" id="emailField" placeholder="IITP Email" required>
        <input type="text" name="phone" placeholder="10-digit Phone" required pattern="\d{10}" title="Enter exactly 10 digits">
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" id="otpCode" name="otp" placeholder="Enter OTP from Email" style="display:none;">
        <button type="button" id="sendOtpButton" onclick="sendOTP()">Send OTP</button>
        <button type="submit" name="register" id="verifyBtn" style="display:none;">Verify & Register</button>
        <p>Already have an account?
          <a href="#" onclick="showForm('login-form'); return false;">Login</a>
        </p>
      </form>
    </div>
  </div>
</div>
</body>





  <script src="script.js"></script> <script>
    // This script ensures the correct form (login or register) is displayed
    // when the page loads, especially after a server-side redirect with an error.
    // It relies on your script.js having a showForm() function that correctly
    // displays one form and hides the other based on the 'active' class or direct style manipulation.

    document.addEventListener('DOMContentLoaded', function() {
      const activeFormId = "<?= $activeForm === 'register' ? 'register-form' : 'login-form'; ?>";
      
      // Call your existing showForm function from script.js
      // Ensure showForm() correctly makes activeFormId visible and others hidden.
      if (typeof showForm === 'function') {
        showForm(activeFormId); 
      } else {
        // Fallback or simple class management if showForm is not robust
        // This part assumes your CSS handles .active class for visibility
        document.getElementById('login-form').classList.remove('active');
        document.getElementById('register-form').classList.remove('active');
        if(document.getElementById(activeFormId)) {
            document.getElementById(activeFormId).classList.add('active');
        } else {
            document.getElementById('login-form').classList.add('active'); // Default
        }
      }
    });
  </script>
</body>
</html>