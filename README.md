# CfCbazar_WebDev
Web site and services for CfCbazar. Platform address is https://cc.free.bg and https://cfcbazar.42web.io.

The repo is under development. All files are not uploaded. Ignored are config files.

## 🧩 Architecture & Shared Assets

This repository is structured as a collection of modular, independent tools and projects:

* **Standalone Projects (`/diy/` & `/games/`):** Each individual subfolder inside `/diy/` and `/games/` functions as a separate, self-contained tool or mini-application.
* **Global Shared Dependencies:** To keep the codebase DRY (Don't Repeat Yourself) and ensure UI consistency, all projects tap into shared root assets:
  * 🎨 `/css/styles.css` — Central stylesheet providing unified layout and UI styling.
  * 🛠️ `/includes/reusable.php` — Shared backend helper functions, global components, and utilities.
  * ⚡ `/js/scripts.js` — Common JavaScript logic and interactive scripts.
   * 
---

## 💼 CfCbazar Web Platform

A modular PHP-based platform for CfCbazar that integrates token mining, games, utilities, and DIY tools. Designed for ESP8266 compatibility, rewards, and user engagement through creative features.

### 🌐 Live Endpoints

- **Mining API**: [`http://cfc-api.atwebpages.com/api.php`](http://cfc-api.atwebpages.com/api.php)  
- **Dashboard**: [`http://cfcbazar.42wwb.io/d.php`](http://cfcbazar.42web.io/d.php)

---

### 🔧 Platform Highlights

- ⛏️ ESP8266-compatible token mining via `api.php`
- 🏦 Platform-controlled token supply with reserve enforcement
- 🔄 Token syncing from remote API to dashboard via `testapi.php`
- 💰 Deposit and withdrawal system for WorkToken and WorkTHR
- 🎮 Mini-games with token rewards
- 🛠️ DIY guides, calculators, and utilities
- 📺 Media features like YouTube playlists and speed tests
- 🔐 Full user system with registration, login, email verification, and password recovery

# Instructions
Here is a clean, structured guide for setting up the web services using your GitHub repository and configuration files.

---

## Web Services Setup Guide

Follow these steps to deploy and configure the web application on your web server.

---

## 1. Clone the Repository

Clone the repository to your local machine or directly to your web server using Git:

```bash
git clone https://github.com/ArakelTheDragon/CfCbazar_WebDev.git

```

---

## 2. Upload Files to the Web Server

If you cloned the repository locally, upload all files and folders to your web host’s root directory (e.g., `public_html/` or `htdocs/`) via **FTP/SFTP** or your hosting control panel's **File Manager**.

---

## 3. Create Configuration Files

To complete the setup, you must create two configuration files in the root directory: **`server.php`** (for user authentication and session management) and **`config.php`** (for database settings and API keys).

> **Important Security Note:** Replace all template values, passwords, and private keys with your own actual environment credentials before deploying.

---

### File A: `server.php`

Create a file named `server.php` in the root directory and add the following template:

```php
<?php 
session_start();

// Variable declarations
$username = "";
$email    = "";
$errors   = array(); 
$_SESSION['success'] = "";

// Database Connection Settings
$db_host = "YOUR_DATABASE_HOST";     // e.g., sql313.infinityfree.com
$db_user = "YOUR_DATABASE_USER";     // e.g., if0_39103611
$db_pass = "YOUR_DATABASE_PASSWORD"; // e.g., YourDatabasePassword
$db_name = "YOUR_DATABASE_NAME";     // e.g., if0_39103611_db1

// Connect to database
$db = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}

// REGISTER USER
if (isset($_POST['reg_user'])) {
    // Receive and sanitize input values
    $username   = mysqli_real_escape_string($db, $_POST['username']);
    $email      = mysqli_real_escape_string($db, $_POST['email']);
    $password_1 = mysqli_real_escape_string($db, $_POST['password_1']);
    $password_2 = mysqli_real_escape_string($db, $_POST['password_2']);

    // Form validation
    if (empty($username)) { array_push($errors, "Username is required"); }
    if (empty($email)) { array_push($errors, "Email is required"); }
    if (empty($password_1)) { array_push($errors, "Password is required"); }

    if ($password_1 != $password_2) {
        array_push($errors, "The two passwords do not match");
    }

    // Register user if there are no validation errors
    if (count($errors) == 0) {
        $password = md5($password_1); // Encrypt password before saving
        $query = "INSERT INTO users (username, email, password) VALUES('$username', '$email', '$password')";
        mysqli_query($db, $query);

        $_SESSION['username'] = $username;
        $_SESSION['success'] = "You are now logged in";
        header('location: index.php');
        exit();
    }
}

// LOGIN USER
if (isset($_POST['login_user'])) {
    $username = mysqli_real_escape_string($db, $_POST['username']);
    $password = mysqli_real_escape_string($db, $_POST['password']);

    if (empty($username)) {
        array_push($errors, "Username is required");
    }
    if (empty($password)) {
        array_push($errors, "Password is required");
    }

    if (count($errors) == 0) {
        $password = md5($password);
        $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
        $results = mysqli_query($db, $query);

        if (mysqli_num_rows($results) == 1) {
            $_SESSION['username'] = $username;
            $_SESSION['success'] = "You are now logged in";
            header('location: index.php');
            exit();
        } else {
            array_push($errors, "Wrong username/password combination");
        }
    }
}
?>

```

---

### File B: `config.php`

Create a file named `config.php` in the root directory and add the following template:

```php
<?php
// Error Reporting (Set display_errors to 0 in production environments)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Credentials
$servername = "YOUR_DATABASE_HOST";
$username   = "YOUR_DATABASE_USER";
$password   = "YOUR_DATABASE_PASSWORD";
$dbname     = "YOUR_DATABASE_NAME";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Web3 / Blockchain Configurations
$meta_private_key    = 'YOUR_METAMASK_PRIVATE_KEY';
$meta_wallet         = 'YOUR_METAMASK_WALLET_ADDRESS';
$token_address       = 'YOUR_TOKEN_PROXY_ADDRESS';
$token_logic_address = 'YOUR_TOKEN_LOGIC_ADDRESS';
$bscscan_api_key     = 'YOUR_BSCSCAN_API_KEY';

// MintMe API Credentials
$api_key             = "YOUR_MINTME_API_KEY";
$oauth_client_id     = "YOUR_MINTME_OAUTH_CLIENT_ID";
$oauth_client_secret = "YOUR_MINTME_OAUTH_CLIENT_SECRET";
$oauth_access_token  = ""; 

// MintMe Wallet Credentials
$private_key   = "YOUR_MINTME_PRIVATE_KEY";
$mintme_wallet = "YOUR_MINTME_WALLET_ADDRESS";

// SMTP Credentials (Brevo / Mailer)
$smtp_host = 'smtp-relay.brevo.com';
$smtp_user = 'YOUR_SMTP_USERNAME';
$smtp_pass = 'YOUR_SMTP_PASSWORD';
?>

```

---

## 4. Finalizing Setup

1. Ensure your database tables (such as `users`) are imported and created on your MySQL server.
2. Ensure both `config.php` and `server.php` are added to `.gitignore` so sensitive credentials are never publicly committed back to version control.
