<?php
$_SESSION['user'] = ['id' => 1];
require_once 'includes/db.php';
require_once 'includes/functions.php';
$uid = 1;
$user = db_fetch("SELECT * FROM users WHERE id=?", [$uid]);

echo "Database cover_image: " . var_export($user['cover_image'], true) . "\n";
echo "Cover URL: " . cover_url($user['cover_image']) . "\n";
echo "Empty check: " . var_export(!empty($user['cover_image']), true) . "\n";
echo "HTML output:\n";
?>
<img id="cover-img"
     src="<?= !empty($user['cover_image']) ? cover_url($user['cover_image']) : '' ?>"
     alt="Cover"
     style="width:100%;height:100%;object-fit:cover;display:<?= !empty($user['cover_image']) ? 'block' : 'none' ?>">
