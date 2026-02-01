<?php
header('Content-Type: text/html; charset=UTF-8');

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
    <title>🚚 CfCbazar Assistant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                <a href="${supplier}" target="_blank">${supplier}</a><br>
                <img src="${image}" alt="Preview" onerror="this.style.display='none';">
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
                document.getElementById('status').textContent = 'Invalid format. Use: add:Name,Link';
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
