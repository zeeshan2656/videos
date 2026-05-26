<?php
// ============================================================
// FreeHub.Live — Homepage
// ============================================================
$meta_title = 'FreeHub — Watch, Share & Earn';
$meta_desc  = 'Discover trending videos, share and earn with our affiliate program.';
require_once __DIR__ . '/includes/header.php';

// ── Selected category filter ─────────────────────────────────
$sel_cat = (int)($_GET['cat'] ?? $_COOKIE['fh_category'] ?? 0);
$cat_filter = "";
$params = [];
if ($sel_cat) {
    $cat_filter = " AND (v.category_id = ? OR EXISTS (SELECT 1 FROM video_categories vc WHERE vc.video_id = v.id AND vc.category_id = ?))";
    $params = [$sel_cat, $sel_cat];
}

// ── Fetch hero / featured video ──────────────────────────────
$hero = db_fetch(
    "SELECT v.*,u.username,u.channel_name,u.avatar
     FROM videos v
     JOIN users u ON u.id=v.user_id
     WHERE v.status='published' AND v.featured=1 AND v.visibility='public' {$cat_filter}
     ORDER BY v.published_at DESC LIMIT 1",
    $params
);

// ── Trending ─────────────────────────────────────────────────
$trending = db_fetchAll(
    "SELECT v.*,u.username,u.channel_name,u.avatar
     FROM videos v
     JOIN users u ON u.id=v.user_id
     WHERE v.status='published' AND v.visibility='public' {$cat_filter}
     ORDER BY v.views DESC LIMIT 10",
    $params
);

// ── Latest ───────────────────────────────────────────────────
$latest = db_fetchAll(
    "SELECT v.*,u.username,u.channel_name,u.avatar
     FROM videos v
     JOIN users u ON u.id=v.user_id
     WHERE v.status='published' AND v.visibility='public' {$cat_filter}
     ORDER BY v.published_at DESC LIMIT 12",
    $params
);

// ── Categories ───────────────────────────────────────────────
$categories = db_fetchAll(
    "SELECT c.*,(SELECT COUNT(*) FROM videos WHERE category_id=c.id AND status='published') as video_count
     FROM categories c WHERE c.is_active=1 ORDER BY c.sort_order LIMIT 10"
);

// ── Selected Category Videos List ────────────────────────────
$cat_videos = [];
if ($sel_cat) {
    $cat_videos = db_fetchAll(
        "SELECT v.*,u.username,u.channel_name,u.avatar
         FROM videos v JOIN users u ON u.id=v.user_id
         WHERE v.status='published' AND v.visibility='public' {$cat_filter}
         ORDER BY v.views DESC LIMIT 16",
        $params
    );
}

function render_ad_placeholder(string $placement, int $position_after = 1): string {
    // Server-side ad rendering — queries the DB directly, no JS AJAX needed
    $device = detect_device();
    $now = date('Y-m-d');
    $ad = db_fetch(
        "SELECT id, title, content_type, content, target_url, image_url,
                ad_width, ad_height
         FROM ads
         WHERE is_active=1
           AND placement=?
           AND (device_target=? OR device_target='all')
           AND position_after=?
           AND (start_date IS NULL OR start_date <= ?)
           AND (end_date IS NULL OR end_date >= ?)
         ORDER BY RAND() LIMIT 1",
        [$placement, $device, $position_after, $now, $now]
    );
    if (!$ad) return '';

    // Track impression
    db_query("UPDATE ads SET impressions=impressions+1 WHERE id=?", [$ad['id']]);

    $sponsored_label = '<div style="font-size:.68rem;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:flex;align-items:center;gap:4px"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>Sponsored</div>';

    $size_style = '';
    if ($ad['ad_width']) $size_style .= 'max-width:' . (int)$ad['ad_width'] . 'px;';
    if ($ad['ad_height']) $size_style .= 'max-height:' . (int)$ad['ad_height'] . 'px;';

    $inner = '';
    if ($ad['content_type'] === 'image' && $ad['image_url']) {
        $img_src = str_starts_with($ad['image_url'], 'http') ? $ad['image_url'] : BASE_URL . '/uploads/ads/' . $ad['image_url'];
        $click_url = $ad['target_url'] ?: '#';
        $inner = '<a href="' . e($click_url) . '" target="_blank" rel="noopener" data-ad-id="' . $ad['id'] . '" class="ad-click-link">'
               . '<img src="' . e($img_src) . '" alt="' . e($ad['title']) . '" style="max-width:100%;height:auto;display:block;border-radius:4px">'
               . '</a>';
    } elseif ($ad['content_type'] === 'html') {
        $inner = $ad['content'];
    } else {
        $click_url = $ad['target_url'] ?: '#';
        $inner = '<a href="' . e($click_url) . '" target="_blank" rel="noopener" data-ad-id="' . $ad['id'] . '" class="ad-click-link" style="font-weight:700;color:var(--accent);text-decoration:underline;font-size:.9rem">'
               . e($ad['content'] ?: $ad['title']) . '</a>';
    }

    return '<div class="ad-sponsored-container" style="margin:24px 0;padding:16px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);text-align:center">'
         . $sponsored_label
         . '<div style="margin:0 auto;display:inline-block;max-width:100%;' . $size_style . '">' . $inner . '</div>'
         . '</div>';
}

