// Prueba de app.controls.makeSwitch (conmutador de dos opciones) con un DOM mínimo simulado.
// Uso: node tests/prueba_controles.js
const fs = require('fs'), path = require('path'), assert = require('assert');

function nodo(tag) {
    const n = { tag, children: [], attrs: {}, listeners: {}, _cls: new Set(), disabled: false, className: '', textContent: '', title: '', type: '' };
    n.appendChild = c => { n.children.push(c); return c; };
    n.setAttribute = (k, v) => { n.attrs[k] = String(v); };
    n.addEventListener = (e, f) => { (n.listeners[e] = n.listeners[e] || []).push(f); };
    n.classList = { toggle: (c, on) => { on ? n._cls.add(c) : n._cls.delete(c); }, contains: c => n._cls.has(c) };
    n.click = () => { if (!n.disabled) (n.listeners.click || []).forEach(f => f()); };
    return n;
}
global.document = { createElement: nodo };
global.window = global;
global.SiarheDataViz = {};

const codigo = fs.readFileSync(path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js', 'siarhe-controls.js'), 'utf8');
new Function(codigo).call(global);
const app = global.SiarheDataViz;

let cambios = [];
const sw = app.controls.makeSwitch({
    etiqueta: 'Prueba',
    a: { valor: 'x', texto: 'Uno' }, b: { valor: 'y', texto: 'Dos' },
    alCambiar: v => cambios.push(v)
});
const [opA, pista, opB] = sw.el.children;

assert.strictEqual(sw.get(), 'x');
assert.strictEqual(pista.attrs['aria-checked'], 'false');
assert(opA.classList.contains('is-active'));

pista.click();
assert.strictEqual(sw.get(), 'y');
assert.strictEqual(pista.attrs['aria-checked'], 'true');
assert(opB.classList.contains('is-active') && !opA.classList.contains('is-active'));

opA.click();
assert.strictEqual(sw.get(), 'x');
assert.deepStrictEqual(cambios, ['y', 'x']);

// set() solo pinta: no avisa
sw.set('y');
assert.strictEqual(sw.get(), 'y');
assert.deepStrictEqual(cambios, ['y', 'x']);

// una opción desactivada no se puede elegir y deja la pista sin uso
sw.set('x');
sw.deshabilita(false, true);
assert(opB.disabled && pista.disabled && !opA.disabled);
pista.click(); opB.click();
assert.strictEqual(sw.get(), 'x');
assert.deepStrictEqual(cambios, ['y', 'x']);

// un valor desconocido en set() cae en la primera opción
sw.deshabilita(false, false);
sw.set('zzz');
assert.strictEqual(sw.get(), 'x');

console.log('prueba_controles: todo correcto');

// Normalización del buscador: sin acentos, mayúsculas ni espacios repetidos
assert.strictEqual(app.controls._normBusqueda('  Michoacán  de   Ocampo '), 'michoacan de ocampo');
assert.strictEqual(app.controls._normBusqueda(null), '');
assert.strictEqual(app.controls._normBusqueda('NUEVO LEÓN').indexOf(app.controls._normBusqueda('leon')) !== -1, true);
console.log('prueba_controles: normalización del buscador correcta');

// Grupos de marcadores por archivo: los niveles de Establecimientos forman un grupo; las clínicas sueltas, otro
app.utils = { markerGroup: t => (t === 'spike' ? 'espectro' : t) };
const st = {
    markerUrls: { CATETER: 'a.json', ESTAB_1: 'e.json', ESTAB_2: 'e.json', HERIDAS: 'h.json', DENS: 'd.json', SIN: '' },
    markerLabels: {
        CATETER: { label: 'Clínicas de catéteres', tipo: 'posicion', archivo: 'clinicas-cateteres.csv' },
        ESTAB_1: { label: 'Establecimientos de Salud: Primer Nivel', tipo: 'posicion', archivo: 'establecimientos-salud.csv' },
        ESTAB_2: { label: 'Establecimientos de Salud: Segundo Nivel', tipo: 'posicion', archivo: 'establecimientos-salud.csv' },
        HERIDAS: { label: 'Clínicas de heridas', tipo: 'posicion', archivo: 'clinicas-heridas.csv' },
        DENS: { label: 'Bacteriemias', tipo: 'spike', archivo: 'clinicas-cateteres.csv' },
        SIN: { label: 'Sin archivo', tipo: 'posicion', archivo: 'x.csv' }
    }
};
st.markerLabels.PROPIO = { label: 'Albergues', tipo: 'posicion', archivo: 'albergues.csv' };
st.markerUrls.PROPIO = 'p.json';
let gs = app.controls.markerGroups(st, 'posicion');
assert.strictEqual(gs.length, 2);
assert.strictEqual(gs[0].titulo, 'Establecimientos de Salud');
assert.deepStrictEqual(gs[0].claves, ['ESTAB_1', 'ESTAB_2']);
assert.strictEqual(gs[0].corto.ESTAB_2, 'Segundo Nivel');
assert.strictEqual(gs[1].titulo, 'Otros marcadores');
assert.deepStrictEqual(gs[1].claves, ['CATETER', 'HERIDAS', 'PROPIO']);
const gd = app.controls.markerGroups(st, 'espectro');
assert.strictEqual(gd.length, 1);
assert.strictEqual(gd[0].titulo, 'Marcadores');

// Grupos del sistema (campo grupo): las clínicas tienen el suyo aunque solo haya una cargada, y van en el orden del catálogo
st.markerGrupos = { establecimientos: 'Establecimientos de Salud', clinicas: 'Clínicas' };
st.markerLabels.CATETER.grupo = 'clinicas'; st.markerLabels.HERIDAS.grupo = 'clinicas';
st.markerLabels.ESTAB_1.grupo = 'establecimientos'; st.markerLabels.ESTAB_2.grupo = 'establecimientos';
gs = app.controls.markerGroups(st, 'posicion');
assert.deepStrictEqual(gs.map(g => g.titulo), ['Establecimientos de Salud', 'Clínicas', 'Otros marcadores']);
assert.deepStrictEqual(gs[1].claves, ['CATETER', 'HERIDAS']);
assert.strictEqual(gs[1].corto.CATETER, 'Catéteres');
assert.strictEqual(gs[1].corto.HERIDAS, 'Heridas');
assert.deepStrictEqual(gs[2].claves, ['PROPIO']);
st.markerUrls.HERIDAS = '';
assert.deepStrictEqual(app.controls.markerGroups(st, 'posicion')[1].claves, ['CATETER']);
st.markerUrls.HERIDAS = 'h.json';
// Un nombre editado que ya no comparte el prefijo deja los nombres completos
st.markerLabels.ESTAB_2.label = 'Hospitales';
gs = app.controls.markerGroups(st, 'posicion');
assert.strictEqual(gs[0].corto.ESTAB_1, 'Establecimientos de Salud: Primer Nivel');
st.markerLabels.ESTAB_2.label = 'Establecimientos de Salud: Segundo Nivel';
gs = app.controls.markerGroups(st, 'posicion');
// Resumen del desplegable
assert.strictEqual(app.controls.markerSummary(gs[0], new Set()), '');
assert.strictEqual(app.controls.markerSummary(gs[0], new Set(['ESTAB_2'])), 'Segundo Nivel');
assert.strictEqual(app.controls.markerSummary(gs[0], new Set(['ESTAB_1', 'ESTAB_2', 'CATETER'])), 'Todos (2)');
console.log('prueba_controles: grupos y resumen de marcadores correctos');

// Etiquetas sin encimarse: gana la geometría de mayor área y al acercar caben más
const codigoMapa = fs.readFileSync(path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js', 'siarhe-map.js'), 'utf8');
new Function(codigoMapa).call(global);
const etq = [
    { x: 100, y: 100, texto: 'Grande', area: 9 },
    { x: 110, y: 102, texto: 'Chica', area: 1 },     // encima de «Grande»
    { x: 400, y: 100, texto: 'Lejana', area: 2 },
    { x: 700, y: 100, texto: '', area: 5 }            // sin nombre: nunca se muestra
];
assert.deepStrictEqual(app.map._labelFit(etq, 14), [true, false, true, false]);
assert.deepStrictEqual(app.map._labelFit(etq, 0.7), [true, true, true, false]);
console.log('prueba_controles: etiquetas sin encimarse correctas');

// ------------------------------------------------------------------
// Marcadores de posición por tema (siarhe-posicion.js)
// ------------------------------------------------------------------
const codigoPos = fs.readFileSync(path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js', 'siarhe-posicion.js'), 'utf8');
new Function(codigoPos).call(global);
const pos = app.pos;
const sp = {
    base: { anio: '2024' },
    markerUrls: {
        ESTAB_1: { 2023: 'e23.json' }, ESTAB_2: { 2023: 'e23.json' },
        CATETER: { total: 'ct.json', 2024: 'c24.json' }, HERIDAS: { total: 'ht.json' }
    },
    markerLabels: {
        ESTAB_1: { tipo: 'posicion', grupo: 'establecimientos', archivo: 'establecimientos-salud.csv', filtro_val: 'Primer Nivel' },
        ESTAB_2: { tipo: 'posicion', grupo: 'establecimientos', archivo: 'establecimientos-salud.csv', filtro_val: 'Segundo Nivel' },
        CATETER: { tipo: 'posicion', grupo: 'clinicas', archivo: 'clinicas-cateteres.csv' },
        HERIDAS: { tipo: 'posicion', grupo: 'clinicas', archivo: 'clinicas-heridas.csv' }
    },
    markerStyles: { ESTAB_2: { shape: 'square', fill: '#123456', stroke: '#000000' } }
};
const [cat, her] = pos.CLINICAS;
// Archivos: las clínicas piden el año exacto o toda la serie; los establecimientos, el año o el anterior más cercano
assert.strictEqual(pos.urlClinica(sp, cat, 'anio'), 'c24.json');
assert.strictEqual(pos.urlClinica(sp, her, 'anio'), '');
assert.strictEqual(pos.urlClinica(sp, her, 'todas'), 'ht.json');
assert.strictEqual(pos.urlEstab(sp), 'e23.json');
const modo = id => pos.MODOS.find(m => m.id === id);
assert.strictEqual(pos.modoValido(sp, { ver: 'clinicas', clinica: 'heridas' }, modo('nivel')), false);
assert.strictEqual(pos.modoValido(sp, { ver: 'clinicas', clinica: 'heridas' }, modo('t_nivel')), true);
assert.strictEqual(pos.modoValido(sp, { ver: 'clinicas', clinica: 'ambas' }, modo('institucion')), true);
assert.strictEqual(pos.modoValido(sp, { ver: 'establecimientos' }, modo('t_nivel')), false);
assert.deepStrictEqual(pos.fuentes(sp, { ver: 'clinicas', clinica: 'ambas', modo: 't_nivel' }).map(f => f.url + ':' + f.bit), ['ct.json:1', 'ht.json:2']);
assert.deepStrictEqual(pos.fuentes(sp, { ver: 'clinicas', clinica: 'ambas', modo: 'nivel' }).map(f => f.url), ['c24.json']);

// Unión de capas: la unidad con las dos clínicas queda una vez, con bits = 3
const u = (clues, nivel, inst) => ({ clues, nivel_atencion: nivel, institucion: inst, lat: 1, lon: 1 });
const unidos = pos.combina([
    { bit: 1, archivo: 'c.csv', points: [u('A', 'Segundo Nivel', 'IMSS'), u('B', 'Tercer Nivel', 'SSA')] },
    { bit: 2, archivo: 'h.csv', points: [u('B', 'Tercer Nivel', 'SSA'), u('C', 'Segundo Nivel', 'IMSS')] }
]);
assert.deepStrictEqual(unidos.map(it => it.p.clues + it.bits), ['A1', 'B3', 'C2']);
assert.deepStrictEqual(unidos[1].archivos, ['c.csv', 'h.csv']);
// Los establecimientos no se unen aunque repitan CLUES
assert.strictEqual(pos.combina([{ bit: 0, archivo: 'e.csv', points: [u('A'), u('A')] }]).length, 2);

// Por nivel, clínicas: color del nivel y forma del tipo de clínica
const estilos = pos.estilosNivel(sp);
assert.strictEqual(estilos['segundo nivel'].fill, '#123456');      // el de Personalizar
assert.strictEqual(estilos['tercer nivel'].fill, '#9B2247');       // el de RHEMX por defecto
let r = pos.clasifica(unidos, { ver: 'clinicas', clinica: 'ambas', modo: 't_nivel', nivSel: new Set(['Segundo Nivel', 'Tercer Nivel']), insSel: new Set() }, estilos);
assert.deepStrictEqual(r.niveles, [{ valor: 'Segundo Nivel', n: 2 }, { valor: 'Tercer Nivel', n: 1 }]);
assert.deepStrictEqual(r.grupos.map(g => g.label), ['Clínica de catéteres: Segundo Nivel', 'Clínica de heridas: Segundo Nivel', 'Clínicas de catéteres y de heridas: Tercer Nivel']);
assert.deepStrictEqual(r.grupos.map(g => g.estilo.shape), ['triangle', 'triangle2', 'star']);
assert.deepStrictEqual(r.grupos.map(g => g.estilo.fill), ['#123456', '#123456', '#9B2247']);
assert.deepStrictEqual(r.grupos[2].archivos, ['c.csv', 'h.csv']);
// Un nivel sin marcar no se dibuja
r = pos.clasifica(unidos, { ver: 'clinicas', clinica: 'ambas', modo: 't_nivel', nivSel: new Set(['Tercer Nivel']), insSel: new Set() }, estilos);
assert.deepStrictEqual(r.grupos.map(g => g.puntos.length), [1]);

// Por institución, establecimientos: la forma es la de la institución (en el orden elegido) y el color el del nivel
const estab = pos.combina([{ bit: 0, archivo: 'e.csv', points: [u('1', 'Primer Nivel', 'SSA'), u('2', 'Segundo Nivel', 'IMSS'), u('3', '', 'IMSS'), u('4', 'Primer Nivel', 'ISSSTE')] }]);
r = pos.clasifica(estab, { ver: 'establecimientos', modo: 'institucion', nivSel: new Set(['Primer Nivel', 'Segundo Nivel', 'No Especificado']), insSel: new Set(['IMSS', 'SSA']) }, estilos);
assert.deepStrictEqual(r.instituciones.map(o => o.valor + o.n), ['IMSS2', 'ISSSTE1', 'SSA1']);
assert.deepStrictEqual(r.niveles.map(o => o.valor + o.n), ['Primer Nivel1', 'Segundo Nivel1', 'No Especificado1']); // solo de las instituciones elegidas
assert.deepStrictEqual(r.grupos.map(g => g.label), ['IMSS · Segundo Nivel', 'IMSS · No Especificado', 'SSA · Primer Nivel']);
assert.deepStrictEqual(r.grupos.map(g => g.estilo.shape), ['cross', 'cross', 'circle']);
assert.strictEqual(r.grupos[2].estilo.fill, '#1E5B4F');
// Por nivel, establecimientos: forma y color del nivel
r = pos.clasifica(estab, { ver: 'establecimientos', modo: 'nivel', nivSel: new Set(['Segundo Nivel']), insSel: new Set() }, estilos);
assert.deepStrictEqual(r.grupos.map(g => [g.label, g.estilo.shape, g.puntos.length]), [['Establecimientos de Salud: Segundo Nivel', 'square', 1]]);

// Enlaces anteriores: k=ESTAB_2,CATETER se traduce a la selección por tema
const sl = Object.assign({}, sp, { pos: Object.assign(pos.nuevo(), { ui: {} }) });
assert.strictEqual(pos.adopta(sl, 'ESTAB_2'), true);
assert.strictEqual(sl.pos.ver, 'establecimientos');
assert.deepStrictEqual(Array.from(sl.pos.pendNiveles), ['segundo nivel']);
assert.strictEqual(pos.adopta(sl, 'CATETER'), false);              // ya se ven establecimientos: una familia a la vez
const sc = Object.assign({}, sp, { pos: Object.assign(pos.nuevo(), { ui: {} }) });
pos.adopta(sc, 'CATETER'); pos.adopta(sc, 'HERIDAS');
assert.deepStrictEqual([sc.pos.ver, sc.pos.clinica], ['clinicas', 'ambas']);

// Leyenda por tema (modelo que dibuja app.map._legendPos), como la de RHEMX
const sv = Object.assign({}, sp, { pos: Object.assign(pos.nuevo(), { ver: 'clinicas', clinica: 'ambas', modo: 't_nivel',
    vistos: { total: 3, bits: { 1: 1, 2: 1, 3: 1 }, niveles: ['Tercer Nivel', 'Segundo Nivel'], instituciones: [] } }) });
let ly = pos.leyenda(sv, '');
assert.strictEqual(ly.titulo, 'Clínicas de catéteres y de heridas por nivel de atención');
assert.strictEqual(ly.texto, '3 clínicas en la República Mexicana: 1 de catéteres, 1 de heridas y 1 con las dos (toda la historia)');
assert.deepStrictEqual(ly.grupos.map(g => g.titulo), ['Nivel de atención (color)', 'Clínica (forma)']);
assert.deepStrictEqual(ly.grupos[0].items.map(i => i.texto + ':' + i.estilo.shape + ':' + i.estilo.fill), ['Segundo:circle:#123456', 'Tercer:circle:#9B2247']);
assert.deepStrictEqual(ly.grupos[1].items.map(i => i.texto + ':' + i.estilo.shape), ['Catéteres:triangle', 'Heridas:triangle2', 'Catéteres y heridas:star']);
// Por institución: nivel (color) e institución (forma, en columnas, en el orden elegido)
Object.assign(sv.pos, { ver: 'establecimientos', modo: 'institucion', insSel: new Set(['SSA', 'IMSS']),
    vistos: { total: 1200, bits: { 1: 0, 2: 0, 3: 0 }, niveles: ['Primer Nivel'], instituciones: ['SSA', 'IMSS'] } });
ly = pos.leyenda(sv, 'Oaxaca');
assert.strictEqual(ly.titulo, 'Unidades de salud de 2 instituciones');
assert.strictEqual(ly.texto, '1,200 unidades en operación en Oaxaca');
assert.strictEqual(ly.grupos[1].columnas, true);
assert.deepStrictEqual(ly.grupos[1].items.map(i => i.texto + ':' + i.estilo.shape), ['SSA:cross', 'IMSS:circle']);
// Por nivel con una sola clínica: un grupo sin título, con la forma de la clínica y el color del nivel
Object.assign(sv.pos, { ver: 'clinicas', clinica: 'heridas', modo: 't_nivel', vistos: { total: 5, bits: { 1: 0, 2: 5, 3: 0 }, niveles: ['Segundo Nivel'], instituciones: [] } });
ly = pos.leyenda(sv, '');
assert.deepStrictEqual(ly.grupos.map(g => [g.titulo, g.items[0].estilo.shape, g.items[0].estilo.fill]), [['', 'triangle2', '#123456']]);
assert.strictEqual(ly.texto, '5 clínicas de heridas en toda la historia en la República Mexicana');
sv.pos.vistos = null;
assert.strictEqual(pos.leyenda(sv, ''), null);
// Partir un título largo sin cortar palabras
assert.deepStrictEqual(app.map._wrap('Instituto de Seguridad y Servicios Sociales', 20), ['Instituto de', 'Seguridad y', 'Servicios Sociales']);
console.log('prueba_controles: marcadores de posición por tema correctos');

// Buscador: rango de coincidencia (0 empieza igual, 1 empieza una palabra, 2 dentro, -1 no coincide) y orden de los resultados
{
    const rango = app.controls._rangoBusqueda;
    assert.strictEqual(rango('madero', 'madero'), 0);
    assert.strictEqual(rango('ciudad madero', 'madero'), 1);
    assert.strictEqual(rango('altamadero', 'madero'), 2);
    assert.strictEqual(rango('victoria', 'madero'), -1);
    assert.strictEqual(rango('hospital general de ciudad victoria', 'hospital victoria general'), 1);   // todas las palabras, en cualquier orden
    assert.strictEqual(rango('hospital general', 'hospital pediatrico'), -1);
    const nombres = ['Francisco I. Madero', 'Madero', 'Villa Maderoso', 'Ciudad Madero', 'Tula'].map(app.controls._normBusqueda);
    assert.deepStrictEqual(app.controls._mejoresBusqueda(nombres, t => t, 'madero', 3), ['madero', 'ciudad madero', 'villa maderoso']);
    console.log('prueba_controles: rango y orden del buscador correctos');
}

// Tabla: columnas según el indicador y la unidad, y clave geoestadística de cada renglón
{
    const tabla = fs.readFileSync(path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js', 'siarhe-table.js'), 'utf8');
    app.utils = {
        findAbsoluteKeyForPair: (g, m) => Object.keys(m).find(k => m[k].pair === g && m[k].tipo === 'absoluto') || null,
        findRateKeyForAbsolute: (g, m) => Object.keys(m).find(k => m[k].pair === g && m[k].tipo === 'tasa') || null
    };
    new Function(tabla).call(global);
    const metricas = {
        poblacion: { label: 'Población' },
        enfermeras_total: { nombre: 'Total', tipo: 'absoluto', pair: 'total' },
        tasa_total: { nombre: 'Total', tipo: 'tasa', pair: 'total' },
        enf_admin: { nombre: 'Administración', tipo: 'absoluto', pair: 'admin' },
        tasa_admin: { nombre: 'Administración', tipo: 'tasa', pair: 'admin' }
    };
    const ids = cols => cols.map(c => c.id).join(',');
    assert.strictEqual(ids(app.table.columnas(metricas, 'tasa_total', 'plazas', true)), 'nombre,clave,pob,total,tasaTotal');
    assert.strictEqual(ids(app.table.columnas(metricas, 'poblacion', 'plazas', true)), 'nombre,clave,pob,total,tasaTotal');
    const conVar = app.table.columnas(metricas, 'tasa_admin', 'personas', false);
    assert.strictEqual(ids(conVar), 'nombre,clave,pob,total,tasaTotal,cnt,tasa');
    assert.strictEqual(conVar[0].titulo, 'Municipio');
    assert.strictEqual(conVar[3].titulo, 'Recursos humanos (total)');
    assert.strictEqual(conVar[5].titulo, 'Administración (recursos humanos)');
    assert.strictEqual(conVar[5].dato, 'enf_admin');
    assert.strictEqual(conVar[6].dato, 'tasa_admin');

    assert.strictEqual(app.table.clave({ CVE_ENT: '9' }, true), '09');
    assert.strictEqual(app.table.clave({ CVE_ENT: '28', CVE_MUN: '032' }, false, '28'), '28032');
    assert.strictEqual(app.table.clave({ CVE_MUN: '1' }, false, '1'), '01001');
    assert.strictEqual(app.table.clave({ CVE_MUN: '28032' }, false, '28'), '28032');
    assert.strictEqual(app.table.clave({ CVE_ENT: '33', isTotal: true }, true), '');
    console.log('prueba_controles: columnas y claves de la tabla correctas');
}

// Decimales de las tasas (como en RHEMX) y entidad pedida en el enlace para la navegación dentro del mapa
{
    const nucleo = fs.readFileSync(path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js', 'siarhe-core.js'), 'utf8');
    global.document.addEventListener = () => {};
    global.getComputedStyle = () => ({ getPropertyValue: () => '' });
    new Function(nucleo).call(global);
    const f = app.utils.fmtTasa;
    assert.strictEqual(f(0), '0');
    assert.strictEqual(f(0.1154), '0.115');
    assert.strictEqual(f(0.9996), '1.000');
    assert.strictEqual(f(1), '1.00');
    assert.strictEqual(f(2.094), '2.09');
    assert.strictEqual(f(9.999), '10.00');
    assert.strictEqual(f(10), '10.0');
    assert.strictEqual(f(1234.56), '1,234.6');
    assert.strictEqual(f(null), 'S/D');
    assert.strictEqual(f(NaN), 'S/D');

    const con = h => { global.location = { hash: h }; return app.nav.delEnlace(); };
    assert.strictEqual(con(''), '');
    assert.strictEqual(con('#siarhe-en=28'), '28');
    assert.strictEqual(con('#siarhe-en=28;siarhe-28=f=mun:28009'), '28');
    assert.strictEqual(con('#siarhe-33=a=2024;siarhe-en=14'), '14');
    assert.strictEqual(con('#siarhe-en=abc'), '');
    assert.strictEqual(con('#siarhe-en=280'), '');
    assert.strictEqual(app.nav.disponible(), false);
    global.SiarheDataVizConfig = { vista: '/x?ent={ent}&mode={mode}' };
    assert.strictEqual(app.nav.disponible(), true);
    console.log('prueba_controles: decimales de las tasas y entidad del enlace correctos');
}

// Cifras del encabezado, resumen y nota (app.cifras, como en RHEMX): sumas sin el extranjero, municipios con registros,
// municipios nuevos (sin polígono ni población) y municipios sin polígono (con población, creados después del marco)
{
    const metricas = {
        tasa_total: { tipo: 'tasa', pair: 'total', nombre: 'Total' }, enfermeras_total: { tipo: 'absoluto', pair: 'total', nombre: 'Total' },
        tasa_primer: { tipo: 'tasa', pair: 'primer', nombre: 'Primer nivel' }, enfermeras_primer: { tipo: 'absoluto', pair: 'primer', nombre: 'Primer nivel' }
    };
    const fila = (mun, pob, tot, pri, extra) => Object.assign({ CVE_ENT: '28', CVE_MUN: mun, estado: 'M' + mun, poblacion: pob, enfermeras_total: tot, enfermeras_primer: pri, isTotal: false, isSpecial: false, isExtranjero: false }, extra || {});
    const st = {
        metricas, currentMetric: 'tasa_primer', isNacional: false,
        csvData: [fila('001', 1000, 10, 4), fila('002', 500, 0, 0), fila('099', 0, 3, 1), fila('100', 200, 2, 2),
            fila('8888', 0, 5, 1, { isSpecial: true }), fila('9999', 1700, 20, 8, { isTotal: true })],
        geoData: { features: [{ properties: { CVE_ENT: '28', CVE_MUN: '001' } }, { properties: { CVE_ENT: '28', CVE_MUN: '002' } }, { properties: { CVE_ENT: '28', CVE_MUN: '003' } }] }
    };
    app.utils.getGeoKey = (p) => p.CVE_MUN;
    const c = app.cifras.calcula(st);
    assert.strictEqual(c.pob, 1700);
    assert.strictEqual(c.total, 20);
    assert.strictEqual(c.sinLugar, 5);
    assert.strictEqual(c.cnt, 8);
    assert.strictEqual(c.esTotal, false);
    assert.strictEqual(c.abs, 'enfermeras_primer');
    assert.strictEqual(c.lugares, 3);          // los del marco geoestadístico
    assert.strictEqual(c.conRegistros, 1);     // 001 (002 tiene 0; 099 y 100 no tienen polígono)
    assert.deepStrictEqual(c.nuevos.map(x => x.clave), ['099']);
    assert.deepStrictEqual(c.sinPoligono.map(x => x.clave), ['100']);
    // Con el total y el extranjero en la República
    const sn = { metricas, currentMetric: 'enfermeras_total', isNacional: true, geoData: null,
        csvData: [fila('', 100, 7, 1, { CVE_ENT: '01' }), fila('', 0, 9, 9, { CVE_ENT: '34', isSpecial: true, isExtranjero: true }), fila('', 0, 2, 0, { CVE_ENT: '8888', isSpecial: true })] };
    const n = app.cifras.calcula(sn);
    assert.strictEqual(n.total, 9);
    assert.strictEqual(n.esTotal, true);
    assert.strictEqual(n.conRegistros, 1);
    console.log('prueba_controles: cifras del encabezado, resumen y nota correctas');

    // Comparación de dos años (app.cambio, como `calculaCambio` de RHEMX): sin dato en un año se toma como 0, el municipio
    // ausente en el año comparado es «nuevo», menos de 5 % es «sin cambio importante» y el total va con la clave «total»
    const C = app.cambio;
    const anterior = [fila('001', 1000, 10, 2), fila('002', 500, 0, 0), fila('100', 200, 2, 0), fila('9999', 1700, 15, 4, { isTotal: true })];
    const sc = Object.assign({}, st, { currentMetric: 'enfermeras_primer', base: { anio: '2024', unidad: 'plazas', bases: {} }, comparar: '2023' });
    sc.cmp = C.deFilas(sc, anterior, '2023', 'plazas');
    const k = C.calcula(sc);
    assert.ok(k, 'la comparación debía estar activa');
    const g = x => k.mapa.get(x);
    assert.deepStrictEqual([g('001').v1, g('001').v2, g('001').delta, g('001').pct, g('001').clase], [4, 2, 2, 100, 'sube']);
    assert.strictEqual(g('002').ceros, true);                      // 0 en ambos años
    assert.strictEqual(g('099').nuevo, true);                      // no estaba en 2023: incremento desde 0
    assert.strictEqual(g('099').pct, null);
    assert.strictEqual(C.fmtPctDe(g('099')), 'desde 0');
    assert.strictEqual(g('100').clase, 'sube');
    assert.deepStrictEqual([g('total').v1, g('total').v2, g('total').delta], [8, 4, 4]);
    assert.deepStrictEqual(k.stats, { suben: 1, bajan: 0, iguales: 1, total: 2 });   // solo los del mapa (001 y 002)
    assert.strictEqual(C.posicion({ clase: 'igual' }), 0.5);
    assert.strictEqual(C.posicion({ clase: 'sube', pct: 25 }), 1);
    assert.strictEqual(C.posicion({ clase: 'baja', pct: -15 }), 0.125);
    assert.strictEqual(C.fmtCambio(sc, -3), '-3');
    assert.strictEqual(C.fmtPorcentaje(4.26), '+4.3 %');
    // Un cambio menor de 5 % es «igual»; la población y las localidades de un estado no se comparan
    sc.cmp = C.deFilas(sc, [fila('001', 1000, 10, 3.9)], '2023', 'plazas');
    sc.csvData = [fila('001', 1000, 10, 4)];
    assert.strictEqual(C.calcula(sc).mapa.get('001').clase, 'igual');
    assert.strictEqual(C.calcula(Object.assign({}, sc, { currentMetric: 'poblacion' })), null);
    assert.strictEqual(C.calcula(Object.assign({}, sc, { isGeoLocMode: true })), null);
    assert.strictEqual(C.calcula(Object.assign({}, sc, { comparar: '2024' })), null);
    console.log('prueba_controles: comparación de dos años correcta');
}

// Proyección del worker de geometrías: la cónica conforme nacional de RHEMX debe dar los mismos puntos que d3
{
    const vm = require('vm');
    const js = path.join(__dirname, '..', 'siarhe-dataviz', 'public', 'js');
    const ctx = { self: {}, console }; ctx.window = ctx; vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(path.join(js, 'libs', 'd3.v7.min.js'), 'utf8'), ctx);
    vm.runInContext(fs.readFileSync(path.join(js, 'siarhe-geo-worker.js'), 'utf8') + '\nthis.creaProyeccion = creaProyeccion;', ctx);
    const prm = { parallels: [17.5, 29.5], rotate: [102, 0], scale: 2548.8484, translate: [926.0453, 1551.0627] };
    const d = ctx.d3.geoConicConformal().parallels(prm.parallels).rotate(prm.rotate).scale(prm.scale).translate(prm.translate);
    const w = ctx.creaProyeccion(Object.assign({ tipo: 'conica' }, prm));
    [[-117.1, 32.5], [-86.7, 21.1], [-99.13, 19.43], [-92.1, 14.6], [-110.3, 24.1]].forEach(([lon, lat]) => {
        const a = d([lon, lat]), b = w(lon, lat);
        assert.ok(Math.abs(a[0] - b[0]) < 1e-9 && Math.abs(a[1] - b[1]) < 1e-9, 'cónica del worker distinta de d3 en ' + lon + ',' + lat);
    });
    const m = ctx.d3.geoMercator().scale(1000).translate([800, 450]), wm = ctx.creaProyeccion({ scale: 1000, translate: [800, 450] });
    const a = m([-99, 19]), b = wm(-99, 19);
    assert.ok(Math.abs(a[0] - b[0]) < 1e-9 && Math.abs(a[1] - b[1]) < 1e-9, 'Mercator del worker distinto de d3');
    console.log('prueba_controles: proyección del worker igual a la de d3 (cónica de RHEMX y Mercator)');
}
