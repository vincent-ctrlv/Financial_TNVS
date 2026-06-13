<?php
// Session and authentication helpers
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

require_once __DIR__ . '/db.php';

// Normalize session user keys and attempt to backfill missing fields from DB.
function _normalize_session_user(mysqli $mysqli): void {
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) return;
    $u = &$_SESSION['user'];
    
    // Support older key name 'user_id'
    if (!isset($u['id']) && isset($u['user_id'])) {
        $u['id'] = (int)$u['user_id'];
    }

    // CRITICAL: Invalidate sessions that have an ID of 0 (from the old login backdoor)
    // This forces users with stale sessions to log in again using the proper system.
    if (!isset($u['id']) || (int)$u['id'] <= 0) {
        unset($_SESSION['user']);
        return;
    }

    // Determine if we need to backfill data from the DB
    $need = [];
    foreach (['name','email','role','avatar'] as $k) { if (!isset($u[$k]) || $u[$k] === '') $need[] = $k; }
    if (empty($need)) return;

    $id = (int)$u['id'];
    if ($stmt = $mysqli->prepare('SELECT name, email, role, avatar FROM users WHERE user_id = ? LIMIT 1')) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!isset($u['name']) || $u['name'] === '') $u['name'] = $row['name'] ?? $u['name'] ?? 'User';
            if (!isset($u['email']) || $u['email'] === '') $u['email'] = $row['email'] ?? $u['email'] ?? '';
            if (!isset($u['role']) || $u['role'] === '') $u['role'] = $row['role'] ?? $u['role'] ?? '';
            if (!isset($u['avatar']) || $u['avatar'] === '') $u['avatar'] = $row['avatar'] ?? $u['avatar'] ?? '';
        }
        $stmt->close();
    }
}

// Run normalization once on every request to keep session shape predictable
_normalize_session_user($mysqli);

function is_logged_in(): bool {
	return !empty($_SESSION['user']);
}

function current_user() {
	return $_SESSION['user'] ?? null;
}

function require_login(): void {
	if (!is_logged_in()) {
		header('Location: login.php');
		exit;
	}
}

