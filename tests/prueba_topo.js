// Prueba del lector de geometrias del plugin con los TopoJSON reales de RHEMX.
// Uso: node prueba_topo.js <carpeta public/js del plugin> <carpeta rhemx>
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS = process.argv[2];
const RHEMX = process.argv[3];
const d3 = require(path.join(JS, 'libs/d3.v7.min.js'));
const topojson = require(path.join(JS, 'libs/topojson-client.min.js'));

// siarhe-topo.js se publica en `self`: se ejecuta en un contexto con ese nombre
const ctx = { self: {} };
vm.runInNewContext(fs.readFileSync(path.join(JS, 'siarhe-topo.js'), 'utf8'), ctx);
const T = ctx.self.SiarheTopo;

let fallos = 0;
const ok = (cond, msg) => { if (!cond) { fallos++; console.log('  FALLA: ' + msg); } };
const lee = (f) => JSON.parse(fs.readFileSync(f, 'utf8'));
const csvFilas = (f) => {
    const l = fs.readFileSync(f, 'utf8').replace(/^﻿/, '').split(/\r?\n/).filter(Boolean);
    const h = l[0].split(',');
    return l.slice(1).map(r => { const c = r.split(','); const o = {}; h.forEach((k, i) => o[k] = c[i]); return o; });
};

// 1. Marco por año
console.log('1. marcoPara');
ok(T.marcoPara(2026, ['2025', '2020', '2015']) === '2025', '2026 -> 2025');
ok(T.marcoPara(2019, ['2025', '2020', '2015']) === '2015', '2019 -> 2015');
ok(T.marcoPara(2024, ['2025', '2020', '2015']) === '2020', '2024 -> 2020');
ok(T.marcoPara(2012, ['2025', '2020', '2015']) === '2015', '2012 sin marco anterior -> el mas antiguo');
ok(T.marcoPara(2030, ['2025']) === '2025', '2030 sin marco 2030 -> 2025');
ok(T.marcoPara(2026, []) === null, 'sin marcos -> null');
ok(T.marcoPara(2018, ['2025']) === '2025', '2018 con solo 2025 -> 2025');

// 2. Conversion y union con las bases estaticas, por marco
for (const marco of ['2016', '2020', '2025']) {
    const dir = path.join(RHEMX, 'web/data/geo', marco);
    if (!fs.existsSync(dir)) continue;
    console.log('2. marco ' + marco);

    const ent = T.aColeccion(lee(path.join(dir, '33_entidades.json')), topojson, d3.geoArea);
    ok(ent.features.length === 32, 'entidades: ' + ent.features.length);
    ok(ent.features.every(f => /^\d{2}$/.test(f.properties.CVE_ENT) && f.properties.NOM_ENT && f.properties.NOMGEO), 'entidades con CVE_ENT y nombre');
    ok(ent.features.every(f => d3.geoArea(f) < 2 * Math.PI), 'entidades sin anillos invertidos');

    // el mapa nacional se proyecta con fitSize: los limites deben caber en el lienzo
    const proj = d3.geoMercator().fitSize([1600, 900], ent);
    const b = d3.geoPath(proj).bounds(ent);
    ok(b[0][0] >= -1 && b[0][1] >= -1 && b[1][0] <= 1601 && b[1][1] <= 901, 'limites proyectados dentro del lienzo: ' + JSON.stringify(b));

    const munRep = T.aColeccion(lee(path.join(dir, '33_municipios.json')), topojson, d3.geoArea);
    ok(munRep.features.length > 2400, 'municipios de la republica: ' + munRep.features.length);
    ok(munRep.features.every(f => f.properties.CVE_ENT.length === 2 && f.properties.CVE_MUN.length === 3 && f.properties.NOM_MUN !== undefined), 'municipios con CVE_ENT + CVE_MUN');
    ok(munRep.features.every(f => d3.geoArea(f) < 2 * Math.PI), 'municipios sin anillos invertidos');

    let sinFila = 0, totalMun = 0;
    for (const nn of ['01', '09', '20', '31']) {
        const mun = T.aColeccion(lee(path.join(dir, nn + '_municipios.json')), topojson, d3.geoArea);
        ok(mun.features.every(f => f.properties.CVE_ENT === nn), nn + '_municipios de su entidad');
        const loc = T.aColeccion(lee(path.join(dir, nn + '_localidades.json')), topojson, d3.geoArea);
        ok(loc.features.length > 0 && loc.features.every(f => f.properties.CVE_ENT === nn && f.properties.CVE_MUN.length === 3 && f.properties.CVE_LOC.length === 4), nn + '_localidades');
        ok(loc.features.every(f => ['Urbana', 'Rural', ''].includes(f.properties.AMBITO)), 'ambito de las localidades');

        // union con la base estatica de 2026 (la clave del CSV de una entidad es CVE_MUN de 3 digitos)
        const slugs = { '01': 'aguascalientes', '09': 'ciudad-de-mexico', '20': 'oaxaca', '31': 'yucatan' };
        const csv = path.join(RHEMX, 'data/processed/mapas/2026/csv', nn + '_' + slugs[nn] + '.csv');
        if (fs.existsSync(csv)) {
            const claves = new Set(csvFilas(csv).map(r => r.CVE_MUN));
            mun.features.forEach(f => { totalMun++; if (!claves.has(f.properties.CVE_MUN)) sinFila++; });
        }
    }
    console.log('   municipios sin fila en la base 2026: ' + sinFila + ' de ' + totalMun);
    const csvNac = path.join(RHEMX, 'data/processed/mapas/2026/csv/33_republica-mexicana.csv');
    if (fs.existsSync(csvNac)) {
        const claves = new Set(csvFilas(csvNac).map(r => r.CVE_ENT));
        ok(ent.features.every(f => claves.has(f.properties.CVE_ENT)), 'cada entidad tiene fila en la base nacional');
    }
}

