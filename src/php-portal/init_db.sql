-- 1. Borrar tablas si existen (para empezar de cero en las pruebas)
DROP TABLE IF EXISTS logs_acceso;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS usuarios;

-- 2. Crear Tabla de Usuarios
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(20) CHECK (rol IN ('admin', 'editor', 'lector')) DEFAULT 'lector'
);

-- 3. Crear Tabla de Documentos
CREATE TABLE documentos (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL, -- Nombre del PDF en la carpeta storage
    estado VARCHAR(20) DEFAULT 'pendiente', -- 'pendiente', 'autorizado'
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Crear Tabla de Logs (Auditoría requerida en el PDF)
CREATE TABLE logs_acceso (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id),
    accion TEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Insertar Datos de Prueba
INSERT INTO usuarios (nombre, email, password, rol) VALUES 
('Lizeth Admin', 'liz@quality.com', '12345', 'admin'),
('Estefania Admin', 'estefi@quality.com', '12345', 'admin'),
('Fernanda User', 'fer@quality.com', '12345', 'lector');

INSERT INTO documentos (titulo, nombre_archivo, estado) VALUES 
('Manual de Calidad V1', 'manual_calidad.pdf', 'autorizado'),
('Politica de Seguridad', 'seguridad_proyectos.pdf', 'pendiente');
