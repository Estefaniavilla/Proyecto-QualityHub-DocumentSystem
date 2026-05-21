<?php
session_start();
require_once 'db.php';

// 🔒 CONTROL DE SEGURIDAD: Si no hay sesión activa, al login de inmediato
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$nombre_usuario = $_SESSION['usuario_nombre'];
$ubicacion_usuario = $_SESSION['usuario_ubicacion']; 
$nomina_usuario = isset($_SESSION['usuario_nomina']) ? $_SESSION['usuario_nomina'] : 'N/A';

// 🧭 CONTROL DE NAVEGACIÓN: Detectar qué botón del menú lateral se presionó (Por defecto: vigentes)
$vista_actual = isset($_GET['vista']) ? trim($_GET['vista']) : 'vigentes';

try {
    // Limpieza básica para evitar problemas de acentos en la base de datos (Producion vs Producción)
    $base_ubicacion = str_replace(['ó', 'Ó'], 'o', $ubicacion_usuario);
    
    // Inicializar variables de despliegue
    $todos_documentos = [];
    $logs_acceso = [];
    $estadisticas = [];

    // --- LÓGICA DE DATOS SEGÚN EL BOTÓN SELECCIONADO ---
    if ($vista_actual === 'obsoletos') {
        // 1. BOTÓN: Historial Obsoletos (Trae todos los obsoletos del sistema)
        $stmt = $pdo->prepare("
            SELECT * FROM documentos 
            WHERE estado = 'Obsoleto' 
            ORDER BY fecha_modificacion DESC
        ");
        $stmt->execute();
        $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($vista_actual === 'logs') {
        // 2. BOTÓN: Logs de Acceso Planta (Requerimiento de trazabilidad en PostgreSQL)
        $stmt = $pdo->prepare("
            SELECT l.*, d.titulo as documento_nombre, d.codigo_iso
            FROM logs_acceso l
            LEFT JOIN documentos d ON l.documento_id = d.id
            WHERE l.nomina = :nomina
            ORDER BY l.fecha_acceso DESC
        ");
        $stmt->execute([':nomina' => $nomina_usuario]);
        $logs_acceso = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($vista_actual === 'reportes') {
        // 3. BOTÓN: Reporte Cumplimiento (Métricas agregadas por área de la planta)
        $stmt = $pdo->prepare("
            SELECT estado, COUNT(*) as total 
            FROM documentos 
            WHERE documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean
            GROUP BY estado
        ");
        $stmt->execute([
            ':ubicacion' => $ubicacion_usuario,
            ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
        ]);
        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // 4. BOTÓN: Documentos Vigentes (Vista predeterminada de inicio)
        $vista_actual = 'vigentes';
        $stmt = $pdo->prepare("
            SELECT * FROM documentos 
            WHERE estado = 'Vigente' 
              AND (documento_ubicacion = :ubicacion OR documento_ubicacion ILIKE :ubicacion_clean)
            ORDER BY fecha_modificacion DESC
        ");
        $stmt->execute([
            ':ubicacion' => $ubicacion_usuario,
            ':ubicacion_clean' => '%' . substr($base_ubicacion, 0, 8) . '%'
        ]);
        $todos_documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error al consultar los datos del panel: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityDoc | Módulo de Consulta</title>
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
            background-color: var(--main-bg);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
            box-shadow: 4px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-top {
            padding: 24px 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 35px;
            padding-left: 8px;
            color: #38bdf8;
        }

        .menu-section-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin: 24px 0 8px 8px;
            font-weight: 700;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 4px;
        }

        .menu-link:hover, .menu-link.active {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .menu-link i {
            width: 20px;
            font-size: 1.1rem;
            text-align: center;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #334155;
        }

        .btn-logout-box {
            background: rgba(239, 68, 68, 0.15); 
            color: #f87171; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 12px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 0.88rem;
            transition: 0.2s;
        }

        .btn-logout-box:hover {
            background: #ef4444;
            color: white;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: #ffffff;
        }

        .topbar {
            height: 65px;
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            flex-shrink: 0;
        }

        .breadcrumbs {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background-color: #0ea5e9;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .content-body {
            padding: 40px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .content-header {
            position: relative;
            margin-bottom: 30px;
        }

        .content-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .content-header p {
            margin: 6px 0 0 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* TABLA ESTILO SAAS CLEAN */
        .table-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        th {
            background-color: #f8fafc;
            padding: 14px 20px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .doc-row:hover {
            background-color: #f8fafc;
        }

        .doc-code {
            color: #0284c7;
            font-weight: 700;
            background-color: #f0f9ff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .doc-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-icon-pdf {
            color: #ef4444;
            font-size: 1.6rem;
        }

        .doc-title {
            font-weight: 600;
            color: #0f172a;
            display: block;
        }

        .doc-filename {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-family: monospace;
            background: #f1f5f9;
            padding: 1px 4px;
            border-radius: 4px;
        }

        .badge-ext {
            background-color: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-status.vigente {
            background-color: #dcfce7;
            color: #15803d;
        }

        .badge-status.obsoleto {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            padding: 8px 14px;
            background-color: #0284c7;
            border: 1px solid transparent;
            border-radius: 6px;
            color: #ffffff;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.15s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .btn-view:hover {
            background-color: #0369a1;
        }

        .btn-review {
            padding: 8px 12px;
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.15s;
        }

        .btn-review:hover {
            background-color: #fef2f2;
            border-color: #fca5a5;
        }

        /* TARJETAS DE REPORTES ESTILO GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 12px;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            width: 450px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .modal-header {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .modal-content textarea {
            width: 100%;
            height: 120px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            font-family: inherit;
            box-sizing: border-box;
            resize: none;
            outline: none;
        }

        .modal-content textarea:focus {
            border-color: var(--sidebar-hover);
        }

        .modal-actions {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            background: #f1f5f9;
            border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;
        }

        .btn-send {
            background: #ef4444;
            color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-top">
            <div class="brand">
                <i class="fa-solid fa-layer-group"></i>
                <span>QualityDoc Portal</span>
            </div>

            <div class="menu-section-title">Consulta Operativa</div>
            <a href="?vista=vigentes" class="menu-link <?php echo ($vista_actual === 'vigentes') ? 'active' : ''; ?>">
                <i class="fa-solid fa-folder-open"></i> Documentos Vigentes
            </a>
            <a href="?vista=obsoletos" class="menu-link <?php echo ($vista_actual === 'obsoletos') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Historial Obsoletos
            </a>

            <div class="menu-section-title">Auditoría y Reportes</div>
            <a href="?vista=logs" class="menu-link <?php echo ($vista_actual === 'logs') ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i> Logs de Acceso Planta
            </a>
            <a href="?vista=reportes" class="menu-link <?php echo ($vista_actual === 'reportes') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> Reporte Cumplimiento
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout-box">
                <i class="fa-solid fa-arrow-right-from-bracket" style="margin-right: 8px;"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="breadcrumbs">Portal de Consulta Pública / <?php echo ucfirst($vista_actual); ?></div>
            <div class="topbar-right">
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($nombre_usuario, 0, 2)); ?></div>
                </div>
            </div>
        </div>

        <div class="content-body">
            
            <?php if ($vista_actual === 'vigentes'): ?>
                <div class="content-header">
                    <h1>Visor Documental de Planta</h1>
                    <p>Mostrando información regulatoria autorizada para: <strong><?php echo htmlspecialchars($ubicacion_usuario); ?></strong></p>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Código ISO</th>
                                <th>Nombre del Documento</th>
                                <th>Tipo</th>
                                <th>Estado Actual</th>
                                <th>Acciones Operativas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todos_documentos)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                        No hay documentos vigentes cargados para tu área de trabajo.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($todos_documentos as $doc): ?>
                                    <tr class="doc-row">
                                        <td><span class="doc-code"><?php echo htmlspecialchars($doc['codigo_iso']); ?></span></td>
                                        <td>
                                            <div class="doc-main">
                                                <i class="fa-solid fa-file-pdf doc-icon-pdf"></i>
                                                <div>
                                                    <span class="doc-title"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                                    <span class="doc-filename"><i class="fa-solid fa-folder-tree"></i> archivos/<?php echo htmlspecialchars($doc['nombre_fisico']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge-ext"><?php echo strtoupper(htmlspecialchars($doc['tipo_archivo'])); ?></span></td>
                                        <td><span class="badge-status vigente">Vigente</span></td>
                                        <td class="actions-cell">
                                            <a href="acciones_documentos.php?accion=ver&id=<?php echo $doc['id']; ?>" class="btn-view">
                                                <i class="fa-regular fa-eye"></i> Abrir Visor PDF
                                            </a>
                                            <button class="btn-review" onclick="openReviewModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titulo']); ?>')">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Reportar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($vista_actual === 'obsoletos'): ?>
                <div class="content-header">
                    <h1>Historial de Documentos Obsoletos</h1>
                    <p>Archivos históricos que han sido dados de baja de las líneas de producción por actualización de norma.</p>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Código ISO</th>
                                <th>Nombre del Documento Retirado</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Fecha Retiro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todos_documentos)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                        No se registran documentos obsoletos en el sistema.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($todos_documentos as $doc): ?>
                                    <tr class="doc-row">
                                        <td><span class="doc-code" style="color:#64748b; background:#f1f5f9;"><?php echo htmlspecialchars($doc['codigo_iso']); ?></span></td>
                                        <td>
                                            <div class="doc-main">
                                                <i class="fa-solid fa-file-pdf doc-icon-pdf" style="color: #64748b;"></i>
                                                <div>
                                                    <span class="doc-title"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                                    <span class="doc-filename"><?php echo htmlspecialchars($doc['nombre_fisico']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge-ext"><?php echo strtoupper(htmlspecialchars($doc['tipo_archivo'])); ?></span></td>
                                        <td><span class="badge-status obsoleto">Obsoleto</span></td>
                                        <td style="color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($doc['fecha_modificacion']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($vista_actual === 'logs'): ?>
                <div class="content-header">
                    <h1>Logs de Acceso Planta</h1>
                    <p>Historial y trazabilidad de lecturas vinculadas a tu número de nómina activa: <strong><?php echo htmlspecialchars($nomina_usuario); ?></strong></p>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Registro</th>
                                <th>Código ISO</th>
                                <th>Documento Consultado</th>
                                <th>Acción realizada</th>
                                <th>Estampa de Tiempo (Timestamp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs_acceso)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                        Tu cuenta de nómina no registra consultas o descargas el día de hoy.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs_acceso as $log): ?>
                                    <tr class="doc-row">
                                        <td><strong>#<?php echo $log['id']; ?></strong></td>
                                        <td><span class="doc-code"><?php echo htmlspecialchars($log['codigo_iso'] ?? 'N/A'); ?></span></td>
                                        <td style="font-weight: 500;"><?php echo htmlspecialchars($log['documento_nombre'] ?? 'Documento Eliminado'); ?></td>
                                        <td><span style="color: #0284c7; font-weight: 600;"><i class="fa-solid fa-check-double"></i> <?php echo htmlspecialchars($log['accion']); ?></span></td>
                                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($log['fecha_acceso']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($vista_actual === 'reportes'): ?>
                <div class="content-header">
                    <h1>Reporte de Cumplimiento Técnico</h1>
                    <p>Resumen analítico e indicadores clave sobre el estado de la documentación asignada a tu zona.</p>
                </div>

                <div class="stats-grid">
                    <?php 
                    $acumulado = 0;
                    foreach($estadisticas as $est) { $acumulado += $est['total']; }
                    ?>
                    <div class="stat-card">
                        <h3>Asignados a tu Área</h3>
                        <div class="stat-number" style="color: #0284c7;"><?php echo $acumulado; ?></div>
                    </div>
                    
                    <?php foreach ($estadisticas as $est): ?>
                        <div class="stat-card">
                            <h3>Documentos <?php echo htmlspecialchars($est['estado']); ?>s</h3>
                            <div class="stat-number" style="color: <?php echo (strtolower($est['estado']) === 'vigente') ? '#15803d' : '#b91c1c'; ?>;">
                                <?php echo $est['total']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Generar Reporte de Cumplimiento / Desviación</div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0;" id="modalDocTitle"></p>
            
            <form action="acciones_documentos.php" method="POST">
                <input type="hidden" name="accion_reporte" value="crear_revision">
                <input type="hidden" name="documento_id" id="modalDocId" value="">
                
                <textarea name="observaciones" placeholder="Describe brevemente la anomalía detectada en el piso de producción o la sugerencia de actualización..." required></textarea>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeReviewModal()">Cancelar</button>
                    <button type="submit" class="btn-send">Emitir Alerta</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReviewModal(docId, docTitle) {
            document.getElementById('modalDocId').value = docId;
            document.getElementById('modalDocTitle').innerText = "Documento afectado: " + docTitle;
            document.getElementById('reviewModal').style.display = 'flex';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }
    </script>
</body>
</html>
