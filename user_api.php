<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Only admins can manage users
require_access('users');

header('Content-Type: application/json');

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

if ($method === 'POST') {
    verify_csrf_or_die();

    if ($action === 'save') {
        $id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'driver');
        $password = $_POST['password'] ?? '';

        if (!$name || !$email) {
            send_json(['error' => 'Name and Email are required'], 400);
        }

        if ($id > 0) {
            // Check if email already exists for another user
            $chk = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("si", $email, $id);
                $chk->execute();
                $res = $chk->get_result();
                if ($res->num_rows > 0) {
                    send_json(['error' => 'Email address is already in use'], 400);
                }
                $chk->close();
            }

            // Update
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, role=?, password_hash=? WHERE user_id=?");
                $stmt->bind_param("ssssi", $name, $email, $role, $hash, $id);
            } else {
                $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, role=? WHERE user_id=?");
                $stmt->bind_param("sssi", $name, $email, $role, $id);
            }
        } else {
            // Check if email already exists
            $chk = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("s", $email);
                $chk->execute();
                $res = $chk->get_result();
                if ($res->num_rows > 0) {
                    send_json(['error' => 'Email address is already in use'], 400);
                }
                $chk->close();
            }

            if (!$password) {
                send_json(['error' => 'Password is required for new users'], 400);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $role, $hash);
        }

        if ($stmt && $stmt->execute()) {
            $logAction = $id > 0 ? 'update_user' : 'create_user';
            log_audit_action($mysqli, $logAction, 'users');
            send_json(['success' => true, 'message' => 'User saved successfully']);
        } else {
            send_json(['error' => 'Database error: ' . $mysqli->error], 500);
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
            send_json(['error' => 'You cannot delete yourself'], 400);
        }

        $stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            log_audit_action($mysqli, 'delete_user', 'users');
            send_json(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            send_json(['error' => 'Database error'], 500);
        }
    }
}

send_json(['error' => 'Invalid request'], 400);
