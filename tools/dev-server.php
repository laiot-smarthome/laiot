<?php

declare(strict_types=1);

/**
 * Роутер для встроенного PHP-сервера: статика из public/, остальное — kernel.
 *
 * Запуск из корня проекта:
 *   php -S 127.0.0.1:8080 -t public tools/dev-server.php
 *
 * 2026-10-27 — замена alpha dev_server.php после удаления старого runtime.
 */

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';
$file = $root . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require $root . '/public/index.php';
