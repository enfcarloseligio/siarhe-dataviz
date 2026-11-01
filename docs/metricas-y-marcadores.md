# Métricas y marcadores: cómo se configuran y qué hace cada campo

Guía de las dos configuraciones que más se usan para ampliar el mapa: **métricas** (los indicadores que colorean el mapa y llenan la tabla) y **marcadores** (los puntos que se dibujan encima). Todo se hace desde el panel de WordPress, menú **SIARHE**. Las dos pantallas guardan **al instante** al pulsar *Guardar Cambios* en su ventana.

---

# 1. Métricas

**Dónde:** SIARHE › Configuración › **Métricas (CSV)**.

Una métrica es una **columna numérica de la base estática** (el CSV de cada entidad) que se puede elegir como indicador. La **clave de la métrica es el nombre exacto de la columna del CSV**.

## Qué hace cada métrica en el sitio

| Dónde se ve | Qué campo usa |
|---|---|
| Selector **Indicador Principal** (mapa y tabla) | *Etiqueta larga* |
| Título de la leyenda del mapa | *Etiqueta abreviada* (si no hay, la corta) |
| Encabezados y etiquetas de los tooltips | *Etiqueta corta* |
| Pantallas muy pequeñas (móvil) | *Etiqueta abreviada* |
| Título del Mapa PNG | *Etiqueta larga* |
| Colores del mapa | *Escala* y, si se activan, *rangos* y *colores* propios |
| Quién la puede elegir | *Visibilidad* |
| Columnas **Enfermeras** y **Tasa** de la tabla | El **par** (absoluto + tasa) de la métrica elegida |

## Tabla de métricas (lista)

Muestra clave, etiquetas, tipo y escala, auditoría (quién la creó y quién la editó por última vez) y acciones: un **indicador de visibilidad** (ojo azul = público, usuarios naranja = privado, ojo tachado = oculto; solo informa, se cambia al editar), el botón de **editar** y el de **eliminar**. Las **nativas** llevan una insignia *Nativa* y un **candado** en lugar del botón de eliminar. Tiene buscador, paginación y orden por columna.

Botones de la parte superior:

- **Añadir Nueva:** abre la ventana de alta.
- **Restaurar Iniciales:** **borra todas las métricas propias** y deja el catálogo de fábrica (las nativas con sus valores originales). Pide confirmación y no se puede deshacer.

## Ventana de alta o edición, campo por campo

| Campo | Qué hace |
|---|---|
| **Clave (columna CSV)** | Nombre **exacto** de la columna del CSV (respeta mayúsculas y minúsculas). Solo letras, números y guion bajo (lo demás se cambia por `_`). No se puede repetir. En las nativas está bloqueada; en las propias se puede renombrar (la anterior se elimina). Si la columna no existe en un CSV, la métrica se muestra sin datos y la carga avisa. |
| **Etiqueta larga** | Nombre completo: aparece en el selector, en descripciones y en el título del PNG. |
| **Etiqueta corta** | Nombre breve para encabezados de tabla, leyenda y tooltips. |
| **Etiqueta abreviada** | Versión mínima para pantallas muy pequeñas. |
| **Tipo de valor** | **Tasa** (decimal, por ejemplo por mil habitantes) o **Absoluto** (conteo entero). Cambia el formato con el que se muestra. En las nativas está bloqueado. |
| **Visibilidad** | **Público** (cualquier visitante), **Privado** (solo usuarios con sesión iniciada) u **Oculto** (no aparece en el sitio). Para `tasa_total`, `enfermeras_total` y `poblacion` está **bloqueada en Público**: sin ellas el mapa no funciona. |
| **Clave relacionada (par)** | Ver la sección siguiente. |
| **Escala de mapa por defecto** | Cómo se colorea el mapa al elegir esta métrica: **Degradado por colores** (cuartiles calculados con los datos), **Rangos** (4 rangos) o **Escala monocromática** (un solo color en degradado). El visitante puede cambiarla después con *Estilo de Mapa*. |
| **Opciones avanzadas** | Dos casillas: *rangos de corte propios* y *colores propios* para esta métrica (en lugar de los generales de Personalizar). |
| **Valores de corte** | Solo con rangos propios: límites de cada uno de los 4 rangos. |
| **Colores de nivel** | Solo con colores propios: color de cada uno de los 4 rangos. |

