import os
from flask import Flask, request, jsonify, render_template
from pymongo import MongoClient

app = Flask(__name__, template_folder="templates")

# Configuración de URI apuntando internamente al contenedor 'mongodb' de tu red Docker
MONGO_URI = os.environ.get("MONGO_URI", "mongodb://mongodb:27017/")
try:
    client = MongoClient(MONGO_URI)
    db = client["proyectofinal"]
    metadatos_col = db["metadatos"]
    usuarios_col = db["usuarios"]
    print("Conexión exitosa a MongoDB desde el contenedor del Servidor.")
except Exception as e:
    print(f"Error crítico en servidor de base de datos: {e}")

@app.route('/')
def home():
    """Carga la interfaz gráfica profesional directamente en el navegador"""
    return render_template('index.html')

@app.route('/api/login', methods=['POST'])
def login():
    """Valida las credenciales contra la colección de usuarios en Mongo"""
    data = request.json or {}
    user = data.get("usuario", "").strip()
    password = data.get("contrasena", "").strip()

    usuario_valido = usuarios_col.find_one({"usuario": user, "contrasena": password})
    if usuario_valido:
        return jsonify({
            "status": "success",
            "usuario": usuario_valido["usuario"],
            "compania": usuario_valido["compania"],
            "departamento": usuario_valido["departamento"]
        })
    return jsonify({"error": "Usuario o contraseña inválidos"}), 401

@app.route('/api/buscar', methods=['GET'])
def buscar_documentos():
    """Consulta la base de datos aplicando los candados multi-compañía asignados"""
    compania = request.args.get("compania")
    departamento = request.args.get("departamento")
    nombre_filtro = request.args.get("nombre", "")
    tipo_filtro = request.args.get("tipo", "Todos")

    if not compania or not departamento:
        return jsonify({"error": "Faltan credenciales corporativas"}), 400

    query = {
        "compania_destino": compania,
        "departamento": departamento
    }

    if nombre_filtro:
        query["nombre"] = {"$regex": nombre_filtro, "$options": "i"}
    if tipo_filtro != "Todos":
        query["tipo"] = tipo_filtro

    try:
        documentos = metadatos_col.find(query)
        resultado = []
        for idx, doc in enumerate(documentos):
            aut = doc.get("autorizado", "No")
            estado = "Autorizado" if aut in ["Sí", "SI", "Autorizado"] else "En Revisión"
            resultado.append({
                "codigo": f"QH-DOC-{str(idx+1).zfill(3)}",
                "nombre": doc.get("nombre", "Sin nombre"),
                "fecha": doc.get("fecha", "N/A"),
                "tipo": doc.get("tipo", "PDF").upper(),
                "estado": estado
            })
        return jsonify(resultado)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)