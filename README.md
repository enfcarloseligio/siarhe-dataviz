# SIARHE Data Visualization Engine

Plugin de WordPress para visualizar datos de recursos humanos en enfermería de México (SIARHE): **mapas coropléticos y tablas interactivas** (D3.js) con marcadores de establecimientos, administración de los archivos de datos y herramientas de validación.

> **Estado:** versión `0.1.0.0` — la parte estática del frontend está terminada. Siguen la carga de bases grandes a SQL (`0.5.0.0`) y el mapa interactivo dinámico (`1.0`). Ver [hitos de versión](docs/versiones-y-publicacion.md).

## Qué hace hoy

- **Mapas y tablas por shortcode:** `[siarhe_mapa_{M|T|MT}_CVE_ENT_{nn}]` (M = mapa, T = tabla, MT = ambos; `33` = nacional) para cada entidad. Vista por entidades o por municipios/localidades.
- **Marcadores** de posición, de densidad (burbujas) y espinas (*spike*) con decenas de miles de puntos, tooltips, medición de distancias y exportación a PNG.
- **Personalización:** colores del mapa y de los botones desde el panel; el visitante puede cambiar los colores de su vista.
- **Administración:** Dashboard con salud del sistema, carga de GeoJSON, localidades, bases estáticas (CSV) y marcadores con validación, vista previa y progreso; **plantillas y diccionario de variables** descargables; **copias de seguridad** con historial y restauración.
- **Rendimiento:** marcadores en canvas, lectura de CSV y de la capa de municipios en *Web Workers*, niveles de detalle y entrega comprimida de los datos. Ver [rendimiento del mapa](docs/rendimiento-mapa.md).
- **Accesibilidad:** operación por teclado del mapa, avisos y leyendas legibles; la tabla es la alternativa accesible a los marcadores.

## Requisitos

| | Mínimo |
|---|---|
| WordPress | **6.0** |
| PHP | **7.4** (probado en 8.2) |
| MySQL | 5.6 |

Extensión `mbstring` requerida; `zlib` y `zip` opcionales. Detalle y notas de servidor en [docs/requisitos.md](docs/requisitos.md).

## Instalación

1. Descarga el archivo `.zip` del plugin (la carpeta `siarhe-dataviz/` de este repositorio).
2. Súbelo desde *Plugins › Añadir nuevo › Subir plugin*, o extráelo en `wp-content/plugins/`.
3. Actívalo y abre el menú **SIARHE**.
4. En **Carga de Datos** sube el GeoJSON, la base estática y los marcadores (descarga las plantillas desde cada pestaña) y copia el shortcode desde el **Dashboard**.

## Documentación

Todo está en la carpeta [`docs/`](docs/README.md): [arquitectura](docs/arquitectura.md), [administración](docs/administracion.md), [formatos de datos](docs/formatos-de-datos.md), [rendimiento](docs/rendimiento-mapa.md), [decisiones de diseño](docs/decisiones.md), [publicación](docs/versiones-y-publicacion.md) y [pruebas](docs/pruebas.md). El historial de cambios está en [CHANGELOG.md](CHANGELOG.md).

## Hoja de ruta

| Versión | Hito |
|---|---|
| `0.1.0.0` | Frontend estático terminado y optimizado ✅ |
| `0.5.0.0` | Cargas a SQL de pivotes, formaciones, investigación, escuelas y carreras (Gestor de Bases) |
| `1.0` | Mapa interactivo dinámico respaldado por SQL |

## Autor y licencia

Desarrollado por **Juan Carlos De La Cruz Eligio** — [@enfcarloseligio](https://github.com/enfcarloseligio) · [enfcarloseligio.com](https://enfcarloseligio.com/).

Licencia **GPL-2.0-or-later** — ver [LICENSE](LICENSE) y <https://www.gnu.org/licenses/gpl-2.0.html>.
