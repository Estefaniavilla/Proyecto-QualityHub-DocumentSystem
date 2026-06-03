<?php
require_once 'db.php'; // Usa tu archivo de conexión a Postgres

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Use POST.']);
    exit;
}

try {
    // 1. Recibimos el paquete desde C#
    $nombre        = $_POST['nombre'] ?? '';
    $extension     = $_POST['extension'] ?? '';
    $estado        = $_POST['estado'] ?? 'Vigente';
    $nombre_fisico = $_POST['nombre_fisico'] ?? '';
    $codigo_iso    = $_POST['codigo_iso'] ?? '';

    if (empty($codigo_iso) || empty($nombre)) throw new Exception("Faltan datos.");

    // 2. Limpiamos la extensión (quitamos el punto si lo trae)
    $ext_limpia = str_replace('.', '', $extension);

    // 3. Revisamos si ya existe en PHP
    $stmtCheck = $pdo->prepare("SELECT id FROM documentos WHERE codigo_iso = :codigo");
    $stmtCheck->execute([':codigo' => $codigo_iso]);
    
    if ($stmtCheck->fetchColumn()) {
        // Si existe, lo actualizamos (Nueva Versión)
        $stmtUpdate = $pdo->prepare("UPDATE documentos SET titulo = :titulo, tipo_archivo = :tipo, estado = :estado, nombre_fisico = :fisico, fecha_modificacion = CURRENT_TIMESTAMP WHERE codigo_iso = :codigo");
        $stmtUpdate->execute([':titulo' => $nombre, ':tipo' => $ext_limpia, ':estado' => $estado, ':fisico' => $nombre_fisico, ':codigo' => $codigo_iso]);
    } else {
        // Si no existe, lo creamos
        $stmtInsert = $pdo->prepare("INSERT INTO documentos (codigo_iso, titulo, tipo_archivo, estado, nombre_fisico, documento_ubicacion) VALUES (:codigo, :titulo, :tipo, :estado, :fisico, 'General')");
        $stmtInsert->execute([':codigo' => $codigo_iso, ':titulo' => $nombre, ':tipo' => $ext_limpia, ':estado' => $estado, ':fisico' => $nombre_fisico]);
    }

    echo json_encode(['success' => true, 'mensaje' => 'Sincronizado en PHP con éxito']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>