### Par absoluto / tasa (la "clave relacionada")

La *clave relacionada* **no es una columna del CSV**: es un **nombre de grupo** (un "gafete") que comparten dos métricas complementarias, una **absoluta** y su **tasa**. El sistema usa el grupo para mostrar juntos el conteo y la tasa en la tabla (**Enfermeras** y **Tasa**) y en el tooltip.

- Las dos métricas del par llevan el **mismo valor** en este campo. Por convención se usa la clave de la métrica absoluta. Ejemplo: `enfermeras_total` (absoluto) y `tasa_total` (tasa) comparten `enfermeras_total`.
- Cada grupo admite **a lo sumo una absoluta y una tasa**. Si intentas guardar otra del mismo tipo en el mismo grupo, el panel lo impide y dice cuál ya existe.
- Una métrica sin par se comporta como su propio grupo.
- En las nativas está bloqueada.

### Escalas y rangos

- **Degradado por colores:** 5 colores sobre los cuartiles (mínimo, Q1, Q2, Q3, máximo). Colores en *Personalizar › Mapa y Colores › Mapa de Calor › Gradiente Cuartiles*.
- **Escala monocromática:** de un color claro a uno oscuro (*Personalizar › Mapa y Colores › Gradiente Monocromático*).
- **Rangos:** 4 rangos con color propio (colores generales en *Gradiente por Rangos*). Por defecto son `Vmin–Q1`, `Q1–Q2`, `Q2–Q3`, `Q3–Vmax`. Con *rangos propios* cada rango tiene **límite inferior, operador (`<`, `<=`, `=`) y límite superior**, y cada límite puede ser:
  - un **comodín**: `Vmin`, `Q1`, `Q2`, `Q3`, `Vmax`, que se calculan con los datos cargados (solo valores positivos; se excluyen las filas de totales), o
  - un **número fijo**.
  El primer límite inferior vacío se guarda como `Vmin` y el último superior vacío como `Vmax`.
- **Valor 0** y **sin dato (S/D)** tienen sus propios colores generales (*Personalizar › Mapa y Colores › Casos Especiales*).

## Cómo agregar una métrica nueva

1. Asegúrate de que la **columna exista en el CSV** de la base estática (y en la plantilla, ver [formatos-de-datos.md](formatos-de-datos.md)). Una métrica y su tasa son dos columnas.
2. *Configuración › Métricas (CSV)* → **Añadir Nueva**.
3. Escribe la **clave** (nombre exacto de la columna), las **tres etiquetas**, el **tipo** y la **visibilidad**.
4. Si tiene tasa o absoluto complementario, ponle a ambas el **mismo par**.
5. Elige la **escala por defecto**; si quieres cortes o colores especiales, marca las opciones avanzadas.
6. **Guardar Cambios** y vuelve a cargar la base estática si el CSV no tenía la columna.
7. Comprueba en un mapa: debe aparecer en *Indicador Principal*.

**Ejemplo:** pasantes por beca A.

| Métrica | Clave | Tipo | Par |
|---|---|---|---|
| Pasantes beca A | `pss_a` | Absoluto | `pss_a` |
| Tasa de pasantes beca A | `tasa_pss_a` | Tasa | `pss_a` |

## Eliminar una métrica

Solo las propias. **No toca el CSV**: solo oculta esa información en el mapa. Las nativas no se pueden eliminar (se pueden ocultar de la vista editando su visibilidad, salvo las tres bloqueadas).

---

# 2. Marcadores

Un marcador tiene **dos piezas separadas** que se unen por el nombre del archivo:

1. La **variable** (la "ranura" del archivo CSV): *Carga de Datos › Marcadores*.
2. La **configuración** (cómo se dibuja y se comporta): *Configuración › Marcadores (CSV)*.

(Guardadas en las opciones `siarhe_archivos_marcadores` y `siarhe_marcadores_config`.)

