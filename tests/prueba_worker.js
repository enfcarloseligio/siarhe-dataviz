// Prueba del worker de marcadores: la capa JSON y su CSV equivalente deben dar los mismos puntos.
// Uso: node prueba_worker.js <carpeta public/js del plugin> <carpeta rhemx> <carpeta con los CSV generados por prueba_marcadores.php>
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const JS = process.argv[2], RHEMX = process.argv[3], SALIDA = process.argv[4];
let fallos = 0;
const ok = (c, m) => { if (!c) { fallos++; console.log('  FALLA: ' + m); } };

function creaWorker() {
    const mensajes = [];
    const ctx = {
        Math, Array, Object, String, Map, Date, Error, console, isNaN, parseFloat,
        fetch: async (url) => ({ ok: true, status: 200, text: async () => fs.readFileSync(url, 'utf8'), json: async () => JSON.parse(fs.readFileSync(url, 'utf8')) }),
        importScripts: (...libs) => libs.forEach(l => vm.runInContext(fs.readFileSync(l, 'utf8'), ctx)),
    };
    ctx.self = ctx;
    ctx.postMessage = (m) => mensajes.push(m);
    vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(path.join(JS, 'siarhe-worker.js'), 'utf8'), ctx);
    return async (url, spec) => {
        mensajes.length = 0;
        await ctx.onmessage({ data: { id: 1, url, spec, libs: [path.join(JS, 'siarhe-capas.js')] } });
        const m = mensajes[mensajes.length - 1];
        if (!m.ok) throw new Error(m.error);
        return m.points;
    };
}

(async () => {
    const pide = creaWorker();
    const json = path.join(RHEMX, 'web/data/maps/unidades_clues_2026.json');
    const csv = path.join(SALIDA, 'unidades_clues_2026.csv');
    const base = { type: 'X', reglas: [], espectroCol: '' };

    console.log('1. establecimientos de salud por nivel de atencion (marcadores del sistema)');
    let total = 0;
    for (const nivel of ['Primer Nivel', 'Segundo Nivel', 'Tercer Nivel', 'No Aplica', 'No Especificado']) {
        const spec = Object.assign({}, base, { isNacional: true, currentEnt: '33', filtroCol: 'nivel_atencion', filtroVal: nivel.toLowerCase() });
        const a = await pide(json, spec), b = await pide(csv, spec);
        ok(JSON.stringify(a) === JSON.stringify(b), nivel + ': JSON y CSV dan puntos distintos');
        ok(a.every(p => p.nivel_atencion === nivel), nivel + ': filtro');
        total += a.length;
        console.log('   ' + nivel.padEnd(16) + a.length + ' puntos' + (a[0] ? '; ejemplo: ' + JSON.stringify({ clues: a[0].clues, nombre: a[0].nombre, institucion: a[0].institucion, municipio: a[0].municipio, tipologia: a[0].tipologia, estrato: a[0].estrato, juris: a[0].jurisdiccion }) : ''));
    }
    const todos = await pide(json, Object.assign({}, base, { isNacional: true, currentEnt: '33' }));
    ok(total === todos.length, 'los niveles suman el total: ' + total + ' de ' + todos.length);

    console.log('2. mapa de una entidad (solo sus puntos)');
    const ags = await pide(json, Object.assign({}, base, { isNacional: false, currentEnt: '01' }));
    const agsCsv = await pide(csv, Object.assign({}, base, { isNacional: false, currentEnt: '01' }));
    ok(ags.length > 0 && ags.every(p => p.cve_ent === '01'), 'Aguascalientes: ' + ags.length);
    ok(JSON.stringify(ags) === JSON.stringify(agsCsv), 'Aguascalientes: JSON y CSV');
    console.log('   Aguascalientes: ' + ags.length + ' puntos');

    console.log('3. clinicas (capas de RHEMX)');
    for (const f of ['clinicas_cateteres_2025', 'clinicas_cateteres_historico', 'clinicas_heridas_historico']) {
        const spec = Object.assign({}, base, { isNacional: true, currentEnt: '33' });
        const a = await pide(path.join(RHEMX, 'web/data/maps', f + '.json'), spec), b = await pide(path.join(SALIDA, f + '.csv'), spec);
        ok(a.length > 0 && JSON.stringify(a) === JSON.stringify(b), f + ': JSON y CSV');
        console.log('   ' + f.padEnd(30) + a.length + ' puntos');
    }

    console.log('4. catalogo CLUES en CSV y su JSON generado por el plugin');
    const cluesCsv = path.join(RHEMX, 'data/processed/CLUES/2026/clues_2026.csv'), cluesJson = path.join(SALIDA, 'clues_2026.json');
    if (fs.existsSync(cluesCsv) && fs.existsSync(cluesJson)) {
        const spec = Object.assign({}, base, { isNacional: true, currentEnt: '33', filtroCol: 'nivel_atencion', filtroVal: 'segundo nivel' });
        const a = await pide(cluesCsv, spec), b = await pide(cluesJson, spec);
        ok(a.length > 0 && a.length === b.length, 'mismo numero de puntos: ' + a.length + ' y ' + b.length);
        const clave = p => [p.clues, p.nombre, p.institucion, p.municipio, p.lat, p.lon, p.cve_ent, p.tipologia, p.nivel_atencion, p.jurisdiccion].join('|');
        ok(a.every((p, i) => clave(p) === clave(b[i])), 'mismos campos en cada punto');
        console.log('   segundo nivel: ' + a.length + ' puntos en ambos');
    }
    console.log(fallos ? 'RESULTADO: ' + fallos + ' falla(s)' : 'RESULTADO: todo correcto');
    process.exit(fallos ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
