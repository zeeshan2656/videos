<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$vid = (int)($_GET['v'] ?? 0);
if (!$vid) { redirect(BASE_URL . '/'); }

$video = db_fetch(
    "SELECT v.*,u.username,u.channel_name,u.avatar,u.subscribers,u.bio
     FROM videos v JOIN users u ON u.id=v.user_id
     WHERE v.id=? AND v.status='published' AND v.visibility='public'", [$vid]
);
if (!$video) { http_response_code(404); die('Video not found'); }

// Track view
$ip   = hash_ip(get_ip());
$aff  = get_ref_code();
$affId = null;
if ($aff) {
    $affUser = db_fetch("SELECT id FROM users WHERE ref_code=?", [$aff]);
    $affId = $affUser['id'] ?? null;
}
$viewRow = db_fetch(
    "SELECT id FROM video_views WHERE video_id=? AND ip_hash=? ORDER BY id DESC LIMIT 1",
    [$vid, $ip]
);
if (!$viewRow) {
    $view_session_id = db_insert('video_views', [
        'video_id'     => $vid,
        'user_id'      => auth_user()['id'] ?? null,
        'affiliate_id' => $affId,
        'ip_hash'      => $ip,
        'ref_code'     => $aff,
        'device'       => detect_device(),
        'is_unique'    => 1,
    ]);
    db_update('videos', ['views' => $video['views']+1], 'id=?', [$vid]);
} else {
    $view_session_id = (int)$viewRow['id'];
}

// Related
$related = db_fetchAll(
    "SELECT v.*,u.username,u.channel_name,u.avatar FROM videos v
     JOIN users u ON u.id=v.user_id
     WHERE v.id!=? AND v.status='published' AND v.visibility='public'
     AND (
       v.category_id=?
       OR EXISTS (SELECT 1 FROM video_categories vc1 WHERE vc1.video_id = v.id AND vc1.category_id = ?)
       OR EXISTS (
         SELECT 1 FROM video_categories vc2 
         WHERE vc2.video_id = v.id 
         AND vc2.category_id IN (SELECT category_id FROM video_categories WHERE video_id = ?)
       )
       OR MATCH(v.title,v.description,v.tags) AGAINST(?)
     )
     ORDER BY v.views DESC LIMIT 12",
    [$vid, $video['category_id'], $video['category_id'], $vid, $video['title']]
);

// User reaction
$user_reaction = null;
if (is_logged_in()) {
    $r = db_fetch("SELECT type FROM video_reactions WHERE video_id=? AND user_id=?", [$vid, auth_user()['id']]);
    $user_reaction = $r['type'] ?? null;
}

