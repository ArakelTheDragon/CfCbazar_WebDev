<?php
// Sample array of projects (can be replaced with a database later)
$projects = [
    [
        "title" => "WiFi Weather Station",
        "description" => "Displays temperature and humidity using DHT22 and sends data to ThingSpeak.",
        "link" => "https://github.com/yourusername/weather-station",
        "sketch" => "images/weather_station_sketch.png",
        "schematic" => "banner.jpg"
    ],
    [
        "title" => "Smart Light Controller",
        "description" => "Control lights via web interface using ESP8266 and relay module.",
        "link" => "https://github.com/yourusername/smart-light",
        "sketch" => "images/smart_light_sketch.png",
        "schematic" => "images/smart_light_schematic.png"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ESP8266 Projects</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        h1 { text-align: center; }
        .project { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .project img { max-width: 100%; height: auto; margin-top: 10px; }
        .project a { color: #007BFF; text-decoration: none; }
        .project a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>🚀 My ESP8266 Projects</h1>

    <?php foreach ($projects as $project): ?>
        <div class="project">
            <h2><?= htmlspecialchars($project['title']) ?></h2>
            <p><?= htmlspecialchars($project['description']) ?></p>
            <p><strong>Code & Details:</strong> <a href="<?= htmlspecialchars($project['link']) ?>" target="_blank"><?= htmlspecialchars($project['link']) ?></a></p>
            <p><strong>Sketch:</strong><br><img src="<?= htmlspecialchars($project['sketch']) ?>" alt="Sketch of <?= htmlspecialchars($project['title']) ?>"></p>
            <p><strong>Schematic:</strong><br><img src="<?= htmlspecialchars($project['schematic']) ?>" alt="Schematic of <?= htmlspecialchars($project['title']) ?>"></p>
        </div>
    <?php endforeach; ?>
</body>
</html>