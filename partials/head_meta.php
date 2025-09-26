<?php
// Shared <head> meta and icons
// Usage: include ROOT_DIR . '/partials/head_meta.php';
?>
<!-- App meta & icons -->
<link rel="icon" type="image/svg+xml" href="<?php echo asset_url('assets/brand/logo-badge.svg'); ?>">
<link rel="shortcut icon" href="<?php echo asset_url('assets/brand/logo-badge.svg'); ?>">
<meta name="theme-color" content="#0ea5e9">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">