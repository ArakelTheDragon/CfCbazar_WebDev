<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/reusable.php";

// Load JSON file
$jsonPath = $_SERVER['DOCUMENT_ROOT'] . "/info.json";
$jsonData = json_decode(file_get_contents($jsonPath), true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CfCbazar Strategy Overview</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>

<body>

<div class="container">

    <div class="header">
        <h1>CfCbazar Strategy Overview</h1>
    </div>

    <!-- BRAND -->
    <div class="card">
        <h2>Brand Identity</h2>
        <p><strong><?php echo htmlspecialchars($jsonData['brand']['name'] ?? ''); ?></strong></p>
        <p class="muted"><?php echo htmlspecialchars($jsonData['brand']['description'] ?? ''); ?></p>

        <h3>Core Ecosystem</h3>
        <ul>
            <?php if (!empty($jsonData['brand']['ecosystem'])): ?>
                <?php foreach ($jsonData['brand']['ecosystem'] as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- PLATFORMS -->
    <div class="card">
        <h2>Platforms</h2>

        <?php if (!empty($jsonData['platforms'])): ?>
            <?php foreach ($jsonData['platforms'] as $platformName => $platform): ?>
                <h3><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $platformName))); ?></h3>
                <?php if (!empty($platform['role'])): ?>
                    <p class="muted"><?php echo htmlspecialchars($platform['role']); ?></p>
                <?php endif; ?>

                <?php if (!empty($platform['url'])): ?>
                    <p><a href="<?php echo htmlspecialchars($platform['url']); ?>" target="_blank"><?php echo htmlspecialchars($platform['url']); ?></a></p>
                <?php endif; ?>

                <?php if (!empty($platform['search_url'])): ?>
                    <p><a href="<?php echo htmlspecialchars($platform['search_url']); ?>" target="_blank">Amazon KDP Search</a></p>
                <?php endif; ?>

                <?php if (!empty($platform['features'])): ?>
                    <ul>
                        <?php foreach ($platform['features'] as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($platform['strategy'])): ?>
                    <h4>Strategy</h4>
                    <ul>
                        <?php foreach ($platform['strategy'] as $step): ?>
                            <li><?php echo htmlspecialchars($step); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($platform['playlists'])): ?>
                    <h4>Playlists</h4>
                    <ul>
                        <?php foreach ($platform['playlists'] as $playlist): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($playlist['name'] ?? ''); ?></strong><br>
                                <a href="<?php echo htmlspecialchars($playlist['url'] ?? '#'); ?>" target="_blank">Open Playlist</a><br>
                                <span class="muted"><?php echo htmlspecialchars($playlist['description'] ?? ''); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <hr style="opacity:0.2; margin:20px 0;">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PRODUCTS -->
    <div class="card">
        <h2>Products</h2>

        <?php if (!empty($jsonData['products']['guides'])): ?>
            <h3>DIY Guides</h3>
            <ul>
                <?php foreach ($jsonData['products']['guides'] as $guide): ?>
                    <li><?php echo htmlspecialchars($guide); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($jsonData['products']['tools'])): ?>
            <h3>Tools</h3>
            <ul>
                <?php foreach ($jsonData['products']['tools'] as $tool): ?>
                    <li><?php echo htmlspecialchars($tool); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($jsonData['products']['entertainment'])): ?>
            <h3>Entertainment</h3>

            <?php if (!empty($jsonData['products']['entertainment']['playlists'])): ?>
                <h4>Playlists</h4>
                <ul>
                    <?php foreach ($jsonData['products']['entertainment']['playlists'] as $ent): ?>
                        <li><?php echo htmlspecialchars($ent); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($jsonData['products']['entertainment']['games'])): ?>
                <h4>Games</h4>
                <ul>
                    <?php foreach ($jsonData['products']['entertainment']['games'] as $game): ?>
                        <li><?php echo htmlspecialchars($game); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- DIRECTORY STRUCTURE -->
    <?php if (!empty($jsonData['directory_structure']['structure'])): ?>
        <div class="card">
            <h2>Directory & File Structure</h2>
            <ul>
                <?php function renderDirectory($items) { ?>
                    <?php foreach ($items as $key => $value): ?>
                        <li>
                            <?php if (is_array($value)): ?>
                                <strong><?php echo htmlspecialchars($key); ?></strong> - <em><?php echo htmlspecialchars($value['description'] ?? 'Directory'); ?></em>
                                <?php if (!empty($value['contents'])): ?>
                                    <ul>
                                        <?php renderDirectory($value['contents']); ?>
                                    </ul>
                                <?php endif; ?>
                            <?php else: ?>
                                <code><?php echo htmlspecialchars($key); ?></code> — <?php echo htmlspecialchars($value); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php } ?>
                <?php renderDirectory($jsonData['directory_structure']['structure']); ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- MARKETING PLAN -->
    <?php if (!empty($jsonData['marketing_plan']['phases'])): ?>
        <div class="card">
            <h2>Marketing Plan</h2>

            <?php foreach ($jsonData['marketing_plan']['phases'] as $phase): ?>
                <h3><?php echo htmlspecialchars($phase['order'] ?? ''); ?>. <?php echo htmlspecialchars($phase['name'] ?? ''); ?></h3>
                <?php if (!empty($phase['actions'])): ?>
                    <ul>
                        <?php foreach ($phase['actions'] as $action): ?>
                            <li><?php echo htmlspecialchars($action); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<footer class="footer">
    © CfCbazar — Strategy Overview
</footer>

</body>
</html>
