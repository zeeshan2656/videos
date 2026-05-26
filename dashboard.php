<?php
// ============================================================
// FreeHub.Live — Earnings & Watch Time Dashboard (all users)
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$preview_uid = (int)($_GET['uid'] ?? 0);
$auth_uid = (int)auth_user()['id'];
$display_uid = $auth_uid;
$display_role = auth_user()['role'] ?? 'viewer';

if ($preview_uid > 0 && is_admin()) {
    $preview_user = db_fetch("SELECT id, role, preferred_currency FROM users WHERE id=?", [$preview_uid]);
    if ($preview_user) {
        $display_uid = (int)$preview_user['id'];
        $display_role = $preview_user['role'] ?? 'viewer';
    }
}

$sidebar_role = $display_role;
$uid  = $display_uid;
$user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
$stats = fh_user_watch_stats($uid);
$currency = $user['preferred_currency'] ?? fh_user_currency();
$minUsd = fh_min_withdrawal_usd($uid);
$canWithdraw = (float)$user['balance'] >= $minUsd && !fh_pending_withdrawal($uid);

$error = '';
$success = '';

$earnings = db_fetchAll(
    "SELECT * FROM earnings WHERE user_id=? ORDER BY created_at DESC LIMIT 40",
    [$uid]
);

// --- Date Filters & Analytics Queries ---
$period  = $_GET['period'] ?? '30';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';

if ($from && $to) {
    $dateWhere  = "DATE(created_at) BETWEEN ? AND ?";
    $dateParams = [$from, $to];
    $label      = "($from to $to)";
    $days       = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
} else {
    $days       = in_array((int)$period, [7, 14, 30, 60, 90]) ? (int)$period : 30;
    $dateWhere  = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $dateParams = [$days];
    $label      = "Last $days days";
}

$qParams = array_merge([$uid], $dateParams);

$total_videos_watched = db_count('video_views', "user_id=? AND $dateWhere", $qParams);
$total_watch_time = db_fetch("SELECT SUM(watch_seconds) as t FROM video_views WHERE user_id=? AND $dateWhere", $qParams)['t'] ?? 0;
$total_viewing_earnings = db_fetch("SELECT SUM(amount) as t FROM earnings WHERE user_id=? AND type='watch_time' AND $dateWhere", $qParams)['t'] ?? 0;

$total_clicks = fh_table_exists('affiliate_clicks') ? db_count('affiliate_clicks', "affiliate_id=? AND $dateWhere", $qParams) : 0;
$total_referrals = fh_table_exists('referral_conversions') ? db_count('referral_conversions', "referrer_id=? AND $dateWhere", $qParams) : 0;

$ref_views_data = db_fetch("SELECT COUNT(id) as views, SUM(watch_seconds) as wt FROM video_views WHERE affiliate_id=? AND $dateWhere", $qParams);
$total_ref_views = $ref_views_data['views'] ?? 0;
$total_ref_watch = $ref_views_data['wt'] ?? 0;

$referral_earnings = db_fetch("SELECT SUM(amount) as t FROM earnings WHERE user_id=? AND type='referral' AND status='approved' AND $dateWhere", $qParams)['t'] ?? 0;

$chart = [];
for ($i = min($days, 30) - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $vw = db_fetch("SELECT SUM(watch_seconds) as wt FROM video_views WHERE user_id=? AND DATE(created_at)=?", [$uid, $d])['wt'] ?? 0;
    $chart[] = ['date' => date('M j', strtotime("-$i days")), 'wt' => $vw];
}
$maxWt = max(1, max(array_column($chart, 'wt')));