// Auto-detect missing durations for videos shown on this page
$durationPool = array_merge($trending, $latest, $cat_videos, $hero ? [$hero] : []);
fh_sync_zero_durations($durationPool, 30);
$refreshDuration = static function (array $rows): array {
    foreach ($rows as $i => $row) {
        if ((int)($row['duration'] ?? 0) === 0) {
            $fresh = db_fetch('SELECT duration FROM videos WHERE id=?', [(int)$row['id']]);
            if ($fresh && (int)$fresh['duration'] > 0) {
                $rows[$i]['duration'] = $fresh['duration'];
            }
        }
    }
    return $rows;
};
$trending   = $refreshDuration($trending);
$latest     = $refreshDuration($latest);
$cat_videos = $refreshDuration($cat_videos);
if ($hero && (int)($hero['duration'] ?? 0) === 0) {
    $fresh = db_fetch('SELECT duration FROM videos WHERE id=?', [(int)$hero['id']]);
    if ($fresh && (int)$fresh['duration'] > 0) {
        $hero['duration'] = $fresh['duration'];
    }
}

$ref = auth_user()['ref_code'] ?? '';
$creatorId    = (is_logged_in() && is_partner()) ? (int)auth_user()['id'] : 0;
$earningsMap  = [];
if ($creatorId) {
    $pool = array_merge($trending, $latest, $cat_videos, $hero ? [$hero] : []);
    $ownIds = [];
    foreach ($pool as $row) {
        if ((int)($row['user_id'] ?? 0) === $creatorId) {
            $ownIds[] = (int)$row['id'];
        }
    }
    if ($ownIds) {
        $earningsMap = fh_creator_video_earnings_map($creatorId, $ownIds);
    }
}
?>

<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <!-- ── Main ── -->
  <main class="main-content" id="main">
    <?php foreach (get_flash() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <!-- Hero -->
    <?php if ($hero): ?>
    <section class="hero" aria-label="Featured video">
      <img src="<?= thumb_url($hero['thumbnail']) ?>" alt="<?= e($hero['title']) ?>"
           class="hero-bg" loading="eager" width="1280" height="720">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <span class="hero-badge">&#9733; Featured</span>
        <h1 class="hero-title"><?= e(truncate($hero['title'], 80)) ?></h1>
        <div class="hero-meta">
          <span><?= e($hero['channel_name'] ?: $hero['username']) ?></span>
          <span>&#183;</span>
          <span><?= format_number((int)$hero['views']) ?> views</span>
          <span>&#183;</span>
          <span><?= time_ago($hero['published_at'] ?? $hero['created_at']) ?></span>
        </div>
        <div class="hero-actions">
          <a href="<?= BASE_URL ?>/watch.php?v=<?= $hero['id'] ?><?= $ref ? '&ref='.$ref : '' ?>" class="btn-play">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Play Now
          </a>
          <a href="<?= BASE_URL ?>/watch.php?v=<?= $hero['id'] ?>" class="btn btn-outline" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.3);color:#fff">
            + Watch Later
          </a>
        </div>
      </div>
    </section>
    <?php endif; ?>



    <!-- Category Videos -->
    <?php if ($sel_cat && $cat_videos): ?>
    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Category Videos</h2>
        <a href="?cat=<?= $sel_cat ?>" class="see-all">See all &rarr;</a>
      </div>
      <div class="grid grid-4">
        <?php foreach ($cat_videos as $v) echo render_video_card($v, fh_video_card_opts($v, $earningsMap, $ref)); ?>
      </div>
    </section>
    <?= render_ad_placeholder('between_sections', 1) ?>
    <?php endif; ?>

    <!-- Trending -->
    <?php if ($trending): ?>
    <section class="section">
      <div class="section-header">
        <h2 class="section-title">&#128293; Trending Now</h2>
        <a href="<?= BASE_URL ?>/search.php?sort=views" class="see-all">See all &rarr;</a>
      </div>
      <div class="grid grid-5">
        <?php foreach (array_slice($trending, 0, 5) as $v) echo render_video_card($v, fh_video_card_opts($v, $earningsMap, $ref)); ?>
      </div>
    </section>
    <?= render_ad_placeholder('between_sections', 2) ?>
    <?php endif; ?>

    <!-- Latest Uploads -->
    <?php if ($latest): ?>
    <section class="section">
      <div class="section-header">
        <h2 class="section-title">&#127381; Latest Uploads</h2>
        <a href="<?= BASE_URL ?>/search.php?sort=latest" class="see-all">See all &rarr;</a>
      </div>
      <div class="grid grid-4" id="video-grid">
        <?php foreach ($latest as $v) echo render_video_card($v, fh_video_card_opts($v, $earningsMap, $ref)); ?>
      </div>
      <div style="text-align:center;margin-top:24px">
        <button class="btn btn-outline" id="load-more" data-page="2">Load More</button>
      </div>
    </section>
    <?= render_ad_placeholder('between_sections', 3) ?>
    <?php endif; ?>

    <!-- No videos state -->
    <?php if (!$trending && !$latest): ?>
    <div style="text-align:center;padding:80px 20px;color:var(--text2)">
      <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
      <h2 style="font-size:1.2rem;margin-bottom:8px;color:var(--text)">No videos yet</h2>
      <p>Be the first to upload — <a href="<?= BASE_URL ?>/partner/" style="color:var(--accent)">Join Partner Program</a></p>
    </div>
    <?php endif; ?>
  </main>
