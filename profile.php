<?php
// ============================================================
// FreeHub.Live — Edit Profile (all logged-in users)
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

function save_data_uri_image(string $data_uri, string $directory, string $fallback_extension = 'jpg'): ?string {
    if (!str_contains($data_uri, ',')) return null;
    [$header, $payload] = explode(',', $data_uri, 2);
    $mime = 'image/jpeg';
    if (preg_match('/^data:(image\/[^;]+);base64$/', $header, $matches)) $mime = $matches[1];
    $decoded = base64_decode($payload, true);
    if ($decoded === false) return null;
    $extension = match ($mime) {
        'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', default => $fallback_extension,
    };
    if (!is_dir($directory)) mkdir($directory, 0755, true);
    $filename = unique_filename($extension);
    if (file_put_contents($directory . $filename, $decoded) === false) return null;
    return $filename;
}

$uid   = auth_user()['id'];
$user  = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
$error = '';
$success = '';
$currencies    = fh_currencies();
$currency_label = $currencies[$user['preferred_currency'] ?? 'USD']['label'] ?? ($user['preferred_currency'] ?? 'USD');
$role_label    = ucfirst($user['role'] ?? 'user');
$cropped_avatar_data = trim($_POST['cropped_avatar_data'] ?? '');
$cropped_cover_data  = trim($_POST['cropped_cover_data'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $channel_name = trim($_POST['channel_name'] ?? '');
        $bio          = trim($_POST['bio'] ?? '');
        if (strlen($channel_name) < 1) {
            $error = 'Channel name is required.';
        } else {
            $updateData = ['channel_name' => $channel_name, 'bio' => $bio ?: null];

            // Avatar
            if (!$error && $cropped_avatar_data !== '') {
                $fn = save_data_uri_image($cropped_avatar_data, __DIR__ . '/uploads/avatars/');
                if (!$fn) { $error = 'Unable to process avatar image.'; }
                else {
                    $old = $user['avatar'] ?? null;
                    if ($old && !str_starts_with($old, 'http') && file_exists(__DIR__ . '/uploads/avatars/' . $old)) @unlink(__DIR__ . '/uploads/avatars/' . $old);
                    $updateData['avatar'] = $fn;
                }
            } elseif (!$error && !empty($_FILES['avatar']['name'])) {
                $afile = $_FILES['avatar'];
                $amime = mime_content_type($afile['tmp_name']);
                if (!allowed_image($amime)) { $error = 'Invalid avatar format.'; }
                elseif ($afile['size'] > 5 * 1024 * 1024) { $error = 'Avatar max 5MB.'; }
                else {
                    $ext = strtolower(pathinfo($afile['name'], PATHINFO_EXTENSION));
                    $afn = unique_filename($ext ?: 'jpg');
                    $ap  = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($ap)) mkdir($ap, 0755, true);
                    move_uploaded_file($afile['tmp_name'], $ap . $afn);
                    $old = $user['avatar'] ?? null;
                    if ($old && !str_starts_with($old, 'http') && file_exists($ap . $old)) @unlink($ap . $old);
                    $updateData['avatar'] = $afn;
                }
            }

            // Cover
            if (!$error && $cropped_cover_data !== '') {
                $fn = save_data_uri_image($cropped_cover_data, __DIR__ . '/uploads/covers/');
                if (!$fn) { $error = 'Unable to process cover image.'; }
                else {
                    $old = $user['cover_image'] ?? null;
                    if ($old && !str_starts_with($old, 'http') && file_exists(__DIR__ . '/uploads/covers/' . $old)) @unlink(__DIR__ . '/uploads/covers/' . $old);
                    $updateData['cover_image'] = $fn;
                }
            } elseif (!$error && !empty($_FILES['cover_image']['name'])) {
                $cfile = $_FILES['cover_image'];
                $cmime = mime_content_type($cfile['tmp_name']);
                if (!allowed_image($cmime)) { $error = 'Invalid cover format.'; }
                elseif ($cfile['size'] > 10 * 1024 * 1024) { $error = 'Cover max 10MB.'; }
                else {
                    $ext = strtolower(pathinfo($cfile['name'], PATHINFO_EXTENSION));
                    $cfn = unique_filename($ext ?: 'jpg');
                    $cp  = __DIR__ . '/uploads/covers/';
                    if (!is_dir($cp)) mkdir($cp, 0755, true);
                    move_uploaded_file($cfile['tmp_name'], $cp . $cfn);
                    $old = $user['cover_image'] ?? null;
                    if ($old && !str_starts_with($old, 'http') && file_exists($cp . $old)) @unlink($cp . $old);
                    $updateData['cover_image'] = $cfn;
                }
            }

            if (!$error) {
                db_update('users', $updateData, 'id=?', [$uid]);
                $user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
                $_SESSION['user']['avatar'] = $user['avatar'];
                $_SESSION['user']['channel_name'] = $user['channel_name'] ?? $user['username'];
                $success = 'Profile updated successfully!';
            }
        }
    }
}

