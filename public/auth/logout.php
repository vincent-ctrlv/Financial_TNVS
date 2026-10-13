<?php
session_start();
session_unset();
session_regenerate_id(true); // Prevent session fixation
session_destroy();
header('Location: login.php');
exit;