</div>

<script>
const FH_CREATOR_ID = <?= (int)$creatorId ?>;
// ── Ad click tracking (server-side rendered ads) ────────────
document.addEventListener('click', function(ev) {
  const link = ev.target.closest('.ad-click-link');
  if (link && link.dataset.adId) {
    const fd = new FormData();
    navigator.sendBeacon?.('<?= BASE_URL ?>/api/ads.php?action=track_click&id=' + link.dataset.adId, fd);
  }
});

// ── Infinite scroll / Load More ───────────────────────────────
function bindLoadMore() {
  const loadMoreBtn = document.getElementById('load-more');
  if (!loadMoreBtn) return;
  
  // Remove existing listeners by cloning the button
  const newBtn = loadMoreBtn.cloneNode(true);
  loadMoreBtn.parentNode.replaceChild(newBtn, loadMoreBtn);
  
  newBtn.addEventListener('click', async function(){
    const btn = this;
    const page = parseInt(btn.dataset.page);
    btn.textContent = 'Loading…';
    btn.disabled = true;
    try {
      const catId = localStorage.getItem('fh_selected_category') || 0;
      const res = await fetch(`<?= BASE_URL ?>/api/videos.php?page=${page}&per_page=12&cat=${catId}`);
      const data = await res.json();
      const grid = document.getElementById('video-grid');
      if (data.videos && data.videos.length) {
        data.videos.forEach(v => {
          const el = document.createElement('article');
          el.className = 'video-card fade-in';
          el.onclick = () => location.href = v.url;
          const durBadge = v.duration_fmt
            ? `<span class="video-duration">${v.duration_fmt}</span>`
            : `<span class="video-duration video-duration--pending">…</span>`;
          const earnHtml = (FH_CREATOR_ID && v.user_id === FH_CREATOR_ID && v.earnings_fmt)
            ? `<div class="video-earnings" title="Watch-time earnings on this video"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>${v.earnings_fmt} earned</span></div>`
            : '';
          el.innerHTML = `
            <div class="video-thumb" style="position:relative">
              <img src="${v.thumbnail}" alt="${v.title}" loading="lazy" width="320" height="180" class="thumb-main">
              ${durBadge}
            </div>
            <div class="video-info">
              <div class="flex gap-3" style="align-items:flex-start">
                <img src="${v.avatar}" class="channel-avatar" loading="lazy" width="32" height="32">
                <div style="min-width:0">
                  <div class="video-title">${v.title}</div>
                  <div class="video-meta">
                    <span>${v.channel}</span><span>·</span>
                    <span style="display:inline-flex;align-items:center;gap:3px">
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      ${v.views}
                    </span><span>·</span><span>${v.ago}</span>
                  </div>
                  ${earnHtml}
                </div>
              </div>
            </div>`;
          grid.appendChild(el);
        });
        btn.dataset.page = page + 1;
        btn.textContent = 'Load More';
        btn.disabled = false;
        if (!data.has_next) btn.style.display = 'none';
      } else {
        btn.style.display = 'none';
      }
    } catch(e) {
      btn.textContent = 'Load More';
      btn.disabled = false;
    }
  });
}
bindLoadMore();
window.bindLoadMore = bindLoadMore;
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
