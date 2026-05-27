import os
import time
import math
from flask import Flask, render_template, request, jsonify, send_from_directory

app = Flask(__name__)

# Ruta interna dentro del contenedor mapeada al volumen compartido
CARPETA_ALMACENAMIENTO = '/app/storage/actuales'

try:
    from pymongo import MongoClient
    cliente = MongoClient("mongodb://mongodb_container:27017/", serverSelectionTimeoutMS=5000)
    db = cliente['local']
    coleccion_metadatos = db['metadatos_documentos']
    cliente.server_info()
    print("Conexión exitosa a MongoDB")
except Exception as e:
    print(f"Error al conectar a MongoDB: {e}")
    coleccion_metadatos = None

datosUsuario = {
    'usuario': 'fer_admin',
    'compania': 'Compania_A',
    'departamento': 'Calidad'
}

def obtener_tamano_formateado(ruta):
    """Calcula dinámicamente el peso real del archivo físico"""
    try:
        bytes_size = os.path.getsize(ruta)
        if bytes_size == 0:
            return "0 KB"
        size_name = ("B", "KB", "MB", "GB")
        i = int(math.floor(math.log(bytes_size, 1024)))
        p = math.pow(1024, i)
        s = round(bytes_size / p, 2)
        return f"{s} {size_name[i]}"
    except:
        return "Desconocido"

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/login', methods=['POST'])
def login():
    data = request.json or {}
    usuario_ingresado = data.get('usuario', '').strip()
    if usuario_ingresado == 'fer_admin':
        return jsonify({
            "status": "success",
            "usuario": datosUsuario['usuario'],
            "compania": datosUsuario['compania'],
            "departamento": datosUsuario['departamento']
        })
    return jsonify({"error": "Usuario o contraseña incorrectos"}), 401

@app.route('/api/buscar', methods=['GET'])
def buscar_documentos():
    filtro_nombre = request.args.get('nombre', '').lower()
    filtro_tipo = request.args.get('tipo', 'Todos')
    filtro_fecha = request.args.get('fecha', '')

    documentos_finales = []

    if os.path.exists(CARPETA_ALMACENAMIENTO):
        archivos = os.listdir(CARPETA_ALMACENAMIENTO)
    else:
        archivos = []

    for archivo in archivos:
        if archivo.startswith('.'):
            continue

        if filtro_nombre and filtro_nombre not in archivo.lower():
            continue

        ext = archivo.split('.')[-1].upper() if '.' in archivo else 'Desconocido'
        if filtro_tipo != 'Todos' and filtro_tipo != ext:
            continue

        ruta_completa = os.path.join(CARPETA_ALMACENAMIENTO, archivo)

        # Consultar si ya tiene metadatos registrados en MongoDB
        doc_mongo = None
        if coleccion_metadatos is not None:
            try:
                doc_mongo = coleccion_metadatos.find_one({"archivo_descarga": archivo})
            except:
                pass

        # Si el archivo fue subido por Estefanía pero no está en MongoDB, lo registramos con metadatos extendidos
        if not doc_mongo and coleccion_metadatos is not None:
            try:
                fecha_mod = os.path.getmtime(ruta_completa)
                fecha_real = time.strftime('%Y-%m-%d', time.localtime(fecha_mod))
            except:
                fecha_real = "2026-05-26"

            tamano_real = obtener_tamano_formateado(ruta_completa)
            nombre_limpio = archivo.replace(f'.{ext.lower()}', '').replace('_', ' ')
            
            # Generamos un código documental único basado en un hash estable del nombre
            codigo_hash = str(abs(hash(archivo)))[:4]
            codigo_documental = f"QH-{ext}-{codigo_hash}"

            doc_mongo = {
                "nombre": nombre_limpio,
                "codigo_doc": codigo_documental,
                "version": "V-1.0",
                "autor_subida": "Estefanía (Gestor Documental)",
                "fecha": fecha_real,
                "estado": "En Revisión (No)",  # Estatus inicial por defecto antes de ser aprobado
                "archivo_descarga": archivo,
                "compania_destino": "Compania_A",
                "departamento": "Calidad",
                "tipo": ext,
                "tamano": tamano_real,
                "clasificacion": "Confidencial Interno"
            }
            try:
                coleccion_metadatos.insert_one(doc_mongo.copy())
            except:
                pass

        if doc_mongo:
            if filtro_fecha and filtro_fecha != doc_mongo.get("fecha", ""):
                continue

            tamano_str = doc_mongo.get("tamano") or obtener_tamano_formateado(ruta_completa)

            documentos_finales.append({
                "nombre": doc_mongo.get("nombre", archivo),
                "codigo_doc": doc_mongo.get("codigo_doc", "QH-GEN-001"),
                "version": doc_mongo.get("version", "V-1.0"),
                "autor_subida": doc_mongo.get("autor_subida", "Estefanía (Gestor Documental)"),
                "fecha": doc_mongo.get("fecha", "2026-05-26"),
                "estado": doc_mongo.get("estado", "En Revisión (No)"),
                "archivo_descarga": archivo,
                "compania_destino": doc_mongo.get("compania_destino", "Compania_A"),
                "departamento": doc_mongo.get("departamento", "Calidad"),
                "tipo": ext,
                "tamano": tamano_str,
                "clasificacion": doc_mongo.get("clasificacion", "Uso Interno")
            })

    return jsonify(documentos_finales)

