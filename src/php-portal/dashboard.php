<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// ==========================================
// ENDPOINT AJAX: REGISTRO DE LOGS
// Se ejecuta ANTES de todo lo demás
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_log'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $doc_id = intval($_POST['doc_id']);
        $nomina = $_SESSION['usuario_nomina'] ?? ($_SESSION['nomina'] ?? 'N/A');
        $accion = $_POST['accion'] ?? 'LECTURA';

        $stmt = $pdo->prepare("INSERT INTO logs_acceso (documento_id, nomina, accion) VALUES (:doc_id, :nomina, :accion)");
        $stmt->execute([
            ':doc_id' => $doc_id,
            ':nomina' => $nomina,
            ':accion' => $accion
        ]);
        echo json_encode(['success' => true, 'mensaje' => 'Log registrado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit; // MUY IMPORTANTE: salir aquí para no renderizar el HTML
}

// ==========================================
// CONTROL DE SEGURIDAD
// ==========================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$nombre_usuario = $_SESSION['usuario_nombre'];
$ubicacion_usuario = $_SESSION['usuario_ubicacion'];
$nomina_usuario = $_SESSION['usuario_nomina'] ?? ($_SESSION['nomina'] ?? 'N/A');

// ==========================================
// CONTROL DE ROLES
// ==========================================
$es_super_admin = (strpos(strtolower($nombre_usuario), 'lizeth') !== false);
$es_juan = (strpos(strtolower($nombre_usuario), 'juan') !== false);

// ==========================================
// PAGINACIÓN
// ==========================================
$por_pagina = 5;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;
$total_paginas = 1;

// ==========================================
// VISTA ACTUAL
// ==========================================
$vista_actual = isset($_GET['vista']) ? trim($_GET['vista']) : 'inicio';

// ==========================================
// VARIABLES GLOBALES
// ==========================================
$todos_documentos = [];
$logs_acceso = [];
$estadisticas = [];
$descargas = [];
$almacen_datos = [];
$error_msg = '';

try {
    $base_ubicacion = str_replace(['ó', 'Ó'], 'o', $ubicacion_usuario);

    // ------------------------------------------
    // VISOR DOCUMENTAL
    // ------------------------------------------
    if ($vista_actual === 'vigentes' || $vista_actual === 'obsoletos') {
        $condicion_estado = ($vista_actual === 'obsoletos') ? "='Obsoleto'" : "IN ('Vigente', 'En Revisión', 'En Fila', 'Autorizado')";

        if ($es_super_admin) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE estado $condicion_estado");
            $stmt_count->execute();
            $total_registros = $stmt_count->fetchColumn();
            $total_paginas = max(1, ceil($total_registros / $por_pagina));

            $stmt = $pdo->prepare("SELECT * FROM documentos WHERE estado $condicion_estado ORDER BY fecha_modificacion DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM documentos WHERE estado $condicion_estado AND (documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean) ORDER BY fecha_modificacion DESC");
            $stmt->execute([
                ':ubicacion' => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // ------------------------------------------
    // ALMACÉN DE REGISTROS
    // ------------------------------------------
    elseif ($vista_actual === 'almacen') {
        $almacen_datos = [
            ['registro' => 'REG-2026-001', 'fecha' => '2026-05-10', 'descripcion' => 'Reporte de Auditoría Interna', 'responsable' => 'Lizeth'],
            ['registro' => 'REG-2026-002', 'fecha' => '2026-05-12', 'descripcion' => 'Revisión por la Dirección', 'responsable' => 'Juan'],
            ['registro' => 'REG-2026-003', 'fecha' => '2026-05-15', 'descripcion' => 'Minuta de Comité de Calidad', 'responsable' => 'Lizeth'],
        ];
    }
    // ------------------------------------------
    // DESCARGAS RECIENTES (solo accion='DESCARGA')
    // ------------------------------------------
    elseif ($vista_actual === 'descargas') {
        $query_descargas = "
            SELECT 
                l.id,
                l.documento_id,
                l.nomina,
                l.accion,
                l.fecha_acceso,
                d.titulo AS documento_nombre,
                d.tipo_archivo,
                d.nombre_fisico,
                d.codigo_iso
            FROM logs_acceso l 
            LEFT JOIN documentos d ON l.documento_id = d.id 
            WHERE l.nomina = :nomina 
              AND l.accion = 'DESCARGA' 
            ORDER BY l.fecha_acceso DESC
        ";
        $stmt = $pdo->prepare($query_descargas);
        $stmt->execute([':nomina' => $nomina_usuario]);
        $descargas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ------------------------------------------
    // LOGS DE ACCESO (todas las acciones)
    // ------------------------------------------
    elseif ($vista_actual === 'logs') {
        $query_logs = "
            SELECT 
                l.id,
                l.documento_id,
                l.nomina,
                l.accion,
                l.fecha_acceso,
                d.titulo AS documento_nombre,
                d.codigo_iso,
                d.tipo_archivo
            FROM logs_acceso l 
            LEFT JOIN documentos d ON l.documento_id = d.id 
            WHERE l.nomina = :nomina 
            ORDER BY l.fecha_acceso DESC
        ";
        $stmt = $pdo->prepare($query_logs);
        $stmt->execute([':nomina' => $nomina_usuario]);
        $logs_acceso = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ------------------------------------------
    // REPORTES
    // ------------------------------------------
    elseif ($vista_actual === 'reportes') {
        if ($es_super_admin) {
            $stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos GROUP BY estado");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos WHERE documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean GROUP BY estado");
            $stmt->execute([
                ':ubicacion' => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
        }
        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_msg = "Error en dashboard: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityHub - DocumentSystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --main-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --primary: #4f46e5;
            --primary-light: #e0e7ff;
            --orange: #f97316;
            --orange-light: #ffedd5;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--main-bg); display: flex; height: 100vh; overflow: hidden; color: var(--text-dark); }

        /* === SIDEBAR === */
        .sidebar { width: 270px; background: #1a2536; color: white; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .sidebar-top { padding: 25px 16px; }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; font-size: 1.25rem; font-weight: 700; margin-bottom: 35px; }
        .brand i { color: #818cf8; font-size: 1.5rem; }
        .menu-section-title { font-size: 0.68rem; text-transform: uppercase; color: #4b5563; margin: 24px 0 8px 8px; font-weight: 700; letter-spacing: 0.05em; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 0 25px 25px 0; color: #9ca3af; text-decoration: none; transition: 0.2s; margin-bottom: 4px; font-size: 0.9rem; font-weight: 500; margin-left: -16px; padding-left: 32px; }
        .menu-link:hover, .menu-link.active { background: var(--primary); color: white; }
        .sidebar-footer { padding: 16px; border-top: 1px solid #232f42; }
        .btn-logout-box { background: rgba(239,68,68,0.1); color: #f87171; display: flex; justify-content: center; align-items: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .btn-logout-box:hover { background: #ef4444; color: white; }

        /* === MAIN === */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; background: #fff; }
        .topbar { height: 65px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; flex-shrink: 0; }
        .breadcrumbs { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-name { font-weight: 600; font-size: 0.9rem; color: #334155; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; justify-content: center; align-items: center; font-weight: 600; font-size: 0.85rem; }
        .content-body { padding: 40px; overflow-y: auto; flex-grow: 1; }
        .content-header h1 { margin: 0; font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .content-header p { color: var(--text-muted); margin-top: 5px; font-size: 0.95rem; }

        /* === TABLAS === */
        .table-container { margin-top: 25px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        .table-container th { padding: 16px 12px; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; border-bottom: 2px solid var(--border-color); }
        .table-container td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; vertical-align: middle; }
        .doc-code { color: #64748b; font-weight: 500; font-size: 0.85rem; }
        .doc-main { display: flex; gap: 12px; align-items: center; }
        .doc-title { display: block; font-weight: 600; color: #1e293b; }
        .doc-filename { font-size: 0.78rem; color: var(--text-muted); }
        .doc-icon { font-size: 1.5rem; }
        .fa-file-pdf { color: #ef4444; }
        .fa-file-word { color: #2563eb; }
        .fa-file-excel { color: #16a34a; }
        .badge-ext { font-size: 0.75rem; font-weight: 700; color: #475569; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; }
        .badge-status { padding: 6px 14px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .en-revision { background: #fef3c7; color: #d97706; }
        .en-fila { background: #e0f2fe; color: #0284c7; }
        .autorizado { background: #dcfce7; color: #16a34a; }
        .obsoleto { background: #fee2e2; color: #dc2626; }
        .badge-lectura { background: #e0f2fe; color: #0369a1; }
        .badge-descarga { background: #dcfce7; color: #15803d; }

        /* === BOTONES DE ACCIÓN === */
        .actions-cell { display: flex; gap: 8px; }
        .btn-action { width: 32px; height: 32px; border-radius: 6px; display: flex; justify-content: center; align-items: center; text-decoration: none; font-size: 0.85rem; transition: 0.2s; border: none; cursor: pointer; }
        .btn-view-doc { background: #e8f5e9; color: #2e7d32; }
        .btn-view-doc:hover { background: #2e7d32; color: white; }
        .btn-download-doc { background: #e3f2fd; color: #1565c0; }
        .btn-download-doc:hover { background: #1565c0; color: white; }
        .btn-report-doc { background: #ffebee; color: #c62828; }
        .btn-report-doc:hover { background: #c62828; color: white; }

        /* === WIDGETS INICIO === */
        .widget-grid { margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .widget-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .widget-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .widget-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; }

        /* === NOTICIAS === */
        .news-header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .tabs-container { display: flex; gap: 25px; border-bottom: 1px solid var(--border-color); margin-bottom: 0; }
        .tab { padding: 10px 0; color: #64748b; font-weight: 600; font-size: 0.95rem; cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; }
        .tab:hover { color: #334155; }
        .tab.active { color: var(--primary); border-bottom: 2px solid var(--primary); }
        .news-list { background: white; border-radius: 0 0 12px 12px; border: 1px solid var(--border-color); border-top: none; padding: 10px 30px; }
        .news-item { display: flex; align-items: center; padding: 25px 0; border-bottom: 1px solid #f1f5f9; }
        .news-item:last-child { border-bottom: none; }
        .news-icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 1.3rem; margin-right: 25px; flex-shrink: 0; }
        .bg-blue { background: var(--primary-light); color: var(--primary); }
        .bg-orange { background: var(--orange-light); color: var(--orange); }
        .news-info { flex-grow: 1; }
        .badge-type { font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; margin-bottom: 6px; display: inline-block; letter-spacing: 0.05em; }
        .badge-type.blue { color: var(--primary); background: var(--primary-light); }
        .badge-type.orange { color: var(--orange); background: var(--orange-light); }
        .news-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0; }
        .news-desc { font-size: 0.85rem; color: #64748b; margin: 0; }
        .news-meta { text-align: left; margin-right: 40px; font-size: 0.85rem; color: #64748b; width: 150px; }
        .news-meta strong { display: block; color: #334155; font-weight: 500; margin-top: 2px; }

        /* === DESCARGAS WRAPPER === */
        .descargas-wrapper { background: white; border-radius: 12px; border: 1px solid var(--border-color); padding: 5px 25px 25px 25px; margin-top: 25px; overflow-x: auto; }
        .descargas-table { width: 100%; border-collapse: collapse; text-align: left; }
        .descargas-table th { color: var(--primary); border-bottom: 1px solid var(--border-color); font-size: 0.75rem; letter-spacing: 0.05em; padding: 16px 12px; text-transform: uppercase; }
        .descargas-table td { font-size: 0.9rem; font-weight: 500; color: #334155; padding: 16px 12px; border-bottom: 1px solid #f1f5f9; }
        .doc-icon-small { font-size: 1.1rem; margin-right: 10px; }

        /* === MODAL === */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-view-content { background: white; border-radius: 12px; width: 85%; height: 85%; display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; border-bottom: 1px solid var(--border-color); }
        .modal-header h3 { margin: 0; font-size: 1.2rem; }
        .close-btn { font-size: 1.5rem; cursor: pointer; color: var(--text-muted); background: none; border: none; }
        .close-btn:hover { color: black; }
        textarea { width: 100%; height: 120px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; margin: 15px 0; resize: none; box-sizing: border-box; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; font-size: 0.95rem; }
        .btn-submit:hover { background: #3730a3; }
        .news-action { color: #818cf8; background: var(--primary-light); width: 35px; height: 35px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; transition: 0.2s; border: none; text-decoration: none; }
        .news-action:hover { background: var(--primary); color: white; }

        /* === PAGINACIÓN === */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; }
        .pagination a { padding: 8px 14px; border: 1px solid var(--border-color); color: #334155; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
        .pagination a:hover, .pagination a.active { background: #0284c7; color: white; border-color: #0284c7; }

        /* === ERROR === */
        .error-banner { background: #fee2e2; color: #991b1b; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; display: block; }
        .empty-state h3 { color: #64748b; margin: 0 0 8px 0; }
        .empty-state p { margin: 0; font-size: 0.9rem; }
    </style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="brand"><i class="fa-solid fa-shield-halved"></i> QualityHub-<br>DocumentSystem</div>

        <div class="menu-section-title">Módulos</div>
        <a href="?vista=inicio" class="menu-link <?= ($vista_actual === 'inicio') ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Inicio</a>
        <a href="?vista=vigentes" class="menu-link <?= ($vista_actual === 'vigentes' || $vista_actual === 'obsoletos') ? 'active' : '' ?>"><i class="fa-solid fa-eye"></i> Visor Documental</a>
        <a href="?vista=almacen" class="menu-link <?= ($vista_actual === 'almacen') ? 'active' : '' ?>"><i class="fa-solid fa-box-archive"></i> Almacén de Registros</a>
        <a href="?vista=noticias" class="menu-link <?= ($vista_actual === 'noticias') ? 'active' : '' ?>"><i class="fa-solid fa-newspaper"></i> Noticias y Avisos</a>

        <div class="menu-section-title">Operaciones</div>
        <a href="?vista=descargas" class="menu-link <?= ($vista_actual === 'descargas') ? 'active' : '' ?>"><i class="fa-solid fa-cloud-arrow-down"></i> Descargas Recientes</a>
        <a href="?vista=logs" class="menu-link <?= ($vista_actual === 'logs') ? 'active' : '' ?>"><i class="fa-solid fa-bolt"></i> Logs de Acceso</a>
        <a href="?vista=reportes" class="menu-link <?= ($vista_actual === 'reportes') ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Reportes y Métricas</a>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout-box"><i class="fa-solid fa-arrow-right-from-bracket" style="margin-right:8px;"></i> Cerrar Sesión</a>
    </div>
</div>

<!-- ============ CONTENIDO PRINCIPAL ============ -->
<div class="main-content">
    <div class="topbar">
        <div class="breadcrumbs">Dashboard / <?php
            $nombres_vista = [
                'inicio' => 'Inicio',
                'vigentes' => 'Visor Documental',
                'obsoletos' => 'Visor Documental',
                'almacen' => 'Almacén de Registros',
                'noticias' => 'Noticias y Avisos',
                'descargas' => 'Descargas Recientes',
                'logs' => 'Logs de Acceso',
                'reportes' => 'Reportes y Métricas'
            ];
            echo $nombres_vista[$vista_actual] ?? ucfirst($vista_actual);
        ?></div>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars($nombre_usuario) ?> (<?= htmlspecialchars($ubicacion_usuario) ?>)</span>
            <div class="user-avatar"><?= strtoupper(substr($nombre_usuario, 0, 2)) ?></div>
        </div>
    </div>

    <div class="content-body">

        <?php if ($error_msg): ?>
            <div class="error-banner"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- ========== INICIO ========== -->
        <?php if ($vista_actual === 'inicio'): ?>
            <div class="content-header">
                <h1>Bienvenido a QualityHub, <?= htmlspecialchars(explode(' ', $nombre_usuario)[0]) ?></h1>
                <p>Sistema Centralizado de Gestión Documental y Calidad Corporativa.</p>
            </div>
            <div class="widget-grid">
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fa-solid fa-bullseye"></i></div>
                        <div><h3 style="margin:0; font-size:1.1rem;">Nuestra Misión</h3></div>
                    </div>
                    <p style="color: #475569; font-size: 0.95rem; line-height: 1.5; margin:0;">Garantizar la excelencia operativa a través de la correcta gestión y distribución de la información documentada.</p>
                </div>
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background: #dcfce7; color: #16a34a;"><i class="fa-solid fa-chart-line"></i></div>
                        <div><h3 style="margin:0; font-size:1.1rem;">Estado Actual</h3></div>
                    </div>
                    <p style="color: #475569; font-size: 0.95rem; line-height: 1.5; margin:0;">El sistema opera óptimamente. Revisa la sección de Noticias para novedades.</p>
                </div>
                <div class="widget-card">
                    <div class="widget-header">
                        <div class="widget-icon" style="background: #fef3c7; color: #d97706;"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div><h3 style="margin:0; font-size:1.1rem;">Accesos Rápidos</h3></div>
                    </div>
                    <ul style="padding-left: 20px; color: #475569; font-size: 0.95rem; line-height: 1.8; margin:0;">
                        <li><a href="?vista=vigentes" style="color: var(--primary); text-decoration:none; font-weight:500;">Visor Documental</a></li>
                        <li><a href="?vista=descargas" style="color: var(--primary); text-decoration:none; font-weight:500;">Mis Descargas Recientes</a></li>
                        <li><a href="?vista=logs" style="color: var(--primary); text-decoration:none; font-weight:500;">Mis Logs de Acceso</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- ========== VISOR DOCUMENTAL ========== -->
        <?php if ($vista_actual === 'vigentes' || $vista_actual === 'obsoletos'): ?>
            <div class="content-header">
                <h1>Visor Documental Corporativo</h1>
                <p><?= $es_super_admin ? 'Panel Global Completo (Paginado de 5 en 5)' : 'Repositorio Documental - Destinado a: ' . htmlspecialchars($ubicacion_usuario) ?></p>
                <div style="margin-top: 15px;">
                    <a href="?vista=vigentes" style="text-decoration:none; color: <?= ($vista_actual==='vigentes')?'var(--primary)':'#64748b' ?>; font-weight:600; margin-right:15px;">Documentos Vigentes</a>
                    <a href="?vista=obsoletos" style="text-decoration:none; color: <?= ($vista_actual==='obsoletos')?'var(--primary)':'#64748b' ?>; font-weight:600;">Historial Obsoletos</a>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead><tr><th>Código</th><th>Nombre del Archivo</th><th>Extensión</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if (empty($todos_documentos)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-folder-open"></i><h3>Sin documentos</h3><p>No hay documentos registrados para esta vista.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($todos_documentos as $doc):
                            $tipo = strtolower($doc['tipo_archivo']);
                            $icono = 'fa-file';
                            if ($tipo === 'pdf') $icono = 'fa-file-pdf';
                            elseif (in_array($tipo, ['docx','doc'])) $icono = 'fa-file-word';
                            elseif (in_array($tipo, ['xlsx','xls'])) $icono = 'fa-file-excel';
                            $clase_estado = 'en-revision';
                            $est = strtolower($doc['estado']);
                            if ($est == 'vigente' || $est == 'autorizado') $clase_estado = 'autorizado';
                            if ($est == 'en fila') $clase_estado = 'en-fila';
                            if ($est == 'obsoleto') $clase_estado = 'obsoleto';
                        ?>
                        <tr>
                            <td><span class="doc-code"><?= htmlspecialchars($doc['codigo_iso']) ?></span></td>
                            <td><div class="doc-main"><i class="fa-solid <?= $icono ?> doc-icon"></i><div><span class="doc-title"><?= htmlspecialchars($doc['titulo']) ?></span><span class="doc-filename"><?= htmlspecialchars($doc['nombre_fisico']) ?></span></div></div></td>
                            <td><span class="badge-ext"><?= strtoupper($tipo) ?></span></td>
                            <td><span class="badge-status <?= $clase_estado ?>"><?= htmlspecialchars($doc['estado']) ?></span></td>
                            <td class="actions-cell">
                                <button onclick="verDocumento(<?= $doc['id'] ?>, 'storage/actuales/<?= htmlspecialchars($doc['nombre_fisico']) ?>', '<?= $tipo ?>')" class="btn-action btn-view-doc" title="Ver"><i class="fa-regular fa-eye"></i></button>
                                <a href="storage/actuales/<?= htmlspecialchars($doc['nombre_fisico']) ?>" download onclick="registrarLog(<?= $doc['id'] ?>, 'DESCARGA')" class="btn-action btn-download-doc" title="Descargar"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                <button onclick="abrirModalReporte(<?= $doc['id'] ?>)" class="btn-action btn-report-doc" title="Reportar Falla / Emitir Emergencia"><i class="fa-solid fa-paper-plane"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($es_super_admin && $total_paginas > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?vista=<?= $vista_actual ?>&p=<?= $i ?>" class="<?= ($pagina_actual === $i) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ========== ALMACÉN DE REGISTROS ========== -->
        <?php if ($vista_actual === 'almacen'): ?>
            <div class="content-header"><h1>Almacén de Registros</h1><p>Historial y control de los registros almacenados del Sistema de Gestión</p></div>
            <div class="descargas-wrapper">
                <table class="descargas-table">
                    <thead><tr><th>Nº DE REGISTRO</th><th>FECHA</th><th>DESCRIPCIÓN</th><th>RESPONSABLE</th></tr></thead>
                    <tbody>
                    <?php foreach($almacen_datos as $a): ?>
                        <tr>
                            <td><i class="fa-solid fa-file-signature doc-icon-small"></i> <?= htmlspecialchars($a['registro']) ?></td>
                            <td><?= htmlspecialchars($a['fecha']) ?></td>
                            <td><?= htmlspecialchars($a['descripcion']) ?></td>
                            <td><?= htmlspecialchars($a['responsable']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ========== DESCARGAS RECIENTES ========== -->
        <?php if ($vista_actual === 'descargas'): ?>
            <div class="content-header">
                <h1>Descargas Recientes</h1>
                <p>Historial de documentos que has descargado desde la plataforma. Nómina actual: <strong><?= htmlspecialchars($nomina_usuario) ?></strong></p>
            </div>
            <div class="descargas-wrapper">
                <table class="descargas-table">
                    <thead><tr><th>DOCUMENTO</th><th>CÓDIGO ISO</th><th>FECHA DESCARGA</th><th>NÓMINA</th><th>ACCIÓN</th></tr></thead>
                    <tbody>
                    <?php if (empty($descargas)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-cloud-arrow-down"></i><h3>Sin descargas registradas</h3><p>Aún no has descargado ningún documento. Ve al Visor Documental y descarga un archivo para que aparezca aquí.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($descargas as $d):
                            $tipo_d = strtolower($d['tipo_archivo'] ?? 'pdf');
                            $icono_d = 'fa-file-pdf';
                            if (in_array($tipo_d, ['docx','doc'])) $icono_d = 'fa-file-word';
                            if (in_array($tipo_d, ['xlsx','xls'])) $icono_d = 'fa-file-excel';
                        ?>
                        <tr>
                            <td><i class="fa-solid <?= $icono_d ?> doc-icon-small"></i> <?= htmlspecialchars($d['documento_nombre'] ?? 'Documento no encontrado') ?></td>
                            <td><span class="doc-code"><?= htmlspecialchars($d['codigo_iso'] ?? 'N/A') ?></span></td>
                            <td><?= date('d/m/Y h:i A', strtotime($d['fecha_acceso'])) ?></td>
                            <td><?= htmlspecialchars($d['nomina']) ?></td>
                            <td>
                                <?php if (!empty($d['nombre_fisico'])): ?>
                                    <a href="storage/actuales/<?= htmlspecialchars($d['nombre_fisico']) ?>" download class="btn-action btn-download-doc" title="Descargar de nuevo"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ========== LOGS DE ACCESO ========== -->
        <?php if ($vista_actual === 'logs'): ?>
            <div class="content-header">
                <h1>Trazabilidad y Logs de Acceso</h1>
                <p>Historial detallado de todas tus interacciones con el repositorio documental. Nómina: <strong><?= htmlspecialchars($nomina_usuario) ?></strong></p>
            </div>
            <div class="table-container">
                <table>
                    <thead><tr><th>Código Doc</th><th>Documento Consultado</th><th>Tipo</th><th>Fecha y Hora</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php if (empty($logs_acceso)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-bolt"></i><h3>Sin logs registrados</h3><p>No se encontraron registros de acceso para tu nómina (<?= htmlspecialchars($nomina_usuario) ?>). Visita el Visor Documental, abre o descarga un archivo, y aparecerá aquí.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs_acceso as $log):
                            $tipo_l = strtolower($log['tipo_archivo'] ?? 'pdf');
                            $icono_l = 'fa-file';
                            if ($tipo_l === 'pdf') $icono_l = 'fa-file-pdf';
                            elseif (in_array($tipo_l, ['docx','doc'])) $icono_l = 'fa-file-word';
                            elseif (in_array($tipo_l, ['xlsx','xls'])) $icono_l = 'fa-file-excel';
                            $accion_clase = (strtoupper($log['accion']) === 'DESCARGA') ? 'badge-descarga' : 'badge-lectura';
                        ?>
                        <tr>
                            <td><span class="doc-code"><?= htmlspecialchars($log['codigo_iso'] ?? 'N/A') ?></span></td>
                            <td><div class="doc-main"><i class="fa-solid <?= $icono_l ?> doc-icon" style="font-size:1.2rem;"></i><strong><?= htmlspecialchars($log['documento_nombre'] ?? 'Documento Eliminado') ?></strong></div></td>
                            <td><span class="badge-ext"><?= strtoupper($tipo_l) ?></span></td>
                            <td><?= date('d/m/Y h:i:s A', strtotime($log['fecha_acceso'])) ?></td>
                            <td><span class="badge-status <?= $accion_clase ?>"><?= htmlspecialchars($log['accion']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ========== NOTICIAS Y AVISOS ========== -->
        <?php if ($vista_actual === 'noticias'): ?>
            <div class="news-header-top">
                <div class="content-header" style="margin:0;"><h1>Noticias y Avisos</h1><p>Mantente informado sobre novedades y actualizaciones corporativas</p></div>
            </div>
            <div class="tabs-container">
                <div class="tab active" onclick="changeNewsTab(this, 'TODAS')">Todas</div>
                <div class="tab" onclick="changeNewsTab(this, 'NOTICIA')">Noticias</div>
                <div class="tab" onclick="changeNewsTab(this, 'AVISO')">Avisos</div>
                <div class="tab" onclick="changeNewsTab(this, 'ACTUALIZACIÓN')">Actualizaciones</div>
            </div>
            <div class="news-list">
                <!-- Ítem 1 -->
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
                <!-- Ítem 2 -->
                <div class="news-item" data-type="AVISO">
                    <div class="news-icon-box bg-orange"><i class="fa-solid fa-bell"></i></div>
                    <div class="news-info">
                        <span class="badge-type orange">AVISO</span>
                        <h4 class="news-title">Mantenimiento Programado del Sistema</h4>
                        <p class="news-desc">El sistema estará en mantenimiento el próximo sábado de 8:00 PM a 12:00 AM.</p>
                    </div>
                    <div class="news-meta">14/05/2026<br><strong>Sistemas</strong></div>
                    <a href="#" class="news-action"><i class="fa-regular fa-eye"></i></a>
                </div>
                <!-- Ítem 3 -->
                <div class="news-item" data-type="ACTUALIZACIÓN">
                    <div class="news-icon-box bg-blue"><i class="fa-solid fa-arrows-rotate"></i></div>
                    <div class="news-info">
                        <span class="badge-type blue">ACTUALIZACIÓN</span>
                        <h4 class="news-title">Actualización de Política de Seguridad</h4>
                        <p class="news-desc">Se ha actualizado la Política de Seguridad de la Información, Versión 1.6 disponible.</p>
                    </div>
                    <div class="news-meta">13/05/2026<br><strong>Sistemas</strong></div>
                    <a href="#" class="news-action"><i class="fa-regular fa-eye"></i></a>
                </div>
                <!-- Ítem 4 -->
                <div class="news-item" data-type="NOTICIA">
                    <div class="news-icon-box bg-blue"><i class="fa-solid fa-users"></i></div>
                    <div class="news-info">
                        <span class="badge-type blue">NOTICIA</span>
                        <h4 class="news-title">Capacitación en ISO 9001:2015</h4>
                        <p class="news-desc">Se programó nueva capacitación para el personal. Consulta el plan de capacitación.</p>
                    </div>
                    <div class="news-meta">12/05/2026<br><strong>Recursos Humanos</strong></div>
                    <a href="#" class="news-action"><i class="fa-regular fa-eye"></i></a>
                </div>
                <!-- Ítem 5 -->
                <div class="news-item" data-type="AVISO">
                    <div class="news-icon-box bg-orange"><i class="fa-solid fa-clipboard-check"></i></div>
                    <div class="news-info">
                        <span class="badge-type orange">AVISO</span>
                        <h4 class="news-title">Auditoría Interna Mayo 2026</h4>
                        <p class="news-desc">Se realizará auditoría interna del 20 al 24 de mayo. Revisa los documentos requeridos.</p>
                    </div>
                    <div class="news-meta">11/05/2026<br><strong>Calidad</strong></div>
                    <a href="#" class="news-action"><i class="fa-regular fa-eye"></i></a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ========== REPORTES ========== -->
        <?php if ($vista_actual === 'reportes'): ?>
            <div class="content-header"><h1>Reportes Estadísticos</h1><p>Volumetría de control documental.</p></div>
            <div class="table-container" style="max-width: 600px;">
                <table>
                    <thead><tr><th>Estado del Flujo</th><th style="text-align:right;">Cantidad Total</th></tr></thead>
                    <tbody>
                    <?php if (empty($estadisticas)): ?>
                        <tr><td colspan="2"><div class="empty-state"><i class="fa-solid fa-chart-pie"></i><h3>Sin métricas</h3><p>No hay datos de reportes disponibles.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($estadisticas as $stat): ?>
                        <tr><td><strong><?= htmlspecialchars($stat['estado']) ?></strong></td><td style="text-align:right; font-weight:700; color:var(--primary);"><?= htmlspecialchars($stat['total']) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div><!-- /content-body -->
</div><!-- /main-content -->

<!-- ============ MODAL REPORTE DE FALLA / EMERGENCIA ============ -->
<div id="modalReporte" class="modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">⚠️ Reportar Falla de Documento</h3>
            <button class="close-btn" onclick="cerrarModalReporte()">&times;</button>
        </div>
        <p style="font-size:0.85rem; color:var(--text-muted);">Esta solicitud será enviada al Sistema de Calidad (.NET) mediante API para su procesamiento inmediato.</p>
        <input type="hidden" id="doc_id_reporte">
        <textarea id="comentario_reporte" placeholder="Describe detalladamente la falla encontrada en el documento..."></textarea>
        <button class="btn-submit" id="btnEnviarReporte" onclick="enviarReporteFalla()">Enviar Reporte de Falla</button>
    </div>
</div>

<!-- ============ MODAL VISOR ============ -->
<div id="modalVisor" class="modal">
    <div class="modal-view-content">
        <div class="modal-header">
            <h3 id="visorTitle">Visor de Documentos Oficial</h3>
            <button class="close-btn" onclick="cerrarVisor()">&times;</button>
        </div>
        <div id="visorBody" style="flex-grow:1; background:#f1f5f9;"></div>
    </div>
</div>

<script>
// =============================================
// REGISTRAR LOG EN BASE DE DATOS VIA AJAX
// =============================================
function registrarLog(docId, accion) {
    // IMPORTANTE: usar 'dashboard.php' directo, sin query params
    var formData = new FormData();
    formData.append('registrar_log', '1');
    formData.append('doc_id', docId);
    formData.append('accion', accion);

    fetch('dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        console.log('Log registrado:', accion, 'doc:', docId, data);
    })
    .catch(function(err) {
        console.error('Error registrando log:', err);
    });
}

// =============================================
// VER DOCUMENTO EN MODAL
// =============================================
function verDocumento(docId, ruta, extension) {
    // 1. Registrar LECTURA en la BD
    registrarLog(docId, 'LECTURA');

    // 2. Abrir modal con el documento
    var visorBody = document.getElementById('visorBody');
    var urlAbsoluta = window.location.origin + '/' + ruta;

    if (extension === 'pdf') {
        visorBody.innerHTML = '<iframe src="' + ruta + '" width="100%" height="100%" style="border:none;"></iframe>';
    } else if (extension === 'docx' || extension === 'doc' || extension === 'xlsx' || extension === 'xls') {
        visorBody.innerHTML = '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(urlAbsoluta) + '" width="100%" height="100%" style="border:none;"></iframe>';
    } else {
        visorBody.innerHTML = '<div style="padding:40px; text-align:center;"><i class="fa-solid fa-file" style="font-size:3rem; color:#94a3b8; margin-bottom:15px; display:block;"></i>Formato no soportado para previsualización. Use el botón de descarga.</div>';
    }

    document.getElementById('modalVisor').style.display = 'flex';
}

function cerrarVisor() {
    document.getElementById('modalVisor').style.display = 'none';
    document.getElementById('visorBody').innerHTML = '';
}

// =============================================
// MODAL REPORTE DE FALLA / EMERGENCIA (API)
// =============================================
function abrirModalReporte(id) {
    document.getElementById('doc_id_reporte').value = id;
    document.getElementById('modalReporte').style.display = 'flex';
}

function cerrarModalReporte() {
    document.getElementById('modalReporte').style.display = 'none';
    document.getElementById('comentario_reporte').value = '';
    document.getElementById('btnEnviarReporte').disabled = false;
    document.getElementById('btnEnviarReporte').innerText = 'Enviar Reporte de Falla';
}

function enviarReporteFalla() {
    var id = document.getElementById('doc_id_reporte').value;
    var comentario = document.getElementById('comentario_reporte').value;

    if (!comentario.trim()) {
        alert('Por favor escribe una descripción de la falla.');
        return;
    }

    var btnEnviar = document.getElementById('btnEnviarReporte');
    btnEnviar.disabled = true;
    btnEnviar.innerText = 'Enviando...';

    var payload = {
        DocumentoId: parseInt(id),
        Comentarios: comentario
    };

    fetch('reportar_falla.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ¡Reporte enviado exitosamente!\n\n' + data.mensaje);
        } else {
            alert('❌ Error al enviar el reporte:\n\n' + (data.error || 'Error desconocido.'));
        }
        cerrarModalReporte();
    })
    .catch(function(err) {
        alert('❌ No se pudo conectar con el servidor.');
        btnEnviar.disabled = false;
        btnEnviar.innerText = 'Enviar Reporte de Falla';
    });
}

// =============================================
// TABS DE NOTICIAS
// =============================================
function changeNewsTab(tabElement, type) {
    document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
    tabElement.classList.add('active');
    document.querySelectorAll('.news-item').forEach(function(item) {
        if (type === 'TODAS') {
            item.style.display = 'flex';
        } else if (item.getAttribute('data-type') === type) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
</body>
</html>