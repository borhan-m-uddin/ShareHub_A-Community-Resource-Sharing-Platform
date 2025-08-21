# Community Sharing (PHP)

A small PHP/MySQL application for sharing resources (items and services). This repository is a sanitized version suitable for sharing.

## What to include before running
- Copy `config.sample.php` to `config.php` and fill in your database credentials.
- Import the `setup_database.sql` file (if present) or run the provided SQL to create the schema.

## Requirements
- PHP 8+ with mysqli
- MySQL / MariaDB

## Quick start (local)
1. Copy the sample config and update credentials:

   copy config.sample.php config.php

2. Import the database schema (example):

   mysql -u root -p community_sharing < setup_database.sql

3. Start the PHP development server:

   php -S localhost:8000

4. Open your browser at `http://localhost:8000`.

## Secure sharing tips
- Do not commit `config.php` with real credentials.
- Add demo users and test data to `setup_database.sql` if you want a ready-to-run demo.

## How to publish to GitHub (from repo root)
1. Initialize git and push to a new remote repository you create on GitHub:

   git init
   git add .
   git commit -m "Initial sanitized import"
   git branch -M main
   git remote add origin https://github.com/YOUR_USER/YOUR_REPO.git
   git push -u origin main

2. Make the repository public on GitHub so others can clone it.

If you'd like, I can prepare a ready-to-push commit (sanitizing the repo) and show the exact commands to run on your machine.
