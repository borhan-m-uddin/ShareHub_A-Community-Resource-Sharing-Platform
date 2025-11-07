<?php
// Vercel Serverless entrypoint for ShareHub.
// Delegates all requests to the existing front controller at public/index.php.

declare(strict_types=1);

// Ensure relative paths resolve from project root
chdir(__DIR__ . '/..');

// Explicitly pull in config & bootstrap before router so builder includes them
if (is_file(__DIR__ . '/../config.php')) {
	require_once __DIR__ . '/../config.php';
}
if (is_file(__DIR__ . '/../bootstrap.php')) {
	require_once __DIR__ . '/../bootstrap.php';
}

// Let the app's front controller handle routing, static passthrough, and legacy mappings
require __DIR__ . '/../public/index.php';
