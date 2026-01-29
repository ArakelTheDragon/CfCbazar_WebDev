<?php
// CfCbazar Product Importer — Paste Link, Preview, Approve
header('Content-Type: text/html; charset=UTF-8');

$itemsFile = 'items.json';

function fetchMetadata($url) {
    $html = @file_get_contents($url);
    if (!$html) return [];

    $meta = [];

    // Title
    preg_match('/<title>(.*?)<\/title>/i', $html, $title);
    $meta['name'] = $title[1] ?? '';

    // Description
    preg_match('/<meta name="description" content="(.*?)"/i', $html, $desc);
    $meta['description'] = $desc[1] ?? '';

    // OpenGraph Image
    preg_match('/<meta property="og:image" content="(.*?)"/i', $html, $img);
    $meta['image'] = $img[1] ?? '';

    return $meta;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'fetch') {
        $url = trim($_POST['url']);
        $meta = fetchMetadata($url);
        $meta['supplier'] = $url;
        echo json_encode($meta);
        exit;
    }

    if ($action === 'approve') {
        $items = file_exists($itemsFile) ? json_decode(file_get_contents($itemsFile), true) : [];

        $newItem = [
            'name' => trim($_POST['name']),
            'supplier' => trim($_POST['supplier']),
            'price' => trim($_POST['price']),
            'description' => trim($_POST['description']),
            'image' => trim($_POST['image']) ?: 'images/id' . count($items) . '.jpg'
        ];

        $items[] = $newItem;
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
    <title>Add Product to CfCbazar</title>
    <style>
        body { font-family: sans-serif; background: #f9f9f9; padding: 30px; max-width: 600px; margin: auto; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; font-size: 16px; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        img { max-width: 100%; margin-top: 10px; border-radius: 6px; }
        #preview { margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <h1>🛒 Add Product to CfCbazar</h1>
    <input type="text" id="url" placeholder="Paste product link (e.g. Temu)">
    <button onclick="fetchProduct()">Fetch & Preview</button>

    <div id="preview" style="display:none;">
        <input type="text" id="name" placeholder="Product Name">
        <input type="text" id="price" placeholder="Price (optional)">
        <textarea id="description" placeholder="Description"></textarea>
        <input type="text" id="image" placeholder="Image URL">
        <img id="preview-img" src="" alt="Preview Image" onerror="this.style.display='none';">
        <input type="text" id="supplier" readonly>
        <button onclick="approveProduct()">✅ Approve & Save</button>
        <div id="status"></div>
    </div>

    <script>
        function fetchProduct() {
            const url = document.getElementById('url').value.trim();
            if (!url) return alert("Please paste a product link.");

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'fetch', url })
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('preview').style.display = 'block';
                document.getElementById('name').value = data.name || '';
                document.getElementById('description').value = data.description || '';
                document.getElementById('image').value = data.image || '';
                document.getElementById('preview-img').src = data.image || '';
                document.getElementById('supplier').value = data.supplier || '';
            });
        }

        function approveProduct() {
            const payload = {
                action: 'approve',
                name: document.getElementById('name').value,
                price: document.getElementById('price').value,
                description: document.getElementById('description').value,
                image: document.getElementById('image').value,
                supplier: document.getElementById('supplier').value
            };

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(payload)
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('status').textContent = data.status === 'success'
                    ? '✅ Product added successfully!'
                    : '❌ Failed to add product.';
            });
        }
    </script>
</body>
</html>