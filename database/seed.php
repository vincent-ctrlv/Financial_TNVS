<?php
// Creates one test account per role for local development.
// Run from the project folder:   php database/seed.php
// Accounts that already exist (same email) are skipped.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once dirname(__DIR__) . '/src/env.php';
require_once dirname(__DIR__) . '/src/db.php';

$accounts = [
    ['name' => 'System Admin',  'email' => 'admin@example.com',   'role' => 'admin',         'password' => 'admin12345',
     'plate_no' => null,       'reference_no' => null,       'address' => null],
    ['name' => 'Finance Staff', 'email' => 'finance@example.com', 'role' => 'finance_staff', 'password' => 'finance12345',
     'plate_no' => null,       'reference_no' => null,       'address' => null],
    ['name' => 'John Driver',   'email' => 'driver@example.com',  'role' => 'driver',        'password' => 'driver12345',
     'plate_no' => 'XYZ 7890', 'reference_no' => 'REF-1002', 'address' => '123 Driver St, Manila'],
];

$exists = $mysqli->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$insert = $mysqli->prepare('INSERT INTO users (name, email, role, password_hash, plate_no, reference_no, address) VALUES (?, ?, ?, ?, ?, ?, ?)');
if (!$exists || !$insert) {
    fwrite(STDERR, "Prepare failed: {$mysqli->error}\nDid you import database/schema.sql?\n");
    exit(1);
}

foreach ($accounts as $a) {
    $exists->bind_param('s', $a['email']);
    $exists->execute();
    $exists->store_result();
    if ($exists->num_rows > 0) {
        echo "skip   {$a['email']} (already exists)\n";
        continue;
    }

    $hash = password_hash($a['password'], PASSWORD_DEFAULT);
    $insert->bind_param('sssssss', $a['name'], $a['email'], $a['role'], $hash, $a['plate_no'], $a['reference_no'], $a['address']);
    if ($insert->execute()) {
        echo "create {$a['email']} / {$a['password']} ({$a['role']})\n";
    } else {
        fwrite(STDERR, "error  {$a['email']}: {$insert->error}\n");
    }
}

echo "\nThese are test passwords. Change them (My Account > Change Password) before using the system for real.\n";