$meta_title = 'Edit Profile';
require_once __DIR__ . '/includes/header.php';
// Re-fetch user from DB because header.php overwrites $user with stale/incomplete session data
$user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
?>

<style>
/* ── Profile Page ── */
.profile-page { max-width: 860px; margin: 0 auto; padding: 24px 16px 48px; }

/* Cover photo */
.prof-cover-wrap {
  position: relative;
  width: 100%;
  height: 240px;
  border-radius: 16px 16px 0 0;
  overflow: hidden;
  background: linear-gradient(135deg, var(--accent) 0%, #ec4899 60%, #f97316 100%);
  cursor: pointer;
}
.prof-cover-wrap img#cover-img {
  width: 100%; height: 100%; object-fit: cover;
}
.prof-cover-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0);
  display: flex; align-items: center; justify-content: center;
  transition: background .25s;
}
.prof-cover-wrap:hover .prof-cover-overlay { background: rgba(0,0,0,.42); }
.prof-cover-btn {
  opacity: 0; transform: translateY(8px);
  transition: opacity .2s, transform .2s;
  display: flex; align-items: center; gap: 8px;
  background: rgba(0,0,0,.6); backdrop-filter: blur(6px);
  color: #fff; font-size: .85rem; font-weight: 600;
  padding: 9px 18px; border-radius: 50px; border: 1.5px solid rgba(255,255,255,.25);
  cursor: pointer;
}
.prof-cover-wrap:hover .prof-cover-btn { opacity: 1; transform: translateY(0); }

/* Card shell */
.prof-card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 0 0 16px 16px;
  padding: 0 28px 28px;
}

/* Avatar */
.prof-avatar-row {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-top: -54px; margin-bottom: 18px;
}
.prof-avatar-wrap {
  position: relative; width: 108px; height: 108px;
  border-radius: 50%; border: 4px solid var(--bg2);
  overflow: hidden; cursor: pointer; flex-shrink: 0;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.prof-avatar-wrap img {
  width: 100%; height: 100%; object-fit: cover;
}
.prof-avatar-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  transition: background .2s;
  border-radius: 50%;
}
.prof-avatar-wrap:hover .prof-avatar-overlay { background: rgba(0,0,0,.5); }
.prof-avatar-icon {
  opacity: 0; transform: scale(.8);
  transition: opacity .2s, transform .2s;
  color: #fff; display: flex; flex-direction: column; align-items: center; gap: 4px;
}
.prof-avatar-wrap:hover .prof-avatar-icon { opacity: 1; transform: scale(1); }
.prof-avatar-icon span { font-size: .65rem; font-weight: 700; letter-spacing: .03em; }

/* Name/meta */
.prof-meta h1 { font-size: 1.3rem; font-weight: 800; margin: 0 0 4px; }
.prof-meta .prof-sub { font-size: .83rem; color: var(--text2); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Stats row */
.prof-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0;
  margin: 16px 0 22px;
  background: var(--bg3); border-radius: 12px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.prof-stat {
  text-align: center;
  padding: 14px 8px;
  border-right: 1px solid var(--border);
}
.prof-stat:last-child { border-right: none; }
.prof-stat-val { font-size: 1.05rem; font-weight: 800; color: var(--text); word-break: break-word; }
.prof-stat-lbl { font-size: .68rem; color: var(--text2); margin-top: 3px; text-transform: uppercase; letter-spacing: .05em; }

/* Form grid */
.prof-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

@media(max-width:640px) {
  .prof-form-grid { grid-template-columns: 1fr; }
  .prof-cover-wrap { height: 160px; }
  .prof-avatar-row { margin-top: -44px; }
  .prof-avatar-wrap { width: 88px; height: 88px; }
  .prof-card { padding: 0 14px 20px; }
  /* 2x2 stats grid on mobile */
  .prof-stats { grid-template-columns: repeat(2, 1fr); }
  .prof-stat:nth-child(2) { border-right: none; }
  .prof-stat:nth-child(1),
  .prof-stat:nth-child(2) { border-bottom: 1px solid var(--border); }
  .prof-stat-val { font-size: .95rem; }
  .prof-meta h1 { font-size: 1.1rem; }
  .prof-sub { font-size: .78rem; gap: 6px !important; }
  .prof-save-bar { flex-direction: row; }
}

/* Alert inline */
.prof-alert {
  border-radius: 10px; padding: 12px 16px;
  font-size: .88rem; font-weight: 500;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 16px;
}
.prof-alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #22c55e; }
.prof-alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #ef4444; }

/* Section label */
.prof-section-label {
  font-size: .7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--text2); margin-bottom: 10px;
  display: flex; align-items: center; gap: 8px;
}
.prof-section-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* Save bar */
.prof-save-bar {
  display: flex; align-items: center; justify-content: flex-end; gap: 12px;
  margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border);
}

