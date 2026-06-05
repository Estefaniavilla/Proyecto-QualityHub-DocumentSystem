# ── Etapa 1: Compilar ──────────────────────────────────────
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

# Copia el archivo de proyecto y restaura dependencias
COPY *.csproj ./
RUN dotnet restore

# Copia todo el código fuente y compila
COPY . ./
RUN dotnet publish -c Release -o /app/publish

# ── Etapa 2: Imagen final liviana ──────────────────────────
FROM mcr.microsoft.com/dotnet/aspnet:10.0 AS final
WORKDIR /app

# Copiamos lo compilado de la etapa anterior
COPY --from=build /app/publish .

# El puerto que usa tu app en C# internamente
EXPOSE 8080

# Variable de entorno para que escuche en el puerto correcto
ENV ASPNETCORE_URLS=http://+:8080
ENV ASPNETCORE_ENVIRONMENT=Production

ENTRYPOINT ["dotnet", "QualityHub-DocumentSystem.dll"]