$meta_title = $video['title'] . ' — ' . setting('site_name','FreeHub');
$meta_desc  = truncate(strip_tags($video['description'] ?? ''), 160);
$meta_image = thumb_url($video['thumbnail']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content watch-page">
    <div class="container">
      <div class="watch-layout">

    <!-- ── Player Column ── -->
    <div>
      <!-- Player -->
      <div class="player-wrapper" id="player-wrapper">
        <?php
        $yt_id = fh_youtube_id($video['video_url']);
        if ($yt_id):
        ?>
          <iframe id="fh-youtube-player" width="100%" height="100%"
                  src="https://www.youtube.com/embed/<?= e($yt_id) ?>?autoplay=1"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowfullscreen style="width:100%;height:100%;border:none;display:block"></iframe>
        <?php else: ?>
          <video id="fh-player" playsinline preload="metadata"
                 poster="<?= thumb_url($video['thumbnail']) ?>"
                 style="width:100%;height:100%;display:block">
            <?php if ($video['hls_url']): ?>
              <source src="<?= e($video['hls_url']) ?>" type="application/x-mpegURL">
            <?php endif; ?>
            <source src="<?= video_url($video['video_url']) ?>" type="video/mp4">
            Your browser does not support video playback.
          </video>
          
          <!-- Big Centered Controls Overlay -->
          <div class="player-overlay-center" id="overlay-center">
            <button class="overlay-center-btn" id="overlay-skip-back" aria-label="Skip back 10s">
              <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
            </button>
            <button class="overlay-center-btn play-pause" id="overlay-play-btn" aria-label="Play/Pause">
              <svg id="overlay-play-icon" width="32" height="32" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </button>
            <button class="overlay-center-btn" id="overlay-skip-fwd" aria-label="Skip forward 10s">
              <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-.49-4.5"/></svg>
            </button>
          </div>

          <!-- Custom Controls Bottom Bar -->
          <div class="player-controls" id="ctrl">
            <div class="progress-bar-container" id="progress-container">
              <div class="progress-bar" id="progress" role="slider" aria-label="Video progress">
                <div class="progress-fill" id="progress-fill" style="width:0%"></div>
                <div class="progress-thumb" id="progress-thumb" style="left:0%"></div>
              </div>
            </div>
            
            <div class="ctrl-row">
              <div class="ctrl-left">
                <button class="ctrl-btn" id="play-btn" aria-label="Play/Pause">
                  <svg id="play-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </button>
                <button class="ctrl-btn desktop-only" id="skip-back" aria-label="Skip back 10s">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
                </button>
                <button class="ctrl-btn desktop-only" id="skip-fwd" aria-label="Skip forward 10s">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-.49-4.5"/></svg>
                </button>
                
                <div class="volume-container desktop-only">
                  <button class="ctrl-btn" id="mute-btn" aria-label="Mute/Unmute">
                    <svg id="volume-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                  </button>
                  <input type="range" class="volume-slider" id="volume" min="0" max="1" step="0.05" value="1" aria-label="Volume">
                </div>
                
                <span class="time-display" id="time-display">0:00 / <?= format_duration((int)$video['duration']) ?></span>
              </div>
              
              <div class="ctrl-right">
                <select id="speed-select" class="speed-selector" aria-label="Playback speed">
                  <option value="0.5">0.5x</option>
                  <option value="1" selected>1x</option>
                  <option value="1.25">1.25x</option>
                  <option value="1.5">1.5x</option>
                  <option value="2">2x</option>
                </select>
                <button class="ctrl-btn desktop-only" id="mini-btn" aria-label="Mini player">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="2"/><rect x="12" y="12" width="8" height="8" rx="1"/></svg>
                </button>
                <button class="ctrl-btn" id="fullscreen-btn" aria-label="Fullscreen">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Video Info -->
      <div class="watch-info">
        <h1 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;line-height:1.3"><?= e($video['title']) ?></h1>
        <div class="watch-actions-row">
          <div class="flex gap-2 text-muted text-sm">
            <span><?= format_number((int)$video['views']) ?> views</span>
            <span>·</span>
            <span><?= time_ago($video['published_at'] ?? $video['created_at']) ?></span>
          </div>
          <div class="watch-action-btns">
            <!-- Like -->
            <button class="btn btn-outline btn-sm" id="like-btn" data-id="<?= $vid ?>"
                    style="<?= $user_reaction==='like'?'background:rgba(99,102,241,.15);color:var(--accent)':'' ?>">
              <svg width="16" height="16" fill="<?= $user_reaction==='like'?'var(--accent)':'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
              <span id="like-count"><?= format_number((int)$video['likes']) ?></span>
            </button>
            <!-- Dislike -->
            <button class="btn btn-outline btn-sm" id="dislike-btn" data-id="<?= $vid ?>">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3z"/><path d="M17 2h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>
            </button>
            <!-- Share -->
            <button class="btn btn-outline btn-sm" id="share-btn">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              Share
            </button>
            <!-- Watch Later -->
            <button class="btn btn-outline btn-sm" id="wl-btn" data-id="<?= $vid ?>">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
              Save
            </button>
            <?php if (is_logged_in() && (auth_user()['id'] == $video['user_id'] || auth_user()['role'] === 'admin')): ?>
            <a href="<?= BASE_URL ?>/partner/edit.php?id=<?= $vid ?>" class="btn btn-outline btn-sm" style="color:var(--accent);border-color:var(--accent)">
              &#9998; Edit Video
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Channel Info -->
      <div class="watch-channel-row">
        <a href="<?= BASE_URL ?>/channel.php?id=<?= $video['user_id'] ?>" class="flex gap-3">
          <img src="<?= avatar_url($video['avatar']) ?>" alt="<?= e($video['channel_name']??$video['username']) ?>"
               class="avatar avatar-lg" width="64" height="64" loading="lazy">
          <div>
            <div style="font-weight:700;font-size:1rem"><?= e($video['channel_name']??$video['username']) ?></div>
            <div class="text-muted text-sm"><?= format_number((int)$video['subscribers']) ?> subscribers</div>
          </div>
        </a>
        <?php if (is_logged_in() && auth_user()['id'] != $video['user_id']): ?>
        <button class="btn btn-primary" id="sub-btn" data-channel="<?= $video['user_id'] ?>">Subscribe</button>
        <?php endif; ?>
      </div>

      <!-- Description -->
      <?php if ($video['description']): ?>
      <div class="watch-desc" style="margin:16px 0;padding:16px;background:var(--bg2);border-radius:var(--radius);font-size:.9rem;line-height:1.7;color:var(--text2)" id="desc-box">
        <div id="desc-text" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">
          <?= nl2br(e($video['description'])) ?>
        </div>
        <button style="color:var(--accent);font-size:.82rem;font-weight:600;margin-top:8px" onclick="
          const d=document.getElementById('desc-text');
          d.style.webkitLineClamp=d.style.webkitLineClamp?'':'3';
          this.textContent=d.style.webkitLineClamp?'Show more':'Show less'
        ">Show more</button>
      </div>
      <?php endif; ?>

      <!-- Comments -->
      <div class="watch-comments" style="margin-top:24px">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:16px"><?= format_number((int)$video['comments_count']) ?> Comments</h2>
        <?php if (is_logged_in()): ?>
        <form id="comment-form" class="comment-form-row">
          <img src="<?= avatar_url(auth_user()['avatar']) ?>" class="avatar avatar-sm" width="32" height="32">
          <div style="flex:1">
            <input type="text" class="form-input" placeholder="Add a comment…" id="comment-input" style="border-radius:20px" maxlength="500">
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Post</button>
          <input type="hidden" name="video_id" value="<?= $vid ?>">
        </form>
        <?php endif; ?>
        <div id="comments-list"></div>
        <button class="btn btn-outline" id="load-comments" style="margin-top:12px">Load Comments</button>
      </div>
    </div>

    <!-- ── Related Column ── -->
    <aside class="player-sidebar" aria-label="Related videos">
      <h2 style="font-size:.95rem;font-weight:700;margin-bottom:12px;padding:0 16px">Up Next</h2>
      <?php foreach ($related as $r): ?>
      <a href="<?= BASE_URL ?>/watch.php?v=<?= $r['id'] ?>" class="related-video-item">
        <div class="related-thumb">
          <img src="<?= thumb_url($r['thumbnail']) ?>" alt="<?= e($r['title']) ?>"
               loading="lazy" width="168" height="94">
          <span style="position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.8);color:#fff;font-size:.68rem;font-weight:600;padding:1px 5px;border-radius:3px">
            <?= format_duration((int)$r['duration']) ?>
          </span>
        </div>
        <div style="min-width:0">
          <div style="font-size:.82rem;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:4px"><?= e($r['title']) ?></div>
          <div class="text-muted" style="font-size:.75rem"><?= e($r['channel_name']??$r['username']) ?></div>
          <div class="text-muted" style="font-size:.75rem"><?= format_number((int)$r['views']) ?> views</div>
        </div>
      </a>
      <?php endforeach; ?>
    </aside>
      </div>
    </div>
  </main>
</div>

<!-- Share Modal -->
<div class="modal-backdrop" id="share-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Share Video</span>
      <button class="btn-icon" onclick="document.getElementById('share-modal').classList.remove('open')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <?php if (auth_user()): ?>
      <p class="text-sm text-muted" style="margin-bottom:12px">Your affiliate link (earns you money when shared):</p>
      <div class="flex gap-2">
        <input class="form-input" id="share-url" value="<?= BASE_URL ?>/watch.php?v=<?= $vid ?>&ref=<?= auth_user()['ref_code'] ?? '' ?>" readonly>
        <button class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('share-url').value);this.textContent='Copied!'">Copy</button>
      </div>
      <?php else: ?>
      <div class="flex gap-2">
        <input class="form-input" id="share-url" value="<?= BASE_URL ?>/watch.php?v=<?= $vid ?>" readonly>
        <button class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('share-url').value);this.textContent='Copied!'">Copy</button>
      </div>
      <p class="text-sm text-muted" style="margin-top:12px"><a href="<?= BASE_URL ?>/auth/register.php" style="color:var(--accent)">Join affiliate program</a> to earn from shares.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const player = document.getElementById('fh-player');