## Paso 1 — Variable y archivo (Carga de Datos › Marcadores)

**Incorporar Nueva Variable (CSV):** registra una ranura **antes** de subir su archivo.

| Campo | Qué hace |
|---|---|
| **Clave interna** | Identificador de la variable (se convierte a MAYÚSCULAS y los espacios a `_`). No se puede cambiar después sin recrear la variable. |
| **Etiqueta pública** | Nombre que verán los visitantes. |
| **Nombre del archivo** | Nombre del CSV en el servidor (se pasa a minúsculas, espacios a `-` y se agrega `.csv`). |

Las variables de fábrica son **Catéteres** (`clinicas-cateteres.csv`), **Heridas** (`clinicas-heridas.csv`) y **Establecimientos de Salud** (`establecimientos-salud.csv`). **Restaurar Iniciales** borra todas las variables propias **y sus CSV del servidor**.

Después, **sube el CSV** con *Tipo de Marcador* = la variable. Debe traer `LATITUD`, `LONGITUD`, `CVE_ENT`, `ENTIDAD`, `CVE_MUN`, `MUNICIPIO`, `CVE_LOC` y `LOCALIDAD` (ver [formatos-de-datos.md](formatos-de-datos.md); hay plantilla y diccionario descargables). Cada subida guarda una copia del archivo anterior. Eliminar una variable propia borra su CSV y su registro.

## Paso 2 — Configuración (Configuración › Marcadores)

**Añadir Nueva** abre la ventana de configuración. **Restaurar Iniciales** vuelve a los marcadores de fábrica.

| Campo | Qué hace |
|---|---|
| **Clave interna** | Identificador del marcador **en la configuración** (respeta mayúsculas; letras, números y `_`; no se repite). Con ella se nombran sus estilos. |
| **Etiqueta pública** | Nombre en los selectores y en la leyenda. |
| **Archivo fuente (CSV)** | CSV ya cargado en el Paso 1 que alimenta este marcador. Un mismo archivo puede alimentar **varios** marcadores con filtros distintos. |
| **Tipo de visualización** | **Posición** (ícono fijo; permite medir distancias), **Espectro** (burbuja cuyo tamaño depende de un valor) o **Spike** (espina cuya altura y color dependen de un valor). |
| **Lógica de filtrado** (opcional) | **Columna** y **valor** que debe tener cada fila para pertenecer a este marcador (se comparan sin distinguir mayúsculas). Sirve cuando un CSV mezcla categorías. Ejemplo: `NIVEL_ATENCION` = `PRIMER NIVEL` para el marcador "Establecimientos (1er Nivel)". |
| **Configuración de densidad** (Espectro y Spike) | **Clave relacionada (par)**: clave de un *indicador* (por ejemplo `tasa_total`). Al elegir ese indicador, el mapa **sugiere activar** este marcador con un aviso. **Modo de cálculo**: "valor absoluto" (la opción "tasa relativa" está marcada *próximamente*). **Columna numérica**: de ahí salen el tamaño de la burbuja o la altura y el color de la espina; **si se deja vacía se usa el número de filas agrupadas**. |
| **Agrupación y tooltip** (bases nominales) | **Agrupar puntos por columna** (por ejemplo `CLUES`): varias filas del mismo punto se juntan en un solo marcador. **Reglas de conteo** (hasta 5): cada una con *etiqueta*, *columna* y *valor*; el tooltip muestra cuántas filas del punto cumplen cada regla (por ejemplo "Graves": `gravedad` = `grave`). |
| **Resumen geográfico** | Si se activa, el tooltip del **municipio o estado** suma los valores de este marcador dentro de su territorio (puntos físicos, total de registros y conteos de las reglas). |
| **Visibilidad** | **Público**, **Privado** (solo usuarios con sesión) u **Oculto**. |

## Paso 3 — Estilo (Personalizar › Mapa y Colores › Estilos dinámicos de marcadores)

Cada marcador aparece con una **vista previa** y estos ajustes, guardados como `m_{clave en minúsculas}_…`:

