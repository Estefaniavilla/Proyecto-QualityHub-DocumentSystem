-- ==============================================================
-- 1. TABLA DE DEPARTAMENTOS / ÁREAS (ISO 9001)
-- ==============================================================
CREATE TABLE IF NOT EXISTS departamentos (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    empresa VARCHAR(100) NOT NULL
);

-- ==============================================================
-- 2. TABLA DE USUARIOS (Para tu Login)
-- ==============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nomina VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL, -- Almacena 'admin123' para pruebas
    rol VARCHAR(50) DEFAULT 'operario',
    departamento_id INT REFERENCES departamentos(id)
);

-- ==============================================================
-- 3. TABLA DE DOCUMENTOS (Control de Vigentes y Obsoletos con Trazabilidad)
-- ==============================================================
CREATE TABLE IF NOT EXISTS documentos (
    id SERIAL PRIMARY KEY,
    codigo_iso VARCHAR(50) NOT NULL, 
    titulo VARCHAR(255) NOT NULL,
    version VARCHAR(10) NOT NULL,
    tipo_archivo VARCHAR(20), -- pdf, docx, etc.
    nombre_fisico TEXT NOT NULL, -- Nombre del archivo en el File Server (uploads/)
    estado VARCHAR(20) CHECK (estado IN ('Vigente', 'Obsoleto', 'Revision')), 
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Campos de Auditoría e Historial que te pide el proyecto:
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_modificó VARCHAR(150) NOT NULL,
    departamento_id INT REFERENCES departamentos(id)
);

-- ==============================================================
-- POBLACIÓN DE DATOS DE PRUEBA (Inserts Exactos)
-- ==============================================================
-- Insertar Departamentos
INSERT INTO departamentos (nombre, empresa) VALUES 
('Calidad', 'Empresa Alpha'),
('Producción', 'Empresa Alpha')
ON CONFLICT DO NOTHING;

-- Insertar tu Usuario de Pruebas para el Login
INSERT INTO usuarios (nomina, nombre, password, rol, departamento_id) VALUES 
('LIZ123', 'Lizeth', 'admin123', 'operario', 1)
ON CONFLICT DO NOTHING;

-- Insertar Documentos (Uno Vigente y uno Obsoleto para tus pestañas)
INSERT INTO documentos (codigo_iso, titulo, version, tipo_archivo, nombre_fisico, estado, usuario_modificó, departamento_id) VALUES 
('ISO-9001-MAN', 'Manual de Procedimientos de Ensamble', 'v3.2', 'pdf', 'manual_ensamble_v3.pdf', 'Vigente', 'Estefanía (Admin)', 2),
('ISO-9001-MAN', 'Manual de Procedimientos de Ensamble (ANTIGUO)', 'v3.1', 'pdf', 'manual_ensamble_v3.1_old.pdf', 'Obsoleto', 'Estefanía (Admin)', 2)
ON CONFLICT DO NOTHING;


DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS usuarios;

-- 1. Tabla de Usuarios
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nomina VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL, -- Almacena 'admin123' directo para tu prueba rápida
    rol VARCHAR(50) NOT NULL,
    usuario_ubicacion TEXT NOT NULL -- Aquí se filtra el acceso ('Alpha - Calidad', 'Alpha - Producción', etc.)
);

-- 2. Tabla de Documentos
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

-- 3. Usuarios de Prueba (Diferentes áreas)
INSERT INTO usuarios (nomina, nombre, password, rol, usuario_ubicacion) VALUES 
('LIZ123', 'Lizeth (Calidad)', 'admin123', 'operario', 'Alpha - Calidad'),
('PROD456', 'Juan (Producción)', 'prod123', 'operario', 'Alpha - Producion'),
('BETA789', 'Carlos (Admin Beta)', 'beta123', 'operario', 'Beta - Administracion')
ON CONFLICT (nomina) DO NOTHING;

-- 4. Documentos clasificados por área
INSERT INTO documentos (codigo_iso, titulo, version, tipo_archivo, nombre_fisico, estado, usuario_modificó, documento_ubicacion) VALUES 
('ISO-9001-MAN', 'Manual de Procedimientos de Ensamble de Planta', 'v3.2', 'pdf', 'manual_ensamble_v3.pdf', 'Vigente', 'Estefanía (Auditor)', 'Alpha - Calidad'),
('ISO-9001-PRO', 'Procedimiento Operativo de Control de Calidad', 'v1.0', 'pdf', 'procedimiento_calidad.pdf', 'Vigente', 'Ing. Ramiro Juárez', 'Alpha - Calidad'),
('ISO-9001-MAN', 'Manual de Procedimientos de Ensamble (ANTIGUO)', 'v3.1', 'pdf', 'manual_old.pdf', 'Obsoleto', 'Estefanía (Auditor)', 'Alpha - Calidad'),
-- Documentos de Producción
('ISO-14001-OPR', 'Guía de Operación de Maquinaria Pesada', 'v2.1', 'pdf', 'guia_maquinaria.pdf', 'Vigente', 'Ing. Pedro Páramo', 'Alpha - Producion'),
-- Documentos de Administración Beta
