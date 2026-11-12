/* /public/js/siarhe-worker.js */
/**
 * SIARHE DataViz - Worker de marcadores
 * ------------------------------------------------------------------
 * Descarga, lee, filtra y agrupa el archivo de un marcador FUERA del hilo principal: con decenas de miles
 * de filas (10 a 14 MB en CSV) el navegador deja de bloquearse mientras se prepara el mapa. Lee la capa en JSON
 * (formato "siarhe-marcadores", con siarhe-capas.js) o el CSV; los dos dan la misma tabla.
 *
 * Recibe { id, url, spec, libs: [siarhe-capas.js] } y responde { id, ok, points, ms } (o { id, ok:false, error }).
 * La logica de filtrado y agrupado replica la de loadMarkerData() de siarhe-controls.js, que se conserva
 * como respaldo cuando el navegador no puede usar workers.
 */
'use strict';

// Archivos ya leidos (la misma base alimenta varios marcadores con filtros distintos): se leen una sola vez
const FILES = new Map();
const MAX_FILES = 2;

/** CSV (RFC 4180) a { header, rows }. Cada fila es un arreglo de cadenas. */
function parseCSV(text) {
    if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
    const rows = [];
    const n = text.length;
    let i = 0;
    let row = [];

    while (i < n) {
        let field;
        if (text.charCodeAt(i) === 34) {                       // campo entre comillas
            let j = i + 1;
            let out = '';
            for (;;) {
                const q = text.indexOf('"', j);
                if (q === -1) { out += text.slice(j); j = n; break; }
                out += text.slice(j, q);
                if (text.charCodeAt(q + 1) === 34) { out += '"'; j = q + 2; continue; } // comilla escapada
                j = q + 1;
                break;
            }
            field = out;
            i = j;
            // Texto suelto tras la comilla de cierre hasta el separador (poco comun): se conserva
            while (i < n) {
                const c = text.charCodeAt(i);
                if (c === 44 || c === 10 || c === 13) break;
                field += text[i];
                i++;
            }
        } else {
            let j = i;
            while (j < n) {
                const c = text.charCodeAt(j);
                if (c === 44 || c === 10 || c === 13) break;
                j++;
            }
            field = text.slice(i, j);
            i = j;
        }
        row.push(field);

        const c = text.charCodeAt(i);
        if (c === 44) { i++; if (i >= n) { row.push(''); rows.push(row); row = []; } continue; }
        // fin de linea (\n, \r o \r\n)
        if (c === 13) { i++; if (text.charCodeAt(i) === 10) i++; }
        else if (c === 10) { i++; }
        rows.push(row);
        row = [];
    }
    if (row.length) rows.push(row);

    const header = rows.length ? rows.shift() : [];
    return { header: header, rows: rows };
}

/** Nombre de columna normalizado (minusculas, sin acentos, guion bajo como espacio); mismo criterio que SiarheCapas.normaliza. */
function norm(nombre) {
    return String(nombre === undefined || nombre === null ? '' : nombre).trim().toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[_\s]+/g, ' ');
}

async function loadFile(url, libs) {
    if (FILES.has(url)) {
        const f = FILES.get(url);
        FILES.delete(url); FILES.set(url, f);   // se marca como el mas reciente
        return f;
    }
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status + ' al descargar ' + url);
    let parsed;
    if (/\.json(\?|$)/i.test(url)) {
        // Capa en JSON: se convierte a la misma tabla que da su CSV
        if (!self.SiarheCapas) {
            if (!Array.isArray(libs) || !libs.length) throw new Error('Falta la libreria para leer la capa JSON');
            importScripts.apply(self, libs);
        }
        const capa = await res.json();
        if (!self.SiarheCapas.esCapa(capa)) throw new Error('El JSON no es una capa de marcadores');
        parsed = self.SiarheCapas.aTabla(capa);
    } else {
        parsed = parseCSV(await res.text());
    }
    FILES.set(url, parsed);
    while (FILES.size > MAX_FILES) FILES.delete(FILES.keys().next().value);
    return parsed;
}

// Columnas de la fila original que otros modulos siguen leyendo despues (tooltips y densidad)
const RAW_COLS = ['entidad', 'nom_ent', 'localidad', 'nom_loc', 'cve_mun', 'cve mun', 'clave del municipio', 'municipio', 'clave_municipio'];

