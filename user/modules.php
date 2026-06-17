<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('modules');

$user = current_user();
$role = $user['role'] ?? '';

// Define all possible modules with metadata
$allModules = [
    // Main
    'dashboard' => [
        'label'       => 'Dashboard',
        'description' => 'Overview of your financial activity and key metrics.',
        'icon'        => '📊',
        'color'       => '#28a745',
        'bg'          => 'rgba(40,167,69,0.10)',
        'url_fn'      => fn($root) => "$root/dashboard.php",
        'group'       => 'Main',
    ],
    // Accounts Management
    'ledger' => [
        'label'       => 'General Ledger',
        'description' => 'Complete record of all financial transactions and journal entries.',
        'icon'        => '📒',
        'color'       => '#3b82f6',
        'bg'          => 'rgba(59,130,246,0.10)',
        'url_fn'      => fn($root) => "$root/modules/ledger.php",
        'group'       => 'Accounts Management',
    ],
    'accounts_receivable' => [
        'label'       => 'Accounts Receivable',
        'description' => 'Track money owed to you and manage incoming payments.',
        'icon'        => '💵',
        'color'       => '#22c55e',
        'bg'          => 'rgba(34,197,94,0.10)',
        'url_fn'      => fn($root) => "$root/modules/accounts_receivable.php",
        'group'       => 'Accounts Management',
    ],
    'accounts_payable' => [
        'label'       => 'Accounts Payable',
        'description' => 'Manage your outstanding bills and outgoing payments.',
        'icon'        => '📤',
        'color'       => '#8b5cf6',
        'bg'          => 'rgba(139,92,246,0.10)',
        'url_fn'      => fn($root) => "$root/modules/accounts_payable.php",
        'group'       => 'Accounts Management',
    ],
    // Financial Operations
    'disbursement' => [
        'label'       => 'Disbursement',
        'description' => 'Record and review all payment disbursements.',
        'icon'        => '💸',
        'color'       => '#f59e0b',
        'bg'          => 'rgba(245,158,11,0.10)',
        'url_fn'      => fn($root) => "$root/modules/disbursement.php",
        'group'       => 'Financial Operations',
    ],
    'collection' => [
        'label'       => 'Collection',
        'description' => 'View and filter your collection statements and accounts.',
        'icon'        => '🏦',
        'color'       => '#06b6d4',
        'bg'          => 'rgba(6,182,212,0.10)',
        'url_fn'      => fn($root) => "$root/modules/collection.php",
        'group'       => 'Financial Operations',
    ],
    'budget' => [
        'label'       => 'Budget',
        'description' => 'Plan and monitor financial budgets across categories.',
        'icon'        => '📋',
        'color'       => '#ec4899',
        'bg'          => 'rgba(236,72,153,0.10)',
        'url_fn'      => fn($root) => "$root/modules/budget.php",
        'group'       => 'Financial Operations',
    ],
    // Administrative
    'users' => [
        'label'       => 'Users Management',
        'description' => 'Add, edit, and manage system user accounts and roles.',
        'icon'        => '👥',
        'color'       => '#ef4444',
        'bg'          => 'rgba(239,68,68,0.10)',
        'url_fn'      => fn($root) => "$root/admin/users.php",
        'group'       => 'Administrative',
    ],
    // My Account
    'profile' => [
        'label'       => 'My Profile',
        'description' => 'View and update your personal information and avatar.',
        'icon'        => '👤',
        'color'       => '#28a745',
        'bg'          => 'rgba(40,167,69,0.10)',
        'url_fn'      => fn($root) => "$root/user/profile.php",
        'group'       => 'My Account',
    ],
    'change_password' => [
        'label'       => 'Change Password',
        'description' => 'Update your login credentials to keep your account secure.',
        'icon'        => '🔐',
        'color'       => '#8b5cf6',
        'bg'          => 'rgba(139,92,246,0.10)',
        'url_fn'      => fn($root) => "$root/user/change_password.php",
        'group'       => 'My Account',
    ],
    'activity' => [
        'label'       => 'My Activity',
        'description' => 'A full audit log of every action you have taken in the system.',
        'icon'        => '📜',
        'color'       => '#3b82f6',
        'bg'          => 'rgba(59,130,246,0.10)',
        'url_fn'      => fn($root) => "$root/user/activity.php",
        'group'       => 'My Account',
    ],
    'my_finances' => [
        'label'       => 'My Finances',
        'description' => 'Personal financial summary — receivables, payables, and disbursements.',
        'icon'        => '💰',
        'color'       => '#f59e0b',
        'bg'          => 'rgba(245,158,11,0.10)',
        'url_fn'      => fn($root) => "$root/user/my_finances.php",
        'group'       => 'My Account',
    ],
];

