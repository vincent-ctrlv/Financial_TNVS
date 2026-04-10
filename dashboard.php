<?php
// Basic PHP dashboard that reads from the `finance` schema
require_once __DIR__ . '/auth.php';
require_login();
require_access('dashboard');
require __DIR__ . '/db.php';

// Helper to fetch a single value safely
function fetch_value($mysqli, $sql, $default = 0) {
	if (!($res = $mysqli->query($sql))) {
		return $default;
	}
	$row = $res->fetch_row();
	return isset($row[0]) ? (float)$row[0] : $default;
}

// Totals and simple KPIs
$totalRevenue = fetch_value($mysqli, "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='fare'");
$totalExpenses = fetch_value($mysqli, "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('fuel','lease','incentive','payout','platform_fee')");
$netIncome = $totalRevenue - $totalExpenses;


// AR/AP breakdowns (totals by status)
function fetch_status_totals($mysqli, $table) {
    $totals = [];
    $col = ($table === 'accountsreceivable') ? 'amount_due' : 'amount_due';
    if ($res = $mysqli->query("SELECT status, COALESCE(SUM($col),0) AS total FROM $table GROUP BY status")) {
        while ($r = $res->fetch_assoc()) { $totals[strtolower((string)$r['status'])] = (float)$r['total']; }
        $res->free();
    }
    return $totals;
}
$arTotalsByStatus = fetch_status_totals($mysqli, 'accountsreceivable');
$apTotalsByStatus = fetch_status_totals($mysqli, 'accountspayable');

$apPending = fetch_value($mysqli, "SELECT COALESCE(SUM(amount_due),0) FROM accountspayable WHERE status='pending'");
$arPending = fetch_value($mysqli, "SELECT COALESCE(SUM(amount_due),0) FROM accountsreceivable WHERE status IN ('pending','overdue')");

// Paid totals
$arPaid = fetch_value($mysqli, "SELECT COALESCE(SUM(amount_due),0) FROM accountsreceivable WHERE status='paid'");
$apPaid = fetch_value($mysqli, "SELECT COALESCE(SUM(amount_due),0) FROM accountspayable WHERE status='paid'");

// Recent transactions including AR/AP
$recent = [];

// Get recent transactions
if ($res = $mysqli->query("SELECT transaction_id as id, type, amount, transaction_date as date, 'transaction' as source FROM transactions ORDER BY transaction_date DESC, transaction_id DESC LIMIT 5")) {
	while ($row = $res->fetch_assoc()) {
		$recent[] = $row;
	}
	$res->free();
}

// Get recent AR records
if ($res = $mysqli->query("SELECT ar_id as id, 'AR' as type, amount_due as amount, due_date as date, 'ar' as source FROM accountsreceivable ORDER BY due_date DESC, ar_id DESC LIMIT 3")) {
	while ($row = $res->fetch_assoc()) {
		$recent[] = $row;
	}
	$res->free();
}

// Get recent AP records
if ($res = $mysqli->query("SELECT ap_id as id, 'AP' as type, amount_due as amount, due_date as date, 'ap' as source FROM accountspayable ORDER BY due_date DESC, ap_id DESC LIMIT 3")) {
	while ($row = $res->fetch_assoc()) {
		$recent[] = $row;
	}
	$res->free();
}

// Sort all recent records by date
usort($recent, function($a, $b) {
	return strtotime($b['date']) - strtotime($a['date']);
});

