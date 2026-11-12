# siarhe-dataviz/build.ps1
# ============================================================
#  SIARHE Data Visualization Engine - Empaquetador (PowerShell)
#
#  Genera un .zip limpio del plugin listo para instalar en
#  WordPress. Lee la version de la constante SIARHE_VERSION,
#  copia la carpeta del plugin a un directorio temporal, quita
#  lo que no debe viajar y escribe builds\siarhe-dataviz-<version>.zip
#
#  Paquete : SIARHE_DataViz
#  Autor   : Juan Carlos De La Cruz Eligio | @enfcarloseligio
#  Licencia: GPL-2.0-or-later
#
#  Uso (desde la raiz del repositorio):
#    .\build.ps1
#
#  Si da error de politicas de ejecucion, ejecutar una vez:
#    Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
#
#  La carpeta builds\ y los .zip estan en .gitignore: el script
#  se versiona, su resultado no.
# ============================================================

# ------------------------------------------------------------
# CONFIGURACION
# Nombres, rutas y listas de exclusion del paquete.
# ------------------------------------------------------------
$NombrePlugin          = "siarhe-dataviz"
$DirPlugin             = "siarhe-dataviz"
$DirBuilds             = "builds"
$ArchivoPrincipal      = Join-Path $DirPlugin "siarhe-dataviz.php"
$UmbralAvisoDuplicados = 3

# Archivos que no deben viajar en el zip (documentacion, registros, temporales).
# No se excluyen *.json ni *.csv de forma global: las librerias locales y los
# archivos de idioma pueden usarlos. Los datos cargados por el usuario viven en
# uploads\ y se descartan aparte.
$PatronesExcluir = @(
    "*.md", "*.log", "*.sh", "*.ps1", "*.bat",
    "*.tmp", "*.bak", ".DS_Store", "Thumbs.db", "desktop.ini"
)

# Carpetas que no deben viajar en el zip.
$CarpetasExcluir = @(
    ".git", "node_modules", "vendor", ".vscode", ".idea"
)

# ------------------------------------------------------------
# FUNCIONES AUXILIARES
# Salida por consola con color.
# ------------------------------------------------------------
function Escribir-Msg {
    param([string]$Mensaje, [string]$Color = "White")
    Write-Host $Mensaje -ForegroundColor $Color
}

function Escribir-Separador {
    Escribir-Msg ("-" * 60) "DarkGray"
}

# ------------------------------------------------------------
# INICIO
# ------------------------------------------------------------
Write-Host ""
Escribir-Msg "============================================================" "Yellow"
Escribir-Msg "  SIARHE DataViz - Generador de paquete para WordPress" "Yellow"
Escribir-Msg "============================================================" "Yellow"
Write-Host ""

# ------------------------------------------------------------
# 1. VERIFICAR LA RAIZ DEL REPOSITORIO
# ------------------------------------------------------------
if (-not (Test-Path $ArchivoPrincipal)) {
    Escribir-Msg "ERROR: No se encontro $ArchivoPrincipal" "Red"
    Escribir-Msg "       Ejecuta este script desde la raiz del repositorio." "DarkGray"
    Escribir-Msg "       Ruta actual: $(Get-Location)" "DarkGray"
    exit 1
}

# ------------------------------------------------------------
# 2. LEER LA VERSION DEL PLUGIN
# La constante SIARHE_VERSION es la fuente; la cabecera "Version:"
# debe coincidir y se compara para avisar de desajustes.
# ------------------------------------------------------------
Escribir-Msg "[1/6] Leyendo version del plugin..." "Cyan"

$contenido = Get-Content $ArchivoPrincipal -Raw
$coincidencia = [regex]::Match($contenido, "define\(\s*'SIARHE_VERSION'\s*,\s*'([^']+)'\s*\)")

if (-not $coincidencia.Success) {
    Escribir-Msg "ERROR: No se pudo leer SIARHE_VERSION desde $ArchivoPrincipal" "Red"
    Escribir-Msg "       Se espera una linea como: define( 'SIARHE_VERSION', 'X.Y.Z.W' );" "DarkGray"
    exit 1
}

$Version = $coincidencia.Groups[1].Value
Escribir-Msg "      Version detectada: $Version" "Green"

