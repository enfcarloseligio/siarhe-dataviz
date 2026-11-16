# Pruebas y verificación

El proyecto **no tiene suite de pruebas versionada** ni paso de compilación. Esto es lo que se hace hoy y lo que falta.

## Verificación habitual

| Qué | Cómo |
|---|---|
| Sintaxis de JavaScript | `node --check archivo.js` en cada `.js` de `public/js/` y `admin/js/` |
| Scripts incrustados en PHP | Se extraen los bloques `<script>` de las plantillas (reemplazando `<?php … ?>`) y se pasan por `node --check` |
| PHP | `php -l` en cada `.php` cambiado (PHP 8.4 fuera de WordPress) y prueba en WordPress |
| Versión | `Version:` y `SIARHE_VERSION` iguales; archivo sin BOM (primeros bytes `3c3f70`) |
| En el sitio | Pruebas manuales en PC (Windows, Brave) y Android (Brave), vertical y horizontal, normal y pantalla completa; el Dashboard comprueba salud y compresión |

## Pruebas automáticas escritas durante el desarrollo de la 0.1.0.0

Scripts de Node (con `jsdom` instalado fuera del proyecto) que cargan los archivos reales del plugin y los datos reales (tus CSV y GeoJSON de muestra):

| Cobertura | Qué comprueba |
|---|---|
| Worker de CSV | El lector de CSV coincide con el módulo `csv` de Python (41 mil filas); el resultado del worker es **idéntico** al del modo normal en 6 casos (nacional, por entidad, con filtro, agrupado con reglas) |
| Cableado worker ↔ página | Con worker, con el `Worker` bloqueado y con error de carga (los dos últimos usan el respaldo y dan lo mismo) |
| Zoom de marcadores SVG | Región visible según el contenedor (16:9, vertical, horizontal, cuadrada), pendientes al desplazar, una sola forma por tipo de posición |
| Canvas | Decisión SVG/canvas por cantidad y dispositivo, dibujo y recorte, escala en pantalla vertical, detección del puntero por forma (posición, burbuja, espina), prioridad, resaltado, clic y medición, exportación como vectores, 8,000 piezas |
| DOM real (jsdom) | Render con la capa superior, reparto entre capas y su orden, medición sobre el canvas, exportar PNG con leyendas, medición y marcadores, sincronía del canvas en zoom programado |
| Tamaños y zoom | Tamaños por dispositivo, clasificación (incluido iPad), borde en píxeles, espinas/burbujas, el tamaño nunca se encoge al acercar, zoom máximo del mapa nacional vs. estatal |
| Capa de municipios | El worker con el archivo real de 37 MB (proyección idéntica a d3, error de simplificación, 15× menos vértices) y, con 300 municipios reales, carga con progreso, respaldos y cambio de nivel al hacer zoom |

## Pruebas en el repositorio (0.1.7.0)

La carpeta [`tests/`](../tests/LEEME.md) guarda las pruebas de la lectura de geometrías (TopoJSON por marco) y de marcadores (CSV y JSON por periodo), que se ejecutan con Node y PHP contra los archivos reales de RHEMX. En la 0.1.7.0 pasaron las cuatro; lo que cubren y cómo ejecutarlas está en su `LEEME.md`.

En la 0.1.8.0 se agregó `prueba_validador.php` (entidad de las bases estáticas y marcadores por entidad) y pasaron las cinco con PHP 8.4 y Node; además se revisó la sintaxis de todos los `.php` cambiados con `php -l`.

