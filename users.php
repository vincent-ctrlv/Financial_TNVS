<?php
require_once __DIR__ . '/db.php';

$rows = [
  [1,'fare',120.00,'Trip A','2025-10-08'],
  [2,'platform_fee',15.00,'Commission','2025-10-09'],
  [null,'payout',5000.00,'Payout','2025-10-01']
];

$stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?)");
foreach ($rows as $r) {
    [$uid,$type,$amt,$desc,$date] = $r;
    $stmt->bind_param('isdss', $uid, $type, $amt, $desc, $date);
    $stmt->execute();
    echo 'Inserted: ' . $mysqli->insert_id . PHP_EOL;
}
$stmt->close();
$mysqli->close();<?php
require_once __DIR__ . '/db.php';

$rows = [
  [1,'fare',120.00,'Trip A','2025-10-08'],
  [2,'platform_fee',15.00,'Commission','2025-10-09'],
  [null,'payout',5000.00,'Payout','2025-10-01']
];

$stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?)");
foreach ($rows as $r) {
    [$uid,$type,$amt,$desc,$date] = $r;
    $stmt->bind_param('isdss', $uid, $type, $amt, $desc, $date);
    $stmt->execute();
    echo 'Inserted: ' . $mysqli->insert_id . PHP_EOL;
}
$stmt->close();
$mysqli->close();<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>User - TNVS</title>
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
				<a class="nav" href="ledger.php"><span>General Ledger</span></a>
				<a class="nav" href="accounts_receivable.php"><span>Accounts Receivable</span></a>
				<a class="nav" href="accounts_payable.php"><span>Accounts Payable</span></a>
			</div>
			<div class="group">
				<h4>Financial</h4>
				<a class="nav" href="disbursement.php"><span>Disbursement</span></a>
				<a class="nav" href="collection.php"><span>Collection</span></a>
				<a class="nav" href="budget.php"><span>Budget</span></a>
			</div>
			<div class="group">
				<h4>Management</h4>
			</div>
			<div class="group">
				<h4>Tools</h4>
				<a class="nav" href="chat.php"><span>AI Chat</span></a>
			</div>
			<!-- Sidebar profile at bottom -->
			<div style="margin-top:auto;padding:16px;">
				<a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
					<?php
					$initial = strtoupper(substr((string)($user['name'] ?? 'U'),0,1));
					$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#d9d9d9"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#555">'.$initial.'</text></svg>';
					$src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
					?>
					<img src="<?php echo $src; ?>" alt="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;background:#ddd;flex:0 0 auto;" />
					<div style="font-size:14px;line-height:1;">
						<div style="font-weight:700"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
						<div style="font-size:12px;color:#666"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
					</div>
				</a>
			</div>
		</nav>
		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<h3>User Panel</h3>
				<p>You are logged in as: <strong><?php $r=$user['role']??'user'; echo htmlspecialchars($r==='finance_staff'?'employee':$r); ?></strong></p>
				<p>Go to <a href="dashboard.php">Dashboard</a>.</p>
			</section>
		</main>
	</div>
	<script src="app.js"></script>
</body>
</html>

