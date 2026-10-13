<?php
// MySQL connection (mysqli). Credentials come from .env.

// The app checks return values (if (!$stmt) ...) and logs failures itself,
// so turn off PHP 8.1+'s default of throwing an exception on every error.
mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = @new mysqli(
	getenv('DB_HOST') ?: '127.0.0.1',
	getenv('DB_USER') ?: 'root',
	getenv('DB_PASS') ?: '',
	getenv('DB_NAME') ?: 'finance',
	(int)(getenv('DB_PORT') ?: 3306)
);
if ($mysqli->connect_errno) {
	error_log('Database connection failed: ' . $mysqli->connect_error);
	http_response_code(500);
	echo 'Database connection failed. Check the DB_* settings in .env and that MySQL is running.';
	exit;
}

$mysqli->set_charset('utf8mb4');