function processRows(file, spec) {
    const header = file.header, rows = file.rows;

    // Nombre de columna normalizado -> posicion. Si se repite gana la ultima, como en el modo normal.
    const colMap = {};
    for (let i = 0; i < header.length; i++) colMap[norm(header[i])] = i;
    const resolve = (names) => {
        for (let k = 0; k < names.length; k++) {
            const idx = colMap[norm(names[k])];
            if (idx !== undefined) return idx;
        }
        return -1;
    };
    const val = (row, idx) => {
        if (idx < 0) return '';
        const v = row[idx];
        return (v !== undefined && v !== null) ? v.toString().trim() : '';
    };

    const latIdx = resolve(['latitud', 'lat']);
    const lonIdx = resolve(['longitud', 'lon']);
    const cveEntIdx = resolve(['cve_ent', 'cve ent', 'clave de la entidad', 'clave_entidad', 'entidad']);
    const cluesIdx = resolve(['clues']);
    const institucionIdx = resolve(['institución', 'institucion', 'nombre de la institucion', 'clave_institucion']);
    const nombreIdx = resolve(['nombre_unidad', 'nombre de la unidad', 'unidad']);
    const municipioIdx = resolve(['municipio']);
    const tipoEstabIdx = resolve(['tipo_establecimiento', 'tipo establecimiento', 'nombre tipo establecimiento']);
    const tipologiaIdx = resolve(['nombre_tipologia', 'nombre tipologia', 'nombre de tipologia']);
    const nivelIdx = resolve(['nivel_atencion', 'nivel atencion']);
    const cveNivelIdx = resolve(['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
    const jurisdiccionIdx = resolve(['jurisdiccion', 'jurisdicción']);
    const estratoIdx = resolve(['estrato_unidad', 'estrato unidad']);

    const filtroCol = spec.filtroCol || null;
    const filtroVal = spec.filtroVal || null;
    const agruparCol = spec.agruparCol || null;
    const filtroIdx = filtroCol ? resolve([filtroCol]) : -1;
    const agruparIdx = agruparCol ? resolve([agruparCol]) : -1;
    const reglas = (spec.reglas || []).map(r => Object.assign({}, r, { _idx: resolve([r.col]) }));

    // Columnas que se conservan en _raw
    const keep = {};
    RAW_COLS.concat(spec.espectroCol ? [spec.espectroCol] : []).forEach(n => { keep[norm(n)] = true; });
    const rawIdx = [];
    for (let i = 0; i < header.length; i++) {
        if (keep[norm(header[i])] && colMap[norm(header[i])] === i) rawIdx.push(i);
    }

    // 1) Filtrado
    const filtered = [];
    for (let i = 0; i < rows.length; i++) {
        const r = rows[i];
        const lat = parseFloat(val(r, latIdx));
        const lon = parseFloat(val(r, lonIdx));
        let ok = !isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0;

        if (ok && !spec.isNacional && cveEntIdx >= 0) {
            // Sin clave de entidad el punto no se puede asignar a ningun mapa: no se dibuja
            const e = val(r, cveEntIdx);
            if (!e || e.padStart(2, '0') !== spec.currentEnt) ok = false;
        }
        if (ok && filtroCol && filtroVal) {
            ok = filtroIdx >= 0 && val(r, filtroIdx).toLowerCase() === filtroVal;
        }
        if (ok) filtered.push(r);
    }

    const buildPoint = (r) => {
        const raw = {};
        for (let k = 0; k < rawIdx.length; k++) raw[header[rawIdx[k]]] = r[rawIdx[k]];
        return {
            clues: val(r, cluesIdx),
            institucion: val(r, institucionIdx),
            nombre: val(r, nombreIdx),
            municipio: val(r, municipioIdx),
            lat: parseFloat(val(r, latIdx)),
            lon: parseFloat(val(r, lonIdx)),
            cve_ent: val(r, cveEntIdx),
            tipo_estab: val(r, tipoEstabIdx),
            tipologia: val(r, tipologiaIdx),
            nivel_atencion: val(r, nivelIdx),
            cve_nivel: val(r, cveNivelIdx),
            jurisdiccion: val(r, jurisdiccionIdx),
            estrato: val(r, estratoIdx),
            tipo: spec.type,
            _raw: raw
        };
    };

    // 2) Agrupado (o mapeo directo)
    if (agruparCol) {
        const groups = new Map();
        if (agruparIdx >= 0) {
            for (let i = 0; i < filtered.length; i++) {
                const r = filtered[i];
                const key = val(r, agruparIdx);
                if (!key) continue;
                let target = groups.get(key);
                if (!target) {
                    target = buildPoint(r);
                    target._agrupados_total = 0;
                    target._conteo_reglas = {};
                    reglas.forEach(g => { target._conteo_reglas[g.label] = 0; });
                    groups.set(key, target);
                }
                target._agrupados_total += 1;
                reglas.forEach(g => {
                    if (g._idx >= 0) {
                        const rowVal = val(r, g._idx).toLowerCase();
                        const want = (g.val === undefined || g.val === null ? '' : g.val).toString().toLowerCase().trim();
                        if (rowVal === want) target._conteo_reglas[g.label] += 1;
                    }
                });
            }
        }
        return Array.from(groups.values());
    }
    const points = new Array(filtered.length);
    for (let i = 0; i < filtered.length; i++) points[i] = buildPoint(filtered[i]);
    return points;
}

self.onmessage = async function(e) {
    const msg = e.data || {};
    const t0 = Date.now();
    try {
        const file = await loadFile(msg.url, msg.libs);
        const t1 = Date.now();
        const points = processRows(file, msg.spec || {});
        self.postMessage({ id: msg.id, ok: true, points: points, rows: file.rows.length, readMs: t1 - t0, procMs: Date.now() - t1 });
    } catch (err) {
        self.postMessage({ id: msg.id, ok: false, error: String(err && err.message ? err.message : err) });
    }
};
