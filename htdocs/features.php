<?php
// features.php - Session-protected features page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("config.php");

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['username']);
    header('Location: login.php');
    exit();
}

$email = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            width: 100%;
            height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            display: flex;
            justify-content: center;
            align-items: center;
            width: 90%;
            max-width: 800px;
            height: 8vh;
            margin: 2vh auto;
            border-radius: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            flex-wrap: wrap;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: max(1.8vw, 14px);
            padding: 2vh 2vw;
            flex-grow: 1;
            text-align: center;
            min-width: 100px;
            border-radius: 10px;
            transition: background 0.3s, transform 0.2s;
        }
        .navbar a:hover, .navbar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        .container {
            width: 90%;
            max-width: 800px;
            margin: 10vh auto;
            padding: 5%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: max(3vw, 24px);
            color: #333;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            background: white;
            margin: 1% auto;
            padding: 2%;
            width: 80%;
            border-radius: 5px;
            font-size: 1.5vw;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        a {
            color: #1e3c72;
            text-decoration: none;
        }
        .menu-toggle {
            display: none;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 2vh;
            font-size: 18px;
            border: none;
            width: 90%;
            max-width: 800px;
            margin: 2vh auto;
            text-align: center;
            cursor: pointer;
            border-radius: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        }
        .mobile-menu {
            display: none;
            flex-direction: column;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            width: 90%;
            max-width: 800px;
            margin: auto;
            border-radius: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        }
        .mobile-menu a {
            padding: 2vh;
            font-size: 4vw;
            text-align: center;
            display: block;
            color: white;
            text-decoration: none;
            transition: background 0.3s, transform 0.2s;
        }
        .mobile-menu a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        @media (max-width: 700px) {
            .navbar { display: none; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>

    <!-- Mobile Menu Button -->
    <button class="menu-toggle">☰ Menu</button>

    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php" class="nav-link" data-page="index.php">Home</a>
        <a href="/index.html" class="nav-link" data-page="speed.html">Speed Test</a>
        <a href="games.html" class="nav-link" data-page="games.html">Games</a>
        <a href="features.php" class="nav-link" data-page="features.php">Features</a>
        <a href="privacy.html" class="nav-link" data-page="privacy.html">Privacy</a>
        <a href="help.html" class="nav-link" data-page="help.html">Help</a>
        <a href="?logout=1" class="nav-link" style="color: yellow;">Logout</a>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu">
        <a href="index.php" class="nav-link" data-page="index.php">Home</a>
        <a href="/index.html" class="nav-link" data-page="speed.html">Speed Test</a>
        <a href="games.html" class="nav-link" data-page="games.html">Games</a>
        <a href="features.php" class="nav-link" data-page="features.php">Features</a>
        <a href="privacy.html" class="nav-link" data-page="privacy.html">Privacy</a>
        <a href="help.html" class="nav-link" data-page="help.html">Help</a>
        <a href="?logout=1" class="nav-link" style="color: yellow;">Logout</a>
    </div>

    <div class="container">
        <h1>Website Features</h1>
        <ul>
            <li><a href="/index.html"><b>Speed Test</b></a> - Measure your internet speed accurately with detailed reports.</li>
            <li><a href="emu/auto.html"><b>Automotive Diagnostic Emulator</b></a> - Simulate and test vehicle diagnostics.</li>
            <li><a href="worktoken.html"><b>Unified Labour Compensation</b></a> - WorkToken.</li>
            <li><a href="sa/index.html"><b>Shopping Assistant</b></a> - Get curated shopping recommendations and discounts.</li>
            <li><a href="miner/"><b>Miner</b></a> - Mine with our web miner.</li>            
            <li><a href="store/index.html"><b>Affiliate Store</b></a> - Shop products through our affiliate links for great deals.</li>
            <li><a href="https://www.youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu"><b>CfC Music TV</b></a> - Watch our TV online.</li>
            <li><a href="internet.html"><b>Internet Drop</b></a> - Monitor internet drop.</li>         
        </ul>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const currentPage = window.location.pathname.split("/").pop();
            const links = document.querySelectorAll(".nav-link");

            links.forEach(link => {
                const dataPage = link.getAttribute("data-page");
                if (dataPage && dataPage === currentPage) {
                    link.classList.add("active");
                }
            });

            document.querySelectorAll(".mobile-menu a").forEach(link => {
                link.addEventListener("click", () => {
                    document.querySelector(".mobile-menu").style.display = "none";
                });
            });

            document.querySelector(".menu-toggle").addEventListener("click", () => {
                const menu = document.querySelector(".mobile-menu");
                menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
            });
        });
    </script>
</body>
</html>