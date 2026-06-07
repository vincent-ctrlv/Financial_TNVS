<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('accounts_receivable');
require __DIR__ . '/../includes/db.php';
$user = current_user();

// Auto-update overdue statuses
$mysqli->query("UPDATE accountsreceivable SET status='overdue' WHERE status='pending' AND due_date < CURDATE()");




// Handle create, update, delete and mark-paid actions (admin and finance_staff)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)) {
    verify_csrf_or_die();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_paid') {
        $arId = (int)($_POST['ar_id'] ?? 0);
        if ($arId > 0) {
            $stmt = $mysqli->prepare("UPDATE accountsreceivable SET status='paid' WHERE ar_id=?");
            if ($stmt) { $stmt->bind_param('i', $arId); $stmt->execute(); $stmt->close(); }

            // Mirror paid record into disbursements
            if ($stmt2 = $mysqli->prepare("SELECT user_id, amount_due, COALESCE(method,'cash') AS method FROM accountsreceivable WHERE ar_id=? LIMIT 1")) {
                $stmt2->bind_param('i', $arId);
                $stmt2->execute();
                $row = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
                if ($row) {
                    insert_disbursement_for($mysqli, (int)$row['user_id'], (float)$row['amount_due'], (string)$row['method'], 'AR #'.$arId);
                }
            }
            log_audit_action($mysqli, 'mark_paid', 'accountsreceivable');
        }
    } elseif ($action === 'mark_pending') {
        $arId = (int)($_POST['ar_id'] ?? 0);
        if ($arId > 0) {
            $stmt = $mysqli->prepare("UPDATE accountsreceivable SET status='pending' WHERE ar_id=?");
            if ($stmt) { $stmt->bind_param('i', $arId); $stmt->execute(); $stmt->close(); }
            log_audit_action($mysqli, 'mark_pending', 'accountsreceivable');
        }
    } elseif ($action === 'update_status') {
        $arId = (int)($_POST['ar_id'] ?? 0);
        $newStatus = strtolower(trim($_POST['status'] ?? ''));
        if ($arId > 0 && in_array($newStatus, ['pending','paid','overdue'], true)) {
            $stmt = $mysqli->prepare("UPDATE accountsreceivable SET status=? WHERE ar_id=?");
            if ($stmt) { $stmt->bind_param('si', $newStatus, $arId); $stmt->execute(); $stmt->close(); }
            if ($newStatus === 'paid') {
                if ($stmt2 = $mysqli->prepare("SELECT user_id, amount_due, COALESCE(method,'cash') AS method FROM accountsreceivable WHERE ar_id=? LIMIT 1")) {
                    $stmt2->bind_param('i', $arId);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    $stmt2->close();
                    if ($row) {
                        insert_disbursement_for($mysqli, (int)$row['user_id'], (float)$row['amount_due'], (string)$row['method'], 'AR #'.$arId);
                    }
                }
            }
            log_audit_action($mysqli, 'update_status_' . $newStatus, 'accountsreceivable');
        }
    }
    // Post/Redirect/Get to avoid duplicate submissions and ensure fresh data
    header('Location: accounts_receivable.php');
    exit;
}


$ar = [];$arTotal = 0;$arPaidTotal = 0; $editArId = (int)($_GET['edit_ar_id'] ?? 0);
if ($res = $mysqli->query("SELECT ar.ar_id, ar.user_id, u.name AS user_name, ar.amount_due, ar.due_date, ar.method, ar.status FROM accountsreceivable ar LEFT JOIN users u ON u.user_id = ar.user_id ORDER BY ar.due_date DESC, ar.ar_id DESC LIMIT 100")) {
	while ($row = $res->fetch_assoc()) {
		$ar[] = $row;
		if (($row['status'] ?? '') === 'paid') {
			$arPaidTotal += (float)$row['amount_due'];
		} else {
			$arTotal += (float)$row['amount_due'];
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
	<title>Accounts Receivable - TNVS</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
</head>
<body>
	<div id="app" class="app">
		<?php include '../includes/header.php'; ?>
		<?php include '../includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>Accounts Receivable</h3>
				<div class="table-wrap">
					<table class="table">
						<thead><tr><th>ID</th><th>User</th><th>Amount Due</th><th>Due Date</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
						<tbody>
							<?php foreach ($ar as $r): ?>
							<tr>
								<td><?php echo (int)$r['ar_id']; ?></td>
								<td><?php echo htmlspecialchars(($r['user_name'] ?? '') !== '' ? $r['user_name'] : (string)(int)$r['user_id']); ?></td>
								<td>₱<?php echo number_format((float)$r['amount_due'], 2); ?></td>
								<td><?php echo htmlspecialchars($r['due_date']); ?></td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_',' ', (string)($r['method'] ?? '')))); ?></td>
                                <td>
                                    <?php $st = strtolower((string)($r['status'] ?? '')); $cls = $st === 'paid' ? 'status-paid' : ($st === 'overdue' ? 'status-overdue' : 'status-pending'); ?>
                                    <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($editArId === (int)$r['ar_id'] && in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)): ?>
                                        <form method="post" style="display:flex; gap:4px; justify-content: flex-start;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="ar_id" value="<?php echo (int)$r['ar_id']; ?>">
                                            <select name="status" class="input" style="width:auto; padding:4px 8px; font-size:12px;">
                                                <option value="pending" <?php echo ($st==='pending')?'selected':''; ?>>Pending</option>
                                                <option value="paid" <?php echo ($st==='paid')?'selected':''; ?>>Paid</option>
                                                <option value="overdue" <?php echo ($st==='overdue')?'selected':''; ?>>Overdue</option>
                                            </select>
                                            <button class="icon-btn" type="submit" title="Save" style="color:var(--success)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            </button>
                                            <a class="icon-btn" href="accounts_receivable.php" title="Cancel" style="color:var(--danger)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                            </a>
                                        </form>
                                    <?php else: ?>
                                        <?php if (in_array(($user['role'] ?? ''), ['admin','finance_staff'], true)): ?>
                                            <a class="icon-btn" href="accounts_receivable.php?edit_ar_id=<?php echo (int)$r['ar_id']; ?>" title="Edit Status">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
							</tr>
							<?php endforeach; ?>
							<?php if (empty($ar)): ?><tr><td colspan="6">No accounts receivable found.</td></tr><?php endif; ?>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="2"><strong>Total Pending</strong></td>
								<td><strong>₱<?php echo number_format($arTotal, 2); ?></strong></td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td colspan="2"><strong>Total Paid</strong></td>
								<td><strong>₱<?php echo number_format($arPaidTotal, 2); ?></strong></td>
								<td></td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>
		</main>
	</div>
	<script src="../assets/js/app.js"></script>
</body>
</html>


