import os
import time
import math
from flask import Flask, render_template, request, jsonify, send_from_directory

app = Flask(__name__)

# Ruta interna dentro del contenedor mapeada al volumen compartido
CARPETA_ALMACENAMIENTO = '/app/storage/actuales'

try:
    from pymongo import MongoClient
    # Conectamos a la base de datos 'proyectofinal'
    cliente = MongoClient("mongodb://mongodb_container:27017/", serverSelectionTimeoutMS=5000)
    db = cliente['proyectofinal']
    coleccion_metadatos = db['metadatos']
    coleccion_usuarios = db['usuarios']
    cliente.server_info()
    print("Conexión exitosa a MongoDB (proyectofinal)")
except Exception as e:
    print(f"Error al conectar a MongoDB: {e}")
    coleccion_metadatos = None
    coleccion_usuarios = None

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

def mapear_formato(formato):
    """Asocia un formato del buscador a las posibles extensiones de archivo"""
    formato_upper = formato.upper()
    if formato_upper == 'WORD':
        return ['WORD', 'DOC', 'DOCX']
    elif formato_upper == 'EXCEL':
        return ['EXCEL', 'XLS', 'XLSX']
    elif formato_upper == 'POWERPOINT':
        return ['POWERPOINT', 'PPT', 'PPTX']
    elif formato_upper == 'PDF':
        return ['PDF']
    return []

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/login', methods=['POST'])
def login():
    data = request.json or {}
    usuario_ingresado = data.get('usuario', '').strip()
    contrasena_ingresada = data.get('contrasena', '').strip()
    compania_ingresada = data.get('compania', '').strip()
    departamento_ingresado = data.get('departamento', '').strip()

    # Si no se pudo establecer conexión con MongoDB, usamos credenciales de respaldo validadas estrictamente
    if coleccion_usuarios is None:
        if usuario_ingresado == 'fer_admin':
            if contrasena_ingresada == '12345' and compania_ingresada == 'Compania_A' and departamento_ingresado == 'Calidad':
                return jsonify({
                    "status": "success",
                    "usuario": "fer_admin",
                    "compania": "Compania_A",
                    "departamento": "Calidad"
                })
            else:
                return jsonify({"error": "Contraseña, compañía o departamento incorrectos para fer_admin"}), 401
        elif usuario_ingresado == 'auditor_global':
            if contrasena_ingresada == '54321' and compania_ingresada == 'Compania_B' and departamento_ingresado == 'Auditoría':
                return jsonify({
                    "status": "success",
                    "usuario": "auditor_global",
                    "compania": "Compania_B",
                    "departamento": "Auditoría"
                })
            else:
                return jsonify({"error": "Contraseña, compañía o departamento incorrectos para auditor_global"}), 401
        return jsonify({"error": "Usuario no registrado o datos incorrectos"}), 401

    try:
        # Buscamos en la base de datos el usuario que coincida exactamente
        usuario_doc = coleccion_usuarios.find_one({"usuario": usuario_ingresado})
        if usuario_doc:
            db_pass = str(usuario_doc.get("contrasena", "")).strip()
            db_comp = str(usuario_doc.get("compania", "")).strip()
            db_dept = str(usuario_doc.get("departamento", "")).strip()
            
            # Validación estricta cruzada
            if db_pass == contrasena_ingresada and db_comp == compania_ingresada and db_dept == departamento_ingresado:
                return jsonify({
                    "status": "success",
                    "usuario": usuario_doc["usuario"],
                    "compania": db_comp,
                    "departamento": db_dept
                })
            else:
                return jsonify({"error": "Contraseña, compañía o departamento incorrectos para este usuario"}), 401
        else:
            return jsonify({"error": "El identificador de usuario no existe"}), 401
    except Exception as e:
        return jsonify({"error": f"Error del servidor de base de datos: {str(e)}"}), 500