const progressFill = document.getElementById('progress-fill');
const progressThumb = document.getElementById('progress-thumb');
const timeDisplay = document.getElementById('time-display');
const playIcon = document.getElementById('play-icon');
const vidDuration = <?= (int)$video['duration'] ?>;
const FH_VIDEO_ID = <?= (int)$vid ?>;

function fmtTime(s){
  s=Math.floor(s);
  const m=Math.floor(s/60),sec=s%60;
  return m+':'+(sec<10?'0':'')+sec;
}

if (player) {
  const wrapper = document.getElementById('player-wrapper');
  const overlayPlayIcon = document.getElementById('overlay-play-icon');
  const muteBtn = document.getElementById('mute-btn');
  const volumeIcon = document.getElementById('volume-icon');
  const volumeSlider = document.getElementById('volume');
  const progressContainer = document.getElementById('progress-container');
  const progressEl = document.getElementById('progress');
  
  let controlsTimeout = null;
  let isDraggingProgress = false;
  let lastVolume = 1;

  function showControls() {
    wrapper.classList.remove('controls-hidden');
    resetControlsTimeout();
  }

  function hideControls() {
    if (!player.paused && !isDraggingProgress) {
      wrapper.classList.add('controls-hidden');
    }
  }

  function resetControlsTimeout() {
    clearTimeout(controlsTimeout);
    if (!player.paused && !isDraggingProgress) {
      controlsTimeout = setTimeout(hideControls, 3000);
    }
  }

  // Mouse / Touch events for controls visibility
  wrapper.addEventListener('mousemove', showControls);
  wrapper.addEventListener('touchstart', showControls, {passive: true});
  
  wrapper.addEventListener('click', (e) => {
    // Prevent toggle play/pause if clicked controls or overlay buttons
    if (e.target.closest('#ctrl') || e.target.closest('.overlay-center-btn')) {
      resetControlsTimeout();
      return;
    }
    
    if (wrapper.classList.contains('controls-hidden')) {
      showControls();
    } else {
      player.paused ? player.play() : player.pause();
      resetControlsTimeout();
    }
  });

  // Auto-save duration from actual video length when missing in database
  player.addEventListener('loadedmetadata', () => {
    const d = Math.floor(player.duration || 0);
    if (d < 1) return;
    if (vidDuration > 0) return;
    fetch('<?= BASE_URL ?>/api/thumbnails.php?action=save_duration', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({video_id: FH_VIDEO_ID, duration: d})
    }).then(r => r.json()).then(data => {
      if (data.success && data.formatted) {
        timeDisplay.textContent = fmtTime(player.currentTime) + ' / ' + data.formatted;
        document.querySelectorAll('.video-duration--pending[data-video-id="' + FH_VIDEO_ID + '"]')
          .forEach(el => { el.textContent = data.formatted; el.classList.remove('video-duration--pending'); });
      }
    }).catch(() => {});
  });

  // Play/Pause Bottom Bar
  document.getElementById('play-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    player.paused ? player.play() : player.pause();
  });
  
  // Play/Pause Overlay Centered
  document.getElementById('overlay-play-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    player.paused ? player.play() : player.pause();
  });

  player.addEventListener('play', () => {
    const pauseSvg = '<rect x="5" y="4" width="4" height="16" rx="1"/><rect x="15" y="4" width="4" height="16" rx="1"/>';
    playIcon.innerHTML = pauseSvg;
    overlayPlayIcon.innerHTML = pauseSvg;
    resetControlsTimeout();
  });

  player.addEventListener('pause', () => {
    const playSvg = '<polygon points="5 3 19 12 5 21 5 3"/>';
    playIcon.innerHTML = playSvg;
    overlayPlayIcon.innerHTML = playSvg;
    showControls();
  });

  // Progress update
  player.addEventListener('timeupdate', () => {
    if (!isDraggingProgress) {
      const pct = player.duration ? (player.currentTime / player.duration) * 100 : 0;
      progressFill.style.width = pct + '%';
      progressThumb.style.left = pct + '%';
      timeDisplay.textContent = fmtTime(player.currentTime) + ' / ' + fmtTime(player.duration || vidDuration);
    }
  });

  // Seek functions
  function seek(e) {
    const rect = progressEl.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const pct = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    progressFill.style.width = (pct * 100) + '%';
    progressThumb.style.left = (pct * 100) + '%';
    
    if (player.duration) {
      player.currentTime = pct * player.duration;
    }
  }

  // Progress click / drag listeners
  progressContainer.addEventListener('mousedown', (e) => {
    isDraggingProgress = true;
    seek(e);
    showControls();
  });

  document.addEventListener('mousemove', (e) => {
    if (isDraggingProgress) {
      seek(e);
      showControls();
    }
  });

  document.addEventListener('mouseup', () => {
    if (isDraggingProgress) {
      isDraggingProgress = false;
      resetControlsTimeout();
    }
  });

  // Mobile Touch Drag seek
  progressContainer.addEventListener('touchstart', (e) => {
    isDraggingProgress = true;
    seek(e);
    showControls();
  }, {passive: true});

  document.addEventListener('touchmove', (e) => {
    if (isDraggingProgress) {
      seek(e);
      showControls();
    }
  }, {passive: true});

  document.addEventListener('touchend', () => {
    if (isDraggingProgress) {
      isDraggingProgress = false;
      resetControlsTimeout();
    }
  });

  // Mute / Volume dynamic icon updating
  function updateVolumeIcon(vol, isMuted) {
    if (isMuted || vol == 0) {
      volumeIcon.innerHTML = '<path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="22" y1="9" x2="16" y2="15" stroke="currentColor" stroke-width="2"/><line x1="16" y1="9" x2="22" y2="15" stroke="currentColor" stroke-width="2"/>';
    } else if (vol < 0.5) {
      volumeIcon.innerHTML = '<path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07" stroke="currentColor" stroke-width="2"/>';
    } else {
      volumeIcon.innerHTML = '<path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07" stroke="currentColor" stroke-width="2"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14" stroke="currentColor" stroke-width="2"/>';
    }
  }

  muteBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (player.muted) {
      player.muted = false;
      player.volume = lastVolume;
      volumeSlider.value = lastVolume;
      updateVolumeIcon(lastVolume, false);
    } else {
      lastVolume = player.volume || 1;
      player.muted = true;
      player.volume = 0;
      volumeSlider.value = 0;
      updateVolumeIcon(0, true);
    }
    resetControlsTimeout();
  });

  volumeSlider?.addEventListener('input', function(e) {
    e.stopPropagation();
    player.volume = this.value;
    player.muted = (this.value == 0);
    lastVolume = this.value > 0 ? this.value : lastVolume;
    updateVolumeIcon(this.value, player.muted);
    resetControlsTimeout();
  });

  // Speed selector
  document.getElementById('speed-select').addEventListener('change', function(e) {
    e.stopPropagation();
    player.playbackRate = this.value;
    resetControlsTimeout();
  });

  // Skip 10s Center Controls
  document.getElementById('overlay-skip-back').addEventListener('click', (e) => {
    e.stopPropagation();
    player.currentTime = Math.max(0, player.currentTime - 10);
    resetControlsTimeout();
  });

  document.getElementById('overlay-skip-fwd').addEventListener('click', (e) => {
    e.stopPropagation();
    player.currentTime = Math.min(player.duration || vidDuration, player.currentTime + 10);
    resetControlsTimeout();
  });

  // Skip 10s Bottom Controls (Desktop-only fallback)
  document.getElementById('skip-back')?.addEventListener('click', (e) => {
    e.stopPropagation();
    player.currentTime = Math.max(0, player.currentTime - 10);
    resetControlsTimeout();
  });
  
  document.getElementById('skip-fwd')?.addEventListener('click', (e) => {
    e.stopPropagation();
    player.currentTime = Math.min(player.duration || vidDuration, player.currentTime + 10);
    resetControlsTimeout();
  });

  // Fullscreen
  document.getElementById('fullscreen-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else {
      wrapper.requestFullscreen().catch(() => {
        // Fallback for Safari/iOS
        player.webkitEnterFullscreen?.();
      });
    }
    resetControlsTimeout();
  });

  // Mini player
  document.getElementById('mini-btn')?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (document.pictureInPictureElement) {
      document.exitPictureInPicture();
    } else {
      player.requestPictureInPicture?.();
    }
    resetControlsTimeout();
  });

  // Keyboard shortcuts
  document.addEventListener('keydown', e => {
    if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
    if (e.code === 'Space') {
      e.preventDefault();
      player.paused ? player.play() : player.pause();
    }
    if (e.code === 'ArrowRight') player.currentTime = Math.min(player.duration || vidDuration, player.currentTime + 10);
    if (e.code === 'ArrowLeft') player.currentTime = Math.max(0, player.currentTime - 10);
    if (e.code === 'ArrowUp') {
      e.preventDefault();
      player.volume = Math.min(1, player.volume + 0.1);
      volumeSlider.value = player.volume;
      updateVolumeIcon(player.volume, false);
    }
    if (e.code === 'ArrowDown') {
      e.preventDefault();
      player.volume = Math.max(0, player.volume - 0.1);
      volumeSlider.value = player.volume;
      updateVolumeIcon(player.volume, player.volume === 0);
    }
    if (e.code === 'KeyF') {
      document.getElementById('fullscreen-btn').click();
    }
  });
}

