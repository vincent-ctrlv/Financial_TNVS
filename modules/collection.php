<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('collection');
require __DIR__ . '/../includes/db.php';
$user = current_user();

// Auto-update overdue statuses for receivables before reporting
if ($user['role'] === 'driver') {
    $stmt = $mysqli->prepare("UPDATE accountsreceivable SET status='overdue' WHERE status='pending' AND due_date < CURDATE() AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        if (!$stmt->execute()) {
            error_log("Failed to auto-update driver overdue accounts receivable: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Prepare statement failed for driver overdue AR update: " . $mysqli->error);
    }
} else {
    if (!$mysqli->query("UPDATE accountsreceivable SET status='overdue' WHERE status='pending' AND due_date < CURDATE()")) {
        error_log("Failed to auto-update all overdue accounts receivable: " . $mysqli->error);
    }
}

$rows = [];
$totals = ['pending' => 0.0, 'paid' => 0.0, 'overdue' => 0.0];

// Compute totals across all AR
if ($user['role'] === 'driver') {
    $stmt = $mysqli->prepare("SELECT status, amount_due FROM accountsreceivable WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $st = strtolower((string)($row['status'] ?? ''));
                $amt = (float)($row['amount_due'] ?? 0);
                if (array_key_exists($st, $totals)) {
                    $totals[$st] += $amt;
                } else {
                    $totals['pending'] += $amt;
                }
            }
        } else {
            error_log("Failed to calculate driver AR totals: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Prepare statement failed for driver AR totals: " . $mysqli->error);
    }
} else {
    $stmt = $mysqli->prepare("SELECT status, amount_due FROM accountsreceivable");
    if ($stmt) {
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $st = strtolower((string)($row['status'] ?? ''));
                $amt = (float)($row['amount_due'] ?? 0);
                if (array_key_exists($st, $totals)) {
                    $totals[$st] += $amt;
                } else {
                    $totals['pending'] += $amt;
                }
            }
        } else {
            error_log("Failed to calculate all AR totals: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Prepare statement failed for all AR totals: " . $mysqli->error);
    }
}

// Table filter: all | pending | paid | overdue (default: all)
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, ['all', 'pending', 'paid', 'overdue'], true)) {
    $statusFilter = 'all';
}

// Pull AR (inflows)
if ($user['role'] === 'driver') {
    if ($statusFilter === 'all') {
        $stmtAr = $mysqli->prepare("SELECT ar.ar_id, ar.user_id, u.name AS user_name, ar.amount_due, ar.due_date, ar.method, ar.status FROM accountsreceivable ar LEFT JOIN users u ON u.user_id = ar.user_id WHERE ar.user_id = ? ORDER BY ar.due_date ASC, ar.ar_id DESC LIMIT 200");
        if ($stmtAr) {
            $stmtAr->bind_param('i', $user['id']);
        }
    } else {
        $stmtAr = $mysqli->prepare("SELECT ar.ar_id, ar.user_id, u.name AS user_name, ar.amount_due, ar.due_date, ar.method, ar.status FROM accountsreceivable ar LEFT JOIN users u ON u.user_id = ar.user_id WHERE ar.status = ? AND ar.user_id = ? ORDER BY ar.due_date ASC, ar.ar_id DESC LIMIT 200");
        if ($stmtAr) {
            $stmtAr->bind_param('si', $statusFilter, $user['id']);
        }
    }
} else {
    if ($statusFilter === 'all') {
        $stmtAr = $mysqli->prepare("SELECT ar.ar_id, ar.user_id, u.name AS user_name, ar.amount_due, ar.due_date, ar.method, ar.status FROM accountsreceivable ar LEFT JOIN users u ON u.user_id = ar.user_id ORDER BY ar.due_date ASC, ar.ar_id DESC LIMIT 200");
    } else {
        $stmtAr = $mysqli->prepare("SELECT ar.ar_id, ar.user_id, u.name AS user_name, ar.amount_due, ar.due_date, ar.method, ar.status FROM accountsreceivable ar LEFT JOIN users u ON u.user_id = ar.user_id WHERE ar.status = ? ORDER BY ar.due_date ASC, ar.ar_id DESC LIMIT 200");
        if ($stmtAr) {
            $stmtAr->bind_param('s', $statusFilter);
        }
    }
}

if ($stmtAr) {
    if ($stmtAr->execute()) {
        $res = $stmtAr->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['source'] = 'AR';
            $rows[] = $row;
        }
    } else {
        error_log("Failed to execute AR pull query: " . $stmtAr->error);
    }
    $stmtAr->close();
} else {
    error_log("Prepare statement failed for AR pull: " . $mysqli->error);
}

