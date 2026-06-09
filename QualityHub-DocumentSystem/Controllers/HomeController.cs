using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MongoDB.Bson;
using MongoDB.Driver;
using QualityHub_DocumentSystem.Data;
using QualityHub_DocumentSystem.Models;

namespace QualityHub_DocumentSystem.Controllers
{
    public class HomeController : Controller
    {
        private readonly AppDbContext _context;
        private readonly string _rutaMasterStorage;

        public HomeController(AppDbContext context)
        {
            _context = context;
            _rutaMasterStorage = Path.Combine(Directory.GetCurrentDirectory(), "storage", "actuales");
        }

        public IActionResult Index() => View();

        [HttpPost]
        public IActionResult ValidarLogin(string username, string password)
        {
            var usuarioValido = _context.User.FirstOrDefault(u => u.UserName == username && u.PasswordHash == password);
            if (usuarioValido != null)
            {
                HttpContext.Session.SetString("UserName", usuarioValido.UserName);
                HttpContext.Session.SetInt32("UserRoleID", usuarioValido.RoleID);
                HttpContext.Session.SetInt32("UserID", usuarioValido.UserID);
                return RedirectToAction("Dashboard");
            }
            ModelState.AddModelError("", "Usuario o contraseña incorrectos.");
            return View("Index");
        }

        [HttpPost]
        public async Task<IActionResult> EnviarAlEcosistema(int documentoId)
        {
            var doc = await _context.Documento.FindAsync(documentoId);
            if (doc != null)
            {
                var version = await _context.DocumentoVersion.Where(v => v.DocumentoID == documentoId).OrderByDescending(v => v.FechaCreacion).FirstOrDefaultAsync();
                if (version != null) await NotificarPortalPHP(doc, version, "Vigente");
            }
            return Json(new { success = true, message = "Archivo sincronizado." });
        }

        public IActionResult Dashboard()
        {
            int roleID = HttpContext.Session.GetInt32("UserRoleID") ?? 0;
            if (roleID == 3) return RedirectToAction("ControlAutorizaciones");
            return View();
        }

        public async Task<IActionResult> ControlDocumentos()
        {
            int roleID = HttpContext.Session.GetInt32("UserRoleID") ?? 0;
            if (roleID == 3) return RedirectToAction("ControlAutorizaciones");

            var documentosDb = await _context.Documento.Include(d => d.DocumentoVersion).ToListAsync();
            var apartadosDocumentales = new List<dynamic>();

            foreach (var doc in documentosDb)
            {
                var ultimaVersion = doc.DocumentoVersion.OrderByDescending(v => v.FechaCreacion).FirstOrDefault();
                string versionStr = ultimaVersion != null ? ultimaVersion.CodigoVersion : "1.0";
                string archivoReal = ultimaVersion != null ? Path.GetFileName(ultimaVersion.RutaArchivo) : doc.Nombre + doc.Extension;

                apartadosDocumentales.Add(new
                {
                    Id = doc.DocumentoID,
                    Codigo = "DOC-" + doc.DocumentoID.ToString("D4"),
                    Nombre = doc.Nombre,
                    Extension = !string.IsNullOrEmpty(doc.Extension) ? doc.Extension.Replace(".", "").ToUpper() : "FILE",
                    Estado = doc.Estado,
                    Version = "v" + versionStr,
                    ArchivoFisico = archivoReal,
                    RutaWeb = ultimaVersion != null ? ultimaVersion.RutaArchivo : $"/storage/actuales/{archivoReal}"
                });
            }

            ViewBag.UserRole = roleID;
            ViewBag.Apartados = apartadosDocumentales;
            return View();
        }

