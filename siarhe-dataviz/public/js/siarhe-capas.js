/* /public/js/siarhe-capas.js */
/**
 * SIARHE DataViz - Lectura de las capas de marcadores en JSON
 * ------------------------------------------------------------------
 * Convierte una capa de marcadores en JSON (formato "siarhe-marcadores", el compacto de RHEMX: listas de textos y filas
 * con la posición en cada lista) a la misma tabla que se obtiene de su CSV: un encabezado y filas de textos. Así el
 * filtrado, el agrupado y los tooltips trabajan igual con cualquiera de los dos archivos.
 *
 * El archivo se carga en la página y dentro del worker de marcadores (importScripts), por eso se publica en `self`.
 * La conversión equivalente del servidor está en includes/class-siarhe-marker-json.php.
 *
 *   1. Catálogo de campos
 *   2. Conversión a tabla
 *   3. Nombres de columna
 */
(function(root) {
    'use strict';

    // ==========================================
    // 1. CATÁLOGO DE CAMPOS
    // ==========================================

    // Campos indexados: campo de la fila -> nombre de su lista en el JSON
    const LISTAS = {
        institucion: 'instituciones', nivel: 'niveles', tipologia: 'tipologias', estrato: 'estratos',
        tipo_estab: 'tipos_establecimiento', jurisdiccion: 'jurisdicciones', municipio: 'municipios', localidad: 'localidades'
    };

    // Encabezado estándar de cada campo conocido (los nombres del catálogo CLUES)
    const ENCABEZADOS = {
        latitud: 'LATITUD', longitud: 'LONGITUD', clues: 'CLUES', nombre: 'NOMBRE DE LA UNIDAD',
        institucion: 'NOMBRE DE LA INSTITUCION', nivel: 'NIVEL ATENCION', tipologia: 'NOMBRE DE TIPOLOGIA',
        entidad: 'CLAVE DE LA ENTIDAD', estrato: 'ESTRATO UNIDAD', tipo_estab: 'NOMBRE TIPO ESTABLECIMIENTO',
        jurisdiccion: 'JURISDICCION', municipio: 'MUNICIPIO', localidad: 'LOCALIDAD',
        plazas: 'PLAZAS DE ENFERMERIA', personas: 'RECURSOS HUMANOS ESTIMADOS', anterior: 'CLUES ANTERIOR',
        reportes: 'REPORTES', anios: 'ANIOS REPORTADOS', primer: 'PRIMER ANIO', ultimo: 'ULTIMO ANIO'
    };

    // ==========================================
    // 2. CONVERSIÓN A TABLA
    // ==========================================

    /** Indica si el JSON recibido es una capa de marcadores. */
    function esCapa(j) {
        return !!j && Array.isArray(j.fila) && Array.isArray(j.unidades);
    }

    /**
     * Capa a { header, rows }: header con el encabezado de cada campo (el del CSV de origen si la capa lo declara en
     * "columnas", el estándar o el nombre del campo) y rows con una lista de textos por unidad. Los campos indexados
     * salen con su texto y la entidad con dos dígitos, igual que en el CSV equivalente. Una fila puede omitir sus últimos
     * campos cuando están vacíos (RHEMX solo escribe la CLUES anterior en las unidades que la tienen).
     */
    function aTabla(j) {
        if (!esCapa(j)) return { header: [], rows: [] };
        const fila = j.fila.map(String);
        const columnas = j.columnas || {};
        const n = fila.length;
        const header = fila.map(function(c) { return columnas[c] || ENCABEZADOS[c] || c; });

        // Por posición: lista de textos (campo indexado), 'entidad' o null (valor directo)
        const modo = fila.map(function(c) {
            if (LISTAS[c] && Array.isArray(j[LISTAS[c]])) return j[LISTAS[c]];
            return c === 'entidad' ? 'entidad' : null;
        });

        const unidades = j.unidades;
        const rows = new Array(unidades.length);
        let k = 0;
        for (let u = 0; u < unidades.length; u++) {
            const src = unidades[u];
            if (!Array.isArray(src)) continue;
            const r = new Array(n);
            for (let i = 0; i < n; i++) {
                const v = src[i];
                const m = modo[i];
                if (m === null) r[i] = (v === null || v === undefined) ? '' : String(v);
                else if (m === 'entidad') r[i] = v > 0 ? (v < 10 ? '0' + v : String(v)) : '';
                else r[i] = (typeof v === 'number' && v >= 0 && m[v] !== undefined) ? String(m[v]) : '';
            }
            rows[k++] = r;
        }
        rows.length = k;
        return { header: header, rows: rows };
    }

    /** Capa a filas-objeto con las llaves del encabezado (la forma que entrega d3.csv). */
    function aObjetos(j) {
        const t = aTabla(j);
        const h = t.header, n = h.length;
        return t.rows.map(function(r) {
            const o = {};
            for (let i = 0; i < n; i++) o[h[i]] = r[i];
            return o;
        });
    }

    // ==========================================
    // 3. NOMBRES DE COLUMNA
    // ==========================================

    /**
     * Nombre de columna normalizado para compararlo: minúsculas, sin acentos y con el guion bajo como espacio. Con esto
     * la configuración de un marcador puede decir "nivel_atencion" y el archivo traer "NIVEL ATENCION".
     */
    function normaliza(nombre) {
        return String(nombre === undefined || nombre === null ? '' : nombre).trim().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[_\s]+/g, ' ');
    }

    root.SiarheCapas = { esCapa: esCapa, aTabla: aTabla, aObjetos: aObjetos, normaliza: normaliza };
})(typeof self !== 'undefined' ? self : this);
