<?php
session_start();
require_once 'db.php';

// Control de seguridad básica
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nomina = $_SESSION['usuario_nomina'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Operario de Planta';

// ACCIÓN 1: REGISTRAR LOG DE ACCESO Y MOSTRAR FICHA TÉCNICA + PDF REAL desde VS Code
if (isset($_GET['accion']) && $_GET['accion'] === 'ver' && isset($_GET['id'])) {
    $documento_id = intval($_GET['id']);
    
    try {
        // 1. OBTENER LOS DATOS COMPLETOS DEL DOCUMENTO (Ficha Técnica de PostgreSQL)
        $stmtDoc = $pdo->prepare("SELECT * FROM documentos WHERE id = :id");
        $stmtDoc->execute([':id' => $documento_id]);
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            die("El documento solicitado no existe en los registros de la planta.");
        }

        // 2. REGISTRAR EL LOG DE AUDITORÍA (Trazabilidad obligatoria)
        $stmtLog = $pdo->prepare("
            INSERT INTO logs_acceso (usuario_id, nomina, documento_id, accion, fecha_acceso) 
            VALUES (:usuario_id, :nomina, :documento_id, 'VISUALIZACION', CURRENT_TIMESTAMP)
        ");
        $stmtLog->execute([
            ':usuario_id' => $usuario_id,
            ':nomina' => $nomina,
            ':documento_id' => $documento_id
        ]);
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>QualityDoc | Ficha de Consulta Técnica</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                :root {
                    --bg-main: #f8fafc;
                    --border-color: #e2e8f0;
                    --text-dark: #0f172a;
                    --text-muted: #64748b;
                    --primary: #0284c7;
                }
                body {
                    margin: 0; font-family: 'Inter', sans-serif;
                    background-color: var(--bg-main); color: var(--text-dark);
                    display: flex; flex-direction: column; height: 100vh; overflow: hidden;
                }
                .topbar {
                    height: 60px; background: #ffffff; border-bottom: 1px solid var(--border-color);
                    display: flex; align-items: center; justify-content: space-between; padding: 0 25px; flex-shrink: 0;
                }
                .btn-back {
                    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
                    color: var(--text-dark); font-weight: 600; font-size: 0.85rem; padding: 8px 14px;
                    border: 1px solid var(--border-color); border-radius: 6px; background: #ffffff; transition: 0.2s;
                }
                .btn-back:hover { background: #f1f5f9; }
                .viewer-container { display: flex; flex-grow: 1; height: calc(100vh - 60px); }
                .metadata-panel {
                    width: 360px; background: #ffffff; border-right: 1px solid var(--border-color);
                    padding: 24px; overflow-y: auto; box-sizing: border-box; flex-shrink: 0;
                }
                .doc-header { border-bottom: 2px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px; }
                .iso-badge {
                    background: #e0f2fe; color: #0369a1; padding: 4px 8px;
                    border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
                }
                .doc-header h1 { font-size: 1.25rem; margin: 10px 0 8px 0; font-weight: 700; color: var(--text-dark); }
                .badge-status { padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
                .badge-status.vigente { background-color: #dcfce7; color: #15803d; }
                
                .meta-group { margin-bottom: 18px; }
                .meta-label { font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px; display: block; }
                .meta-value { font-size: 0.88rem; color: var(--text-dark); font-weight: 500; }
                
                .pdf-panel { flex-grow: 1; background: #525659; display: flex; justify-content: center; align-items: center; }
                iframe { width: 100%; height: 100%; border: none; }
            </style>
        </head>
        <body>
            <div class="topbar">
                <a href="dashboard.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Panel de Planta
                </a>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    ID Operario: <strong><?php echo htmlspecialchars($nomina); ?></strong>
                </div>
            </div>

            <div class="viewer-container">
                <div class="metadata-panel">
                    <div class="doc-header">
                        <span class="iso-badge"><?php echo htmlspecialchars($doc['codigo_iso']); ?></span>
                        <h1><?php echo htmlspecialchars($doc['titulo']); ?></h1>
                        <span class="badge-status vigente">Vigente</span>
                    </div>

                    <div class="meta-group">
                        <span class="meta-label"><i class="fa-solid fa-code-branch"></i> Versión del PDF</span>
                        <div class="meta-value">Revisión <?php echo htmlspecialchars($doc['version']); ?></div>
                    </div>

                    <div class="meta-group">
                        <span class="meta-label"><i class="fa-solid fa-user-check"></i> Autorizado por</span>
                        <div class="meta-value" style="color: #0369a1; font-weight: 600;">
                            <?php echo htmlspecialchars($doc['autorizado_por']); ?>
                        </div>
                    </div>

                    <div class="meta-group">
                        <span class="meta-label"><i class="fa-solid fa-calendar-check"></i> Fecha de Autorización</span>
                        <div class="meta-value"><?php echo htmlspecialchars($doc['fecha_autorizacion']); ?></div>
                    </div>

                    <div class="meta-group">
                        <span class="meta-label"><i class="fa-solid fa-gears"></i> Proceso Asociado</span>
                        <div class="meta-value"><?php echo htmlspecialchars($doc['proceso_sistema']); ?></div>
                    </div>

                    <div style="margin-top: 30px; padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px dashed var(--border-color);">
                        <span class="meta-label" style="color: #16a34a;"><i class="fa-solid fa-clock-rotate-left"></i> Archivo Local Cargado</span>
                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                            Ruta del File-Server local: <code>archivos/<?php echo htmlspecialchars($doc['nombre_fisico']); ?></code>
                        </p>
                    </div>
                </div>

                <div class="pdf-panel">
                    <?php 
                    // Tomamos el campo 'nombre_fisico' de tu Postgres (ej: 'MNC-001.pdf')
                    $archivo_pdf = $doc['nombre_fisico'];
                    // Armamos la ruta hacia la carpeta que se ve en tu VS Code
                    $ruta_completa = "archivos/" . $archivo_pdf;

                    // Verificamos si de verdad guardaste el archivo ahí
                    if (!empty($archivo_pdf) && file_exists($ruta_completa)): 
                    ?>
                        <iframe src="<?php echo htmlspecialchars($ruta_completa); ?>#toolbar=1&navpanes=0"></iframe>
                    <?php else: ?>
                        <div style="color: #ffffff; text-align: center; padding: 20px;">
                            <i class="fa-solid fa-circle-xmark" style="font-size: 3.5rem; color: #ef4444; margin-bottom: 15px;"></i>
                            <p style="margin: 0; font-size: 0.95rem; font-weight: 600;">Error en File-Server Local</p>
                            <span style="font-size: 0.8rem; color: #94a3b8; display: block; margin-top: 5px;">
                                No se encontró el archivo físico: <code><?php echo htmlspecialchars($ruta_completa); ?></code>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } catch (PDOException $e) {
        die("Error en el sistema de auditoría: " . $e->getMessage());
    }
}

// ACCIÓN 2: ENVIAR REVISIÓN / REPORTE (Se queda intacto)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_reporte']) && $_POST['accion_reporte'] === 'crear_revision') {
    $documento_id = intval($_POST['documento_id']);
    $observaciones = trim($_POST['observaciones']);
    
    if (empty($observaciones)) {
        echo "<script>alert('La sugerencia no puede ir vacía.'); window.location.href='dashboard.php';</script>";
        exit;
    }
    
    try {
        $stmtRev = $pdo->prepare("
            INSERT INTO sugerencias_revision (documento_id, usuario_id, observaciones, estado_revision, fecha_solicitud) 
            VALUES (:documento_id, :usuario_id, :observaciones, 'Pendiente de Auditoria', CURRENT_TIMESTAMP)
        ");
        $stmtRev->execute([
            ':documento_id' => $documento_id,
            ':usuario_id' => $usuario_id,
            ':observaciones' => $observaciones
        ]);
        
        echo "<script>
            alert('¡Propuesta guardada! Se envió al módulo de Acciones Correctivas de la base de datos.');
            window.location.href = 'dashboard.php';
        </script>";
        exit;
    } catch (PDOException $e) {
        die("Error al guardar el reporte en Postgres: " . $e->getMessage());
    }
}

header("Location: dashboard.php");
exit;
?>
