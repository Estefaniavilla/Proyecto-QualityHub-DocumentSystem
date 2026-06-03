<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usuario_id'])) { http_response_code(403); exit('Acceso denegado'); }

$archivo   = basename($_GET['f'] ?? '');
$descargar = isset($_GET['download']) && $_GET['download'] == '1';

if (empty($archivo)) { http_response_code(400); exit('Archivo no especificado'); }

// Ruta física dentro del contenedor Docker (mapea a ./storage/actuales en tu host)
$ruta = __DIR__ . '/storage/actuales/' . $archivo;

if (!file_exists($ruta)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;">
    <h2 style="color:#dc2626;">&#x26A0; Archivo no encontrado</h2>
    <p style="color:#64748b;">El archivo <b>' . htmlspecialchars($archivo) . '</b> no existe en el servidor.</p>
    <p style="font-size:.85rem;color:#94a3b8;">Verifica que el Autorizador de C# haya copiado el archivo físico a la carpeta <code>storage/actuales/</code></p>
    </body></html>';
    exit;
}

// Detectar tipo MIME
$ext  = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls'  => 'application/vnd.ms-excel',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    default => 'application/octet-stream'
};

// Headers de descarga o visualización
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
if ($descargar) {
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
} else {
    header('Content-Disposition: inline; filename="' . $archivo . '"');
}
readfile($ruta);
exit;
?>