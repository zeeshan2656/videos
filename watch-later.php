<?php
// ============================================================
// FreeHub.Live — Watch Later
// ============================================================
$meta_title = 'Watch Later';
require_once __DIR__ . '/includes/header.php';
require_login();

$uid = auth_user()['id'];

// Handle remove action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    if (!empty($_POST['remove_id'])) {
        $rid = (int)$_POST['remove_id'];
        db_query("DELETE FROM watch_later WHERE user_id=? AND video_id=?", [$uid, $rid]);
    }
    if (!empty($_POST['clear_all'])) {
        db_query("DELETE FROM watch_later WHERE user_id=?", [$uid]);
    }
    redirect(BASE_URL . '/watch-later.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;

$total = db_fetch("SELECT COUNT(*) as c FROM watch_later WHERE user_id=?", [$uid])['c'] ?? 0;
$pg = paginate($total, $per_page, $page);

$items = db_fetchAll(
    "SELECT wl.*, v.title, v.slug, v.thumbnail, v.duration, v.views, v.status, v.visibility,
            u.username, u.channel_name, u.avatar
     FROM watch_later wl
     JOIN videos v ON v.id = wl.video_id
     JOIN users u ON u.id = v.user_id
     WHERE wl.user_id = ?
     ORDER BY wl.added_at DESC
     LIMIT ? OFFSET ?",
    [$uid, $per_page, $pg['offset']]
);
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content">
    <div class="section-header" style="margin-bottom:20px">
      <h1 style="font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:8px">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Watch Later
      </h1>
      <?php if ($total > 0): ?>
        <form method="POST" onsubmit="return confirm('Remove all videos from Watch Later?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="clear_all" value="1"
                  class="btn btn-outline btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.2)">
            Clear All
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$items): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text2)">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <h2 style="font-size:1.1rem;margin-bottom:6px;color:var(--text)">No saved videos</h2>
        <p>Save videos to watch later and they'll appear here.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-4">
        <?php foreach ($items as $item):
          $v = $item;
          $url = BASE_URL . '/watch.php?v=' . $v['video_id'];
          $thumb = thumb_url($v['thumbnail']);
          $dur = format_duration((int)$v['duration']);
          $views = format_number((int)$v['views']);
          $ago = time_ago($v['added_at']);
          $ch = e($v['channel_name'] ?: $v['username']);
          $title = e($v['title']);
          $av = avatar_url($v['avatar']);
        ?>
        <article class="video-card fade-in" style="position:relative">
          <div onclick="location.href='<?= $url ?>'">
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
                    <span>Saved <?= $ago ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <form method="POST" style="position:absolute;top:8px;right:8px;z-index:5">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="remove_id" value="<?= $v['video_id'] ?>">
            <button type="submit" class="btn btn-sm" title="Remove"
                    style="background:rgba(0,0,0,.7);color:#fff;border:none;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.9rem">&times;</button>
          </form>
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
