# Decisiones de diseño

Formato: **qué se decidió** — porqué — consecuencias. Las más recientes arriba.

## Datos y rendimiento

- **Copias de lectura `.json` / `.txt` para archivos de 100 KB o más** — el servidor entregaba `.csv`/`.geojson` como `application/octet-stream` sin comprimir (7 MB por mapa nacional); la solución en el servidor dependía del hosting. Una copia con extensión conocida se comprime en cualquier parte. Cuesta espacio en disco (original + copia) y se puede desactivar.
- **Worker con respaldo para los CSV de marcadores y la capa de municipios** — mantener la página fluida con archivos de 10–40 MB. La lógica de filtrado está duplicada (worker y modo normal) a propósito para no depender de que el worker exista; está cubierta por una prueba que compara ambos.
- **Un solo canvas para posición, burbujas y espinas** — separar tipos entre SVG y canvas rompía el orden de apilado. Se activa por cantidad (3,000; 800 en táctiles) porque con pocos puntos el SVG es más simple y accesible. Las leyendas y la medición pasaron a una capa SVG superior para quedar sobre el canvas.
- **Niveles de detalle por polígono (Douglas-Peucker), no topología compartida** — es suficiente a la escala en que se usan (error < 1 px) y no exige librerías. Puede dejar rendijas de menos de un píxel entre vecinos que el borde blanco tenue oculta. TopoJSON sería la alternativa si hiciera falta.
- **No convertir los CSV a JSON columnar** (por ahora) — la compresión de la red y el worker resolvieron los cuellos de botella medidos. Se reevaluará con las bases grandes a SQL.
- **Omisión de puntos por píxel: descartada** — con el canvas no hace falta y cambiaría lo que se puede señalar.

## Visualización

- **Tamaños y bordes en píxeles de pantalla**, y el tipo de dispositivo por el ancho del contenedor — con unidades del mapa el resultado dependía de la escala (borde grueso en pantalla completa, espinas de 3 px en móvil).
- **Zoom máximo por resolución del terreno** (≈30 m/px) en el mapa nacional — 50× no separaba establecimientos de una ciudad.
- **Un solo estilo para todos los botones del sitio**, configurable por variables CSS (`--s-btn-*`), sin las clases `.button` de WordPress — el tema los recoloreaba. Los controles de zoom, el panel de medición y los avisos conservan su estilo.
- **La tabla de datos viene plegada cuando se muestran mapa y tabla juntos** — en entidades con tablas grandes empujaba las referencias.
- **Colores personalizados del visitante solo en su navegador** (no se guardan en el sitio ni en el enlace compartible); se aplican también al PNG.
- **Texto de los tooltips en formato "Nombre Propio"** salvo la CLUES; conectores en minúscula, siglas de hasta 3 letras y una lista de siglas conocidas se conservan.

## Datos de entrada

- **`tasa_total` y `enfermeras_total` obligatorias** en las bases estáticas — sin ellas el mapa no muestra nada.
- **Los marcadores exigen `CVE_ENT`, `ENTIDAD`, `CVE_MUN`, `MUNICIPIO`, `CVE_LOC` y `LOCALIDAD`** — el mapa de cada entidad filtra por clave; una fila sin `CVE_ENT` no se dibuja en ningún mapa.
- **La plantilla y el diccionario solo incluyen lo que lee el mapa**; las métricas propias y las columnas de pasantes no se incluyen.
- **Eliminar un archivo no guarda copia** — la copia no se podría restaurar sin su registro en la base.

## Panel de administración

- **Mantener el aspecto de WordPress con una capa moderna** (tokens, tarjetas, modales y avisos propios) en lugar de un diseño totalmente propio.
- **Sin `!important`**: se resuelve con especificidad.
- **Copias de seguridad: 1 por archivo por defecto** (configurable de 0 a 5), compresión `.gz` opcional; al reducir el límite se avisa antes de borrar las más antiguas.
- **Los motores de tabla de Enlaces, Métricas y Marcadores no se unificaron del todo**: son editores con filas mutables; se les añadió solo el orden por columna.
- **Diccionario en `.xlsx` con `ZipArchive`**, con respaldo a CSV si no está disponible.

## Proyecto

- **Mapa estático primero, dinámico (SQL) después.** El Gestor de Bases será el punto de entrada del mapa dinámico; ver [versiones-y-publicacion.md](versiones-y-publicacion.md).
- **Sin paso de compilación** — se edita y se recarga; se acepta a cambio de no tener bundler. Los scripts del frontend son simples y comparten el espacio `window.SiarheDataViz`.
- **Requisitos mínimos:** PHP 7.4 y WordPress 6.0; ver [requisitos.md](requisitos.md).
