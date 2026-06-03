<?php
/**
 * ver_archivo.php
 * Conecta el almacenamiento de .NET con el portal PHP
 */

$archivo = basename($_GET['f'] ?? '');
$descargar = isset($_GET['download']) ? true : false;

if (empty($archivo)) {
    http_response_code(400);
    echo 'Archivo no especificado.';
    exit;
}

// =========================================================================
// 🔴 ¡ATENCIÓN! Cambia "estef" y "QualityDoc-Polyglot" por el nombre
// real de la carpeta donde tienes tu proyecto de C# en tu computadora.
// =========================================================================
$rutas = [
    __DIR__ . '/storage/actuales/' . $archivo,                             
    'C:/Users/estef/source/repos/QualityHub-DocumentSystem/storage/actuales/' . $archivo,
    'C:/Users/estef/QualityHub-DocumentSystem/storage/actuales/' . $archivo 
];

$rutaEncontrada = null;
foreach ($rutas as $r) {
    if (file_exists($r)) {
        $rutaEncontrada = $r;
        break;
    }
}

if (!$rutaEncontrada) {
    http_response_code(404);
    echo "<h2>Error 404</h2><p>El archivo <b>$archivo</b> no se encuentra físicamente en el storage de .NET.</p>";
    exit;
}

$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
$contentType = 'application/octet-stream';
if ($ext === 'pdf') $contentType = 'application/pdf';
if (in_array($ext, ['doc', 'docx'])) $contentType = 'application/msword';
if (in_array($ext, ['xls', 'xlsx'])) $contentType = 'application/vnd.ms-excel';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($rutaEncontrada));

if ($ext === 'pdf' && !$descargar) {
    // Si es PDF y es vista previa, mostrar en navegador
    header('Content-Disposition: inline; filename="' . $archivo . '"');
} else {
    // Si es Office o botón de descarga, forzar descarga
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
}

readfile($rutaEncontrada);
exit;
?>