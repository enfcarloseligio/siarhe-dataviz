# Formatos de datos

La fuente de verdad en código es `includes/class-siarhe-columns.php` (alimenta las plantillas y el diccionario) y `validate_csv_file()` en `admin/class-siarhe-admin.php` (valida al subir). Las plantillas y el diccionario se descargan desde **Carga de Datos** en cada pestaña.

## Reglas generales de los CSV

- Codificación **UTF-8** (con o sin BOM; el plugin normaliza). Si llega en ISO-8859-1 se convierte.
- Separador **coma**. Si el archivo usa `;` o tabulador se rechaza con un mensaje claro.
- Decimales con **punto**, sin separador de miles.
- Claves como **texto con ceros iniciales**: `CVE_ENT` `01`, `CVE_MUN` `001`, `CVE_LOC` `0001`.
- Datos faltantes: celda vacía o `S/D`.
- La primera fila es el encabezado; los nombres se escriben exactamente como en la plantilla.

## Base estática (una por entidad, año, tema y unidad)

Cada base se identifica por **entidad + año + tema + unidad**. El año se elige en un selector (desde 2006, el primer año con población: el censo de 2005 cubre 2006 a 2009). El tema y sus unidades:

| Tema | Unidades de conteo | Qué exige el archivo |
|---|---|---|
| *Enfermería* (`enfermeria`) | *plazas* de enfermería o *recursos humanos de enfermería* (`personas`) | Identificador, nombre, población, `enfermeras_total` y `tasa_total` |
| *Clínicas de catéteres* (`clinicas_cateteres`) y *Clínicas de heridas* (`clinicas_heridas`) | *tasa* o *frecuencia* | Identificador y nombre |
| Temas propios (los crea el administrador) | Las que se elijan al crearlo: frecuencia, tasa, plazas, recursos humanos | Identificador y nombre |

El mapa público muestra las bases de Enfermería (`siarhe_base_tema_mapa()`).

**La entidad se comprueba.** Al subir, el plugin lee la `CVE_ENT` de las filas (sin las de totales, 8888 y 9999) y rechaza el archivo si es de otra entidad, si es estatal y se eligió República Mexicana o si es nacional y se eligió una entidad (`Siarhe_Validador::revisa_base`). En Extranjero no se comprueba. Subir otra base de la misma combinación la reemplaza y guarda copia de la anterior. Las bases que ya estaban cargadas cuando se agregó el selector (versión 0.1.2.0) quedaron como del año 2026 y en plazas.

Nombre de los archivos en el servidor: `{entidad}-{año}-{unidad}.csv` y `.json` en Enfermería (por ejemplo `oaxaca-2026-plazas.csv`, `republica-mexicana-2026-personas.json`) y `{entidad}-{año}-{tema}-{unidad}` en los demás temas (`oaxaca-2025-clinicas-cateteres-tasa.csv`).

### CSV o JSON: se acepta cualquiera y siempre existen los dos

Se puede subir el CSV o el JSON optimizado que genera RHEMX (`data/processed/mapas/<año>/json/` para plazas y `json_personas/` para personas, con los mismos nombres que los CSV). El plugin valida lo que se sube y crea el otro formato (`Siarhe_Static_Json`), de modo que los dos son siempre equivalentes: el CSV queda para descargarlo o editarlo en Excel y el JSON, que pesa casi la mitad, es el que lee el mapa.

El JSON (`formato: "siarhe-estatico"`, `version: 1`) lleva las mismas columnas que el CSV, pero no repite las tasas que se pueden calcular: cada `tasa_<x>` que es exactamente `enfermeras_<x> / población × 1,000` se declara en `tasas` y el navegador la calcula.

```json
{
  "formato": "siarhe-estatico", "version": 1, "anio": 2026, "unidad": "plazas",
  "columnas": ["id", "CVE_ENT", "estado", "población", "enfermeras_total", "..."],
  "tasas": { "tasa_total": ["enfermeras_total", 1000] },
  "sin_poblacion": 0,
  "filas": [[1, "01", "Aguascalientes", 1534416, 5328, "..."]]
}
```

