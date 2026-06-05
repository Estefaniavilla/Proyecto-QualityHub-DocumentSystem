using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityHub_DocumentSystem.Data;
using QualityHub_DocumentSystem.Models;
using System;
using System.Linq;
using System.Threading.Tasks;

namespace QualityHub_DocumentSystem.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    public class DocumentosApiController : ControllerBase
    {
        private readonly AppDbContext _context;

        public DocumentosApiController(AppDbContext context)
        {
            _context = context;
        }

        // POST: api/DocumentosApi/ReportarFalla
        [HttpPost("ReportarFalla")]
        public async Task<IActionResult> ReportarFalla([FromBody] ReporteFallaDto reporte)
        {
            if (reporte == null || reporte.DocumentoId <= 0)
            {
                return BadRequest(new { mensaje = "Datos del reporte inválidos o el ID del documento es 0." });
            }

            // 1. Buscar el documento en la base de datos (Incluyendo sus versiones)
            var documento = await _context.Documento
                                          .Include(d => d.DocumentoVersion)
                                          .FirstOrDefaultAsync(d => d.DocumentoID == reporte.DocumentoId);

            if (documento == null)
            {
                return NotFound(new { mensaje = $"El documento con ID {reporte.DocumentoId} no existe en el sistema." });
            }

            // 2. Cambiamos el estado del documento a "En Revisión"
            documento.Estado = "En Revisión";

            // 3. Obtener el ID de la versión actual
            // Ordenamos por VersionID de mayor a menor y tomamos el primero (el más reciente)
            int versionActualId = 0;
            if (documento.DocumentoVersion != null && documento.DocumentoVersion.Any())
            {
                versionActualId = documento.DocumentoVersion.OrderByDescending(v => v.VersionID).First().VersionID;
            }

            // 4. Crear el registro exacto para DocumentoLogs
            var nuevoLog = new DocumentoLog
            {
                VersionAnteriorID = null,
                VersionActualID = versionActualId,      // Aquí usamos el ID de la versión que extrajimos
                UserID = documento.CreadoPor,           // Se registra a nombre del creador original
                Accion = "Alerta de falla desde Planta (PHP)",
                Comentario = reporte.Comentarios,
                FechaMovimiento = DateTime.Now
            };

            _context.DocumentoLogs.Add(nuevoLog);

            // 5. Guardamos todo en SQL Server
            try
            {
                await _context.SaveChangesAsync();
                return Ok(new
                {
                    exito = true,
                    mensaje = "Falla recibida con éxito. El estado del documento ha cambiado a 'En Revisión' y se guardó en el Log."
                });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new
                {
                    exito = false,
                    mensaje = "Error al actualizar la base de datos.",
                    detalle = ex.Message
                });
            }
        }
    }
}