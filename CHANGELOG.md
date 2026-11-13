# Historial de versiones

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Los números de versión siguen los hitos descritos en [docs/versiones-y-publicacion.md](docs/versiones-y-publicacion.md).

## [0.1.9.0] - 2026-11-13

### Panel
- **Selectores con búsqueda como regla general de Carga de Datos:** todos los selectores de los formularios (marco, año, tema, clínica, unidad, tipo de marcador, periodo) y de las barras de las tablas (registros, marco, año, tema, unidad) se pueden desplegar o escribir, igual que el de entidad.
- **Marcadores:** *Incorporar Nueva Variable* pasa al final de la pantalla, como en Bases Estáticas, y pregunta **¿Tiene serie completa?** (Sí: «Todas» y un archivo por año, como las clínicas; No: solo por año, como los establecimientos).
- **Plantillas mínimas:** en Bases Estáticas, *Mínima nacional* y *Mínima municipal* (clave, nombre y columnas de valor) para los temas propios y Clínicas; en Marcadores, *Plantilla mínima* (latitud, longitud, clave de la entidad y nombre). Se suben como CSV y el plugin crea el JSON que lee el mapa.
- **Métricas:** la tabla tiene un filtro por tema.
- **Dashboard:** se retira el botón *Generar copias de lectura ahora*; con las cargas en JSON ya no hace falta (las copias de los CSV se siguen creando solas al subirlos).

### Mapa y tabla
- **Esqueleto de carga:** mientras llegan los datos, el mapa, los controles y la tabla muestran bloques grises con brillo en lugar del recuadro giratorio (sin animación con `prefers-reduced-motion`).
- **Ancho de la página:** el contenido va en un **contenedor centrado de 1140 px** como máximo. En *Personalizar › Mapa y Colores* se puede cambiar a **canvas**: ancho completo de la ventana con un margen lateral de 2.5 % a 5 %.
- **Referencias del pie en el orden acordado:** datos de enfermería, de establecimientos de salud y espaciales; las de las clínicas de catéteres y de heridas aparecen al activar sus marcadores.
- **El extranjero ya no cuenta en el total nacional:** la fila «Extranjero» (clave 34) no entra en la suma ni en la tabla, como en RHEMX; la nota del total lo dice.
- **Aviso legal:** ahora indica que la información se obtuvo de portales de datos abiertos o por solicitudes de transparencia, que el análisis sigue un proceso de depuración por el que los resultados pueden diferir de las cifras oficiales y que no representa una postura oficial.

## [0.1.8.0] - 2026-11-13

### Carga de datos
- **Filtro de periodo en las cuatro tablas:** *Geolocalización* y *Localidades (Geo)* filtran por marco, y *Bases Estáticas* y *Marcadores* por año. El selector solo ofrece los periodos con al menos un archivo cargado, abre en el más reciente (sin archivos, en el año o el marco vigente) y conserva la vista **Todos**. En cada periodo se listan todas las entidades, con archivo o sin él, para ver lo que falta.
- **Orden de las entidades:** en los selectores y las tablas van primero **Extranjero**, después **República Mexicana** y después las entidades por su clave (01 a 32).
- **Se retiran las entidades «Clínicas de Heridas» y «Clínicas de Catéteres»** (claves 98 y 99) de los mapas: sus datos ahora son un tema de las bases estáticas. Un archivo cargado en ellas aparece al final de su tabla como «entidad retirada» para poder eliminarlo.
- **Validación de la entidad en las bases estáticas:** al subir un CSV o un JSON se comprueba, por su `CVE_ENT`, que sea de la entidad elegida. Se rechaza la base de una entidad en el lugar de otra, una estatal en República Mexicana y la nacional en una entidad, con un mensaje que dice de cuál es el archivo.
- **Botón «Validar archivos cargados»:** en el Dashboard (todo) y en cada pestaña (lo suyo). Revisa lo que ya está en el servidor: que el archivo exista y que sea de la entidad, la capa, el marco, el año y la unidad donde está cargado. Si algo no corresponde, lista el apartado, dónde está cargado, el archivo y el problema, para reemplazarlo.

