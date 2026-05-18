import tkinter as tk
import os
from tkinter import ttk, messagebox
from pymongo import MongoClient

class LoginWindow:
    def __init__(self, root):
        self.root = root
        self.root.title("QualityHub - Control de Acceso")
        self.root.geometry("400x520")
        self.root.configure(bg="#f8f9fa")
        self.root.resizable(False, False)

        # MONGO_URI configurado para apuntar al contenedor 'mongodb' en Docker o local
        mongo_uri = os.environ.get("MONGO_URI", "mongodb://localhost:27017/")

        # Conexión a MongoDB
        try:
            self.client = MongoClient(mongo_uri)
            self.db = self.client["proyectofinal"]
            self.usuarios_col = self.db["usuarios"]
            self.metadatos_col = self.db["metadatos"]
        except Exception as e:
            messagebox.showerror("Error de Conexión", f"No se pudo conectar a MongoDB: {e}")
            self.root.destroy()
            return

        self.build_ui()

    def build_ui(self):
        card = tk.Frame(self.root, bg="#ffffff", highlightbackground="#e2e8f0", highlightthickness=1)
        card.place(relx=0.5, rely=0.5, anchor="center", width=340, height=440)

        tk.Label(card, text="🛡️", font=("Helvetica", 32), fg="#0ea5e9", bg="#ffffff").pack(pady=(25, 5))
        tk.Label(card, text="QualityHub", font=("Helvetica", 20, "bold"), fg="#1e293b", bg="#ffffff").pack()
        tk.Label(card, text="Document System Login", font=("Helvetica", 10), fg="#64748b", bg="#ffffff").pack(pady=(0, 25))

        form_frame = tk.Frame(card, bg="#ffffff")
        form_frame.pack(padx=25, fill="x")

        tk.Label(form_frame, text="USUARIO", font=("Helvetica", 8, "bold"), fg="#475569", bg="#ffffff").pack(anchor="w", pady=(0, 4))
        self.ent_user = tk.Entry(form_frame, font=("Helvetica", 11), bg="#f1f5f9", fg="#1e293b", bd=0, highlightthickness=1, highlightbackground="#cbd5e1", highlightcolor="#0ea5e9")
        self.ent_user.pack(fill="x", ipady=7, pady=(0, 18))
        self.ent_user.insert(0, "fer_admin")

        tk.Label(form_frame, text="CONTRASEÑA", font=("Helvetica", 8, "bold"), fg="#475569", bg="#ffffff").pack(anchor="w", pady=(0, 4))
        self.ent_pass = tk.Entry(form_frame, font=("Helvetica", 11), bg="#f1f5f9", fg="#1e293b", bd=0, highlightthickness=1, highlightbackground="#cbd5e1", highlightcolor="#0ea5e9", show="*")
        self.ent_pass.pack(fill="x", ipady=7, pady=(0, 25))

        btn_login = tk.Button(card, text="Iniciar Sesión", font=("Helvetica", 11, "bold"), bg="#0ea5e9", fg="#ffffff", bd=0, activebackground="#38bdf8", activeforeground="#ffffff", cursor="hand2", command=self.autenticar)
        btn_login.pack(padx=25, fill="x", ipady=9)

    def autenticar(self):
        user = self.ent_user.get().strip()
        password = self.ent_pass.get().strip()

        if not user or not password:
            messagebox.showwarning("Campos Vacíos", "Por favor rellena todos los campos.")
            return

        usuario_valido = self.usuarios_col.find_one({"usuario": user, "contrasena": password})

        if usuario_valido:
            self.root.destroy()
            main_window = tk.Tk()
            BuscadorOriginalClaro(main_window, usuario_valido, self.metadatos_col)
            main_window.mainloop()
        else:
            messagebox.showerror("Error", "Credenciales incorrectas de acceso.")


