<?php
// ============================================================
// FreeHub.Live — Account Settings (all logged-in users)
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid   = auth_user()['id'];
$user  = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
$error = '';
$success = '';

$currencies = fh_currencies();
$currency_label = $currencies[$user['preferred_currency'] ?? 'USD']['label'] ?? ($user['preferred_currency'] ?? 'USD');
$role_label = ucfirst($user['role'] ?? 'user');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $currency = strtoupper(trim($_POST['preferred_currency'] ?? 'USD'));
        if (!isset($currencies[$currency])) $currency = 'USD';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email already taken
            $taken = db_fetch("SELECT id FROM users WHERE email=? AND id!=?", [$email, $uid]);
            if ($taken) {
                $error = 'This email address is already registered.';
            } else {
                $updateData = [
                    'email'              => $email,
                    'preferred_currency' => $currency,
                ];

                // Password change validation
                $current_pass = $_POST['current_password'] ?? '';
                $new_pass     = $_POST['new_password'] ?? '';
                $confirm_pass = $_POST['confirm_password'] ?? '';

                if (strlen($current_pass) > 0 || strlen($new_pass) > 0 || strlen($confirm_pass) > 0) {
                    if (!password_verify($current_pass, $user['password_hash'])) {
                        $error = 'The current password you entered is incorrect.';
                    } elseif (strlen($new_pass) < 6) {
                        $error = 'Your new password must be at least 6 characters long.';
                    } elseif ($new_pass !== $confirm_pass) {
                        $error = 'The new passwords do not match.';
                    } else {
                        $updateData['password_hash'] = password_hash($new_pass, PASSWORD_DEFAULT);
                    }
                }

                if (!$error) {
                    db_update('users', $updateData, 'id=?', [$uid]);
                    // Refresh user data
                    $user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
                    // Update session
                    $_SESSION['user']['email'] = $user['email'];
                    $_SESSION['user']['preferred_currency'] = $user['preferred_currency'] ?? 'USD';
                    $success = 'Settings updated successfully!';
                }
            }
        }
    }
}

$meta_title = 'Account Settings';
require_once __DIR__ . '/includes/header.php';
// Re-fetch user from DB because header.php overwrites $user with stale/incomplete session data
$user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);
?>

<div class="profile-shell" style="margin-top: 12px">

      <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px">&#10003; <?= e($success) ?></div><?php endif; ?>

      <form id="settings-edit-form" method="POST">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <div class="profile-grid" style="margin-bottom:24px">
          <!-- Account settings card -->
          <div class="card">
            <div class="section-header" style="margin-bottom:16px">
              <div>
                <h2 style="font-size:1rem;font-weight:800;margin-bottom:4px">Account Details</h2>
                <p class="text-muted text-sm" style="margin:0">Your email address and preferred display currency.</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input class="form-input" type="email" name="email" required value="<?= e($user['email']) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Preferred Currency</label>
              <select class="form-input form-select" name="preferred_currency">
                <?php foreach ($currencies as $code => $info): ?>
                <option value="<?= $code ?>" <?= ($user['preferred_currency'] ?? 'USD') === $code ? 'selected' : '' ?>>
                  <?= e($info['label']) ?> (<?= e($code) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Security settings card -->
          <div class="card">
            <div class="section-header" style="margin-bottom:16px">
              <div>
                <h2 style="font-size:1rem;font-weight:800;margin-bottom:4px">Security &amp; Password</h2>
                <p class="text-muted text-sm" style="margin:0">Leave these fields blank if you do not wish to change your password.</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input class="form-input" type="password" name="current_password" placeholder="Verify current password">
            </div>

            <div class="form-group">
              <label class="form-label">New Password</label>
              <input class="form-input" type="password" name="new_password" placeholder="Min 6 characters">
            </div>

            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input class="form-input" type="password" name="confirm_password" placeholder="Re-type new password">
            </div>
          </div>
        </div>

        <div class="card profile-actions">
          <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary" style="padding:10px 24px">&#10003; Save Settings</button>
        </div>
      </form>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