$cabecera = [regex]::Match($contenido, "(?m)^\s*\*\s*Version:\s*(\S+)")
if ($cabecera.Success -and $cabecera.Groups[1].Value -ne $Version) {
    Escribir-Msg "      AVISO: la cabecera 'Version:' ($($cabecera.Groups[1].Value)) no coincide con SIARHE_VERSION ($Version)." "Yellow"
}

# ------------------------------------------------------------
# 3. VERIFICAR EL ESTADO DE GIT
# Solo informa; el zip se genera de todos modos.
# ------------------------------------------------------------
Escribir-Msg "[2/6] Verificando estado de Git..." "Cyan"

if (Test-Path ".git") {
    try {
        $cambios = git status --porcelain 2>$null
        $numCambios = ($cambios | Measure-Object).Count
        if ($numCambios -gt 0) {
            Escribir-Msg "      AVISO: hay $numCambios archivo(s) con cambios sin confirmar. El zip se generara igual." "Yellow"
        } else {
            Escribir-Msg "      Repositorio limpio." "Green"
        }
    } catch {
        Escribir-Msg "      No se pudo consultar Git. Se omite la verificacion." "DarkGray"
    }
} else {
    Escribir-Msg "      No es un repositorio Git. Se omite la verificacion." "DarkGray"
}

# ------------------------------------------------------------
# 4. CALCULAR EL NOMBRE DEL ZIP
# Si ya existe uno con la misma version se agrega un sufijo (1), (2)...
# ------------------------------------------------------------
Escribir-Msg "[3/6] Calculando nombre del archivo zip..." "Cyan"

$NombreBase = "$NombrePlugin-$Version"
$NombreZip  = "$NombreBase.zip"
$Contador   = 0

if (-not (Test-Path $DirBuilds)) {
    New-Item -ItemType Directory -Path $DirBuilds -Force | Out-Null
}

while (Test-Path (Join-Path $DirBuilds $NombreZip)) {
    $Contador++
    $NombreZip = "$NombreBase($Contador).zip"
}

if ($Contador -eq 0) {
    Escribir-Msg "      Nombre final: $NombreZip" "Green"
} else {
    Escribir-Msg "      Nombre final: $NombreZip (build repetido #$Contador)" "Yellow"
}

if ($Contador -ge $UmbralAvisoDuplicados) {
    Escribir-Msg "      ATENCION: $Contador builds con la version $Version." "Red"
    Escribir-Msg "      Revisa si falta subir la version (cabecera y SIARHE_VERSION) y la entrada de CHANGELOG.md." "Yellow"
}

# ------------------------------------------------------------
# 5. COPIAR EL PLUGIN A UNA CARPETA TEMPORAL Y LIMPIARLO
# ------------------------------------------------------------
Escribir-Msg "[4/6] Preparando archivos del plugin..." "Cyan"

$TempDir = Join-Path $env:TEMP "siarhe-build-$(Get-Random)"
New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

# Se copia la carpeta completa (sin \* al final) para conservar la jerarquia:
# $TempDir\siarhe-dataviz\...
Copy-Item -Path $DirPlugin -Destination $TempDir -Recurse -Force
$TempPlugin = Join-Path $TempDir $NombrePlugin

# Verificaciones posteriores a la copia.
$Esenciales = @(
    @{ Ruta = (Join-Path $TempPlugin "siarhe-dataviz.php"); Tipo = "Leaf" },
    @{ Ruta = (Join-Path $TempPlugin "includes");           Tipo = "Container" },
    @{ Ruta = (Join-Path $TempPlugin "public");             Tipo = "Container" },
    @{ Ruta = (Join-Path $TempPlugin "admin");              Tipo = "Container" }
)
foreach ($item in $Esenciales) {
    if (-not (Test-Path $item.Ruta -PathType $item.Tipo)) {
        Escribir-Msg "ERROR: la copia fallo, falta $($item.Ruta)" "Red"
        Remove-Item -Path $TempDir -Recurse -Force -ErrorAction SilentlyContinue
        exit 1
    }
}
Escribir-Msg "      Copia verificada." "Green"

Escribir-Msg "      Excluyendo archivos innecesarios..." "DarkGray"

