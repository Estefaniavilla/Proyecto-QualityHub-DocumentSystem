using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.FileProviders;
using QualityHub_DocumentSystem.Data;
using System;
using System.IO;

var builder = WebApplication.CreateBuilder(args);

// ==========================================
// 1. REGISTRO DE SERVICIOS
// ==========================================
builder.Services.AddControllersWithViews();

builder.Services.AddCors(options =>
{
    options.AddPolicy("PermitirTodo", policy =>
    {
        policy.AllowAnyOrigin().AllowAnyMethod().AllowAnyHeader();
    });
});

builder.Services.AddDbContext<AppDbContext>(options =>
    options.UseSqlServer(
        builder.Configuration.GetConnectionString("DefaultConnection"),
        sqlServerOptionsAction: sqlOptions =>
        {
            sqlOptions.EnableRetryOnFailure(maxRetryCount: 5, maxRetryDelay: TimeSpan.FromSeconds(30), errorNumbersToAdd: null);
        }
    ));

builder.Services.AddSession();
builder.Services.AddHttpContextAccessor();

var app = builder.Build();

// ==========================================
// 2. CONFIGURACIÓN DEL PIPELINE HTTP
// ==========================================
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles(); // Archivos base (wwwroot)

// =========================================================================
// CONFIGURACIÓN CORRECTA DEL VISOR DE PDFs (Funciona en Windows y Docker)
// =========================================================================
var storagePath = Path.Combine(Directory.GetCurrentDirectory(), "storage", "actuales");
if (!Directory.Exists(storagePath))
{
    Directory.CreateDirectory(storagePath);
}

app.UseStaticFiles(new StaticFileOptions
{
    FileProvider = new PhysicalFileProvider(storagePath),
    RequestPath = "/storage/actuales"
});
Console.WriteLine($"[ÉXITO] Visor montado en: {storagePath}");

app.UseRouting();
app.UseCors("PermitirTodo");
app.UseSession();
app.UseAuthorization();

// ==========================================
// 3. MAPEO DE RUTAS
// ==========================================
app.MapStaticAssets();
app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Home}/{action=Index}/{id?}")
    .WithStaticAssets();

app.Run();