<?php

declare(strict_types=1);
require_once "Database.php";
require_once "ProductRepository.php";
require_once "Helpers.php";

header("Content-Type: application/json; charset=utf-8");

try {
    $pdo = getConnection();
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    $uriPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?? "/";
    $segments = array_values(array_filter(explode("/", trim($uriPath, "/"))));
    [$resource, $resourceId] = resolveRoute($segments); //["products", 2] ,["products", null], [null, null]
    if ($resource !== "products") {
        respondError(404, "Recurso no encontrado. Usa /products");
    }
    if ($method === "GET" && $resourceId === null) {
        $products = getAllProducts($pdo);
        respondJson(200, $products);
    }
    if ($method === "GET" && $resourceId !== null) {
        if ($resourceId <= 0) {
            respondError(400, "El id debe ser un número válido");
        }
        $product = getProductById($pdo, $resourceId);
        if ($product === null) {
            respondError(404, "Producto no encontrado");
        }
        respondJson(200, $product);
    }
    if ($method === "POST" && $resourceId === null) {
        $payload = readJsonBody();
        $errors = validateProductPayload($payload, isCreate: true);
        if (count($errors) > 0) {
            respondJson(422, ["errors" => $errors]);
        }
        $newId = createProduct($pdo, $payload);
        $newProduct = getProductById($pdo, $newId);
        respondJson(
            201,
            [
                "message" => "Producto creado correctamente",
                "data" => $newProduct
            ]
        );
    }
    if (($method === "PUT" || $method === "PATCH") && $resourceId !== null) {
        if ($resourceId <= 0) {
            respondError(400, "El id debe ser un número válido");
        }
        $existing = getProductById($pdo, $resourceId);
        if ($existing === null) {
            respondError(404, "Producto no encontrado");
        }
        $payload = readJsonBody();
        $isCreate = false;
        $requireAllFields = ($method === "PUT");
        $errors = validateProductPayload($payload, $isCreate, $requireAllFields);
        if (count($errors) > 0) {
            respondJson(422, ["errors" => $errors]);
        }
        $merged = mergedProductData($existing, $payload);
        updateProduct($pdo, $resourceId, $merged);
        $updated = getProductById($pdo, $resourceId);
        respondJson(
            200,
            [
                "message" => "Producto actualizado correctamente",
                "data" => $updated
            ]
        );
    }
    if ($method === "DELETE" && $resourceId !== null) {
        if ($resourceId <= 0) {
            respondError(400, "El id debe ser un número válido");
        }
        $existing = getProductById($pdo, $resourceId);
        if ($existing === null) {
            respondError(404, "Producto no encontrado");
        }
        $deleted = deleteProduct($pdo, $resourceId);
        if (!$deleted) {
            respondError(409, "No se pudo eliminar el producto");
        }
        respondJson(
            200,
            [
                "message" => "Producto eliminado correctamente",
                "data" => $existing
            ]
        );
    }
} catch (PDOException $e) {
    respondError(500, "Error de conexión: " . $e->getMessage());
} catch (Exception $expection) {
    respondError(500, "Error interno: " . $expection->getMessage());
}