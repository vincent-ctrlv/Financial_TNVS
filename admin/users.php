<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('users');
$user = current_user();

// Fetch users
$users = [];
if ($stmt = $mysqli->prepare("SELECT user_id, name, email, role, plate_no, reference_no, address FROM users ORDER BY name ASC")) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Users Management - TNVS</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.5">
</head>
<body>
	<div id="app" class="app">
		<?php include '../includes/header.php'; ?>
		<?php include '../includes/sidebar.php'; ?>

		<div id="overlay" class="overlay"></div>
		<main class="content" id="mainContent">
			<section class="section active">
				<div class="section-header">
                    <h3>Users Management</h3>
                </div>

                <div class="section-actions">
                    <div class="search-input-group">
                        <input type="text" id="userSearch" placeholder="Search by name, email or role..." onkeyup="filterUsers()">
                    </div>
                    <button class="btn-small" onclick="addUser()">+ Add New User</button>
                </div>
                
                <div class="card">
                    <div class="table-wrap">
                        <table class="table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr data-id="<?php echo htmlspecialchars($u['user_id']); ?>" data-name="<?php echo htmlspecialchars($u['name']); ?>" data-email="<?php echo htmlspecialchars($u['email']); ?>" data-role="<?php echo htmlspecialchars($u['role']); ?>" data-plate="<?php echo htmlspecialchars($u['plate_no'] ?? ''); ?>" data-reference="<?php echo htmlspecialchars($u['reference_no'] ?? ''); ?>" data-address="<?php echo htmlspecialchars($u['address'] ?? ''); ?>">
                                    <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><span class="badge badge-<?php echo strtolower($u['role']); ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                    <td class="actions-cell">
                                        <button class="icon-btn" onclick='editUser(<?php echo json_encode($u); ?>)' title="Edit User">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                        <?php if ($u['user_id'] != $user['id']): ?>
                                        <button class="icon-btn" onclick="deleteUser(<?php echo $u['user_id']; ?>)" title="Delete User" style="color:var(--danger)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
			</section>
		</main>

        <!-- User Modal -->
        <div id="userModal" class="modal">
            <div class="modal-card">
                <div class="modal-header">
                    <h4 id="modalTitle">Add New User</h4>
                    <button class="icon-btn" onclick="hideUserModal()">✕</button>
                </div>
                <div class="modal-body">
                    <form id="userForm" class="form-grid" onsubmit="saveUser(event)">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="user_id" id="formUserId">
                        <input type="hidden" name="action" value="save">
                        
                        <label>
                            Full Name
                            <input type="text" name="name" id="formName" required placeholder="Enter full name">
                        </label>
                        
                        <label>
                            Email Address
                            <input type="email" name="email" id="formEmail" required placeholder="Enter email address">
                        </label>
                        
                        <label>
                            System Role
                            <select name="role" id="formRole" required>
                                <option value="admin">Admin</option>
                                <option value="finance_staff">Finance Staff</option>
                                <option value="driver">Driver</option>
                            </select>
                        </label>
                        
                        <label>
                            Password
                            <input type="password" name="password" id="formPassword" placeholder="Leave blank to keep current">
                        </label>
                        
                        <!-- Driver Specific Fields -->
                        <div id="driverFields" style="grid-column: 1 / -1; display: none; flex-direction: column; gap: 12px; margin-top: 6px; padding-top: 12px; border-top: 1px dashed var(--border);">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <label style="margin: 0;">
                                    Plate Number
                                    <input type="text" name="plate_no" id="formPlateNo" placeholder="e.g. ABC 1234">
                                </label>
                                <label style="margin: 0;">
                                    Reference Number
                                    <input type="text" name="reference_no" id="formReferenceNo" placeholder="e.g. REF-7890">
                                </label>
                            </div>
                            <label style="margin: 0;">
                                Address
                                <input type="text" name="address" id="formAddress" placeholder="Enter full address">
                            </label>
                        </div>
                        
                        <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end; gap:12px; margin-top:12px;">
                            <button type="button" class="text-btn" onclick="hideUserModal()">Cancel</button>
                            <button type="submit" class="btn-small">Save User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
	</div>
						<script>
							window._userApiUrl = '<?php
								$_apiRoot = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\\\");
								if (in_array(basename(dirname($_SERVER["SCRIPT_NAME"])), ["modules","admin","auth","user"])) { $_apiRoot = dirname($_apiRoot); }
								echo rtrim($_apiRoot, "/\\\\") . "/admin/user_api.php";
							?>';
						</script>
	<script src="../assets/js/app.js"></script>
</body>
</html>