class BuscadorOriginalClaro:
    def __init__(self, root, usuario, coleccion_metadatos):
        self.root = root
        self.usuario = usuario
        self.metadatos_col = coleccion_metadatos
        
        self.root.title("QualityHub - Control de Versiones y Distribución")
        self.root.geometry("900x600")
        self.root.configure(bg="#f8f9fa")
        
        self.build_ui()
        self.buscar_documentos()

    def build_ui(self):
        info_text = f"Compañía: {self.usuario['compania']}   |   Departamento: {self.usuario['departamento']}   |   Usuario: {self.usuario['usuario'].upper()}"
        banner_info = tk.Label(self.root, text=info_text, font=("Helvetica", 9, "bold"), fg="#475569", bg="#e2e8f0", anchor="e", padx=15)
        banner_info.pack(fill="x", ipady=6)

        header = tk.Label(self.root, text="QUALITYHUB DOCUMENT SEARCH", font=("Helvetica", 16, "bold"), fg="#ffffff", bg="#0ea5e9", pady=12)
        header.pack(fill="x")

        filter_frame = tk.Frame(self.root, bg="#f8f9fa", pady=15)
        filter_frame.pack(fill="x", padx=20)

        tk.Label(filter_frame, text="Nombre del Documento:", fg="#334155", bg="#f8f9fa", font=("Helvetica", 9, "bold")).grid(row=0, column=0, padx=8, sticky="w")
        self.ent_nombre = tk.Entry(filter_frame, font=("Helvetica", 10), width=25, bg="#ffffff", fg="#1e293b", bd=0, highlightthickness=1, highlightbackground="#cbd5e1", highlightcolor="#0ea5e9")
        self.ent_nombre.grid(row=1, column=0, padx=8, pady=5, ipady=4)

        tk.Label(filter_frame, text="Tipo / Extensión:", fg="#334155", bg="#f8f9fa", font=("Helvetica", 9, "bold")).grid(row=0, column=1, padx=8, sticky="w")
        self.cmb_tipo = ttk.Combobox(filter_frame, values=["Todos", "PDF", "PowerPoint", "Excel", "Word"], font=("Helvetica", 10), width=15, state="readonly")
        self.cmb_tipo.set("Todos")
        self.cmb_tipo.grid(row=1, column=1, padx=8, pady=5, ipady=3)

        btn_buscar = tk.Button(filter_frame, text="🔍 BUSCAR DOCUMENTOS", font=("Helvetica", 10, "bold"), bg="#0ea5e9", fg="#ffffff", bd=0, activebackground="#38bdf8", activeforeground="#ffffff", padx=15, cursor="hand2", command=self.buscar_documentos)
        btn_buscar.grid(row=1, column=2, padx=20, pady=5, ipady=5)

        style = ttk.Style()
        style.theme_use("clam")
        style.configure("Treeview", background="#ffffff", fieldbackground="#ffffff", foreground="#334155", rowheight=35, font=("Helvetica", 10), borderwidth=0)
        style.configure("Treeview.Heading", background="#f1f5f9", foreground="#475569", font=("Helvetica", 9, "bold"), borderwidth=1, relief="flat")
        style.map("Treeview", background=[("selected", "#e0f2fe")], foreground=[("selected", "#0369a1")])

        table_frame = tk.Frame(self.root, bg="#ffffff", highlightbackground="#e2e8f0", highlightthickness=1)
        table_frame.pack(fill="both", expand=True, padx=20, pady=(0, 20))

        columnas = ("nombre", "fecha", "estado", "acciones")
        self.tabla = ttk.Treeview(table_frame, columns=columnas, show="headings", style="Treeview")
        
        self.tabla.heading("nombre", text="NOMBRE DEL DOCUMENTO")
        self.tabla.heading("fecha", text="FECHA DE REGISTRO")
        self.tabla.heading("estado", text="ESTADO")
        self.tabla.heading("acciones", text="ACCIONES")

        self.tabla.column("nombre", width=380, anchor="w")
        self.tabla.column("fecha", width=150, anchor="center")
        self.tabla.column("estado", width=140, anchor="center")
        self.tabla.column("acciones", width=150, anchor="center")
        
        self.tabla.pack(fill="both", expand=True, padx=2, pady=2)

    def buscar_documentos(self):
        for fila in self.tabla.get_children():
            self.tabla.delete(fila)

        query = {
            "compania_destino": self.usuario["compania"],
            "departamento": self.usuario["departamento"]
        }

        nombre_filtro = self.ent_nombre.get().strip()
        tipo_filtro = self.cmb_tipo.get()

        if nombre_filtro:
            query["nombre"] = {"$regex": nombre_filtro, "$options": "i"}
        if tipo_filtro != "Todos":
            query["tipo"] = tipo_filtro

        try:
            documentos = self.metadatos_col.find(query)
            for doc in documentos:
                nombre = doc.get("nombre", "Sin nombre")
                fecha = doc.get("fecha", "N/A")
                aut = doc.get("autorizado", "No")
                estado = "Autorizado" if aut in ["Sí", "SI", "Autorizado"] else "En Revisión"
                
                self.tabla.insert("", "end", values=(f"📄  {nombre}", fecha, estado, "📥 Descargar"))
        except Exception as e:
            print(f"Error consultando la colección de metadatos: {e}")

if __name__ == "__main__":
    # Evitar bucles infinitos si se ejecuta en entornos de servidor sin pantalla completa real
    if os.environ.get("DISPLAY") == ":99":
        print("Servidor virtual detectado en Docker. Iniciando el servicio en segundo plano de manera estable...")
        
    base_root = tk.Tk()
    app = LoginWindow(base_root)
    base_root.mainloop()