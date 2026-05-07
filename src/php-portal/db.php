<?php
// Configuración de conexión para el contenedor de Postgres
$host = "postgres_db"; // Nombre del contenedor en el docker-compose
$port = "5432";
$dbname = "quality_reports";
$user = "user_admin";
$password = "password123";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // echo "Conexión exitosa a Postgres"; 
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
