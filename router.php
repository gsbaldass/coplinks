<?php

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__ . '/public' . $path);
$public = realpath(__DIR__ . '/public');
if ($path !== '/' && $file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR)
    && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require __DIR__ . '/public/index.php';
