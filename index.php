<?php
session_start();
// If user is already logged in, redirect them to the user_page.php
if (isset($_SESSION['userid'])) {
    header("Location: user_page.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to IITP Sharemarket</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, sans-serif;
            overflow: hidden; /* Prevents scrollbars if image is slightly larger */
        }

        .welcome-background {
            /* Using mainimg.jpg as per your previous welcome.php code */
            background-image: url('assets/mainimg.jpg'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .welcome-background::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.4); /* Black overlay with 40% opacity - adjust as needed */
            z-index: 1;
        }

        .welcome-content {
            position: relative;
            z-index: 2; /* Ensures content is above the overlay */
            text-align: center;
            color: white;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2); /* Optional: slight background for content for readability */
            border-radius: 10px;
        }

        .welcome-logo {
            width: 100px; /* Adjust size as needed */
            height: auto;
            margin-bottom: 20px;
        }

        .welcome-content h1 {
            font-size: 2.8em; /* Adjust size as needed */
            margin-top: 0;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }

        .welcome-button {
            display: inline-block;
            padding: 15px 35px;
            font-size: 1.2em;
            color: #fff;
            background-color: #007bff; /* Example button color */
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .welcome-button:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }
    </style>
</head>
<body>
    <div class="welcome-background">
        <div class="welcome-content">
            <img src="assets/logo.jpg" alt="IITP Sharemarket Logo" class="welcome-logo">
            <h1>WELCOME TO IITP SHAREMARKET</h1>
            <a href="auth_forms.php" class="welcome-button">Click to Login / Register</a>
        </div>
    </div>
</body>
</html>





