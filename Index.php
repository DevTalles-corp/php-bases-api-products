<?php

declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uriPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?? "/";
$segments = array_values(array_filter(explode("/", trim($uriPath, "/"))));

function resolveRoute(array $segments): array
{
    $pos = array_search("products", $segments, true);
    if ($pos === false) {
        return [null, null];
    }
    $resource = "products";
    $id = $segments[$pos + 1] ?? null;
    if ($id !== null) {
        if (!ctype_digit($id)) {
            respondError(400, "El id debe ser numérico");
        }
        // ["products",2]
        return [$resource, (int)$id];
    }
    return [$resource, null]; // ["products",null]
}
function respondJson(int $statusCode, $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function respondError(int $statusCode, string $message): void
{
    respondJson($statusCode, ["error" => $message]);
}
function readJsonBody(): array
{
    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === '') {
        respondError(400, "El cuerpo de la petición está vacío");
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respondError(400, "JSON inválido:" . json_last_error_msg());
    }
    if (!is_array($data)) {
        respondError(400, "El JSON debe representar un objeto");
    }
    return $data;
}