$meta_title = 'My Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
      <div class="flex" style="justify-content:flex-end;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        
        <!-- Date Filters -->
        <form method="GET" action="" style="display:flex;gap:8px;align-items:center" class="text-sm">
          <select name="period" class="form-input form-select" style="padding:4px 8px;height:32px;font-size:.8rem" onchange="this.form.submit()">
            <option value="7" <?= $period==='7'?'selected':'' ?>>Last 7 Days</option>
            <option value="14" <?= $period==='14'?'selected':'' ?>>Last 14 Days</option>
            <option value="30" <?= $period==='30'?'selected':'' ?>>Last 30 Days</option>
            <option value="60" <?= $period==='60'?'selected':'' ?>>Last 60 Days</option>
            <option value="90" <?= $period==='90'?'selected':'' ?>>Last 90 Days</option>
            <option value="custom" <?= $period==='custom'?'selected':'' ?>>Custom Range...</option>
          </select>
          <?php if ($period === 'custom'): ?>
          <input type="date" name="from" value="<?= e($from) ?>" class="form-input" style="padding:4px 8px;height:32px;font-size:.8rem" required>
          <span>to</span>
          <input type="date" name="to" value="<?= e($to) ?>" class="form-input" style="padding:4px 8px;height:32px;font-size:.8rem" required>
          <button type="submit" class="btn btn-primary btn-sm" style="height:32px">Apply</button>
          <?php endif; ?>
        </form>
      </div>

      <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <?php $watch_stats_user_id = $uid; require __DIR__ . '/includes/partials/watch_earnings_stats.php'; ?>

      <!-- ── Viewer Analytics ── -->
      <h3 style="font-weight:700;margin-bottom:12px;margin-top:24px">Analytics <?= e($label) ?></h3>
      
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_videos_watched) ?></div>
          <div class="stat-label">Videos Watched</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= format_duration((int)$total_watch_time) ?></div>
          <div class="stat-label">Watch Time</div>
        </div>
        <div class="stat-card" style="border-color:rgba(99,102,241,.2)">
          <div class="stat-value" style="color:var(--accent)">$<?= number_format((float)$total_viewing_earnings, 4) ?></div>
          <div class="stat-label">Viewing Earnings</div>
        </div>
      </div>

      <h3 style="font-weight:700;margin-bottom:12px">Referral Performance <?= e($label) ?></h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px">
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_clicks) ?></div>
          <div class="stat-label">Shared Links Clicks</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_referrals) ?></div>
          <div class="stat-label">Referral Signups</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_ref_views) ?></div>
          <div class="stat-label">Referral Views</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= format_duration((int)$total_ref_watch) ?></div>
          <div class="stat-label">Referral Watch Time</div>
        </div>
        <div class="stat-card" style="border-color:rgba(34,197,94,.3)">
          <div class="stat-value" style="color:var(--green)">$<?= number_format((float)$referral_earnings, 4) ?></div>
          <div class="stat-label">Referral Earnings</div>
        </div>
      </div>

      <!-- Watch Time Chart -->
      <div class="card" style="margin-bottom:24px">
        <h3 style="font-weight:700;margin-bottom:16px">Daily Watch Time Trend <?= e($label) ?></h3>
        <div style="display:flex;align-items:flex-end;gap:8px;height:140px;overflow-x:auto;padding-bottom:8px">
          <?php foreach ($chart as $d):
            $h = round(($d['wt']/$maxWt)*120);
          ?>
          <div style="flex:1;min-width:30px;display:flex;flex-direction:column;align-items:center;gap:4px;height:140px;justify-content:flex-end">
            <span style="font-size:.65rem;color:var(--text2);white-space:nowrap"><?= format_duration((int)$d['wt']) ?></span>
            <div style="width:100%;max-width:24px;height:<?= $h ?>px;background:linear-gradient(var(--accent),var(--accent2));border-radius:4px 4px 0 0" title="<?= format_duration((int)$d['wt']) ?> watch time"></div>
            <span style="font-size:.65rem;color:var(--text2)"><?= $d['date'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>


      <?php if (!is_admin() && (is_partner() || $user['role'] === 'partner')): ?>
      <div class="card" style="margin-bottom:20px">
        <h3 style="font-weight:700;margin-bottom:8px">Creator Channel</h3>
        <p class="text-sm text-muted" style="margin-bottom:12px">
          Your channel: <a href="<?= BASE_URL ?>/channel.php?id=<?= $uid ?>" style="color:var(--accent)"><?= e($user['channel_name'] ?? $user['username']) ?></a>
          — earnings from viewers watching your videos count toward your balance.
        </p>
        <a href="<?= BASE_URL ?>/partner/" class="btn btn-outline btn-sm">Open Creator Studio</a>
      </div>
      <?php endif; ?>

      <?php if (is_admin()): ?>
      <div class="card" style="margin-bottom:20px;border-color:rgba(99,102,241,.3)">
        <h3 style="font-weight:700;margin-bottom:8px">Admin Channel &amp; Ads</h3>
        <p class="text-sm text-muted" style="margin-bottom:12px">
          Manage site ads and your official channel from the admin panel. Ad click revenue is credited to the admin account.
        </p>
        <div class="flex gap-2" style="flex-wrap:wrap">
          <a href="<?= BASE_URL ?>/admin/ads.php" class="btn btn-primary btn-sm">Manage Ads</a>
          <a href="<?= BASE_URL ?>/channel.php?id=<?= $uid ?>" class="btn btn-outline btn-sm">View Admin Channel</a>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!is_admin()): ?>
      <!-- Referral Link Card -->
      <div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(139,92,246,.04));border-color:rgba(99,102,241,.3)">
        <div class="flex gap-3" style="align-items:center;flex-wrap:wrap">
          <div style="flex:1">
            <h3 style="font-weight:700;font-size:.95rem;margin-bottom:4px">🔗 Your Referral Link</h3>
            <code style="font-size:.82rem;color:var(--accent);word-break:break-all"><?= e(BASE_URL . '/?ref=' . ($user['ref_code'] ?? '')) ?></code>
          </div>
          <a href="<?= BASE_URL ?>/referral.php" class="btn btn-outline btn-sm">View Full Dashboard →</a>
        </div>
      </div>



      <div class="card">
        <h3 style="font-weight:700;margin-bottom:16px">Earnings History</h3>
        <?php if ($earnings): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Type</th><th>Amount (USD)</th><th>Status</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($earnings as $e): ?>
            <tr>
              <td><span class="badge badge-blue"><?= e($e['type']) ?></span></td>
              <td style="font-weight:600;color:var(--green)"><?= fh_format_money((float)$e['amount'], $currency) ?></td>
              <td><span class="badge badge-<?= $e['status']==='paid'||$e['status']==='approved'?'green':($e['status']==='pending'?'yellow':'gray') ?>"><?= e($e['status']) ?></span></td>
              <td class="text-sm text-muted"><?= e($e['description'] ?? '') ?></td>
              <td class="text-xs text-muted"><?= date('M j, Y', strtotime($e['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-muted text-sm">No earnings yet. Watch videos to start earning based on your watch time.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
