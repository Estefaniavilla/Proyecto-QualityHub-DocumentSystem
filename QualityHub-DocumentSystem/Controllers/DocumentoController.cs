using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityHub_DocumentSystem.Data;
using QualityHub_DocumentSystem.Models;
using System.Linq;
using System.Threading.Tasks;

namespace QualityHub_DocumentSystem.Controllers
{
    public class DocumentoController : Controller
    {
        // Variable para usar la base de datos
        private readonly AppDbContext _context;

        // Constructor donde inyectamos la base de datos
        public DocumentoController(AppDbContext context)
        {
            _context = context;
        }

        // 1. GET: /Documento/Crear
        // Muestra el formulario para redactar un documento desde cero
        [HttpGet]
        public IActionResult Crear()
        {
            return View();
        }

        // 2. GET: /Documento/Subir
        // Muestra el formulario para cargar un archivo existente (PDF, Word, etc.)
        [HttpGet]
        public IActionResult Subir()
        {
            return View();
        }

        // 3. GET: /Documento/VerSugerencia/5
        // Muestra la vista con el comentario de la falla reportada desde PHP
        [HttpGet]
        public async Task<IActionResult> VerSugerencia(int id)
        {
            // 1. Buscamos el documento en la tabla
            var documento = await _context.Documento.FindAsync(id);
            if (documento == null) return NotFound();

            // 2. Buscamos los IDs de todas las versiones de este documento
            var versionesIds = await _context.DocumentoVersion
                                             .Where(v => v.DocumentoID == id)
                                             .Select(v => v.VersionID)
                                             .ToListAsync();

            // 3. Buscamos el último log en la BD que coincida con esas versiones y que sea la alerta de PHP
            var ultimoLog = await _context.DocumentoLogs
                                          .Where(l => versionesIds.Contains(l.VersionActualID) && l.Accion.Contains("Alerta de falla"))
                                          .OrderByDescending(l => l.FechaMovimiento)
                                          .FirstOrDefaultAsync();

            // 4. Pasamos los datos a la vista usando ViewBag
            ViewBag.Documento = documento;
            ViewBag.ComentarioFalla = ultimoLog != null ? ultimoLog.Comentario : "El comentario no se encontró en la base de datos.";
            ViewBag.FechaFalla = ultimoLog != null ? ultimoLog.FechaMovimiento.ToString("dd/MM/yyyy a las HH:mm") : "";

            return View();
        }

        // 4. GET: /Documento/EnviarARevisador/5
        // El Creador ya corrigió la falla y se lo manda al Revisador
        [HttpGet]
        public async Task<IActionResult> EnviarARevisador(int id)
        {
            var documento = await _context.Documento.FindAsync(id);
            if (documento != null)
            {
                documento.Estado = "Pendiente Revisión"; // Cambia de escritorio
                await _context.SaveChangesAsync();
            }
            // Lo regresa al panel del creador
            return RedirectToAction("Creador", "Home");
        }

        // 5. GET: /Documento/EnviarAAutorizador/5
        // El Revisador le da el Visto Bueno y se lo pasa al Autorizador Final
        [HttpGet]
        public async Task<IActionResult> EnviarAAutorizador(int id)
        {
            var documento = await _context.Documento.FindAsync(id);
            if (documento != null)
            {
                documento.Estado = "Pendiente Autorización"; // Cambia al último escritorio
                await _context.SaveChangesAsync();
            }
            // Lo regresa al panel del revisador
            return RedirectToAction("Revisador", "Home");
        }



        [HttpPost]
        public async Task<IActionResult> ProcesarSubida(string codigo, string version, string nombre, IFormFile archivo)
        {
            try
            {
                if (archivo != null && archivo.Length > 0)
                {
                    string rutaCarpeta = Path.Combine(Directory.GetCurrentDirectory(), "storage", "actuales");
                    if (!Directory.Exists(rutaCarpeta)) Directory.CreateDirectory(rutaCarpeta);

                    string rutaCompleta = Path.Combine(rutaCarpeta, archivo.FileName);
                    using (var stream = new FileStream(rutaCompleta, FileMode.Create))
                    {
                        await archivo.CopyToAsync(stream);
                    }

                    var docExistente = await _context.Documento.FirstOrDefaultAsync(d => d.Nombre == nombre);
                    string nuevaVersionStr = "1.0";

                    // 👇 SOLUCIÓN AL ERROR: Se inicializa en 0
                    int documentoId = 0;

                    string extension = Path.GetExtension(archivo.FileName);

                    if (docExistente != null)
                    {
                        var ultimaVersion = await _context.DocumentoVersion
                            .Where(v => v.DocumentoID == docExistente.DocumentoID)
                            .OrderByDescending(v => v.FechaCreacion)
                            .FirstOrDefaultAsync();

                        if (ultimaVersion != null && !string.IsNullOrEmpty(ultimaVersion.CodigoVersion))
                        {
                            var partes = ultimaVersion.CodigoVersion.Split('.');
                            if (partes.Length == 2 && int.TryParse(partes[1], out int minor))
                                nuevaVersionStr = $"{partes[0]}.{minor + 1}";
                            else
                                nuevaVersionStr = ultimaVersion.CodigoVersion + ".1";
                        }

                        docExistente.Estado = "Pendiente Revisión";
                        docExistente.Extension = extension;
                        _context.Documento.Update(docExistente);
                        await _context.SaveChangesAsync();

                        documentoId = docExistente.DocumentoID;
                    }
                    else
                    {
                        var nuevoDoc = new Documento
                        {
                            Nombre = nombre,
                            Extension = extension,
                            Estado = "Pendiente Revisión",
                            EmpresaID = 1,
                            CreadoPor = 1,
                            FechaCreacion = DateTime.Now
                        };

                        _context.Documento.Add(nuevoDoc);
                        await _context.SaveChangesAsync();

                        documentoId = nuevoDoc.DocumentoID;
                    }

                    var nuevaVersionRegistro = new DocumentoVersion
                    {
                        DocumentoID = documentoId,
                        CodigoVersion = nuevaVersionStr,
                        RutaArchivo = $"/storage/actuales/{archivo.FileName}",
                        FechaCreacion = DateTime.Now
                    };

                    _context.DocumentoVersion.Add(nuevaVersionRegistro);
                    await _context.SaveChangesAsync();

                    TempData["Mensaje"] = $"¡Archivo físico subido como Versión {nuevaVersionStr} y enviado al Revisador!";
                }
            }
            catch (Exception ex)
            {
                TempData["Error"] = $"Error al subir el archivo: {ex.Message}";
            }

            return RedirectToAction("Creador", "Home");
        }

    }

    }