// Like
document.getElementById('like-btn')?.addEventListener('click',async function(){
  const res=await fetch('<?= BASE_URL ?>/api/videos.php?action=react',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({video_id:<?= $vid ?>,type:'like'})
  });
  const d=await res.json();
  if(d.success) document.getElementById('like-count').textContent=d.likes;
});

// Share modal
document.getElementById('share-btn').addEventListener('click',()=>document.getElementById('share-modal').classList.add('open'));
document.getElementById('share-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});

// Watch Later
document.getElementById('wl-btn')?.addEventListener('click',async function(){
  const res=await fetch('<?= BASE_URL ?>/api/videos.php?action=watch_later',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({video_id:<?= $vid ?>})
  });
  const d=await res.json();
  this.textContent=d.saved?'Saved ✓':'Save';
});

// Subscribe
document.getElementById('sub-btn')?.addEventListener('click',async function(){
  const res=await fetch('<?= BASE_URL ?>/api/videos.php?action=subscribe',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({channel_id:<?= $video['user_id'] ?>})
  });
  const d=await res.json();
  this.textContent=d.subscribed?'Subscribed ✓':'Subscribe';
});

// Load Comments
document.getElementById('load-comments')?.addEventListener('click',async function(){
  this.style.display='none';
  const res=await fetch('<?= BASE_URL ?>/api/videos.php?action=comments&video_id=<?= $vid ?>');
  const d=await res.json();
  const list=document.getElementById('comments-list');
  (d.comments||[]).forEach(c=>{
    const div=document.createElement('div');
    div.style.cssText='display:flex;gap:12px;margin-bottom:16px';
    div.innerHTML=`<img src="${c.avatar}" class="avatar avatar-sm" width="32" height="32" loading="lazy">
      <div><div style="font-weight:600;font-size:.85rem">${c.username}</div>
      <div style="font-size:.88rem;margin-top:4px;color:var(--text2)">${c.content}</div>
      <div style="font-size:.75rem;color:var(--text3);margin-top:4px">${c.ago}</div></div>`;
    list.appendChild(div);
  });
});

// Post Comment
document.getElementById('comment-form')?.addEventListener('submit',async function(e){
  e.preventDefault();
  const input=document.getElementById('comment-input');
  if(!input.value.trim()) return;
  const res=await fetch('<?= BASE_URL ?>/api/videos.php?action=comment',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({video_id:<?= $vid ?>,content:input.value.trim()})
  });
  const d=await res.json();
  if(d.success){ input.value=''; }
});

// HLS support
if(player && typeof Hls!=='undefined'&&Hls.isSupported()&&player.dataset.hls){
  const hls=new Hls({maxBufferLength:30});
  hls.loadSource(player.dataset.hls);
  hls.attachMedia(player);
}
</script>
<script>
window.FH_WATCH = {
  viewId: <?= (int)$view_session_id ?>,
  videoId: <?= (int)$vid ?>,
  endpoint: <?= json_encode(BASE_URL . '/api/watchtime.php') ?>
};
</script>
<script src="<?= BASE_URL ?>/assets/js/watchtime.js" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
