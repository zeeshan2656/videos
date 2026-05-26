<?php
// ============================================================
// FreeHub.Live — Referral / Affiliate Dashboard (all users)
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

// Admin doesn't participate in referrals
if (is_admin()) redirect(BASE_URL . '/admin/');

$uid  = (int)auth_user()['id'];
$user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
$ref_code = $user['ref_code'] ?? '';

// Generate if missing
if (empty($ref_code)) {
    $ref_code = generate_ref_code();
    while (db_fetch("SELECT id FROM users WHERE ref_code=? AND id!=?", [$ref_code, $uid])) {
        $ref_code = generate_ref_code();
    }
    db_update('users', ['ref_code' => $ref_code], 'id=?', [$uid]);
}

$ref_link  = BASE_URL . '/?ref=' . urlencode($ref_code);
$ref_bonus = (float)setting('referral_bonus_usd', '0.00');

// Stats
$total_clicks = fh_table_exists('affiliate_clicks')
    ? db_count('affiliate_clicks', 'affiliate_id=?', [$uid])
    : 0;
$total_referrals = fh_table_exists('referral_conversions')
    ? db_count('referral_conversions', 'referrer_id=?', [$uid])
    : 0;
$referral_earnings = fh_table_exists('earnings')
    ? (float)(db_fetch("SELECT COALESCE(SUM(amount),0) AS t FROM earnings WHERE user_id=? AND type='referral' AND status='approved'", [$uid])['t'] ?? 0)
    : 0.0;

// Recent referred users
$referred_users = [];
if (fh_table_exists('referral_conversions') && fh_table_exists('users')) {
    $referred_users = db_fetchAll(
        "SELECT u.username, u.channel_name, u.role, u.created_at, rc.created_at AS joined_via_ref
         FROM referral_conversions rc
         JOIN users u ON u.id = rc.referred_user_id
         WHERE rc.referrer_id = ?
         ORDER BY rc.created_at DESC
         LIMIT 20",
        [$uid]
    );
}

// Recent clicks
$recent_clicks = [];
if (fh_table_exists('affiliate_clicks')) {
    $recent_clicks = db_fetchAll(
        "SELECT device, created_at FROM affiliate_clicks
         WHERE affiliate_id=?
         ORDER BY created_at DESC LIMIT 10",
        [$uid]
    );
}

$meta_title = 'Refer & Earn — ' . setting('site_name', 'FreeHub');
require_once __DIR__ . '/includes/header.php';
?>

