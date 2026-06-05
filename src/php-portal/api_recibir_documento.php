<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Use POST.']);
    exit;
}

try {
    $nombre        = $_POST['nombre']        ?? '';
    $extension     = $_POST['extension']     ?? '';
    $estado        = $_POST['estado']        ?? 'Vigente';
    $nombre_fisico = $_POST['nombre_fisico'] ?? '';
    $codigo_iso    = $_POST['codigo_iso']    ?? '';
    $version       = $_POST['version']       ?? '1.0';

    if (empty($codigo_iso)) $codigo_iso = 'DOC-' . time();
    if (empty($nombre))     $nombre     = 'Documento Sin Nombre';
    if (empty($version))    $version    = '1.0';

    $ext_limpia = str_replace('.', '', $extension);

    $stmtCheck = $pdo->prepare("SELECT id FROM documentos WHERE codigo_iso = :codigo");
    $stmtCheck->execute([':codigo' => $codigo_iso]);

    if ($stmtCheck->fetchColumn()) {
        // ACTUALIZAR (usando la columna con acento)
        $stmtUpdate = $pdo->prepare("
            UPDATE documentos 
            SET titulo = :titulo, 
                tipo_archivo = :tipo, 
                estado = :estado, 
                nombre_fisico = :fisico, 
                version = :version,
                fecha_modificacion = CURRENT_TIMESTAMP,
                usuario_modificó = 'Sistema .NET' 
            WHERE codigo_iso = :codigo
        ");
        $stmtUpdate->execute([
            ':titulo'  => $nombre,
            ':tipo'    => $ext_limpia,
            ':estado'  => $estado,
            ':fisico'  => $nombre_fisico,
            ':version' => $version,
            ':codigo'  => $codigo_iso
        ]);
    } else {
        // INSERTAR (usando la columna con acento)
        $stmtInsert = $pdo->prepare("
            INSERT INTO documentos 
                (codigo_iso, titulo, tipo_archivo, estado, nombre_fisico, version, usuario_modificó, documento_ubicacion) 
            VALUES 
                (:codigo, :titulo, :tipo, :estado, :fisico, :version, 'Sistema .NET', 'General')
        ");
        $stmtInsert->execute([
            ':codigo'  => $codigo_iso,
            ':titulo'  => $nombre,
            ':tipo'    => $ext_limpia,
            ':estado'  => $estado,
            ':fisico'  => $nombre_fisico,
            ':version' => $version
        ]);
    }

    echo json_encode(['success' => true, 'mensaje' => 'Sincronizado en PHP con éxito']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>