        [HttpPost]
        public async Task<IActionResult> ProcesarRevision(int documentoId, string dictamen, string comentarios)
        {
            try
            {
                var docPrincipal = await _context.Documento.FindAsync(documentoId);
                if (docPrincipal != null)
                {
                    docPrincipal.Estado = dictamen;
                    _context.Entry(docPrincipal).State = EntityState.Modified;

                    var ultimaVersion = await _context.DocumentoVersion.OrderByDescending(v => v.FechaCreacion).FirstOrDefaultAsync(v => v.DocumentoID == documentoId);

                    var log = new DocumentoLog
                    {
                        VersionActualID = ultimaVersion?.VersionID ?? 0,
                        // 🌟 FIX: Usar UserID real de la sesión (No el RoleID)
                        UserID = HttpContext.Session.GetInt32("UserID") ?? 1,
                        Accion = dictamen == "Revisado" ? "Aprobado por Revisador" : "Rechazado por Revisador",
                        Comentario = string.IsNullOrEmpty(comentarios) ? "Sin comentarios" : comentarios,
                        FechaMovimiento = DateTime.Now
                    };

                    _context.DocumentoLogs.Add(log);
                    await _context.SaveChangesAsync();
                    TempData["Mensaje"] = $"¡Dictamen ({dictamen}) registrado correctamente!";
                }
            }
            catch (Exception ex)
            {
                var errorReal = ex.InnerException != null ? ex.InnerException.Message : ex.Message;
                TempData["Error"] = $"Error exacto de SQL: {errorReal}";
            }
            return RedirectToAction("ControlDocumentos");
        }

        [HttpGet]
        public async Task<IActionResult> ObtenerSugerencias(int documentoId)
        {
            var versionesIds = await _context.DocumentoVersion.Where(v => v.DocumentoID == documentoId).Select(v => v.VersionID).ToListAsync();

            var logPhp = await _context.DocumentoLogs
                .Where(l => versionesIds.Contains(l.VersionActualID) && l.Accion.Contains("Falla"))
                .OrderByDescending(l => l.FechaMovimiento)
                .FirstOrDefaultAsync();

            var ultimoLogRevisador = await _context.DocumentoLogs
                .Where(l => versionesIds.Contains(l.VersionActualID) && !l.Accion.Contains("Falla"))
                .OrderByDescending(l => l.FechaMovimiento)
                .FirstOrDefaultAsync();

            string sugerenciaPHP = logPhp != null ? logPhp.Comentario : "No hay fallas reportadas por PHP para esta versión.";
            string mensajeRevisador = ultimoLogRevisador != null ? ultimoLogRevisador.Comentario : "El revisador no dejó comentarios específicos.";

            return Json(new { success = true, sugerenciaPHP = sugerenciaPHP, mensajeRevisador = mensajeRevisador });
        }

