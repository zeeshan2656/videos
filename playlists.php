<?php
// ============================================================
// FreeHub.Live — Playlists
// ============================================================
$meta_title = 'My Playlists';
require_once __DIR__ . '/includes/header.php';
require_login();

$uid = auth_user()['id'];

// Handle create playlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        if (strlen($title) >= 1) {
            db_insert('playlists', [
                'user_id' => $uid,
                'title'   => $title,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'visibility'  => in_array($_POST['visibility'] ?? '', ['public','private','unlisted']) ? $_POST['visibility'] : 'public',
            ]);
        }
        redirect(BASE_URL . '/playlists.php');
    }
    if ($action === 'delete') {
        $pid = (int)($_POST['playlist_id'] ?? 0);
        db_query("DELETE FROM playlists WHERE id=? AND user_id=?", [$pid, $uid]);
        redirect(BASE_URL . '/playlists.php');
    }
}

$playlists = db_fetchAll(
    "SELECT p.*, 
            (SELECT COUNT(*) FROM playlist_videos pv WHERE pv.playlist_id = p.id) as video_count,
            (SELECT v.thumbnail FROM playlist_videos pv JOIN videos v ON v.id = pv.video_id WHERE pv.playlist_id = p.id ORDER BY pv.sort_order LIMIT 1) as thumbnail
     FROM playlists p
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC",
    [$uid]
);
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content">
    <div class="section-header" style="margin-bottom:20px">
      <h1 style="font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:8px">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        My Playlists
      </h1>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('create-playlist-form').style.display='block';this.style.display='none'">
        + New Playlist
      </button>
    </div>

    <!-- Create Playlist Form (hidden by default) -->
    <div id="create-playlist-form" class="card" style="display:none;margin-bottom:20px;padding:20px">
      <h3 style="font-weight:700;font-size:.95rem;margin-bottom:12px">Create New Playlist</h3>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label class="form-label">Playlist Title *</label>
          <input class="form-input" type="text" name="title" required maxlength="150" placeholder="My awesome playlist">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-input" name="description" rows="2" placeholder="Optional description…" style="resize:vertical"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Visibility</label>
          <select class="form-input form-select" name="visibility">
            <option value="public">Public</option>
            <option value="unlisted">Unlisted</option>
            <option value="private">Private</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm">Create</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('#create-playlist-form').style.display='none'">Cancel</button>
        </div>
      </form>
    </div>

    <?php if (!$playlists): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text2)">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <h2 style="font-size:1.1rem;margin-bottom:6px;color:var(--text)">No playlists yet</h2>
        <p>Create your first playlist to organize your favorite videos.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-4">
        <?php foreach ($playlists as $p):
          $thumb = $p['thumbnail'] ? thumb_url($p['thumbnail']) : BASE_URL . '/assets/img/default-thumb.jpg';
        ?>
        <article class="video-card fade-in" style="position:relative">
          <div class="video-thumb" style="position:relative">
            <img src="<?= $thumb ?>" alt="<?= e($p['title']) ?>" loading="lazy" width="320" height="180" class="thumb-main" style="opacity:.8">
            <span class="video-duration"><?= (int)$p['video_count'] ?> videos</span>
          </div>
          <div class="video-info" style="padding:10px 0">
            <div class="video-title"><?= e($p['title']) ?></div>
            <div class="video-meta">
              <span><?= ucfirst($p['visibility']) ?></span>
              <span>·</span>
              <span>Created <?= time_ago($p['created_at']) ?></span>
            </div>
            <?php if ($p['description']): ?>
              <div style="font-size:.78rem;color:var(--text2);margin-top:4px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= e($p['description']) ?></div>
            <?php endif; ?>
          </div>
          <form method="POST" style="position:absolute;top:8px;right:8px;z-index:5">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="playlist_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm" title="Delete playlist"
                    style="background:rgba(0,0,0,.7);color:#fff;border:none;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.9rem"
                    onclick="return confirm('Delete this playlist?')">&times;</button>
          </form>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
