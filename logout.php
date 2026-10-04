<?php
/**
 * Aarcon Builders - Logout Endpoint
 */

require_once __DIR__ . '/config/config.php';

logout_user();
set_flash('info', 'You have been logged out securely.');
header('Location: login.php?logged_out=1');
exit;
