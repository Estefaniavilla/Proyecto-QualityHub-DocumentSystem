# 🛡️ QualityHub DocumentSystem 
> **Arquitectura Políglota de Microservicios para la Gestión Documental ISO**

<div align="center">
  <br>
  <img src="https://img.shields.io/badge/C%23-239120?style=for-the-badge&logo=c-sharp&logoColor=white" alt="C#">
  <img src="https://img.shields.io/badge/.NET_8-512BD4?style=for-the-badge&logo=dotnet&logoColor=white" alt=".NET 8">
  <img src="https://img.shields.io/badge/PHP_8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/Python_3.11-3776AB?style=for-the-badge&logo=python&logoColor=white" alt="Python 3.11">
  <img src="https://img.shields.io/badge/Flask-000000?style=for-the-badge&logo=flask&logoColor=white" alt="Flask">
  <br><br>
  <img src="https://img.shields.io/badge/SQL_Server_2022-CC2927?style=for-the-badge&logo=microsoft-sql-server&logoColor=white" alt="SQL Server">
  <img src="https://img.shields.io/badge/PostgreSQL_16-316192?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/MongoDB_7.0-4EA94B?style=for-the-badge&logo=mongodb&logoColor=white" alt="MongoDB">
  <img src="https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
</div>

---

## 📖 Descripción del Proyecto

**QualityHub DocumentSystem** es una plataforma distribuida de grado empresarial diseñada para el control estricto, auditoría y distribución de información documentada bajo normativas ISO (9001, 14001, 45001). 

A diferencia de los sistemas monolíticos tradicionales, QualityHub implementa una **Arquitectura de Microservicios Políglota**. El ecosistema se divide en tres módulos totalmente independientes (Gestión, Operación y Búsqueda), donde cada uno fue desarrollado en el lenguaje de programación más adecuado para su tarea y respaldado por una base de datos especializada, maximizando así el rendimiento, la escalabilidad y la seguridad del sistema.

---

## 🏛️ Topología de los Módulos y Bases de Datos

El diseño del sistema se fundamenta en la premisa de "la herramienta adecuada para el trabajo adecuado". 

### ⚙️ 1. Módulo Central (Gestión y Aprobación)
Este es el "Cerebro" del sistema. Administra el ciclo de vida, los flujos de autorización y las firmas electrónicas.
- **💻 Backend:** C# con ASP.NET Core 8.
- **🗄️ Base de Datos:** `SQL Server 2022`.
- **🎯 Justificación:** SQL Server garantiza transacciones ACID perfectas. Aquí reside la verdad absoluta sobre el estatus oficial de los documentos. Maneja la creación, versionado y logs de auditoría inmutables mediante Entity Framework Core.

### 👥 2. Portal de Operarios (Sincronización y Lectura)
Diseñado para la consulta masiva y concurrente por parte de los operadores de planta o sucursales.
- **💻 Backend:** PHP 8.2 + Vanilla JavaScript (ES2022).
- **🗄️ Base de Datos:** `PostgreSQL 16`.
- **🎯 Justificación:** PostgreSQL es ideal para manejar grandes volúmenes de consultas de lectura y generar reportes analíticos complejos mediante PDO. Este módulo recibe los datos de C# y los expone de forma amigable, registrando la trazabilidad de descargas y vistas.

### 🔍 3. Buscador Avanzado (Indexación y Analítica)
Microservicio dedicado exclusivamente a la búsqueda ultrarrápida dentro del acervo documental.
- **💻 Backend:** Python 3.11 con Flask (API REST).
- **🗄️ Base de Datos:** `MongoDB 7.0`.
- **🎯 Justificación:** MongoDB permite almacenar metadatos de documentos en colecciones JSON flexibles mediante PyMongo. Su motor de indexación garantiza tiempos de respuesta de milisegundos en búsquedas dinámicas, sin saturar las bases de datos relacionales.

---

## 🔄 Flujo de Vida del Documento (Comunicación Inter-Servicios)

El sistema garantiza un control estricto sobre cuándo y cómo fluye la información entre las distintas tecnologías:

1. **📥 Creación (.NET):** Un *Creador* sube un nuevo documento utilizando el Módulo Central en C#.
2. **🔒 Bloqueo en Bóveda (SQL Server):** SQL Server guarda y bloquea el documento en estado *"En Revisión"*. Durante esta fase, el documento es completamente invisible para el Portal PHP y el Buscador Python.
3. **✍️ Auditoría y Firma:** Un *Revisador* verifica el archivo y lo pasa "En Fila". Posteriormente, la Dirección emite un dictamen.
4. **🚀 Autorización y Sincronización Simultánea:** Al aprobarse, el estado cambia a *"Vigente"*. Inmediatamente, **.NET dispara webhooks (HTTP POST)** hacia las APIs de PHP y Python.
5. **📡 Distribución:** Los contenedores de PHP y Python atrapan el *payload* e inyectan el documento en PostgreSQL y MongoDB simultáneamente, haciéndolo visible a toda la empresa en tiempo real.

---

## 🐳 Arquitectura de Contenedores (Docker)

Todo el ecosistema está orquestado con **Docker Compose**, lo que elimina por completo el problema de *"en mi máquina sí funciona"*. El sistema se despliega a través de tres grupos de contenedores (`Compose Projects`) que se comunican a través de una red virtual privada y encriptada llamada `app-network`.

### 📦 Mapeo de Contenedores y Puertos

| Grupo (Stack) | Nombre del Contenedor | Imagen Base | Puerto Expuesto (Host:Container) | Función |
|---|---|---|---|---|
| **`grupo-csharp`** | `csharp_app_container` | `grupo-csharp-csharp-app` | `7083:8080` | Backend ASP.NET Core (Portal Administrativo) |
| **`grupo-csharp`** | `sql_server_db` | `mssql/server:2022-latest` | `1433:1433` | Motor transaccional principal |
| **`grupo-php`** | `php_portal_container` | `chialab/php:8.2-apache` | `8081:80` | Servidor Apache para el Dashboard de Operadores |
| **`grupo-php`** | `postgres_db_container` | `postgres:16` | `5433:5432` | Motor relacional para reportes operativos |
| **`grupo-python`**| `python_busqueda_container`| `grupo-python-python...` | `8082:5000` | API REST Flask para búsquedas complejas |
| **`grupo-python`**| `mongodb_container` | `mongo:7` | `27017:27017` | Base de datos NoSQL para indexación JSON |

---

## 🛠️ Guía Rápida de Despliegue

Sigue estos pasos para levantar el clúster completo en tu entorno local o servidor de producción:

**1. Generar la red de microservicios:**
```bash
docker network create app-network
```

**2. Desplegar el clúster de Base de Datos y Motor PHP:**
```bash
cd php-portal
docker-compose -f docker-compose-php.yml up --build -d
```

**3. Desplegar el clúster Analítico (Python + MongoDB):**
```bash
cd python-searcher
docker-compose -f docker-compose-python.yml up --build -d
```

**4. Desplegar el clúster Core (.NET + SQL Server):**
```bash
cd csharp-app
docker-compose -f docker-compose-csharp.yml up --build -d
```

✅ Para comprobar el estado de todos los nodos en tiempo real, puedes utilizar Docker Desktop o el comando:
```bash
docker ps -a
```

---

## 🛡️ Seguridad y Trazabilidad

- **Segregación de Funciones:** Implementación estricta del principio *"Maker-Checker"* (El creador no autoriza, el autorizador no crea).
- **Control Inverso de Errores:** Si un operador detecta un documento corrupto en el portal PHP, puede pulsar "Reportar Falla". La API de PHP envía una petición inversa al contenedor C#, el cual cambia el estatus en SQL Server a *"En Revisión"* y oculta el documento automáticamente de la red corporativa para evitar no conformidades.

---

> 💡 **Nota del Desarrollador:** Este proyecto demuestra la capacidad de integrar múltiples lenguajes de programación, patrones de diseño de microservicios, bases de datos SQL/NoSQL y orquestación de contenedores para resolver un problema corporativo real de manera elegante y escalable.