// Limit to 10 most recent
$recent = array_slice($recent, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TNVS Dashboard</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<div id="app" class="app">
		<header class="app-header">
			<button id="menuToggle" class="icon-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
			<div class="brand" role="img" aria-label="TNVS Finance System">
				<svg viewBox="0 0 120 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<text x="10" y="16" font-size="16" font-weight="800" letter-spacing="0.5" font-family="Segoe UI, Roboto, Helvetica, Arial, sans-serif" fill="currentColor">SAKAYSAYA</text>
				</svg>
			</div>
			<div class="user">
				<span id="currentUser">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?></span>
				<a href="logout.php" class="text-btn">Logout</a>
			</div>
		</header>

		<nav id="sidebar" class="sidebar">
			<div class="group">
				<h4>Main</h4>
				<?php if (can_access('dashboard')): ?><a class="nav active" href="dashboard.php"><span>Dashboard</span></a><?php endif; ?>
			</div>
			<div class="group">
				<h4>Accounts Management</h4>
				<?php if (can_access('ledger')): ?><a class="nav" href="ledger.php"><span>General Ledger</span></a><?php endif; ?>
				<?php if (can_access('accounts_receivable')): ?><a class="nav" href="accounts_receivable.php"><span>Accounts Receivable</span></a><?php endif; ?>
				<?php if (can_access('accounts_payable')): ?><a class="nav" href="accounts_payable.php"><span>Accounts Payable</span></a><?php endif; ?>
				
			</div>
			<div class="group">
				<h4>Financial Operations</h4>
				<?php if (can_access('disbursement')): ?><a class="nav" href="disbursement.php"><span>Disbursement</span></a><?php endif; ?>
				<?php if (can_access('collection')): ?><a class="nav" href="collection.php"><span>Collection</span></a><?php endif; ?>
			</div>
			<div class="group">
				<h4>Administrative System</h4>
				<?php if (can_access('users')): ?><a class="nav" href="users.php"><span>Users</span></a><?php endif; ?>
			</div>
			<div class="group">
				<div style="margin-top:auto;padding:16px;">
					<a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
						<?php
						$name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
						$initial = strtoupper(substr((string)$name,0,1));
						$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#d9d9d9"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#555">'.$initial.'</text></svg>';
						$src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
						?>
						<img src="<?php echo $src; ?>" alt="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto;" />
						<div style="font-size:14px;line-height:1;">
							<div style="font-weight:700"><?php echo $name; ?></div>
							<div style="font-size:12px;color:#666"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></div>
						</div>
					</a>
				</div>
		</nav>
		<div id="overlay" class="overlay"></div>

		<main class="content" id="mainContent">
			<section id="dashboard" class="section active">
				<h3>Dashboard Overview</h3>
				<div class="cards">
					<div class="card success">
						<h4>Total Revenue</h4>
						<div class="big">₱<?php echo number_format($totalRevenue, 2); ?></div>
						<div class="card-trend positive">Up to date</div>
					</div>
					<div class="card warning">
						<h4>Total Expenses</h4>
						<div class="big">₱<?php echo number_format($totalExpenses, 2); ?></div>
						<div class="card-trend">Tracked</div>
					</div>
					<div class="card">
						<h4>Net Income</h4>
						<div class="big">₱<?php echo number_format($netIncome, 2); ?></div>
						<div class="card-trend <?php echo ($netIncome >= 0 ? 'positive' : ''); ?>"><?php echo $netIncome >= 0 ? 'Positive' : 'Negative'; ?></div>
					</div>
					<div class="card">
						<h4>AR Pending</h4>
						<div class="big">₱<?php echo number_format($arPending, 2); ?></div>
						<div class="card-trend">Receivables</div>
					</div>
					<div class="card">
						<h4>AP Pending</h4>
						<div class="big">₱<?php echo number_format($apPending, 2); ?></div>
						<div class="card-trend">Payables</div>
					</div>
					<div class="card">
						<h4>AR Paid</h4>
						<div class="big">₱<?php echo number_format($arPaid, 2); ?></div>
						<div class="card-trend">Receivables</div>
					</div>
					<div class="card">
						<h4>AP Paid</h4>
						<div class="big">₱<?php echo number_format($apPaid, 2); ?></div>
						<div class="card-trend">Payables</div>
					</div>
				</div>

                <div class="analytics">
                    <div class="chart-card" style="grid-column: 1 / -1;">
                        <h4>AR/AP Breakdown</h4>
                        <div class="breakdown">
                            <div class="breakdown-row">
                                <div class="label">AR Pending</div>
                                <?php $arPend=(float)($arTotalsByStatus['pending']??0)+(float)($arTotalsByStatus['overdue']??0); $arTotal=(float)array_sum($arTotalsByStatus); $w=$arTotal>0?min(100,max(5,round(($arPend/$arTotal)*100))):10; ?>
                                <div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
                                <div class="value">₱<?php echo number_format($arPend,2); ?></div>
                            </div>
                            <div class="breakdown-row">
                                <div class="label">AR Paid</div>
                                <?php $v=(float)($arTotalsByStatus['paid']??0); $w=$arTotal>0?min(100,max(5,round(($v/$arTotal)*100))):10; ?>
                                <div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
                                <div class="value">₱<?php echo number_format($v,2); ?></div>
                            </div>
                            <div class="breakdown-row">
                                <div class="label">AP Pending</div>
                                <?php $apTotal=(float)array_sum($apTotalsByStatus); $v=(float)($apTotalsByStatus['pending']??0); $w=$apTotal>0?min(100,max(5,round(($v/$apTotal)*100))):10; ?>
                                <div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
                                <div class="value">₱<?php echo number_format($v,2); ?></div>
                            </div>
                            <div class="breakdown-row">
                                <div class="label">AP Paid</div>
                                <?php $v=(float)($apTotalsByStatus['paid']??0); $w=$apTotal>0?min(100,max(5,round(($v/$apTotal)*100))):10; ?>
                                <div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
                                <div class="value">₱<?php echo number_format($v,2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="analytics">
                    <div class="chart-card">
                        <h4>Recent Transactions</h4>
                        <div class="table-wrap">
                            <table class="table" id="txTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $r): ?>
                                    <tr>
                                        <td><?php echo (int)$r['id']; ?></td>
                                        <td><?php echo htmlspecialchars($r['type']); ?></td>
                                        <td>₱<?php echo number_format((float)$r['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($r['date']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent)): ?>
                                    <tr><td colspan="4">No transactions found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
			</section>
		</main>
	</div>
	<script src="app.js"></script>
</body>
</html>

