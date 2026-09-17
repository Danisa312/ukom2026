<?php
/**
 * Main Entry Point
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (is_owner()) {
        redirect('owner/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
} else {
    redirect('modules/auth/login.php');
}
