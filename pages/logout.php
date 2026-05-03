<?php
// ============================================================
// IEXPLORE LAGUNA — Logout
// pages/logout.php
// ============================================================
require_once __DIR__ . '/../includes/helpers.php';
session_start_safe();
logout_user();
$_SESSION['flash']['success'] = 'You have been logged out successfully.';
header('Location: ' . APP_URL);
exit;