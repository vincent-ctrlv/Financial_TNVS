<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();
require_access('my_finances');
$sessionUser = current_user();
$uid  = (int)($sessionUser['id'] ?? 0);
$role = $sessionUser['role'] ?? '';

// ---------- Aggregate Summaries ----------
// AR
$arTotal = $arPending = $arPaid = $arOverdue = 0.0;
if ($stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) AS total, status FROM accountsreceivable WHERE user_id=? GROUP BY status")) {
    $stmt->bind_param('i', $uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $arTotal += (float)$r['total'];
        if ($r['status'] === 'pending') $arPending = (float)$r['total'];
        if ($r['status'] === 'paid')    $arPaid    = (float)$r['total'];
        if ($r['status'] === 'overdue') $arOverdue = (float)$r['total'];
    }
    $stmt->close();
}

// AP
$apTotal = $apPending = $apPaid = $apOverdue = 0.0;
if ($stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_due),0) AS total, status FROM accountspayable WHERE user_id=? GROUP BY status")) {
    $stmt->bind_param('i', $uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $apTotal += (float)$r['total'];
        if ($r['status'] === 'pending') $apPending = (float)$r['total'];
        if ($r['status'] === 'paid')    $apPaid    = (float)$r['total'];
        if ($r['status'] === 'overdue') $apOverdue = (float)$r['total'];
    }
    $stmt->close();
}

// Disbursements
$disbTotal = 0.0;
if ($stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM disbursements WHERE user_id=?")) {
    $stmt->bind_param('i', $uid); $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $disbTotal = (float)($r['total'] ?? 0);
    $stmt->close();
}

// ---------- Recent AR ----------
$recentAR = [];
if ($stmt = $mysqli->prepare("SELECT ar_id, amount_due, due_date, status, method FROM accountsreceivable WHERE user_id=? ORDER BY due_date DESC LIMIT 8")) {
    $stmt->bind_param('i',$uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $recentAR[] = $r; }
    $stmt->close();
}

// ---------- Recent AP ----------
$recentAP = [];
if ($stmt = $mysqli->prepare("SELECT ap_id, amount_due, due_date, status, method FROM accountspayable WHERE user_id=? ORDER BY due_date DESC LIMIT 8")) {
    $stmt->bind_param('i',$uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $recentAP[] = $r; }
    $stmt->close();
}

// ---------- Recent Disbursements ----------
$recentDisb = [];
if ($stmt = $mysqli->prepare("SELECT disbursement_id, amount, method, disbursement_date, remarks FROM disbursements WHERE user_id=? ORDER BY disbursement_date DESC LIMIT 8")) {
    $stmt->bind_param('i',$uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $recentDisb[] = $r; }
    $stmt->close();
}

