<?php

declare(strict_types=1);
require_once "Database.php";
try {
    $pdo = getConnection();
    echo "Conexión exitosa";
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
