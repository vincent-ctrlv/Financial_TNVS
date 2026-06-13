<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('ledger');
require_once __DIR__ . '/../includes/db.php';
$user = current_user();

$rows = [];
$queryTransactions = "SELECT transaction_id, type, amount, description, transaction_date 
                      FROM transactions 
                      ORDER BY transaction_date DESC, transaction_id DESC 
                      LIMIT 100";

if ($stmt = $mysqli->prepare($queryTransactions)) {
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    } else {
        error_log("Failed to execute ledger transactions query: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Failed to prepare ledger transactions query: " . $mysqli->error);
}

$totals = [];
$queryTotals = "SELECT type, COALESCE(SUM(amount),0) AS total FROM transactions GROUP BY type";

if ($stmt = $mysqli->prepare($queryTotals)) {
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $totals[$r['type']] = (float)$r['total'];
        }
    } else {
        error_log("Failed to execute ledger totals query: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Failed to prepare ledger totals query: " . $mysqli->error);
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
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
</head>
<body>
	<div id="app" class="app">
		<?php include '../includes/header.php'; ?>
		<?php include '../includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>General Ledger</h3>
				<div class="cards">
					<div class="card">
						<h4>Totals by Type</h4>
						<div class="vertical-breakdown">
							<?php foreach ([
								'fare' => 'Fare',
								'platform_fee' => 'Platform Fee',
								'fuel' => 'Fuel',
								'lease' => 'Lease',
								'incentive' => 'Incentive',
								'payout' => 'Payout',
							] as $key => $label): $val = (float)($totals[$key] ?? 0); $height_pct = ($_maxLedger > 0 && $val > 0) ? round(($val / $_maxLedger) * 100) : 0; ?>
							<div class="vertical-col col-<?php echo htmlspecialchars($key); ?>">
								<div class="value">₱<?php echo number_format($val, 2); ?></div>
								<div class="v-barline"><span style="--final-height: <?php echo $height_pct; ?>%;"></span></div>
								<div class="label"><?php echo htmlspecialchars($label); ?></div>
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
	<script src="../assets/js/app.js"></script>
</body>
</html>
