# 🧪 Pruebas del plugin

Scripts que comprueban la lectura de geometrías y de marcadores con los **archivos reales de RHEMX**, sin WordPress. Viven fuera de la carpeta del plugin, así que no viajan en el `.zip`.

> [!NOTE]
> Necesitan Node.js, PHP 7.4 o superior y el repositorio hermano `rhemx` con sus datos generados (`web/data/geo`, `web/data/maps` y, para el catálogo CLUES, `data/processed/CLUES`).

## 🪜 Cómo ejecutarlas

Desde la raíz del repositorio, con `..\rhemx` como carpeta de RHEMX:

Paso 1 — Geometrías en el navegador: convierte los TopoJSON de tres marcos, comprueba su unión con las bases estáticas y el worker de geometrías.

```powershell
node tests\prueba_topo.js siarhe-dataviz\public\js ..\rhemx
```

Paso 2 — Geometrías en el servidor: marcos disponibles y validación de la capa y la entidad de cada archivo.

```powershell
php tests\prueba_geo.php siarhe-dataviz ..\rhemx
```

Paso 3 — Marcadores en el servidor: convierte las capas JSON de RHEMX a CSV y de vuelta, y el catálogo CLUES de CSV a JSON y de vuelta. Deja los archivos en la carpeta de salida, que usa el paso siguiente.

```powershell
php tests\prueba_marcadores.php siarhe-dataviz ..\rhemx builds\pruebas
```

Paso 4 — Marcadores en el navegador: el worker debe dar los mismos puntos con el JSON y con su CSV, incluidos los filtros de los marcadores del sistema.

```powershell
node tests\prueba_worker.js siarhe-dataviz\public\js ..\rhemx builds\pruebas
```

Paso 5 — Validador y marcadores por entidad: el orden de las entidades, los tipos de marcador y los temas; que cada base estática se acepte en su entidad y se rechace en otra; y que la capa de marcadores dividida por entidad dé las mismas filas que la completa.

```powershell
php tests\prueba_validador.php siarhe-dataviz ..\rhemx builds\pruebas
```

Cada script termina con `RESULTADO: todo correcto` o con la lista de fallas.

## 🗂️ Qué cubre cada una

| Script | Comprueba |
|---|---|
| `prueba_topo.js` | Marco que corresponde a cada año; conversión de `33_entidades`, `33_municipios`, `NN_municipios` y `NN_localidades` (marcos 2016, 2020 y 2025); que ningún polígono quede con el anillo invertido; que cada polígono tenga fila en la base estática; el worker con una topología |
| `prueba_geo.php` | Lista de marcos quinquenales; aceptación de cada archivo en su pestaña y rechazo por capa equivocada, entidad equivocada o AGEB |
| `prueba_marcadores.php` | Periodos válidos; ida y vuelta JSON → CSV → JSON de las capas de RHEMX; ida y vuelta CSV → JSON → CSV del catálogo CLUES (celda por celda) |
| `prueba_worker.js` | Mismos puntos desde el JSON y desde el CSV; filtros por nivel de atención; puntos de una sola entidad; capas de clínicas |
| `prueba_controles.js` | Sin argumentos (`node tests\prueba_controles.js`). Conmutador de dos opciones, normalización del buscador, grupos y resumen de los desplegables de marcadores, acomodo de etiquetas sin encimarse, rango y orden de los resultados del buscador y columnas y claves de la tabla, con un DOM simulado |
| `prueba_validador.php` | Orden Extranjero, República Mexicana y entidades; tipos de marcador y sus periodos; temas de las bases; las 33 bases de un año aceptadas en su lugar y rechazadas en otro (CSV y JSON); una base de clínicas; división de `unidades_clues`, catéteres y heridas en 32 capas con las mismas filas que la completa |

Lo que **no** cubren: las pantallas del panel, la carga real de archivos y el aspecto del mapa. Eso se prueba en un WordPress (ver [docs/pruebas.md](../docs/pruebas.md)).

## 🖥️ Arnés de navegador (ver el mapa sin WordPress)

`tests/arnes/` arma una página estática que carga el front real del plugin (CSS y JS de `public/`) con los datos de RHEMX: mapa nacional, bases de 2019, 2024 y 2025, marcos 2015 a 2025, catálogo CLUES y clínicas de catéteres y de heridas. Sirve para revisar controles, marcadores, etiquetas y tamaños en móvil, tableta y PC antes de subir el paquete.

Paso 1 — Configuración por defecto del plugin (métricas, temas, marcadores) en JSON:

```powershell
php tests\arnes\config.php siarhe-dataviz > builds\pruebas\config.json
```

Paso 2 — Página de prueba (el tercer argumento opcional es `canvas` o `contenedor`):

```powershell
python tests\arnes\arma.py builds\pruebas\config.json builds\pruebas\arnes.html
```

Paso 3 — Servidor local desde la carpeta que contiene `siarhe-dataviz` y `rhemx`, y abrir `http://127.0.0.1:8765/siarhe-dataviz/builds/pruebas/arnes.html`:

```powershell
cd ..; python -m http.server 8765 --bind 127.0.0.1
```

> [!NOTE]
> No pasa por PHP: el arnés no prueba el shortcode, la base de datos ni el panel. La pantalla completa, Esc y la descarga de PNG necesitan un clic real del usuario.
