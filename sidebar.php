<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function is_active($p) {
    global $currentPage;
    return $currentPage === $p ? 'active' : '';
}
?>
<nav id="sidebar" class="sidebar">
    <div class="group">
        <h4>Main</h4>
        <?php if (can_access('dashboard')): ?>
        <a class="nav <?php echo is_active('dashboard.php'); ?>" href="dashboard.php"><span>Dashboard</span></a>
        <?php endif; ?>
    </div>
    
    <div class="group">
        <h4>Accounts Management</h4>
        <?php if (can_access('ledger')): ?>
        <a class="nav <?php echo is_active('ledger.php'); ?>" href="ledger.php"><span>General Ledger</span></a>
        <?php endif; ?>
        <?php if (can_access('accounts_receivable')): ?>
        <a class="nav <?php echo is_active('accounts_receivable.php'); ?>" href="accounts_receivable.php"><span>Accounts Receivable</span></a>
        <?php endif; ?>
        <?php if (can_access('accounts_payable')): ?>
        <a class="nav <?php echo is_active('accounts_payable.php'); ?>" href="accounts_payable.php"><span>Accounts Payable</span></a>
        <?php endif; ?>
    </div>
    
    <div class="group">
        <h4>Financial Operations</h4>
        <?php if (can_access('disbursement')): ?>
        <a class="nav <?php echo is_active('disbursement.php'); ?>" href="disbursement.php"><span>Disbursement</span></a>
        <?php endif; ?>
        <?php if (can_access('collection')): ?>
        <a class="nav <?php echo is_active('collection.php'); ?>" href="collection.php"><span>Collection</span></a>
        <?php endif; ?>
        <?php if (can_access('budget')): ?>
        <a class="nav <?php echo is_active('budget.php'); ?>" href="budget.php"><span>Budget</span></a>
        <?php endif; ?>
    </div>
    
    <?php if (can_access('users')): ?>
    <div class="group">
        <h4>Administrative System</h4>
        <a class="nav <?php echo is_active('users.php'); ?>" href="users.php"><span>Users Management</span></a>
    </div>
    <?php endif; ?>

    <!-- Sidebar profile at bottom -->
    <div class="sidebar-footer">
        <a href="profile.php" class="sidebar-profile">
            <?php
            $u = current_user();
            $u_name = htmlspecialchars($u['name'] ?? 'User');
            $u_email = htmlspecialchars($u['email'] ?? '');
            $u_avatar = $u['avatar'] ?? '';
            // Determine source: use upload if exists, otherwise fallback to premium SVG
            $u_src = '';
            if ($u_avatar && file_exists(__DIR__ . '/' . $u_avatar)) {
                $u_src = $u_avatar;
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
