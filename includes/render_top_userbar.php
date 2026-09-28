<?php
/**
 * CfCbazar Top Bar – Rotating Adverts
 * File: /includes/render_top_userbar.php
 *
 * Sticky bar under the main header that cycles through advert links.
 */

declare(strict_types=1);

if (!function_exists('render_top_userbar')) {

    function render_top_userbar(): void
    {
        // Edit this list freely – text + URL
        $ads = [
            ['text' => '🛒 Smart Deals on eBay',           'url' => 'https://ebay.us/m/DM1tRs'],
            ['text' => '🎨 Visit our Etsy Store',          'url' => 'https://cfcbazar.etsy.com'],
            ['text' => '🛍️ Shopify Store – New Items',    'url' => 'https://store-cfcbazar.myshopify.com/'],
            ['text' => '🛠️ Free DIY Tools & Guides',      'url' => '/diy/'],
            ['text' => '🎮 Play Games & Earn Tokens',     'url' => '/games/'],
            ['text' => '💰 Worker Dashboard',             'url' => '/worktoken/'],
            ['text' => '📺 Free TV & Entertainment',      'url' => 'https://www.youtube.com/@cfcbazar/playlists'],
            ['text' => '📢 Join Smart Deals Facebook Group', 'url' => 'https://www.facebook.com/groups/'],
        ];

        $id = 'cfc-adbar-' . substr(md5((string) microtime(true)), 0, 6);

        echo '<div id="' . $id . '" class="cfc-adbar" style="position:static;top:75px;left:0;right:0;background:#111;color:#fff;padding:14px 16px;z-index:100;border-bottom:1px solid rgba(255,255,255,.1);box-sizing:border-box;font-size:18px;text-align:center;overflow:hidden;min-height:56px;line-height:28px;display:flex;align-items:center;justify-content:center;">';

        foreach ($ads as $i => $ad) {
            $text = htmlspecialchars($ad['text'], ENT_QUOTES, 'UTF-8');
            $url  = htmlspecialchars($ad['url'], ENT_QUOTES, 'UTF-8');
            $display = $i === 0 ? 'inline' : 'none';
            echo '<a class="cfc-ad-item" href="' . $url . '" target="_blank" rel="noopener" style="display:' . $display . ';color:#fff;text-decoration:none;font-weight:600;">' . $text . '</a>';
        }

        echo '</div>';

        echo <<<JS
<script>
(function(){
  var bar = document.getElementById('{$id}');
  if (!bar) return;
  var items = bar.querySelectorAll('.cfc-ad-item');
  if (items.length < 2) return;
  var idx = 0;
  setInterval(function(){
    items[idx].style.display = 'none';
    idx = (idx + 1) % items.length;
    items[idx].style.display = 'inline';
  }, 4000);
})();
</script>
JS;
    }
}