function require_role(string $role): void {
	require_login();
	$user = current_user();
	if (!$user || ($user['role'] ?? '') !== $role) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

// Role-based access control
function role_permissions(): array {
    // Page slugs to permit per role
    return [
        'admin' => [
            'dashboard','ledger','accounts_receivable','accounts_payable',
            'collection','budget','disbursement','bank_recon','users','profile'
        ],
        'finance_staff' => [
            'dashboard','ledger','accounts_receivable','accounts_payable',
            'collection','budget','disbursement','profile'
        ],
        'driver' => [
            'dashboard','collection','profile'
        ],
    ];
}

function can_access(string $page_slug): bool {
    $user = current_user();
    $role = $user['role'] ?? '';
    $perms = role_permissions();
    return isset($perms[$role]) && in_array($page_slug, $perms[$role], true);
}

function require_access(string $page_slug): void {
    require_login();
    if (!can_access($page_slug)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}

function verify_csrf_or_die(): void {
    $ok = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['_csrf']);
    if (!$ok) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
}

function attempt_login(mysqli $mysqli, string $email, string $password): bool {
	$stmt = $mysqli->prepare('SELECT user_id, name, email, password_hash, role, avatar FROM users WHERE email = ? LIMIT 1');
	if (!$stmt) return false;
	$stmt->bind_param('s', $email);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$stmt->close();
	if (!$row) return false;
	$hash = $row['password_hash'] ?? '';
	if ($hash && password_verify($password, $hash)) {
		$_SESSION['user'] = [
			'id' => (int)$row['user_id'],
			'name' => $row['name'] ?? 'User',
			'email' => $row['email'],
			'role' => $row['role'] ?? 'driver',
			'avatar' => $row['avatar'] ?? '',
		];
		return true;
	}
	return false;
}

/**
 * Insert a disbursement record when an AR or AP entry is marked paid.
 * De-duplicates by the remark string (e.g. 'AR #5' or 'AP #12').
 *
 * @param mysqli $mysqli   Active DB connection
 * @param int    $userId   ID of the user linked to the record
 * @param float  $amount   Payment amount
 * @param string $method   Payment method (cash|gcash|bank_transfer)
 * @param string $remark   Unique remark, e.g. 'AR #5'
 * @return bool            True if a new row was inserted, false if it already existed or failed
 */
function insert_disbursement_for(mysqli $mysqli, int $userId, float $amount, string $method, string $remark): bool {
    // Validate/normalise method
    if (!in_array($method, ['cash', 'gcash', 'bank_transfer'], true)) {
        $method = 'cash';
    }

    // Deduplicate: skip if a disbursement with this remark already exists
    if ($chk = $mysqli->prepare("SELECT 1 FROM disbursements WHERE remarks = ? LIMIT 1")) {
        $chk->bind_param('s', $remark);
        $chk->execute();
        $chk->store_result();
        $exists = $chk->num_rows > 0;
        $chk->close();
        if ($exists) return false;
    }

    $today = date('Y-m-d');
    if ($ins = $mysqli->prepare("INSERT INTO disbursements (user_id, amount, method, disbursement_date, remarks) VALUES (?,?,?,?,?)")) {
        $ins->bind_param('idsss', $userId, $amount, $method, $today, $remark);
        $ok = $ins->execute();
        $ins->close();
        return $ok;
    }
    return false;
}

/**
 * Log an audit action to the auditlogs table.
 *
 * @param mysqli $mysqli       Active DB connection
 * @param string $action       Action performed (e.g. 'mark_paid', 'update_profile', 'delete_user')
 * @param string $targetTable  Target table (e.g. 'accountsreceivable', 'users')
 * @return bool                True on success
 */
function log_audit_action(mysqli $mysqli, string $action, string $targetTable): bool {
    $userId = null;
    if (isset($_SESSION['user']['id'])) {
        $userId = (int)$_SESSION['user']['id'];
    }
    if ($userId === null) {
        return false;
    }
    if ($stmt = $mysqli->prepare("INSERT INTO auditlogs (user_id, action, target_table) VALUES (?, ?, ?)")) {
        $stmt->bind_param('iss', $userId, $action, $targetTable);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

/**
 * Helper to mirror a paid Accounts Payable (AP) record into Disbursements.
 *
 * @param mysqli $mysqli
 * @param int $apId
 * @return bool True if successfully mirrored or already mirrored, false on failure.
 */
function mirror_ap_to_disbursement(mysqli $mysqli, int $apId): bool {
    if ($apId <= 0) {
        return false;
    }
    $stmt = $mysqli->prepare("SELECT user_id, amount_due, COALESCE(method, 'cash') AS method FROM accountspayable WHERE ap_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $apId);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return insert_disbursement_for(
                    $mysqli,
                    (int)$row['user_id'],
                    (float)$row['amount_due'],
                    (string)$row['method'],
                    'AP #' . $apId
                );
            }
        } else {
            error_log("Failed to execute AP fetch for mirroring: " . $stmt->error);
            $stmt->close();
        }
    } else {
        error_log("Failed to prepare AP fetch for mirroring: " . $mysqli->error);
    }
    return false;
}

/**
 * Helper to mirror a paid Accounts Receivable (AR) record into Disbursements.
 *
 * @param mysqli $mysqli
 * @param int $arId
 * @return bool True if successfully mirrored or already mirrored, false on failure.
 */
function mirror_ar_to_disbursement(mysqli $mysqli, int $arId): bool {
    if ($arId <= 0) {
        return false;
    }
    $stmt = $mysqli->prepare("SELECT user_id, amount_due, COALESCE(method, 'cash') AS method FROM accountsreceivable WHERE ar_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $arId);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return insert_disbursement_for(
                    $mysqli,
                    (int)$row['user_id'],
                    (float)$row['amount_due'],
                    (string)$row['method'],
                    'AR #' . $arId
                );
            }
        } else {
            error_log("Failed to execute AR fetch for mirroring: " . $stmt->error);
            $stmt->close();
        }
    } else {
        error_log("Failed to prepare AR fetch for mirroring: " . $mysqli->error);
    }
    return false;
}
?>