        [HttpPost]
        public async Task<IActionResult> CrearDocumento(string codigo, string version, string nombre, string extension, IFormFile archivoDigital)
        {
            try
            {
                if (archivoDigital == null || archivoDigital.Length == 0)
                {
                    TempData["Error"] = "Debes adjuntar un archivo digital.";
                    return RedirectToAction("Creador", "Home");
                }
                int userIdActual = HttpContext.Session.GetInt32("UserID") ?? 0;
                var userExiste = await _context.User.FindAsync(userIdActual);
                if (userExiste == null) userIdActual = 1;

                var docReferencia = await _context.Documento.FirstOrDefaultAsync();
                int empresaId = docReferencia?.EmpresaID ?? 1;

                if (!Directory.Exists(_rutaMasterStorage)) Directory.CreateDirectory(_rutaMasterStorage);
                string rutaCompleta = Path.Combine(_rutaMasterStorage, archivoDigital.FileName);
                using (var stream = new FileStream(rutaCompleta, FileMode.Create)) { await archivoDigital.CopyToAsync(stream); }

                var docExistente = await _context.Documento.FirstOrDefaultAsync(d => d.Nombre == nombre);
                string nuevaVersionStr = "1.0";
                int documentoId = 0;

                if (docExistente != null)
                {
                    var ultimaVersion = await _context.DocumentoVersion.Where(v => v.DocumentoID == docExistente.DocumentoID).OrderByDescending(v => v.FechaCreacion).FirstOrDefaultAsync();
                    if (ultimaVersion != null && !string.IsNullOrEmpty(ultimaVersion.CodigoVersion))
                    {
                        var partes = ultimaVersion.CodigoVersion.Split('.');
                        if (partes.Length == 2 && int.TryParse(partes[1], out int minor)) nuevaVersionStr = $"{partes[0]}.{minor + 1}";
                        else nuevaVersionStr = ultimaVersion.CodigoVersion + ".1";
                    }
                    docExistente.Estado = "Pendiente Revisión";
                    docExistente.Extension = extension;
                    _context.Documento.Update(docExistente);
                    await _context.SaveChangesAsync();
                    documentoId = docExistente.DocumentoID;
                }
                else
                {
                    var nuevoDoc = new Documento { Nombre = nombre, Extension = extension, Estado = "Pendiente Revisión", EmpresaID = empresaId, CreadoPor = userIdActual, FechaCreacion = DateTime.Now };
                    _context.Documento.Add(nuevoDoc);
                    await _context.SaveChangesAsync();
                    documentoId = nuevoDoc.DocumentoID;
                }

                _context.DocumentoVersion.Add(new DocumentoVersion { DocumentoID = documentoId, CodigoVersion = nuevaVersionStr, RutaArchivo = $"/storage/actuales/{archivoDigital.FileName}", FechaCreacion = DateTime.Now });
                await _context.SaveChangesAsync();
                TempData["Mensaje"] = $"¡Documento '{nombre}' guardado como versión {nuevaVersionStr} y enviado al Revisador!";
            }
            catch (Exception ex)
            {
                TempData["Error"] = $"Error al transferir: {ex.InnerException?.Message ?? ex.Message}";
            }
            return RedirectToAction("Creador", "Home");
        }

        [HttpPost]
        public async Task<IActionResult> CargarArchivo(int documentoId, IFormFile archivoFisico)
        {
            try
            {
                if (archivoFisico == null || archivoFisico.Length == 0) return Json(new { success = false, message = "Archivo vacío." });
                var docExistente = await _context.Documento.FindAsync(documentoId);
                if (docExistente == null) return Json(new { success = false, message = "Documento no encontrado." });

                if (!Directory.Exists(_rutaMasterStorage)) Directory.CreateDirectory(_rutaMasterStorage);
                string rutaCompleta = Path.Combine(_rutaMasterStorage, archivoFisico.FileName);
                using (var stream = new FileStream(rutaCompleta, FileMode.Create)) { await archivoFisico.CopyToAsync(stream); }

                var ultimaVersion = await _context.DocumentoVersion.Where(v => v.DocumentoID == documentoId).OrderByDescending(v => v.FechaCreacion).FirstOrDefaultAsync();
                string nuevaVersionStr = "1.0";
                if (ultimaVersion != null && !string.IsNullOrEmpty(ultimaVersion.CodigoVersion))
                {
                    var partes = ultimaVersion.CodigoVersion.Split('.');
                    if (partes.Length == 2 && int.TryParse(partes[1], out int minor)) nuevaVersionStr = $"{partes[0]}.{minor + 1}";
                }

                docExistente.Estado = "Pendiente Revisión";
                string ext = Path.GetExtension(archivoFisico.FileName)?.Replace(".", "").ToUpper();
                if (!string.IsNullOrEmpty(ext) && ext.Length > 10) ext = ext.Substring(0, 10);
                docExistente.Extension = ext;
                _context.Documento.Update(docExistente);

                var nuevaVersion = new DocumentoVersion { DocumentoID = documentoId, CodigoVersion = nuevaVersionStr, RutaArchivo = $"/storage/actuales/{archivoFisico.FileName}", FechaCreacion = DateTime.Now };
                _context.DocumentoVersion.Add(nuevaVersion);
                await _context.SaveChangesAsync();

                _context.DocumentoLogs.Add(new DocumentoLog { VersionActualID = nuevaVersion.VersionID, UserID = HttpContext.Session.GetInt32("UserID") ?? 1, Accion = "Carga de Nueva Versión Corregida", Comentario = $"El creador subió la corrección.", FechaMovimiento = DateTime.Now });
                await _context.SaveChangesAsync();

                TempData["Mensaje"] = $"¡Documento corregido y enviado al Revisador como versión {nuevaVersionStr}!";
                return Json(new { success = true });
            }
            catch (Exception ex)
            {
                var errorReal = ex.InnerException != null ? ex.InnerException.Message : ex.Message;
                return Json(new { success = false, message = "Falla en BD: " + errorReal });
            }
        }

