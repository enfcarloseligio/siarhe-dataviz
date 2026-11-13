# Panel de administración

Menú **SIARHE** de WordPress. Todas las pantallas requieren permiso `manage_options` (administrador). El diseño usa un sistema propio de tokens (`--s-*` en `admin/css/siarhe-admin-ui.css`) que respeta el color del esquema de WordPress; las tarjetas llevan un borde superior de acento.

## Dashboard

- **Estadísticas:** cuántas entidades tienen mapa + base, GeoJSON, base estática y localidades.
- **Validar archivos cargados:** revisa todo lo que ya está en el servidor (mapas, localidades, bases estáticas y marcadores) y lista lo que no corresponde: archivos que faltan, de otra entidad, de otra capa, de otro año o de otra unidad, con el apartado donde están (`Siarhe_Validador::revisar`). Cada pestaña de *Carga de Datos* tiene el mismo botón para lo suyo.
- **Salud del sistema:** carpeta de datos escribible, tabla instalada, límites de subida, memoria de PHP, compresión de copias (zlib), copias de seguridad (cantidad y espacio), **compresión de los datos del mapa** (consulta los encabezados reales que entrega el servidor), versión del plugin y respuesta del CDN de D3. Incluye el botón *Generar copias de lectura ahora*, que también crea la versión por entidad de las capas de marcadores que no la tengan.
- **Entidades y shortcodes:** matriz de las 34 entidades (Extranjero, República Mexicana y las 32 entidades por su clave) con qué archivos tiene cada una, fecha de última actualización y botones para copiar el shortcode (*Completo*, *Mapa*, *Tabla*). Con buscador, filtro por estado y columnas ordenables.

## Carga de Datos (`siarhe-uploader`)

Pestañas: **Geolocalización**, **Localidades (Geo)**, **Bases Estáticas**, **Marcadores**, y las futuras **Pivotes, Formaciones, Escuelas, Investigación y Carreras** (marcadores de posición hasta la Fase 4).

Las dos primeras usan **una sola plantilla** (`admin/partials/upload-tabs/_uploader.php`) configurada por un arreglo por pestaña; *Bases Estáticas* y *Marcadores* tienen la suya. Cada carga ofrece:

- Arrastrar y soltar, vista previa de las primeras filas del CSV o resumen del GeoJSON y barra de progreso real.
- Validación previa en el navegador y validación completa en el servidor (ver [formatos-de-datos.md](formatos-de-datos.md)).
- En Bases Estáticas y Marcadores: **plantillas CSV** y **diccionario de variables** descargables.
- Metadatos de cada archivo: año de los datos, fecha de corte, fuente/referencia y comentarios internos (editables después).
- Tabla de archivos con buscador, paginación, columnas ordenables (incluida *Auditoría*, que ordena por última modificación o, si no hubo, por fecha de subida), tamaño y acciones.

Reglas comunes de las cuatro pestañas:

| Regla | Cómo |
|---|---|
| Orden de las entidades | Extranjero, República Mexicana y las entidades por su clave (`siarhe_get_entities_ordered()`), en los selectores y en las tablas |
| Filtro de periodo | Marco (geometrías) o año (bases y marcadores): solo los periodos con al menos un archivo; abre en el más reciente y ofrece **Todos**. Sin archivos, el marco o el año vigente |
| Lo que falta | En cada periodo se listan todas las entidades, con archivo o sin él |
| Validación al subir | La capa y la entidad en las geometrías; la entidad, el año y la unidad en las bases; el periodo que admite el tipo en los marcadores |
| Validar lo cargado | Botón *Validar archivos cargados* de la barra de cada tabla |

**Bases Estáticas** agrega el **tema**: *Enfermería* (plazas y recursos humanos de enfermería), *Clínicas* (de catéteres o de heridas; tasa y frecuencia) y los **temas propios** que el administrador crea en la misma pantalla con su nombre, clave y unidades (`siarhe_base_temas()`, opción `siarhe_bases_temas`). La tabla filtra por año, tema y unidad.

**Marcadores** ofrece, en este orden, *Establecimientos de Salud*, *Clínicas de Catéteres*, *Clínicas de Heridas*, *Pasantes de Enfermería* y las variables que se agreguen (`siarhe_marker_types()`). Los establecimientos y los pasantes se cargan por año; las clínicas y las variables nuevas admiten además **Todas**. La tabla filtra por año y por tema.

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