@app.route('/api/metadatos/<path:nombre_archivo>', methods=['GET'])
def obtener_metadatos(nombre_archivo):
    doc = None
    if coleccion_metadatos is not None:
        try:
            doc = coleccion_metadatos.find_one({"archivo_descarga": nombre_archivo})
        except:
            pass
    
    if doc:
        return jsonify({
            "nombre": doc.get("nombre", nombre_archivo),
            "codigo_doc": doc.get("codigo_doc", "QH-GEN-001"),
            "version": doc.get("version", "V-1.0"),
            "autor_subida": doc.get("autor_subida", "Estefanía (Gestor Documental)"),
            "tipo": doc.get("tipo", "PDF"),
            "fecha": doc.get("fecha", "2026-05-26"),
            "compania_destino": doc.get("compania_destino", "Compania_A"),
            "departamento": doc.get("departamento", "Calidad"),
            "estado": doc.get("estado", "En Revisión (No)"),
            "archivo_descarga": nombre_archivo,
            "tamano": doc.get("tamano", "Desconocido"),
            "clasificacion": doc.get("clasificacion", "Uso Interno")
        })
    
    return jsonify({"error": "No se encontraron metadatos"}), 404

@app.route('/api/metadatos/editar', methods=['POST'])
def editar_metadatos():
    data = request.json or {}
    archivo_descarga = data.get("archivo_descarga")
    
    if not archivo_descarga:
        return jsonify({"error": "Identificador de archivo faltante"}), 400
        
    # Recuperamos el documento actual para NO permitir cambiar el estado desde tu sesión
    estado_actual = "En Revisión (No)"
    if coleccion_metadatos is not None:
        doc_existente = coleccion_metadatos.find_one({"archivo_descarga": archivo_descarga})
        if doc_existente:
            estado_actual = doc_existente.get("estado", "En Revisión (No)")

    valores_actualizados = {
        "nombre": data.get("nombre"),
        "compania_destino": data.get("compania_destino"),
        "departamento": data.get("departamento"),
        "estado": estado_actual,  # Forzamos a mantener el estado previo definido por Estefanía
        "archivo_descarga": archivo_descarga,
        "fecha": data.get("fecha"),
        "codigo_doc": data.get("codigo_doc", "QH-GEN-001"),
        "version": data.get("version", "V-1.0"),
        "autor_subida": data.get("autor_subida", "Estefanía (Gestor Documental)"),
        "tamano": data.get("tamano"),
        "clasificacion": data.get("clasificacion", "Uso Interno")
    }
    
    if coleccion_metadatos is not None:
        coleccion_metadatos.update_one(
            {"archivo_descarga": archivo_descarga},
            {"$set": valores_actualizados},
            upsert=True
        )
    
    return jsonify({"status": "success", "message": "Metadatos actualizados con éxito"})

@app.route('/api/ver/<path:nombre_archivo>')
def ver_archivo_inline(nombre_archivo):
    """Envía el archivo con cabeceras de visualización inline para forzar su apertura en el navegador"""
    response = send_from_directory(CARPETA_ALMACENAMIENTO, nombre_archivo, as_attachment=False)
    if nombre_archivo.lower().endswith('.pdf'):
        response.headers['Content-Type'] = 'application/pdf'
        response.headers['Content-Disposition'] = 'inline'
    elif nombre_archivo.lower().endswith('.docx'):
        response.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    elif nombre_archivo.lower().endswith('.xls') or nombre_archivo.lower().endswith('.xlsx'):
        response.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    return response

@app.route('/api/descargar/<path:nombre_archivo>')
def descargar_archivo_attachment(nombre_archivo):
    """Fuerza la descarga directa del archivo físico de la carpeta storage"""
    return send_from_directory(CARPETA_ALMACENAMIENTO, nombre_archivo, as_attachment=True)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)