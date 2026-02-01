<?php
require '/config.php';

$slug = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($slug !== 'index.php' && ctype_digit($slug)) {
    $stmt = $db->prepare('SELECT `long` FROM short_links WHERE id = ?');
    $stmt->execute([$slug]);
    if ($dest = $stmt->fetchColumn()) {
        header("Location: $dest");
        exit;
    }
    http_response_code(404);
    echo 'Link not found.';
    exit;
}
?>

<!-- UI for shortening -->
<form onsubmit="return shorten();">
  <input id="u" type="text" placeholder="Paste link" autocomplete="off">
  <button type="submit">Shorten</button>
</form>
<div id="out"></div>

<script>
async function shorten() {
  const url = document.getElementById('u').value.trim();
  if (!url) return false;

  const r = await fetch('/server.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({url})
  });

  const j = await r.json();
  document.getElementById('out').textContent = j.short || j.error;
  return false;
}
</script>