<?php

$host = "postgres_db";
$port = "5432";
$dbname = "quality_reports";
$user = "user_admin";
$password = "password123";

try {

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Conexion exitosa a PostgreSQL";

} catch (PDOException $e) {

    die("Error de conexion: " . $e->getMessage());
}