- Las columnas de identificación (`id`, `CVE_ENT`, `CVE_MUN`, `CVE_LOC`, `MAPA`, `estado`, `entidad`, `municipio`, `localidad`) y toda columna con texto se guardan como texto, con sus ceros iniciales; el resto son números, o `null` si la celda está vacía o dice `S/D`.
- `sin_poblacion` es el valor de las tasas calculadas en las filas sin población (0 en «No Disponible»).
- Si una tasa no coincide con su fórmula (por ejemplo una columna calculada de otra manera), no se declara en `tasas` y se conserva como columna.
- Al subir un JSON, `anio` y, en Enfermería, `unidad` deben coincidir con lo elegido en el formulario; si no, se rechaza. El JSON se convierte a CSV (con las tasas redondeadas a 9 decimales) y ese CSV pasa por las mismas validaciones que cualquier otro.
- El mismo algoritmo vive en Python (`src/estatico_json.py` de RHEMX) y en PHP (`includes/class-siarhe-static-json.php`); se comprobó con las 792 bases de RHEMX (12 años × 2 unidades × 33 archivos) que el JSON de ambos es equivalente y que JSON → CSV recupera cada celda.

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

## Marcadores (un archivo por tipo y periodo)

Cada tipo de marcador es una familia de archivos, **uno por periodo**: un año de datos o **Todas** (`total`: las unidades de toda la historia, que han existido o reportado al menos una vez).

| Tipo (en este orden) | Periodos |
|---|---|
| *Establecimientos de Salud* | Por año |
| *Clínicas de Catéteres*, *Clínicas de Heridas* | «Todas» y por año |
| *Pasantes de Enfermería* | Por año |
| Variables que agrega el administrador | «Todas» y por año |

**Versión por entidad.** Se carga un solo archivo con todo el país. Al guardarlo, el plugin crea una capa por entidad (`markers/entidades/{nombre}-{periodo}-entNN.json`, mismo formato, con las listas de textos reducidas a los que usa esa entidad) y el mapa de cada estado descarga solo la suya; el mapa nacional lee la capa completa (`Siarhe_Marker_Json::divide_por_entidad`). Los puntos sin clave de entidad solo quedan en la capa completa. El mapa usa el archivo del año que el visitante ve; si no existe, el de toda la historia; si tampoco, el del año anterior más cercano. Se guardan en `markers/` como `{nombre del tipo}-{periodo}.csv` y `.json` (`clinicas-cateteres-2025.json`, `clinicas-heridas-total.json`).

### CSV o JSON: se acepta cualquiera y siempre existen los dos

Igual que en las bases estáticas: el archivo que se sube se valida y de él se obtiene el otro (`Siarhe_Marker_Json`). El mapa lee el JSON; si no se pudo crear, lee el CSV.

**CSV**

| Columna | Nivel | Notas |
|---|---|---|
| `LATITUD`, `LONGITUD` | **Obligatorias** | Grados decimales WGS84; también `lat`/`lon`. Las filas sin coordenadas o con 0 no se dibujan; si más del 20 % cae fuera de México se avisa al subir |
| `CVE_ENT` o `CLAVE DE LA ENTIDAD` | **Obligatoria** | En el mapa de una entidad **solo se dibujan los puntos de esa entidad**; una fila sin clave se avisa y **no se dibuja en ningún mapa** estatal |
| `ENTIDAD`, `CVE_MUN`, `MUNICIPIO`, `CVE_LOC`, `LOCALIDAD` | Recomendadas | Si faltan se avisa: los puntos se dibujan, pero no hay resumen por municipio |
| `VALOR_MARCADOR_1`, `VALOR_MARCADOR_2`… | Opcionales | Datos propios del marcador: se les pone el nombre que se configure en *Configuración › Marcadores* (densidad, filtro, agrupación, reglas de tooltip) |

Se aceptan los encabezados del catálogo CLUES (`clues_<año>.csv` de RHEMX): `NOMBRE DE LA UNIDAD`, `NOMBRE DE LA INSTITUCION`, `NIVEL ATENCION`, `NOMBRE DE TIPOLOGIA`, `NOMBRE TIPO ESTABLECIMIENTO`, `JURISDICCION`… Los nombres de columna se comparan sin distinguir mayúsculas, acentos ni guion bajo.

**JSON (formato `siarhe-marcadores`, el de las capas de RHEMX)**

```json
{
  "formato": "siarhe-marcadores", "version": 1, "periodo": "2026",
  "fila": ["latitud", "longitud", "clues", "nombre", "institucion", "nivel", "tipologia", "entidad", "estrato", "tipo_estab", "jurisdiccion", "municipio", "localidad", "plazas", "personas", "anterior"],
  "instituciones": ["…"], "niveles": ["No Aplica", "Primer Nivel", "Segundo Nivel", "Tercer Nivel"], "tipologias": ["…"],
  "estratos": ["Urbana", "Rural"], "tipos_establecimiento": ["…"], "jurisdicciones": ["…"], "municipios": ["…"], "localidades": ["…"],
  "unidades": [[21.88213, -102.30027, "ASCIJ000012", "Cij Aguascalientes", 0, 1, 14, 1, 0, 2, 4, 35, 191, 0, 0]]
}
```

