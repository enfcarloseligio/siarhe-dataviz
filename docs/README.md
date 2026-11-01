# Documentación de SIARHE Data Visualization Engine

Documentación técnica y de decisiones del plugin. Vive en la raíz del repositorio (fuera de la carpeta del plugin, así que **no viaja en el `.zip`** que se instala en WordPress).

| Documento | Para qué sirve |
|---|---|
| [requisitos.md](requisitos.md) | Versiones mínimas de PHP y WordPress, extensiones, servidor y navegadores |
| [arquitectura.md](arquitectura.md) | Cómo está organizado el plugin: backend, frontend, datos y capas del mapa |
| [administracion.md](administracion.md) | Pantallas del panel (Dashboard, Carga de Datos, Configuración, Personalizar) |
| [metricas-y-marcadores.md](metricas-y-marcadores.md) | **Guía campo por campo** para agregar métricas y marcadores nuevos y qué hace cada cosa en el panel |
| [formatos-de-datos.md](formatos-de-datos.md) | Reglas de los CSV y GeoJSON que se suben, columnas obligatorias y plantillas |
| [rendimiento-mapa.md](rendimiento-mapa.md) | Optimizaciones del mapa (canvas, workers, copias de lectura), números medidos y banderas de diagnóstico |
| [decisiones.md](decisiones.md) | Decisiones de diseño importantes y su porqué |
| [versiones-y-publicacion.md](versiones-y-publicacion.md) | Hitos de versión (0.1.0.0, 0.5.0.0, 1.0) y el procedimiento para publicar |
| [pruebas.md](pruebas.md) | Cómo se ha verificado el código y qué falta |

Otros archivos útiles en la raíz: [`README.md`](../README.md) (presentación), [`CHANGELOG.md`](../CHANGELOG.md) (historial de versiones), [`CLAUDE.md`](../CLAUDE.md) (guía para trabajar el código con Claude Code) y [`LICENSE`](../LICENSE) (GPL v2).

## Cómo mantener esta documentación

- Cuando se toma una decisión que no es evidente leyendo el código, se agrega a [decisiones.md](decisiones.md) con su motivo.
- Cuando cambia un formato de archivo o una columna obligatoria, se actualiza [formatos-de-datos.md](formatos-de-datos.md) (la fuente de verdad en código es `includes/class-siarhe-columns.php`).
- Cada publicación agrega una entrada a [`CHANGELOG.md`](../CHANGELOG.md).
- Los números de rendimiento se anotan con su fecha y el archivo con el que se midieron.