### Bases estáticas
- **Temas:** cada base es ahora **entidad + año + tema + unidad**. *Enfermería* (plazas de enfermería y recursos humanos de enfermería) y *Clínicas*, que abre un segundo selector con *Clínicas de catéteres* (por defecto) y *Clínicas de heridas*, en tasa y frecuencia. Las bases ya cargadas quedaron en Enfermería.
- **Temas propios:** el administrador puede crear temas nuevos (nombre, clave y unidades de conteo) para cargar otras métricas, por ejemplo pasantes de servicio social, sin mezclarlas con las que ya existen; eliminar un tema borra sus bases.
- La tabla filtra por año, tema y unidad. Las bases de otros temas se guardan como `{entidad}-{año}-{tema}-{unidad}` y solo exigen el identificador y el nombre (las de Enfermería siguen exigiendo población, `enfermeras_total` y `tasa_total`).
- El mapa público sigue mostrando las bases de Enfermería; las de los demás temas se cargan y administran, y su vista en el mapa queda pendiente.

### Marcadores
- **Tipos de fábrica y su orden:** *Establecimientos de Salud*, *Clínicas de Catéteres*, *Clínicas de Heridas* y **Pasantes de Enfermería** (nuevo).
- **Periodos por tipo:** los establecimientos de salud y los pasantes se cargan **por año**; las clínicas y las variables nuevas admiten además **Todas** (antes «Toda la historia»). La tabla lista, por tipo, primero «Todas» y después los años, del más reciente al más antiguo, y filtra por año y por tema; al elegir un año se ven también los archivos de «Todas».
- Los archivos cargados antes de existir los periodos toman el suyo: los de las clínicas, «Todas»; los de establecimientos, el año más reciente.
- **Versión por entidad:** se sigue cargando un solo archivo nacional, y el plugin crea de él una capa por entidad (`markers/entidades/`). El mapa de cada estado descarga solo sus puntos: el catálogo de establecimientos de 2026 pasa de 4.1 MB a 126 KB en promedio (327 KB el mayor). El botón *Generar copias de lectura ahora* del Dashboard las crea para las capas que ya estaban cargadas.

## [0.1.7.1] - 2026-11-12

### Carga de datos
- Los textos de ayuda de *Geolocalización* y *Localidades (Geo)* usan los nombres nuevos de los archivos de RHEMX, con la clave de la entidad al inicio (`33_entidades.json`, `01_municipios.json`, `33_municipios.json`, `01_localidades.json`), y dicen de qué carpeta se toman (`Capa 1 Geolocalizacion` y `Capa 2 Localidades`). El plugin valida el contenido y no depende del nombre del archivo, así que los archivos con los nombres anteriores se siguen aceptando.

## [0.1.7.0] - 2026-11-12

### Geometrías (mapa base y capa detallada)
- **TopoJSON por marco geoestadístico:** *Geolocalización* y *Localidades (Geo)* ahora cargan el **TopoJSON que exporta RHEMX** (`.json` o `.topojson`; objeto `datos`, propiedades `c`, `n` y `t`) y un archivo **por entidad y marco quinquenal** (2005, 2010, 2015, 2020, 2025; el de 2030 aparecerá al llegar ese año). Un marco intermedio, como el de 2016, se carga en el quinquenio anterior (2015) con su fecha real en *Fecha de corte*. Los archivos se guardan como `{entidad}-{marco}.json`.
- **El mapa cambia de marco con el año:** cada año de datos usa el marco de su quinquenio (o el anterior más cercano que esté cargado). Al elegir un año de otro quinquenio el mapa cambia los polígonos sin perder el acercamiento ni los marcadores, y el pie muestra la fuente y la fecha de corte del marco en uso. La capa detallada también va por marco.
- **Validación al subir:** el plugin comprueba que el archivo sea de la capa correcta (entidades, municipios o localidades, por los dígitos de la clave) y de la entidad elegida, y lo rechaza con un mensaje claro si no lo es (por ejemplo, un archivo de localidades en la pestaña de municipios, o uno de AGEB).
- **Compatibilidad:** los GeoJSON ya cargados se siguen leyendo y quedaron asignados al marco **2025**; se reemplazan al subir el TopoJSON de ese marco.
- Se incluye `topojson-client` 3.1.0 (copia local) y `siarhe-topo.js`, que la página y el worker de geometrías comparten.

