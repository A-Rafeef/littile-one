<?php

/**
 * Admin Authentication Check
 * Kids Store v5
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Check if user is logged in
function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Require login or redirect
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(ADMIN_URL . '/index.php');
    }
}

// Get current admin
function getCurrentAdmin(): ?array
{
    if (!isLoggedIn()) return null;

    $stmt = getDB()->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}