foreach ($patron in $PatronesExcluir) {
    Get-ChildItem -Path $TempPlugin -Recurse -Filter $patron -Force -ErrorAction SilentlyContinue |
        Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
}

foreach ($carpeta in $CarpetasExcluir) {
    Get-ChildItem -Path $TempPlugin -Recurse -Directory -Filter $carpeta -Force -ErrorAction SilentlyContinue |
        Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
}

# Los datos cargados por el usuario no viajan nunca: se vacia uploads\ y se
# conserva solo el .gitkeep para que la carpeta exista.
$DirUploads = Join-Path $TempPlugin "uploads"
if (Test-Path $DirUploads) {
    Get-ChildItem -Path $DirUploads -Recurse -File -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne ".gitkeep" } |
        Remove-Item -Force -ErrorAction SilentlyContinue
}

# ------------------------------------------------------------
# 6. CREAR EL ZIP
# Se usa ZipArchive de bajo nivel para garantizar rutas con "/":
# CreateFromDirectory en .NET Framework 4.x puede escribir "\" y eso
# da problemas en servidores Linux (WordPress).
# ------------------------------------------------------------
Escribir-Msg "[5/6] Comprimiendo plugin..." "Cyan"

$RutaZipAbsoluta = Join-Path (Resolve-Path $DirBuilds).Path $NombreZip

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipStream = [System.IO.File]::Open($RutaZipAbsoluta, [System.IO.FileMode]::Create)
$archive   = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

# La ruta relativa se calcula por el nombre unico de la carpeta temporal y no
# por longitud: $env:TEMP puede venir en formato corto (8.3, ej. JUAN~1.DEL)
# mientras que FullName devuelve la ruta larga.
$marcadorTemp = "\" + (Split-Path $TempDir -Leaf) + "\"

Get-ChildItem -Path $TempDir -Recurse -File -Force | ForEach-Object {
    $rutaCompleta   = $_.FullName
    $inicioRelativo = $rutaCompleta.IndexOf($marcadorTemp, [System.StringComparison]::OrdinalIgnoreCase) + $marcadorTemp.Length
    $rutaZip        = $rutaCompleta.Substring($inicioRelativo) -replace '\\', '/'

    $entrada        = $archive.CreateEntry($rutaZip, [System.IO.Compression.CompressionLevel]::Optimal)
    $streamEntrada  = $entrada.Open()
    $streamArchivo  = [System.IO.File]::OpenRead($rutaCompleta)
    $streamArchivo.CopyTo($streamEntrada)
    $streamArchivo.Close()
    $streamEntrada.Close()
}

$archive.Dispose()
$zipStream.Close()

Remove-Item -Path $TempDir -Recurse -Force -ErrorAction SilentlyContinue

# ------------------------------------------------------------
# 7. RESUMEN FINAL
# Cuenta los archivos y comprueba que las rutas internas usen "/".
# ------------------------------------------------------------
Escribir-Msg "[6/6] Paquete generado correctamente." "Green"
Write-Host ""

$zip = [System.IO.Compression.ZipFile]::OpenRead($RutaZipAbsoluta)
$numArchivos  = $zip.Entries.Count
$estructuraOk = -not ($zip.Entries | Where-Object { $_.FullName -match '\\' })
$zip.Dispose()

Escribir-Separador
Escribir-Msg "  RESUMEN DEL BUILD" "Yellow"
Escribir-Separador
Escribir-Msg "  Version    : $Version" "Green"
Escribir-Msg "  Archivo    : $DirBuilds\$NombreZip" "Green"
Escribir-Msg ("  Tamano     : {0:N2} KB" -f ((Get-Item $RutaZipAbsoluta).Length / 1KB)) "Green"
Escribir-Msg "  Archivos   : $numArchivos" "Green"

if ($estructuraOk) {
    Escribir-Msg "  Estructura : OK (rutas con /)" "Green"
} else {
    Escribir-Msg "  Estructura : ADVERTENCIA (se detectaron \ en las rutas)" "Red"
}

if ($Contador -gt 0) {
    Escribir-Msg "  Repetidos  : $Contador" "Yellow"
}
Escribir-Separador
Write-Host ""
Escribir-Msg "  Para instalar: Plugins > Anadir nuevo > Subir plugin > $NombreZip" "DarkGray"
Write-Host ""
