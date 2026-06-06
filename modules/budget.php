<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_access('budget');
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Budget - TNVS</title>
	<link rel="stylesheet" href="../assets/css/style.css?v=1.0.2">
</head>
<body>
	<div id="app" class="app">
		<?php include '../includes/header.php'; ?>
		<?php include '../includes/sidebar.php'; ?>

		<main class="content" id="mainContent">
			<section class="section active">
				<h3>Budget Overview</h3>
				<div class="card">
                    <p>Budget management system coming soon...</p>
                </div>
			</section>
		</main>
	</div>
	<script src="../assets/js/app.js"></script>
</body>
</html>
