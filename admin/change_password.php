<?php
// Redirect to profile page which includes the change password form
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();
redirect(APP_URL . '/admin/profile.php#change-password');