| Ajuste | Aplica a | Notas |
|---|---|---|
| **Forma** | Posición | Círculo, cuadrado, triángulo, rombo, estrella o cruz. Espectro es siempre círculo y spike siempre triángulo |
| **Relleno** | Todos | Espectro: por defecto rojo semitransparente. Spike: es el color del pico **más alto** |
| **Color del pico más bajo** (opcional) | Spike | Si se deja vacío, los picos bajos usan una versión clara del color alto |
| **Borde** | Todos | Se dibuja fino (0.4–1 px) |

## Paso 4 — Tooltip (Configuración › Tooltips)

Define qué datos aparecen en la tarjeta de un marcador, su **orden** (arrastrando), un dato **destacado** con su color, y los colores y la opacidad de la tarjeta. Los campos se leen de estas columnas del CSV **si existen** (ninguna es obligatoria):

| Dato del tooltip | Columna(s) que lee |
|---|---|
| Nombre (título) | `NOMBRE_UNIDAD` (o `nombre de la unidad` / `unidad`) |
| Institución | `INSTITUCION` (o `CLAVE_INSTITUCION`) |
| CLUES | `CLUES` (se muestra **tal cual**, sin cambiar mayúsculas) |
| Tipo | `TIPO_ESTABLECIMIENTO` + `NOMBRE_TIPOLOGIA` |
| Nivel | `NIVEL_ATENCION` |
| Jurisdicción | `JURISDICCION` |
| Ubicación | `ENTIDAD`, `MUNICIPIO` y `LOCALIDAD` |
| Zona | `ESTRATO_UNIDAD` (rural / urbana) |

El resto del texto se muestra en formato "Nombre Propio". La tarjeta del **mapa** (estado o municipio) tiene su propio bloque en la misma pantalla: población, absoluto, tasa, orden y dato destacado.

## Dónde aparece un marcador en el sitio

- **Posición** → selector **Marcadores (Posición)**; son los únicos que se pueden **medir** (distancias).
- **Espectro y Spike** → selector **Marcadores (Densidad)**.
- Con muchos puntos (más de 3,000 en total; 800 en pantallas táctiles) se dibujan en un canvas; ver [rendimiento-mapa.md](rendimiento-mapa.md).
- Los marcadores *Ocultos* no aparecen; los *Privados* solo para usuarios con sesión.

## Cómo agregar un marcador nuevo (resumen)

**Ejemplo:** casos de dengue por unidad, como burbujas.

1. *Carga de Datos › Marcadores* → **Incorporar Nueva Variable**: clave `CASOS_DENGUE`, etiqueta "Casos de Dengue 2026", archivo `casos-dengue.csv`.
2. Prepara el CSV con la plantilla (las 8 columnas obligatorias + una columna numérica, por ejemplo `total_casos`) y **súbelo** a esa variable.
3. *Configuración › Marcadores* → **Añadir Nueva**: clave `CASOS_DENGUE`, etiqueta, archivo fuente `casos-dengue.csv`, tipo **Espectro**, columna numérica `total_casos`; opcionalmente la *clave relacionada* de un indicador y la agrupación por `CLUES`.
4. *Personalizar › Mapa y Colores*: elige el relleno.
5. Revisa el tooltip en *Configuración › Tooltips* y comprueba en un mapa que aparezca en **Marcadores (Densidad)**.

## Errores y avisos frecuentes

| Mensaje o síntoma | Causa |
|---|---|
| "No tiene las columnas requeridas…" al subir | Falta alguna de las 8 columnas obligatorias |
| Aviso de filas sin `CVE_ENT` | Esos puntos no se dibujan en ningún mapa |
| "La clave … ya existe" | Clave repetida (distingue mayúsculas) |
| "Debe seleccionar un archivo fuente" | No se eligió el CSV del marcador |
| El marcador no aparece | Visibilidad *Oculto*/*Privado*, CSV sin filas para esa entidad, o filtro de columna/valor que no coincide |
| Burbujas o espinas sin tamaño | La *columna numérica* no existe o está vacía en esas filas (valor 0 o sin dato no se dibuja) |
