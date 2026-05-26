<?php
// Dynamic XML Sitemap
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$videos = db_fetchAll(
    "SELECT id,slug,updated_at,published_at FROM videos WHERE status='published' AND visibility='public' ORDER BY published_at DESC LIMIT 5000"
);
$categories = db_fetchAll("SELECT slug,updated_at FROM categories WHERE is_active=1");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">

  <url><loc><?= BASE_URL ?>/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
  <url><loc><?= BASE_URL ?>/search.php</loc><changefreq>hourly</changefreq><priority>0.9</priority></url>
  <url><loc><?= BASE_URL ?>/auth/register.php</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>

  <?php foreach($categories as $c): ?>
  <url>
    <loc><?= BASE_URL ?>/search.php?cat_slug=<?= urlencode($c['slug']) ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.7</priority>
    <lastmod><?= date('Y-m-d', strtotime($c['updated_at'])) ?></lastmod>
  </url>
  <?php endforeach; ?>

  <?php foreach($videos as $v): ?>
  <url>
    <loc><?= BASE_URL ?>/watch.php?v=<?= $v['id'] ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
    <lastmod><?= date('Y-m-d', strtotime($v['published_at']??$v['updated_at'])) ?></lastmod>
  </url>
  <?php endforeach; ?>

</urlset>
