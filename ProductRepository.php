<?php
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
function updateProduct(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE products SET name = :name, price=:price, stock=:stock WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        "id" => $id,
        "name" => trim((string)$data["name"]),
        "price" => (float)$data["price"],
        "stock" => (float)$data["stock"]
    ]);
    return $stmt->rowCount() > 0;
}
function deleteProduct(PDO $pdo, int $id): bool
{
    $sql = "DELETE FROM products WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["id" => $id]);
    return $stmt->rowCount() > 0;
}