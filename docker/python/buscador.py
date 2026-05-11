import tkinter as tk
from tkinter import ttk, messagebox
from pymongo import MongoClient
from datetime import datetime

# --- PALETA DE COLORES "ULTRA-PROFESIONAL" (Dark Mode Business) ---
COLOR_BG = "#101214"          # Fondo casi negro
COLOR_CARD = "#1C1F22"        # Color de tarjetas/paneles
COLOR_TEXT = "#ECECED"        # Texto principal blanco suave
COLOR_TEXT_DIM = "#9A9DA1"    # Texto secundario/etiquetas
COLOR_ACCENT = "#007BFF"      # Azul vibrante para acentos
COLOR_BTN = "#228B22"         # Verde bosque para el botón
COLOR_BTN_HOVER = "#2ab52a"   # Verde más claro para hover

def realizar_busqueda():
    try:
        # CONEXIÓN (Mantenemos la que te funcionó)
        client = MongoClient("mongodb://localhost:27017/")
        db = client['proyectofinal']
        coleccion = db['metadatos']

        tipo_buscado = combo_tipo.get()
        
        # Limpiar tabla
        for item in tabla.get_children():
            tabla.delete(item)
        
        resultados = coleccion.find({"tipo": tipo_buscado})
        
        datos_encontrados = False
        for i, doc in enumerate(resultados):
            datos_encontrados = True
            # Alternar color de fila para legibilidad
            tag = 'evenrow' if i % 2 == 0 else 'oddrow'
            
            # Dar formato a la fecha si es necesario
            fecha_str = doc.get("fecha")
            if isinstance(fecha_str, datetime):
                fecha_str = fecha_str.strftime("%Y-%m-%d")

            tabla.insert("", "end", values=(
                doc.get("nombre", "N/A").upper(), 
                fecha_str, 
                "✅ SÍ" if doc.get("autorizado") == "Sí" else "❌ NO"
            ), tags=(tag,))
            
        if not datos_encontrados:
            messagebox.showinfo("Búsqueda", f"No se encontraron documentos.")

    except Exception as e:
        messagebox.showerror("Error Critico", f"Falla de conexión a MongoDB:\n{e}")

# --- VENTANA PRINCIPAL ---
ventana = tk.Tk()
ventana.title("QualityHub - Advanced Search Dashboard")
ventana.geometry("850x600")
ventana.configure(bg=COLOR_BG)

# --- ESTILOS AVANZADOS (ttk) ---
style = ttk.Style()
style.theme_use("default") # Usamos default para tener control total

# Estilo para el Combo (Menú desplegable dark)
style.configure("TCombobox", fieldbackground=COLOR_CARD, background=COLOR_CARD, foreground=COLOR_TEXT, arrowcolor=COLOR_TEXT)
ventana.option_add('*TCombobox*Listbox.background', COLOR_CARD)
ventana.option_add('*TCombobox*Listbox.foreground', COLOR_TEXT)

# Estilo para la Tabla (La clave del diseño)
style.configure("Treeview", 
                background=COLOR_CARD, 
                foreground=COLOR_TEXT, 
                rowheight=35, # Filas más altas para aire
                fieldbackground=COLOR_CARD,
                font=("Inter", 10), # Tipografía moderna
                borderwidth=0)

# Estilo para las cabeceras de la tabla
style.configure("Treeview.Heading", 
                background=COLOR_BG, 
                foreground=COLOR_TEXT_DIM, 
                font=("Inter", 9, "bold"), 
                relief="flat")
style.map("Treeview.Heading", background=[('active', COLOR_BG)])

# Colores alternos para las filas
tabla_tags = ttk.Treeview(ventana) # Dummy para configurar tags
style.configure("Treeview", background=COLOR_CARD)
# Estos tags se aplican en la función realizar_busqueda

# --- DISEÑO DE LA INTERFAZ ---

# 1. BARRA SUPERIOR (MINIMALISTA)
header_frame = tk.Frame(ventana, bg=COLOR_BG, pady=20)
header_frame.pack(fill="x", padx=40)

tk.Label(header_frame, text="QUALITY HUB", font=("Inter", 18, "bold"), fg=COLOR_TEXT, bg=COLOR_BG).pack(side="left")
tk.Label(header_frame, text="DOCUMENT SYSTEM", font=("Inter", 10), fg=COLOR_TEXT_DIM, bg=COLOR_BG).pack(side="left", padx=15, pady=(5,0))

# 2. PANEL DE FILTROS (DISEÑO DE TARJETA)
filter_card = tk.Frame(ventana, bg=COLOR_CARD, bd=0, highlightbackground="#2A2E32", highlightthickness=1)
filter_card.pack(fill="x", padx=40, pady=10)

# Contenedor interno para padding
filter_container = tk.Frame(filter_card, bg=COLOR_CARD, pady=20, padx=20)
filter_container.pack()

tk.Label(filter_container, text="Filtrar por Tipo:", font=("Inter", 10), bg=COLOR_CARD, fg=COLOR_TEXT_DIM).pack(side="left", padx=10)

combo_tipo = ttk.Combobox(filter_container, values=["PowerPoint", "PDF", "Excel", "Word"], font=("Inter", 11), state="readonly", width=20)
combo_tipo.pack(side="left", padx=10)
combo_tipo.current(0)

# Botón Moderno (Sin bordes, con hover)
btn_buscar = tk.Button(filter_container, text="BUSCAR DOCUMENTOS", command=realizar_busqueda, 
                       bg=COLOR_BTN, fg="white", font=("Inter", 10, "bold"), 
                       relief="flat", padx=20, pady=8, cursor="hand2", activebackground=COLOR_BTN_HOVER, activeforeground="white")
btn_buscar.pack(side="left", padx=30)

# 3. ÁREA DE RESULTADOS
tk.Label(ventana, text="RESULTADOS DE BÚSQUEDA", font=("Inter", 9, "bold"), fg=COLOR_TEXT_DIM, bg=COLOR_BG).pack(anchor="w", padx=45, pady=(25, 5))

frame_results = tk.Frame(ventana, bg=COLOR_BG)
frame_results.pack(fill="both", expand=True, padx=40, pady=5)

# Contenedor con borde para la tabla
table_border = tk.Frame(frame_results, bg=COLOR_CARD, bd=0, highlightbackground="#2A2E32", highlightthickness=1)
table_border.pack(fill="both", expand=True)

columnas = ("Nombre", "Fecha", "Autorizado")
tabla = ttk.Treeview(table_border, columns=columnas, show="headings", style="Treeview")

# Configurar columnas
tabla.heading("Nombre", text="NOMBRE DEL DOCUMENTO")
tabla.heading("Fecha", text="FECHA DE REGISTRO")
tabla.heading("Autorizado", text="ESTADO")

tabla.column("Nombre", anchor="w", width=350)
tabla.column("Fecha", anchor="center", width=150)
tabla.column("Autorizado", anchor="center", width=150)

# Colores alternos de fila (Configuración de tags)
tabla.tag_configure('evenrow', background=COLOR_CARD)
tabla.tag_configure('oddrow', background="#232629") # Un gris ligeramente más claro

tabla.pack(fill="both", expand=True)

# 4. PIE DE PÁGINA (SUBTRESALDO)
tk.Label(ventana, text="Dashboard de Búsqueda Avanzada | Powered by MongoDB & Python | Desarrollado por Fernanda", 
         font=("Inter", 8), fg="#4F545C", bg=COLOR_BG).pack(side="bottom", pady=15)

ventana.mainloop()