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
    // If we have an id but missing name/email/role, try to load from DB
    $need = [];
    foreach (['name','email','role'] as $k) { if (!isset($u[$k]) || $u[$k] === '') $need[] = $k; }
    if ((!isset($u['id']) || (int)$u['id'] === 0) || empty($need)) return;
    $id = (int)$u['id'];
    if ($stmt = $mysqli->prepare('SELECT name, email, role FROM users WHERE user_id = ? LIMIT 1')) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!isset($u['name']) || $u['name'] === '') $u['name'] = $row['name'] ?? $u['name'] ?? 'User';
            if (!isset($u['email']) || $u['email'] === '') $u['email'] = $row['email'] ?? $u['email'] ?? '';
            if (!isset($u['role']) || $u['role'] === '') $u['role'] = $row['role'] ?? $u['role'] ?? '';
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
	$stmt = $mysqli->prepare('SELECT user_id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
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
		];
		return true;
	}
	return false;
}

?>

