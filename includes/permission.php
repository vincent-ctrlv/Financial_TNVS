<?php
/**
 * Permission helper utilities.
 * Provides a thin wrapper around the existing role‑based checks in auth.php.
 * The functions are deliberately simple – they rely on the `role` value stored
 * in the session (populated during login) and the `role_permissions()` array
 * defined in `includes/auth.php`.
 */

/**
 * Check if a given user ID has a specific role.
 *
 * @param int $userId   The user ID.
 * @param string $roleName The role name to check (e.g. 'admin', 'finance_staff', 'driver').
 * @return bool True if the user possesses the role.
 */
function has_role(int $userId, string $roleName): bool {
    // Retrieve the user from the session if it matches the ID.
    if (!isset($_SESSION['user']) || (int)($_SESSION['user']['id'] ?? 0) !== $userId) {
        return false;
    }
    $role = $_SESSION['user']['role'] ?? '';
    return $role === $roleName;
}

/**
 * Require that the current logged‑in user has the given role.
 * Terminates the request with a 403 response if the check fails.
 *
 * @param string $role Required role name.
 */
function require_role(string $role): void {
    require_once __DIR__ . '/auth.php'; // Ensure auth helpers are loaded.
    require_login();
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        echo 'Forbidden – insufficient role';
        exit;
    }
}

/**
 * Alias for can_access – checks whether the current user can view a page slug.
 */
function can_view(string $page_slug): bool {
    return can_access($page_slug);
}
?>
