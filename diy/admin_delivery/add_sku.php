<?php
/**
 * SKU Map Admin Manager & Inventory Editor
 * Location: /diy/admin_delivery/add_sku.php
 * Brand / Author: CfCbazar Group
 *
 * Aligned with process_orders.php (strict SKU resolution).
 * - Only `name` and `link` are written per entry. `id` is not used by the runtime.
 * - The reserved key DEFAULT cannot be created, overwritten, or deleted through this UI.
 * - `link` must parse as http(s) URL.
 * - Saving an existing key overwrites it (with a confirmation on the form).
 */

$skuMapFile = __DIR__ . '/sku_map.json';
$message = '';
$messageType = '';
$RESERVED_KEY = 'DEFAULT';
$DEFAULT_ENTRY = [
    'name' => 'CfCbazar Digital Guide',
    'link' => 'https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf',
];

function loadSkuMap(string $path, array $defaultEntry): array {
    if (!file_exists($path)) {
        return ['DEFAULT' => $defaultEntry];
    }
    $raw = json_decode(file_get_contents($path), true);
    if (!is_array($raw)) {
        return ['DEFAULT' => $defaultEntry];
    }
    // Normalize: keep only name + link for each entry; ensure DEFAULT exists.
    $out = [];
    foreach ($raw as $k => $v) {
        if (!is_array($v) || !isset($v['name'], $v['link'])) continue;
        $key = ($k === 'DEFAULT') ? 'DEFAULT' : strtoupper(trim((string)$k));
        $out[$key] = [
            'name' => (string)$v['name'],
            'link' => (string)$v['link'],
        ];
    }
    if (!isset($out['DEFAULT'])) {
        $out['DEFAULT'] = $defaultEntry;
    }
    return $out;
}

function saveSkuMap(string $path, array $map): bool {
    $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

function isValidHttpUrl(string $url): bool {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return false;
    return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $skuMap = loadSkuMap($skuMapFile, $DEFAULT_ENTRY);

    if ($action === 'delete') {
        $targetKey = strtoupper(trim($_POST['target_key'] ?? ''));
        if ($targetKey === $RESERVED_KEY) {
            $message = "The <strong>DEFAULT</strong> entry is reserved and cannot be deleted. Edit it instead.";
            $messageType = "error";
        } elseif (isset($skuMap[$targetKey])) {
            unset($skuMap[$targetKey]);
            if (saveSkuMap($skuMapFile, $skuMap)) {
                $message = "Deleted SKU entry: <strong>" . htmlspecialchars($targetKey) . "</strong>";
                $messageType = "success";
            } else {
                $message = "Failed to write sku_map.json during deletion.";
                $messageType = "error";
            }
        } else {
            $message = "Entry not found: <strong>" . htmlspecialchars($targetKey) . "</strong>";
            $messageType = "error";
        }
    } else {
        $mapKey = strtoupper(trim($_POST['map_key'] ?? ''));
        $name   = trim($_POST['name'] ?? '');
        $link   = trim($_POST['link'] ?? '');

        if ($mapKey === '' || $name === '' || $link === '') {
            $message = "SKU, title, and link are required.";
            $messageType = "error";
        } elseif (!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,127}$/', $mapKey)) {
            $message = "SKU must be 1–128 chars: letters, digits, dot, underscore, dash; must start with a letter or digit.";
            $messageType = "error";
        } elseif (!isValidHttpUrl($link)) {
            $message = "Download link must be a valid http:// or https:// URL.";
            $messageType = "error";
        } elseif ($mapKey === $RESERVED_KEY) {
            // Treat form save of DEFAULT as "edit default", which is allowed.
            $skuMap[$RESERVED_KEY] = ['name' => $name, 'link' => $link];
            if (saveSkuMap($skuMapFile, $skuMap)) {
                $message = "Updated reserved <strong>DEFAULT</strong> entry.";
                $messageType = "success";
            } else {
                $message = "Failed to write sku_map.json.";
                $messageType = "error";
            }
        } else {
            $existed = isset($skuMap[$mapKey]);
            $skuMap[$mapKey] = ['name' => $name, 'link' => $link];
            if (saveSkuMap($skuMapFile, $skuMap)) {
                $message = ($existed ? "Updated" : "Added") . " SKU entry: <strong>" . htmlspecialchars($mapKey) . "</strong>";
                $messageType = "success";
            } else {
                $message = "Failed to write sku_map.json. Check file permissions.";
                $messageType = "error";
            }
        }
    }
}