        [HttpGet]
        public async Task<IActionResult> ObtenerDetallesAutorizacion(int documentoId)
        {
            var doc = await _context.Documento.FindAsync(documentoId);
            var versiones = await _context.DocumentoVersion.Where(v => v.DocumentoID == documentoId).ToListAsync();
            var versionIds = versiones.Select(v => v.VersionID).ToList();

            var logs = await _context.DocumentoLogs.Where(l => versionIds.Contains(l.VersionActualID)).OrderByDescending(l => l.FechaMovimiento).ToListAsync();
            var logRevisador = logs.FirstOrDefault(l => l.Accion.Contains("Aprobado") || l.Accion.Contains("Revisado"));

            string nombreCreador = "Usuario del Sistema";
            if (doc?.CreadoPor != null)
            {
                var userCreador = await _context.User.FindAsync(doc.CreadoPor);
                if (userCreador != null) nombreCreador = userCreador.UserName;
            }

            string nombreRevisador = "Revisador del Sistema";
            if (logRevisador?.UserID != null)
            {
                var userRevisador = await _context.User.FindAsync(logRevisador.UserID);
                if (userRevisador != null) nombreRevisador = userRevisador.UserName;
            }

            var logPhp = logs.FirstOrDefault(l => l.Accion.Contains("Falla"));
            string sugerenciaPHP = logPhp != null ? logPhp.Comentario : "Sin reportes del portal público (PHP).";
            string mensajeRevisador = logRevisador != null ? logRevisador.Comentario : "El revisador no dejó comentarios.";

            return Json(new { success = true, creador = nombreCreador, revisador = nombreRevisador, sugerenciaPHP = sugerenciaPHP, mensajeRevisador = mensajeRevisador });
        }
        [HttpPost]
        public async Task<IActionResult> ProcesarAutorizacion(int documentoId, string dictamen, string comentarios)
        {
            try
            {
                var doc = await _context.Documento.FindAsync(documentoId);
                if (doc != null)
                {
                    doc.Estado = dictamen == "Autorizado" ? "Vigente" : "Pendiente Revisión";
                    _context.Documento.Update(doc);

                    var ultimaVersion = await _context.DocumentoVersion
                        .OrderByDescending(v => v.FechaCreacion)
                        .FirstOrDefaultAsync(v => v.DocumentoID == documentoId);

                    // Solo agregamos log si hay una versión válida (evita el crash de FK)
                    if (ultimaVersion != null)
                    {
                        _context.DocumentoLogs.Add(new DocumentoLog
                        {
                            VersionActualID = ultimaVersion.VersionID,
                            UserID = HttpContext.Session.GetInt32("UserID") ?? 1,
                            Accion = dictamen == "Autorizado" ? "Autorizado por Dirección" : "Rechazado por Autorizador",
                            Comentario = string.IsNullOrEmpty(comentarios) ? "Sin comentarios adicionales" : comentarios,
                            FechaMovimiento = DateTime.Now
                        });
                    }

                    await _context.SaveChangesAsync(); // Ahora sí no crashea

                    // Ahora NotificarPortalPHP SÍ se ejecuta
                    if (dictamen == "Autorizado")
                    {
                        await NotificarPortalPHP(doc, ultimaVersion, "Vigente");
                    }
                }
            }
            catch (Exception ex)
            {
                var errorReal = ex.InnerException != null ? ex.InnerException.Message : ex.Message;
                Console.WriteLine($"[ProcesarAutorizacion ERROR] {errorReal}");
                TempData["Error"] = $"Error exacto de SQL: {errorReal}";
            }
            return RedirectToAction("ControlAutorizaciones");
        }

