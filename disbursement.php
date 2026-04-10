<?php
// Session and access
require_once __DIR__ . '/auth.php';
require_login();
require_access('disbursement');
require __DIR__ . '/db.php';
$user = current_user();

// Ensure 'method' column exists for payment type in both tables
foreach (['accountsreceivable', 'accountspayable'] as $tbl) {
    if ($chk = $mysqli->query("SHOW COLUMNS FROM `$tbl` LIKE 'method'")) {
        if ($chk->num_rows === 0) {
            $mysqli->query("ALTER TABLE `$tbl` ADD COLUMN method ENUM('cash','gcash','bank_transfer') DEFAULT 'cash'");
        }
        $chk->free();
    }
}

$rows = [];$byMethod = [];
// Optional one-time repair: backfill missing/empty method values in disbursements
// Usage: visit disbursement.php?repair_methods=1 (admin only)
if (isset($_GET['repair_methods']) && ($_GET['repair_methods'] === '1')) {
	// Only allow admin to run the repair
	if ((($user['role'] ?? '') === 'admin')) {
		// Find disbursements with missing or empty method and try to infer from remarks
		if ($res = $mysqli->query("SELECT disbursement_id, remarks FROM disbursements WHERE method IS NULL OR method = ''")) {
			while ($row = $res->fetch_assoc()) {
				$did = (int)$row['disbursement_id'];
				$rm = trim((string)($row['remarks'] ?? ''));
				$meth = null;

				if (preg_match('/^AR #?([0-9]+)$/i', $rm, $m)) {
					$id = (int)$m[1];
					if ($r2 = $mysqli->query("SELECT COALESCE(method,'cash') AS method FROM accountsreceivable WHERE ar_id=".$id." LIMIT 1")) {
						if ($rrow = $r2->fetch_assoc()) {
							$meth = strtolower(trim((string)$rrow['method']));
						}
						$r2->free();
					}
				} elseif (preg_match('/^AP #?([0-9]+)$/i', $rm, $m)) {
					$id = (int)$m[1];
					if ($r2 = $mysqli->query("SELECT COALESCE(method,'cash') AS method FROM accountspayable WHERE ap_id=".$id." LIMIT 1")) {
						if ($rrow = $r2->fetch_assoc()) {
							$meth = strtolower(trim((string)$rrow['method']));
						}
						$r2->free();
					}
				}

				if (!in_array($meth, ['cash','gcash','bank_transfer'], true)) {
					$meth = 'cash';
				}

				if ($stmt = $mysqli->prepare("UPDATE disbursements SET method = ? WHERE disbursement_id = ?")) {
					$stmt->bind_param('si', $meth, $did);
					$stmt->execute();
					$stmt->close();
				}
			}
			$res->free();
		}

		// redirect after repair to avoid re-running on refresh
		header('Location: disbursement.php');
		exit;
	} else {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}
if ($res = $mysqli->query("SELECT d.disbursement_id, d.user_id, u.name AS user_name, d.amount, d.method, d.disbursement_date, d.remarks FROM disbursements d LEFT JOIN users u ON u.user_id = d.user_id ORDER BY d.disbursement_date DESC, d.disbursement_id DESC LIMIT 100")) {
	while ($row = $res->fetch_assoc()) {
		// normalize method value for display
		$method = strtolower((string)($row['method'] ?? ''));
		if (!in_array($method, ['cash','gcash','bank_transfer'], true) || $method === '') {
			// Try to infer method from remarks like 'AR #123' or 'AP #123'
			$rm = trim((string)($row['remarks'] ?? ''));
			$inferred = '';
			if (preg_match('/^AR #?(\d+)$/i', $rm, $m)) {
				$aid = (int)$m[1];
				if ($stmt = $mysqli->prepare('SELECT COALESCE(method,\'cash\') AS method FROM accountsreceivable WHERE ar_id = ? LIMIT 1')) {
					$stmt->bind_param('i', $aid);
					$stmt->execute();
					$res2 = $stmt->get_result();
					if ($r2 = $res2->fetch_assoc()) { $inferred = strtolower((string)($r2['method'] ?? '')); }
					$stmt->close();
				}
			} elseif (preg_match('/^AP #?(\d+)$/i', $rm, $m)) {
				$apid = (int)$m[1];
				if ($stmt = $mysqli->prepare('SELECT COALESCE(method,\'cash\') AS method FROM accountspayable WHERE ap_id = ? LIMIT 1')) {
					$stmt->bind_param('i', $apid);
					$stmt->execute();
					$res2 = $stmt->get_result();
					if ($r2 = $res2->fetch_assoc()) { $inferred = strtolower((string)($r2['method'] ?? '')); }
					$stmt->close();
				}
			}
			if (in_array($inferred, ['cash','gcash','bank_transfer'], true)) {
				$method = $inferred;
			} else {
				$method = 'cash';
			}
		}
		$row['method'] = $method;
		$rows[] = $row;
		// accumulate totals by inferred method
		if (!isset($byMethod[$method])) $byMethod[$method] = 0.0;
		$byMethod[$method] += (float)$row['amount'];
	}
	$res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Disbursement - TNVS</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<div id="app" class="app">
		<?php include 'header.php'; ?>
		<?php include 'sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>Disbursement Summary</h3>
				<div class="cards">
					<div class="card"><h4>By Method</h4>
						<div class="breakdown">
                            <?php foreach (['bank_transfer'=>'Bank Transfer','gcash'=>'GCash','cash'=>'Cash'] as $k=>$label): $val=(float)($byMethod[$k]??0); $w=min(100,max(5,round($val?60:10))); ?>
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
	<script src="app.js"></script>
</body>
</html>
