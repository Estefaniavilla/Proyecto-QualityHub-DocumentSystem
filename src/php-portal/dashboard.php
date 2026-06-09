<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// ==========================================
// ENDPOINT AJAX: REGISTRO DE LOGS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_log'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $doc_id  = intval($_POST['doc_id']);
        $nomina  = $_SESSION['usuario_nomina'] ?? ($_SESSION['nomina'] ?? 'N/A');
        $accion  = $_POST['accion'] ?? 'LECTURA';
        $stmt = $pdo->prepare("INSERT INTO logs_acceso (documento_id, nomina, accion) VALUES (:doc_id, :nomina, :accion)");
        $stmt->execute([':doc_id' => $doc_id, ':nomina' => $nomina, ':accion' => $accion]);
        echo json_encode(['success' => true, 'mensaje' => 'Log registrado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// CONTROL DE SEGURIDAD
// ==========================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$nombre_usuario   = $_SESSION['usuario_nombre'];
$ubicacion_usuario = $_SESSION['usuario_ubicacion'];
$nomina_usuario   = $_SESSION['usuario_nomina'] ?? ($_SESSION['nomina'] ?? 'N/A');

// ==========================================
// CONTROL DE ROLES
// ==========================================
$es_super_admin = (strpos(strtolower($nombre_usuario), 'lizeth') !== false);
$es_juan        = (strpos(strtolower($nombre_usuario), 'juan')   !== false);

// ==========================================
// BUSCADOR AVANZADO (PYTHON / MONGODB)
// ==========================================
$busqueda_avanzada = isset($_GET['q']) ? trim($_GET['q']) : '';
$archivos_encontrados = null;
$mapa_snippets = [];
$error_msg = '';

if (!empty($busqueda_avanzada)) {
    // Usamos el nombre exacto de tu contenedor de Python
    $url = "http://python_busqueda_container:5000/api/buscar?nombre=" . urlencode($busqueda_avanzada);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seg maximo para no trabar PHP
    $respuesta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $respuesta) {
        $datos = json_decode($respuesta, true);
        if (isset($datos['documentos'])) {
            $archivos_encontrados = [];
            foreach ($datos['documentos'] as $doc) {
                $nombre_fisico = $doc['archivo_descarga'];
                $archivos_encontrados[] = $nombre_fisico;
                if (!empty($doc['snippet_match'])) {
                    $mapa_snippets[$nombre_fisico] = $doc['snippet_match'];
                }
            }
        }
    } else {
        $error_msg = "Aviso: No se pudo conectar con el motor de búsqueda profunda en Mongo.";
    }
}

// ==========================================
// PAGINACIÓN
// ==========================================
$por_pagina    = 5;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset        = ($pagina_actual - 1) * $por_pagina;
$total_paginas = 1;

// ==========================================
// VISTA ACTUAL
// ==========================================
$vista_actual = isset($_GET['vista']) ? trim($_GET['vista']) : 'inicio';

// ==========================================
// VARIABLES GLOBALES
// ==========================================
$todos_documentos = [];
$docs_recientes   = [];   
$logs_acceso      = [];
$estadisticas     = [];
$descargas        = [];
$almacen_datos    = [];

try {
    $base_ubicacion = str_replace(['ó','Ó'], 'o', $ubicacion_usuario);

    // ------------------------------------------
    // DOCUMENTOS RECIÉN AUTORIZADOS (para el inicio)
    // ------------------------------------------
    $stmt_recientes = $pdo->prepare("
        SELECT * FROM documentos
        WHERE estado IN ('Vigente','Autorizado')
        ORDER BY fecha_modificacion DESC
        LIMIT 5
    ");
    $stmt_recientes->execute();
    $docs_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------
    // VISOR DOCUMENTAL
    // ------------------------------------------
    if ($vista_actual === 'vigentes' || $vista_actual === 'obsoletos') {
        
        // --- INYECCIÓN DEL FILTRO MONGO ---
        $sql_filtro_mongo = "";
        $params_mongo = [];
        if (is_array($archivos_encontrados)) {
            if (empty($archivos_encontrados)) {
                $sql_filtro_mongo = " AND id = -1 "; // Falso, Python no encontró nada
            } else {
                $in_marks = [];
                foreach ($archivos_encontrados as $i => $file) {
                    $key = ":mongo_file_$i";
                    $in_marks[] = $key;
                    $params_mongo[$key] = $file;
                }
                $sql_filtro_mongo = " AND nombre_fisico IN (" . implode(',', $in_marks) . ") ";
            }
        }

        $condicion_estado = ($vista_actual === 'obsoletos')
            ? "='Obsoleto'"
            : "IN ('Vigente', 'En Revisión', 'En Fila', 'Autorizado')";

        if ($es_super_admin) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE estado $condicion_estado $sql_filtro_mongo");
            $stmt_count->execute($params_mongo);
            $total_registros = $stmt_count->fetchColumn();
            $total_paginas   = max(1, ceil($total_registros / $por_pagina));

            $stmt = $pdo->prepare("SELECT * FROM documentos WHERE estado $condicion_estado $sql_filtro_mongo ORDER BY fecha_modificacion DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit',  $por_pagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
            foreach ($params_mongo as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmt->execute();
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM documentos WHERE estado $condicion_estado AND (documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean) $sql_filtro_mongo ORDER BY fecha_modificacion DESC");
            $params = array_merge([
                ':ubicacion'       => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ], $params_mongo);
            $stmt->execute($params);
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    // ------------------------------------------
    // ALMACÉN DE REGISTROS
    // ------------------------------------------
    } elseif ($vista_actual === 'almacen') {
        $almacen_datos = [
            ['registro' => 'REG-2026-001', 'fecha' => '2026-05-10', 'descripcion' => 'Reporte de Auditoría Interna',    'responsable' => 'Lizeth'],
            ['registro' => 'REG-2026-002', 'fecha' => '2026-05-12', 'descripcion' => 'Revisión por la Dirección',        'responsable' => 'Juan'],
            ['registro' => 'REG-2026-003', 'fecha' => '2026-05-15', 'descripcion' => 'Minuta de Comité de Calidad',      'responsable' => 'Lizeth'],
        ];

    // ------------------------------------------
    // DESCARGAS RECIENTES
    // ------------------------------------------
    } elseif ($vista_actual === 'descargas') {
        $stmt = $pdo->prepare("
            SELECT l.id, l.documento_id, l.nomina, l.accion, l.fecha_acceso,
                   d.titulo AS documento_nombre, d.tipo_archivo, d.nombre_fisico, d.codigo_iso
            FROM logs_acceso l
            LEFT JOIN documentos d ON l.documento_id = d.id
            WHERE l.nomina = :nomina AND l.accion = 'DESCARGA'
            ORDER BY l.fecha_acceso DESC
        ");
        $stmt->execute([':nomina' => $nomina_usuario]);
        $descargas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------
    // LOGS DE ACCESO
    // ------------------------------------------
    } elseif ($vista_actual === 'logs') {
        $stmt = $pdo->prepare("
            SELECT l.id, l.documento_id, l.nomina, l.accion, l.fecha_acceso,
                   d.titulo AS documento_nombre, d.codigo_iso, d.tipo_archivo
            FROM logs_acceso l
            LEFT JOIN documentos d ON l.documento_id = d.id
            WHERE l.nomina = :nomina
            ORDER BY l.fecha_acceso DESC
        ");
        $stmt->execute([':nomina' => $nomina_usuario]);
        $logs_acceso = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------
    // REPORTES
    // ------------------------------------------
    } elseif ($vista_actual === 'reportes') {
        if ($es_super_admin) {
            $stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos GROUP BY estado");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos WHERE documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean GROUP BY estado");
            $stmt->execute([
                ':ubicacion'       => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
        }
        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    if (empty($error_msg)) {
        $error_msg = "Error en dashboard: " . $e->getMessage();
    }
}

// ==========================================
// HELPER: icono por extensión
// ==========================================
function iconoPorTipo($tipo) {
    $t = strtolower($tipo ?? '');
    if ($t === 'pdf')                    return 'fa-file-pdf';
    if (in_array($t, ['docx','doc']))    return 'fa-file-word';
    if (in_array($t, ['xlsx','xls']))    return 'fa-file-excel';
    return 'fa-file';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityHub - DocumentSystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --main-bg:    #f8fafc;
            --border:     #e2e8f0;
            --text-dark:  #0f172a;
            --text-muted: #64748b;
            --primary:    #4f46e5;
            --primary-lt: #e0e7ff;
            --orange:     #f97316;
            --orange-lt:  #ffedd5;
            --green:      #16a34a;
            --green-lt:   #dcfce7;
        }

        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; font-family:'Inter',sans-serif; background:var(--main-bg); display:flex; height:100vh; overflow:hidden; color:var(--text-dark); }

        /* ── SIDEBAR ── */
        .sidebar { width:270px; background:#1a2536; color:#fff; display:flex; flex-direction:column; justify-content:space-between; flex-shrink:0; }
        .sidebar-top { padding:25px 16px; }
        .brand { display:flex; align-items:center; gap:10px; font-size:1.1rem; font-weight:700; margin-bottom:30px; line-height:1.3; }
        .brand i { color:#818cf8; font-size:1.4rem; flex-shrink:0; }
        .menu-section-title { font-size:.68rem; text-transform:uppercase; color:#4b5563; margin:24px 0 8px 8px; font-weight:700; letter-spacing:.05em; }
        .menu-link { display:flex; align-items:center; gap:12px; padding:12px 14px 12px 32px; border-radius:0 25px 25px 0; color:#9ca3af; text-decoration:none; transition:.2s; margin-bottom:4px; font-size:.9rem; font-weight:500; margin-left:-16px; }
        .menu-link:hover, .menu-link.active { background:var(--primary); color:#fff; }
        .sidebar-footer { padding:16px; border-top:1px solid #232f42; }
        .btn-logout-box { background:rgba(239,68,68,.1); color:#f87171; display:flex; justify-content:center; align-items:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:600; transition:.2s; }
        .btn-logout-box:hover { background:#ef4444; color:#fff; }

        /* ── MAIN ── */
        .main-content { flex-grow:1; display:flex; flex-direction:column; overflow:hidden; background:#fff; }
        .topbar { height:65px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 40px; flex-shrink:0; }
        .breadcrumbs { font-size:.85rem; color:var(--text-muted); font-weight:500; }
        .user-profile { display:flex; align-items:center; gap:12px; }
        .user-name { font-weight:600; font-size:.9rem; color:#334155; }
        .user-avatar { width:36px; height:36px; border-radius:50%; background:var(--primary); color:#fff; display:flex; justify-content:center; align-items:center; font-weight:600; font-size:.85rem; }
        .content-body { padding:40px; overflow-y:auto; flex-grow:1; }
        .content-header h1 { margin:0; font-size:1.75rem; font-weight:700; color:#1e293b; }
        .content-header p  { color:var(--text-muted); margin-top:5px; font-size:.95rem; }

        /* ── TABLAS ── */
        .table-container { margin-top:25px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; text-align:left; }
        .table-container th { padding:14px 12px; font-size:.73rem; text-transform:uppercase; color:#64748b; font-weight:700; border-bottom:2px solid var(--border); }
        .table-container td { padding:14px 12px; border-bottom:1px solid #f1f5f9; font-size:.9rem; vertical-align:middle; }
        .doc-code  { color:#64748b; font-weight:500; font-size:.85rem; }
        .doc-main  { display:flex; gap:12px; align-items:center; }
        .doc-title { display:block; font-weight:600; color:#1e293b; }
        .doc-filename { font-size:.78rem; color:var(--text-muted); }
        .doc-icon  { font-size:1.5rem; }
        .fa-file-pdf   { color:#ef4444; }
        .fa-file-word  { color:#2563eb; }
        .fa-file-excel { color:#16a34a; }
        .badge-ext    { font-size:.73rem; font-weight:700; color:#475569; background:#f1f5f9; padding:3px 8px; border-radius:4px; }
        .badge-status { padding:5px 12px; border-radius:6px; font-size:.73rem; font-weight:600; display:inline-block; }
        .en-revision  { background:#fef3c7; color:#d97706; }
        .en-fila      { background:#e0f2fe; color:#0284c7; }
        .autorizado   { background:var(--green-lt); color:var(--green); }
        .vigente      { background:var(--green-lt); color:var(--green); }
        .obsoleto     { background:#fee2e2; color:#dc2626; }
        .badge-lectura  { background:#e0f2fe;  color:#0369a1; }
        .badge-descarga { background:var(--green-lt); color:#15803d; }

        /* ── BOTONES ACCIÓN ── */
        .actions-cell { display:flex; gap:8px; }
        .btn-action { width:32px; height:32px; border-radius:6px; display:flex; justify-content:center; align-items:center; text-decoration:none; font-size:.85rem; transition:.2s; border:none; cursor:pointer; }
        .btn-view-doc     { background:#e8f5e9; color:#2e7d32; }
        .btn-view-doc:hover    { background:#2e7d32; color:#fff; }
        .btn-download-doc { background:#e3f2fd; color:#1565c0; }
        .btn-download-doc:hover { background:#1565c0; color:#fff; }
        .btn-report-doc   { background:#ffebee; color:#c62828; }
        .btn-report-doc:hover   { background:#c62828; color:#fff; }

        /* ── WIDGETS INICIO ── */
        .widget-grid { margin-top:25px; display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px; }
        .widget-card { background:#fff; padding:25px; border-radius:14px; border:1px solid var(--border); box-shadow:0 4px 6px -1px rgba(0,0,0,.05); }
        .widget-header { display:flex; align-items:center; gap:15px; margin-bottom:12px; }
        .widget-icon { width:48px; height:48px; border-radius:10px; display:flex; justify-content:center; align-items:center; font-size:1.4rem; flex-shrink:0; }

        /* ── TABLA DE DOCUMENTOS RECIENTES (INICIO) ── */
        .recientes-wrapper { margin-top:30px; background:#fff; border-radius:14px; border:1px solid var(--border); overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,.05); }
        .recientes-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border); background:#f8fafc; }
        .recientes-header h3 { margin:0; font-size:1rem; font-weight:700; color:#1e293b; }
        .badge-nuevo { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; font-size:.65rem; font-weight:700; padding:3px 9px; border-radius:20px; letter-spacing:.5px; animation:pulseNew 2s infinite; }
        @keyframes pulseNew { 0%,100%{box-shadow:0 0 0 0 rgba(79,70,229,.4);} 50%{box-shadow:0 0 0 6px rgba(79,70,229,0);} }
        .recientes-table { width:100%; border-collapse:collapse; }
        .recientes-table th { padding:12px 20px; font-size:.7rem; text-transform:uppercase; color:#64748b; font-weight:700; border-bottom:1px solid var(--border); background:#f8fafc; }
        .recientes-table td { padding:14px 20px; border-bottom:1px solid #f8fafc; font-size:.88rem; vertical-align:middle; }
        .recientes-table tr:last-child td { border-bottom:none; }
        .recientes-table tr:hover td { background:#f8fafc; }
        .dot-live { width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block; margin-right:6px; animation:blink 1.5s infinite; }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.3;} }

        /* ── NOTICIAS ── */
        .news-header-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:25px; }
        .tabs-container { display:flex; gap:25px; border-bottom:1px solid var(--border); }
        .tab { padding:10px 0; color:#64748b; font-weight:600; font-size:.95rem; cursor:pointer; border-bottom:2px solid transparent; transition:.2s; }
        .tab:hover { color:#334155; }
        .tab.active { color:var(--primary); border-bottom:2px solid var(--primary); }
        .news-list { background:#fff; border-radius:0 0 12px 12px; border:1px solid var(--border); border-top:none; padding:10px 30px; }
        .news-item { display:flex; align-items:center; padding:22px 0; border-bottom:1px solid #f1f5f9; }
        .news-item:last-child { border-bottom:none; }
        .news-icon-box { width:45px; height:45px; border-radius:12px; display:flex; justify-content:center; align-items:center; font-size:1.3rem; margin-right:22px; flex-shrink:0; }
        .bg-blue   { background:var(--primary-lt); color:var(--primary); }
        .bg-orange { background:var(--orange-lt);  color:var(--orange);  }
        .bg-green  { background:var(--green-lt);   color:var(--green);   }
        .news-info { flex-grow:1; }
        .badge-type { font-size:.65rem; font-weight:800; padding:3px 8px; border-radius:4px; margin-bottom:6px; display:inline-block; letter-spacing:.05em; }
        .badge-type.blue   { color:var(--primary); background:var(--primary-lt); }
        .badge-type.orange { color:var(--orange);  background:var(--orange-lt);  }
        .badge-type.green  { color:var(--green);   background:var(--green-lt);   }
        .news-title { font-size:1rem; font-weight:700; color:#1e293b; margin:0 0 4px; }
        .news-desc  { font-size:.85rem; color:#64748b; margin:0; }
        .news-meta  { text-align:left; margin-right:35px; font-size:.85rem; color:#64748b; width:145px; flex-shrink:0; }
        .news-meta strong { display:block; color:#334155; font-weight:500; margin-top:2px; }
        .news-action { color:#818cf8; background:var(--primary-lt); width:35px; height:35px; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer; transition:.2s; border:none; text-decoration:none; flex-shrink:0; }
        .news-action:hover { background:var(--primary); color:#fff; }

        /* ── DESCARGAS / LOGS ── */
        .descargas-wrapper { background:#fff; border-radius:12px; border:1px solid var(--border); padding:5px 25px 25px; margin-top:25px; overflow-x:auto; }
        .descargas-table { width:100%; border-collapse:collapse; text-align:left; }
        .descargas-table th { color:var(--primary); border-bottom:1px solid var(--border); font-size:.73rem; letter-spacing:.05em; padding:14px 12px; text-transform:uppercase; font-weight:700; }
        .descargas-table td { font-size:.88rem; font-weight:500; color:#334155; padding:14px 12px; border-bottom:1px solid #f1f5f9; }
        .doc-icon-small { font-size:1.1rem; margin-right:8px; }

        /* ── MODAL ── */
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(15,23,42,.6); backdrop-filter:blur(4px); justify-content:center; align-items:center; }
        .modal-content { background:#fff; padding:30px; border-radius:14px; width:520px; max-width:92%; box-shadow:0 20px 25px -5px rgba(0,0,0,.15); }
        .modal-view-content { background:#fff; border-radius:14px; width:87%; height:88%; display:flex; flex-direction:column; overflow:hidden; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:15px 25px; border-bottom:1px solid var(--border); }
        .modal-header h3 { margin:0; font-size:1.15rem; font-weight:700; }
        .close-btn { font-size:1.5rem; cursor:pointer; color:var(--text-muted); background:none; border:none; line-height:1; }
        .close-btn:hover { color:#000; }
        textarea { width:100%; height:120px; padding:12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; margin:15px 0; resize:none; }
        .btn-submit { background:var(--primary); color:#fff; border:none; padding:12px 20px; border-radius:7px; cursor:pointer; font-weight:600; width:100%; font-size:.95rem; transition:.2s; }
        .btn-submit:hover { background:#3730a3; }

        /* ── PAGINACIÓN ── */
        .pagination { display:flex; justify-content:center; gap:5px; margin-top:25px; }
        .pagination a { padding:8px 14px; border:1px solid var(--border); color:#334155; text-decoration:none; border-radius:6px; font-size:.85rem; font-weight:500; transition:.2s; }
        .pagination a:hover, .pagination a.active { background:#0284c7; color:#fff; border-color:#0284c7; }

        /* ── ESTADOS VACÍOS ── */
        .error-banner { background:#fee2e2; color:#991b1b; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-size:.9rem; }
        .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
        .empty-state i { font-size:3rem; margin-bottom:15px; display:block; }
        .empty-state h3 { color:#64748b; margin:0 0 8px; }
        .empty-state p  { margin:0; font-size:.9rem; }

        /* ── NOTIFICACIÓN TOAST ── */
        .toast-qh { position:fixed; bottom:28px; right:28px; background:#1e293b; color:#fff; padding:14px 22px; border-radius:12px; display:flex; align-items:center; gap:12px; font-size:.9rem; font-weight:500; box-shadow:0 10px 25px rgba(0,0,0,.25); z-index:9999; transform:translateY(100px); opacity:0; transition:all .4s cubic-bezier(.175,.885,.32,1.275); pointer-events:none; }
        .toast-qh.show { transform:translateY(0); opacity:1; }
        .toast-qh i { font-size:1.2rem; color:#10b981; }
    </style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="brand">
            <i class="fa-solid fa-shield-halved"></i>
            QualityHub<br>DocumentSystem
        </div>

        <div class="menu-section-title">Módulos</div>
        <a href="?vista=inicio"    class="menu-link <?= $vista_actual==='inicio'   ?'active':'' ?>"><i class="fa-solid fa-house"></i> Inicio</a>
        <a href="?vista=vigentes"  class="menu-link <?= in_array($vista_actual,['vigentes','obsoletos'])?'active':'' ?>"><i class="fa-solid fa-eye"></i> Visor Documental</a>
        <a href="?vista=almacen"   class="menu-link <?= $vista_actual==='almacen'  ?'active':'' ?>"><i class="fa-solid fa-box-archive"></i> Almacén de Registros</a>
        <a href="?vista=noticias"  class="menu-link <?= $vista_actual==='noticias' ?'active':'' ?>"><i class="fa-solid fa-newspaper"></i> Noticias y Avisos</a>

        <div class="menu-section-title">Operaciones</div>
        <a href="?vista=descargas" class="menu-link <?= $vista_actual==='descargas'?'active':'' ?>"><i class="fa-solid fa-cloud-arrow-down"></i> Descargas Recientes</a>
        <a href="?vista=logs"      class="menu-link <?= $vista_actual==='logs'     ?'active':'' ?>"><i class="fa-solid fa-bolt"></i> Logs de Acceso</a>
        <a href="?vista=reportes"  class="menu-link <?= $vista_actual==='reportes' ?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Reportes y Métricas</a>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout-box"><i class="fa-solid fa-arrow-right-from-bracket" style="margin-right:8px;"></i> Cerrar Sesión</a>
    </div>
</div>

<!-- ============ CONTENIDO PRINCIPAL ============ -->
<div class="main-content">
    <div class="topbar">
        <div class="breadcrumbs">Dashboard /
            <?php
                $nombres_vista = [
                    'inicio'    => 'Inicio',     'vigentes'  => 'Visor Documental',
                    'obsoletos' => 'Visor Documental',       'almacen'   => 'Almacén de Registros',
                    'noticias'  => 'Noticias y Avisos',      'descargas' => 'Descargas Recientes',
                    'logs'      => 'Logs de Acceso',         'reportes'  => 'Reportes y Métricas',
                ];
                echo $nombres_vista[$vista_actual] ?? ucfirst($vista_actual);
            ?>
        </div>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars($nombre_usuario) ?> (<?= htmlspecialchars($ubicacion_usuario) ?>)</span>
            <div class="user-avatar"><?= strtoupper(substr($nombre_usuario, 0, 2)) ?></div>
        </div>
    </div>

    <div class="content-body">

        <?php if ($error_msg): ?>
            <div class="error-banner"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             INICIO
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'inicio'): ?>
            <div class="content-header">
                <h1>Bienvenido, <?= htmlspecialchars(explode(' ', $nombre_usuario)[0]) ?> 👋</h1>
                <p>Sistema Centralizado de Gestión Documental y Calidad Corporativa — QualityHub .NET + PHP</p>
            </div>

            <div class="widget-grid">
                <!-- Widget Misión -->
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background:var(--primary-lt);color:var(--primary);"><i class="fa-solid fa-bullseye"></i></div>
                        <div><h3 style="margin:0;font-size:1.05rem;">Nuestra Misión</h3></div>
                    </div>
                    <p style="color:#475569;font-size:.9rem;line-height:1.6;margin:0;">Garantizar la excelencia operativa a través de la correcta gestión y distribución de la información documentada.</p>
                </div>
                <!-- Widget Estado API -->
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background:var(--green-lt);color:var(--green);"><i class="fa-solid fa-plug-circle-check"></i></div>
                        <div><h3 style="margin:0;font-size:1.05rem;">Conexión con .NET</h3></div>
                    </div>
                    <p style="color:#475569;font-size:.9rem;line-height:1.6;margin:0 0 10px;">
                        <span class="dot-live"></span> Canal activo — Los documentos autorizados desde QualityHub .NET llegan automáticamente a este portal.
                    </p>
                    <span style="font-size:.78rem;color:var(--green);font-weight:600;"><i class="fa-solid fa-arrow-down me-1"></i> <?= count($docs_recientes) ?> documento(s) recibido(s)</span>
                </div>
                <!-- Widget Accesos Rápidos -->
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div><h3 style="margin:0;font-size:1.05rem;">Accesos Rápidos</h3></div>
                    </div>
                    <ul style="padding-left:18px;color:#475569;font-size:.9rem;line-height:2;margin:0;">
                        <li><a href="?vista=vigentes"  style="color:var(--primary);text-decoration:none;font-weight:500;">Visor Documental</a></li>
                        <li><a href="?vista=descargas" style="color:var(--primary);text-decoration:none;font-weight:500;">Mis Descargas Recientes</a></li>
                        <li><a href="?vista=logs"      style="color:var(--primary);text-decoration:none;font-weight:500;">Mis Logs de Acceso</a></li>
                    </ul>
                </div>
            </div>

            <div class="recientes-wrapper">
                <div class="recientes-header">
                    <h3><i class="fa-solid fa-file-circle-check me-2" style="color:var(--green);"></i>Documentos Autorizados Recientemente</h3>
                    <span class="badge-nuevo">&#9679; EN VIVO — desde QualityHub .NET</span>
                </div>
                <?php if (empty($docs_recientes)): ?>
                    <div class="empty-state" style="padding:40px 20px;">
                        <i class="fa-solid fa-inbox" style="font-size:2.5rem;color:#cbd5e1;margin-bottom:12px;display:block;"></i>
                        <h3 style="font-size:1rem;color:#64748b;">Aún no hay documentos autorizados</h3>
                        <p style="font-size:.85rem;">Cuando el Autorizador libere un documento en QualityHub .NET, aparecerá aquí automáticamente.</p>
                    </div>
                <?php else: ?>
                    <table class="recientes-table">
                        <thead>
                            <tr>
                                <th>Código ISO</th>
                                <th>Nombre del Documento</th>
                                <th>Extensión</th>
                                <th>Estado</th>
                                <th>Fecha de Liberación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($docs_recientes as $dr):
                                $icono_dr = iconoPorTipo($dr['tipo_archivo']);
                            ?>
                            <tr>
                                <td><span class="doc-code"><?= htmlspecialchars($dr['codigo_iso'] ?? 'N/A') ?></span></td>
                                <td>
                                    <div class="doc-main">
                                        <i class="fa-solid <?= $icono_dr ?> doc-icon" style="font-size:1.3rem;"></i>
                                        <div>
                                            <span class="doc-title"><?= htmlspecialchars($dr['titulo']) ?></span>
                                            <span class="doc-filename"><?= htmlspecialchars($dr['nombre_fisico']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge-ext"><?= strtoupper($dr['tipo_archivo'] ?? '') ?></span></td>
                                <td>
                                    <span class="dot-live"></span>
                                    <span class="badge-status vigente">Vigente</span>
                                </td>
                                <td style="color:var(--text-muted);font-size:.83rem;">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('d/m/Y H:i', strtotime($dr['fecha_modificacion'])) ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button onclick="verDocumento(<?= $dr['id'] ?>, '<?= htmlspecialchars($dr['nombre_fisico']) ?>', '<?= strtolower($dr['tipo_archivo']) ?>')" class="btn-action btn-view-doc" title="Ver"><i class="fa-regular fa-eye"></i></button>
                                        <a href="ver_archivo.php?f=<?= htmlspecialchars($dr['nombre_fisico']) ?>&download=1" onclick="registrarLog(<?= $dr['id'] ?>, 'DESCARGA')" class="btn-action btn-download-doc" title="Descargar"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             VISOR DOCUMENTAL
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'vigentes' || $vista_actual === 'obsoletos'): ?>
            <div class="content-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1>Visor Documental Corporativo</h1>
                    <p><?= $es_super_admin ? 'Panel Global Completo' : 'Repositorio destinado a: ' . htmlspecialchars($ubicacion_usuario) ?></p>
                    <div style="margin-top:12px;">
                        <a href="?vista=vigentes"  style="text-decoration:none;color:<?= $vista_actual==='vigentes'?'var(--primary)':'#64748b' ?>;font-weight:600;margin-right:18px;"><i class="fa-solid fa-file-circle-check me-1"></i>Documentos Vigentes</a>
                        <a href="?vista=obsoletos" style="text-decoration:none;color:<?= $vista_actual==='obsoletos'?'var(--primary)':'#64748b' ?>;font-weight:600;"><i class="fa-solid fa-box-archive me-1"></i>Historial Obsoletos</a>
                    </div>
                </div>
                
                <!-- NUEVO: BUSCADOR MONGODB -->
                <form method="GET" action="" style="display:flex; gap:10px; background:#fff; border:1px solid var(--border); padding:8px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <input type="hidden" name="vista" value="<?= htmlspecialchars($vista_actual) ?>">
                    <div style="position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 12px; color: #94a3b8;"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($busqueda_avanzada) ?>" placeholder="Buscar dentro de documentos..." style="border:none; background:#f8fafc; border-radius:6px; padding:10px 10px 10px 38px; width:300px; outline:none; font-family:inherit; font-size:0.9rem; border:1px solid #e2e8f0;">
                    </div>
                    <button type="submit" style="background:var(--primary); color:#fff; border:none; padding:0 22px; border-radius:6px; font-weight:600; cursor:pointer; transition:0.2s;">Buscar</button>
                    <?php if (!empty($busqueda_avanzada)): ?>
                        <a href="?vista=<?= htmlspecialchars($vista_actual) ?>" style="display:flex; align-items:center; justify-content:center; background:#fee2e2; color:#ef4444; text-decoration:none; padding:0 15px; border-radius:6px; font-weight:600;" title="Limpiar Búsqueda"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead><tr><th>Código</th><th>Nombre del Archivo</th><th>Extensión</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if (empty($todos_documentos)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fa-solid fa-folder-open"></i>
                                <h3>Sin documentos</h3>
                                <p><?= !empty($busqueda_avanzada) ? 'No se encontraron coincidencias en el motor de búsqueda.' : 'No hay documentos registrados para esta vista.' ?></p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($todos_documentos as $doc):
                            $icono     = iconoPorTipo($doc['tipo_archivo']);
                            $est       = strtolower($doc['estado']);
                            $cls_est   = 'en-revision';
                            if (in_array($est, ['vigente','autorizado'])) $cls_est = 'autorizado';
                            if ($est === 'en fila')  $cls_est = 'en-fila';
                            if ($est === 'obsoleto') $cls_est = 'obsoleto';
                        ?>
                        <tr>
                            <td><span class="doc-code"><?= htmlspecialchars($doc['codigo_iso']) ?></span></td>
                            <td>
                                <div class="doc-main">
                                    <i class="fa-solid <?= $icono ?> doc-icon"></i>
                                    <div>
                                        <span class="doc-title"><?= htmlspecialchars($doc['titulo']) ?></span>
                                        <span class="doc-filename"><?= htmlspecialchars($doc['nombre_fisico']) ?></span>
                                        
                                        <!-- NUEVO: MOSTRAR SNIPPET DE BÚSQUEDA -->
                                        <?php if (isset($mapa_snippets[$doc['nombre_fisico']])): ?>
                                            <div style="font-size: 0.78rem; color: #475569; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 12px; margin-top: 6px; border-radius: 0 6px 6px 0; max-width: 450px; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                                <?= $mapa_snippets[$doc['nombre_fisico']] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge-ext"><?= strtoupper($doc['tipo_archivo']) ?></span></td>
                            <td><span class="badge-status <?= $cls_est ?>"><?= htmlspecialchars($doc['estado']) ?></span></td>
                            <td class="actions-cell">
                                <button onclick="verDocumento(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['nombre_fisico']) ?>', '<?= strtolower($doc['tipo_archivo']) ?>')" class="btn-action btn-view-doc" title="Ver"><i class="fa-regular fa-eye"></i></button>
                                <a href="ver_archivo.php?f=<?= htmlspecialchars($doc['nombre_fisico']) ?>&download=1" onclick="registrarLog(<?= $doc['id'] ?>, 'DESCARGA')" class="btn-action btn-download-doc" title="Descargar"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                               <button onclick="abrirModalReporte('<?= $doc['codigo_iso'] ?>')" class="btn-action btn-report-doc" title="Reportar Falla"><i class="fa-solid fa-paper-plane"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($es_super_admin && $total_paginas > 1 && empty($busqueda_avanzada)): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?vista=<?= $vista_actual ?>&p=<?= $i ?>" class="<?= $pagina_actual===$i?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             ALMACÉN DE REGISTROS
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'almacen'): ?>
            <div class="content-header"><h1>Almacén de Registros</h1><p>Historial y control de los registros almacenados del Sistema de Gestión</p></div>
            <div class="descargas-wrapper">
                <table class="descargas-table">
                    <thead><tr><th>Nº de Registro</th><th>Fecha</th><th>Descripción</th><th>Responsable</th></tr></thead>
                    <tbody>
                    <?php foreach ($almacen_datos as $a): ?>
                        <tr>
                            <td><i class="fa-solid fa-file-signature doc-icon-small"></i><?= htmlspecialchars($a['registro']) ?></td>
                            <td><?= htmlspecialchars($a['fecha']) ?></td>
                            <td><?= htmlspecialchars($a['descripcion']) ?></td>
                            <td><?= htmlspecialchars($a['responsable']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             DESCARGAS RECIENTES
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'descargas'): ?>
            <div class="content-header">
                <h1>Descargas Recientes</h1>
                <p>Historial de documentos descargados. Nómina: <strong><?= htmlspecialchars($nomina_usuario) ?></strong></p>
            </div>
            <div class="descargas-wrapper">
                <table class="descargas-table">
                    <thead><tr><th>Documento</th><th>Código ISO</th><th>Fecha Descarga</th><th>Nómina</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php if (empty($descargas)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-cloud-arrow-down"></i><h3>Sin descargas registradas</h3><p>Ve al Visor Documental y descarga un archivo para que aparezca aquí.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($descargas as $d):
                            $icono_d = iconoPorTipo($d['tipo_archivo'] ?? 'pdf');
                        ?>
                        <tr>
                            <td><i class="fa-solid <?= $icono_d ?> doc-icon-small"></i><?= htmlspecialchars($d['documento_nombre'] ?? 'Documento no encontrado') ?></td>
                            <td><span class="doc-code"><?= htmlspecialchars($d['codigo_iso'] ?? 'N/A') ?></span></td>
                            <td><?= date('d/m/Y h:i A', strtotime($d['fecha_acceso'])) ?></td>
                            <td><?= htmlspecialchars($d['nomina']) ?></td>
                            <td>
                                <?php if (!empty($d['nombre_fisico'])): ?>
                                    <a href="ver_archivo.php?f=<?= htmlspecialchars($d['nombre_fisico']) ?>&download=1" class="btn-action btn-download-doc" title="Descargar de nuevo"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             LOGS DE ACCESO
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'logs'): ?>
            <div class="content-header">
                <h1>Trazabilidad y Logs de Acceso</h1>
                <p>Historial de todas tus interacciones con el repositorio. Nómina: <strong><?= htmlspecialchars($nomina_usuario) ?></strong></p>
            </div>
            <div class="table-container">
                <table>
                    <thead><tr><th>Código Doc</th><th>Documento Consultado</th><th>Tipo</th><th>Fecha y Hora</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php if (empty($logs_acceso)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-bolt"></i><h3>Sin logs registrados</h3><p>Visita el Visor Documental para generar trazabilidad.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs_acceso as $log):
                            $icono_l    = iconoPorTipo($log['tipo_archivo'] ?? 'pdf');
                            $accion_cls = (strtoupper($log['accion']) === 'DESCARGA') ? 'badge-descarga' : 'badge-lectura';
                        ?>
                        <tr>
                            <td><span class="doc-code"><?= htmlspecialchars($log['codigo_iso'] ?? 'N/A') ?></span></td>
                            <td><div class="doc-main"><i class="fa-solid <?= $icono_l ?> doc-icon" style="font-size:1.2rem;"></i><strong><?= htmlspecialchars($log['documento_nombre'] ?? 'Documento eliminado') ?></strong></div></td>
                            <td><span class="badge-ext"><?= strtoupper($log['tipo_archivo'] ?? '') ?></span></td>
                            <td style="font-size:.82rem;color:var(--text-muted);"><?= date('d/m/Y H:i:s', strtotime($log['fecha_acceso'])) ?></td>
                            <td><span class="badge-status <?= $accion_cls ?>"><?= htmlspecialchars($log['accion']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             NOTICIAS Y AVISOS
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'noticias'): ?>
            <div class="news-header-top">
                <div class="content-header" style="margin:0;"><h1>Noticias y Avisos</h1><p>Mantente informado sobre novedades y actualizaciones corporativas</p></div>
            </div>
            <div class="tabs-container">
                <div class="tab active" onclick="changeNewsTab(this,'TODAS')">Todas</div>
                <div class="tab" onclick="changeNewsTab(this,'NOTICIA')">Noticias</div>
                <div class="tab" onclick="changeNewsTab(this,'AVISO')">Avisos</div>
                <div class="tab" onclick="changeNewsTab(this,'ACTUALIZACIÓN')">Actualizaciones</div>
            </div>
            <div class="news-list">
                <?php foreach ($docs_recientes as $dr): ?>
                <div class="news-item" data-type="ACTUALIZACIÓN">
                    <div class="news-icon-box bg-green"><i class="fa-solid fa-file-circle-check"></i></div>
                    <div class="news-info">
                        <span class="badge-type green">ACTUALIZACIÓN DOCUMENTAL</span>
                        <h4 class="news-title">Nuevo documento autorizado: <?= htmlspecialchars($dr['titulo']) ?></h4>
                        <p class="news-desc">Código: <?= htmlspecialchars($dr['codigo_iso'] ?? 'N/A') ?> — Liberado desde QualityHub .NET y disponible en el Visor Documental.</p>
                    </div>
                    <div class="news-meta"><?= date('d/m/Y', strtotime($dr['fecha_modificacion'])) ?><br><strong>QualityHub .NET</strong></div>
                    <button onclick="verDocumento(<?= $dr['id'] ?>, '<?= htmlspecialchars($dr['nombre_fisico']) ?>', '<?= strtolower($dr['tipo_archivo']) ?>')" class="news-action" title="Ver documento"><i class="fa-regular fa-eye"></i></button>
                </div>
                <?php endforeach; ?>

                <div class="news-item" data-type="NOTICIA">
                    <div class="news-icon-box bg-blue"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="news-info">
                        <span class="badge-type blue">NOTICIA</span>
                        <h4 class="news-title">Nueva Versión del Manual de Calidad</h4>
                        <p class="news-desc">Se ha publicado la versión 3.0 del Manual de Calidad. Revisa los cambios importantes.</p>
                    </div>
                    <div class="news-meta">15/05/2026<br><strong>Dirección General</strong></div>
                    <a href="#" class="news-action"><i class="fa-regular fa-eye"></i></a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             REPORTES Y ESTADÍSTICAS
        ════════════════════════════════════════════ -->
        <?php if ($vista_actual === 'reportes'): ?>
            <div class="content-header">
                <h1>Reportes y Métricas Operativas</h1>
                <p>Distribución de documentos por estatus en el repositorio actual.</p>
            </div>
            <div class="widget-grid" style="margin-top:20px;">
                <?php
                    $totales = array_sum(array_column($estadisticas, 'total'));
                    foreach ($estadisticas as $stat):
                        $porcentaje = $totales > 0 ? round(($stat['total'] / $totales) * 100, 1) : 0;
                        $color = '#cbd5e1';
                        if (strtolower($stat['estado']) === 'vigente')    $color = 'var(--green)';
                        if (strtolower($stat['estado']) === 'en revisión') $color = '#f59e0b';
                        if (strtolower($stat['estado']) === 'obsoleto')   $color = '#ef4444';
                ?>
                <div class="widget-card">
                    <h3 style="margin:0 0 10px;font-size:.9rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;"><?= htmlspecialchars($stat['estado']) ?></h3>
                    <div style="font-size:2.2rem;font-weight:700;color:<?= $color ?>;margin-bottom:10px;"><?= $stat['total'] ?></div>
                    <div style="width:100%;background:#e2e8f0;height:6px;border-radius:3px;overflow:hidden;">
                        <div style="width:<?= $porcentaje ?>%;background:<?= $color ?>;height:100%;"></div>
                    </div>
                    <div style="text-align:right;font-size:.75rem;color:#94a3b8;margin-top:5px;"><?= $porcentaje ?>% del total</div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ============================================================
     MODALES 
============================================================= -->

<!-- 1. MODAL VISUALIZADOR -->
<div id="modalVisor" class="modal">
    <div class="modal-view-content">
        <div class="modal-header">
            <h3>Visor Corporativo Seguro</h3>
            <button class="close-btn" onclick="cerrarVisor()">&times;</button>
        </div>
        <div style="flex-grow:1; position:relative; background:#f1f5f9;">
            <iframe id="iframeDoc" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<!-- 2. MODAL REPORTE DE FALLA -->
<div id="modalReporte" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-paper-plane" style="color:var(--primary);margin-right:10px;"></i>Reportar Falla</h3>
            <button class="close-btn" onclick="cerrarModalReporte()">&times;</button>
        </div>
        <p style="color:var(--text-muted);font-size:.95rem;margin-top:15px;">¿Encontraste un error en el documento <strong id="rep_codigo"></strong>?</p>
        <form onsubmit="enviarReporte(event)">
            <input type="hidden" id="rep_doc_id" name="doc_id">
            <textarea id="rep_motivo" placeholder="Describe brevemente el error (ej. Faltan firmas, el anexo está cortado...)" required></textarea>
            <button type="submit" class="btn-submit">Enviar Reporte a Calidad</button>
        </form>
    </div>
</div>

<!-- NOTIFICACIÓN -->
<div id="toast" class="toast-qh"><i class="fa-solid fa-circle-check"></i><span id="toast-msg">Mensaje</span></div>

<script>
    // ----- LÓGICA DE PESTAÑAS (NOTICIAS) -----
    function changeNewsTab(tabObj, tipo) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tabObj.classList.add('active');
        document.querySelectorAll('.news-item').forEach(item => {
            item.style.display = (tipo === 'TODAS' || item.getAttribute('data-type') === tipo) ? 'flex' : 'none';
        });
    }

    // ----- LÓGICA DEL VISUALIZADOR Y LOGS -----
    function verDocumento(id, archivoFisico, tipo) {
        if (!archivoFisico) {
            mostrarToast("Error: El archivo físico no está disponible en el servidor.", true);
            return;
        }

        document.getElementById('iframeDoc').src = '';
        const modal = document.getElementById('modalVisor');
        modal.style.display = 'flex';

        registrarLog(id, 'LECTURA');

        const urlDoc = 'ver_archivo.php?f=' + encodeURIComponent(archivoFisico);

        if (tipo === 'pdf') {
            document.getElementById('iframeDoc').src = urlDoc;
        } else if (tipo === 'docx' || tipo === 'doc' || tipo === 'xlsx' || tipo === 'xls') {
            const absoluteUrl = window.location.origin + window.location.pathname.replace('dashboard.php', '') + urlDoc;
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(absoluteUrl)}`;
            document.getElementById('iframeDoc').src = officeViewerUrl;
        } else {
            document.getElementById('iframeDoc').src = urlDoc;
        }
    }

    function cerrarVisor() {
        document.getElementById('modalVisor').style.display = 'none';
        document.getElementById('iframeDoc').src = '';
    }

    // ----- REGISTRO DE LOGS AJAX -----
    function registrarLog(docId, accion) {
        const formData = new FormData();
        formData.append('registrar_log', '1');
        formData.append('doc_id', docId);
        formData.append('accion', accion);

        fetch('dashboard.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .catch(err => console.error("Error al registrar log: ", err));
    }

    // ----- MODAL REPORTE -----
    function abrirModalReporte(codigo) {
        document.getElementById('rep_codigo').innerText = codigo;
        document.getElementById('rep_doc_id').value = codigo;
        document.getElementById('rep_motivo').value = '';
        document.getElementById('modalReporte').style.display = 'flex';
    }

    function cerrarModalReporte() {
        document.getElementById('modalReporte').style.display = 'none';
    }

    function enviarReporte(e) {
        e.preventDefault();
        cerrarModalReporte();
        mostrarToast("Reporte enviado a Control Documental exitosamente");
    }

    // ----- TOAST NOTIFICACIONES -----
    function mostrarToast(msg, isError = false) {
        const toast = document.getElementById('toast');
        const icon = toast.querySelector('i');
        
        document.getElementById('toast-msg').innerText = msg;
        
        if (isError) {
            icon.className = 'fa-solid fa-circle-exclamation';
            icon.style.color = '#ef4444';
        } else {
            icon.className = 'fa-solid fa-circle-check';
            icon.style.color = '#10b981';
        }

        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    }
</script>
</body>
</html>