// Determine web root (same logic as sidebar.php)
$_webRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (in_array(basename(dirname($_SERVER['SCRIPT_NAME'])), ['modules','admin','auth','user'])) {
    $_webRoot = dirname($_webRoot);
}
$_webRoot = rtrim($_webRoot, '/\\');

// Filter to only modules the current user can access (excluding 'modules' itself)
$accessible = [];
foreach ($allModules as $slug => $meta) {
    if (can_access($slug)) {
        $url = ($meta['url_fn'])($_webRoot);
        $accessible[$slug] = array_merge($meta, ['url' => $url]);
    }
}

// Group them
$grouped = [];
foreach ($accessible as $slug => $meta) {
    $grouped[$meta['group']][$slug] = $meta;
}

$totalCount = count($accessible);
$roleLabel  = match($role) {
    'admin'         => 'Administrator',
    'finance_staff' => 'Finance Staff',
    'driver'        => 'Driver',
    default         => ucfirst($role),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Modules – AdminFinance</title>
    <meta name="description" content="View all modules and features available to your account role.">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
    <style>
        /* ── Page layout ── */
        .mod-container { max-width: 1100px; margin: 0 auto; }

        /* ── Hero bar ── */
        .mod-hero {
            background: linear-gradient(135deg, rgba(40,167,69,0.08) 0%, rgba(59,130,246,0.06) 100%);
            border: 1.5px solid rgba(40,167,69,0.14);
            border-radius: 20px;
            padding: 28px 36px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .mod-hero-icon {
            width: 60px; height: 60px; border-radius: 18px;
            background: rgba(40,167,69,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; flex-shrink: 0;
        }
        .mod-hero-text h2 {
            font-size: 26px; font-weight: 800; color: var(--text);
            letter-spacing: -.7px; margin-bottom: 4px;
        }
        .mod-hero-text p { font-size: 14px; color: var(--muted); margin: 0; }
        .mod-hero-badge {
            margin-left: auto;
            display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
        }
        .role-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px; border-radius: 99px;
            background: rgba(40,167,69,0.1); color: var(--primary);
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
        }
        .count-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 99px;
            background: rgba(59,130,246,0.1); color: #3b82f6;
            font-size: 12px; font-weight: 700;
        }

        /* ── Group section ── */
        .mod-group { margin-bottom: 40px; }
        .mod-group-title {
            font-size: 11px; font-weight: 800; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .mod-group-title::after {
            content: ''; flex: 1; height: 1px; background: #f0f0f0;
        }

        /* ── Card grid ── */
        .mod-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }
        .mod-card {
            background: white;
            border-radius: 20px;
            padding: 24px 22px 20px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid rgba(0,0,0,0.04);
            text-decoration: none;
            display: flex; flex-direction: column; gap: 14px;
            transition: transform .22s cubic-bezier(.34,1.56,.64,1),
                        box-shadow .22s ease,
                        border-color .22s ease;
            position: relative; overflow: hidden;
        }
        .mod-card::before {
            content: '';
            position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px;
            border-radius: 50%;
            opacity: 0;
            transition: opacity .22s;
        }
        .mod-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: rgba(40,167,69,0.2); }
        .mod-card:hover::before { opacity: 1; }

        .mod-card-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
            transition: transform .22s cubic-bezier(.34,1.56,.64,1);
        }
        .mod-card:hover .mod-card-icon { transform: scale(1.12) rotate(-4deg); }

        .mod-card-body { flex: 1; }
        .mod-card-label {
            font-size: 15px; font-weight: 800; color: var(--text);
            margin-bottom: 5px; letter-spacing: -.2px;
        }
        .mod-card-desc {
            font-size: 12.5px; color: var(--muted);
            line-height: 1.55;
        }
        .mod-card-arrow {
            font-size: 18px; align-self: flex-end;
            opacity: 0; transform: translateX(-6px);
            transition: opacity .2s, transform .2s;
        }
        .mod-card:hover .mod-card-arrow { opacity: 1; transform: translateX(0); }

        /* ── Empty state ── */
        .mod-empty {
            text-align: center; padding: 80px 20px;
            color: var(--muted);
        }
        .mod-empty svg { opacity: .2; margin-bottom: 16px; }
        .mod-empty p { font-size: 15px; font-weight: 600; }

        /* ── Animations ── */
        @keyframes popIn {
            from { opacity: 0; transform: scale(.92) translateY(12px); }
            to   { opacity: 1; transform: scale(1)   translateY(0); }
        }
        .mod-card { animation: popIn .35s ease both; }
    </style>