// 3. GeoJSON anterior: las propiedades no se tocan
console.log('3. propiedades de un GeoJSON anterior');
const previas = { CVE_ENT: '01', CVE_MUN: '001', NOMGEO: 'Aguascalientes' };
ok(T.normalizaPropiedades(previas) === previas, 'un elemento con CVE_ENT se devuelve igual');
ok(!T.esTopologia({ type: 'FeatureCollection', features: [] }), 'FeatureCollection no es topologia');

// 4. Worker de geometrias con una topologia (fetch e importScripts simulados)
console.log('4. worker de geometrias');
(async () => {
    const dir = path.join(RHEMX, 'web/data/geo/2025');
    const mensajes = [];
    const wctx = {
        Math, Float64Array, Uint8Array, Array, Object, String, Infinity, NaN, Error, console,
        fetch: async (url) => ({ ok: true, status: 200, json: async () => lee(url) }),
        importScripts: (...libs) => libs.forEach(l => vm.runInContext(fs.readFileSync(l, 'utf8'), wctx)),
    };
    wctx.self = wctx;
    wctx.postMessage = (m) => mensajes.push(m);
    vm.createContext(wctx);
    vm.runInContext(fs.readFileSync(path.join(JS, 'siarhe-geo-worker.js'), 'utf8'), wctx);

    const ent = T.aColeccion(lee(path.join(dir, '33_entidades.json')), topojson, d3.geoArea);
    const proj = d3.geoMercator().fitSize([1600, 900], ent);
    for (const archivo of ['33_municipios.json', '01_localidades.json']) {
        mensajes.length = 0;
        await wctx.onmessage({ data: { id: 1, url: path.join(dir, archivo), proj: { scale: proj.scale(), translate: proj.translate() },
            tolerances: [0.25, 0.06], libs: [path.join(JS, 'siarhe-topo.js'), path.join(JS, 'libs/topojson-client.min.js')] } });
        const fin = mensajes[mensajes.length - 1];
        ok(fin && fin.ok === true, archivo + ': ' + (fin && fin.error));
        if (fin && fin.ok) {
            ok(fin.features.every(f => f.properties.CVE_ENT && f.properties.CVE_MUN && f.d.length === 3 && f.d[2].startsWith('M')), archivo + ': trazos y propiedades');
            console.log('   ' + archivo + ': ' + fin.features.length + ' poligonos; ejemplo ' + JSON.stringify(fin.features[0].properties));
        }
    }
    console.log(fallos ? ('RESULTADO: ' + fallos + ' falla(s)') : 'RESULTADO: todo correcto');
    process.exit(fallos ? 1 : 0);
})();
