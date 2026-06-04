<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_access('ledger');
require_once __DIR__ . '/includes/db.php';
$user = current_user();

$rows = [];
if ($res = $mysqli->query("SELECT transaction_id, type, amount, description, transaction_date FROM transactions ORDER BY transaction_date DESC, transaction_id DESC LIMIT 100")) {
	while ($row = $res->fetch_assoc()) { $rows[] = $row; }
	$res->free();
}

$totals = [];
if ($res = $mysqli->query("SELECT type, COALESCE(SUM(amount),0) AS total FROM transactions GROUP BY type")) {
	while ($r = $res->fetch_assoc()) { $totals[$r['type']] = (float)$r['total']; }
	$res->free();
}
// Determine max value for proportional bar widths
$_maxLedger = max(array_values($totals) ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>General Ledger - TNVS</title>
	<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
	<div id="app" class="app">
		<?php include 'includes/header.php'; ?>
		<?php include 'includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>General Ledger</h3>
				<div class="cards">
					<div class="card">
						<h4>Totals by Type</h4>
						<div class="breakdown">
							<?php foreach ([
								'fare' => 'Fare',
								'platform_fee' => 'Platform Fee',
								'fuel' => 'Fuel',
								'lease' => 'Lease',
								'incentive' => 'Incentive',
								'payout' => 'Payout',
							] as $key => $label): $val = (float)($totals[$key] ?? 0); $width = $_maxLedger > 0 ? max(2, round(($val / $_maxLedger) * 100)) : 2; ?>
							<div class="breakdown-row">
								<div class="label"><?php echo htmlspecialchars($label); ?></div>
								<div class="barline"><span style="--final-width: <?php echo $width; ?>%;"></span></div>
								<div class="value">₱<?php echo number_format($val, 2); ?></div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="chart-card">
					<h4>Recent Transactions</h4>
					<div class="table-wrap">
						<table class="table">
							<thead>
								<tr>
									<th>ID</th>
									<th>Type</th>
									<th>Amount</th>
									<th>Description</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($rows as $r): ?>
								<tr>
									<td><?php echo (int)$r['transaction_id']; ?></td>
									<td><?php echo htmlspecialchars($r['type']); ?></td>
									<td>₱<?php echo number_format((float)$r['amount'], 2); ?></td>
									<td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
									<td><?php echo htmlspecialchars($r['transaction_date']); ?></td>
								</tr>
								<?php endforeach; ?>
								<?php if (empty($rows)): ?>
								<tr><td colspan="5">No transactions found.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		</main>
	</div>
	<script src="assets/js/app.js"></script>
</body>
</html>
