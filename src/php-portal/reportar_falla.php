<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_iso = $_POST['codigo_iso'] ?? '';
    $sugerencia = $_POST['sugerencia'] ?? '';

    // 🔥 IMPORTANTE: Cambia "7083" por el puerto exacto en el que corre tu C# (Localhost)
// ✅ PHP habla con C# por nombre de servicio Docker
    $url_csharp = "http://csharp_app_container:8080/api/RecibirFalla";
    $ch = curl_init($url_csharp);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'codigoIso' => $codigo_iso,
        'sugerencia' => $sugerencia
    ]));
    
    // Ignoramos el certificado SSL por si tu localhost en C# marca error de "no seguro"
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    header('Content-Type: application/json');
    echo json_encode(['success' => ($httpcode == 200), 'respuesta' => $response]);
}
?>