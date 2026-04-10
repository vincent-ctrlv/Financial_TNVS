<?php
require_once __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
$u = current_user();
$uid = (int)($u['id'] ?? 0);
$data = ['name' => $u['name'] ?? 'User', 'avatar' => null];
if ($uid) {
    if ($stmt = $mysqli->prepare('SELECT name, avatar FROM users WHERE user_id = ? LIMIT 1')) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $data['name'] = $row['name'] ?? $data['name'];
            $av = $row['avatar'] ?? '';
            if ($av && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $av)) { $data['avatar'] = $av . '?v=' . time(); }
        }
        $stmt->close();
    }
}
header('Content-Type: application/json');
echo json_encode($data);
exit;
?>


