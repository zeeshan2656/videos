<?php
// ============================================================
// FreeHub.Live — Withdrawal Request Page (all users)
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
$currency = $user['preferred_currency'] ?? fh_user_currency();
$minUsd = fh_min_withdrawal_usd($uid);
$canWithdraw = (float)$user['balance'] >= $minUsd && !fh_pending_withdrawal($uid);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'withdraw') {
        $method  = trim($_POST['payment_method'] ?? '');
        $details = trim($_POST['payment_details'] ?? '');
        $country = trim($_POST['country'] ?? '');
        if (!$method || strlen($details) < 5) {
            $error = 'Please provide payment method and full payment details.';
        } elseif ((float)$user['balance'] < $minUsd) {
            $error = 'Minimum withdrawal is ' . fh_format_money($minUsd, $currency) . '.';
        } elseif (fh_pending_withdrawal($uid)) {
            $error = 'You already have a pending withdrawal request.';
        } else {
            $amount = (float)$user['balance'];
            $withdrawalDays = (int)setting('withdrawal_days', '7');
            $dueBy = date('Y-m-d', strtotime("+$withdrawalDays days"));
            $approvalMode = setting('withdrawal_approval_mode', 'manual');
            $status = ($approvalMode === 'auto') ? 'approved' : 'pending';
            db_insert('withdrawal_requests', [
                'user_id'         => $uid,
                'amount'          => $amount,
                'currency'        => $currency,
                'payment_method'  => $method,
                'payment_details' => $details,
                'country'         => $country ?: null,
                'status'          => $status,
                'due_by'          => $dueBy,
            ]);
            db_insert('earnings', [
                'user_id'     => $uid,
                'type'        => 'payout',
                'amount'      => $amount,
                'description' => 'Withdrawal request — due by ' . $dueBy,
                'status'      => $status,
            ]);
            db_update('users', ['balance' => 0], 'id=?', [$uid]);
            $_SESSION['user']['balance'] = 0;
            if ($status === 'approved') {
                flash('success', 'Withdrawal request approved and processed automatically.');
            } else {
                flash('success', "Withdrawal request submitted. Admin will process within $withdrawalDays business days.");
            }
            redirect(BASE_URL . '/withdrawal.php');
        }
    }
}

$withdrawals = db_fetchAll(
    "SELECT * FROM withdrawal_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 20",
    [$uid]
);
$pendingWd = fh_pending_withdrawal($uid);

$meta_title = 'Withdrawal Setup & History';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

      <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <?php if (!is_admin()): ?>
      <!-- Withdrawal Request Card -->
      <div class="card" style="margin-bottom:24px">
        <h3 style="font-weight:700;margin-bottom:12px">Request Withdrawal</h3>
        <?php if ($pendingWd): ?>
          <p class="text-sm" style="color:var(--yellow)">
            Pending request of <?= fh_format_money((float)$pendingWd['amount'], $pendingWd['currency']) ?>
            — due by <?= e($pendingWd['due_by'] ?? '7 days') ?>.
          </p>
        <?php elseif ($canWithdraw): ?>
          <p class="text-sm text-muted" style="margin-bottom:16px">
            Your balance exceeds the <?= fh_format_money($minUsd, $currency) ?> minimum.
            Submit your payment details below to request a payout.
          </p>
          <form id="withdrawal-request-form" method="POST" style="max-width:520px">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="withdraw">
            <div class="form-group">
              <label class="form-label">Payment Method</label>
              <select class="form-input form-select" name="payment_method" required>
                <option value="">Select…</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="paypal">PayPal</option>
                <option value="wise">Wise</option>
                <option value="jazzcash">JazzCash (PK)</option>
                <option value="easypaisa">Easypaisa (PK)</option>
                <option value="upi">UPI (IN)</option>
                <option value="crypto">Cryptocurrency</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Country</label>
              <input class="form-input" type="text" name="country" placeholder="e.g. Pakistan, USA" maxlength="80">
            </div>
            <div class="form-group">
              <label class="form-label">Payment Details</label>
              <textarea class="form-input" name="payment_details" rows="4" required
                placeholder="Account name, bank/PayPal email, IBAN, wallet address, etc."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Request Withdrawal (<?= fh_format_money((float)$user['balance'], $currency) ?>)</button>
          </form>
        <?php else: ?>
          <p class="text-sm text-muted">
            Minimum withdrawal: <strong><?= fh_format_money($minUsd, $currency) ?></strong>.
            Current balance: <?= fh_format_money((float)$user['balance'], $currency) ?>.
          </p>
          <div class="progress" style="margin-top:12px">
            <div class="progress-bar-fill" style="width:<?= $minUsd > 0 ? min(100, round(((float)$user['balance'] / $minUsd) * 100)) : 0 ?>%"></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Withdrawal History -->
      <div class="card" style="margin-bottom:24px">
        <h3 style="font-weight:700;margin-bottom:12px">Withdrawal History</h3>
        <?php if ($withdrawals): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Amount</th><th>Method</th><th>Status</th><th>Due By</th><th>Requested</th></tr></thead>
            <tbody>
            <?php foreach ($withdrawals as $w): ?>
            <tr>
              <td><?= fh_format_money((float)$w['amount'], $w['currency']) ?></td>
              <td class="text-sm"><?= e($w['payment_method']) ?></td>
              <td><span class="badge badge-<?= $w['status']==='paid'?'green':($w['status']==='pending'?'yellow':'gray') ?>"><?= e($w['status']) ?></span></td>
              <td class="text-xs text-muted"><?= e($w['due_by'] ?? '—') ?></td>
              <td class="text-xs text-muted"><?= date('M j, Y', strtotime($w['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-muted text-sm">No withdrawal requests submitted yet.</p>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="card">
        <p class="text-sm text-muted">Withdrawals are managed through the Admin Panel for administrator accounts.</p>
      </div>
      <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
