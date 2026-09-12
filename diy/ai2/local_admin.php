<?php
/* ============================================================
   CfCbazar Local AI — Web Admin Panel for Skill Management
   File: /diy/ai/local_admin.php
   ============================================================ */

$localFile = __DIR__ . "/local.php";

/* ------------------------------------------------------------
   Load current skill list from local.php
------------------------------------------------------------ */

$localCode = file_get_contents($localFile);

preg_match('/\$skillList\s*=\s*

\[(.*?)\]

;/s', $localCode, $match);

$currentSkills = [];

if (!empty($match[1])) {
    $raw = trim($match[1]);
    $lines = explode(",", $raw);

    foreach ($lines as $line) {
        $line = trim($line);
        $line = str_replace(["\"", "'"], "", $line);
        if ($line !== "") {
            $currentSkills[] = $line;
        }
    }
}

/* ------------------------------------------------------------
   Handle form submission
------------------------------------------------------------ */

$message = "";

if (!empty($_POST["skills"])) {

    $newSkills = explode("\n", trim($_POST["skills"]));
    $newSkills = array_map("trim", $newSkills);
    $newSkills = array_filter($newSkills);

    // Build new skill list code
    $newSkillCode = "\$skillList = [\n";
    foreach ($newSkills as $s) {
        $newSkillCode .= "    \"$s\",\n";
    }
    $newSkillCode .= "];";

    // Replace old skill list in local.php
    $updatedCode = preg_replace(
        '/\$skillList\s*=\s*

\[(.*?)\]

;/s',
        $newSkillCode,
        $localCode
    );

    file_put_contents($localFile, $updatedCode);

    $message = "Skill list updated successfully!";
}

/* ------------------------------------------------------------
   HTML Interface
------------------------------------------------------------ */
?>
<!DOCTYPE html>
<html>
<head>
<title>Local AI Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { background:#0f172a; color:#f8fafc; font-family:Arial; padding:20px; }
.container { max-width:700px; margin:auto; background:#1e293b; padding:20px; border-radius:10px; }
textarea { width:100%; height:200px; background:#0f172a; color:#fff; border:1px solid #475569; padding:10px; }
button { padding:12px 20px; background:#2563eb; color:#fff; border:none; border-radius:8px; cursor:pointer; }
button:hover { background:#1d4ed8; }
.msg { margin-top:10px; padding:10px; background:#334155; border-left:4px solid #38bdf8; }
</style>
</head>
<body>

<div class="container">
<h2>Local AI Admin Panel</h2>
<p>Edit your skill list below. One skill per line.</p>

<form method="POST">
<textarea name="skills"><?php
foreach ($currentSkills as $s) echo $s . "\n";
?></textarea>

<br><br>
<button type="submit">Save Skill List</button>
</form>

<?php if ($message): ?>
<div class="msg"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

</div>

</body>
</html>

