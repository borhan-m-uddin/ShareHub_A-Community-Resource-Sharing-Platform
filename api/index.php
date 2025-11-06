<?php
// Vercel Serverless entrypoint for ShareHub.
// Delegates all requests to the existing front controller at public/index.php.

declare(strict_types=1);

// Ensure relative paths resolve from project root
chdir(__DIR__ . '/..');

// Let the app's front controller handle routing, static passthrough, and legacy mappings
require __DIR__ . '/../public/index.php';