$currentMap = loadSkuMap($skuMapFile, $DEFAULT_ENTRY);
$totalEntries = count($currentMap);
$defaultEntry = $currentMap['DEFAULT'] ?? $DEFAULT_ENTRY;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar - SKU Map Inventory & Editor</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .wrapper { max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2, h3 { color: #2c3e50; margin-top: 0; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50; font-size: 14px; }
        input[type="text"], input[type="url"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .hint { font-size: 12px; color: #666; margin-top: 4px; }
        button.btn-primary { background: #27ae60; color: #fff; border: none; padding: 12px 20px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; }
        button.btn-primary:hover { background: #219653; }
        .sku-item { border: 1px solid #e1e8ed; border-radius: 6px; margin-bottom: 10px; background: #fafbfc; overflow: hidden; }
        .sku-header { padding: 12px 15px; background: #edf2f7; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: bold; font-size: 14px; color: #2c3e50; }
        .sku-header:hover { background: #e2e8f0; }
        .sku-body { padding: 15px; display: none; background: #fff; border-top: 1px solid #e1e8ed; }
        .sku-item.active .sku-body { display: block; }
        .badge { background: #cbd5e0; color: #2d3748; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: monospace; }
        .badge-reserved { background: #fef3c7; color: #92400e; }
        .actions { margin-top: 12px; display: flex; gap: 10px; }
        button.btn-save { background: #3182ce; color: #fff; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button.btn-save:hover { background: #2b6cb0; }
        button.btn-delete { background: #e53e3e; color: #fff; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button.btn-delete:hover { background: #c53030; }
        button.btn-delete:disabled { background: #a0aec0; cursor: not-allowed; }
        .search-box { margin-bottom: 15px; width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .reserved-section { background: #fef9e7; border: 1px solid #fde68a; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <h2>Add / Update SKU Map Entry</h2>
        <p>Add a new product or modify existing entries in your <code>sku_map.json</code> inventory map.</p>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" id="skuForm">
            <input type="hidden" name="action" value="save">
            <div class="form-group">
                <label for="map_key">SKU (must match eBay Custom Label):</label>
                <input type="text" id="map_key" name="map_key" placeholder="e.g. ULTIMATE-GUIDE-COFFEE-V2" required>
                <div class="hint">Letters, digits, dot, underscore, dash. Uppercased automatically. Must exactly match the SKU on your eBay listing.</div>
            </div>

            <div class="form-group">
                <label for="name">Product Title / Name:</label>
                <input type="text" id="name" name="name" placeholder="e.g. Ultimate Guide On Making Coffee v2" required>
            </div>

            <div class="form-group">
                <label for="link">Download Link (Google Drive / Secure URL):</label>
                <input type="url" id="link" name="link" placeholder="e.g. https://drive.google.com/drive/folders/..." required>
            </div>

            <button type="submit" class="btn-primary">Save to sku_map.json</button>
        </form>
    </div>

    <div class="card">
        <h3>Reserved DEFAULT entry</h3>
        <p>The <code>DEFAULT</code> entry is used when an order's SKU is missing or not found in the map. Edit it here; it cannot be deleted.</p>
        <form method="POST" class="reserved-section">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="map_key" value="DEFAULT">
            <div class="form-group">
                <label for="default_name">DEFAULT product title:</label>
                <input type="text" id="default_name" name="name" value="<?php echo htmlspecialchars($defaultEntry['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="default_link">DEFAULT download link:</label>
                <input type="url" id="default_link" name="link" value="<?php echo htmlspecialchars($defaultEntry['link']); ?>" required>
            </div>
            <button type="submit" class="btn-save">Update DEFAULT</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing SKU Catalog (<?php echo $totalEntries; ?> items)</h3>
        <p>Click any item below to expand its details, edit its values directly, or delete it.</p>

        <input type="text" id="searchFilter" class="search-box" placeholder="Search catalog by title or SKU..." onkeyup="filterCatalog()">

        <div id="catalogList" style="margin-top: 15px;">
            <?php if (empty($currentMap)): ?>
                <p style="text-align:center; color:#777;">No items found in sku_map.json.</p>
            <?php else: ?>
                <?php foreach ($currentMap as $key => $data): ?>
                    <?php $isDefault = ($key === 'DEFAULT'); ?>
                    <div class="sku-item" data-search="<?php echo strtolower(htmlspecialchars($key . ' ' . $data['name'])); ?>">
                        <div class="sku-header" onclick="toggleItem(this)">
                            <span>
                                <?php echo htmlspecialchars($key); ?>
                                <?php if ($isDefault): ?>
                                    <span class="badge badge-reserved">RESERVED</span>
                                <?php endif; ?>
                            </span>
                            <span>▼</span>
                        </div>
                        <div class="sku-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save">
                                <div class="form-group">
                                    <label>SKU:</label>
                                    <input type="text" name="map_key" value="<?php echo htmlspecialchars($key); ?>" <?php echo $isDefault ? 'readonly' : ''; ?> required>
                                </div>
                                <div class="form-group">
                                    <label>Product Title:</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($data['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Download Link:</label>
                                    <input type="url" name="link" value="<?php echo htmlspecialchars($data['link']); ?>" required>
                                </div>
                                <div class="actions">
                                    <button type="submit" class="btn-save">Update Entry</button>
                            </form>
                                    <form method="POST" onsubmit="return confirm('Delete SKU <?php echo htmlspecialchars(addslashes($key)); ?>?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="target_key" value="<?php echo htmlspecialchars($key); ?>">
                                        <button type="submit" class="btn-delete" <?php echo $isDefault ? 'disabled title="DEFAULT cannot be deleted"' : ''; ?>>Delete</button>
                                    </form>
                                </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleItem(headerElem) {
    const item = headerElem.parentElement;
    item.classList.toggle('active');
}

function filterCatalog() {
    const query = document.getElementById('searchFilter').value.toLowerCase();
    const items = document.getElementsByClassName('sku-item');
    for (let i = 0; i < items.length; i++) {
        const text = items[i].getAttribute('data-search');
        items[i].style.display = text.includes(query) ? '' : 'none';
    }
}
</script>
</body>
</html>
