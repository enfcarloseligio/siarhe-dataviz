# Versiones y publicación

## Hitos de versión

| Versión | Hito | Estado |
|---|---|---|
| **0.1.0.0** | Cierre de la parte estática del frontend y todas sus optimizaciones | **Publicada** (31-oct-2026) |
| **0.5.0.0** | Todos los apartados de carga cuyos archivos se convierten a SQL: **pivotes, formaciones, investigación, escuelas y carreras** (Fase 4, Gestor de Bases) | Pendiente |
| **1.0** | El **mapa interactivo** (dinámico, respaldado por SQL) funcionando | Pendiente |

Las versiones `0.0.x` fueron incrementos de trabajo hasta llegar al primer hito.

### Qué incluye la 0.1.0.0

Mapa estático optimizado (canvas para marcadores, Web Worker para los CSV, vista por municipios con niveles de detalle, tamaños/bordes/zoom por dispositivo), copias de lectura comprimidas, plantillas y diccionario de variables descargables, copias de seguridad con historial, panel rediseñado con Dashboard real, personalización de colores y botones, tabla plegable y tooltips con formato. Detalle en [`CHANGELOG.md`](../CHANGELOG.md) y [rendimiento-mapa.md](rendimiento-mapa.md).

### Hacia la 0.5.0.0 y la 1.0

1. Reunir las reglas de los **archivos crudos de INEGI** y de las bases completas (pivotes, formaciones, escuelas, investigación, carreras) del proyecto hermano; documentarlas en [formatos-de-datos.md](formatos-de-datos.md).
2. Diseñar el esquema SQL y el **Gestor de Bases**: importación por lotes (50–100 filas) con AJAX (nonce y permisos), progreso, reanudación, reporte de validación y bitácora; reutilizando el componente de carga y `validate_csv_file()`.
3. Adaptar el mapa para que sea compatible con el mapa de **RHEMX**.
4. Mapa dinámico: agregación en SQL (por ejemplo por celdas hexagonales a zoom nacional) en lugar de dibujar millones de puntos.

## Procedimiento para publicar

1. **Probar** en WordPress y en los dispositivos (PC y móvil, vertical y horizontal, pantalla completa).
2. **Leer la versión actual** en `siarhe-dataviz/siarhe-dataviz.php`. Está declarada **dos veces** (cabecera `Version:` y constante `SIARHE_VERSION`) y debe coincidir; el número puede haberse ajustado a mano.
3. **Cambiar ambas** con `sed` (no con `Set-Content` de PowerShell, que agrega un BOM). Comprobar que el archivo empieza con los bytes `3c3f70` (`<?p`).
4. **Verificar:** `node --check` de cada `.js`; no hay PHP en el equipo de desarrollo, así que se revisa el balance de llaves y se prueba en el sitio.
5. **Commit** cuyo mensaje empieza con la versión (`0.1.0.0 …`) y push a `main`.
6. **Empaquetar** (opcional): `.\build.ps1` desde la raíz del repositorio; lee la versión de `SIARHE_VERSION` y genera `builds\…zip` (la carpeta y el script no se suben al repositorio).
7. En el sitio: actualizar el plugin, **entrar a una pantalla del panel** (crea el `.htaccess` y las copias de lectura que falten), **vaciar la caché de LiteSpeed y la de Cloudflare**, y revisar el Dashboard (compresión de los datos, salud del sistema).
8. Agregar la entrada a [`CHANGELOG.md`](../CHANGELOG.md).

## Convenciones

- El mensaje de commit empieza con la versión y resume el cambio en una línea.
- No se nombran carpetas `vendor/` (git las ignora y `build.ps1` las excluye del zip): las librerías locales están en `public/js/libs/` y `admin/libs/`.
- Los datos reales (`*.csv`, `*.json`, `*.geojson`, `*.xlsx`) **no se versionan** (`.gitignore`); las muestras de trabajo van en `gitignore/` (carpeta ignorada).
