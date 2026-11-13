# Pruebas y verificación

El proyecto **no tiene suite de pruebas versionada** ni paso de compilación. Esto es lo que se hace hoy y lo que falta.

## Verificación habitual

| Qué | Cómo |
|---|---|
| Sintaxis de JavaScript | `node --check archivo.js` en cada `.js` de `public/js/` y `admin/js/` |
| Scripts incrustados en PHP | Se extraen los bloques `<script>` de las plantillas (reemplazando `<?php … ?>`) y se pasan por `node --check` |
| PHP | No hay PHP en el equipo de desarrollo: se revisa el **balance de llaves, paréntesis y corchetes** y se prueba en WordPress |
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

> [!IMPORTANT]
> La 0.1.8.0 tampoco se ha probado en un WordPress real: faltan las pantallas de carga con sus filtros, la migración del esquema (versión 4: columna `tema`), la carga de bases de clínicas y de temas propios, el botón *Validar archivos cargados* y la versión por entidad de los marcadores en el mapa de un estado.

> [!IMPORTANT]
> La 0.1.7.0 **no se ha probado en un WordPress real**: faltan la carga de archivos desde el panel (geometrías por marco y marcadores por periodo), la migración del esquema (versión 3) y el cambio de marco al cambiar de año en el mapa.

## Pendiente

- Las pruebas escritas para la 0.1.0.0 (tabla de arriba) no se conservaron; las nuevas van en `tests/`.
- Pruebas del PHP (validadores de CSV, catálogo de columnas, copias de seguridad); requeriría PHP en el entorno de desarrollo o un contenedor.
- Las pruebas de aspecto (cómo se ve) siguen siendo manuales; solo se verifican geometría y lógica.
