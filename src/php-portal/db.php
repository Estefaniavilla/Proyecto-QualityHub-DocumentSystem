<?php
$host = "postgres_db";
$port = "5432";
$dbname = "quality_reports"; // <- ESTE ES EL CAMBIO
$user = "user_admin";
$password = "password123";

try {

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
} ?>