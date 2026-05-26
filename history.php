<?php
// ============================================================
// FreeHub.Live — Watch History
// ============================================================
$meta_title = 'Watch History';
require_once __DIR__ . '/includes/header.php';
require_login();

$uid = auth_user()['id'];

// Handle clear history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    if (!empty($_POST['clear_history'])) {
        db_query("DELETE FROM watch_history WHERE user_id=?", [$uid]);
    }
    redirect(BASE_URL . '/history.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;
$offset = ($page - 1) * $per_page;

$total = db_fetch("SELECT COUNT(*) as c FROM watch_history WHERE user_id=?", [$uid])['c'] ?? 0;
$pg = paginate($total, $per_page, $page);

$history = db_fetchAll(
    "SELECT wh.*, v.title, v.slug, v.thumbnail, v.duration, v.views, v.status, v.visibility,
            u.username, u.channel_name, u.avatar
     FROM watch_history wh
     JOIN videos v ON v.id = wh.video_id
     JOIN users u ON u.id = v.user_id
     WHERE wh.user_id = ?
     ORDER BY wh.last_watched DESC
     LIMIT ? OFFSET ?",
    [$uid, $per_page, $pg['offset']]
);
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content">
    <div class="section-header" style="margin-bottom:20px">
      <h1 style="font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:8px">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
        Watch History
      </h1>
      <?php if ($total > 0): ?>
        <form method="POST" action="<?= BASE_URL ?>/history.php" style="margin:0"
              onsubmit="return confirm('Clear all watch history?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="clear_history" value="1"
                  class="btn btn-outline btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.2)">
            Clear History
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$history): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text2)">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
        <h2 style="font-size:1.1rem;margin-bottom:6px;color:var(--text)">No watch history</h2>
        <p>Videos you watch will appear here.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-4">
        <?php foreach ($history as $h):
          $v = $h;
          $url = BASE_URL . '/watch.php?v=' . $v['video_id'];
          $thumb = thumb_url($v['thumbnail']);
          $dur = format_duration((int)$v['duration']);
          $views = format_number((int)$v['views']);
          $ago = time_ago($v['last_watched']);
          $ch = e($v['channel_name'] ?: $v['username']);
          $title = e($v['title']);
          $av = avatar_url($v['avatar']);
        ?>
        <article class="video-card fade-in" onclick="location.href='<?= $url ?>'">
          <div class="video-thumb" style="position:relative">
            <img src="<?= $thumb ?>" alt="<?= $title ?>" loading="lazy" width="320" height="180" class="thumb-main">
            <span class="video-duration"><?= $dur ?></span>
          </div>
          <div class="video-info">
            <div class="flex gap-3" style="align-items:flex-start">
              <img src="<?= $av ?>" alt="<?= $ch ?>" class="channel-avatar" loading="lazy" width="32" height="32">
              <div style="min-width:0">
                <div class="video-title"><?= $title ?></div>
                <div class="video-meta">
                  <span><?= $ch ?></span>
                  <span>·</span>
                  <span><?= $views ?> views</span>
                  <span>·</span>
                  <span>Watched <?= $ago ?></span>
                </div>
              </div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pg['has_next']): ?>
        <div style="text-align:center;margin-top:24px">
          <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">Load More</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
