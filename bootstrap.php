<?php
// Bootstrap file for initializing application
require_once __DIR__ . '/lib/settings.php';

// Load timezone from settings or default to UTC
$settings = loadSettings(__DIR__ . '/config/settings.json');
$timezone = $settings['timezone'] ?? 'UTC';
date_default_timezone_set($timezone);