        [HttpPost]
        [Route("api/RecibirFalla")]
        public async Task<IActionResult> RecibirFallaDesdePHP([FromForm] string codigoIso, [FromForm] string sugerencia)
        {
            try
            {
                if (string.IsNullOrEmpty(codigoIso))
                    return BadRequest(new { success = false, message = "Código ISO requerido" });

                var doc = await _context.Documento.FirstOrDefaultAsync(d => d.Codigo == codigoIso);
                if (doc == null)
                    return NotFound(new { success = false, message = $"Documento '{codigoIso}' no encontrado en C#" });

                doc.Estado = "Rechazado";
                _context.Documento.Update(doc);

                var ultimaVersion = await _context.DocumentoVersion
                    .OrderByDescending(v => v.FechaCreacion)
                    .FirstOrDefaultAsync(v => v.DocumentoID == doc.DocumentoID);

                // Solo agregamos el log si existe una versión válida
                if (ultimaVersion != null)
                {
                    var userID = HttpContext.Session.GetInt32("UserID") ?? doc.CreadoPor;

                    _context.DocumentoLogs.Add(new DocumentoLog
                    {
                        VersionActualID = ultimaVersion.VersionID,
                        UserID = userID,
                        Accion = "Falla Reportada desde Portal PHP",
                        Comentario = sugerencia ?? "Se reportó un problema de calidad.",
                        FechaMovimiento = DateTime.Now
                    });
                }

                await _context.SaveChangesAsync();
                return Ok(new { success = true, message = "Falla registrada en C#" });
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[RecibirFalla ERROR] {ex.Message} | Inner: {ex.InnerException?.Message}");
                return StatusCode(500, new { success = false, error = ex.InnerException?.Message ?? ex.Message });
            }
        }

        private async Task NotificarPortalPHP(Documento doc, DocumentoVersion versionActual, string estadoFijo)
        {
            try
            {
                using var client = new HttpClient();
                client.Timeout = TimeSpan.FromSeconds(10);
                var phpUrl = "http://php_portal_container:80";

                // Usamos IsNullOrEmpty para cubrir tanto null como string vacío
                var nombreFinal = !string.IsNullOrEmpty(doc.Nombre) ? doc.Nombre : "Documento"; var codigoFinal = !string.IsNullOrEmpty(doc.Codigo) ? doc.Codigo : $"DOC-{doc.DocumentoID}";
                var versionFinal = versionActual?.CodigoVersion ?? "1.0";
                var extensionFinal = doc.Extension ?? ".pdf";

                var values = new Dictionary<string, string>
        {
            { "nombre",        nombreFinal },
            { "extension",     extensionFinal },
            { "version",       versionFinal },
            { "estado",        estadoFijo },
            { "nombre_fisico", $"{codigoFinal}_v{versionFinal}{extensionFinal}" },
            { "codigo_iso",    codigoFinal }
        };

                var content = new FormUrlEncodedContent(values);
                var response = await client.PostAsync($"{phpUrl}/api_recibir_documento.php", content);
                var body = await response.Content.ReadAsStringAsync();
                Console.WriteLine($"[PHP Sync] Status: {response.StatusCode} | Respuesta: {body}");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[PHP Sync ERROR] {ex.Message}");
            }
        }

