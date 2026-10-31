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

## Pendiente

- **Mover esas pruebas a `tests/` en el repositorio** (son scripts de Node que no viajan en el `.zip` del plugin) con una nota de cómo ejecutarlas. Hoy viven en una carpeta temporal de la sesión de trabajo y se perderían.
- Pruebas del PHP (validadores de CSV, catálogo de columnas, copias de seguridad); requeriría PHP en el entorno de desarrollo o un contenedor.
- Las pruebas de aspecto (cómo se ve) siguen siendo manuales; solo se verifican geometría y lógica.