/* Crop Modal */
.crop-backdrop {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
  z-index: 1000; align-items: center; justify-content: center;
}
.crop-backdrop.open { display: flex; }
.crop-box {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 16px; padding: 24px; max-width: 540px; width: 94%;
  box-shadow: 0 24px 80px rgba(0,0,0,.5);
}
.crop-box h3 { font-size: 1rem; font-weight: 800; margin: 0 0 16px; }
.crop-box .crop-img-wrap { max-height: 340px; overflow: hidden; border-radius: 10px; }
.crop-box .crop-img-wrap img { max-width: 100%; display: block; }
.crop-actions { display: flex; gap: 10px; margin-top: 16px; justify-content: flex-end; }
</style>

<div class="profile-page">

  <?php if ($error): ?>
  <div class="prof-alert prof-alert-error">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($error) ?>
  </div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="prof-alert prof-alert-success">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?= e($success) ?>
  </div>
  <?php endif; ?>

  <form id="profile-form" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" id="cropped-avatar-data" name="cropped_avatar_data" value="">
    <input type="hidden" id="cropped-cover-data"  name="cropped_cover_data"  value="">
    <input type="file" id="cover-file-input"  name="cover_image" accept="image/*" style="display:none">
    <input type="file" id="avatar-file-input" name="avatar"      accept="image/*" style="display:none">

    <!-- ── Cover Photo ── -->
    <div class="prof-cover-wrap" id="cover-wrap" onclick="document.getElementById('cover-file-input').click()">
      <img id="cover-img"
           src="<?= !empty($user['cover_image']) ? cover_url($user['cover_image']) : '' ?>"
           alt="Cover"
           style="width:100%;height:100%;object-fit:cover;display:<?= !empty($user['cover_image']) ? 'block' : 'none' ?>">
      <div class="prof-cover-overlay">
        <div class="prof-cover-btn">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          <?= !empty($user['cover_image']) ? 'Change Cover Photo' : 'Add Cover Photo' ?>
        </div>
      </div>
    </div>

    <!-- ── Profile Card ── -->
    <div class="prof-card">

      <!-- Avatar row -->
      <div class="prof-avatar-row">
        <div class="prof-avatar-wrap" id="avatar-wrap" onclick="document.getElementById('avatar-file-input').click()">
          <img id="avatar-img" src="<?= avatar_url($user['avatar']) ?>" alt="Avatar">
          <div class="prof-avatar-overlay">
            <div class="prof-avatar-icon">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              <span>Change</span>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;padding-bottom:6px">
          <a href="<?= BASE_URL ?>/channel.php?id=<?= $uid ?>" target="_blank" class="btn btn-outline btn-sm">View Channel</a>
        </div>
      </div>

      <!-- Name + meta -->
      <div class="prof-meta">
        <h1><?= e($user['channel_name'] ?? $user['username']) ?></h1>
        <div class="prof-sub">
          <span class="badge badge-blue"><?= e($role_label) ?></span>
          <span>@<?= e($user['username']) ?></span>
          <span><?= e($user['email']) ?></span>
        </div>
      </div>

      <!-- Stats -->
      <div class="prof-stats">
        <div class="prof-stat">
          <div class="prof-stat-val"><?= format_number((int)db_fetch("SELECT COUNT(id) as c FROM videos WHERE user_id=?",[$uid])['c']) ?></div>
          <div class="prof-stat-lbl">Videos</div>
        </div>
        <div class="prof-stat">
          <div class="prof-stat-val"><?= format_number((int)($user['subscribers'] ?? 0)) ?></div>
          <div class="prof-stat-lbl">Subscribers</div>
        </div>
        <div class="prof-stat">
          <div class="prof-stat-val"><?= format_number((int)db_fetch("SELECT SUM(views) as s FROM videos WHERE user_id=?",[$uid])['s']) ?></div>
          <div class="prof-stat-lbl">Total Views</div>
        </div>
        <div class="prof-stat">
          <div class="prof-stat-val"><?= e($currency_label) ?></div>
          <div class="prof-stat-lbl">Currency</div>
        </div>
      </div>

      <!-- Form fields -->
      <div class="prof-section-label">Channel Details</div>
      <div class="prof-form-grid">
        <div class="form-group">
          <label class="form-label">Channel / Display Name *</label>
          <input class="form-input" type="text" name="channel_name" required maxlength="100"
                 placeholder="Your channel name" value="<?= e($user['channel_name'] ?? $user['username']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Username <span class="text-muted text-xs">(cannot change)</span></label>
          <input class="form-input" type="text" value="<?= e($user['username']) ?>" disabled style="opacity:.55;cursor:not-allowed">
        </div>
      </div>

      <div class="form-group" style="margin-top:4px">
        <label class="form-label">Bio <span class="text-muted text-xs">— up to 500 chars</span></label>
        <textarea class="form-input" name="bio" rows="4" maxlength="500"
                  placeholder="Tell viewers what your channel is about…" style="resize:vertical"><?= e($user['bio'] ?? '') ?></textarea>
      </div>

      <!-- Save bar -->
      <div class="prof-save-bar">
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" style="padding:10px 28px;gap:8px">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save Changes
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ── Avatar Crop Modal ── -->
<div class="crop-backdrop" id="avatar-modal">
  <div class="crop-box">
    <h3>✂️ Crop Profile Photo</h3>
    <div class="crop-img-wrap"><img id="avatar-crop-img" src=""></div>
    <div class="crop-actions">
      <button class="btn btn-outline" onclick="closeCropModal('avatar')">Cancel</button>
      <button class="btn btn-primary" id="avatar-crop-btn">Apply Photo</button>
    </div>
  </div>