        [HttpGet]
        public async Task<IActionResult> ObtenerHistorialDocumento(int documentoId)
        {
            var doc = await _context.Documento.FindAsync(documentoId);
            var versiones = await _context.DocumentoVersion.Where(v => v.DocumentoID == documentoId).OrderByDescending(v => v.FechaCreacion).ToListAsync();
            var versionIds = versiones.Select(v => v.VersionID).ToList();
            var logs = await _context.DocumentoLogs.Where(l => versionIds.Contains(l.VersionActualID)).OrderByDescending(l => l.FechaMovimiento).ToListAsync();

            var historial = new List<object>();
            foreach (var log in logs)
            {
                string nombreUsuario = "Sistema";
                var user = await _context.User.FindAsync(log.UserID);
                if (user != null) nombreUsuario = user.UserName;

                historial.Add(new { accion = log.Accion, comentario = log.Comentario, usuario = nombreUsuario, fecha = log.FechaMovimiento.ToString("dd/MM/yyyy HH:mm") });
            }

            string creador = "Sin datos";
            if (doc?.CreadoPor != null)
            {
                var uc = await _context.User.FindAsync(doc.CreadoPor);
                if (uc != null) creador = uc.UserName;
            }

            string versionActual = versiones.FirstOrDefault()?.CodigoVersion ?? "1.0";
            return Json(new { success = true, nombre = doc?.Nombre ?? "Documento", estado = doc?.Estado ?? "Desconocido", version = versionActual, creador = creador, historial = historial });
        }

        [HttpPost]
        public async Task<IActionResult> RecibirSugerenciaPhp([FromBody] SugerenciaPhpDto datos)
        {
            try
            {
                if (datos == null || datos.DocumentoId <= 0) return BadRequest(new { success = false, error = "Datos inválidos." });

                var doc = await _context.Documento.FindAsync(datos.DocumentoId);
                var ultimaVersion = await _context.DocumentoVersion.Where(v => v.DocumentoID == datos.DocumentoId).OrderByDescending(v => v.FechaCreacion).FirstOrDefaultAsync();

                _context.DocumentoLogs.Add(new DocumentoLog
                {
                    VersionActualID = ultimaVersion?.VersionID ?? 0,
                    // 🌟 FIX: Asignar al creador
                    UserID = doc != null ? doc.CreadoPor : 1,
                    Accion = "Reporte de Falla (Desde Dashboard PHP)",
                    Comentario = $"[{datos.Origen ?? "PHP"}] {datos.Comentarios}",
                    FechaMovimiento = DateTime.Now
                });

                if (doc != null)
                {
                    doc.Estado = "Rechazado";
                    _context.Documento.Update(doc);
                }

                await _context.SaveChangesAsync();
                return Ok(new { success = true, mensaje = "Sugerencia enviada al creador." });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new { success = false, error = ex.Message });
            }
        }

        public async Task<IActionResult> ControlAutorizaciones()
        {
            var pendientes = await _context.Documento.Where(d => d.Estado == "Revisado").ToListAsync();
            return View(pendientes);
        }
        public async Task<IActionResult> Creador() => View(await _context.Documento.ToListAsync());
        public IActionResult CerrarSesion() { HttpContext.Session.Clear(); return RedirectToAction("Index", "Home"); }