@app.route('/api/buscar', methods=['GET'])
def buscar_documentos():
    filtro_nombre = request.args.get('nombre', '').strip().lower()
    filtro_tipo = request.args.get('tipo', 'Todos').strip()
    filtro_fecha = request.args.get('fecha', '').strip()
    filtro_compania = request.args.get('compania', '').strip()
    filtro_departamento = request.args.get('departamento', '').strip()
    
    # Parámetros de paginación
    try:
        page = int(request.args.get('page', 1))
        per_page = int(request.args.get('per_page', 5))
    except ValueError:
        page = 1
        per_page = 5

    documentos_candidatos = []

    if os.path.exists(CARPETA_ALMACENAMIENTO):
        archivos = os.listdir(CARPETA_ALMACENAMIENTO)
    else:
        archivos = []

    for archivo in archivos:
        if archivo.startswith('.'):
            continue

        ext = archivo.split('.')[-1].upper() if '.' in archivo else 'Desconocido'
        
        # Filtro de tipo rápido previo a la base de datos
        if filtro_tipo != 'Todos':
            formatos_permitidos = mapear_formato(filtro_tipo)
            if ext not in formatos_permitidos:
                continue

        ruta_completa = os.path.join(CARPETA_ALMACENAMIENTO, archivo)

        # Consultar si ya tiene metadatos registrados en MongoDB
        doc_mongo = None
        if coleccion_metadatos is not None:
            try:
                doc_mongo = coleccion_metadatos.find_one({"archivo_descarga": archivo})
            except:
                pass

        # Si el archivo fue subido físicamente pero no está en MongoDB, lo registramos con los metadatos del visor actual
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
                "estado": "En Revisión (No)",  # Estatus inicial por defecto
                "archivo_descarga": archivo,
                "compania_destino": filtro_compania if filtro_compania else "Compania_A",
                "departamento": filtro_departamento if filtro_departamento else "Calidad",
                "tipo": ext,
                "tamano": tamano_real,
                "clasificacion": "Confidencial Interno"
            }
            try:
                coleccion_metadatos.insert_one(doc_mongo.copy())
            except:
                pass

        # Si tenemos el documento (sea previamente guardado o recién creado en MongoDB)
        if doc_mongo:
            # 1. Filtro estricto de Compañía: El usuario solo ve los documentos asignados a su compañía
            if filtro_compania and doc_mongo.get("compania_destino", "").strip().upper() != filtro_compania.strip().upper():
                continue

            # 2. Filtro de Tipo Técnico
            if filtro_tipo != 'Todos':
                formatos_permitidos = mapear_formato(filtro_tipo)
                ext_doc = doc_mongo.get("tipo", ext).upper()
                if ext_doc not in formatos_permitidos:
                    continue

            # 3. Filtro de Fecha de Registro
            if filtro_fecha and filtro_fecha != doc_mongo.get("fecha", ""):
                continue

            # 4. Filtro por Nombre y Metadatos Extendidos (Búsqueda global en campos de metadatos)
            if filtro_nombre:
                # Comprobamos coincidencias en Nombre, Código ISO, Versión, Autor de Subida, Clasificación y Formato
                nombre_doc = doc_mongo.get("nombre", "").lower()
                codigo_doc = doc_mongo.get("codigo_doc", "").lower()
                version_doc = doc_mongo.get("version", "").lower()
                autor_doc = doc_mongo.get("autor_subida", "").lower()
                clasif_doc = doc_mongo.get("clasificacion", "").lower()
                tipo_doc = doc_mongo.get("tipo", "").lower()
                nombre_archivo = archivo.lower()

                match_name = filtro_nombre in nombre_doc
                match_codigo = filtro_nombre in codigo_doc
                match_version = filtro_nombre in version_doc
                match_autor = filtro_nombre in autor_doc
                match_clasif = filtro_nombre in clasif_doc
                match_tipo = filtro_nombre in tipo_doc
                match_archivo = filtro_nombre in nombre_archivo

                if not (match_name or match_codigo or match_version or match_autor or match_clasif or match_tipo or match_archivo):
                    continue

            tamano_str = doc_mongo.get("tamano") or obtener_tamano_formateado(ruta_completa)

            documentos_candidatos.append({
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

    # Calcular los conteos de KPI sobre el conjunto TOTAL filtrado antes de paginar
    conteo_autorizados = sum(1 for doc in documentos_candidatos if doc["estado"] in ['Authorized', 'Autorizado'])
    conteo_revision = len(documentos_candidatos) - conteo_autorizados

    # Calcular Paginación
    total_items = len(documentos_candidatos)
    total_pages = math.ceil(total_items / per_page) if total_items > 0 else 1
    
    # Ajustar página actual a rangos válidos
    if page < 1:
        page = 1
    elif page > total_pages:
        page = total_pages

    start_idx = (page - 1) * per_page
    end_idx = start_idx + per_page
    paginated_docs = documentos_candidatos[start_idx:end_idx]

    return jsonify({
        "documentos": paginated_docs,
        "total": total_items,
        "page": page,
        "pages": total_pages,
        "per_page": per_page,
        "kpis": {
            "totales": total_items,
            "autorizados": conteo_autorizados,
            "revision": conteo_revision
        }
    })

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
        
    estado_actual = "En Revisión (No)"
    if coleccion_metadatos is not None:
        doc_existente = coleccion_metadatos.find_one({"archivo_descarga": archivo_descarga})
        if doc_existente:
            estado_actual = doc_existente.get("estado", "En Revisión (No)")

    valores_actualizados = {
        "nombre": data.get("nombre"),
        "compania_destino": data.get("compania_destino"),
        "departamento": data.get("departamento"),
        "estado": estado_actual,  # Mantenemos el estado controlado
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