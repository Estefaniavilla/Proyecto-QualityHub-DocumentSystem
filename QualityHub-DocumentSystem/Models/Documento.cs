using System;
using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;
using System.Collections.Generic;

namespace QualityHub_DocumentSystem.Models
{
    [Table("Documento")]
    public class Documento
    {
        [Key]
        [Column("DocumentoID")]
        public int DocumentoID { get; set; }

        [Column("BaseName")]
        public string Nombre { get; set; } = string.Empty;

        [Column("Extension")]
        public string Extension { get; set; } = string.Empty;

        [Column("Status")]
        public string Estado { get; set; } = string.Empty;

        [Column("EmpresaID")]
        public int EmpresaID { get; set; }

        [Column("CreatedBy_UserID")]
        public int CreadoPor { get; set; }

        // =========================================================================
        // PROPIEDAD DE NAVEGACIÓN (Sincronizada con tu nombre de colección)
        // =========================================================================
        public virtual ICollection<DocumentoVersion> DocumentoVersion { get; set; } = new List<DocumentoVersion>();

        // =========================================================================
        // PROPIEDADES EN MODO "COMPATIBILIDAD" (Para tus vistas y lógicas existentes)
        // =========================================================================

        [NotMapped]
        public string Titulo => Nombre;

        [NotMapped]
        public int UsuarioID => CreadoPor;

        [NotMapped]
        public string Codigo { get; set; } = string.Empty;

        [NotMapped]
        public string Ruta => $"{Nombre}{Extension}";

        // Le ponemos [NotMapped] para que no tire error en el "ToListAsync()" de SQL
        [NotMapped]
        public DateTime FechaCreacion { get; set; } = DateTime.Now;
    }
}