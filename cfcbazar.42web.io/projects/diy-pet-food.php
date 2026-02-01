<?php
require_once __DIR__ . '/../includes/reusable.php';

if (!function_exists('enforce_https')) {
    function enforce_https() {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (!headers_sent()) {
                header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
                exit;
            }
        }
    }
}
enforce_https();
track_visits();
include_header("DIY Pet Food: Stick & Mix", "Easy homemade pet food recipe using meat sticks, veggies, and rice. Safe, simple, and vet-friendly.");
include_menu();
?>

<div class="container">
  <h1 class="page-title">🐶 DIY Pet Food: Stick & Mix</h1>
  <p class="intro">A simple, nutritious recipe for homemade pet food using everyday ingredients. Perfect for small batches and beginner pet owners.</p>

  <div class="card">
    <img src="projects/images/diy-pet-food.jpeg" alt="Pet Food Ingredients" style="width:100%; max-width:600px; display:block; margin:20px auto; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
  </div>

  <div class="card">
    <h2>🛒 Ingredients</h2>
    <ul>
      <li>🐾 1 plain meat stick (chicken or beef)</li>
      <li>🐾 1 carrot (grated)</li>
      <li>🐾 1 zucchini (sliced)</li>
      <li>🐾 ½ cup cooked rice</li>
      <li>🐾 1 tsp fish oil</li>
      <li>🐾 Pinch of crushed eggshells</li>
    </ul>
  </div>

  <div class="card">
    <h2>🥣 Instructions</h2>
    <ol>
      <li>Chop the meat stick into small pieces</li>
      <li>Mix with grated carrot and zucchini</li>
      <li>Add cooked rice and stir well</li>
      <li>Drizzle fish oil and sprinkle eggshell powder</li>
      <li>Serve fresh or refrigerate up to 2 days</li>
    </ol>
  </div>

  <div class="card">
    <h2>⚠️ Safety Tips</h2>
    <ul>
      <li>🐾 Use plain, unsalted meat sticks (no spices)</li>
      <li>🐾 Never include onions, garlic, or chocolate</li>
      <li>🐾 Consult a vet for long-term feeding plans</li>
    </ul>
  </div>
</div>

<?php include_footer(); ?>