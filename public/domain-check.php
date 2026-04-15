<?php
// Simple domain restriction check - place this in public/domain-check.php

$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown';
$path = $_SERVER['REQUEST_URI'] ?? '/';

echo "Host: " . $host . "\n";
echo "Path: " . $path . "\n";
echo "Should be blocked: " . (($host === 'alldatagh.com' || $host === 'www.alldatagh.com') ? 'YES' : 'NO') . "\n";

if ($host === 'alldatagh.com' || $host === 'www.alldatagh.com') {
    echo "This domain should only show shop pages!\n";
    if (!str_starts_with(trim($path, '/'), 'shop/')) {
        echo "This page should be BLOCKED!\n";
    } else {
        echo "This shop page should be ALLOWED.\n";
    }
}
?>