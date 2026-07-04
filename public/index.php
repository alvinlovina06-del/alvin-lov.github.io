<?php
/**
 * Entry Point - UASKTE App
 * Redirect berdasarkan status login
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Auth.php';

if (App\Auth::isLoggedIn()) {
    if (App\Auth::isAdmin()) {
        header('Location: admin/users.php');
    } else {
        header('Location: dashboard.php');
    }
} else {
    header('Location: login.php');
}
exit;
