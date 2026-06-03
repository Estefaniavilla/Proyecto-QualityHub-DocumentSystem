// =======================================================
// Script de Inicialización Automática de MongoDB
// QualityHub - Sistema de Control Documental
// Se ejecuta UNA SOLA VEZ al crear el contenedor
// =======================================================

// Seleccionamos (o creamos) la base de datos del proyecto
db = db.getSiblingDB('proyectofinal');

// Creamos la colección de usuarios si no existe
if (!db.getCollectionNames().includes('usuarios')) {
    db.createCollection('usuarios');
    print('Colección "usuarios" creada.');
}

// Insertamos los usuarios SOLO si no existen (evita duplicados en reinicios)
var usuariosIniciales = [
    {
        usuario: "fer_admin",
        contrasena: "12345",
        compania: "Compania_A",
        departamento: "Calidad"
    },
    {
        usuario: "auditor_global",
        contrasena: "54321",
        compania: "Compania_B",
        departamento: "Auditor\u00eda"
    }
];

usuariosIniciales.forEach(function(usuario) {
    var existe = db.usuarios.findOne({ usuario: usuario.usuario });
    if (!existe) {
        db.usuarios.insertOne(usuario);
        print('Usuario "' + usuario.usuario + '" insertado correctamente.');
    } else {
        print('Usuario "' + usuario.usuario + '" ya existe. Omitido.');
    }
});

// Creamos la colección de metadatos si no existe
if (!db.getCollectionNames().includes('metadatos')) {
    db.createCollection('metadatos');
    print('Colección "metadatos" creada.');
}

print('=== Inicialización de QualityHub completada ===');
