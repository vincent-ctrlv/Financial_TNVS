<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();
require_access('activity');
$sessionUser = current_user();
$uid = (int)($sessionUser['id'] ?? 0);

// Pagination
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Date filter
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

// Count total rows
$countSql = "SELECT COUNT(*) AS total FROM auditlogs WHERE user_id = ?";
$params   = [$uid];
$types    = 'i';
if ($dateFrom) { $countSql .= " AND DATE(timestamp) >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo)   { $countSql .= " AND DATE(timestamp) <= ?"; $params[] = $dateTo;   $types .= 's'; }

$total = 0;
if ($stmt = $mysqli->prepare($countSql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['total']; }
    $stmt->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch rows
$logs = [];
$dataSql = "SELECT log_id, action, target_table, timestamp FROM auditlogs WHERE user_id = ?";
if ($dateFrom) { $dataSql .= " AND DATE(timestamp) >= ?"; }
if ($dateTo)   { $dataSql .= " AND DATE(timestamp) <= ?"; }
$dataSql .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$dataParams = $params;
$dataParams[] = $perPage;
$dataParams[] = $offset;
$dataTypes = $types . 'ii';
if ($stmt = $mysqli->prepare($dataSql)) {
    $stmt->bind_param($dataTypes, ...$dataParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $logs[] = $row; }
    $stmt->close();
}

// Action icon & color map
function actionMeta(string $action): array {
    $map = [
        'change_password' => ['🔐', '#8b5cf6', 'Changed password'],
        'update_profile'  => ['✏️',  '#3b82f6', 'Updated profile'],
        'mark_paid'       => ['💰', '#22c55e', 'Marked as paid'],
        'delete_user'     => ['🗑️',  '#ef4444', 'Deleted a user'],
        'add_user'        => ['➕', '#28a745', 'Added a user'],
        'edit_user'       => ['📝', '#f59e0b', 'Edited a user'],
    ];
    return $map[$action] ?? ['⚙️',  '#6b7280', ucwords(str_replace('_',' ',$action))];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
    <style>
        .activity-container { max-width: 860px; margin: 0 auto; }
        .filter-bar {
            display: flex; align-items: center; gap: 14px;
            flex-wrap: wrap; margin-bottom: 28px;
        }
        .filter-bar label {
            font-size: 12px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .5px;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-bar input[type="date"] {
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            padding: 8px 12px; font-size: 13px; color: var(--text);
            background: white;
        }
        .filter-bar input[type="date"]:focus { outline:none; border-color:var(--primary); }
        .timeline { display: flex; flex-direction: column; gap: 0; }
        .tl-item {
            display: flex; gap: 20px; align-items: flex-start;
            padding: 20px 0;
            border-bottom: 1px solid #f3f4f6;
            animation: fadeInUp .35s ease both;
        }
        .tl-item:last-child { border-bottom: none; }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .tl-icon {
            width: 44px; height: 44px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .tl-body { flex: 1; }
        .tl-action { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .tl-meta { font-size: 12px; color: var(--muted); display: flex; gap: 12px; flex-wrap: wrap; }
        .tl-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 2px 10px; border-radius: 99px;
            font-size: 11px; font-weight: 700;
            background: rgba(0,0,0,0.04); color: var(--muted);
        }
        .tl-time { font-size: 12px; color: var(--muted); white-space: nowrap; padding-top: 4px; }
        .empty-state { text-align: center; padding: 64px 20px; color: var(--muted); }
        .empty-state svg { opacity: .25; margin-bottom: 16px; }
        .pagination { display: flex; gap: 8px; justify-content: center; margin-top: 32px; flex-wrap: wrap; }
        .page-btn {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            border: 1.5px solid #e5e7eb; background: white;
            color: var(--text); text-decoration: none; cursor: pointer;
            transition: all .2s;
        }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: var(--shadow-glow); }
        .page-btn:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
        .stat-mini {
            display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap;
        }
        .stat-mini-card {
            flex: 1; min-width: 130px;
            background: white; border-radius: 16px;
            padding: 18px 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(40,167,69,0.08);
        }
        .stat-mini-card .val { font-size: 26px; font-weight: 800; color: var(--primary); }
        .stat-mini-card .lbl { font-size: 12px; color: var(--muted); margin-top: 2px; font-weight: 600; }
    </style>
</head>
<body>
<div id="app" class="app">
    <?php include SRC_PATH . '/partials/header.php'; ?>
    <?php include SRC_PATH . '/partials/sidebar.php'; ?>
    <div id="overlay" class="overlay"></div>
    <main class="content" id="mainContent">
        <div class="activity-container">
            <section class="section active">
                <h2 style="font-size:32px;font-weight:800;color:var(--primary);margin-bottom:8px;letter-spacing:-1px;">My Activity</h2>
                <p style="color:var(--muted);font-size:14px;margin-bottom:28px;">A complete log of all actions you have performed in the system.</p>

                <!-- Mini stats -->
                <div class="stat-mini">
                    <div class="stat-mini-card">
                        <div class="val"><?php echo number_format($total); ?></div>
                        <div class="lbl">Total Actions</div>
                    </div>
                    <div class="stat-mini-card">
                        <?php
                        $todayCount = 0;
                        if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM auditlogs WHERE user_id=? AND DATE(timestamp)=CURDATE()")) {
                            $stmt->bind_param('i',$uid); $stmt->execute();
                            $r = $stmt->get_result()->fetch_assoc();
                            $todayCount = (int)($r['c'] ?? 0);
                            $stmt->close();
                        }
                        ?>
                        <div class="val"><?php echo $todayCount; ?></div>
                        <div class="lbl">Today</div>
                    </div>
                    <div class="stat-mini-card">
                        <?php
                        $weekCount = 0;
                        if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM auditlogs WHERE user_id=? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)")) {
                            $stmt->bind_param('i',$uid); $stmt->execute();
                            $r = $stmt->get_result()->fetch_assoc();
                            $weekCount = (int)($r['c'] ?? 0);
                            $stmt->close();
                        }
                        ?>
                        <div class="val"><?php echo $weekCount; ?></div>
                        <div class="lbl">This Week</div>
                    </div>
                </div>

                <!-- Date filter -->
                <form method="get" class="filter-bar">
                    <label>From <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>"></label>
                    <label>To   <input type="date" name="to"   value="<?php echo htmlspecialchars($dateTo); ?>"></label>
                    <button type="submit" class="btn-small" style="padding:9px 22px;">Filter</button>
                    <?php if ($dateFrom || $dateTo): ?>
                    <a href="activity.php" style="font-size:13px;color:var(--muted);text-decoration:none;">✕ Clear</a>
                    <?php endif; ?>
                </form>

                <div class="card" style="padding:8px 32px;">
                    <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <p style="font-size:15px;font-weight:600;">No activity found<?php echo ($dateFrom||$dateTo)?' for this period.':' yet.'; ?></p>
                    </div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logs as $i => $log):
                            [$icon, $color, $label] = actionMeta($log['action']);
                            $bg = $color . '18';
                            $ts = strtotime($log['timestamp']);
                            $timeAgo = '';
                            $diff = time() - $ts;
                            if ($diff < 60)          $timeAgo = 'Just now';
                            elseif ($diff < 3600)    $timeAgo = floor($diff/60).'m ago';
                            elseif ($diff < 86400)   $timeAgo = floor($diff/3600).'h ago';
                            elseif ($diff < 604800)  $timeAgo = floor($diff/86400).'d ago';
                            else                     $timeAgo = date('M j, Y', $ts);
                        ?>
                        <div class="tl-item" style="animation-delay:<?php echo $i * 0.04; ?>s">
                            <div class="tl-icon" style="background:<?php echo $bg; ?>"><?php echo $icon; ?></div>
                            <div class="tl-body">
                                <div class="tl-action"><?php echo htmlspecialchars($label); ?></div>
                                <div class="tl-meta">
                                    <span class="tl-badge">📋 <?php echo htmlspecialchars($log['target_table']); ?></span>
                                    <span><?php echo date('M j, Y · g:i A', $ts); ?></span>
                                </div>
                            </div>
                            <div class="tl-time"><?php echo $timeAgo; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $qBase = http_build_query(['from'=>$dateFrom,'to'=>$dateTo]);
                    for ($p = 1; $p <= $totalPages; $p++):
                        $active = ($p === $page) ? ' active' : '';
                    ?>
                    <a href="?<?php echo $qBase; ?>&page=<?php echo $p; ?>" class="page-btn<?php echo $active; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

            </section>
        </div>
    </main>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
