<?php
session_start();
require_once 'db.php';

// ==========================================
// CONTROL DE SEGURIDAD
// ==========================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$nombre_usuario = $_SESSION['usuario_nombre'];
$ubicacion_usuario = $_SESSION['usuario_ubicacion'];
$nomina_usuario = $_SESSION['usuario_nomina'] ?? 'N/A';

// ==========================================
// CONTROL DE ROLES POR NÓMINA / NOMBRE
// ==========================================
$es_super_admin = (strpos(strtolower($nombre_usuario), 'lizeth') !== false);
$es_juan = (strpos(strtolower($nombre_usuario), 'juan') !== false);

// ==========================================
// CONFIGURACIÓN DE PAGINACIÓN (Para el Visor Documental)
// ==========================================
$por_pagina = 5;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;
$total_paginas = 1; 

// ==========================================
// VISTA ACTUAL (Ahora por defecto es 'inicio')
// ==========================================
$vista_actual = isset($_GET['vista']) ? trim($_GET['vista']) : 'inicio';
$sub_vista = isset($_GET['sub']) ? trim($_GET['sub']) : 'vigentes'; // Para manejar vigentes/obsoletos dentro de visor

try {
    $base_ubicacion = str_replace(['ó', 'Ó'], 'o', $ubicacion_usuario);

    $todos_documentos = [];
    $logs_acceso = [];
    $estadisticas = [];
    $conteo_rapido = ['vigentes' => 0, 'revision' => 0, 'obsoletos' => 0];

    // Cargar estadísticas rápidas para la bienvenida empresarial siempre
    if ($es_super_admin) {
        $stmt_cc = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos GROUP BY estado");
        $stmt_cc->execute();
    } else {
        $stmt_cc = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos WHERE documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean GROUP BY estado");
        $stmt_cc->execute([':ubicacion' => $ubicacion_usuario, ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%']);
    }
    foreach ($stmt_cc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $est = strtolower($r['estado']);
        if (in_array($est, ['vigente', 'autorizado'])) $conteo_rapido['vigentes'] += $r['total'];
        elseif (in_array($est, ['en revisión', 'en fila'])) $conteo_rapido['revision'] += $r['total'];
        elseif ($est == 'obsoleto') $conteo_rapido['obsoletos'] += $r['total'];
    }

    // ==========================================
    // LÓGICA DE VISOR DOCUMENTAL
    // ==========================================
    if ($vista_actual === 'visor') {
        
        $condicion_estado = ($sub_vista === 'obsoletos') ? "='Obsoleto'" : "IN ('Vigente', 'En Revisión', 'En Fila', 'Autorizado')";

        if ($es_super_admin) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE estado $condicion_estado");
            $stmt_count->execute();
            $total_registros = $stmt_count->fetchColumn();
            $total_paginas = ceil($total_registros / $por_pagina);

            $stmt = $pdo->prepare("
                SELECT * FROM documentos 
                WHERE estado $condicion_estado 
                ORDER BY fecha_modificacion DESC 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($es_juan) {
            $stmt = $pdo->prepare("
                SELECT * FROM documentos 
                WHERE estado $condicion_estado 
                AND (documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean)
                ORDER BY fecha_modificacion DESC 
                LIMIT 5
            ");
            $stmt->execute([
                ':ubicacion' => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM documentos 
                WHERE estado $condicion_estado 
                AND (documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean)
                ORDER BY fecha_modificacion DESC
            ");
            $stmt->execute([
                ':ubicacion' => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
            $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // ==========================================
    // LOGS DE ACCESO
    // ==========================================
    elseif ($vista_actual === 'logs') {
        $stmt = $pdo->prepare("
            SELECT l.*, d.titulo as documento_nombre, d.codigo_iso
            FROM logs_acceso l
            LEFT JOIN documentos d ON l.documento_id = d.id
            WHERE l.nomina = :nomina
            ORDER BY l.fecha_acceso DESC
        ");
        $stmt->execute([':nomina' => $nomina_usuario]);
        $logs_acceso = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ==========================================
    // REPORTES
    // ==========================================
    elseif ($vista_actual === 'reportes') {
        if ($es_super_admin) {
            $stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM documentos GROUP BY estado");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT estado, COUNT(*) as total FROM documentos 
                WHERE documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean 
                GROUP BY estado
            ");
            $stmt->execute([
                ':ubicacion' => $ubicacion_usuario,
                ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
            ]);
        }
        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error en dashboard: " . $e->getMessage());
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
            --sidebar-hover: #0284c7;
            --main-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: var(--text-dark);
        }

        /* SIDEBAR */
        .sidebar {
            width: 270px;
            background: #1a2536;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sidebar-top { padding: 25px 16px; }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 35px;
        }
        .brand i { color: #38bdf8; font-size: 1.5rem; }

        .menu-section-title {
            font-size: 0.68rem;
            text-transform: uppercase;
            color: #4b5563;
            margin: 24px 0 8px 8px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 0 25px 25px 0;
            color: #9ca3af;
            text-decoration: none;
            transition: 0.2s;
            margin-bottom: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: -16px;
            padding-left: 32px;
        }

        .menu-link:hover, .menu-link.active {
            background: #0284c7;
            color: white;
        }

        .sidebar-footer { padding: 16px; border-top: 1px solid #232f42; }

        .btn-logout-box {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-logout-box:hover { background: #ef4444; color: white; }

        /* MAIN CONTENT */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #ffffff;
        }

        .topbar {
            height: 65px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
        }

        .breadcrumbs { font-size: 0.85rem; color: var(--text-muted); }

        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-name { font-weight: 600; font-size: 0.9rem; color: #334155; }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #0284c7;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .content-body { padding: 40px; overflow-y: auto; flex-grow: 1; }

        .content-header h1 { margin: 0; font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .content-header p { color: var(--text-muted); margin-top: 5px; font-size: 0.95rem; }

        /* NUEVOS ESTILOS EMPRESARIALES DE BIENVENIDA */
        .welcome-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 35px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner h2 { margin: 0; font-size: 1.8rem; font-weight: 700; color: #f8fafc; }
        .welcome-banner p { margin: 10px 0 0 0; color: #94a3b8; font-size: 1rem; line-height: 1.5; max-width: 600px; }
        .welcome-banner i.bg-icon {
            position: absolute; right: -20px; bottom: -30px; font-size: 10rem; color: rgba(2, 132, 199, 0.15); pointer-events: none;
        }
        .corp-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px;
        }
        .corp-card {
            background: #ffffff; border: 1px solid var(--border-color); padding: 20px; border-radius: 10px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .corp-card-icon {
            width: 48px; height: 48px; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 1.3rem;
        }
        .c-blue { background: #e0f2fe; color: #0284c7; }
        .c-amber { background: #fef3c7; color: #d97706; }
        .c-red { background: #fee2e2; color: #dc2626; }
        .corp-card-data h3 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .corp-card-data p { margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        
        .quick-actions { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; }
        .quick-actions h3 { margin: 0 0 15px 0; font-size: 1.1rem; color: #1e293b; }
        .actions-btn-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .btn-corp-quick {
            background: white; border: 1px solid var(--border-color); color: #334155; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-corp-quick:hover { border-color: #0284c7; color: #0284c7; background: #f0f9ff; }

        /* TABLA ESTILO CORPORATIVO */
        .table-container { margin-top: 25px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        th {
            padding: 16px 12px;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
        }

        td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; vertical-align: middle; }
        
        .doc-code { color: #64748b; font-weight: 500; font-size: 0.85rem; }
        .doc-main { display: flex; gap: 12px; align-items: center; }
        .doc-title { block: font-weight: 600; color: #1e293b; }
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

        .actions-cell { display: flex; gap: 8px; }
        .btn-action {
            width: 32px; height: 32px; border-radius: 6px; display: flex; justify-content: center; align-items: center; text-decoration: none; font-size: 0.85rem; transition: 0.2s; border: none; cursor: pointer;
        }
        .btn-view-doc { background: #e8f5e9; color: #2e7d32; }
        .btn-view-doc:hover { background: #2e7d32; color: white; }
        .btn-download-doc { background: #e3f2fd; color: #1565c0; }
        .btn-download-doc:hover { background: #1565c0; color: white; }
        .btn-suggest-doc { background: #fff3e0; color: #ef6c00; }
        .btn-suggest-doc:hover { background: #ef6c00; color: white; }
        .btn-emergency-doc { background: #ffebee; color: #c62828; }
        .btn-emergency-doc:hover { background: #c62828; color: white; }

        /* ESTILOS PAGINACIÓN */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; }
        .pagination a { padding: 8px 14px; border: 1px solid var(--border-color); color: #334155; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
        .pagination a:hover, .pagination a.active { background: #0284c7; color: white; border-color: #0284c7; }

        /* ESTILOS NOTICIAS */
        .news-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 30px; }
        .news-card { background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .news-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .news-img-wrapper { position: relative; width: 100%; height: 180px; background: #e2e8f0; }
        .news-img { width: 100%; height: 100%; object-fit: cover; }
        .news-badge { position: absolute; top: 15px; left: 15px; background: #0284c7; color: white; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; border-radius: 20px; text-transform: uppercase; }
        .news-badge.important { background: #ef4444; }
        .news-content { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .news-meta { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
        .news-title { margin: 0 0 10px 0; font-size: 1.15rem; font-weight: 600; color: #1e293b; line-height: 1.4; }
        .news-excerpt { font-size: 0.9rem; color: #475569; line-height: 1.5; margin: 0 0 20px 0; flex-grow: 1; }
        .news-footer { border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: flex-end; }
        .btn-read-more { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; color: #0284c7; text-decoration: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-top">
        <div class="brand">
            <i class="fa-solid fa-shield-halved"></i>
            QualityHub-<br>DocumentSystem
        </div>

        <div class="menu-section-title">Módulos</div>
        <!-- El Inicio ahora va a su panel corporativo limpio -->
        <a href="?vista=inicio" class="menu-link <?php echo ($vista_actual === 'inicio') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i> Inicio
        </a>
        <!-- Visor Documental ahora maneja la tabla que antes estaba en el home -->
        <a href="?vista=visor&sub=vigentes" class="menu-link <?php echo ($vista_actual === 'visor') ? 'active' : ''; ?>">
            <i class="fa-solid fa-eye"></i> Visor Documental
        </a>
        <a href="#" class="menu-link"><i class="fa-solid fa-box-archive"></i> Almacén de Registros</a>

        <a href="?vista=noticias" class="menu-link <?php echo ($vista_actual === 'noticias') ? 'active' : ''; ?>">
            <i class="fa-solid fa-newspaper"></i> Noticias y Avisos
        </a>

        <div class="menu-section-title">Operaciones</div>
        <a href="?vista=visor&sub=vigentes" class="menu-link <?php echo ($vista_actual === 'visor') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-invoice"></i> Control de Documentos
        </a>
        <a href="?vista=logs" class="menu-link <?php echo ($vista_actual === 'logs') ? 'active' : ''; ?>">
            <i class="fa-solid fa-bolt"></i> Logs de Acceso
        </a>
        <a href="?vista=reportes" class="menu-link <?php echo ($vista_actual === 'reportes') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> Reportes y Métricas
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout-box">
            <i class="fa-solid fa-arrow-right-from-bracket" style="margin-right:8px;"></i> Cerrar Sesión
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="breadcrumbs">
            Dashboard / <?php 
                if ($vista_actual === 'inicio') echo 'Inicio';
                elseif ($vista_actual === 'visor') echo 'Visor Documental (' . ucfirst($sub_vista) . ')';
                elseif ($vista_actual === 'noticias') echo 'Noticias y Avisos';
                else echo ucfirst($vista_actual); 
            ?>
        </div>
        <div class="user-profile">
            <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?> (<?php echo $es_super_admin ? 'SuperAdmin' : 'Usuario'; ?>)</span>
            <div class="user-avatar"><?php echo strtoupper(substr($nombre_usuario, 0, 2)); ?></div>
        </div>
    </div>

    <div class="content-body">

        <!-- ==========================================
             NUEVA VISTA: BIENVENIDA EMPRESARIAL (INICIO)
             ========================================== -->
        <?php if ($vista_actual === 'inicio'): ?>
            <div class="welcome-banner">
                <h2>Bienvenido al Sistema, <?php echo htmlspecialchars($nombre_usuario); ?></h2>
                <p>Ubicación Organizacional: <strong><?php echo htmlspecialchars($ubicacion_usuario); ?></strong> | No. Nómina: <code><?php echo htmlspecialchars($nomina_usuario); ?></code></p>
                <p style="margin-top: 15px; font-size: 0.9rem; color: #cbd5e1;">QualityHub coordina la integridad normativa, el control de versiones e ISO 9001:2015 de la organización de manera ágil y segura.</p>
                <i class="fa-solid fa-building-shield bg-icon"></i>
            </div>

            <!-- Resumen Corporativo Dinámico -->
            <div class="corp-grid">
                <div class="corp-card">
                    <div class="corp-card-icon c-blue"><i class="fa-solid fa-file-circle-check"></i></div>
                    <div class="corp-card-data">
                        <h3><?php echo $conteo_rapido['vigentes']; ?></h3>
                        <p>Documentos Vigentes</p>
                    </div>
                </div>
                <div class="corp-card">
                    <div class="corp-card-icon c-amber"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="corp-card-data">
                        <h3><?php echo $conteo_rapido['revision']; ?></h3>
                        <p>En Flujo / Revisión</p>
                    </div>
                </div>
                <div class="corp-card">
                    <div class="corp-card-icon c-red"><i class="fa-solid fa-folder-minus"></i></div>
                    <div class="corp-card-data">
                        <h3><?php echo $conteo_rapido['obsoletos']; ?></h3>
                        <p>Historial Obsoletos</p>
                    </div>
                </div>
            </div>

            <!-- Accesos Rápidos Estratégicos -->
            <div class="quick-actions">
                <h3>Accesos Corporativos Rápidos</h3>
                <div class="actions-btn-group">
                    <a href="?vista=visor&sub=vigentes" class="btn-corp-quick">
                        <i class="fa-solid fa-folder-open"></i> Explorar Visor Documental
                    </a>
                    <a href="?vista=noticias" class="btn-corp-quick">
                        <i class="fa-solid fa-bullhorn"></i> Ver Avisos Recientes
                    </a>
                    <a href="?vista=logs" class="btn-corp-quick">
                        <i class="fa-solid fa-shield-halved"></i> Consultar Mis Logs
                    </a>
                </div>
            </div>
        <?php endif; ?>


        <!-- ==========================================
             VISTA MOVIDA: VISOR DOCUMENTAL
             ========================================== -->
        <?php if ($vista_actual === 'visor'): ?>
            <div class="content-header">
                <h1>Control de Versiones y Distribución</h1>
                <p><?php echo $es_super_admin ? 'Panel Global Completo (Paginado de 5 en 5)' : 'Repositorio Documental - Destinado a: ' . htmlspecialchars($ubicacion_usuario); ?></p>
                <div style="margin-top: 15px;">
                    <a href="?vista=visor&sub=vigentes" style="text-decoration:none; color: <?php echo ($sub_vista==='vigentes')?'#0284c7':'#64748b'; ?>; font-weight:600; margin-right:15px;">Vigentes</a>
                    <a href="?vista=visor&sub=obsoletos" style="text-decoration:none; color: <?php echo ($sub_vista==='obsoletos')?'#0284c7':'#64748b'; ?>; font-weight:600;">Historial Obsoletos</a>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre del Archivo</th>
                            <th>Extensión</th>
                            <th>Estado</th>
                            <th>Acciones del File-Server</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todos_documentos)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:40px; color:#64748b;">
                                    No hay registros de documentos cargados en esta ubicación.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todos_documentos as $doc): 
                                $tipo = strtolower($doc['tipo_archivo']);
                                $icono = 'fa-file';
                                if ($tipo === 'pdf') $icono = 'fa-file-pdf';
                                elseif (in_array($tipo, ['docx', 'doc'])) $icono = 'fa-file-word';
                                elseif (in_array($tipo, ['xlsx', 'xls'])) $icono = 'fa-file-excel';
                                
                                $clase_estado = 'en-revision';
                                if(strtolower($doc['estado']) == 'autorizado' || strtolower($doc['estado']) == 'vigente') $clase_estado = 'autorizado';
                                if(strtolower($doc['estado']) == 'en fila') $clase_estado = 'en-fila';
                                if(strtolower($doc['estado']) == 'obsoleto') $clase_estado = 'obsoleto';
                            ?>
                                <tr>
                                    <td><span class="doc-code"><?php echo htmlspecialchars($doc['codigo_iso']); ?></span></td>
                                    <td>
                                        <div class="doc-main">
                                            <i class="fa-solid <?php echo $icono; ?> doc-icon"></i>
                                            <div>
                                                <span class="doc-title"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                                <span class="doc-filename"><?php echo htmlspecialchars($doc['nombre_fisico'] ?? 'Esperando carga...'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge-ext"><?php echo strtoupper($tipo); ?></span></td>
                                    <td><span class="badge-status <?php echo $clase_estado; ?>"><?php echo htmlspecialchars($doc['estado']); ?></span></td>
                                    <td class="actions-cell">
                                        <button onclick="verDocumento('storage/actuales/<?php echo $doc['nombre_fisico']; ?>', '<?php echo $tipo; ?>')" class="btn-action btn-view-doc" title="Ver Documento"><i class="fa-regular fa-eye"></i></button>
                                        <a href="storage/actuales/<?php echo $doc['nombre_fisico']; ?>" download class="btn-action btn-download-doc" title="Descargar"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                        <button onclick="abrirModalSugerencia(<?php echo $doc['id']; ?>, 'Sugerencia')" class="btn-action btn-suggest-doc" title="Sugerir Modificación"><i class="fa-regular fa-pen-to-square"></i></button>
                                        <button onclick="abrirModalSugerencia(<?php echo $doc['id']; ?>, 'Emergencia')" class="btn-action btn-emergency-doc" title="Emitir Emergencia"><i class="fa-solid fa-paper-plane"></i></button>
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
                        <a href="?vista=visor&sub=<?php echo $sub_vista; ?>&p=<?php echo $i; ?>" class="<?php echo ($pagina_actual === $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ==========================================
             VISTA: NOTICIAS Y AVISOS
             ========================================== -->
        <?php if ($vista_actual === 'noticias'): ?>
            <div class="content-header">
                <h1>Noticias y Avisos Oficiales</h1>
                <p>Mantente al tanto de los últimos comunicados, actualizaciones del sistema corporativo y políticas de calidad globales.</p>
            </div>

            <div class="news-container">
                <div class="news-card">
                    <div class="news-img-wrapper">
                        <span class="news-badge important">Urgente</span>
                        <img src="https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=600&q=80" alt="Auditoría ISO" class="news-img">
                    </div>
                    <div class="news-content">
                        <div class="news-meta">
                            <span><i class="fa-regular fa-calendar"></i> 27 Mayo, 2026</span>
                            <span><i class="fa-regular fa-user"></i> Control de Calidad</span>
                        </div>
                        <h3 class="news-title">Próxima Auditoría Interna de Certificación ISO 9001:2015</h3>
                        <p class="news-excerpt">Se les informa a todos los departamentos que la jornada oficial de auditoría iniciará la próxima semana. Por favor, asegúrense de tener sus manuales y registros actualizados en QualityHub.</p>
                        <div class="news-footer">
                            <a href="#" class="btn-read-more">Leer Comunicado <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="news-card">
                    <div class="news-img-wrapper">
                        <span class="news-badge">Actualización</span>
                        <img src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80" alt="Sistema Virtual" class="news-img">
                    </div>
                    <div class="news-content">
                        <div class="news-meta">
                            <span><i class="fa-regular fa-calendar"></i> 22 Mayo, 2026</span>
                            <span><i class="fa-regular fa-user"></i> TI Soporte</span>
                        </div>
                        <h3 class="news-title">Lanzamiento del Visor Documental de Office en Tiempo Real</h3>
                        <p class="news-excerpt">Hemos integrado un nuevo sistema de previsualización que permite visualizar archivos .docx y .xlsx directamente desde la plataforma sin necesidad de descargas.</p>
                        <div class="news-footer">
                            <a href="#" class="btn-read-more">Ver Detalles <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==========================================
             VISTA: LOGS
             ========================================== -->
        <?php if ($vista_actual === 'logs'): ?>
            <div class="content-header">
                <h1>Trazabilidad y Logs de Acceso</h1>
                <p>Historial detallado de interacciones con el repositorio documental corporativo.</p>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código Doc</th>
                            <th>Documento Consultado</th>
                            <th>Fecha y Hora</th>
                            <th>Acción Ejecutada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs_acceso)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:#64748b;">No hay logs registrados para su número de nómina.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs_acceso as $log): ?>
                                <tr>
                                    <td><span class="doc-code"><?php echo htmlspecialchars($log['codigo_iso'] ?? 'N/A'); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($log['documento_nombre'] ?? 'Documento Eliminado'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($log['fecha_acceso']); ?></td>
                                    <td><span class="badge-ext"><?php echo htmlspecialchars($log['accion'] ?? 'LECTURA'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ==========================================
             VISTA: REPORTES
             ========================================== -->
        <?php if ($vista_actual === 'reportes'): ?>
            <div class="content-header">
                <h1>Reportes Estadísticos</h1>
                <p>Volumetría de control documental correspondiente a su región operativa.</p>
            </div>
            <div class="table-container" style="max-width: 600px;">
                <table>
                    <thead>
                        <tr>
                            <th>Estado del Flujo</th>
                            <th style="text-align: right;">Cantidad Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($estadisticas)): ?>
                            <tr><td colspan="2" style="text-align:center; padding:40px; color:#64748b;">Sin métricas calculadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($estadisticas as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['estado']); ?></strong></td>
                                    <td style="text-align: right;"><?php echo htmlspecialchars($stat['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
