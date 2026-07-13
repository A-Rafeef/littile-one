<?php

/**
 * Admin Logout
 * Kids Store v5
 */

require_once __DIR__ . '/includes/auth.php';

// Destroy session
session_destroy();

// Redirect to login
redirect(ADMIN_URL . '/index.php');
