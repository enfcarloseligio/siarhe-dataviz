# Historial de versiones

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Los números de versión siguen los hitos descritos en [docs/versiones-y-publicacion.md](docs/versiones-y-publicacion.md).

## [0.1.4.0] - 2026-11-12

### Métricas
- **62 variables nuevas de RHEMX** (124 columnas con sus tasas): los estudios (auxiliar, técnico, postécnico, licenciatura, **LEO**, especialidad, maestría, doctorado, posgrado, perfiles) y las áreas de especialización (`esp_*`, cada una también en postécnico y posgrado). Vienen de fábrica (`includes/metricas-rhemx.php`, generado del diccionario de RHEMX) como métricas **opcionales**: solo aparecen en el selector de indicadores si la base cargada trae su columna, y la carga no avisa si faltan.
- Las instalaciones que ya tenían métricas guardadas las reciben sin perder su configuración (`siarhe_get_metricas_config()` agrega las nativas que falten). Al cambiar de año o de unidad, el selector se ajusta a las columnas de esa base.

## [0.1.3.0] - 2026-11-12

### Mapa y tabla
- **Selector de año y de unidad:** el mapa y la tabla ofrecen el año de los datos y la unidad (plazas / recursos humanos estimados) entre las bases cargadas de la entidad (`data-bases`). Al cambiar se descarga esa base y se redibujan el mapa, los totales, la tabla y el año de los títulos y de las descargas. Aparecen solo si hay más de una opción. El año y la unidad viajan en la URL (`a`, `u`).

### Carga de datos
- El selector de año llega hasta el año en curso (antes ofrecía el siguiente) y el servidor rechaza años posteriores.

## [0.1.2.1] - 2026-11-12

### Mapa
- **El mapa lee el JSON optimizado** de la base estática (si existe; si no, el CSV): `app.data.jsonToRows` lo convierte en las mismas filas que el CSV y calcula las tasas declaradas en `tasas`. Comprobado contra los CSV de RHEMX (Oaxaca y nacional, sin diferencias).

## [0.1.2.0] - 2026-11-12

### Bases estáticas
- **Año y unidad:** cada base estática es ahora una combinación entidad + año + unidad (plazas o recursos humanos estimados). El año se elige en un selector desde 2006 y la tabla de bases cargadas se puede filtrar por año y unidad. Las bases ya cargadas pasan a ser del año 2026 y en plazas (migración del esquema a la versión 2). Mientras el mapa no tenga selector de año, muestra la más reciente en plazas.
- **CSV o JSON:** se puede subir el CSV o el JSON optimizado de RHEMX (`json/`, `json_personas/`); el plugin crea el otro formato, así que siempre existen los dos y el mapa leerá el JSON, que pesa casi la mitad. Las bases que ya estaban cargadas reciben su JSON al actualizar. Formato en [docs/formatos-de-datos.md](docs/formatos-de-datos.md).

## [0.1.1.1] - 2026-11-12

### Marcadores
- **CSV con encabezados del catálogo CLUES:** además de `CVE_ENT`, `CVE_MUN` y `CVE_LOC`, los marcadores aceptan `CLAVE DE LA ENTIDAD`, `CLAVE DEL MUNICIPIO` y `CLAVE DE LA LOCALIDAD`, y para la institución, el tipo de establecimiento y la tipología `NOMBRE DE LA INSTITUCION`, `NOMBRE TIPO ESTABLECIMIENTO` y `NOMBRE DE TIPOLOGIA`. Así se puede subir tal cual el `clues_<año>.csv` que genera RHEMX. La validación al subir, el lector en segundo plano, el respaldo en el hilo principal y los tooltips usan los mismos nombres.

## [0.1.1.0] - 2026-10-31

### Mapa
- **Marcadores encimados:** al hacer clic o tocar un marcador que tiene otros debajo (o en la misma coordenada) se abre un panel con "n de N", flechas, una lista numerada tocable y la tarjeta del marcador actual; otro toque en el mismo lugar pasa al siguiente. Se calcula solo al tocar, no agrupa ni fusiona marcadores y funciona en canvas y en SVG. Con la medición activa, una pila ofrece *Agregar a la medición*.

### Documentación
- Nueva guía [docs/metricas-y-marcadores.md](docs/metricas-y-marcadores.md): campo por campo para agregar métricas y marcadores y qué hace cada cosa en el panel.

## [0.1.0.1] - 2026-10-31

### Proyecto
- **Cabecera del plugin completa:** `Requires at least: 6.0`, `Requires PHP: 7.4`, URI del plugin y del autor, licencia GPL-2.0-or-later, dominio y carpeta de traducciones.
- **Comprobación de requisitos** al cargar el plugin (aviso en el panel si el sitio no cumple PHP o WordPress).
- **Licencia:** `LICENSE` con el texto de la GPL v2 (estaba vacío).
- **Documentación:** carpeta `docs/` (requisitos, arquitectura, administración, formatos de datos, rendimiento del mapa, decisiones, versiones y publicación, pruebas), `CHANGELOG.md` y `README.md` actualizado.
- Texto de la plantilla de marcadores corregido: las filas fuera de México no se descartan, solo se avisa si son muchas.

## [0.1.0.0] - 2026-10-31

Cierre de la parte estática del frontend.

