# Formatos de datos

La fuente de verdad en código es `includes/class-siarhe-columns.php` (alimenta las plantillas y el diccionario) y `validate_csv_file()` en `admin/class-siarhe-admin.php` (valida al subir). Las plantillas y el diccionario se descargan desde **Carga de Datos** en cada pestaña.

## Reglas generales de los CSV

- Codificación **UTF-8** (con o sin BOM; el plugin normaliza). Si llega en ISO-8859-1 se convierte.
- Separador **coma**. Si el archivo usa `;` o tabulador se rechaza con un mensaje claro.
- Decimales con **punto**, sin separador de miles.
- Claves como **texto con ceros iniciales**: `CVE_ENT` `01`, `CVE_MUN` `001`, `CVE_LOC` `0001`.
- Datos faltantes: celda vacía o `S/D`.
- La primera fila es el encabezado; los nombres se escriben exactamente como en la plantilla.

## Base estática (una por entidad)

Nombre del archivo en el servidor: `{entidad}.csv` (por ejemplo `oaxaca.csv`, `republica-mexicana.csv`).

| Columna | Nacional | Municipal | Notas |
|---|---|---|---|
| `CVE_ENT` | **Obligatoria** | Recomendada | 2 dígitos; `33` = total nacional |
| `CVE_MUN` | — | **Obligatoria** | 3 dígitos; une la fila con su polígono |
| `estado` / `municipio` | **Obligatoria** (`estado`) | **Obligatoria** (`municipio`) | También se acepta `Entidad` |
| `población` | **Obligatoria** | **Obligatoria** | Cualquier encabezado que empiece con `pob` |
| `tasa_total`, `enfermeras_total` | **Obligatorias** | **Obligatorias** | **Sin ellas el mapa no muestra datos**; la carga se rechaza |
| Demás métricas nativas | Recomendadas | Recomendadas | Si faltan, avisa y se muestran sin datos |
| Métricas propias | Opcionales | Opcionales | Mismo nombre que su clave en *Configuración › Métricas* |

Métricas nativas (pares absoluto/tasa): total, primer, segundo y tercer nivel de atención, apoyo, administrativas (unidades administrativas), escuelas (establecimientos formadores de recursos humanos en enfermería), otros y no asignado (establecimientos con tipo de establecimiento no asignado). Sus descripciones viven en `siarhe_get_default_metricas()` (`includes/helpers.php`).

## Marcadores (un CSV por variable)

| Columna | Nivel | Notas |
|---|---|---|
| `LATITUD`, `LONGITUD` | **Obligatorias** | Grados decimales WGS84; también `lat`/`lon`. Las filas sin coordenadas o con 0 no se dibujan; si más del 20 % cae fuera de México se avisa al subir |
| `CVE_ENT`, `ENTIDAD`, `CVE_MUN`, `MUNICIPIO`, `CVE_LOC`, `LOCALIDAD` | **Obligatorias** | Todas se validan. En el mapa de una entidad **solo se dibujan los puntos de esa entidad**; una fila sin `CVE_ENT` se avisa y **no se dibuja en ningún mapa** |
| `VALOR_MARCADOR_1`, `VALOR_MARCADOR_2`… | Opcionales | Datos propios del marcador: se les pone el nombre que se configure en *Configuración › Marcadores* (densidad, filtro, agrupación, reglas de tooltip) |

Tipos de marcador: **posición** (símbolo fijo), **espectro** (burbuja con área proporcional al valor) y **spike** (espina cuya altura y color siguen el valor). Los de densidad comparten la columna numérica configurada o, si no hay, el conteo de filas agrupadas.

Los campos que el mapa reconoce de forma opcional para el tooltip (`CLUES`, `NOMBRE_UNIDAD`, `INSTITUCION`, `NIVEL_ATENCION`, `JURISDICCION`, etc.) se leen si existen, pero no se piden.

## GeoJSON

- **Proyección:** WGS84 (EPSG:4326), obligatoria para que alinee con las coordenadas.
- **Entidades** (`geojson/{entidad}.geojson`): propiedades `CVE_ENT` (y `CVEGEO`, `NOMGEO`). El nacional lleva los 32 estados.
- **Municipios / localidades** (`localidades/{entidad}.geojson`): propiedades `CVE_ENT`, `CVE_MUN`, `NOMGEO`. Es la capa de la *Vista geográfica* detallada.
- Referencia de peso real: el GeoJSON nacional de entidades pesa ~7 MB (330 mil vértices) y el de municipios ~37 MB (2,469 polígonos, 1.78 millones de vértices); ver [rendimiento-mapa.md](rendimiento-mapa.md).

## Copias de lectura

Los archivos de **100 KB o más** tienen junto a ellos una copia idéntica con extensión `.json` (GeoJSON) o `.txt` (CSV) que es la que lee el mapa. **El archivo original no cambia** y es el que se respalda, versiona y descarga. Los menores de 100 KB no la tienen.

## Futuras bases a SQL (0.5.0.0)

Pivotes, formaciones, escuelas, investigación y carreras (archivos de más de 100 MB) se convertirán en tablas SQL. Sus reglas se definirán a partir de los archivos crudos de INEGI y del proyecto hermano (RHEMX) y se documentarán aquí.
