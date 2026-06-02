<?php
require_once __DIR__ . '/auth.php';
require_role('admin');
// admin.php is deprecated — redirect to the unified dashboard
header('Location: dashboard.php');
exit;
