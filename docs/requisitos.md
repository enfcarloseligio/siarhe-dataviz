# Requisitos y compatibilidad

## Versiones mínimas

| | Mínimo requerido | Probado en |
|---|---|---|
| **WordPress** | **6.0** | 7.1.4 |
| **PHP** | **7.4** | 8.2.30 |
| **MySQL / MariaDB** | MySQL 5.6 (declarado en el `README`) | no verificado en versiones antiguas |

Dónde se declaran (los tres lugares deben coincidir):

1. Cabecera del plugin, `siarhe-dataviz/siarhe-dataviz.php`: `Requires at least: 6.0` y `Requires PHP: 7.4`. Con esto WordPress **impide activar** el plugin si no se cumplen.
2. Constantes `SIARHE_MIN_PHP` y `SIARHE_MIN_WP` en ese mismo archivo. Se comprueban al cargar el plugin: si el sitio se degrada después de activarlo, muestra un aviso en el panel y no carga el resto del código.
3. `README.md` y este documento.

### Por qué esos mínimos

- **PHP 7.4:** el código usa el operador `??`, `intdiv()` y arreglos cortos; no usa nada de PHP 8 (`str_contains`, `match`, `?->`, enums). Se verificó con una búsqueda en todo el código.
- **WordPress 6.0:** lo que realmente necesita el código es modesto (`wp_date` 5.3, `register_setting` con argumentos 4.7, la variable CSS `--wp-admin-theme-color` 5.7), de modo que el piso técnico es ~5.8. Se fijó **6.0** porque las versiones anteriores ya no reciben soporte y es el mismo mínimo que usa LiveQuiz Class. Nunca se ha probado por debajo de 7.x.
- Las pruebas del código JavaScript se hicieron con Node y jsdom (ver [pruebas.md](pruebas.md)); no hay PHP instalado en el equipo de desarrollo, así que el PHP se revisa con balance de llaves y se prueba en el sitio.

## Extensiones de PHP

| Extensión | ¿Obligatoria? | Para qué |
|---|---|---|
| `mbstring` | Sí | Normalizar la codificación de los CSV (`mb_check_encoding`, `mb_convert_encoding`) |
| `json` | Sí | Opciones, configuración y GeoJSON (viene incluida en PHP 8) |
| `zlib` | No | Comprimir las copias de seguridad (`.gz`). Sin ella se guardan sin comprimir; el Dashboard lo indica |
| `zip` (`ZipArchive`) | No | Generar el diccionario de variables en `.xlsx`. Sin ella se descarga en `.csv` |

## Servidor

- **Escritura** en `wp-content/uploads/siarhe-data/` (el plugin crea las subcarpetas). El Dashboard lo comprueba.
- **Límites de subida:** `upload_max_filesize` y `post_max_size` deben alcanzar el archivo más grande que se cargue (los CSV de marcadores nacionales pesan 10–14 MB; el GeoJSON de municipios, ~37 MB). El Dashboard muestra los valores vigentes.
- **Compresión de los datos:** muchos servidores entregan `.csv` y `.geojson` como `application/octet-stream` y **no los comprimen**. El plugin lo resuelve con copias de lectura (`.json` / `.txt`); ver [rendimiento-mapa.md](rendimiento-mapa.md). Detrás de Cloudflare conviene vaciar su caché después de actualizar el plugin.
- El plugin escribe un `.htaccess` en `siarhe-data/` con los tipos MIME correctos. En OpenLiteSpeed solo tiene efecto si el sitio permite `.htaccess` con la opción *FileInfo*; no es imprescindible gracias a las copias de lectura.

## Navegadores

Navegadores actuales (Chrome, Edge, Brave, Firefox y Safari recientes). El mapa usa Canvas 2D, Web Workers, `ResizeObserver` y `matchMedia`. Si el navegador no soporta workers, el mapa **funciona igual** con el procesamiento en el hilo principal (más lento con archivos grandes).

Verificado en: Brave en PC (Windows), Brave en Android (vertical y horizontal, también en pantalla completa).

## Servicios externos

- **D3.js v7** desde `d3js.org`, con **respaldo local** en `public/js/libs/` si el CDN falla.
- **Select2** (solo en el panel) desde CDN, con respaldo local en `admin/libs/`.
- No se envían datos a terceros.