</head>
<body>
<div id="app" class="app">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    <div id="overlay" class="overlay"></div>

    <main class="content" id="mainContent">
        <div class="mod-container">
            <section class="section active">

                <!-- Hero -->
                <div class="mod-hero">
                    <div class="mod-hero-icon">🧩</div>
                    <div class="mod-hero-text">
                        <h2>My Modules</h2>
                        <p>All features and pages available to your account.</p>
                    </div>
                    <div class="mod-hero-badge">
                        <span class="role-pill">🎭 <?php echo htmlspecialchars($roleLabel); ?></span>
                        <span class="count-pill">🔓 <?php echo $totalCount; ?> module<?php echo $totalCount !== 1 ? 's' : ''; ?> unlocked</span>
                    </div>
                </div>

                <?php if (empty($accessible)): ?>
                <div class="mod-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 24 24"
                         fill="none" stroke="#28a745" stroke-width="1.5">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    <p>No modules are available for your role.</p>
                </div>
                <?php else: ?>

                <?php foreach ($grouped as $groupName => $modules): ?>
                <div class="mod-group">
                    <div class="mod-group-title"><?php echo htmlspecialchars($groupName); ?></div>
                    <div class="mod-grid">
                        <?php $delay = 0; foreach ($modules as $slug => $meta): ?>
                        <a href="<?php echo htmlspecialchars($meta['url']); ?>"
                           class="mod-card"
                           id="mod-<?php echo htmlspecialchars($slug); ?>"
                           style="animation-delay:<?php echo $delay * 0.05; ?>s;
                                  --card-color:<?php echo $meta['color']; ?>;">
                            <!-- background orb inherits color via pseudo-element -->
                            <style>
                                #mod-<?php echo htmlspecialchars($slug); ?>::before { background: <?php echo $meta['color']; ?>; }
                            </style>
                            <div class="mod-card-icon"
                                 style="background:<?php echo $meta['bg']; ?>;">
                                <?php echo $meta['icon']; ?>
                            </div>
                            <div class="mod-card-body">
                                <div class="mod-card-label"
                                     style="color:<?php echo $meta['color']; ?>">
                                    <?php echo htmlspecialchars($meta['label']); ?>
                                </div>
                                <div class="mod-card-desc">
                                    <?php echo htmlspecialchars($meta['description']); ?>
                                </div>
                            </div>
                            <div class="mod-card-arrow" style="color:<?php echo $meta['color']; ?>">→</div>
                        </a>
                        <?php $delay++; endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php endif; ?>

            </section>
        </div>
    </main>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
