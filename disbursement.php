<?php
// Session and access
require_once __DIR__ . '/auth.php';
require_login();
require_access('disbursement');
require __DIR__ . '/db.php';
$user = current_user();

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
		<header class="app-header">
			<button id="menuToggle" class="icon-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
			<div class="brand" role="img" aria-label="TNVS Finance System">
				<svg viewBox="0 0 120 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<text x="10" y="16" font-size="16" font-weight="800" letter-spacing="0.5" font-family="Segoe UI, Roboto, Helvetica, Arial, sans-serif" fill="currentColor">SAKAYSAYA</text>
				</svg>
			</div>
			<div class="user">
				<span id="currentUser">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
				<a class="text-btn" href="logout.php">Logout</a>
			</div>
		</header>
		<nav id="sidebar" class="sidebar">
			<div class="group">
				<h4>Main</h4>
				<a class="nav" href="dashboard.php"><span>Dashboard</span></a>
			</div>
			<div class="group">
				<h4>Accounts Management</h4>
				<a class="nav" href="ledger.php"><span>General Ledger</span></a>
				<a class="nav" href="accounts_receivable.php"><span>Accounts Receivable</span></a>
				<a class="nav" href="accounts_payable.php"><span>Accounts Payable</span></a>
			</div>
			<div class="group">
				<h4>Financial Operations</h4>
				<a class="nav active" href="disbursement.php"><span>Disbursement</span></a>
				<a class="nav" href="collection.php"><span>Collection</span></a>
			</div>
			<div class="group">
				<h4>Administrative System</h4>
				<?php if ((($user['role'] ?? '') === 'admin')): ?>
				<a class="nav" href="users.php"><span>Users</span></a>
				<?php endif; ?>
			</div>
			<div class="group">
				
			</div>
			<!-- Sidebar profile at bottom -->
			<div style="margin-top:auto;padding:16px;">
				<a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
					<?php $cu = isset($user)? $user : (isset($_SESSION['user'])? $_SESSION['user'] : null); $nm = htmlspecialchars($cu['name'] ?? 'User'); $initial = strtoupper(substr($nm,0,1)); $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#d9d9d9"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#555">'.$initial.'</text></svg>'; $src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg); ?>
					<img src="<?php echo $src; ?>" alt="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto;" />
					<div style="font-size:14px;line-height:1;">
						<div style="font-weight:700"><?php echo $nm; ?></div>
						<div style="font-size:12px;color:#666"><?php echo htmlspecialchars($cu['email'] ?? ''); ?></div>
					</div>
				</a>
			</div>
		</nav>
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
