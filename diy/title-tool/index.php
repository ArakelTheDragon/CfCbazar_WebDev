<?php
// ----------------------------
// Config
// ----------------------------
$dataFile = __DIR__ . "/data.json";
$titlesDir = __DIR__ . "/titles/";

// Ensure titles directory exists
if (!is_dir($titlesDir)) {
    mkdir($titlesDir, 0755, true);
}

// ----------------------------
// Init JSON
// ----------------------------
if (!file_exists($dataFile)) {
    $default = [
        "categories" => [
            "main1" => [],
            "main2" => [],
            "main3" => [],
            "main4" => [],
            "main5" => []
        ]
    ];
    file_put_contents($dataFile, json_encode($default, JSON_PRETTY_PRINT), LOCK_EX);
}

$data = json_decode(file_get_contents($dataFile), true);

// ----------------------------
// Helpers
// ----------------------------
function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function safeFileName($name) {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9-_ ]/', '', $name);
    return str_replace(' ', '-', $name);
}

// ----------------------------
// Add Subcategory
// ----------------------------
if (isset($_POST["add_sub"])) {
    $cat = $_POST["category"] ?? '';
    $sub = trim($_POST["subcategory"] ?? '');

    if (isset($data["categories"][$cat]) && $sub !== "") {
        if (!isset($data["categories"][$cat][$sub])) {
            $data["categories"][$cat][$sub] = [];
            saveData($dataFile, $data);
        }
    }
}

// ----------------------------
// Add Title + Article
// ----------------------------
if (isset($_POST["add_title"])) {
    $cat = $_POST["category"] ?? '';
    $sub = $_POST["subcategory"] ?? '';
    $title = trim($_POST["title"] ?? '');
    $article = $_POST["article"] ?? '';

    if (
        $title !== "" &&
        isset($data["categories"][$cat]) &&
        isset($data["categories"][$cat][$sub])
    ) {
        if (!in_array($title, $data["categories"][$cat][$sub])) {

            $data["categories"][$cat][$sub][] = $title;
            saveData($dataFile, $data);

            $safeName = safeFileName($title);
            $filePath = $titlesDir . $safeName . ".php";

            // FULL HTML PAGE with shared CSS
            $content = "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>" . htmlspecialchars($title) . "</title>
<link rel='stylesheet' href='../styles.css'>
</head>
<body>

<div class='container'>

<a href='../index.php' style='display:inline-block;margin-bottom:15px;'>← Back</a>

<div class='box'>
    <h1>" . htmlspecialchars($title) . "</h1>
    <p>" . nl2br(htmlspecialchars($article)) . "</p>
</div>

</div>

</body>
</html>";

            file_put_contents($filePath, $content, LOCK_EX);
        }
    }
}

// ----------------------------
// Search
// ----------------------------
function searchTitles($data, $query) {
    $words = array_filter(explode(" ", strtolower($query)));
    $results = [];

    foreach ($data["categories"] as $cat => $subs) {
        foreach ($subs as $sub => $titles) {
            foreach ($titles as $t) {
                $score = 0;
                foreach ($words as $w) {
                    if (str_contains(strtolower($t), $w)) {
                        $score++;
                    }
                }
                if ($score > 0) {
                    $results[] = [
                        "title" => $t,
                        "category" => $cat,
                        "subcategory" => $sub,
                        "score" => $score
                    ];
                }
            }
        }
    }

    usort($results, fn($a, $b) => $b["score"] <=> $a["score"]);
    return $results;
}

$searchResults = [];
if (isset($_GET["search"])) {
    $searchResults = searchTitles($data, $_GET["search"]);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Title Tool</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container">

<h1>Title Tool</h1>

<!-- SEARCH -->
<div class="box">
    <h2>Search</h2>
    <form method="get">
        <input type="text" name="search" placeholder="Search titles..." value="<?= htmlspecialchars($_GET["search"] ?? '') ?>">
        <button>Search</button>
    </form>

    <?php if ($searchResults): ?>
        <h3>Results:</h3>
        <?php foreach ($searchResults as $r): ?>
            <div>
                <strong><?= htmlspecialchars($r["title"]) ?></strong>
                (<?= htmlspecialchars($r["category"]) ?> → <?= htmlspecialchars($r["subcategory"]) ?>)
                — score: <?= $r["score"] ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ADD SUBCATEGORY -->
<div class="box">
    <h2>Add Subcategory</h2>
    <form method="post">
        <select name="category">
            <?php foreach ($data["categories"] as $cat => $subs): ?>
                <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="subcategory" placeholder="New subcategory">
        <button name="add_sub">Add</button>
    </form>
</div>

<!-- ADD TITLE -->
<div class="box">
    <h2>Add Title + Article</h2>
    <form method="post">
        <select name="category" id="catSelect" onchange="updateSubs()">
            <?php foreach ($data["categories"] as $cat => $subs): ?>
                <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
        </select>

        <select name="subcategory" id="subSelect"></select>

        <input type="text" name="title" placeholder="New title">

        <textarea name="article" placeholder="Write the article here..."></textarea>

        <button name="add_title">Add</button>
    </form>
</div>

<!-- BROWSE -->
<div class="box">
    <h2>Browse</h2>
    <?php foreach ($data["categories"] as $cat => $subs): ?>
        <h3><?= htmlspecialchars($cat) ?></h3>
        <?php foreach ($subs as $sub => $titles): ?>
            <strong><?= htmlspecialchars($sub) ?></strong><br>
            <?php foreach ($titles as $t): ?>
                <?php $safe = safeFileName($t); ?>
                <a class="title-link" href="titles/<?= $safe ?>.php">
                    <?= htmlspecialchars($t) ?>
                </a>
            <?php endforeach; ?>
            <br>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

</div>

<script>
const data = <?= json_encode($data["categories"]) ?>;

function updateSubs() {
    let cat = document.getElementById("catSelect").value;
    let subSelect = document.getElementById("subSelect");
    subSelect.innerHTML = "";

    for (let s in data[cat]) {
        let opt = document.createElement("option");
        opt.value = s;
        opt.textContent = s;
        subSelect.appendChild(opt);
    }
}
updateSubs();
</script>

</body>
</html>