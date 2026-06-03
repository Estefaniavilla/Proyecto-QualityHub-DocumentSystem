<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
require_once 'db.php'; // Asegúrate de que el nombre de tu archivo de conexión coincida

$response = ['success' => false, 'message' => ''];
try {
    $nombre = $_POST['nombre'] ?? '';
    $extension = $_POST['extension'] ?? '';
    $version = $_POST['version'] ?? '';
    $estado = $_POST['estado'] ?? 'Vigente';
    $nombre_fisico = $_POST['nombre_fisico'] ?? '';
    $codigo_iso = $_POST['codigo_iso'] ?? '';
    $fecha = $_POST['fecha_autorizacion'] ?? date('Y-m-d H:i:s');

    if (empty($codigo_iso)) throw new Exception("Código ISO requerido.");

    // Verifica si el documento ya está en PHP
    $stmt = $pdo->prepare("SELECT id FROM documentos WHERE codigo_iso = ?");
    $stmt->execute([$codigo_iso]);
    $existe = $stmt->fetch();

    if ($existe) {
        $stmt = $pdo->prepare("UPDATE documentos SET nombre=?, extension=?, version=?, estado=?, nombre_fisico=?, fecha_autorizacion=? WHERE codigo_iso=?");
        $stmt->execute([$nombre, $extension, $version, $estado, $nombre_fisico, $fecha, $codigo_iso]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO documentos (nombre, extension, version, estado, nombre_fisico, codigo_iso, fecha_autorizacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $extension, $version, $estado, $nombre_fisico, $codigo_iso, $fecha]);
    }
    $response['success'] = true;
    $response['message'] = "Sincronizado correctamente.";
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}
echo json_encode($response);
?>