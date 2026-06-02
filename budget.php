<?php
require_once __DIR__ . '/auth.php';
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
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<div id="app" class="app">
		<?php include 'header.php'; ?>
		<?php include 'sidebar.php'; ?>

		<main class="content" id="mainContent">
			<section class="section active">
				<h3>Budget Overview</h3>
				<div class="card">
                    <p>Budget management system coming soon...</p>
                </div>
			</section>
		</main>
	</div>
	<script src="app.js"></script>
</body>
</html>
