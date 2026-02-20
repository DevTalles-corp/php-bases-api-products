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

function storagePath(): string
{
    return __DIR__ . "/storage_products.json";
}
function loadProducts(): array
{
    $path = storagePath();
    if (!file_exists($path)) {
        $seed = [
            ["id" => 1, "name" => "Laptop", "price" => 1200.00, "stock" => 3],
            ["id" => 2, "name" => "Mouse", "price" => 25.00, "stock" => 15],
        ];
        $dir = dirname($path);
        if (!is_writable($dir)) {
            respondError(500, "No se puede crear el archivo de almacenamiento. 
                      La carpeta '$dir' no tiene permisos de escritura. 
                      Asigna permisos de escritura a la carpeta para continuar.");
        }
        file_put_contents($path, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $seed;
    }
    $content = file_get_contents($path);
    $data = json_decode($content ? $content : "[]", true);
    return is_array($data) ? $data : [];
}
function saveProducts(array $products): void
{
    $path = storagePath();
    file_put_contents($path, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod($path, 0666);
}
function finById(array $products, int $id): ?array
{
    foreach ($products as $product) {
        if ((int)$product["id"] === $id) {
            return $product;
        }
    }
    return null;
}
function nextId(array $products): int
{
    $maxId = 0;
    foreach ($products as $product) {
        $maxId = max($maxId, (int)($product["id"] ?? 0));
    }
    return $maxId + 1;
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
//Flujo principal (handlers)
[$resource, $resourceId] = resolveRoute($segments); //["products", 2] ,["products", null], [null, null]
if ($method === "GET" && $resourceId === null) {
    $products = loadProducts();
    respondJson(200, $products);
}
if ($method === "GET" && $resourceId !== null) {
    $products = loadProducts();
    $product = finById($products, $resourceId);
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
    $products = loadProducts();
    $newProduct =
        [
            "id" => nextId($products),
            "name" => trim((string)$payload["name"]),
            "price" => (float)$payload["price"],
            "stock" => (float)$payload["stock"]
        ];
    $products[] = $newProduct;
    saveProducts($products);
    respondJson(
        201,
        [
            "message" => "Producto creado correctamente",
            "data" => $newProduct
        ]
    );
}
