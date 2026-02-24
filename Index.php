<?php

declare(strict_types=1);
require_once "Database.php";

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
function getAllProducts(PDO $pdo): array
{
    $sql = "SELECT id,name,price,stock,created_at FROM products ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
try {
    $pdo = getConnection();
    [$resource, $resourceId] = resolveRoute($segments); //["products", 2] ,["products", null], [null, null]
    if ($resource !== "products") {
        respondError(404, "Recurso no encontrado. Usa /products");
    }
    if ($method === "GET" && $resourceId === null) {
        $products = getAllProducts($pdo);
        respondJson(200, $products);
    }
} catch (PDOException $e) {
    respondError(500, "Error de conexión: " . $e->getMessage());
} catch (Exception $expection) {
    respondError(500, "Error interno: " . $expection->getMessage());
}
