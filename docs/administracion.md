# Panel de administración

Menú **SIARHE** de WordPress. Todas las pantallas requieren permiso `manage_options` (administrador). El diseño usa un sistema propio de tokens (`--s-*` en `admin/css/siarhe-admin-ui.css`) que respeta el color del esquema de WordPress; las tarjetas llevan un borde superior de acento.

## Dashboard

- **Estadísticas:** cuántas entidades tienen mapa + base, GeoJSON, base estática y localidades.
- **Validar archivos cargados:** revisa todo lo que ya está en el servidor (mapas, localidades, bases estáticas y marcadores) y lista lo que no corresponde: archivos que faltan, de otra entidad, de otra capa, de otro año o de otra unidad, con el apartado donde están (`Siarhe_Validador::revisar`). Cada pestaña de *Carga de Datos* tiene el mismo botón para lo suyo.
- **Salud del sistema:** carpeta de datos escribible, tabla instalada, límites de subida, memoria de PHP, compresión de copias (zlib), copias de seguridad (cantidad y espacio), **compresión de los datos del mapa** (consulta los encabezados reales que entrega el servidor), versión del plugin y respuesta del CDN de D3.
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
| Selectores con búsqueda | Todo selector de los formularios y de las barras de las tablas se puede desplegar o escribir (`SiarheAdmin.initSelects`, Select2) |
| Plantillas mínimas | En Bases Estáticas, *Mínima nacional* y *Mínima municipal* para los temas propios y Clínicas; en Marcadores, *Plantilla mínima*. Se suben como CSV y el plugin crea el JSON |

**Bases Estáticas** agrega el **tema**: *Enfermería* (plazas y recursos humanos de enfermería), *Clínicas* (de catéteres o de heridas; tasa y frecuencia) y los **temas propios** que el administrador crea en la misma pantalla con su nombre, clave y unidades (`siarhe_base_temas()`, opción `siarhe_bases_temas`). La tabla filtra por año, tema y unidad.

**Marcadores** ofrece, en este orden, *Establecimientos de Salud*, *Clínicas de Catéteres*, *Clínicas de Heridas*, *Pasantes de Enfermería* y las variables que se agreguen (`siarhe_marker_types()`). Los establecimientos y los pasantes se cargan por año; las clínicas admiten además **Todas**. Al crear una variable (*Incorporar Nueva Variable*, al final de la pantalla) se responde **¿Tiene serie completa?**: Sí admite «Todas» y años; No, solo años. La tabla filtra por año y por tema.

Reemplazar un archivo **guarda una copia del anterior** (ver *Versiones y Copias*). Eliminar un archivo lo borra del servidor sin guardar copia (a propósito: una copia sin su registro en la base no se podría restaurar).

## Personalizar (`siarhe-customize`)

- **Tema Admin:** colores de las tablas del propio panel, con vista previa.
- **Mapa & Colores:** colores del mapa (cuartiles, monocromático, rangos, valor 0 y sin dato), **botones del sitio** (fondo, borde y texto en estado normal, al pasar el cursor y al presionar; el texto también pinta los íconos), estilos de la tabla pública y estilo de cada marcador.

## Configuración (`siarhe-settings`)

- **General:** disposición por defecto de las páginas del mapa: *contenedor* (centrado, máximo 1140 px) o *canvas* (ancho completo de la ventana con un margen lateral de 2.5 % a 5 %). Hasta la 0.1.10.0 estaba en *Personalizar › Mapa & Colores*; lo guardado ahí sigue valiendo mientras no se guarde esta pestaña. En PC el visitante puede cambiarla para sí con el botón «Ancho completo» / «Ancho normal» del mapa (se recuerda en su navegador).
- **Enlaces y Rutas:** a qué página lleva el doble clic de cada entidad y los documentos legales.
- **Tooltips:** contenido, orden y colores de las tarjetas del mapa y de los marcadores.
- **Métricas (CSV):** indicadores (columnas del CSV). Las **nativas** están protegidas; se pueden agregar propias con tipo (tasa o absoluto), escala y colores. La tabla filtra por tema.
- **Marcadores (CSV):** variables de marcador (posición, espectro o *spike*), su CSV de origen, filtros, agrupación y reglas del tooltip.

> La guía detallada, campo por campo, de **métricas** y **marcadores** (y los pasos para agregar uno nuevo) está en [metricas-y-marcadores.md](metricas-y-marcadores.md).
- **Versiones y Copias:** cuántas copias conservar por archivo (0–5, por defecto 1), compresión `.gz` de las copias (si hay `zlib`), espacio usado, **copias de lectura** (activar/desactivar), **historial de versiones** con filtro por tipo, buscador y paginación, y **restaurar** o eliminar cada copia con confirmación. La actividad reciente (quién restauró o eliminó qué) queda registrada.

## Tablas del panel

Todas comparten el mismo motor (`SiarheAdmin.initTable`, o `initSortHeaders` en las de Configuración que gestionan su propio filtrado): buscador sin acentos, paginación, contador y orden por columna con flechas que heredan el color del encabezado.

## Pendiente (Fase 4, hacia la 0.5.0.0)

*Gestor de Bases*: importación de CSV/XLSX a tablas SQL por lotes (AJAX con nonce y permisos), con progreso, reanudación, reporte de validación y bitácora; y las pestañas de carga de Pivotes, Formaciones, Escuelas, Investigación y Carreras.
