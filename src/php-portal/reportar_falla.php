<?php
// ================================================
// reportar_falla.php
// Consume la API .NET de Estefanía
// Endpoint: POST /api/DocumentosApi/ReportarFalla
// ================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// ----- Control de seguridad -----
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Inicia sesión.']);
    exit;
}

// ----- Solo acepta POST -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ==========================================
// CONFIGURACIÓN DE LA API .NET (ESTEFANÍA)
// ==========================================
$API_URL = "https://mtw4dw87-7083.usw3.devtunnels.ms/api/DocumentosApi/ReportarFalla";

// ----- Recibir datos del AJAX del dashboard -----
$input = json_decode(file_get_contents('php://input'), true);

$documento_id = isset($input['DocumentoId']) ? (int)$input['DocumentoId'] : 0;
$comentarios  = isset($input['Comentarios']) ? trim($input['Comentarios']) : '';

// ----- Validaciones -----
if ($documento_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de documento inválido.']);
    exit;
}

if (empty($comentarios)) {
    http_response_code(400);
    echo json_encode(['error' => 'Los comentarios son obligatorios.']);
    exit;
}

// ==========================================
// ARMAR EL JSON EXACTO QUE PIDE LA API .NET
// ==========================================
$datos = [
    "DocumentoId" => $documento_id,
    "Comentarios" => $comentarios
];

// ==========================================
// ENVIAR A LA API .NET CON cURL
// ==========================================
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));

// ==============================================================
// ← AQUÍ ESTÁ EL CAMBIO: Se agregó 'X-Tunnel-Skip-Auto-Detect'
// para que Microsoft DevTunnels deje pasar la petición de PHP
// sin mostrar la página de advertencia del navegador
// ==============================================================
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Tunnel-Skip-Auto-Detect: 1'
]);

// Para que no se trabe con el certificado HTTPS del túnel
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Timeout de 15 segundos para no dejar colgado al usuario
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$respuesta   = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

// ==========================================
// REGISTRAR EN LOGS DE ACCESO (LOCAL)
// ==========================================
try {
    $nomina = $_SESSION['usuario_nomina'] ?? 'N/A';
    $stmt_log = $pdo->prepare("
        INSERT INTO logs_acceso (documento_id, nomina, accion, fecha_acceso)
        VALUES (:doc_id, :nomina, 'REPORTE_FALLA_API', NOW())
    ");
    $stmt_log->execute([
        ':doc_id' => $documento_id,
        ':nomina' => $nomina
    ]);
} catch (PDOException $e) {
    // Si falla el log, no detener la respuesta principal
}

// ==========================================
// RESPONDER AL DASHBOARD
// ==========================================
if ($curl_error) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'No se pudo conectar con la API de Calidad (.NET).',
        'detalle' => $curl_error  // Aquí veremos el error exacto si sigue fallando
    ]);
    exit;
}

if ($codigo_http == 200) {
    echo json_encode([
        'success'       => true,
        'mensaje'       => '¡Alerta enviada exitosamente al sistema de Calidad (.NET)!',
        'respuesta_api' => json_decode($respuesta, true) ?? $respuesta
    ]);
} else {
    http_response_code($codigo_http ?: 500);
    echo json_encode([
        'success'    => false,
        'error'      => 'La API de Calidad respondió con un error.',
        'codigo_http'=> $codigo_http,
        'respuesta'  => json_decode($respuesta, true) ?? $respuesta
    ]);
}
?>