using System;
using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityHub_DocumentSystem.Models
{
    [Table("DocumentoLogs")]
    public class DocumentoLog
    {
        [Key]
        public int LogID { get; set; }
        public int? VersionAnteriorID { get; set; }
        public int VersionActualID { get; set; }
        public int UserID { get; set; }
        public string Accion { get; set; } = string.Empty;
        public string? Comentario { get; set; }
        public DateTime FechaMovimiento { get; set; }

        [ForeignKey("UserID")]
        public virtual User? Usuario { get; set; }
    }
}