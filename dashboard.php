<?php
// Basic PHP dashboard that reads from the `finance` schema
require_once __DIR__ . '/auth.php';
require_login();
require_access('dashboard');
require __DIR__ . '/db.php';
$user = current_user();


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
		<?php include 'header.php'; ?>
		<?php include 'sidebar.php'; ?>

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

