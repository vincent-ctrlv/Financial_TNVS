<?php
// Basic PHP dashboard that reads from the `finance` schema
require_once __DIR__ . '/includes/auth.php';
require_login();
require_access('dashboard');
require __DIR__ . '/includes/db.php';
$user = current_user();

if ($user['role'] === 'driver') {
    $driverId = (int)$user['id'];
    
    // Fetch driver details
    $driverData = null;
    $stmt = $mysqli->prepare("SELECT name, email, avatar, plate_no, reference_no, address, created_at FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $driverData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    // Total Revenue (Fares + Incentives)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type IN ('fare', 'incentive')");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($totalRevenue);
        $stmt->fetch();
        $stmt->close();
    } else {
        $totalRevenue = 0.0;
    }

    // Total Expenses (Fuel + Lease + Platform Fee)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type IN ('fuel', 'lease', 'platform_fee')");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($totalExpenses);
        $stmt->fetch();
        $stmt->close();
    } else {
        $totalExpenses = 0.0;
    }
    
    // Net Income
    $netIncome = $totalRevenue - $totalExpenses;
    
    // Total Payouts Received (Cash-outs)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'payout'");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($totalPayouts);
        $stmt->fetch();
        $stmt->close();
    } else {
        $totalPayouts = 0.0;
    }

    // AR/AP Pending & Paid
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) FROM accountspayable WHERE user_id = ? AND status='pending'");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($apPending);
        $stmt->fetch();
        $stmt->close();
    } else {
        $apPending = 0.0;
    }

    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) FROM accountsreceivable WHERE user_id = ? AND status IN ('pending','overdue')");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($arPending);
        $stmt->fetch();
        $stmt->close();
    } else {
        $arPending = 0.0;
    }

    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) FROM accountsreceivable WHERE user_id = ? AND status='paid'");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($arPaid);
        $stmt->fetch();
        $stmt->close();
    } else {
        $arPaid = 0.0;
    }

    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) FROM accountspayable WHERE user_id = ? AND status='paid'");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $stmt->bind_result($apPaid);
        $stmt->fetch();
        $stmt->close();
    } else {
        $apPaid = 0.0;
    }

    // AR/AP status totals for progress bar breakdown
    $arTotalsByStatus = ['pending'=>0.0, 'overdue'=>0.0, 'paid'=>0.0];
    $stmt = $mysqli->prepare("SELECT status, COALESCE(SUM(amount_due),0) FROM accountsreceivable WHERE user_id = ? GROUP BY status");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_row()) {
            $arTotalsByStatus[strtolower($row[0])] = (float)$row[1];
        }
        $stmt->close();
    }

    $apTotalsByStatus = ['pending'=>0.0, 'paid'=>0.0];
    $stmt = $mysqli->prepare("SELECT status, COALESCE(SUM(amount_due),0) FROM accountspayable WHERE user_id = ? GROUP BY status");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_row()) {
            $apTotalsByStatus[strtolower($row[0])] = (float)$row[1];
        }
        $stmt->close();
    }

    // Recent Transactions
    $recent = [];
    $stmt = $mysqli->prepare("SELECT transaction_id as id, type, amount, transaction_date as date, 'transaction' as source FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC, transaction_id DESC LIMIT 10");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $recent[] = $row;
        }
        $stmt->close();
    }

    // Driver Chart Data - Last 30 Days Performance
    $driverDates = [];
    $driverRevenues = [];
    $driverExpenses = [];

    $stmt = $mysqli->prepare("
        SELECT 
            transaction_date,
            SUM(CASE WHEN type IN ('fare', 'incentive') THEN amount ELSE 0 END) as revenue,
            SUM(CASE WHEN type IN ('fuel', 'lease', 'platform_fee') THEN amount ELSE 0 END) as expenses
        FROM transactions 
        WHERE user_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY transaction_date 
        ORDER BY transaction_date ASC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $driverDates[] = date('M d', strtotime($row['transaction_date']));
            $driverRevenues[] = (float)$row['revenue'];
            $driverExpenses[] = (float)$row['expenses'];
        }
        $stmt->close();
    }

    // Fallback if no records exist in last 30 days
    if (empty($driverDates)) {
        for ($i = 6; $i >= 0; $i--) {
            $driverDates[] = date('M d', strtotime("-$i days"));
            $driverRevenues[] = 0.0;
            $driverExpenses[] = 0.0;
        }
    }

    // Driver Share Breakdown
    $driverShareLabels = [];
    $driverShareValues = [];
    $stmt = $mysqli->prepare("
        SELECT type, SUM(amount) as total 
        FROM transactions 
        WHERE user_id = ?
        GROUP BY type
    ");
    if ($stmt) {
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $driverShareLabels[] = ucfirst(str_replace('_', ' ', $row['type']));
            $driverShareValues[] = (float)$row['total'];
        }
        $stmt->close();
    }

    if (empty($driverShareValues)) {
        $driverShareLabels = ['Fares', 'Incentives', 'Fuel', 'Lease', 'Platform Commission'];
        $driverShareValues = [0, 0, 0, 0, 0];
    }
} else {
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
        $col = 'amount_due';
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

    // Admin Chart Data - Overall Performance (Last 30 Days)
    $adminDates = [];
    $adminRevenues = [];
    $adminExpenses = [];

    $res = $mysqli->query("
        SELECT 
            transaction_date,
            SUM(CASE WHEN type = 'fare' THEN amount ELSE 0 END) as revenue,
            SUM(CASE WHEN type IN ('fuel', 'lease', 'platform_fee', 'incentive', 'payout') THEN amount ELSE 0 END) as expenses
        FROM transactions 
        WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY transaction_date 
        ORDER BY transaction_date ASC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $adminDates[] = date('M d', strtotime($row['transaction_date']));
            $adminRevenues[] = (float)$row['revenue'];
            $adminExpenses[] = (float)$row['expenses'];
        }
        $res->free();
    }

    // Fallback if no records exist
    if (empty($adminDates)) {
        for ($i = 6; $i >= 0; $i--) {
            $adminDates[] = date('M d', strtotime("-$i days"));
            $adminRevenues[] = 0.0;
            $adminExpenses[] = 0.0;
        }
    }

    // Admin Share Breakdown
    $adminShareLabels = [];
    $adminShareValues = [];
    $res = $mysqli->query("
        SELECT type, SUM(amount) as total 
        FROM transactions 
        GROUP BY type
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $adminShareLabels[] = ucfirst(str_replace('_', ' ', $row['type']));
            $adminShareValues[] = (float)$row['total'];
        }
        $res->free();
    }

    if (empty($adminShareValues)) {
        $adminShareLabels = ['Fare', 'Platform Fee', 'Fuel', 'Lease', 'Incentive', 'Payout'];
        $adminShareValues = [0, 0, 0, 0, 0, 0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TNVS Dashboard</title>
	<link rel="stylesheet" href="assets/css/style.css?v=1.0.5">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
	<div id="app" class="app">
		<?php include 'includes/header.php'; ?>
		<?php include 'includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>

		<main class="content" id="mainContent">
			<section id="dashboard" class="section active">
				<?php if ($user['role'] === 'driver'): ?>
				<!-- ===== DRIVER PORTAL STYLES ===== -->
				<style>
				/* ── Driver Banner ── */
				.driver-banner-card {
					display: flex;
					align-items: center;
					gap: 28px;
					padding: 28px 32px;
					background: linear-gradient(135deg, rgba(40,167,69,0.07) 0%, rgba(32,201,151,0.04) 100%);
					border-radius: 24px;
					border: 1px solid rgba(40,167,69,0.18);
					margin-bottom: 28px;
					box-shadow: 0 4px 20px rgba(40,167,69,0.08);
					animation: cardSlideIn 0.5s ease both;
					position: relative;
					overflow: hidden;
				}
				.driver-banner-card::before {
					content: '';
					position: absolute;
					top: 0; left: 0; right: 0;
					height: 3px;
					background: var(--gradient-primary);
				}
				.driver-avatar-ring {
					width: 88px; height: 88px;
					border-radius: 50%;
					overflow: hidden;
					border: 3px solid rgba(40,167,69,0.4);
					box-shadow: 0 0 0 6px rgba(40,167,69,0.1);
					flex-shrink: 0;
					background: rgba(40,167,69,0.05);
					transition: box-shadow 0.3s ease;
				}
				.driver-avatar-ring:hover {
					box-shadow: 0 0 0 8px rgba(40,167,69,0.18);
				}
				.driver-avatar-ring img { width:100%; height:100%; object-fit:cover; }
				.driver-portal-badge {
					background: var(--gradient-primary);
					color: white;
					padding: 7px 18px;
					border-radius: 30px;
					font-size: 11px;
					font-weight: 800;
					text-transform: uppercase;
					letter-spacing: 1.5px;
					box-shadow: 0 4px 15px rgba(40,167,69,0.35);
				}
				.driver-info-chip {
					display: inline-flex;
					align-items: center;
					gap: 7px;
					background: white;
					padding: 8px 18px;
					border-radius: 30px;
					font-size: 13px;
					font-weight: 600;
					color: var(--text);
					box-shadow: 0 2px 12px rgba(0,0,0,0.06);
					border: 1px solid rgba(0,0,0,0.06);
					transition: all 0.2s ease;
				}
				.driver-info-chip:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(40,167,69,0.12); }

				/* ── Hero Carousel ── */
				.hero-carousel {
					position: relative;
					border-radius: 20px;
					overflow: hidden;
					margin-bottom: 32px;
					box-shadow: 0 12px 40px rgba(0,0,0,0.18);
					animation: cardSlideIn 0.6s ease 0.1s both;
					background: #111;
				}
				.hero-carousel-track {
					display: flex;
					transition: transform 0.6s cubic-bezier(0.4,0,0.2,1);
					height: 340px;
				}
				.hero-slide {
					min-width: 100%;
					position: relative;
					overflow: hidden;
				}
				.hero-slide img {
					width: 100%; height: 100%;
					object-fit: cover;
					display: block;
					transition: transform 8s ease;
				}
				.hero-slide.active img { transform: scale(1.04); }
				.hero-slide-overlay {
					position: absolute;
					inset: 0;
					background: linear-gradient(to top, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.1) 50%, transparent 100%);
				}
				.hero-slide-caption {
					position: absolute;
					bottom: 28px; left: 36px; right: 36px;
					color: white;
					animation: captionSlide 0.5s ease;
				}
				.hero-slide-caption h3 {
					font-size: 22px; font-weight: 800;
					margin: 0 0 6px;
					background: none;
					-webkit-text-fill-color: white;
					color: white;
					text-shadow: 0 2px 12px rgba(0,0,0,0.4);
				}
				.hero-slide-caption p {
					font-size: 14px; opacity: 0.85;
					margin: 0;
					color: rgba(255,255,255,0.9);
				}
				@keyframes captionSlide {
					from { opacity:0; transform:translateY(12px); }
					to { opacity:1; transform:translateY(0); }
				}
				.carousel-btn {
					position: absolute;
					top: 50%; transform: translateY(-50%);
					width: 44px; height: 44px;
					border-radius: 50%;
					border: none;
					background: rgba(255,255,255,0.18);
					backdrop-filter: blur(8px);
					color: white;
					font-size: 20px;
					cursor: pointer;
					display: flex; align-items: center; justify-content: center;
					transition: all 0.2s ease;
					z-index: 10;
					box-shadow: 0 4px 15px rgba(0,0,0,0.2);
				}
				.carousel-btn:hover { background: rgba(255,255,255,0.32); transform: translateY(-50%) scale(1.1); }
				.carousel-btn.prev { left: 16px; }
				.carousel-btn.next { right: 16px; }
				.carousel-dots {
					position: absolute;
					bottom: 16px; left: 50%; transform: translateX(-50%);
					display: flex; gap: 8px; z-index: 10;
				}
				.carousel-dot {
					width: 10px; height: 10px;
					border-radius: 50%;
					background: rgba(255,255,255,0.45);
					cursor: pointer;
					transition: all 0.3s ease;
					border: none;
				}
				.carousel-dot.active {
					background: var(--primary);
					box-shadow: 0 0 8px rgba(40,167,69,0.6);
					width: 28px; border-radius: 5px;
				}

				/* ── Financial Cards ── */
				.driver-kpi-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 16px;
					margin-bottom: 28px;
				}
				.driver-kpi-card {
					background: white;
					border-radius: 20px;
					padding: 24px 22px 20px;
					border: 1px solid rgba(0,0,0,0.05);
					box-shadow: 0 2px 12px rgba(0,0,0,0.05);
					transition: all 0.22s ease;
					position: relative;
					overflow: hidden;
					animation: cardSlideIn 0.5s ease both;
				}
				.driver-kpi-card::after {
					content: '';
					position: absolute;
					bottom: 0; left: 0; right: 0;
					height: 3px;
					border-radius: 0 0 20px 20px;
					background: var(--gradient-primary);
					opacity: 0;
					transition: opacity 0.2s;
				}
				.driver-kpi-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
				.driver-kpi-card:hover::after { opacity: 1; }
				.driver-kpi-card h4 {
					font-size: 11px; font-weight: 700;
					text-transform: uppercase; letter-spacing: 1.5px;
					color: var(--muted); margin: 0 0 14px;
				}
				.driver-kpi-card .kpi-value {
					font-size: 28px; font-weight: 900;
					letter-spacing: -1.5px; margin-bottom: 6px;
					color: var(--primary);
					animation: numberCount 2s ease-out;
				}
				.driver-kpi-card .kpi-label {
					font-size: 12px; font-weight: 600;
				}
				.kpi-green .kpi-value { color: var(--success); text-shadow: 0 0 20px rgba(40,167,69,0.3); }
				.kpi-green .kpi-label { color: var(--success); }
				.kpi-yellow .kpi-value { color: #d4920a; }
				.kpi-yellow .kpi-label { color: #d4920a; }
				.kpi-red .kpi-value { color: var(--danger); }
				.kpi-red .kpi-label { color: var(--danger); }
				.kpi-blue .kpi-value { color: var(--info); }
				.kpi-blue .kpi-label { color: var(--info); }

				/* ── Recent Transactions ── */
				.driver-tx-card {
					background: white;
					border-radius: 20px;
					padding: 28px;
					border: 1px solid rgba(0,0,0,0.05);
					box-shadow: 0 2px 12px rgba(0,0,0,0.05);
					margin-bottom: 28px;
					animation: cardSlideIn 0.6s ease 0.3s both;
				}
				.driver-tx-card h4 {
					font-size: 16px; font-weight: 800;
					color: var(--text);
					margin: 0 0 20px;
					display: flex; align-items: center; gap: 10px;
				}
				.driver-tx-card h4::before {
					content: '';
					width: 4px; height: 20px;
					background: var(--gradient-primary);
					border-radius: 2px;
					display: inline-block;
				}

				/* ── Statement Breakdown ── */
				.driver-breakdown-card {
					background: white;
					border-radius: 20px;
					padding: 28px;
					border: 1px solid rgba(0,0,0,0.05);
					box-shadow: 0 2px 12px rgba(0,0,0,0.05);
					margin-bottom: 28px;
					animation: cardSlideIn 0.6s ease 0.2s both;
				}
				</style>

				<!-- ===== DRIVER PORTAL HTML ===== -->
				<?php
				$u_avatar = $driverData['avatar'] ?? '';
				if ($u_avatar && file_exists(dirname(__DIR__) . '/' . $u_avatar)) {
					$avatarSrc = $u_avatar;
				} else {
					$u_initial = strtoupper(substr((string)($driverData['name'] ?? 'Driver'), 0, 1));
					$u_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#28a745" fill-opacity="0.1"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#28a745" font-weight="800">'.$u_initial.'</text></svg>';
					$avatarSrc = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($u_svg);
				}
				?>

				<!-- Profile Banner -->
				<div class="driver-banner-card">
					<div class="driver-avatar-ring">
						<img src="<?php echo $avatarSrc; ?>" alt="Driver Avatar">
					</div>
					<div style="flex:1; min-width:0;">
						<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
							<div>
								<h2 style="font-size:26px; font-weight:900; color:var(--primary); margin:0 0 5px; letter-spacing:-0.5px;">Welcome Back, <?php echo htmlspecialchars($driverData['name'] ?? 'Driver'); ?>!</h2>
								<p style="color:var(--muted); font-size:13px; margin:0;">Registered since <?php echo date('F Y', strtotime($driverData['created_at'])); ?></p>
							</div>
							<span class="driver-portal-badge">Driver Portal</span>
						</div>
						<div style="display:flex; flex-wrap:wrap; gap:10px;">
							<span class="driver-info-chip">🚗 Plate No: <strong style="color:var(--primary);"><?php echo htmlspecialchars($driverData['plate_no'] ?? 'N/A'); ?></strong></span>
							<span class="driver-info-chip">🔑 Ref ID: <strong style="color:var(--primary);"><?php echo htmlspecialchars($driverData['reference_no'] ?? 'N/A'); ?></strong></span>
							<span class="driver-info-chip">📍 <?php echo htmlspecialchars($driverData['address'] ?? 'N/A'); ?></span>
						</div>
					</div>
				</div>

				<!-- Hero Image Carousel -->
				<div class="hero-carousel" id="heroCarousel">
					<div class="hero-carousel-track" id="carouselTrack">
						<div class="hero-slide active">
							<img src="assets/img/driver_hero1.png" alt="Driver on the road" id="slide0img">
							<div class="hero-slide-overlay"></div>
							<div class="hero-slide-caption">
								<h3>Your Journey, Your Earnings</h3>
								<p>Track your income, expenses, and payouts all in one place</p>
							</div>
						</div>
						<div class="hero-slide">
							<img src="assets/img/driver_hero2.png" alt="Smart vehicle technology">
							<div class="hero-slide-overlay"></div>
							<div class="hero-slide-caption">
								<h3>Powered by Smart Technology</h3>
								<p>Real-time financial tracking for the modern driver</p>
							</div>
						</div>
						<div class="hero-slide">
							<img src="assets/img/driver_hero3.png" alt="Sustainable driving">
							<div class="hero-slide-overlay"></div>
							<div class="hero-slide-caption">
								<h3>Drive Into the Future</h3>
								<p>SakaySaya – Keeping you informed and ahead of the curve</p>
							</div>
						</div>
					</div>

					<button class="carousel-btn prev" onclick="carouselMove(-1)" aria-label="Previous">&#8249;</button>
					<button class="carousel-btn next" onclick="carouselMove(1)" aria-label="Next">&#8250;</button>

					<div class="carousel-dots">
						<button class="carousel-dot active" onclick="carouselGo(0)" id="dot0"></button>
						<button class="carousel-dot" onclick="carouselGo(1)" id="dot1"></button>
						<button class="carousel-dot" onclick="carouselGo(2)" id="dot2"></button>
					</div>
				</div>

				<!-- Financial KPIs -->
				<h3 style="margin-bottom:20px;">My Financials Overview</h3>
				<div class="driver-kpi-grid">
					<div class="driver-kpi-card kpi-green">
						<h4>Gross Earnings</h4>
						<div class="kpi-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
						<div class="kpi-label">Fares &amp; Incentives</div>
					</div>
					<div class="driver-kpi-card kpi-yellow">
						<h4>Total Expenses</h4>
						<div class="kpi-value" style="color:#d4920a;">₱<?php echo number_format($totalExpenses, 2); ?></div>
						<div class="kpi-label" style="color:#d4920a;">Lease, Fuel &amp; Fees</div>
					</div>
					<div class="driver-kpi-card <?php echo $netIncome >= 0 ? 'kpi-green' : 'kpi-red'; ?>">
						<h4>Net Take-Home</h4>
						<div class="kpi-value">₱<?php echo number_format($netIncome, 2); ?></div>
						<div class="kpi-label"><?php echo $netIncome >= 0 ? 'Positive Balance' : 'Negative Balance'; ?></div>
					</div>
					<div class="driver-kpi-card kpi-blue">
						<h4>Total Payouts</h4>
						<div class="kpi-value" style="color:var(--info);">₱<?php echo number_format($totalPayouts, 2); ?></div>
						<div class="kpi-label" style="color:var(--info);">Cash Out Paid</div>
					</div>
					<div class="driver-kpi-card">
						<h4>Receivables (AR)</h4>
						<div class="kpi-value">₱<?php echo number_format($arPending, 2); ?></div>
						<div class="kpi-label" style="color:var(--muted);">To Be Received</div>
					</div>
					<div class="driver-kpi-card kpi-red">
						<h4>Payables (AP)</h4>
						<div class="kpi-value">₱<?php echo number_format($apPending, 2); ?></div>
						<div class="kpi-label">To Be Paid</div>
					</div>
				</div>

				<!-- Interactive Charts -->
				<div class="analytics" style="margin-bottom: 28px;">
					<div class="chart-card">
						<h4>My Performance (Last 30 Days)</h4>
						<canvas id="driverPerformanceChart" style="max-height: 320px;"></canvas>
					</div>
					<div class="chart-card">
						<h4>Income Breakdown</h4>
						<canvas id="driverIncomeShareChart" style="max-height: 320px;"></canvas>
					</div>
				</div>

				<!-- Statement Breakdown -->
				<div class="driver-breakdown-card">
					<h4 style="font-size:16px; font-weight:800; color:var(--text); margin:0 0 20px; display:flex; align-items:center; gap:10px;">
						<span style="width:4px;height:20px;background:var(--gradient-primary);border-radius:2px;display:inline-block;"></span>
						Personal Statement Breakdown
					</h4>
					<div class="breakdown">
						<?php
						$arPend = (float)($arTotalsByStatus['pending']??0)+(float)($arTotalsByStatus['overdue']??0);
						$arTotal = (float)array_sum($arTotalsByStatus);
						$w = $arTotal>0?min(100,max(5,round(($arPend/$arTotal)*100))):10;
						?>
						<div class="breakdown-row">
							<div class="label">Receivables Pending</div>
							<div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
							<div class="value">₱<?php echo number_format($arPend,2); ?></div>
						</div>
						<?php $v=(float)($arTotalsByStatus['paid']??0); $w=$arTotal>0?min(100,max(5,round(($v/$arTotal)*100))):10; ?>
						<div class="breakdown-row">
							<div class="label">Receivables Received</div>
							<div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
							<div class="value">₱<?php echo number_format($v,2); ?></div>
						</div>
						<?php $apTotal=(float)array_sum($apTotalsByStatus); $v=(float)($apTotalsByStatus['pending']??0); $w=$apTotal>0?min(100,max(5,round(($v/$apTotal)*100))):10; ?>
						<div class="breakdown-row">
							<div class="label">Payables Pending</div>
							<div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
							<div class="value">₱<?php echo number_format($v,2); ?></div>
						</div>
						<?php $v=(float)($apTotalsByStatus['paid']??0); $w=$apTotal>0?min(100,max(5,round(($v/$apTotal)*100))):10; ?>
						<div class="breakdown-row">
							<div class="label">Payables Paid</div>
							<div class="barline"><span style="--final-width: <?php echo $w; ?>%"></span></div>
							<div class="value">₱<?php echo number_format($v,2); ?></div>
						</div>
					</div>
				</div>

				<!-- Recent Transactions -->
				<div class="driver-tx-card">
					<h4>My Recent Transactions</h4>
					<div class="table-wrap">
						<table class="table" id="txTable">
							<thead>
								<tr>
									<th>ID</th>
									<th>Transaction Type</th>
									<th>Amount</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($recent as $r): ?>
								<tr>
									<td><?php echo (int)$r['id']; ?></td>
									<td>
										<?php
										$t_type = $r['type'];
										$badgeClass = '';
										$labelText = '';
										if ($t_type === 'fare') {
											$badgeClass = 'status-paid'; $labelText = '🚗 Fare Earned';
										} elseif ($t_type === 'incentive') {
											$badgeClass = 'status-paid'; $labelText = '🎁 Incentive Bonus';
										} elseif ($t_type === 'fuel') {
											$badgeClass = 'status-overdue'; $labelText = '⛽ Fuel Charge';
										} elseif ($t_type === 'lease') {
											$badgeClass = 'status-overdue'; $labelText = '🔑 Lease Payment';
										} elseif ($t_type === 'platform_fee') {
											$badgeClass = 'status-overdue'; $labelText = '📱 Platform Commission';
										} elseif ($t_type === 'payout') {
											$badgeClass = 'badge-staff'; $labelText = '💰 Cash Out Payout';
										} else {
											$badgeClass = 'status-pending'; $labelText = htmlspecialchars(ucfirst($t_type));
										}
										?>
										<span class="badge <?php echo $badgeClass; ?>"><?php echo $labelText; ?></span>
									</td>
									<?php
									$amount = (float)$r['amount'];
									$isExpense = in_array($t_type, ['fuel', 'lease', 'platform_fee'], true);
									$colorStyle = $isExpense ? 'color:var(--danger);font-weight:700;' : ($t_type === 'payout' ? 'color:var(--info);font-weight:700;' : 'color:var(--success);font-weight:700;');
									$prefix = $isExpense ? '- ₱' : ($t_type === 'payout' ? '₱' : '+ ₱');
									?>
									<td style="<?php echo $colorStyle; ?>"><?php echo $prefix . number_format($amount, 2); ?></td>
									<td style="color:var(--muted); font-size:13px;"><?php echo date('M d, Y', strtotime($r['date'])); ?></td>
								</tr>
								<?php endforeach; ?>
								<?php if (empty($recent)): ?>
								<tr><td colspan="4" style="text-align:center; color:var(--muted); padding:32px;">No transactions found yet.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Carousel JS -->
				<script>
				(function() {
					var current = 0;
					var total = 3;
					var timer;

					function update() {
						var track = document.getElementById('carouselTrack');
						if (!track) return;
						track.style.transform = 'translateX(-' + (current * 100) + '%)';
						// Update dots
						for (var i = 0; i < total; i++) {
							var dot = document.getElementById('dot' + i);
							if (dot) dot.className = 'carousel-dot' + (i === current ? ' active' : '');
						}
						// Update active slide for zoom effect
						var slides = track.querySelectorAll('.hero-slide');
						slides.forEach(function(s, idx) {
							s.classList.toggle('active', idx === current);
						});
					}

					window.carouselMove = function(dir) {
						current = (current + dir + total) % total;
						update();
						resetTimer();
					};

					window.carouselGo = function(idx) {
						current = idx;
						update();
						resetTimer();
					};

					function resetTimer() {
						clearInterval(timer);
						timer = setInterval(function() {
							current = (current + 1) % total;
							update();
						}, 5000);
					}

					resetTimer();
				})();
				</script>

				<?php else: ?>
					<!-- Admin / Finance Staff Dashboard -->
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

					<!-- Interactive Charts -->
					<div class="analytics" style="margin-bottom: 32px;">
						<div class="chart-card">
							<h4>Financial Performance (Last 30 Days)</h4>
							<canvas id="adminPerformanceChart" style="max-height: 320px;"></canvas>
						</div>
						<div class="chart-card">
							<h4>Transaction Share</h4>
							<canvas id="adminShareChart" style="max-height: 320px;"></canvas>
						</div>
					</div>

					<!-- Detailed Ledger & Breakdowns -->
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
						<div class="chart-card">
							<h4>AR/AP Status Breakdown</h4>
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
				<?php endif; ?>
			</section>
		</main>
	</div>
	<script src="assets/js/app.js"></script>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		// Common configurations & font settings
		Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
		Chart.defaults.color = '#718096'; // Muted labels
		
		<?php if ($user['role'] === 'driver'): ?>
		// --- DRIVER CHARTS ---
		
		// 1. Performance Line Chart (Earnings vs Expenses)
		var driverCtx = document.getElementById('driverPerformanceChart');
		if (driverCtx) {
			new Chart(driverCtx.getContext('2d'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode($driverDates); ?>,
					datasets: [
						{
							label: 'Earnings (Fare + Incentive)',
							data: <?php echo json_encode($driverRevenues); ?>,
							borderColor: '#28a745',
							backgroundColor: 'rgba(40, 167, 69, 0.05)',
							borderWidth: 3,
							fill: true,
							tension: 0.35,
							pointRadius: 4,
							pointBackgroundColor: '#28a745',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 2,
							pointHoverRadius: 6
						},
						{
							label: 'Expenses (Lease, Fuel, Fees)',
							data: <?php echo json_encode($driverExpenses); ?>,
							borderColor: '#dc3545',
							backgroundColor: 'rgba(220, 53, 69, 0.05)',
							borderWidth: 3,
							fill: true,
							tension: 0.35,
							pointRadius: 4,
							pointBackgroundColor: '#dc3545',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 2,
							pointHoverRadius: 6
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'top',
							labels: {
								boxWidth: 12,
								padding: 16,
								font: { weight: '600', size: 12 }
							}
						},
						tooltip: {
							padding: 12,
							cornerRadius: 8,
							backgroundColor: 'rgba(15, 23, 42, 0.9)',
							titleFont: { size: 13, weight: '700' },
							bodyFont: { size: 13 },
							callbacks: {
								label: function(context) {
									return ' ' + context.dataset.label + ': ₱' + Number(context.raw).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
								}
							}
						}
					},
					scales: {
						y: {
							grid: { color: 'rgba(0, 0, 0, 0.04)' },
							ticks: {
								callback: function(value) { return '₱' + value; },
								font: { weight: '600' }
							}
						},
						x: {
							grid: { display: false }
						}
					}
				}
			});
		}

		// 2. Share Breakdown Doughnut Chart
		var driverShareCtx = document.getElementById('driverIncomeShareChart');
		if (driverShareCtx) {
			new Chart(driverShareCtx.getContext('2d'), {
				type: 'doughnut',
				data: {
					labels: <?php echo json_encode($driverShareLabels); ?>,
					datasets: [{
						data: <?php echo json_encode($driverShareValues); ?>,
						backgroundColor: [
							'#28a745', // Fare
							'#20c997', // Incentive
							'#dc3545', // Fuel
							'#ffc107', // Lease
							'#fd7e14'  // Platform Fee
						],
						borderWidth: 2,
						borderColor: '#ffffff',
						hoverOffset: 6
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 12,
								padding: 12,
								font: { weight: '600', size: 11 }
							}
						},
						tooltip: {
							padding: 12,
							cornerRadius: 8,
							backgroundColor: 'rgba(15, 23, 42, 0.9)',
							callbacks: {
								label: function(context) {
									var total = context.dataset.data.reduce((a, b) => a + b, 0);
									var val = context.raw;
									var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
									return ' ' + context.label + ': ₱' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + pct + '%)';
								}
							}
						}
					},
					cutout: '65%'
				}
			});
		}
		
		<?php else: ?>
		// --- ADMIN CHARTS ---
		
		// 1. Performance Line Chart (Revenue vs Expenses)
		var adminCtx = document.getElementById('adminPerformanceChart');
		if (adminCtx) {
			new Chart(adminCtx.getContext('2d'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode($adminDates); ?>,
					datasets: [
						{
							label: 'Total Revenue (Fares)',
							data: <?php echo json_encode($adminRevenues); ?>,
							borderColor: '#28a745',
							backgroundColor: 'rgba(40, 167, 69, 0.05)',
							borderWidth: 3,
							fill: true,
							tension: 0.35,
							pointRadius: 4,
							pointBackgroundColor: '#28a745',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 2,
							pointHoverRadius: 6
						},
						{
							label: 'Total Expenses',
							data: <?php echo json_encode($adminExpenses); ?>,
							borderColor: '#dc3545',
							backgroundColor: 'rgba(220, 53, 69, 0.05)',
							borderWidth: 3,
							fill: true,
							tension: 0.35,
							pointRadius: 4,
							pointBackgroundColor: '#dc3545',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 2,
							pointHoverRadius: 6
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'top',
							labels: {
								boxWidth: 12,
								padding: 16,
								font: { weight: '600', size: 12 }
							}
						},
						tooltip: {
							padding: 12,
							cornerRadius: 8,
							backgroundColor: 'rgba(15, 23, 42, 0.9)',
							titleFont: { size: 13, weight: '700' },
							bodyFont: { size: 13 },
							callbacks: {
								label: function(context) {
									return ' ' + context.dataset.label + ': ₱' + Number(context.raw).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
								}
							}
						}
					},
					scales: {
						y: {
							grid: { color: 'rgba(0, 0, 0, 0.04)' },
							ticks: {
								callback: function(value) { return '₱' + value; },
								font: { weight: '600' }
							}
						},
						x: {
							grid: { display: false }
						}
					}
				}
			});
		}

		// 2. Share Breakdown Doughnut Chart
		var adminShareCtx = document.getElementById('adminShareChart');
		if (adminShareCtx) {
			new Chart(adminShareCtx.getContext('2d'), {
				type: 'doughnut',
				data: {
					labels: <?php echo json_encode($adminShareLabels); ?>,
					datasets: [{
						data: <?php echo json_encode($adminShareValues); ?>,
						backgroundColor: [
							'#28a745', // Fare (Green)
							'#fd7e14', // Platform Fee (Orange)
							'#dc3545', // Fuel (Red)
							'#ffc107', // Lease (Yellow)
							'#20c997', // Incentive (Teal)
							'#17a2b8'  // Payout (Cyan)
						],
						borderWidth: 2,
						borderColor: '#ffffff',
						hoverOffset: 6
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 12,
								padding: 12,
								font: { weight: '600', size: 11 }
							}
						},
						tooltip: {
							padding: 12,
							cornerRadius: 8,
							backgroundColor: 'rgba(15, 23, 42, 0.9)',
							callbacks: {
								label: function(context) {
									var total = context.dataset.data.reduce((a, b) => a + b, 0);
									var val = context.raw;
									var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
									return ' ' + context.label + ': ₱' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + pct + '%)';
								}
							}
						}
					},
					cutout: '65%'
				}
			});
		}
		<?php endif; ?>
	});
	</script>
</body>
</html>

