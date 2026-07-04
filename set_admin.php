<?php
require_once __DIR__ . '/config/app.php';

$pdo = getDBConnection();
$email = 'alvinlovina06@gmail.com';

// Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "User exists. Updating role to admin...\n";
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
    $stmt->execute([$email]);
    echo "Role updated successfully.\n";
} else {
    echo "User does not exist. Inserting new user as admin...\n";
    $stmt = $pdo->prepare("INSERT INTO users (name, email, email_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Alvin Lovina', $email, hash('sha512', $email), 'admin']);
    echo "User inserted as admin successfully.\n";
}
