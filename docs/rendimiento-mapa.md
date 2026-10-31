# Rendimiento del mapa

Resumen de las optimizaciones de la versión **0.1.0.0**, por qué existen y los números que las justifican. Medidas del 31-oct-2026 con los archivos reales del sitio (mapa nacional con todos los establecimientos de salud, ~41 mil puntos).

## 1. Entrega comprimida de los datos (copias de lectura)

**Problema.** El servidor (OpenLiteSpeed + Cloudflare) entregaba `.csv` y `.geojson` como `application/octet-stream` y **sin comprimir**: el GeoJSON nacional viajaba completo (7 MB) en cada carga.

**Solución.** Para archivos de 100 KB o más se mantiene una copia idéntica con extensión `.geojson.json` / `.csv.txt` (tipos que todo servidor conoce y comprime). El mapa lee la copia; el original no cambia. Se crea al subir o restaurar, al abrir el mapa o el Dashboard, y con el botón *Generar copias de lectura ahora*. Se puede desactivar en *Configuración › Versiones y Copias*. Funciones: `siarhe_data_read_url()`, `siarhe_build_read_copy()`, `siarhe_generate_all_read_copies()`.

| Archivo | Antes | Después (por la red) |
|---|---|---|
| GeoJSON nacional de entidades | 7.06 MB | **733 KB** (Brotli) |
| CSV de establecimientos | 10.6 MB | **1.27 MB** (gzip) |
| CSV de pasantes | 13.9 MB | **2.69 MB** (gzip) |

Además se escribe un `.htaccess` en `siarhe-data/` con los tipos MIME correctos (solo surte efecto si el servidor lo permite). El Dashboard comprueba el resultado real.

## 2. Marcadores en canvas

**Problema.** Un `<path>` SVG por punto: con 41 mil nodos, cada paso de zoom reescribía su forma y borde, y el navegador debía dibujarlos todos en cada cuadro.

**Solución.** Con más de **3,000** marcadores activos (**800** en pantallas táctiles) todos —posición, burbujas y espinas— se dibujan en **un solo `<canvas>`**:

- Los símbolos y burbujas se dibujan con imágenes pequeñas reutilizables (`drawImage`); las burbujas se agrupan por tamaño en pasos de ~6 %, así 4,000 burbujas usan unas 40 imágenes.
- Las espinas se dibujan como triángulos con color por nivel (32 niveles).
- El orden de apilado se conserva: burbujas < espinas (norte atrás) < posición.
- Tooltip, resaltado y clic usan un índice espacial (`d3.quadtree`) y prueban **la forma real** de cada tipo (círculo de toque, radio de la burbuja, triángulo de la espina con un ancho mínimo de toque).
- En animaciones programadas (Restablecer, botones +/−) el canvas se dibuja en el **mismo cuadro** que el mapa; con gestos se agrupa en un dibujo por cuadro.
- Con menos puntos todo sigue en SVG; en SVG el zoom actualiza solo los marcadores **visibles** (`_refreshMarkerGeometry`), calculados según el tamaño real del contenedor (no solo el rectángulo 16:9 del `viewBox`), y los eventos están delegados en la capa.

Con 8,000 piezas (4,000 espinas + 4,000 burbujas): preparar ~18 ms, un dibujo completo unos 6–13 ms (contexto simulado), detección del puntero ~0.1 ms.

## 3. Lectura de los CSV en un Web Worker

`siarhe-worker.js` descarga, lee, filtra (entidad, filtro del marcador, coordenadas) y agrupa el CSV **fuera del hilo principal** y devuelve solo los puntos listos, con las columnas que el mapa usa después (`_raw` pasa de 24–25 columnas a 4–5). Un archivo compartido por varios marcadores se lee una vez (caché de 2 archivos). Si no hay workers, `loadMarkerData` usa el camino anterior (`d3.csv`). **La lógica de filtrado existe en los dos sitios y debe mantenerse idéntica.**

Leer los 10 MB tarda ~80 ms y procesarlos 40–140 ms (Node); el modo anterior bloqueaba la página.

## 4. Vista por municipios (capa detallada)

**Problema.** El GeoJSON de municipios (37 MB, 2,469 polígonos, **1.78 millones de vértices**) se interpretaba, proyectaba y dibujaba en el hilo principal, y el navegador lo rasterizaba y lo recorría para detectar el cursor en cada movimiento.

**Solución.** `siarhe-geo-worker.js` lo descarga, proyecta (Mercator, mismos parámetros que el mapa) y genera trazos en **tres niveles de detalle** (Douglas-Peucker):

| Nivel | Se usa con zoom | Vértices | Trazos |
|---|---|---|---|
| 0 | < 2.5 | 112,589 (15× menos) | 1.3 MB |
| 1 | 2.5 a 10 | 322,574 (5×) | 3.7 MB |
| 2 | ≥ 10 | 1,734,278 (completo) | 23 MB |

Error máximo medido del nivel 0: 0.29 unidades del `viewBox` (< 1 px a esa escala). `_locApplyLod()` cambia el trazo solo cuando el zoom cruza un umbral; desplazar el mapa o hacer zoom dentro de un nivel no reescribe nada. Respaldo: `d3.json` + `state.path` si no hay worker o la proyección no es reproducible. La capa base de entidades (330 mil vértices) aún se proyecta en el hilo principal.

## 5. Tamaños, bordes y zoom por dispositivo

- El **dispositivo** se decide por el ancho del contenedor (`< 520 px` móvil, `< 900 px` o puntero táctil tablet) y por `pointer: coarse`, no por `window.innerWidth`.
- **Cruces/símbolos de posición** en píxeles de pantalla: 14 px (PC), 15 (tablet), 11 (móvil) hasta k=4, bajando suavemente a 8, 10 y 8 px en k=25 y **sin volver a crecer**.
- **Borde** en píxeles: `min(1, max(0.4, 1.5 × escala))`. Antes dependía de la escala y en pantalla completa de PC tapaba el color del relleno.
- **Espinas y burbujas** en píxeles y **crecen** con el zoom: espina 6.8×17–69 px (PC) a 9×22–88 px; en móvil 4.8×11–46 a **12×24–96 px**; las burbujas se multiplican ×1.25 (PC), ×1.3 (tablet) y ×1.35 (móvil) con mucho zoom. El área de toque de una espina es al menos 5 px (PC) / 9 px (táctil) de semi-base.
- **Zoom máximo** del mapa nacional según el terreno (~30 m por píxel, tope 400×): ~**110×** en PC y ~**300×** en móvil (antes 50× = 84 m y 223 m por píxel). Los mapas estatales conservan 50×.

## Banderas de diagnóstico (URL)

| Parámetro | Efecto |
|---|---|
| `?siarhe_perf=1` | Escribe en la consola los tiempos de lectura, procesado, pintado, zoom y dibujo del canvas |
| `?siarhe_canvas=0` | Fuerza el modo SVG (para comparar) |
| `?siarhe_canvas=1` | Fuerza el canvas aunque haya pocos puntos |

## Descartado a propósito

- **Omitir puntos que caen en el mismo píxel:** cambia lo que el visitante puede señalar; con el canvas no hace falta.
- **JSON columnar** para los CSV de marcadores: la compresión ya resolvió la descarga y el worker el parseo; ver [decisiones.md](decisiones.md).

## Ideas pendientes

Cargar el nivel de detalle completo de los municipios solo al acercar (ahorra memoria en teléfonos modestos), pasar la capa base de entidades al worker, foco de teclado en los marcadores, y la vista de densidad por hexágonos para el mapa dinámico (la agregación se haría en SQL).