<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="container">
      <h1 style="font-size:1.35rem;font-weight:800;margin-bottom:8px">Refer &amp; Earn</h1>
      <p class="text-muted text-sm" style="margin-bottom:24px">
        Share your unique referral link. Earn commissions when friends join and use the platform.
      </p>

      <!-- Referral Link Card -->
      <div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(139,92,246,.05));border-color:rgba(99,102,241,.3)">
        <h3 style="font-weight:700;margin-bottom:8px;color:var(--accent)">Your Unique Referral Link</h3>
        <p class="text-sm text-muted" style="margin-bottom:16px">Share this link anywhere — social media, blogs, messages, or emails.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input type="text" id="ref-link-input" value="<?= e($ref_link) ?>"
                 class="form-input" style="flex:1;min-width:200px;font-size:.85rem;font-family:monospace"
                 readonly onclick="this.select()">
          <button onclick="copyRefLink()" class="btn btn-primary" id="copy-btn" style="white-space:nowrap">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Copy Link
          </button>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <span style="font-size:.8rem;color:var(--text2)">Your referral code: <code style="color:var(--accent);font-weight:700;background:rgba(99,102,241,.1);padding:2px 8px;border-radius:4px"><?= e($ref_code) ?></code></span>
          <?php if ($ref_bonus > 0): ?>
          <span class="badge badge-green">+$<?= number_format($ref_bonus, 2) ?> per signup</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="stat-grid-3" style="margin-bottom:24px">
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_clicks) ?></div>
          <div class="stat-label">Link Clicks</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= format_number($total_referrals) ?></div>
          <div class="stat-label">Signups via Referral</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">$<?= number_format($referral_earnings, 2) ?></div>
          <div class="stat-label">Referral Earnings</div>
        </div>
      </div>

      <!-- Share Buttons -->
      <div class="card" style="margin-bottom:24px">
        <h3 style="font-weight:700;margin-bottom:12px">Share Your Link</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a href="https://wa.me/?text=<?= urlencode('Join me on ' . setting('site_name','FreeHub') . ' — Watch videos and earn money! ' . $ref_link) ?>"
             target="_blank" class="btn btn-outline btn-sm" style="gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0 0 20.465 3.488"/></svg>
            WhatsApp
          </a>
          <a href="https://t.me/share/url?url=<?= urlencode($ref_link) ?>&text=<?= urlencode('Join ' . setting('site_name','FreeHub') . ' and earn money watching videos!') ?>"
             target="_blank" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="#229ED9"><path d="m22.05 1.577c-.393-.016-.784.08-1.117.235L2.19 9.607c-1.91.772-1.786 2.523-.23 2.95l4.865 1.278 11.26-7.11c.54-.323 1.04-.13.633.238L9.5 14.74l-.42 4.853c.535 0 .775-.259 1.07-.543l2.67-2.573 4.942 3.66c.93.517 1.6.24 1.837-.867l3.365-15.85c.28-1.34-.43-1.896-1.224-1.843z"/></svg>
            Telegram
          </a>
          <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Earn money watching videos on ' . setting('site_name','FreeHub') . '! Sign up with my link: ' . $ref_link) ?>"
             target="_blank" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            Twitter / X
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($ref_link) ?>"
             target="_blank" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            Facebook
          </a>
        </div>
      </div>

      <!-- Recent Referrals -->
      <?php if ($referred_users): ?>
      <div class="card" style="margin-bottom:24px">
        <h3 style="font-weight:700;margin-bottom:12px">People You've Referred (<?= count($referred_users) ?>)</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($referred_users as $ru): ?>
            <tr>
              <td style="font-weight:600;font-size:.85rem"><?= e($ru['channel_name'] ?: $ru['username']) ?></td>
              <td><span class="badge badge-<?= $ru['role']==='partner'?'green':'gray' ?>"><?= e($ru['role'] === 'partner' ? 'Creator' : 'Watch & Earn') ?></span></td>
              <td class="text-xs text-muted"><?= date('M j, Y', strtotime($ru['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Recent Link Clicks -->
      <?php if ($recent_clicks): ?>
      <div class="card">
        <h3 style="font-weight:700;margin-bottom:12px">Recent Link Clicks</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Device</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recent_clicks as $click): ?>
            <tr>
              <td class="text-sm"><?= e($click['device'] ?: 'Unknown') ?></td>
              <td class="text-xs text-muted"><?= date('M j, Y H:i', strtotime($click['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- How it works -->
      <div class="card" style="margin-top:24px;background:rgba(99,102,241,.05);border-color:rgba(99,102,241,.2)">
        <h3 style="font-weight:700;margin-bottom:16px">How Referrals Work</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
          <div style="text-align:center;padding:12px">
            <div style="font-size:2rem;margin-bottom:8px">🔗</div>
            <div style="font-weight:700;font-size:.9rem;margin-bottom:4px">Share Your Link</div>
            <div class="text-sm text-muted">Share your unique referral link on social media, blogs, or with friends</div>
          </div>
          <div style="text-align:center;padding:12px">
            <div style="font-size:2rem;margin-bottom:8px">👥</div>
            <div style="font-weight:700;font-size:.9rem;margin-bottom:4px">Friends Sign Up</div>
            <div class="text-sm text-muted">When someone signs up through your link, they're linked to your account</div>
          </div>
          <div style="text-align:center;padding:12px">
            <div style="font-size:2rem;margin-bottom:8px">💰</div>
            <div style="font-weight:700;font-size:.9rem;margin-bottom:4px">Earn Rewards</div>
            <div class="text-sm text-muted">
              <?php if ($ref_bonus > 0): ?>
                Earn $<?= number_format($ref_bonus, 2) ?> for every confirmed signup
              <?php else: ?>
                Track your referrals and grow your network
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
function copyRefLink() {
  const input = document.getElementById('ref-link-input');
  const btn   = document.getElementById('copy-btn');
  input.select();
  input.setSelectionRange(0, 99999);
  try {
    navigator.clipboard.writeText(input.value).catch(() => document.execCommand('copy'));
  } catch(e) {
    document.execCommand('copy');
  }
  btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
  btn.style.background = 'var(--green)';
  setTimeout(() => {
    btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Link';
    btn.style.background = '';
  }, 2500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