La 0.1.9.0 (selectores con búsqueda, esqueleto de carga, contenedor o canvas, referencias, total sin extranjero) se revisó con `php -l`, `node --check` y las cinco pruebas; tampoco se ha visto en WordPress. `prueba_validador.php` escribe en `builds\pruebas\division\` para no pisar los archivos de las demás.

La 0.1.10.0 (controles homologados con RHEMX) agregó `prueba_controles.js` (conmutador, buscador, grupos de marcadores, etiquetas sin encimarse y marcadores de posición por tema, con un DOM simulado) y el **arnés de navegador** de `tests/arnes/`, que carga el front real con datos de RHEMX sin WordPress. Con el arnés se vieron en PC, tableta y móvil los controles, los marcadores por tema, el cambio de año, los enlaces, el buscador, las etiquetas, el ancho, la tabla y la pantalla completa con Esc. La 0.1.11.0 (un solo botón «PNG», D3 local primero, pestaña *Configuración › General*) se revisó con `php -l`, `node --check` y `prueba_controles.js`.

La 0.1.12.0 (buscador con municipios y CLUES, tabla con clave y descargas, conmutador Contenedor / Canvas, zoom y tamaños de RHEMX) agregó a `prueba_controles.js` el rango del buscador y las columnas y claves de la tabla, y se vio en el arnés: mismas coincidencias que RHEMX para «madero» y «civil de», ir a un municipio y a una unidad, enlace con el lugar buscado, descargas CSV, Excel y JSON, zoom máximo de 50 y marcadores de 14 px.

La 0.1.13.0 (entrar a un estado dentro del mapa, «Estilo de marcadores», decimales de RHEMX, PNG con medidas de PC, colores del sistema y restablecer) agregó a `prueba_controles.js` los decimales de las tasas y la entidad del enlace, y se vio en el arnés con la República y los 32 estados: entrar a un estado desde el buscador («ciudad madero»), desde la tabla y desde un enlace copiado, volver con el botón y con Esc, buscar una unidad dentro del estado, cambiar y restaurar el estilo de un nivel, y la descarga del PNG, que sale idéntica con la ventana de teléfono y la de PC.

La 0.1.14.0 (selectores propios de la tabla y resalte del renglón desde el mapa) se vio en el arnés: cambiar año, tema, indicador, unidad y medida desde la tabla y desde el mapa, con los dos juegos de controles siempre iguales, y el resalte en la República (Ciudad de México, clave 09) y en un estado (municipios).

La 0.1.15.0 (página de la entidad y cambio de diseño) se vio en el arnés: el buscador en la República con «ciudad madero» abre `estado-28.html` con el año elegido y el municipio enfocado, y una CLUES abre `estado-14.html` con su nivel encendido y su ficha; el cambio de diseño al cargar, medido con `PerformanceObserver` (`layout-shift`), quedó en 0.0006 en móvil y 0.005 en tableta y PC. En el sitio publicado (0.1.14.0) se midió antes el origen del 0.47 de PageSpeed: la hoja del plugin al final del `<body>`.

La 0.1.16.0 (hoja antes de la visualización, aro de los marcadores, 14 formas, ficha con personal y clínica, etiquetas en el enlace, orden de Personalizar) se revisó con `php -l`, `node --check`, `prueba_controles.js` y la pantalla de Personalizar dibujada en consola; el aro, las formas y la ficha se vieron en el arnés.

La 0.1.16.0 se probó en el sitio real (2026-11-16): el dueño confirmó que todo funciona y que PageSpeed quedó en 100 en todo, con el cambio de diseño en PC resuelto.

La 0.1.17.0 (tarjetas de cifras, resumen y nota al pie, ficha fija de la unidad buscada, leyenda opaca en el PNG, limpieza de la hoja) se revisó con `node --check`, `php -l` y `prueba_controles.js` (cifras con municipios nuevos, sin polígono y extranjero), y en el arnés: Tamaulipas y la República dan los totales del renglón «Total» de las bases de RHEMX (11,208 plazas y 3,611,225 habitantes; 371,590 y 130,393,389), el indicador «Primer nivel» y la unidad «Recursos humanos» cambian tarjetas, resumen y nota, y la ficha de «Hospital General Civil Nuevo Laredo» elegida en el buscador sigue abierta al mover el cursor y se cierra con la ×. Para la limpieza de la hoja se compararon los estilos calculados de los 520 elementos del panel antes y después (PC y móvil): ninguno cambió. Cambio de diseño al cargar Tamaulipas (`PerformanceObserver`, `layout-shift`): 0.003 en PC y 0 en móvil. Para llegar a 0 en móvil, el resumen del servidor ya trae la frase «(en … de ellos hay registros; … no tienen municipio asignado)»; sin ella el renglón extra daba 0.016.

La 0.1.18.0 (proyección y encuadre de RHEMX, PNG de RHEMX, selección y ficha fija, aros, ficha de las unidades del sistema, botones del mapa, centrado de textos) se revisó con las seis pruebas y en el arnés: encuadre de Tamaulipas y Colima contra el cálculo de RHEMX en Node (diferencia de 0.2 % y 0.9 %), la República con k = 1 y la izquierda libre, búsqueda de «Hospital General Civil Ciudad Madero» (25 veces el encuadre, seleccionado y con la ficha fija; al mover el puntero la ficha se suelta y el aro oscuro sigue a otro marcador con el seleccionado intacto; un clic fuera lo quita), aros de 19 px de radio, PNG de Tamaulipas con marcadores y el centrado de los textos medido en píxeles con Roboto (todos a 0.25 px).

La 0.1.19.0 (tooltips de estados y municipios de RHEMX, Roboto incrustada en el PNG) se revisó con `node --check`, `php -l`, `prueba_controles.js` y `prueba_topo.js`, y en el arnés: Tampico da «Municipio: Tampico», población, «Plazas de enfermería» y «Tasa por 1,000 habitantes» con etiquetas y cifras de 11.8 px; Tamaulipas en la República da sus tres datos y «Doble clic para ver sus municipios»; la leyenda dice «Plazas de enfermería»; y el PNG de la República sale de 1920×1080 con la fuente incrustada (en un Windows sin Roboto instalada).

La 0.1.19.1 (municipios de cada estado en el tooltip) se vio en el arnés: Tamaulipas muestra «Municipios: 43» medio segundo después de pasar por él; los conteos de la capa 2 se compararon con los datos de RHEMX en los 32 estados (iguales con el marco 2025).

> [!IMPORTANT]
> La 0.1.10.0 a la 0.1.15.0 no se probaron por separado en un WordPress real; la 0.1.16.0, que las incluye, sí: el dueño la instaló en su sitio y confirmó que todo está correcto (2026-11-16). La 0.1.17.0 falta verla en el sitio: las tarjetas, el resumen y la nota con el tema del sitio, la ficha fija tras buscar una unidad, el PNG y repetir PageSpeed (las tarjetas reservan su alto para no mover la página).

> [!IMPORTANT]
> La 0.1.8.0 tampoco se ha probado en un WordPress real: faltan las pantallas de carga con sus filtros, la migración del esquema (versión 4: columna `tema`), la carga de bases de clínicas y de temas propios, el botón *Validar archivos cargados* y la versión por entidad de los marcadores en el mapa de un estado.

> [!IMPORTANT]
> La 0.1.7.0 **no se ha probado en un WordPress real**: faltan la carga de archivos desde el panel (geometrías por marco y marcadores por periodo), la migración del esquema (versión 3) y el cambio de marco al cambiar de año en el mapa.

## Pendiente

- Las pruebas escritas para la 0.1.0.0 (tabla de arriba) no se conservaron; las nuevas van en `tests/`.
- Pruebas del PHP (validadores de CSV, catálogo de columnas, copias de seguridad); requeriría PHP en el entorno de desarrollo o un contenedor.
- Las pruebas de aspecto (cómo se ve) siguen siendo manuales; solo se verifican geometría y lógica.