- `fila` dice qué campo ocupa cada posición de una fila de `unidades`.
- Los campos `institucion`, `nivel`, `tipologia`, `estrato`, `tipo_estab`, `jurisdiccion`, `municipio` y `localidad` guardan la **posición** del texto en su lista (-1 = sin dato); `entidad` es la clave de la entidad como número.
- Cualquier otro campo (plazas, reportes, conteos de densidad…) va tal cual. Una fila puede omitir sus últimos campos si están vacíos.
- Los archivos de RHEMX se suben sin cambios (`unidades_clues_<año>.json`, `clinicas_cateteres_<año>.json`, `clinicas_cateteres_historico.json`, `clinicas_heridas_historico.json`); no llevan `formato`, se reconocen por `fila` y `unidades`. Un JSON creado desde un CSV agrega `columnas` (el encabezado original de cada campo) para que el CSV y el JSON den exactamente las mismas columnas.

Tipos de marcador: **posición** (símbolo fijo), **espectro** (burbuja con área proporcional al valor) y **spike** (espina cuya altura y color siguen el valor). Los de densidad comparten la columna numérica configurada o, si no hay, el conteo de filas agrupadas.

**Marcadores del sistema:** los establecimientos de salud por nivel de atención (`ESTAB_1`, `ESTAB_2`, `ESTAB_3`, `ESTAB_6`, `ESTAB_NE`) leen el tipo *Establecimientos de Salud*, filtran `nivel_atencion` por *Primer Nivel*, *Segundo Nivel*, *Tercer Nivel*, *No Aplica* y *No Especificado* (los textos de RHEMX) y solo permiten cambiar su visibilidad (`siarhe_get_default_marcadores()`).

## Geometrías (TopoJSON por marco geoestadístico)

El formato de carga es el **TopoJSON que exporta RHEMX** (`python src/geo_mapas.py`; copia lista para el plugin en `data/processed/geo/<marco>/`, con un `LEEME.txt`). Hay un archivo por **capa, entidad y marco**, guardado como `{entidad}-{marco}.json`.

| Pestaña | Capa | República Mexicana | Cada entidad | Clave `c` |
|---|---|---|---|---|
| *Geolocalización* (`geojson/`) | Mapa base | `33_entidades.json` | `NN_municipios.json` | 2 dígitos / 5 dígitos |
| *Localidades (Geo)* (`localidades/`) | Capa detallada | `33_municipios.json` | `NN_localidades.json` | 5 dígitos / 9 dígitos |

- **Estructura:** `"type": "Topology"`, objeto `datos` cuyas geometrías llevan `c` (clave), `n` (nombre) y, en las localidades, `t` (`u` urbana, `r` rural). Coordenadas en longitud/latitud **WGS84 (EPSG:4326)**. La clave une cada polígono con su fila de la base estática (`CVE_ENT`, o `CVE_MUN` = los tres últimos dígitos de la clave del municipio).
- **Marco:** quinquenal (2005, 2010, 2015, 2020, 2025; 2030 cuando llegue). Cada marco dibuja los datos de sus cinco años; si el del año no está cargado se usa el anterior más cercano. El marco de 2016 se carga en 2015 y su fecha real va en *Fecha de corte*.
- **Validación:** al subir se comprueba la capa (por los dígitos de la clave) y la entidad (por los dos primeros). Un archivo de otra capa, de otra entidad o de AGEB se rechaza.
- **GeoJSON anteriores:** los `FeatureCollection` ya cargados (`{entidad}.geojson`, propiedades `CVE_ENT`, `CVE_MUN`, `NOMGEO`) se siguen leyendo y quedaron en el marco 2025.
- Referencia de peso: el TopoJSON nacional de entidades pesa ~0.55 MB y el de municipios ~2.1 MB (antes, en GeoJSON, ~7 MB y ~37 MB); ver [rendimiento-mapa.md](rendimiento-mapa.md).

## Copias de lectura

Los archivos `.geojson` y `.csv` de **100 KB o más** tienen junto a ellos una copia idéntica con extensión `.json` (GeoJSON) o `.txt` (CSV) que es la que lee el mapa (los `.json` de geometrías, bases y marcadores no la necesitan: el servidor ya los comprime). **El archivo original no cambia** y es el que se respalda, versiona y descarga. Los menores de 100 KB no la tienen.

## Futuras bases a SQL (0.5.0.0)

Pivotes, formaciones, escuelas, investigación y carreras (archivos de más de 100 MB) se convertirán en tablas SQL. Sus reglas se definirán a partir de los archivos crudos de INEGI y del proyecto hermano (RHEMX) y se documentarán aquí.
