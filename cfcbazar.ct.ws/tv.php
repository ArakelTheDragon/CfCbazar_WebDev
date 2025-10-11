<?php
// CfCbazar Free TV - Public Access to Movies, Music, and Sports
ob_start();
require 'includes/reusable.php';

$userEmail = $_SESSION['email'] ?? '';
$title = 'CfCbazar Free TV - Movies, Music, Sports';

include_header();
?>
<meta name="description" content="Stream free public domain movies, original music, and curated sports highlights on CfCbazar Free TV. Enjoy movies like The Lost World, music like Pump It, and sports content, no subscription needed!">
<?php include_menu(); ?>

<main class="container tv-container" style="padding-top: 70px;">
  <h1 class="page-title">📺 CfCbazar Free TV</h1>

  <section>
    <h2>Channel 4: Free Public Domain Movies</h2>
    <p class="highlights">Watch classic public domain films like Irish Luck, Popeye for President, and The Lost World.</p>
    <p>Enjoy a curated selection of free movies on CfCbazar Movie TV. Our collection features timeless public domain films, including adventure classics like <em>The Lost World</em> and beloved cartoons like <em>Popeye for President</em>. Stream high-quality content without subscription fees, perfect for movie enthusiasts. Check back for new additions or explore more at <a href="/index.php">CfCbazar Home</a>! Sign up at <a href="/register.php">Register</a> for exclusive features.</p>
    <iframe src="https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig7wtDx6qWNTF69kUCvW5Cjb" allowfullscreen title="CfCbazar Movie TV: Free Public Domain Movies" loading="lazy"></iframe>
  </section>

  <section>
    <h2>Channel 2: Original Music & Remixes</h2>
    <p class="highlights">Original music & remixes — Pump It v1 & v2, I'm a Survivor Remix, Disco Inferno.</p>
    <p>Discover original music and remixes on CfCbazar Music TV. From high-energy tracks like <em>Pump It v1 & v2</em> to creative remixes like <em>I'm a Survivor</em> and <em>Disco Inferno</em>, our playlist offers free music streaming for all tastes. Perfect for music lovers seeking unique, royalty-free tracks. Join <a href="/register.php">CfCbazar</a> to stay updated on new releases!</p>
    <iframe src="https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu" allowfullscreen title="CfCbazar Music TV: Original Music and Remixes" loading="lazy"></iframe>
  </section>

  <section>
    <h2>Channel 3: Curated Sports Highlights</h2>
    <p class="highlights">Sports-themed content curated by CfCbazar.</p>
    <p>Experience the thrill of sports with CfCbazar YT Sport TV. Our curated playlist features exciting sports highlights and content, handpicked by CfCbazar. Whether you're a fan of action-packed moments or in-depth sports analysis, our free streaming service has something for every sports enthusiast. Visit <a href="/index.php">CfCbazar Home</a> for more or sign up at <a href="/register.php">Register</a>!</p>
    <iframe src="https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig7PuROupmf6PK4Hd57XQjcE" allowfullscreen title="CfCbazar YT Sport TV: Curated Sports Highlights" loading="lazy"></iframe>
  </section>

  <script type="application/ld+json">
  [
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": "CfCbazar Movie TV: Free Public Domain Movies",
      "description": "Stream free public domain movies like Irish Luck, Popeye for President, and The Lost World on CfCbazar Movie TV.",
      "embedUrl": "https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig7wtDx6qWNTF69kUCvW5Cjb",
      "thumbnailUrl": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg",
      "uploadDate": "2025-09-28",
      "publisher": {
        "@type": "Organization",
        "name": "CfCbazar",
        "logo": {
          "@type": "ImageObject",
          "url": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg"
        }
      }
    },
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": "CfCbazar Music TV: Original Music and Remixes",
      "description": "Discover original music and remixes like Pump It v1 & v2, I'm a Survivor Remix, and Disco Inferno on CfCbazar Music TV.",
      "embedUrl": "https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu",
      "thumbnailUrl": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg",
      "uploadDate": "2025-09-28",
      "publisher": {
        "@type": "Organization",
        "name": "CfCbazar",
        "logo": {
          "@type": "ImageObject",
          "url": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg"
        }
      }
    },
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": "CfCbazar YT Sport TV: Curated Sports Highlights",
      "description": "Watch curated sports highlights and content on CfCbazar YT Sport TV, perfect for sports enthusiasts.",
      "embedUrl": "https://www.youtube.com/embed/videoseries?list=PLY4e42xsZig7PuROupmf6PK4Hd57XQjcE",
      "thumbnailUrl": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg",
      "uploadDate": "2025-09-28",
      "publisher": {
        "@type": "Organization",
        "name": "CfCbazar",
        "logo": {
          "@type": "ImageObject",
          "url": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg"
        }
      }
    }
  ]
  </script>
</main>

<?php
include_footer();
ob_end_flush();
?>