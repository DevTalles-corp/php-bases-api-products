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
function validateProductPayload(array $data, bool $isCreate, bool $requireAllFields = false): array
{
    $errors = [];
    $mustHaveAll = $isCreate || $requireAllFields;

    $fields = [
        "name" => [
            "requiredMessage" => "El nombre es obligatorio",
            "rules" => function ($value) use (&$errors) {
                $value = trim((string)$value);

                if ($value === "") {
                    $errors[] = "El nombre no puede estar vacío";
                }

                if (mb_strlen($value) < 2) {
                    $errors[] = "El nombre debe tener al menos 2 caracteres";
                }
            }
        ],
        "price" => [
            "requiredMessage" => "El precio es obligatorio",
            "rules" => function ($value) use (&$errors) {
                if (!is_numeric($value)) {
                    $errors[] = "El precio debe ser númerico";
                }
                if ((float)$value <= 0) {
                    $errors[] = "El precio debe ser mayor a cero";
                }
            }
        ],
        "stock" => [
            "requiredMessage" => "El stock es obligatorio",
            "rules" => function ($value) use (&$errors) {
                if (!is_numeric($value)) {
                    $errors[] = "El stock debe ser númerico";
                }
                if ((float)$value <= 0) {
                    $errors[] = "El stock debe ser mayor a cero";
                }
            }
        ]
    ];

    foreach ($fields as $field => $config) {

        if ($mustHaveAll && !array_key_exists($field, $data)) {
            $errors[] = $config["requiredMessage"];
            continue;
        }

        if (array_key_exists($field, $data)) {
            $config["rules"]($data[$field]);
        }
    }

    return $errors;
}

function getAllProducts(PDO $pdo): array
{
    $sql = "SELECT id,name,price,stock,created_at FROM products ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
function getProductById(PDO $pdo, int $id): ?array
{
    $sql = "SELECT id,name,price,stock,created_at FROM products WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["id" => $id]);
    $product = $stmt->fetch();
    return $product !== false ? $product : null;
}
function createProduct(PDO $pdo, array $data): int
{
    $sql = "INSERT INTO products (name,price,stock) VALUES (:name, :price, :stock)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        "name" => trim((string)$data["name"]),
        "price" => (float)$data["price"],
        "stock" => (float)($data["stock"] ?? 0)
    ]);
    return (int)$pdo->lastInsertId();
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
} catch (PDOException $e) {
    respondError(500, "Error de conexión: " . $e->getMessage());
} catch (Exception $expection) {
    respondError(500, "Error interno: " . $expection->getMessage());
}
