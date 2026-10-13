<?php
// Session and access
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();
require_access('disbursement');
$user = current_user();

$rows = [];
$byMethod = [];

$queryDisbursements = "SELECT d.disbursement_id, d.user_id, u.name AS user_name, d.amount, d.method, d.disbursement_date, d.remarks 
                       FROM disbursements d 
                       LEFT JOIN users u ON u.user_id = d.user_id 
                       ORDER BY d.disbursement_date DESC, d.disbursement_id DESC 
                       LIMIT 100";

if ($stmt = $mysqli->prepare($queryDisbursements)) {
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // normalize method value for display
            $method = strtolower((string)($row['method'] ?? ''));
            if (!in_array($method, ['cash', 'gcash', 'bank_transfer'], true) || $method === '') {
                $method = 'cash';
            }
            $row['method'] = $method;
            $rows[] = $row;
            // accumulate totals by inferred method
            if (!isset($byMethod[$method])) {
                $byMethod[$method] = 0.0;
            }
            $byMethod[$method] += (float)$row['amount'];
        }
    } else {
        error_log("Failed to execute disbursements query: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Failed to prepare disbursements query: " . $mysqli->error);
}

// Determine max method total for proportional bar widths
$_maxDisbMethod = max(array_values($byMethod) ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Disbursement - TNVS</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
</head>
<body>
	<div id="app" class="app">
		<?php include SRC_PATH . '/partials/header.php'; ?>
		<?php include SRC_PATH . '/partials/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>Disbursement Summary</h3>
				<div class="cards">
					<div class="card"><h4>By Method</h4>
						<div class="breakdown">
                            <?php foreach (['bank_transfer'=>'Bank Transfer','gcash'=>'GCash','cash'=>'Cash'] as $k=>$label): $val=(float)($byMethod[$k]??0); $w=$_maxDisbMethod>0?max(2,round(($val/$_maxDisbMethod)*100)):2; ?>
							<div class="breakdown-row"><div class="label"><?php echo $label; ?></div><div class="barline"><span style="--final-width: <?php echo $w; ?>%;"></span></div><div class="value">₱<?php echo number_format($val,2); ?></div></div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="chart-card">
					<h4>Recent Disbursements</h4>
					<div class="table-wrap">
						<table class="table">
							<thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Method</th><th>Date</th><th>Remarks</th></tr></thead>
							<tbody>
								<?php foreach ($rows as $r): ?>
								<tr>
									<td><?php echo (int)$r['disbursement_id']; ?></td>
									<td><?php echo htmlspecialchars(($r['user_name'] ?? '') !== '' ? $r['user_name'] : (string)(int)$r['user_id']); ?></td>
									<td>₱<?php echo number_format((float)$r['amount'], 2); ?></td>
									<td><?php echo htmlspecialchars(ucwords(str_replace('_',' ', (string)($r['method'] ?? 'cash')))); ?></td>
									<td><?php echo htmlspecialchars($r['disbursement_date']); ?></td>
									<td><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td>
								</tr>
								<?php endforeach; ?>
								<?php if (empty($rows)): ?><tr><td colspan="6">No disbursements found.</td></tr><?php endif; ?>
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
