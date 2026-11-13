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

Cada script termina con `RESULTADO: todo correcto` o con la lista de fallas.

## 🗂️ Qué cubre cada una

| Script | Comprueba |
|---|---|
| `prueba_topo.js` | Marco que corresponde a cada año; conversión de `33_entidades`, `33_municipios`, `NN_municipios` y `NN_localidades` (marcos 2016, 2020 y 2025); que ningún polígono quede con el anillo invertido; que cada polígono tenga fila en la base estática; el worker con una topología |
| `prueba_geo.php` | Lista de marcos quinquenales; aceptación de cada archivo en su pestaña y rechazo por capa equivocada, entidad equivocada o AGEB |
| `prueba_marcadores.php` | Periodos válidos; ida y vuelta JSON → CSV → JSON de las capas de RHEMX; ida y vuelta CSV → JSON → CSV del catálogo CLUES (celda por celda) |
| `prueba_worker.js` | Mismos puntos desde el JSON y desde el CSV; filtros por nivel de atención; puntos de una sola entidad; capas de clínicas |

Lo que **no** cubren: las pantallas del panel, la carga real de archivos y el aspecto del mapa. Eso se prueba en un WordPress (ver [docs/pruebas.md](../docs/pruebas.md)).