### Rendimiento
- Marcadores de **posición, burbujas y espinas en un solo canvas** cuando hay más de 3,000 activos (800 en pantallas táctiles); tooltip, resaltado y clic por la forma real de cada tipo; orden de apilado conservado.
- **Lectura de los CSV de marcadores en un Web Worker** (con respaldo si no hay workers): descarga, filtrado y agrupado fuera del hilo principal, con menos columnas por punto.
- **Vista por municipios** preparada por un worker en **tres niveles de detalle** (1.78 millones de vértices → 112 mil a zoom nacional).
- Zoom de marcadores SVG solo sobre los **visibles** (según el tamaño real del contenedor), eventos delegados y dibujo rápido con muchos puntos.
- **Copias de lectura** `.json` / `.txt` de los archivos de 100 KB o más para que el servidor los comprima (GeoJSON nacional 7 MB → 733 KB); botón en el Dashboard, creación al subir y opción para desactivarlas. Chequeo de compresión en el Dashboard.

### Mapa y visualización
- **Tamaños, bordes y zoom por dispositivo:** símbolos en píxeles de pantalla (sin volver a crecer), borde de 0.4–1 px, espinas y burbujas que crecen con el zoom, área de toque mínima en las espinas, zoom máximo de ~110× (PC) y ~300× (móvil) en el mapa nacional.
- Botón **Personalizar** colores del mapa (solo en el navegador del visitante); **botones con un solo estilo** configurable desde *Personalizar › Mapa y Colores*.
- **Tabla plegable** cuando se muestran mapa y tabla juntos; tooltips en formato "Nombre Propio" que **no se salen del mapa**; los marcadores sin `CVE_ENT` no se dibujan.
- Las leyendas y la medición pasan a una capa SVG superior (sobre el canvas).

### Datos y administración
- **Plantillas CSV y diccionario de variables** descargables (bases estáticas y marcadores), con un catálogo único de columnas. `tasa_total` y `enfermeras_total` son obligatorias; los marcadores exigen `CVE_ENT`, `ENTIDAD`, `CVE_MUN`, `MUNICIPIO`, `CVE_LOC` y `LOCALIDAD`.
- Descripciones de las métricas nativas corregidas.

## [0.0.15] – [0.0.12] - 2026-10-31

- **0.0.15.x** Copias de lectura comprimibles, Web Worker de CSV y zoom de marcadores visibles (primeras versiones).
- **0.0.14.0** Botón Personalizar, botones configurables, tabla plegable, tooltips con formato y sin salirse del mapa; `.htaccess` con tipos MIME en `siarhe-data/`.
- **0.0.13.0 – 0.0.13.5** Historial de versiones con filtro por tipo, buscador y paginación; motor de tablas compartido; columnas ordenables en todas las tablas; borde superior y espaciado unificados en las tarjetas; título y ayuda en todas las tablas; limpieza de estilos en línea; plantillas y diccionario de variables; validación de marcadores. La pestaña *Servicio Social* pasa a **Carreras**.
- **0.0.12.0** Copias de seguridad configurables (0–5, compresión `.gz`), historial con restaurar y eliminar, espacio usado y salud en el Dashboard.

## [0.0.11] – [0.0.9] - 2026-10-31

- **0.0.11.0** Carga de archivos con arrastrar y soltar, vista previa y barra de progreso.
- **0.0.10.0** Plantilla única de carga para Geolocalización, Localidades y Bases Estáticas.
- **0.0.9.0** Rediseño del panel: sistema de diseño, Dashboard real, ayudas `?`, modales y avisos, validación previa de archivos, botón *Subir*.

## [0.0.8] – [0.0.6] - 2026-10-30

- **0.0.8.1** Margen único a los bordes para leyendas, controles y paneles; el botón de medir solo aparece con marcadores de posición activos.
- **0.0.8.0** Medición de distancias entre marcadores de posición (ruta encadenada y desde un centro, descarga CSV).
- **0.0.7.0** *Personalizar* (Tema Admin y Mapa & Colores) y marcadores *Spike* con degradado de color.
- **0.0.6.0** D3 y Select2 con CDN primero y respaldo local.

## [0.0.5.x] - 2026-10-29 / 30

- **0.0.5.51** Un único origen para el catálogo de entidades, valores por defecto y variables CSS.
- **0.0.5.50** Accesibilidad, validación de cargas, enlace compartible, leyendas legibles y botón *Restablecer*.
- **0.0.5.49** Marcadores y segunda vista: oleada continua por región, carga por trozos, capa de detalle en caché.
- **0.0.5.48** CSS sin `!important`; estilos de JavaScript pasados a la hoja con especificidad.
- **0.0.5.47** Eficiencia: D3 solo con shortcode, versionado por `filemtime`, `db_version`, evaluador de rangos precalculado.
- **0.0.5.46** Escape de salida (XSS en JavaScript desde CSV/GeoJSON, `esc_*` en el panel y en el CSS dinámico).
- **0.0.5.45** Seguridad del panel: capacidades, nonces y validación de subidas.

## [0.0.5.x] - marzo-abril 2026 y antes

Desarrollo inicial (16-feb-2026): estructura del plugin, carga de GeoJSON y CSV, mapas coropléticos con D3, configuración de rangos, ajustes del panel y del mapa en móvil, pantalla completa y *cache busting* (0.0.5.2 – 0.0.5.42).
