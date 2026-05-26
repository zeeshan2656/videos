<?php
// ============================================================
// FreeHub.Live — Categories Page
// ============================================================
$meta_title = 'Categories — ' . 'FreeHub';
$meta_desc  = 'Browse videos by category';

require_once __DIR__ . '/includes/header.php';

$categories = db_fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM videos v WHERE (v.category_id=c.id OR EXISTS (SELECT 1 FROM video_categories vc WHERE vc.video_id = v.id AND vc.category_id = c.id)) AND v.status='published') as video_count
     FROM categories c WHERE c.is_active=1 ORDER BY c.sort_order"
);
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content" id="main">
    <div class="section-header" style="margin-bottom:24px">
      <h1 class="section-title">&#128204; All Categories</h1>
    </div>

    <?php if ($categories): ?>
    <div class="grid grid-4" style="gap:24px 16px">
      <?php foreach ($categories as $c):
        $count = format_number((int)$c['video_count']);
        $url = BASE_URL . '/?cat=' . $c['id'];
        $thumb = category_image_url($c['image']);
        $name = e($c['name']);
        $desc = !empty($c['description']) ? e(truncate($c['description'], 50)) : '';
      ?>
      <article class="video-card category-card fade-in" onclick="location.href='<?= $url ?>'" style="cursor:pointer">
        <div class="video-thumb">
          <img src="<?= $thumb ?>" alt="<?= $name ?>" loading="lazy" class="thumb-main">
          <span class="video-duration"><?= $count ?> video<?= $c['video_count'] != 1 ? 's' : '' ?></span>
        </div>
        <div class="video-info">
          <div class="video-title"><?= $name ?></div>
          <div class="video-meta">
            <span>Category</span>
            <?php if ($desc): ?>
            <span>·</span>
            <span><?= $desc ?></span>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:80px 20px;color:var(--text2)">
      <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <h2 style="font-size:1.2rem;margin-bottom:8px;color:var(--text)">No categories yet</h2>
      <p>Check back soon!</p>
    </div>
    <?php endif; ?>
  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
