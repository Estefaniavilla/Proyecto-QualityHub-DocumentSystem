using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityHub_DocumentSystem.Models
{
    [Table("User")]
    public class User
    {
        [Key]
        public int UserID { get; set; }

        [Required]
        public string UserName { get; set; } = string.Empty;

        [Required]
        public string PasswordHash { get; set; } = string.Empty;

        public int RoleID { get; set; }

        public int EmpresaID { get; set; }

        [NotMapped]
        public string? NombreRol { get; set; }

        [NotMapped]
        public string? NombreEmpresa { get; set; }
    }
}