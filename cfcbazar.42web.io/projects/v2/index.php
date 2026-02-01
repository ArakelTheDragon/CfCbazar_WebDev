<?php
// Load projects from external JSON with error handling
$jsonContent = @file_get_contents('projects.json');
if ($jsonContent === false) {
    $projects = [];
    $errorMessage = 'Error: Could not load projects.json. Please check if the file exists and is readable.';
} else {
    $projects = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $projects = [];
        $errorMessage = 'Error: Invalid JSON format in projects.json. ' . json_last_error_msg();
    } else {
        $errorMessage = '';
    }
}

// Handle search query
$searchQuery = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$searchWords = array_filter(explode(' ', $searchQuery));

// Filter projects by word match count
function matchScore($project, $searchWords) {
    $text = strtolower($project['title'] . ' ' . $project['description']);
    $score = 0;
    foreach ($searchWords as $word) {
        if (stripos($text, $word) !== false) {
            $score++;
        }
    }
    return $score;
}

if ($searchQuery) {
    usort($projects, function($a, $b) use ($searchWords) {
        return matchScore($b, $searchWords) <=> matchScore($a, $searchWords);
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore a collection of innovative ESP8266 projects by CfCbazar, including IoT devices, sensors, and more. Search and view detailed schematics and GitHub code.">
    <meta name="keywords" content="ESP8266 projects, IoT projects, Arduino projects, electronics tutorials, microcontroller projects, CfCbazar">
    <meta name="author" content="CfCbazar">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="CfCbazar's ESP8266 Projects">
    <meta property="og:description" content="Discover ESP8266-based projects with code and schematics. Perfect for makers and hobbyists.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yourwebsite.com/esp8266-projects">
    <meta property="og:image" content="https://yourwebsite.com/images/esp8266-logo.jpg"> <!-- Replace with actual image URL -->
    <title>CfCbazar's Projects | IoT and Microcontroller Tutorials</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom, #f0f4f8, #d9e2ec);
            color: #333;
            padding: 20px;
            margin: 0;
            line-height: 1.6;
        }

        h1 {
            text-align: center;
            margin-bottom: 40px;
            color: #2c3e50;
            font-size: 2.5em;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .search-box {
            max-width: 800px;
            margin: 0 auto 30px;
            padding: 0 15px;
        }

        .search-box form {
            display: flex;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .search-box input[type="text"] {
            flex: 1;
            padding: 15px 20px;
            font-size: 1.2em;
            border: none;
            background: #fff;
            color: #333;
        }

        .search-box input[type="text"]::placeholder {
            color: #aaa;
        }

        .search-box button {
            padding: 15px 30px;
            font-size: 1.2em;
            border: none;
            background-color: #3498db;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-box button:hover {
            background-color: #2980b9;
        }

        .projects-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .project {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .project:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }

        .project h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .project p {
            margin-bottom: 15px;
        }

        .project img {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .project a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .project a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .link-label {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        .error-message {
            max-width: 800px;
            margin: 20px auto;
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            text-align: center;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }

            .search-box input[type="text"],
            .search-box button {
                font-size: 1em;
                padding: 12px 15px;
            }

            .project h2 {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <h1>🚀 CfCbazar's Projects</h1>

    <div class="search-box">
        <form method="get">
            <input type="text" name="search" placeholder="Search projects by title or description..." value="<?= htmlspecialchars($searchQuery) ?>" aria-label="Search ESP8266 projects by title or description">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="projects-container">
        <?php if ($errorMessage): ?>
            <div class="error-message">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php elseif (empty($projects)): ?>
            <div class="error-message">
                No projects available at the moment.
            </div>
        <?php else: ?>
            <?php
            $filteredProjects = array_filter($projects, function($project) use ($searchQuery, $searchWords) {
                return !$searchQuery || matchScore($project, $searchWords) > 0;
            });
            ?>
            <?php if ($searchQuery && empty($filteredProjects)): ?>
                <div class="error-message">
                    No projects found matching your search query: "<?= htmlspecialchars($searchQuery) ?>".
                </div>
            <?php else: ?>
                <?php foreach ($filteredProjects as $project): ?>
                    <div class="project">
                        <h2><?= htmlspecialchars($project['title']) ?></h2>
                        <p><?= htmlspecialchars($project['description']) ?></p>
                        <p><strong>Code & Details:</strong>
                            <a href="<?= htmlspecialchars($project['link']) ?>" target="_blank">
                                <span class="link-label">View on GitHub</span>
                            </a>
                        </p>
                        <p><strong>Schematic:</strong><br>
                            <img src="<?= htmlspecialchars($project['schematic'] ?? '/images/placeholder-schematic.jpg') ?>" alt="Schematic diagram for <?= htmlspecialchars($project['title']) ?> project">
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>