### Marcadores
- **Por periodo:** cada tipo de marcador (clínicas de catéteres, de heridas, establecimientos de salud…) admite un archivo **por año** y uno de **Toda la historia** (todas las unidades que han reportado al menos una vez). El mapa usa el del año que se ve; si no existe, el de toda la historia.
- **CSV o JSON:** se acepta el CSV o la **capa JSON de RHEMX** (`unidades_clues_<año>.json`, `clinicas_cateteres_<año>.json`, `clinicas_cateteres_historico.json`, `clinicas_heridas_historico.json`). Al subir uno se crea el otro y el mapa lee el JSON. En el CSV solo son obligatorias las coordenadas y la clave de la entidad.
- **Marcadores del sistema:** los establecimientos de salud por nivel de atención llevan los textos de RHEMX (*Primer Nivel*, *Segundo Nivel*, *Tercer Nivel*, *No Aplica*, *No Especificado*), filtran por la columna de nivel de atención y no se pueden modificar; solo su visibilidad.
- Los nombres de columna de la configuración de un marcador se comparan sin distinguir mayúsculas, acentos ni guion bajo (`nivel_atencion` = `NIVEL ATENCION`).

### Mapa
- El selector **Indicador Principal** se llama **Indicador** y ya no muestra la nota descriptiva debajo, que descuadraba la fila de controles (la unidad de conteo y el tema ya dicen lo mismo).
- **Corregido:** el botón no activo del conmutador *Medida* quedaba con texto blanco sobre gris claro al pasar el cursor en sitios cuyo tema da texto blanco a `button:hover`.

## [0.1.6.0] - 2026-11-12

### Métricas e indicadores
- **Indicador por nombre corto y conmutador Medida:** el selector de indicadores lista cada indicador una sola vez, con su nombre corto (*Auxiliar*, *Técnico*, *Primer nivel*…), y el nuevo conmutador **Medida** (*Tasa* / *Frecuencia*) cambia entre su tasa por mil habitantes y su frecuencia (número de plazas o de personas). Antes cada indicador aparecía dos veces con textos largos (*Tasa de enfermeras (estudios): Auxiliar*). Cada botón se desactiva si el indicador no tiene esa medida (por ejemplo, la población solo tiene frecuencia).
- **Descripción según la unidad:** bajo el selector se muestra la descripción del indicador y `{unidad}` se sustituye por *plazas* o *personas* según la unidad elegida. El selector de **unidad** ahora siempre se ve (deshabilitado si la entidad solo tiene una).
- **Nivel de atención es un tema propio** (primer, segundo, tercer nivel, apoyo, administrativas, escuelas, otros y no asignado). **General** queda con el total y la población.
- **Métricas y temas del sistema:** las 143 métricas de fábrica son del sistema; sus nombres, descripciones, tipo, par y tema se **fuerzan** al leer la configuración (aunque se hubieran editado o guardado dañadas) y el panel las muestra bloqueadas; solo se respetan su visibilidad y su escala/colores. Los temas General, Nivel de atención, Estudios y Especialización tampoco se editan ni se eliminan.
- **Panel de métricas:** campos nuevos *Nombre del indicador* y *Descripción*; el tipo de valor se llama **Tasa** / **Frecuencia** con una nota de ayuda; la tabla tiene columna **Tema**; al editar una métrica se puede **crear un tema nuevo** desde la misma lista de temas.

### Corregido
- Acentos en **marcadores de posición, densidad** y demás textos guardados (también en *Mapa y colores*, la carga de marcadores y los tooltips): se leían quitando barras a un JSON válido (`Clu00ednicas`). Ahora todo pasa por `siarhe_decode_json_option`, que además repara lo ya dañado.
- **Tooltips:** la configuración se descartaba al guardar desde la versión 0.0.5.45 (se registraba como arreglo y se recibe como JSON); ahora se conserva.

## [0.1.5.1] - 2026-11-12

### Mapa
- **Iconos en los controles:** año, unidad, tema, indicador, estilo, vista y marcadores llevan el icono de línea de RHEMX antes de su título (`public/img/`, se pintan con el color del texto).

## [0.1.5.0] - 2026-11-12

### Métricas
- **Temas:** las métricas se agrupan en temas, como en RHEMX. Nuevo selector **Tema** antes del indicador en el mapa y la tabla (solo muestra los temas con indicadores en la base cargada y se oculta si queda uno). Los temas se crean, renombran y eliminan en *Configuración › Métricas* (tarjeta **Temas**) y cada métrica se asigna a uno al editarla. Existen de fábrica **General** (donde caen todas las métricas actuales y las que no tengan tema; no se puede eliminar), **Estudios** y **Especialización** (las 62 variables de RHEMX). Al eliminar un tema sus métricas pasan a General.
- **Corregido:** los textos con acentos de las métricas guardadas aparecían como `atenciu00f3n`. La configuración se leía quitando barras dos veces; ahora se lee tal cual y solo se quitan si no es JSON válido (`siarhe_decode_json_option`), y los textos que ya se hubieran guardado dañados se reparan al leerlos.

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
