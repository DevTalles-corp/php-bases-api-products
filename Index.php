<?php

declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uriPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?? "/";
$segments = array_values(array_filter(explode("/", trim($uriPath, "/"))));
