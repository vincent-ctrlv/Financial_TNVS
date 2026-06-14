<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function is_active($p) {
    global $currentPage;
    return $currentPage === $p ? 'active' : '';
}
// Build a root-relative base path that works from any subfolder
$_webRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
// If we're inside a subfolder (modules/, admin/, auth/, user/), go up one level
if (in_array(basename(dirname($_SERVER['SCRIPT_NAME'])), ['modules','admin','auth','user'])) {
    $_webRoot = dirname($_webRoot);
}
$_webRoot = rtrim($_webRoot, '/\\');
?>
<nav id="sidebar" class="sidebar">
    <div class="group">
        <h4>Main</h4>
        <?php if (can_access('dashboard')): ?>
        <a class="nav <?php echo is_active('dashboard.php'); ?>" href="<?php echo $_webRoot; ?>/dashboard.php"><span>Dashboard</span></a>
        <?php endif; ?>
    </div>
    
    <div class="group">
        <h4>Accounts Management</h4>
        <?php if (can_access('ledger')): ?>
        <a class="nav <?php echo is_active('ledger.php'); ?>" href="<?php echo $_webRoot; ?>/modules/ledger.php"><span>General Ledger</span></a>
        <?php endif; ?>
        <?php if (can_access('accounts_receivable')): ?>
        <a class="nav <?php echo is_active('accounts_receivable.php'); ?>" href="<?php echo $_webRoot; ?>/modules/accounts_receivable.php"><span>Accounts Receivable</span></a>
        <?php endif; ?>
        <?php if (can_access('accounts_payable')): ?>
        <a class="nav <?php echo is_active('accounts_payable.php'); ?>" href="<?php echo $_webRoot; ?>/modules/accounts_payable.php"><span>Accounts Payable</span></a>
        <?php endif; ?>
    </div>
    
    <div class="group">
        <h4>Financial Operations</h4>
        <?php if (can_access('disbursement')): ?>
        <a class="nav <?php echo is_active('disbursement.php'); ?>" href="<?php echo $_webRoot; ?>/modules/disbursement.php"><span>Disbursement</span></a>
        <?php endif; ?>
        <?php if (can_access('collection')): ?>
        <a class="nav <?php echo is_active('collection.php'); ?>" href="<?php echo $_webRoot; ?>/modules/collection.php"><span>Collection</span></a>
        <?php endif; ?>
        <?php if (can_access('budget')): ?>
        <a class="nav <?php echo is_active('budget.php'); ?>" href="<?php echo $_webRoot; ?>/modules/budget.php"><span>Budget</span></a>
        <?php endif; ?>
    </div>
    
    <?php if (can_access('users')): ?>
    <div class="group">
        <h4>Administrative System</h4>
        <a class="nav <?php echo is_active('users.php'); ?>" href="<?php echo $_webRoot; ?>/admin/users.php"><span>Users Management</span></a>
    </div>
    <?php endif; ?>

    <div class="group">
        <h4>My Account</h4>
        <?php if (can_access('profile')): ?>
        <a class="nav <?php echo is_active('profile.php'); ?>" href="<?php echo $_webRoot; ?>/user/profile.php"><span>My Profile</span></a>
        <?php endif; ?>
        <?php if (can_access('change_password')): ?>
        <a class="nav <?php echo is_active('change_password.php'); ?>" href="<?php echo $_webRoot; ?>/user/change_password.php"><span>Change Password</span></a>
        <?php endif; ?>
        <?php if (can_access('activity')): ?>
        <a class="nav <?php echo is_active('activity.php'); ?>" href="<?php echo $_webRoot; ?>/user/activity.php"><span>My Activity</span></a>
        <?php endif; ?>
        <?php if (can_access('my_finances')): ?>
        <a class="nav <?php echo is_active('my_finances.php'); ?>" href="<?php echo $_webRoot; ?>/user/my_finances.php"><span>My Finances</span></a>
        <?php endif; ?>
    </div>

    <!-- Sidebar profile at bottom -->
    <div class="sidebar-footer">
        <a href="<?php echo $_webRoot; ?>/user/profile.php" class="sidebar-profile">
            <?php
            $u = current_user();
            $u_name = htmlspecialchars($u['name'] ?? 'User');
            $u_email = htmlspecialchars($u['email'] ?? '');
            $u_avatar = $u['avatar'] ?? '';
            // Determine source: use upload if exists, otherwise fallback to premium SVG
            $u_src = '';
            if ($u_avatar && file_exists(dirname(__DIR__) . '/' . $u_avatar)) {
                $u_src = $_webRoot . '/' . $u_avatar;
            } else {
                $u_initial = strtoupper(substr((string)$u_name, 0, 1));
                // Use literal primary color #28a745 (var doesn't work in data:img)
                $u_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#28a745" fill-opacity="0.1"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#28a745" font-weight="800">'.$u_initial.'</text></svg>';
                $u_src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($u_svg);
            }
            ?>
            <img src="<?php echo $u_src; ?>" alt="avatar" class="avatar-img" />
            <div class="profile-info">
                <div class="profile-name"><?php echo $u_name; ?></div>
                <div class="profile-email"><?php echo $u_email; ?></div>
            </div>
        </a>
    </div>
</nav>
