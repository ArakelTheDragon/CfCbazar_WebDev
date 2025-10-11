<?php
// features.php — CfCbazar DIY Tools & Dashboard (Public Access)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("config.php");

// Page visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = 'Features & DIY Tools';

        $ins = $conn->prepare("
            INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            $ins->execute();
            $ins->close();
        }
    }
    $upd->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  
  <title>DIY Tools, Features & WorkToken Dashboard | CfCbazar</title>
  
  <meta name="description" content="Access CfCbazar's DIY tools and features: URL shortener, power calculator, survival expense tracker, work value tables, numbers lookup, and more." />
  <meta name="keywords" content="DIY tools, WorkToken dashboard, URL shortener, power calculator, survival tool, work value table, phone number lookup, online quizzes, CfCbazar" />
  <meta name="robots" content="index, follow" />
  
  <!-- Open Graph for social sharing -->
  <meta property="og:title" content="DIY Tools & WorkToken Dashboard | CfCbazar" />
  <meta property="og:description" content="Explore CfCbazar's features including a URL shortener, power calculator, survival tool, and more." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://yourdomain.com/features/" />
  <meta property="og:image" content="https://yourdomain.com/assets/og-image.png" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="DIY Tools & WorkToken Dashboard | CfCbazar" />
  <meta name="twitter:description" content="Explore CfCbazar's tools like URL shortener, power calculator, survival expense tracker, and manage your WorkTokens." />
  <meta name="twitter:image" content="https://yourdomain.com/assets/twitter-image.png" />
  
  <style>  
    body {  
      font-family: Arial, sans-serif;  
      background: #f5f7fa;  
      margin: 0;  
      padding: 2rem;  
      max-width: 600px;  
      margin: auto;  
      color: #333;  
    }  
    h1 { color: #007acc; }  
    .card {  
      background: #fff;  
      padding: 1rem 1.5rem;  
      border-radius: 8px;  
      margin-top: 1.5rem;  
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);  
    }  
    a {  
      display: inline-block;  
      margin-top: 1rem;  
      padding: 10px 20px;  
      background: #28a745;  
      color: white;  
      border-radius: 6px;  
      text-decoration: none;  
    }  
    .balance-box {  
      margin-top: 1rem;  
      font-size: 1.2em;  
      font-weight: bold;  
    }  
  </style>  
</head>
<body>
  <h1>Welcome!</h1>
  <div class="balance-box">Explore our tools below.</div>
  <div><a href="/index.php">🏠 Go to Home</a></div>
  
  <div class="card">
    <h2>🎮 Games</h2>
    <p>Want to play instead?</p>
    <a href="games.php">Go to Games</a>
  </div>

  <div class="card">
    <h2>🔗 URL Shortener</h2>
    <p>Share links, pay with tokens based on traffic.</p>
    <a href="/r.php">URL Shortener</a>
  </div>    

  <div class="card">
    <h2>🔌 Power Calculator</h2>    
    <p>Check the power consumption of your devices & how much it costs you.</p>    
    <a href="power.php">Power Calculator</a>
  </div>  
  
  <div class="card">
    <h2>💲 Survival Tool</h2>
    <p>Check your expenses for basic survival.</p>   
    <a href="survival.php">Survival Tool</a>  
  </div>

  <div class="card">
    <h2>💼 Work Value Table</h2>
    <p>Check the value of work in different regions for different professions, products & services.</p>
    <a href="work_value.php">Work Value Table</a>
  </div>    

  <div class="card">
    <h2>📞 Numbers Lookup</h2>
    <p>Check who called you and the owner of the number.</p>
    <a href="numbers.php">Numbers</a>
  </div> 
  
  <div class="card">
    <h2>🛠️ DIY Projects</h2>
    <p>Explore our DIY projects, ESP8266, NodeMCU and everyday hacks!</p>
    <a href="projects">DIY Projects</a>
  </div>   

  <div class="card">
    <h2>🧠 Quizzes & Tasks</h2>
    <p>Participate and get rewarded. (Coming soon!)</p>
    <a href="#">Coming Soon</a>
  </div>
</body>
</html>