<?php
require_once __DIR__ . "/../../includes/reusable.php";

// --- Page metadata -----------------------------------------------------------
$url        = "diy/index.php";
$parent_url = "diy";
$title      = "CfC Free TV — Free Shows & Public‑Domain Entertainment";
$template   = "Generic";   // optional: e.g. "tool", "article", "game"
$meta_title = "CfC Free TV — Free Shows & Public‑Domain Entertainment";
$meta_desc  = "CfC Free TV — a curated playlist of free shows, public‑domain content, documentaries, indie videos, and open‑access entertainment.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CfC Free TV — Free Shows & Public‑Domain Entertainment</title>

    <meta name="description" content="CfC Free TV — a curated playlist of free shows, public‑domain content, documentaries, indie videos, and open‑access entertainment.">
    <meta name="keywords" content="CfC Free TV, free shows, public domain, documentaries, indie content, CfCbazar DIY">
    <meta name="author" content="CfCbazar">
    <link rel="canonical" href="https://cfcbazar.42web.io/diy/cfc-free-tv/">

    <meta property="og:title" content="CfC Free TV — Free Shows & Public‑Domain Entertainment">
    <meta property="og:description" content="Watch free shows, documentaries, indie content, and public‑domain classics curated by CfCbazar.">
    <meta property="og:image" content="/assets/cfcbazar-preview.png">
    <meta property="og:url" content="https://cfcbazar.42web.io/diy/cfc-free-tv/">
    <meta property="og:type" content="article">

    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>

<div class="container">

    <div class="header">
        <h1>CfC Free TV</h1>
    </div>

    <!-- INTRO -->
    <div class="card">
        <p class="muted">
            Welcome to <strong>CfC Free TV</strong>, a curated collection of free shows, public‑view programs,
            documentaries, indie content, and open‑access entertainment from around the web.
        </p>

        <p>
            I search for high‑quality, freely available videos and bring them together in one place so you can relax,
            explore, and discover something new without paying a cent.
        </p>

        <p>
            This playlist is part of the <strong>CfCbazar ecosystem</strong> — a growing hub for smart tools,
            DIY guides, games, music, and the WorkToken system.  
            By enjoying CfC Free TV, you’re helping support the project and its mission to make useful digital content
            accessible to everyone.
        </p>

        <p>
            🎬 <strong>Watch the full playlist here:</strong><br>
            <a href="https://youtube.com/playlist?list=PLY4e42xsZig63V3ApQaBkjDLiBFB-C0eE&si=E9pMrzKRLeVrvWNT" target="_blank">
                CfC Free TV Playlist on YouTube
            </a>
        </p>
    </div>

    <!-- WHAT YOU'LL FIND -->
    <div class="card">
        <h2>What You’ll Find on CfC Free TV</h2>

        <ul>
            <li>Public‑domain and public‑view shows</li>
            <li>Free documentaries and educational programs</li>
            <li>Open‑licensed entertainment</li>
            <li>Indie creators and hidden gems</li>
            <li>Relaxing, fun, and binge‑friendly content</li>
        </ul>

        <p class="muted">
            New videos are added regularly as more public‑domain and open‑licensed content becomes available.
            Expect classic cartoons, retro films, indie animations, educational shorts, and more.
        </p>
    </div>

    <!-- VIDEO LIST -->
    <div class="card">
    <h2>CfC Free TV – Full Playlist</h2>

    <ul>
        <li><strong>Popeye the Sailor Meets Sinbad the Sailor</strong> — 16:07</li>
        <li><strong>Popeye: Parlez Vous Woo</strong> — 6:11</li>
        <li><strong>Popeye: Cooking With Gags</strong> — 6:34</li>
        <li><strong>Popeye: Taxi Turvy</strong> — 6:03</li>
        <li><strong>Popeye: Gopher Spinach</strong> — 6:28</li>
        <li><strong>Popeye: Spree Lunch</strong> — 6:04</li>
        <li><strong>Popeye for President</strong> — 6:11</li>

        <li><strong>Abe Lincoln of the Ninth Avenue</strong> (1939) — 1:04:24</li>
        <li><strong>The Flapper</strong> (1920) — 1:25:29</li>
        <li><strong>Street Angel</strong> (1928) — 1:41:29</li>
        <li><strong>The Lost World</strong> (1925) — 1:08:06</li>
        <li><strong>The Hunchback of Notre Dame</strong> (1923) — Part Two — 5:00</li>

        <li><strong>Detour</strong> (1945) — 1:07:39</li>
        <li><strong>Gang Bullets</strong> (1938) — 57:45</li>
        <li><strong>Outside the Law</strong> (1920)</li>

        <li><strong>House on Haunted Hill</strong> (1959) — 1:14:44</li>
        <li><strong>The Masque of the Red Death</strong> (1964) — 1:18:22</li>
        <li><strong>Theatre of Death</strong> (1967) — 1:42:39</li>

        <li><strong>She Shoulda Said No!</strong> (1949) — 1:09:08</li>
        <li><strong>Under California Stars</strong> (1948)</li>

        <li><em>More videos coming soon…</em></li>
    </ul>

    <p class="muted">
        This playlist continues to grow as more public‑domain and open‑licensed content is added.
        Expect more classic films, retro animations, indie gems, and educational programs.
    </p>
</div>

    <!-- WATCH ON TV -->
    <div class="card">
    <h2>How to Watch CfC Free TV on Your TV</h2>

    <img src="images/img1.jpg" alt="How to watch CfC Free TV on TV"
         style="width:100%; border-radius:12px; margin-bottom:15px;">

    <p>
        Watching the <a href="https://youtube.com/playlist?list=PLY4e42xsZig63V3ApQaBkjDLiBFB-C0eE&si=F5wt55lSI_05y1cx" target="_blank">playlist</a>
        on your TV is simple using the YouTube app:
    </p>

    <ol>
        <li>Open the <a href="https://youtube.com/playlist?list=PLY4e42xsZig63V3ApQaBkjDLiBFB-C0eE&si=F5wt55lSI_05y1cx" target="_blank">playlist</a> on your phone.</li>
        <li>Tap the <strong>⋮ menu</strong> in the top‑right corner.</li>
        <li>Select <strong>“Watch on TV”</strong>.</li>
        <li>Choose your smart TV or Chromecast device.</li>
    </ol>

    <p class="muted">
        Your phone becomes the remote, and the <a href="https://youtube.com/playlist?list=PLY4e42xsZig63V3ApQaBkjDLiBFB-C0eE&si=F5wt55lSI_05y1cx" target="_blank">playlist</a>
        plays directly on your television — perfect for relaxing, background entertainment, or discovering new free content.
    </p>
</div>

    <!-- ECOSYSTEM -->
    <div class="card">
        <h2>Explore More at CfCbazar</h2>

        <p>
            If you enjoy CfC Free TV, check out the full CfCbazar platform for:
        </p>

        <ul>
            <li>DIY electronics projects</li>
            <li>Tools and utilities</li>
            <li>Games and music</li>
            <li>The WorkToken system</li>
        </ul>

        <p>
            🌐 <a href="https://cfcbazar.42web.io" target="_blank">Visit CfCbazar</a>
        </p>

        <p class="muted">
            Sit back, press play, and enjoy CfC Free TV.  
            #FreeTV #PublicDomainShows #OpenMedia #CfCbazar #FreeEntertainment #OnlineShows
        </p>
    </div>

</div>

<footer class="footer">
    © CfCbazar — Free TV & Open Media
</footer>

</body>
</html>
