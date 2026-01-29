<?php
// CfCbazar Item Search — Visit Tracking + SEO
header('Content-Type: text/html; charset=UTF-8');

require 'config.php'; // defines $conn

// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
  $upd->bind_param('s', $path);
  $upd->execute();

  if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'index' : $slug;
    $title = 'Item Search & Supplier Links';

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

$itemsFile = 'items.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data && isset($data['name']) && isset($data['supplier'])) {
        $items = file_exists($itemsFile) ? json_decode(file_get_contents($itemsFile), true) : [];
        $items[] = [
            'name' => trim($data['name']),
            'supplier' => trim($data['supplier']),
            'image' => 'images/id' . count($items) . '.jpg'
        ];
        file_put_contents($itemsFile, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar Item Search & Supplier Links | Free Tool 🚚</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Search and add items with supplier links instantly on CfCbazar. A free tool for finding products, tracking suppliers, and managing your shopping list.">
    <meta name="keywords" content="CfCbazar, item search, supplier links, shopping tool, free product search, product finder, ecommerce tools">
    <meta name="author" content="CfCbazar">
    <meta name="robots" content="index, follow">

    <!-- Open Graph for social media -->
    <meta property="og:title" content="CfCbazar Item Search & Supplier Links">
    <meta property="og:description" content="Free product search tool to find items and suppliers instantly. Add new items with ease.">
    <meta property="og:image" content="https://cfcbazar.ct.ws/images/preview.jpg">
    <meta property="og:url" content="https://cfcbazar.ct.ws/item-search.php">
    <meta property="og:type" content="website">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CfCbazar Item Search & Supplier Links">
    <meta name="twitter:description" content="Find products and suppliers instantly. Add items with ease using CfCbazar's free shopping tool.">
    <meta name="twitter:image" content="https://cfcbazar.ct.ws/images/preview.jpg">

    <!-- Schema.org markup -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "CfCbazar Item Search",
        "url": "https://cfcbazar.ct.ws/item-search.php",
        "description": "Free online tool to search and add items with supplier links instantly.",
        "applicationCategory": "Productivity",
        "operatingSystem": "All"
    }
    </script>

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f4f8;
            margin: 0;
            padding: 40px 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        h2 {
            text-align: center;
            font-size: 1.2rem;
            font-weight: normal;
            color: #666;
            margin-top: -20px;
            margin-bottom: 30px;
        }
        .container {
            max-width: 800px;
            margin: auto;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
            display: block;
            margin-top: 10px;
            border-radius: 4px;
        }
        #status {
            margin-top: 20px;
            font-weight: 500;
            color: #555;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🚚 CfCbazar Item Search</h1>
    <h2>Find items with supplier links instantly — free and easy to use</h2>
    <input type="text" id="search-box" placeholder="Search item" />
    <div id="results"></div>
    <div id="status"></div>
</div>

<script>
    let items = [];

    fetch('items.json')
        .then(res => res.json())
        .then(data => {
            items = data;
            displayResults([]);
        })
        .catch(err => {
            document.getElementById('status').textContent = 'Failed to load items.';
            console.error(err);
        });

    function displayResults(results) {
        const container = document.getElementById('results');
        container.innerHTML = '';
        if (results.length === 0) {
            document.getElementById('status').textContent = 'No matches found.';
            return;
        }
        results.forEach(({ name, supplier, image }) => {
            const card = document.createElement('div');
            card.className = 'card';
            card.innerHTML = `
                <strong>${name}</strong><br>
                <a href="${supplier}" target="_blank" rel="noopener noreferrer">${supplier}</a><br>
                <img src="${image}" alt="${name} preview" onerror="this.style.display='none';">
            `;
            container.appendChild(card);
        });
        document.getElementById('status').textContent = `${results.length} result(s) found.`;
    }

    function handleSearchInput(e) {
        const input = e.target.value.trim();
        if (input.startsWith('add:')) {
            const [name, supplier] = input.slice(4).split(',').map(x => x.trim());
            if (!name || !supplier) {
                document.getElementById('status').textContent = 'Invalid format.';
                return;
            }
            document.getElementById('status').textContent = 'Adding item...';

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, supplier })
            })
            .then(res => res.json())
            .then(resp => {
                if (resp.status === 'success') {
                    const newIndex = items.length;
                    items.push({ name, supplier, image: `images/id${newIndex}.jpg` });
                    document.getElementById('status').textContent = `Added: ${name}`;
                    displayResults([]);
                    document.getElementById('search-box').value = '';
                } else {
                    document.getElementById('status').textContent = 'Error adding item.';
                }
            });
            return;
        }

        const terms = input.toLowerCase().split(' ').filter(Boolean);
        const results = items.filter(item =>
            terms.some(term =>
                item.name.toLowerCase().includes(term) ||
                item.supplier.toLowerCase().includes(term)
            )
        );
        displayResults(results);
    }

    document.getElementById('search-box').addEventListener('input', handleSearchInput);
</script>
</body>
</html>