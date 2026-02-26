<?php
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
function mergedProductData(array $existing, array $payload): array
{
    $merged = [];
    if (array_key_exists("name", $payload)) {
        $merged["name"] = trim((string)$payload["name"]);
    } else {
        $merged["name"] = trim((string)$existing["name"]);
    }
    if (array_key_exists("price", $payload)) {
        $merged["price"] = (float)$payload["price"];
    } else {
        $merged["price"] = (float)$existing["price"];
    }
    if (array_key_exists("stock", $payload)) {
        $merged["stock"] = (float)$payload["stock"];
    } else {
        $merged["stock"] = (float)$existing["stock"];
    }
    return $merged;
}