        // ============================================================
        // BÚSQUEDA AVANZADA EN MONGODB
        // ============================================================
        [HttpGet]
        public async Task<IActionResult> BuscarDocumentosMongo(
            string? q, string? ext, string? fecha,
            int pagina = 1, int porPagina = 10)
        {
            try
            {
                var mongoClient = new MongoClient("mongodb://mongodb_container:27017");
                var db = mongoClient.GetDatabase("proyectofinal");
                var col = db.GetCollection<BsonDocument>("metadatos");

                // Construir filtro dinámico
                var filtros = new List<FilterDefinition<BsonDocument>>();

                // Filtro por texto (busca en múltiples campos de metadatos)
                if (!string.IsNullOrWhiteSpace(q))
                {
                    var textoRegex = new BsonRegularExpression(q, "i");
                    var camposTexto = new[]
                    {
                        "nombre", "nombre_fisico", "codigo_iso", "estado",
                        "author", "company", "title", "subject",
                        "contenido_extraido", "texto_completo", "content"
                    };
                    var oTexto = camposTexto
                        .Select(c => Builders<BsonDocument>.Filter.Regex(c, textoRegex))
                        .ToList();
                    filtros.Add(Builders<BsonDocument>.Filter.Or(oTexto));
                }

                // Filtro por extensión
                if (!string.IsNullOrWhiteSpace(ext))
                {
                    var extRegex = new BsonRegularExpression(ext, "i");
                    filtros.Add(Builders<BsonDocument>.Filter.Or(
                        Builders<BsonDocument>.Filter.Regex("extension", extRegex),
                        Builders<BsonDocument>.Filter.Regex("nombre_fisico", extRegex)
                    ));
                }

                // Filtro por fecha
                if (!string.IsNullOrWhiteSpace(fecha) && DateTime.TryParse(fecha, out var fechaFiltro))
                {
                    var inicio = new BsonDateTime(fechaFiltro.Date);
                    var fin    = new BsonDateTime(fechaFiltro.Date.AddDays(1));
                    filtros.Add(Builders<BsonDocument>.Filter.Or(
                        Builders<BsonDocument>.Filter.And(
                            Builders<BsonDocument>.Filter.Gte("fecha_modificacion", inicio),
                            Builders<BsonDocument>.Filter.Lt("fecha_modificacion", fin)
                        ),
                        Builders<BsonDocument>.Filter.And(
                            Builders<BsonDocument>.Filter.Gte("ultima_modificacion", inicio),
                            Builders<BsonDocument>.Filter.Lt("ultima_modificacion", fin)
                        )
                    ));
                }

                var filtroFinal = filtros.Count > 0
                    ? Builders<BsonDocument>.Filter.And(filtros)
                    : Builders<BsonDocument>.Filter.Empty;

                var total = await col.CountDocumentsAsync(filtroFinal);
                var totalPaginas = (int)Math.Ceiling((double)total / porPagina);

                var docs = await col
                    .Find(filtroFinal)
                    .Skip((pagina - 1) * porPagina)
                    .Limit(porPagina)
                    .ToListAsync();

                var resultados = docs.Select(doc =>
                {
                    var nombre      = doc.GetValue("nombre", BsonNull.Value)?.ToString() ?? "";
                    var nombreFis   = doc.GetValue("nombre_fisico", BsonNull.Value)?.ToString() ?? "";
                    var codigoIso   = doc.GetValue("codigo_iso", BsonNull.Value)?.ToString() ?? "N/A";
                    var estado      = doc.GetValue("estado", BsonNull.Value)?.ToString() ?? "";
                    var version     = doc.GetValue("version", BsonNull.Value)?.ToString() ?? "N/A";
                    var extension   = doc.GetValue("extension", BsonNull.Value)?.ToString() ?? "";
                    var author      = doc.GetValue("author", BsonNull.Value)?.ToString() ?? "";
                    var company     = doc.GetValue("company", BsonNull.Value)?.ToString() ?? "";
                    var rutaWeb     = doc.GetValue("ruta_web", BsonNull.Value)?.ToString() ?? "";

                    // Generar snippet con coincidencia resaltada
                    string snippet = "";
                    if (!string.IsNullOrWhiteSpace(q))
                    {
                        var camposPosibles = new[] { "contenido_extraido", "texto_completo", "content", "subject", "title" };
                        foreach (var campo in camposPosibles)
                        {
                            if (doc.Contains(campo))
                            {
                                var texto = doc[campo]?.ToString() ?? "";
                                if (texto.Contains(q, StringComparison.OrdinalIgnoreCase))
                                {
                                    var idx = texto.IndexOf(q, StringComparison.OrdinalIgnoreCase);
                                    var inicio = Math.Max(0, idx - 60);
                                    var fin    = Math.Min(texto.Length, idx + q.Length + 80);
                                    var frag   = texto.Substring(inicio, fin - inicio);
                                    // Resaltar con <mark>
                                    snippet = System.Text.RegularExpressions.Regex.Replace(
                                        System.Net.WebUtility.HtmlEncode(frag),
                                        System.Text.RegularExpressions.Regex.Escape(System.Net.WebUtility.HtmlEncode(q)),
                                        m => $"<mark>{m.Value}</mark>",
                                        System.Text.RegularExpressions.RegexOptions.IgnoreCase
                                    );
                                    if (inicio > 0) snippet = "..." + snippet;
                                    if (fin < texto.Length) snippet += "...";
                                    break;
                                }
                            }
                        }
                        // Si no hubo snippet de contenido, buscar en metadatos simples
                        if (string.IsNullOrEmpty(snippet))
                        {
                            foreach (var elem in doc.Elements)
                            {
                                if (elem.Name.StartsWith("_")) continue;
                                var val = elem.Value?.ToString() ?? "";
                                if (val.Contains(q, StringComparison.OrdinalIgnoreCase))
                                {
                                    var campo = elem.Name.Replace("_", " ");
                                    snippet = $"<strong>{System.Net.WebUtility.HtmlEncode(campo)}:</strong> " +
                                              System.Text.RegularExpressions.Regex.Replace(
                                                  System.Net.WebUtility.HtmlEncode(val),
                                                  System.Text.RegularExpressions.Regex.Escape(System.Net.WebUtility.HtmlEncode(q)),
                                                  m => $"<mark>{m.Value}</mark>",
                                                  System.Text.RegularExpressions.RegexOptions.IgnoreCase
                                              );
                                    break;
                                }
                            }
                        }
                    }

                    return new
                    {
                        nombre, nombreFisico = nombreFis, codigoIso,
                        estado, version, extension, author, company, rutaWeb, snippet
                    };
                }).ToList();

                return Json(new { resultados, total, totalPaginas });
            }
            catch (Exception ex)
            {
                return Json(new { resultados = new object[0], total = 0, totalPaginas = 0, error = ex.Message });
            }
        }

