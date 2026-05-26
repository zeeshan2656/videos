<?php
// ============================================================
// FreeHub.Live — Search Page
// ============================================================
$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'relevance';
$cat  = (int)($_GET['cat'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$meta_title = ($q ? '"' . $q . '" — Search' : 'Search') . ' — ' . setting('site_name','FreeHub');
$meta_desc  = $q ? "Search results for \"$q\" on " . setting('site_name','FreeHub') : 'Search videos';

$where  = "v.status='published' AND v.visibility='public'";
$where_params = [];
if ($q) {
    $where .= " AND MATCH(v.title,v.description,v.tags) AGAINST(? IN BOOLEAN MODE)";
    $where_params[] = $q . '*';
}
if ($cat) {
    $where .= " AND (v.category_id=? OR EXISTS (SELECT 1 FROM video_categories vc WHERE vc.video_id = v.id AND vc.category_id = ?))";
    $where_params[] = $cat;
    $where_params[] = $cat;
}

$order = match($sort) {
    'views'  => 'v.views DESC',
    'latest' => 'v.published_at DESC',
    'oldest' => 'v.published_at ASC',
    default  => $q ? 'MATCH(v.title,v.description,v.tags) AGAINST(?) DESC' : 'v.views DESC',
};
// ORDER BY params are separate — db_count doesn't use ORDER BY
$order_params = [];
if ($sort === 'relevance' && $q) $order_params[] = $q . '*';

$total = db_count('videos v', $where, $where_params);
$pg    = paginate($total, 16, $page);

$all_params = array_merge($where_params, $order_params);
$videos = db_fetchAll(
    "SELECT v.*,u.username,u.channel_name,u.avatar
     FROM videos v JOIN users u ON u.id=v.user_id
     WHERE $where ORDER BY $order LIMIT 16 OFFSET {$pg['offset']}",
    $all_params
);
$categories = db_fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order");
$ref = auth_user()['ref_code'] ?? '';
$creatorId   = (is_logged_in() && is_partner()) ? (int)auth_user()['id'] : 0;
$earningsMap = [];
if ($creatorId && $videos) {
    $ownIds = [];
    foreach ($videos as $v) {
        if ((int)($v['user_id'] ?? 0) === $creatorId) {
            $ownIds[] = (int)$v['id'];
        }
    }
    if ($ownIds) {
        $earningsMap = fh_creator_video_earnings_map($creatorId, $ownIds);
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <!-- Search Bar -->
    <form method="GET" action="<?= BASE_URL ?>/search.php" style="margin-bottom:20px">
      <div class="flex gap-3 search-form-row">
        <div class="search-bar search-bar-secondary" style="max-width:100%;flex:1">
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search videos, channels, tags…"
                 class="form-input" style="border-radius:24px;padding:11px 44px 11px 20px;font-size:1rem" autofocus>
          <button type="submit" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text2)">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </button>
        </div>
        <select name="sort" class="form-input form-select" style="width:auto" onchange="this.form.submit()">
          <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Most Relevant</option>
          <option value="views"     <?= $sort==='views'?'selected':'' ?>>Most Viewed</option>
          <option value="latest"    <?= $sort==='latest'?'selected':'' ?>>Latest</option>
          <option value="oldest"    <?= $sort==='oldest'?'selected':'' ?>>Oldest</option>
        </select>
      </div>
    </form>



    <!-- Results header -->
    <?php if ($q): ?>
    <div class="flex" style="justify-content:space-between;align-items:center;margin-bottom:16px">
      <p class="text-muted text-sm">
        <?= $total ?> result<?= $total!=1?'s':'' ?> for <strong style="color:var(--text)">"<?= e($q) ?>"</strong>
      </p>
    </div>
    <?php endif; ?>

    <!-- Grid -->
    <?php if ($videos): ?>
    <div class="grid grid-4">
      <?php foreach ($videos as $v) {
          echo render_video_card($v, fh_video_card_opts($v, $earningsMap, $ref));
      } ?>
    </div>

    <!-- Pagination -->
    <?php if ($pg['pages'] > 1): ?>
    <div class="flex gap-2" style="margin-top:28px;justify-content:center">
      <?php if ($pg['has_prev']): ?><a href="?q=<?= urlencode($q) ?>&sort=<?= $sort ?>&cat=<?= $cat ?>&page=<?= $page-1 ?>" class="btn btn-outline">&laquo; Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($pg['pages'],$page+2); $i++): ?>
      <a href="?q=<?= urlencode($q) ?>&sort=<?= $sort ?>&cat=<?= $cat ?>&page=<?= $i ?>"
         class="btn <?= $i===$page?'btn-primary':'btn-outline' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($pg['has_next']): ?><a href="?q=<?= urlencode($q) ?>&sort=<?= $sort ?>&cat=<?= $cat ?>&page=<?= $page+1 ?>" class="btn btn-outline">Next &raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="text-align:center;padding:80px 20px;color:var(--text2)">
      <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;opacity:.4"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <h2 style="font-size:1.2rem;font-weight:700;color:var(--text);margin-bottom:8px">No results found</h2>
      <p>Try different keywords or <a href="<?= BASE_URL ?>/" style="color:var(--accent)">browse all videos</a></p>
    </div>
    <?php endif; ?>
  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
