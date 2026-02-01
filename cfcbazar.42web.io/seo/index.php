<?php

ini_set('display_errors', 0);
error_reporting(0);

function extract_tag($html, $tag) {
    if (preg_match("/<$tag[^>]*>(.*?)<\/$tag>/is", $html, $m)) {
        return trim($m[1]);
    }
    return "";
}

function extract_meta($html, $name) {
    if (preg_match('/<meta[^>]+name=["\']'.$name.'["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return trim($m[1]);
    }
    return "";
}

function scan_pages($root) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $pages = [];

    foreach ($rii as $file) {
        if ($file->isDir()) continue;

        $path = $file->getPathname();

        if (!preg_match('/\.(php|html|htm)$/i', $path)) continue;

        $html = @file_get_contents($path);
        if (!$html) continue;

        $rel = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);

        $pages[] = [
            "url" => $rel,
            "title" => extract_tag($html, "title"),
            "description" => extract_meta($html, "description"),
            "keywords" => extract_meta($html, "keywords"),
            "robots" => extract_meta($html, "robots"),
            "h1" => extract_tag($html, "h1"),
            "last_modified" => date("Y-m-d", filemtime($path)),
            "size" => filesize($path)
        ];
    }

    return $pages;
}

function generate_sitemap($pages) {
    $base = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    foreach ($pages as $p) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>{$base}{$p['url']}</loc>\n";
        $xml .= "    <lastmod>{$p['last_modified']}</lastmod>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>";

    file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/sitemap.xml", $xml);
}

$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$pages = scan_pages($root);

if (isset($_GET['generate_sitemap'])) {
    generate_sitemap($pages);
    echo "Sitemap generated.";
    exit;
}

header('Content-Type: application/json');
echo json_encode(["pages" => $pages], JSON_PRETTY_PRINT);

