# Arquitectura

El plugin vive en la carpeta anidada `siarhe-dataviz/`. **No hay paso de compilación, gestor de paquetes ni linter**: se edita PHP/JS/CSS y se recarga en WordPress. La versión se declara en dos lugares de `siarhe-dataviz/siarhe-dataviz.php` (cabecera `Version:` y constante `SIARHE_VERSION`).

## Estructura

```
siarhe-dataviz/
├── siarhe-dataviz.php          punto de entrada (requisitos, constantes, módulos, activación)
├── admin/                      panel de administración
│   ├── class-siarhe-admin.php  manejadores admin_post_*, opciones, validación de cargas
│   ├── partials/               pantallas (dashboard, cargas, configuración, personalizar)
│   ├── css/ js/ libs/          sistema de diseño del panel, comportamiento y respaldos de Select2
├── includes/
│   ├── helpers.php             catálogo de entidades, opciones por defecto, variables CSS, copias de lectura
│   ├── class-siarhe-shortcodes.php   shortcodes dinámicos por entidad
│   ├── class-siarhe-versions.php     copias de seguridad de los archivos y su historial
│   ├── class-siarhe-columns.php      catálogo de columnas (plantillas y diccionario)
│   ├── class-siarhe-xlsx.php         generador mínimo de .xlsx
│   └── db/                     instalador de tablas (dbDelta)
├── public/                     frontend
│   ├── partials/siarhe-view.php      entrada real del frontend
│   ├── css/siarhe-frontend.css
│   └── js/                     módulos + workers + libs/ (D3 local)
└── languages/                  dominio de traducción (vacío por ahora)
```

## Datos

- **Archivos:** `wp-content/uploads/siarhe-data/{geojson, localidades, static-min, markers, pivote, formaciones, escuelas, investigacion, carreras}/`. Cada carpeta puede tener `_versions/` con las copias de seguridad y, junto a los archivos grandes, sus copias de lectura (`*.geojson.json`, `*.csv.txt`).
- **Tabla `{prefix}siarhe_static_assets`:** un registro por archivo cargado (entidad, tipo, ruta, año, fecha de corte, referencia, comentarios, `es_activo`, auditoría). El frontend busca aquí el GeoJSON y el CSV activos de cada entidad.
- **Opciones de WordPress:** `siarhe_map_options` (colores, botones, tabla), `siarhe_theme_options` (tema del panel), `siarhe_links_map`, `siarhe_metricas_config`, `siarhe_tooltip_config`, `siarhe_marcadores_config`, `siarhe_archivos_marcadores`, `siarhe_versions_*` (copias), `siarhe_read_copies`, `siarhe_db_version`.
- **Seguridad:** todas las acciones del panel pasan por `admin_post_*` con `current_user_can('manage_options')` y nonce; las descargas de plantillas igual. Los datos que llegan de CSV/GeoJSON se escapan antes de insertarse en HTML.

## Flujo de una visualización

1. `[siarhe_mapa_{M|T|MT}_CVE_ENT_{nn}]` (M = mapa, T = tabla, MT = ambos; `33` = nacional) lo atiende `Siarhe_Shortcodes`, que busca en la tabla el GeoJSON y el CSV activos de la entidad.
2. `public/partials/siarhe-view.php` encola los scripts según el modo y entrega la configuración al JavaScript con atributos `data-*` (URLs, métricas, tooltips, marcadores, URL de los workers).
3. Las URLs de los archivos grandes pasan por `siarhe_data_read_url()`, que devuelve la **copia de lectura** comprimible.
4. `siarhe-core.js` descarga GeoJSON y CSV, y arma el estado; los demás módulos dibujan.

## Frontend (JavaScript, `public/js/`)

Scripts simples en un solo espacio de nombres `window.SiarheDataViz`, con orden de carga garantizado por dependencias de WordPress:

`siarhe-core.js` (estado, utilidades, datos, paleta) → `siarhe-controls.js` (selectores, panel Personalizar, carga de marcadores) → `siarhe-tooltips.js` → `siarhe-map.js` (mapa D3, zoom, marcadores, medición, exportar PNG) y `siarhe-table.js`.

Workers (hilos en segundo plano, cada uno con respaldo si no se pueden usar):

- `siarhe-worker.js`: descarga, lee, filtra y agrupa el CSV de un marcador.
- `siarhe-geo-worker.js`: prepara la capa de municipios/localidades en tres niveles de detalle.

### Capas dentro de `.siarhe-map-container`

En este orden, de abajo hacia arriba:

1. `<svg>` principal: polígonos, etiquetas y, con pocos puntos, marcadores SVG.
2. `<canvas class="siarhe-canvas-markers">`: marcadores de posición, burbujas y espinas cuando hay muchos.
3. `<svg class="siarhe-overlay-svg">`: leyendas y capa de medición (mismo `viewBox`, sigue el zoom).
4. Controles HTML (zoom, panel de medición) y el tooltip.

La exportación a PNG clona el SVG principal y le agrega las leyendas, la medición y los puntos del canvas como vectores.

## Puntos de extensión

- **Nueva carga de archivos:** llamar a `Siarhe_Versions::archive()` antes de reemplazar y a `siarhe_build_read_copy()` después de guardar, y registrar su carpeta en `Siarhe_Versions::TYPES`.
- **Nuevas columnas de plantilla:** `Siarhe_Columns` (también alimenta el diccionario descargable).
- **Nueva entidad o clave:** `siarhe_get_entities()` en `helpers.php` (única fuente del catálogo).