function fmt(float $v): string { return '₱ ' . number_format($v, 2); }
function statusBadge(string $s): string {
    $cfg = [
        'pending' => ['#f59e0b','#fffbeb'],
        'paid'    => ['#22c55e','#f0fdf4'],
        'overdue' => ['#ef4444','#fef2f2'],
    ];
    [$c,$bg] = $cfg[$s] ?? ['#6b7280','#f3f4f6'];
    return "<span style='display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700;color:{$c};background:{$bg};'>" . strtoupper(htmlspecialchars($s)) . "</span>";
}
function methodBadge(string $m): string {
    $labels = ['cash'=>'Cash','gcash'=>'GCash','bank_transfer'=>'Bank Transfer'];
    return htmlspecialchars($labels[$m] ?? ucfirst($m));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Finances</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
    <style>
        .mf-container { max-width: 1100px; margin: 0 auto; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }
        .sum-card {
            background: white;
            border-radius: 20px;
            padding: 26px 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(40,167,69,0.08);
            position: relative; overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }
        .sum-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .sum-card::before {
            content: ''; position: absolute;
            top: -24px; right: -24px;
            width: 90px; height: 90px;
            border-radius: 50%;
            opacity: .08;
        }
        .sum-card.green::before  { background: #28a745; }
        .sum-card.blue::before   { background: #3b82f6; }
        .sum-card.purple::before { background: #8b5cf6; }
        .sum-card.orange::before { background: #f59e0b; }
        .sum-card .sc-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px; font-size: 20px;
        }
        .sum-card .sc-val {
            font-size: 26px; font-weight: 800;
            color: var(--text); letter-spacing: -1px;
            margin-bottom: 4px;
        }
        .sum-card .sc-lbl {
            font-size: 12px; font-weight: 700;
            color: var(--muted); text-transform: uppercase; letter-spacing: .5px;
        }
        .sc-sub { font-size: 11px; color: var(--muted); margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap; }
        .sc-sub span { display: inline-flex; align-items: center; gap: 3px; }
        .tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; }
        .tab-btn {
            padding: 10px 22px; font-size: 13px; font-weight: 700;
            border: none; background: none; cursor: pointer;
            color: var(--muted); border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: all .2s; border-radius: 8px 8px 0 0;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(40,167,69,0.04); }
        .tab-btn:hover:not(.active) { color: var(--text); background: #f9fafb; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; animation: fadeIn .25s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
        .mf-table { width: 100%; border-collapse: collapse; }
        .mf-table th { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; padding: 10px 14px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .mf-table td { padding: 13px 14px; font-size: 14px; color: var(--text); border-bottom: 1px solid #f9fafb; }
        .mf-table tr:last-child td { border-bottom: none; }
        .mf-table tr:hover td { background: #fafcff; }
        .empty-row td { text-align: center; color: var(--muted); padding: 40px; font-size: 14px; }
        .net-bar {
            background: linear-gradient(135deg, rgba(40,167,69,0.08), rgba(59,130,246,0.05));
            border: 1.5px solid rgba(40,167,69,0.15);
            border-radius: 16px; padding: 20px 28px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px; margin-bottom: 32px;
        }
        .net-label { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
        .net-value { font-size: 28px; font-weight: 800; letter-spacing: -1px; }
    </style>
</head>
<body>
<div id="app" class="app">
    <?php include SRC_PATH . '/partials/header.php'; ?>
    <?php include SRC_PATH . '/partials/sidebar.php'; ?>
    <div id="overlay" class="overlay"></div>
    <main class="content" id="mainContent">
        <div class="mf-container">
            <section class="section active">
                <h2 style="font-size:32px;font-weight:800;color:var(--primary);margin-bottom:8px;letter-spacing:-1px;">My Finances</h2>
                <p style="color:var(--muted);font-size:14px;margin-bottom:28px;">Your personal financial summary — receivables, payables, and disbursements.</p>

                <!-- Summary Cards -->
                <div class="summary-grid">
                    <div class="sum-card green">
                        <div class="sc-icon" style="background:rgba(40,167,69,0.1);">💵</div>
                        <div class="sc-val"><?php echo fmt($arTotal); ?></div>
                        <div class="sc-lbl">Total Receivable</div>
                        <div class="sc-sub">
                            <span style="color:#22c55e">✓ <?php echo fmt($arPaid); ?> paid</span>
                            <span style="color:#f59e0b">⏳ <?php echo fmt($arPending); ?></span>
                            <?php if ($arOverdue > 0): ?><span style="color:#ef4444">⚠ <?php echo fmt($arOverdue); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="sum-card blue">
                        <div class="sc-icon" style="background:rgba(59,130,246,0.1);">📤</div>
                        <div class="sc-val"><?php echo fmt($apTotal); ?></div>
                        <div class="sc-lbl">Total Payable</div>
                        <div class="sc-sub">
                            <span style="color:#22c55e">✓ <?php echo fmt($apPaid); ?> paid</span>
                            <span style="color:#f59e0b">⏳ <?php echo fmt($apPending); ?></span>
                            <?php if ($apOverdue > 0): ?><span style="color:#ef4444">⚠ <?php echo fmt($apOverdue); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="sum-card purple">
                        <div class="sc-icon" style="background:rgba(139,92,246,0.1);">💸</div>
                        <div class="sc-val"><?php echo fmt($disbTotal); ?></div>
                        <div class="sc-lbl">Total Disbursed</div>
                    </div>
                    <div class="sum-card orange">
                        <div class="sc-icon" style="background:rgba(245,158,11,0.1);">📊</div>
                        <?php $net = $arPaid - $apPaid - $disbTotal; ?>
                        <div class="sc-val" style="color:<?php echo $net >= 0 ? '#22c55e' : '#ef4444'; ?>"><?php echo fmt(abs($net)); ?></div>
                        <div class="sc-lbl">Net <?php echo $net >= 0 ? 'Surplus' : 'Deficit'; ?></div>
                        <div class="sc-sub"><span><?php echo $net >= 0 ? '▲ Positive balance' : '▼ Negative balance'; ?></span></div>
                    </div>
                </div>

                <!-- Net bar -->
                <?php if ($arTotal > 0 || $apTotal > 0): ?>
                <div class="net-bar">
                    <div>
                        <div class="net-label">Balance at a Glance</div>
                        <div style="font-size:13px;color:var(--muted);margin-top:4px;">AR Collected − AP Paid − Disbursements</div>
                    </div>
                    <div>
                        <div class="net-label" style="text-align:right;">Net Position</div>
                        <div class="net-value" style="color:<?php echo $net >= 0 ? '#22c55e' : '#ef4444'; ?>;text-align:right;">
                            <?php echo ($net >= 0 ? '+' : '−') . fmt(abs($net)); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="card" style="padding:28px 28px 20px;">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="switchTab('ar',this)">💵 Receivable</button>
                        <button class="tab-btn" onclick="switchTab('ap',this)">📤 Payable</button>
                        <button class="tab-btn" onclick="switchTab('disb',this)">💸 Disbursements</button>
                    </div>

                    <!-- AR Tab -->
                    <div id="tab-ar" class="tab-panel active">
                        <div style="overflow-x:auto;">
                        <table class="mf-table">
                            <thead><tr>
                                <th>#</th><th>Amount Due</th><th>Due Date</th><th>Method</th><th>Status</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($recentAR)): ?>
                                <tr class="empty-row"><td colspan="5">No receivable records found.</td></tr>
                            <?php else: foreach ($recentAR as $r): ?>
                                <tr>
                                    <td style="color:var(--muted);font-size:12px;">AR-<?php echo $r['ar_id']; ?></td>
                                    <td style="font-weight:700;"><?php echo fmt((float)$r['amount_due']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($r['due_date'])); ?></td>
                                    <td><?php echo methodBadge($r['method'] ?? 'cash'); ?></td>
                                    <td><?php echo statusBadge($r['status'] ?? 'pending'); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <!-- AP Tab -->
                    <div id="tab-ap" class="tab-panel">
                        <div style="overflow-x:auto;">
                        <table class="mf-table">
                            <thead><tr>
                                <th>#</th><th>Amount Due</th><th>Due Date</th><th>Method</th><th>Status</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($recentAP)): ?>
                                <tr class="empty-row"><td colspan="5">No payable records found.</td></tr>
                            <?php else: foreach ($recentAP as $r): ?>
                                <tr>
                                    <td style="color:var(--muted);font-size:12px;">AP-<?php echo $r['ap_id']; ?></td>
                                    <td style="font-weight:700;"><?php echo fmt((float)$r['amount_due']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($r['due_date'])); ?></td>
                                    <td><?php echo methodBadge($r['method'] ?? 'cash'); ?></td>
                                    <td><?php echo statusBadge($r['status'] ?? 'pending'); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <!-- Disbursements Tab -->
                    <div id="tab-disb" class="tab-panel">
                        <div style="overflow-x:auto;">
                        <table class="mf-table">
                            <thead><tr>
                                <th>#</th><th>Amount</th><th>Date</th><th>Method</th><th>Remarks</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($recentDisb)): ?>
                                <tr class="empty-row"><td colspan="5">No disbursement records found.</td></tr>
                            <?php else: foreach ($recentDisb as $r): ?>
                                <tr>
                                    <td style="color:var(--muted);font-size:12px;">D-<?php echo $r['disbursement_id']; ?></td>
                                    <td style="font-weight:700;"><?php echo fmt((float)$r['amount']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($r['disbursement_date'])); ?></td>
                                    <td><?php echo methodBadge($r['method'] ?? 'cash'); ?></td>
                                    <td style="color:var(--muted);font-size:13px;"><?php echo htmlspecialchars($r['remarks'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div><!-- /card -->

            </section>
        </div>
    </main>
</div>
<script src="../assets/js/app.js"></script>
<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
