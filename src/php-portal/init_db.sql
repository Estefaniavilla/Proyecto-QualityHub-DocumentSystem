------------------------------------------------------------------
CREATE TABLE usuarios (                                                        
    id SERIAL PRIMARY KEY,
    nomina VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL, -- Almacena 'admin123' directo para tu prueba rápida
    rol VARCHAR(50) NOT NULL,
    usuario_ubicacion TEXT NOT NULL -- Aquí se filtra el acceso ('Alpha - Calidad', 'Alpha - Producción', etc.)
);

INSERT INTO usuarios (nomina, nombre, password, rol, usuario_ubicacion) VALUES 
('LIZ123', 'Lizeth (Calidad)', 'admin123', 'operario', 'Alpha - Calidad'),
('PROD456', 'Juan (Producción)', 'prod123', 'operario', 'Alpha - Producion'),
('BETA789', 'Carlos (Admin Beta)', 'beta123', 'operario', 'Beta - Administracion')
ON CONFLICT (nomina) DO NOTHING;


 CREATE TABLE IF NOT EXISTS departamentos (                                                                                  
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    empresa VARCHAR(100) NOT NULL
);


INSERT INTO departamentos (nombre, empresa) VALUES                             
('Calidad', 'Empresa Alpha'),
('Producción', 'Empresa Alpha')
ON CONFLICT DO NOTHING;

CREATE TABLE documentos (                                                                                  
    id SERIAL PRIMARY KEY,
    codigo_iso VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    version VARCHAR(10) NOT NULL,
    tipo_archivo VARCHAR(20) NOT NULL,
    nombre_fisico TEXT NOT NULL,
    estado VARCHAR(20) NOT NULL,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_modificó VARCHAR(150) NOT NULL,
    documento_ubicacion TEXT NOT NULL -- Columna clave para el filtro de seguridad
);

-- DEPARTAMENTO: Alpha - Calidad | Usuario: Estefania (Auditor)
INSERT INTO documentos (codigo_iso, titulo, version, tipo_archivo, nombre_fisico, estado, usuario_modificado, documento_ubicacion) VALUES 
('ISO-9001-MAN', 'Manual de Calidad ISO 9001', 'v1.0', 'pdf', 'Manual de Calidad ISO 9001.pdf', 'Vigente', 'Estefania (Auditor)', 'Alpha - Calidad'),
('ISO-9001-CNC', 'Control de Productos No Conformes', 'v1.0', 'docx', 'Control de Productos No Conformes.docx', 'Vigente', 'Estefania (Auditor)', 'Alpha - Calidad'),
('ISO-9001-AUD', 'Procedimiento de Auditoría Interna', 'v1.0', 'docx', 'Procedimiento de Auditoría Interna.docx', 'Vigente', 'Estefania (Auditor)', 'Alpha - Calidad');

-- DEPARTAMENTO: Alpha - Producción | Usuario: Ing. Pedro Páramo
INSERT INTO documentos (codigo_iso, titulo, version, tipo_archivo, nombre_fisico, estado, usuario_modificado, documento_ubicacion) VALUES 
('ISO-14001-MAN', 'Bitácora de Mantenimiento Preventivo', 'v1.0', 'pdf', 'Bitacora_Mantenimiento_Preventivo.pdf', 'Vigente', 'Ing. Pedro Páramo', 'Alpha - Producción'),
('ISO-14001-CAP', 'Programa de Capacitación Anual', 'v1.0', 'pdf', 'Programa de Capacitación Anual.pdf', 'Vigente', 'Ing. Pedro Páramo', 'Alpha - Producción'),
('ISO-14001-AMB', 'Plan de Gestión Ambiental', 'v1.0', 'pdf', 'Plan de Gestión Ambiental.pdf', 'Vigente', 'Ing. Pedro Páramo', 'Alpha - Producción');

-- DEPARTAMENTO: Alpha - Gerencia | Usuario: Ing. Ramiro Juárez
INSERT INTO documentos (codigo_iso, titulo, version, tipo_archivo, nombre_fisico, estado, usuario_modificado, documento_ubicacion) VALUES 
('ISO-9000-IDE', 'Manual de Identidad Institucional', 'v1.0', 'pdf', 'Manual de Identidad Institucional.pdf', 'Vigente', 'Ing. Ramiro Juárez', 'Alpha - Gerencia'),
('ISO-9000-SEG', 'Política de Seguridad de la Información', 'v1.0', 'pdf', 'Politica de Seguridad de la Informacion.pdf', 'Vigente', 'Ing. Ramiro Juárez', 'Alpha - Gerencia'),
('ISO-9000-RIE', 'Matriz de Identificación de Riesgos', 'v1.0', 'xls', 'Matriz de Identificación de Riesgos.xls', 'Vigente', 'Ing. Ramiro Juárez', 'Alpha - Gerencia'),
('ISO-9000-PRO', 'Evaluación de Desempeño de Proveedores', 'v1.0', 'xlsx', 'Evaluacion de Desempeño de Proveedores.xlsx', 'Vigente', 'Ing. Ramiro Juárez', 'Alpha - Gerencia');