        [HttpGet]
        public async Task<IActionResult> ObtenerMetadatosMongo(string archivo)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(archivo))
                    return Json(new { error = "Nombre de archivo requerido" });

                var mongoClient = new MongoClient("mongodb://mongodb_container:27017");
                var db = mongoClient.GetDatabase("proyectofinal");
                var col = db.GetCollection<BsonDocument>("metadatos");

                var filtro = Builders<BsonDocument>.Filter.Or(
                    Builders<BsonDocument>.Filter.Regex("nombre_fisico", new BsonRegularExpression(archivo, "i")),
                    Builders<BsonDocument>.Filter.Regex("nombre", new BsonRegularExpression(archivo, "i"))
                );

                var doc = await col.Find(filtro).FirstOrDefaultAsync();

                if (doc == null)
                    return Json(new { error = "Documento no encontrado en MongoDB" });

                // Convertir a diccionario limpio (sin ObjectId binarios)
                var resultado = new Dictionary<string, object?>();
                foreach (var elem in doc.Elements)
                {
                    if (elem.Name == "_id") continue;
                    var val = elem.Value?.BsonType == BsonType.ObjectId
                        ? elem.Value.AsObjectId.ToString()
                        : elem.Value?.ToString();
                    if (!string.IsNullOrEmpty(val))
                        resultado[elem.Name] = val;
                }

                return Json(resultado);
            }
            catch (Exception ex)
            {
                return Json(new { error = ex.Message });
            }
        }
    }
}