# Panel de administración

Menú **SIARHE** de WordPress. Todas las pantallas requieren permiso `manage_options` (administrador). El diseño usa un sistema propio de tokens (`--s-*` en `admin/css/siarhe-admin-ui.css`) que respeta el color del esquema de WordPress; las tarjetas llevan un borde superior de acento.

## Dashboard

- **Estadísticas:** cuántas entidades tienen mapa + base, GeoJSON, base estática y localidades.
- **Salud del sistema:** carpeta de datos escribible, tabla instalada, límites de subida, memoria de PHP, compresión de copias (zlib), copias de seguridad (cantidad y espacio), **compresión de los datos del mapa** (consulta los encabezados reales que entrega el servidor), versión del plugin y respuesta del CDN de D3. Incluye el botón *Generar copias de lectura ahora*.
- **Entidades y shortcodes:** matriz de las 34 entidades con qué archivos tiene cada una, fecha de última actualización y botones para copiar el shortcode (*Completo*, *Mapa*, *Tabla*). Con buscador, filtro por estado y columnas ordenables.

## Carga de Datos (`siarhe-uploader`)

Pestañas: **Geolocalización**, **Localidades (Geo)**, **Bases Estáticas**, **Marcadores**, y las futuras **Pivotes, Formaciones, Escuelas, Investigación y Carreras** (marcadores de posición hasta la Fase 4).

Las tres primeras usan **una sola plantilla** (`admin/partials/upload-tabs/_uploader.php`) configurada por un arreglo por pestaña. Cada carga ofrece:

- Arrastrar y soltar, vista previa de las primeras filas del CSV o resumen del GeoJSON y barra de progreso real.
- Validación previa en el navegador y validación completa en el servidor (ver [formatos-de-datos.md](formatos-de-datos.md)).
- En Bases Estáticas y Marcadores: **plantillas CSV** y **diccionario de variables** descargables.
- Metadatos de cada archivo: año de los datos, fecha de corte, fuente/referencia y comentarios internos (editables después).
- Tabla de archivos con buscador, paginación, columnas ordenables (incluida *Auditoría*, que ordena por última modificación o, si no hubo, por fecha de subida), tamaño y acciones.

Reemplazar un archivo **guarda una copia del anterior** (ver *Versiones y Copias*). Eliminar un archivo lo borra del servidor sin guardar copia (a propósito: una copia sin su registro en la base no se podría restaurar).

## Personalizar (`siarhe-customize`)

- **Tema Admin:** colores de las tablas del propio panel, con vista previa.
- **Mapa & Colores:** colores del mapa (cuartiles, monocromático, rangos, valor 0 y sin dato), **botones del sitio** (fondo, borde y texto en estado normal, al pasar el cursor y al presionar; el texto también pinta los íconos), estilos de la tabla pública y estilo de cada marcador.

## Configuración (`siarhe-settings`)

- **Enlaces y Rutas:** a qué página lleva el doble clic de cada entidad y los documentos legales.
- **Tooltips:** contenido, orden y colores de las tarjetas del mapa y de los marcadores.
- **Métricas (CSV):** indicadores (columnas del CSV). Las **nativas** están protegidas; se pueden agregar propias con tipo (tasa o absoluto), escala y colores.
- **Marcadores (CSV):** variables de marcador (posición, espectro o *spike*), su CSV de origen, filtros, agrupación y reglas del tooltip.

> La guía detallada, campo por campo, de **métricas** y **marcadores** (y los pasos para agregar uno nuevo) está en [metricas-y-marcadores.md](metricas-y-marcadores.md).
- **Versiones y Copias:** cuántas copias conservar por archivo (0–5, por defecto 1), compresión `.gz` de las copias (si hay `zlib`), espacio usado, **copias de lectura** (activar/desactivar), **historial de versiones** con filtro por tipo, buscador y paginación, y **restaurar** o eliminar cada copia con confirmación. La actividad reciente (quién restauró o eliminó qué) queda registrada.

## Tablas del panel

Todas comparten el mismo motor (`SiarheAdmin.initTable`, o `initSortHeaders` en las de Configuración que gestionan su propio filtrado): buscador sin acentos, paginación, contador y orden por columna con flechas que heredan el color del encabezado.

## Pendiente (Fase 4, hacia la 0.5.0.0)

*Gestor de Bases*: importación de CSV/XLSX a tablas SQL por lotes (AJAX con nonce y permisos), con progreso, reanudación, reporte de validación y bitácora; y las pestañas de carga de Pivotes, Formaciones, Escuelas, Investigación y Carreras.
