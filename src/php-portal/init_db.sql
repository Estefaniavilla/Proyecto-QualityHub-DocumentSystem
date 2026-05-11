-- 1. Crear la tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nomina VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100),
    ubicacion VARCHAR(100)
);

-- 2. Insertar un usuario para que puedas loguearte (Password: admin123)
INSERT INTO usuarios (nomina, password, nombre, ubicacion) 
VALUES ('ESTEF123', 'admin123', 'Estefania Villa', 'Planta Apodaca')
ON CONFLICT (nomina) DO NOTHING;