// Pull AP (outflows) - treat as 'AP' source, skip when filtering 'overdue'
if ($statusFilter === 'all' || $statusFilter === 'pending' || $statusFilter === 'paid') {
    if ($user['role'] === 'driver') {
        if ($statusFilter === 'all') {
            $stmtAp = $mysqli->prepare("SELECT ap.ap_id, ap.user_id, u.name AS user_name, ap.amount_due, ap.due_date, ap.method, ap.status FROM accountspayable ap LEFT JOIN users u ON u.user_id = ap.user_id WHERE ap.user_id = ? ORDER BY ap.due_date ASC, ap.ap_id DESC LIMIT 200");
            if ($stmtAp) {
                $stmtAp->bind_param('i', $user['id']);
            }
        } else {
            $stmtAp = $mysqli->prepare("SELECT ap.ap_id, ap.user_id, u.name AS user_name, ap.amount_due, ap.due_date, ap.method, ap.status FROM accountspayable ap LEFT JOIN users u ON u.user_id = ap.user_id WHERE ap.status = ? AND ap.user_id = ? ORDER BY ap.due_date ASC, ap.ap_id DESC LIMIT 200");
            if ($stmtAp) {
                $stmtAp->bind_param('si', $statusFilter, $user['id']);
            }
        }
    } else {
        if ($statusFilter === 'all') {
            $stmtAp = $mysqli->prepare("SELECT ap.ap_id, ap.user_id, u.name AS user_name, ap.amount_due, ap.due_date, ap.method, ap.status FROM accountspayable ap LEFT JOIN users u ON u.user_id = ap.user_id ORDER BY ap.due_date ASC, ap.ap_id DESC LIMIT 200");
        } else {
            $stmtAp = $mysqli->prepare("SELECT ap.ap_id, ap.user_id, u.name AS user_name, ap.amount_due, ap.due_date, ap.method, ap.status FROM accountspayable ap LEFT JOIN users u ON u.user_id = ap.user_id WHERE ap.status = ? ORDER BY ap.due_date ASC, ap.ap_id DESC LIMIT 200");
            if ($stmtAp) {
                $stmtAp->bind_param('s', $statusFilter);
            }
        }
    }

    if ($stmtAp) {
        if ($stmtAp->execute()) {
            $res = $stmtAp->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['source'] = 'AP';
                $rows[] = $row;
            }
        } else {
            error_log("Failed to execute AP pull query: " . $stmtAp->error);
        }
        $stmtAp->close();
    } else {
        error_log("Prepare statement failed for AP pull: " . $mysqli->error);
    }
}

// Sort combined by due_date ASC, then newest id DESC
usort($rows, function($a, $b) {
    $da = strtotime((string)($a['due_date'] ?? ''));
    $db = strtotime((string)($b['due_date'] ?? ''));
    if ($da === $db) {
        $ida = (int)($a['ar_id'] ?? $a['ap_id'] ?? 0);
        $idb = (int)($b['ar_id'] ?? $b['ap_id'] ?? 0);
        return $idb <=> $ida;
    }
    return $da <=> $db;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Collection - TNVS</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
</head>
<body>
	<div id="app" class="app">
		<?php include '../includes/header.php'; ?>
		<?php include '../includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3><?php echo ($user['role'] === 'driver') ? 'My Collections & Statements' : 'Collection'; ?></h3>
				<div class="cards">
					<div class="card success">
						<h4>Paid</h4>
						<div class="big">₱<?php echo number_format($totals['paid'] ?? 0, 2); ?></div>
					</div>
					<div class="card warning">
						<h4>Pending</h4>
						<div class="big">₱<?php echo number_format($totals['pending'] ?? 0, 2); ?></div>
					</div>
					<div class="card">
						<h4>Overdue</h4>
						<div class="big">₱<?php echo number_format($totals['overdue'] ?? 0, 2); ?></div>
					</div>
				</div>
				<div class="chart-card">
					<h4><?php echo ($user['role'] === 'driver') ? 'Personal Accounts Statement' : 'Collections'; ?></h4>
					<div style="margin:10px 0; display:flex; gap:8px; flex-wrap:wrap;">
						<a class="btn-small" href="collection.php" style="<?php echo ($statusFilter==='all')?'background:#28a745;color:#fff;':''; ?>">All</a>
						<a class="btn-small" href="collection.php?status=pending" style="<?php echo ($statusFilter==='pending')?'background:#f0ad4e;color:#fff;':''; ?>">Pending</a>
						<a class="btn-small" href="collection.php?status=overdue" style="<?php echo ($statusFilter==='overdue')?'background:#dc3545;color:#fff;':''; ?>">Overdue</a>
						<a class="btn-small" href="collection.php?status=paid" style="<?php echo ($statusFilter==='paid')?'background:#28a745;color:#fff;':''; ?>">Paid</a>
					</div>
					<div class="table-wrap">
						<table class="table">
							<thead><tr><th>ID</th><th>Source</th><th>User</th><th>Amount</th><th>Due Date</th><th>Type</th><th>Status</th></tr></thead>
							<tbody>
							<?php foreach ($rows as $r): ?>
								<tr>
								<td><?php echo (int)($r['ar_id'] ?? $r['ap_id'] ?? 0); ?></td>
								<td><?php echo htmlspecialchars($r['source'] ?? ''); ?></td>
								<td><?php echo htmlspecialchars(($r['user_name'] ?? '') !== '' ? $r['user_name'] : (string)(int)$r['user_id']); ?></td>
								<?php $amount = (float)($r['amount_due'] ?? 0); if (($r['source'] ?? '') === 'AP') { $amount = -$amount; } ?>
								<td>₱<?php echo number_format($amount, 2); ?></td>
								<td><?php echo htmlspecialchars($r['due_date']); ?></td>
								<td><?php echo htmlspecialchars(str_replace('_',' ', $r['method'] ?? '')); ?></td>
								<td><?php $st = strtolower((string)($r['status'] ?? '')); $cls = $st === 'paid' ? 'status-paid' : ($st === 'overdue' ? 'status-overdue' : 'status-pending'); ?><span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
								</tr>
								<?php endforeach; ?>
							<?php if (empty($rows)): ?><tr><td colspan="7">No receivables or payables found.</td></tr><?php endif; ?>
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
