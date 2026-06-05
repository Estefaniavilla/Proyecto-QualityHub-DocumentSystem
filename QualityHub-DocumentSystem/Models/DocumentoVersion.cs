using System;
using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityHub_DocumentSystem.Models
{
    [Table("DocumentoVersion")]
    public class DocumentoVersion
    {
        [Key]
        [Column("VersionID")] // Nombre real en tu SQL
        public int VersionID { get; set; }

        [Column("DocumentoID")]
        public int DocumentoID { get; set; }

        [Column("CodigoVersion")]
        public string CodigoVersion { get; set; } = string.Empty;

        // =========================================================================
        // ¡EL PUENTE CONECTOR CON TU DISCO!
        // Mapea la columna física 'StoragePath' a tu propiedad 'RutaArchivo'
        // =========================================================================
        [Column("StoragePath")]
        public string RutaArchivo { get; set; } = string.Empty;

        [Column("FechaCreacion")]
        public DateTime? FechaCreacion { get; set; }

        // Relación con el Documento Padre
        [ForeignKey("DocumentoID")]
        public virtual Documento? Documento { get; set; }
    }
}