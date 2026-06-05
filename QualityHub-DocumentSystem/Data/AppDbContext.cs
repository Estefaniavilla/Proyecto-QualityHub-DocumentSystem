using Microsoft.EntityFrameworkCore;
using QualityHub_DocumentSystem.Models; // Asegúrate de que este namespace apunte a tus modelos

namespace QualityHub_DocumentSystem.Data
{
    public class AppDbContext : DbContext
    {
        public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }

        // DECLARA CADA TABLA SOLO UNA VEZ
        public DbSet<Documento> Documento { get; set; }
        public DbSet<DocumentoVersion> DocumentoVersion { get; set; }
        public DbSet<DocumentoLog> DocumentoLogs { get; set; } // Nombre de la clase
        public DbSet<User> User { get; set; }
        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            // Mapeos a las tablas físicas
            modelBuilder.Entity<Documento>().ToTable("Documento");
            modelBuilder.Entity<DocumentoVersion>().ToTable("DocumentoVersion");
            modelBuilder.Entity<DocumentoLog>().ToTable("DocumentoLogs");
            modelBuilder.Entity<User>().ToTable("User");
        }
    }
}
