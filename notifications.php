<?php
// ============================================================
// FreeHub.Live — Notifications
// ============================================================
$meta_title = 'Notifications';
require_once __DIR__ . '/includes/header.php';
require_login();

$uid = auth_user()['id'];

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    if (!empty($_POST['mark_read'])) {
        $nid = (int)$_POST['mark_read'];
        db_query("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [$nid, $uid]);
    }
    if (!empty($_POST['mark_all_read'])) {
        db_query("UPDATE notifications SET is_read=1 WHERE user_id=?", [$uid]);
    }
    if (!empty($_POST['delete_id'])) {
        $nid = (int)$_POST['delete_id'];
        db_query("DELETE FROM notifications WHERE id=? AND user_id=?", [$nid, $uid]);
    }
    if (!empty($_POST['clear_all'])) {
        db_query("DELETE FROM notifications WHERE user_id=?", [$uid]);
    }
    // Redirect to avoid form resubmission
    $redirect_url = $_POST['goto'] ?? '';
    if ($redirect_url && filter_var($redirect_url, FILTER_VALIDATE_URL)) {
        redirect($redirect_url);
    }
    redirect(BASE_URL . '/notifications.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;

$total = db_fetch("SELECT COUNT(*) as c FROM notifications WHERE user_id=?", [$uid])['c'] ?? 0;
$unread = db_fetch("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0", [$uid])['c'] ?? 0;
$pg = paginate($total, $per_page, $page);

$notifications = db_fetchAll(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$uid, $per_page, $pg['offset']]
);

// Icon map for notification types
$icons = [
    'video_published' => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
    'video_approved'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    'video_rejected'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    'new_subscriber'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
    'comment'         => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'like'            => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
    'earning'         => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'system'          => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
];
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content">
    <div class="section-header" style="margin-bottom:20px">
      <h1 style="font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:8px">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
        <?php if ($unread > 0): ?>
          <span class="badge badge-blue"><?= $unread ?> new</span>
        <?php endif; ?>
      </h1>
      <?php if ($unread > 0): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="mark_all_read" value="1" class="btn btn-outline btn-sm">Mark All Read</button>
        </form>
      <?php endif; ?>
      <?php if ($total > 0): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Clear all notifications?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="clear_all" value="1" class="btn btn-outline btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.2)">Clear All</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text2)">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h2 style="font-size:1.1rem;margin-bottom:6px;color:var(--text)">No notifications</h2>
        <p>You're all caught up!</p>
      </div>
    <?php else: ?>
      <div class="card" style="overflow:hidden">
        <?php foreach ($notifications as $n):
          $icon = $icons[$n['type']] ?? $icons['system'];
          $is_unread = !$n['is_read'];
        ?>
        <div class="flex gap-3" style="padding:14px 16px;border-bottom:1px solid var(--border);align-items:flex-start;background:<?= $is_unread ? 'rgba(99,102,241,.04)' : 'transparent' ?>;position:relative">
          <!-- Icon -->
          <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $is_unread ? 'var(--accent)' : 'var(--bg3)' ?>;color:<?= $is_unread ? '#fff' : 'var(--text2)' ?>">
            <?= $icon ?>
          </div>
          <!-- Content -->
          <div style="flex:1;min-width:0">
            <div style="font-weight:<?= $is_unread ? '700' : '500' ?>;font-size:.88rem;color:var(--text)"><?= e($n['title']) ?></div>
            <?php if ($n['message']): ?>
              <div style="font-size:.8rem;color:var(--text2);margin-top:2px;line-height:1.4"><?= e($n['message']) ?></div>
            <?php endif; ?>
            <div style="font-size:.72rem;color:var(--text3);margin-top:4px"><?= time_ago($n['created_at']) ?></div>
          </div>
          <!-- Actions -->
          <div class="flex gap-1" style="flex-shrink:0;align-items:center">
            <?php if ($n['url']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="mark_read" value="<?= $n['id'] ?>">
                <input type="hidden" name="goto" value="<?= e($n['url']) ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:.72rem;padding:2px 8px">View</button>
              </form>
            <?php elseif ($is_unread): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="mark_read" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="font-size:.72rem;padding:2px 8px">Read</button>
              </form>
            <?php endif; ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="delete_id" value="<?= $n['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:none;border:none;color:var(--text3);cursor:pointer;padding:2px;font-size:1rem;line-height:1" title="Dismiss">&times;</button>
            </form>
          </div>
          <!-- Unread dot -->
          <?php if ($is_unread): ?>
            <div style="position:absolute;top:16px;left:6px;width:6px;height:6px;border-radius:50%;background:var(--accent)"></div>
          <?php endif; ?>
        </div>
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
