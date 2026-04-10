<?php
require_once __DIR__ . '/auth.php';
require_login();
require_access('accounts_payable');
require __DIR__ . '/db.php';
$user = current_user();


// Handle mark-paid (admin and finance_staff)
// Auto-update overdue for AP as well (mirror AR)
$mysqli->query("UPDATE accountspayable SET status='overdue' WHERE status='pending' AND due_date < CURDATE()");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)) {
    verify_csrf_or_die();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_paid') {
        $apId = (int)($_POST['ap_id'] ?? 0);
        if ($apId > 0) {
            // Update AP status
            $stmt = $mysqli->prepare("UPDATE accountspayable SET status='paid' WHERE ap_id=?");
            if ($stmt) { $stmt->bind_param('i', $apId); $stmt->execute(); $stmt->close(); }

            // Fetch details to insert a disbursement entry (outflow)
            if ($res = $mysqli->query("SELECT ap.user_id, ap.amount_due, COALESCE(ap.method,'cash') AS method FROM accountspayable ap WHERE ap.ap_id=".$apId." LIMIT 1")) {
                if ($row = $res->fetch_assoc()) {
                    $userId = (int)$row["user_id"];
                    $amount = (float)$row["amount_due"];
                    // normalize and validate method
                    $method = strtolower(trim((string)($row['method'] ?? 'cash')));
                    if (!in_array($method, ['cash','gcash','bank_transfer'], true)) { $method = 'cash'; }
                    $res->free();
                    // Avoid duplicate disbursement by unique remark
                    $remark = 'AP #'.$apId;
                    $exists = false;
                    if ($chk = $mysqli->prepare("SELECT 1 FROM disbursements WHERE remarks = ? LIMIT 1")) {
                        $chk->bind_param('s', $remark);
                        $chk->execute();
                        $chk->store_result();
                        $exists = $chk->num_rows > 0;
                        $chk->close();
                    }
                    if (!$exists) {
                        $ins = $mysqli->prepare("INSERT INTO disbursements (user_id, amount, method, disbursement_date, remarks) VALUES (?,?,?,?,?)");
                        if ($ins) {
                            $today = date('Y-m-d');
                            $ins->bind_param('idsss', $userId, $amount, $method, $today, $remark);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                } else { $res->free(); }
            }
        }
    } elseif ($action === 'mark_pending') {
        $apId = (int)($_POST['ap_id'] ?? 0);
        if ($apId > 0) {
            $stmt = $mysqli->prepare("UPDATE accountspayable SET status='pending' WHERE ap_id=?");
            if ($stmt) { $stmt->bind_param('i', $apId); $stmt->execute(); $stmt->close(); }
        }
    } elseif ($action === 'update_status') {
        $apId = (int)($_POST['ap_id'] ?? 0);
        $newStatus = strtolower(trim($_POST['status'] ?? ''));
        if ($apId > 0 && in_array($newStatus, ['pending','paid','overdue'], true)) {
            $stmt = $mysqli->prepare("UPDATE accountspayable SET status=? WHERE ap_id=?");
            if ($stmt) { $stmt->bind_param('si', $newStatus, $apId); $stmt->execute(); $stmt->close(); }
            if ($newStatus === 'paid') {
                if ($res = $mysqli->query("SELECT ap.user_id, ap.amount_due, COALESCE(ap.method,'cash') AS method FROM accountspayable ap WHERE ap.ap_id=".$apId." LIMIT 1")) {
                    if ($row = $res->fetch_assoc()) {
                        $userId = (int)$row['user_id'];
                        $amount = (float)$row['amount_due'];
                        // normalize and validate method
                        $method = strtolower(trim((string)($row['method'] ?? 'cash')));
                        if (!in_array($method, ['cash','gcash','bank_transfer'], true)) { $method = 'cash'; }
                        $res->free();
                        $remark = 'AP #'.$apId;
                        $exists = false;
                        if ($chk = $mysqli->prepare("SELECT 1 FROM disbursements WHERE remarks = ? LIMIT 1")) {
                            $chk->bind_param('s', $remark);
                            $chk->execute();
                            $chk->store_result();
                            $exists = $chk->num_rows > 0;
                            $chk->close();
                        }
                        if (!$exists) {
                            $ins = $mysqli->prepare("INSERT INTO disbursements (user_id, amount, method, disbursement_date, remarks) VALUES (?,?,?,?,?)");
                            if ($ins) {
                                $today = date('Y-m-d');
                                $ins->bind_param('idsss', $userId, $amount, $method, $today, $remark);
                                $ins->execute();
                                $ins->close();
                            }
                        }
                    } else { $res->free(); }
                }
            }
        }
    }
    // PRG
    header('Location: accounts_payable.php');
    exit;
}

// Ensure 'method' column exists for payment type (cash/gcash/bank_transfer)
if ($chk = $mysqli->query("SHOW COLUMNS FROM accountspayable LIKE 'method'")) {
    if ($chk->num_rows === 0) {
        $mysqli->query("ALTER TABLE accountspayable ADD COLUMN method ENUM('cash','gcash','bank_transfer') DEFAULT 'cash'");
    }
    $chk->free();
}



$ap = [];$apTotal = 0;$apPaidTotal = 0; $editApId = (int)($_GET['edit_ap_id'] ?? 0);
if ($res = $mysqli->query("SELECT ap.ap_id, ap.user_id, u.name AS user_name, ap.amount_due, ap.due_date, ap.method, ap.status FROM accountspayable ap LEFT JOIN users u ON u.user_id = ap.user_id ORDER BY ap.due_date DESC, ap.ap_id DESC LIMIT 100")) {
	while ($row = $res->fetch_assoc()) {
		$ap[] = $row;
		if (($row['status'] ?? '') === 'paid') {
			$apPaidTotal += (float)$row['amount_due'];
		} else {
			$apTotal += (float)$row['amount_due'];
		}
	}
	$res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Accounts Payable - TNVS</title>
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
				<a class="nav active" href="accounts_payable.php"><span>Accounts Payable</span></a>
			</div>
			<div class="group">
				<h4>Financial Operations</h4>
				<a class="nav" href="disbursement.php"><span>Disbursement</span></a>
				<a class="nav" href="collection.php"><span>Collection</span></a>
			</div>
			<div class="group">
				<h4>Administrative System</h4>
				<?php if ((($user['role'] ?? '') === 'admin')): ?>
				<a class="nav" href="users.php"><span>Users</span></a>
				<?php endif; ?>
			</div>
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
				<h3>Accounts Payable</h3>
				<div class="table-wrap">
					<table class="table">
						<thead><tr><th>ID</th><th>User</th><th>Amount Due</th><th>Due Date</th><th>Type</th><th>Status</th></tr></thead>
						<tbody>
							<?php foreach ($ap as $r): ?>
							<tr>
								<td><?php echo (int)$r['ap_id']; ?></td>
								<td><?php echo htmlspecialchars(($r['user_name'] ?? '') !== '' ? $r['user_name'] : (string)(int)$r['user_id']); ?></td>
								<td>₱<?php echo number_format((float)$r['amount_due'], 2); ?></td>
								<td><?php echo htmlspecialchars($r['due_date']); ?></td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_',' ', (string)($r['method'] ?? 'cash')))); ?></td>
                                <td>
                                    <?php $st = strtolower((string)($r['status'] ?? '')); $cls = $st === 'paid' ? 'status-paid' : ($st === 'overdue' ? 'status-overdue' : 'status-pending'); ?>
                                    <?php if ($editApId === (int)$r['ap_id'] && in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)): ?>
                                        <form method="post" style="display:inline-block;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="ap_id" value="<?php echo (int)$r['ap_id']; ?>">
                                            <select name="status" class="input" style="width:auto;">
                                                <option value="pending" <?php echo ($st==='pending')?'selected':''; ?>>Pending</option>
                                                <option value="paid" <?php echo ($st==='paid')?'selected':''; ?>>Paid</option>
                                                <option value="overdue" <?php echo ($st==='overdue')?'selected':''; ?>>Overdue</option>
                                            </select>
                                            <button class="btn-small" type="submit">Save</button>
                                            <a class="btn-small" href="accounts_payable.php">Cancel</a>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                                        <?php if (in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)): ?>
                                            <a class="btn-small" style="margin-left:8px;" href="accounts_payable.php?edit_ap_id=<?php echo (int)$r['ap_id']; ?>">Edit</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
							</tr>
							<?php endforeach; ?>
							<?php if (empty($ap)): ?><tr><td colspan="6">No accounts payable found.</td></tr><?php endif; ?>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="2"><strong>Total Pending</strong></td>
								<td><strong>₱<?php echo number_format($apTotal, 2); ?></strong></td>
								<td></td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td colspan="2"><strong>Total Paid</strong></td>
								<td><strong>₱<?php echo number_format($apPaidTotal, 2); ?></strong></td>
								<td></td>
								<td></td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>
		</main>
	</div>
	<script src="app.js"></script>
</body>
</html>