</div>

<!-- ── Cover Crop Modal ── -->
<div class="crop-backdrop" id="cover-modal">
  <div class="crop-box">
    <h3>✂️ Crop Cover Photo</h3>
    <div class="crop-img-wrap"><img id="cover-crop-img" src=""></div>
    <div class="crop-actions">
      <button class="btn btn-outline" onclick="closeCropModal('cover')">Cancel</button>
      <button class="btn btn-primary" id="cover-crop-btn">Apply Cover</button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script data-page-script="true">
(function(){
  let avatarCropper = null, coverCropper = null;

  /* ── Avatar ── */
  document.getElementById('avatar-file-input').addEventListener('change', function(){
    if (!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('avatar-crop-img');
      img.src = e.target.result;
      openCropModal('avatar');
      if (avatarCropper) { avatarCropper.destroy(); avatarCropper = null; }
      img.onload = () => {
        avatarCropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, dragMode: 'move', guides: true, autoCropArea: 1, movable: true });
      };
    };
    reader.readAsDataURL(this.files[0]);
    this.value = '';
  });

  document.getElementById('avatar-crop-btn').addEventListener('click', function(){
    if (!avatarCropper) return;
    const canvas = avatarCropper.getCroppedCanvas({ width: 400, height: 400 });
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    document.getElementById('cropped-avatar-data').value = dataUrl;
    document.getElementById('avatar-img').src = dataUrl;
    closeCropModal('avatar');
    avatarCropper.destroy(); avatarCropper = null;
  });

  /* ── Cover ── */
  document.getElementById('cover-file-input').addEventListener('change', function(){
    if (!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('cover-crop-img');
      img.src = e.target.result;
      openCropModal('cover');
      if (coverCropper) { coverCropper.destroy(); coverCropper = null; }
      img.onload = () => {
        coverCropper = new Cropper(img, { aspectRatio: 16/5, viewMode: 1, dragMode: 'move', guides: true, autoCropArea: 1, movable: true });
      };
    };
    reader.readAsDataURL(this.files[0]);
    this.value = '';
  });

  document.getElementById('cover-crop-btn').addEventListener('click', function(){
    if (!coverCropper) return;
    const canvas = coverCropper.getCroppedCanvas({ width: 1200, height: 375 });
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    document.getElementById('cropped-cover-data').value = dataUrl;
    const coverImg = document.getElementById('cover-img');
    coverImg.src = dataUrl;
    coverImg.style.display = 'block';
    closeCropModal('cover');
    coverCropper.destroy(); coverCropper = null;
  });

  /* ── Modal helpers ── */
  function openCropModal(type) {
    document.getElementById(type + '-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  window.closeCropModal = function(type) {
    document.getElementById(type + '-modal').classList.remove('open');
    document.body.style.overflow = '';
  };

  /* Close on backdrop click */
  document.querySelectorAll('.crop-backdrop').forEach(el => {
    el.addEventListener('click', function(e){
      if (e.target === this) closeCropModal(this.id.replace('-modal',''));
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
