import tkinter as tk
from tkinter import ttk, messagebox
from pymongo import MongoClient
import os
import shutil # Para simular la descarga

# --- CONFIGURACIÓN ESTÉTICA ---
COLOR_BG = "#121418"
COLOR_CARD = "#1C1F26"
COLOR_ACCENT = "#28A745" # Verde éxito
COLOR_TEXT = "#E0E6ED"

def realizar_busqueda():
    try:
        # Conexión a Mongo 7 (La versión que pidió Liz)
        client=MongoClient("mongodb://localhost:27017/")
        db = client['proyectofinal']
        coleccion = db['metadatos']

        # Obtener valores de los filtros
        busqueda_nombre = entry_nombre.get().lower()
        busqueda_tipo = combo_tipo.get()
        busqueda_fecha = entry_fecha.get()

        # Limpiar tabla
        for item in tabla.get_children():
            tabla.delete(item)
        
        # Construir consulta dinámica (Multicriterio)
        query = {}
        if busqueda_tipo != "Todos":
            query["tipo"] = busqueda_tipo
        if busqueda_fecha:
            query["fecha"] = busqueda_fecha

        # Ejecutar búsqueda
        for doc in coleccion.find(query):
            nombre_doc = doc.get("nombre", "")
            # Filtro de nombre manual para ser más flexible
            if busqueda_nombre in nombre_doc.lower():
                estado = "✅ AUTORIZADO" if doc.get("autorizado") == "Sí" else "⏳ PENDIENTE"
                # Insertamos los datos incluyendo el ID oculto para la descarga
                tabla.insert("", "end", values=(
                    nombre_doc.upper(), 
                    doc.get("fecha", "---"), 
                    estado,
                    "DESCARGAR"
                ))
    except Exception as e:
        messagebox.showerror("Error", f"No se pudo conectar a MongoDB: {e}")

def descargar_archivo(event):
    # Detectar si se hizo clic en la columna de 'Acciones'
    item_id = tabla.identify_row(event.y)
    col = tabla.identify_column(event.x)
    
    if col == "#4" and item_id: # Columna de acciones
        valores = tabla.item(item_id)['values']
        nombre_archivo = valores[0].lower()
        
        # RUTA DEL FILE SERVER (Mapeada en Docker)
        ruta_origen = f"/shared_files/{nombre_archivo}"
        ruta_destino = os.path.join(os.path.expanduser("~"), "Downloads", nombre_archivo)
        
        messagebox.showinfo("Descarga", f"Simulando descarga de: {nombre_archivo}\n\nDesde el File Server: {ruta_origen}")
        # Aquí iría la lógica real de: shutil.copy(ruta_origen, ruta_destino)

# --- INTERFAZ GRÁFICA ---
root = tk.Tk()
root.title("QualityHub - Sistema de Gestión de Calidad")
root.geometry("1000x700")
root.configure(bg=COLOR_BG)

# Header
header = tk.Frame(root, bg="#1A73E8", height=80)
header.pack(fill="x")
tk.Label(header, text="QUALITYHUB DOCUMENT SEARCH", font=("Segoe UI", 18, "bold"), fg="white", bg="#1A73E8").pack(pady=20)

# PANEL DE FILTROS (Tarjeta Superior)
filter_card = tk.Frame(root, bg=COLOR_CARD, padx=20, pady=20)
filter_card.pack(pady=20, padx=30, fill="x")

# Filtro Nombre
tk.Label(filter_card, text="Nombre del Documento:", fg=COLOR_TEXT, bg=COLOR_CARD).grid(row=0, column=0, sticky="w")
entry_nombre = tk.Entry(filter_card, bg="#2D323E", fg="white", insertbackground="white", borderwidth=0, width=30)
entry_nombre.grid(row=1, column=0, padx=5, pady=5)

# Filtro Tipo
tk.Label(filter_card, text="Tipo:", fg=COLOR_TEXT, bg=COLOR_CARD).grid(row=0, column=1, sticky="w")
combo_tipo = ttk.Combobox(filter_card, values=["Todos", "PDF", "PowerPoint", "Excel", "Word"], state="readonly")
combo_tipo.current(0)
combo_tipo.grid(row=1, column=1, padx=5, pady=5)

# Filtro Fecha
tk.Label(filter_card, text="Fecha (AAAA-MM-DD):", fg=COLOR_TEXT, bg=COLOR_CARD).grid(row=0, column=2, sticky="w")
entry_fecha = tk.Entry(filter_card, bg="#2D323E", fg="white", insertbackground="white", borderwidth=0)
entry_fecha.grid(row=1, column=2, padx=5, pady=5)

# Botón Buscar
btn_search = tk.Button(filter_card, text="🔍 BUSCAR DOCUMENTOS", command=realizar_busqueda, bg=COLOR_ACCENT, fg="white", font=("Segoe UI", 10, "bold"), relief="flat", padx=20)
btn_search.grid(row=1, column=3, padx=20)

# TABLA DE RESULTADOS
style = ttk.Style()
style.theme_use("clam")
style.configure("Treeview", background=COLOR_CARD, foreground=COLOR_TEXT, fieldbackground=COLOR_CARD, rowheight=40, borderwidth=0)
style.map("Treeview", background=[('selected', '#343B47')])
style.configure("Treeview.Heading", background="#2D323E", foreground="white", font=("Segoe UI", 10, "bold"), borderwidth=0)

tabla = ttk.Treeview(root, columns=("Nom", "Fec", "Est", "Acc"), show="headings")
tabla.heading("Nom", text="NOMBRE DEL DOCUMENTO")
tabla.heading("Fec", text="FECHA DE REGISTRO")
tabla.heading("Est", text="ESTADO")
tabla.heading("Acc", text="ACCIONES")

tabla.column("Nom", width=400)
tabla.column("Acc", width=150, anchor="center")
tabla.pack(pady=10, padx=30, fill="both", expand=True)

# Evento para el botón de descarga en la tabla
tabla.bind("<ButtonRelease-1>", descargar_archivo)

tk.Label(root, text="Módulo de Búsqueda desarrollado por Fernanda v2.0 | Conectado a MongoDB 7", bg=COLOR_BG, fg="#5C6370", font=("Arial", 8)).pack(pady=10)

root.mainloop()