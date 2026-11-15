/* /public/js/siarhe-posicion.js */
/**
 * SIARHE DataViz - Marcadores de posición por tema
 * ------------------------------------------------------------------
 * Fila «Marcadores de posición» con el mismo recorrido que RHEMX:
 *   Ver  (Sin marcadores | Establecimientos de Salud | Clínicas | Otros marcadores)
 *   Clínica  (Catéteres | Heridas | Ambas)                         solo con Clínicas
 *   Por  (nivel de atención | institución, del año o de toda la serie «(todas)»)
 *   Instituciones y Niveles  (selección múltiple, leídos de la capa)
 *
 * Los establecimientos y las clínicas son del sistema: sus archivos son los de los marcadores con grupo
 * `establecimientos` y las claves CATETER y HERIDAS. Cada combinación que se dibuja (nivel, institución, tipo de
 * clínica) se registra como un marcador virtual (clave que empieza con «@»), con su rótulo, su estilo y sus puntos,
 * de modo que el mapa, la leyenda, las fichas y la medición lo tratan como cualquier otro marcador.
 *
 * Estilo: el color es el del nivel de atención (el de los marcadores ESTAB_* de Personalizar); la forma es la del
 * nivel, la de la institución (por institución, en el orden en que se eligieron) o la del tipo de clínica
 * (catéteres triángulo, heridas triángulo invertido, las dos en la misma unidad estrella).
 */
(function(app) {
    'use strict';

    const ORDEN_NIVELES = ['Primer Nivel', 'Segundo Nivel', 'Tercer Nivel', 'No Aplica', 'No Especificado'];
    const ESTILO_NIVEL = {
        'primer nivel': { shape: 'cross', fill: '#1E5B4F', stroke: '#ffffff' },
        'segundo nivel': { shape: 'cross', fill: '#A57F2C', stroke: '#ffffff' },
        'tercer nivel': { shape: 'cross', fill: '#9B2247', stroke: '#ffffff' },
        'no aplica': { shape: 'triangle', fill: '#57595C', stroke: '#ffffff' },
        'no especificado': { shape: 'circle', fill: '#475569', stroke: '#ffffff' }
    };
    const ESTILO_OTRO = { shape: 'circle', fill: '#475569', stroke: '#ffffff' };
    // Orden de RHEMX (ORDEN_FORMAS_INSTITUCION): al agotarse, se vuelve a empezar con el color más claro
    const FORMAS_INSTITUCION = ['cross', 'circle', 'square', 'triangle', 'diamond', 'star', 'wye', 'triangle2', 'diamond2', 'square2', 'ellipse', 'plus', 'times', 'asterisk'];
    const CLINICAS = [
        { id: 'cateteres', clave: 'CATETER', bit: 1, nombre: 'Catéteres' },
        { id: 'heridas', clave: 'HERIDAS', bit: 2, nombre: 'Heridas' }
    ];
    const FORMA_CLINICA = { 1: 'triangle', 2: 'triangle2', 3: 'star' };
    const NOMBRE_CLINICA = { 1: 'Clínica de catéteres', 2: 'Clínica de heridas', 3: 'Clínicas de catéteres y de heridas' };
    const MODOS = [
        { id: 'nivel', por: 'nivel', serie: 'anio', nombre: 'Por nivel de atención' },
        { id: 'institucion', por: 'institucion', serie: 'anio', nombre: 'Por institución' },
        { id: 't_nivel', por: 'nivel', serie: 'todas', nombre: 'Por nivel de atención (todas)' },
        { id: 't_institucion', por: 'institucion', serie: 'todas', nombre: 'Por institución (todas)' }
    ];
    const SIN_NIVEL = 'No Especificado', SIN_INSTITUCION = 'Sin institución';

    const norm = function(t) {
        return String(t == null ? '' : t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[_\s]+/g, ' ').trim();
    };
    const modoDe = function(id) { return MODOS.find(function(m) { return m.id === id; }) || null; };
    const modoDeId = modoDe;
    const clinicasDe = function(eleccion) { return CLINICAS.filter(function(c) { return eleccion === 'ambas' || c.id === eleccion; }); };
    const ordenNivel = function(a, b) {
        const ia = ORDEN_NIVELES.indexOf(a), ib = ORDEN_NIVELES.indexOf(b);
        if (ia !== ib) return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
        return a < b ? -1 : (a > b ? 1 : 0);
    };
    // Aclara un color #rrggbb hacia el blanco (rondas de formas de institución agotadas)
    const aclara = function(hex, ronda) {
        if (!ronda || !/^#[0-9a-f]{6}$/i.test(hex)) return hex;
        const t = Math.min(0.6, 0.28 * ronda);
        const c = [1, 3, 5].map(function(i) { const v = parseInt(hex.substr(i, 2), 16); return Math.round(v + (255 - v) * t).toString(16).padStart(2, '0'); });
        return '#' + c.join('');
    };

    app.pos = {
        MODOS: MODOS, CLINICAS: CLINICAS, norm: norm,

        anio: function(state) {
            return String((state.base && state.base.anio) || (state.containerEl && state.containerEl.dataset.anio) || '');
        },

        /** Clave de un marcador del grupo «establecimientos» con archivo: todos comparten la capa del catálogo CLUES. */
        claveEstab: function(state) {
            return Object.keys(state.markerLabels || {}).find(function(k) {
                const m = state.markerLabels[k] || {};
                return k.charAt(0) !== '@' && m.grupo === 'establecimientos' && m.tipo === 'posicion' && state.markerUrls && state.markerUrls[k];
            }) || null;
        },

        /** Archivo de los establecimientos para el año que se ve (con la regla general: el año, o el anterior más cercano). */
        urlEstab: function(state) {
            const k = app.pos.claveEstab(state);
            return k ? app.controls.markerUrl(state, k) : '';
        },

        /** Archivo de una clínica: el del año exacto (serie «anio») o el de toda la historia (serie «todas»). Sin archivo, ''. */
        urlClinica: function(state, clinica, serie) {
            const urls = state.markerUrls ? state.markerUrls[clinica.clave] : null;
            if (!urls) return '';
            if (typeof urls === 'string') return serie === 'todas' ? urls : '';
            return (serie === 'todas' ? urls.total : urls[app.pos.anio(state)]) || '';
        },

        hayClinica: function(state, clinica) {
            return !!(app.pos.urlClinica(state, clinica, 'anio') || app.pos.urlClinica(state, clinica, 'todas'));
        },

        /** ¿Hay datos para ese modo («Por») con lo elegido en Ver y Clínica? */
        modoValido: function(state, pos, modo) {
            if (pos.ver === 'establecimientos') return modo.serie === 'anio' && !!app.pos.urlEstab(state);
            if (pos.ver === 'clinicas') return clinicasDe(pos.clinica).some(function(c) { return !!app.pos.urlClinica(state, c, modo.serie); });
            return false;
        },

        /** Archivos que alimentan lo elegido: [{ url, bit, archivo }] (bit 0 = establecimientos, 1 catéteres, 2 heridas). */
        fuentes: function(state, pos) {
            const modo = modoDe(pos.modo);
            if (!modo) return [];
            if (pos.ver === 'establecimientos') {
                const k = app.pos.claveEstab(state), url = app.pos.urlEstab(state);
                return url ? [{ url: url, bit: 0, archivo: (state.markerLabels[k] || {}).archivo || '' }] : [];
            }
            if (pos.ver === 'clinicas') {
                return clinicasDe(pos.clinica).map(function(c) {
                    return { url: app.pos.urlClinica(state, c, modo.serie), bit: c.bit, archivo: (state.markerLabels[c.clave] || {}).archivo || '' };
                }).filter(function(f) { return !!f.url; });
            }
            return [];
        },

        /**
         * Une las capas: [{ bit, archivo, points }] -> [{ p, bits, archivos }]. Una unidad que está en las dos clínicas
         * (misma CLUES o, sin CLUES, mismas coordenadas) queda una sola vez con bits = 3.
         */
        combina: function(capas) {
            const mapa = new Map(), lista = [];
            capas.forEach(function(capa) {
                (capa.points || []).forEach(function(p, i) {
                    const id = capa.bit ? (p.clues ? 'c:' + p.clues : 'x:' + p.lat + ',' + p.lon) : 'e:' + i;
                    let it = capa.bit ? mapa.get(id) : null;
                    if (!it) {
                        it = { p: p, bits: 0, archivos: [] };
                        if (capa.bit) mapa.set(id, it);
                        lista.push(it);
                    }
                    it.bits |= capa.bit;
                    if (capa.archivo && it.archivos.indexOf(capa.archivo) === -1) it.archivos.push(capa.archivo);
                });
            });
            return lista;
        },

        /** Estilo de cada nivel de atención: el de su marcador ESTAB_* (Personalizar) o el de RHEMX. Clave = nivel normalizado. */
        estilosNivel: function(state) {
            const out = {};
            Object.keys(ESTILO_NIVEL).forEach(function(n) { out[n] = ESTILO_NIVEL[n]; });
            Object.keys(state.markerLabels || {}).forEach(function(k) {
                const m = state.markerLabels[k] || {};
                if (k.charAt(0) === '@' || m.grupo !== 'establecimientos' || !m.filtro_val) return;
                const e = (state.markerStyles || {})[k];
                if (e && e.fill) out[norm(m.filtro_val)] = { shape: e.shape || 'circle', fill: e.fill, stroke: e.stroke || '#ffffff' };
            });
            // Lo que el visitante cambió en «Estilo de marcadores» (solo en su vista) va encima
            const propios = (state.pos && state.pos.estilos) || {};
            Object.keys(propios).forEach(function(n) { out[n] = Object.assign({}, out[n] || ESTILO_OTRO, propios[n]); });
            return out;
        },

        /**
         * Reparte las unidades en los grupos que se dibujan según lo elegido. Función pura (probada en tests/).
         *   items: [{ p: { nivel_atencion, institucion, ... }, bits, archivos }]
         *   pos:   { ver, clinica, modo, nivSel: Set, insSel: Set (en orden de elección) }
         *   estilos: { nivelNormalizado: { shape, fill, stroke } }
         * Devuelve { grupos: [{ id, label, estilo, archivo, puntos }], niveles: [{ valor, n }], instituciones: [{ valor, n }] }.
         * Los conteos de instituciones son de toda la capa; los de niveles, de las instituciones elegidas cuando se agrupa
         * por institución.
         */
        clasifica: function(items, pos, estilos) {
            const modo = modoDe(pos.modo) || MODOS[0];
            const porInst = modo.por === 'institucion';
            const nivelDe = function(it) { return it.p.nivel_atencion || SIN_NIVEL; };
            const instDe = function(it) { return it.p.institucion || SIN_INSTITUCION; };
            const cInst = new Map(), cNiv = new Map();
            items.forEach(function(it) {
                const i = instDe(it), n = nivelDe(it);
                cInst.set(i, (cInst.get(i) || 0) + 1);
                if (!porInst || pos.insSel.has(i)) cNiv.set(n, (cNiv.get(n) || 0) + 1);
            });
            const niveles = Array.from(cNiv.keys()).sort(ordenNivel).map(function(v) { return { valor: v, n: cNiv.get(v) }; });
            const instituciones = Array.from(cInst.keys()).sort(function(a, b) { return a.localeCompare(b, 'es'); }).map(function(v) { return { valor: v, n: cInst.get(v) }; });

            const ordenInst = Array.from(pos.insSel);
            const formaPorClinica = pos.ver === 'clinicas' && (!porInst || pos.clinica === 'ambas');
            const grupos = new Map();
            items.forEach(function(it) {
                const n = nivelDe(it), i = instDe(it);
                if (!pos.nivSel.has(n)) return;
                if (porInst && !pos.insSel.has(i)) return;
                const id = (porInst ? 'i|' + i + '|' : 'n|') + n + '|' + (formaPorClinica ? it.bits : 0);
                let g = grupos.get(id);
                if (!g) {
                    const base = estilos[norm(n)] || ESTILO_OTRO;
                    const estilo = { shape: base.shape, fill: base.fill, fill2: '', stroke: base.stroke };
                    let label;
                    if (porInst) {
                        const idx = Math.max(0, ordenInst.indexOf(i));
                        estilo.shape = FORMAS_INSTITUCION[idx % FORMAS_INSTITUCION.length];
                        estilo.fill = aclara(base.fill, Math.floor(idx / FORMAS_INSTITUCION.length));
                        label = i + ' · ' + n;
                    } else {
                        label = (it.bits ? NOMBRE_CLINICA[it.bits] : 'Establecimientos de Salud') + ': ' + n;
                    }
                    if (formaPorClinica && it.bits) {
                        estilo.shape = FORMA_CLINICA[it.bits];
                        if (porInst) { estilo.fill = base.fill; label += ' (' + NOMBRE_CLINICA[it.bits].replace(/^Cl[ií]nicas? de /, '') + ')'; }
                    }
                    g = { id: id, label: label, estilo: estilo, archivo: it.archivos[0] || '', archivos: [], nivel: n, inst: porInst ? i : '', bits: formaPorClinica ? it.bits : 0, puntos: [] };
                    grupos.set(id, g);
                }
                it.archivos.forEach(function(a) { if (g.archivos.indexOf(a) === -1) g.archivos.push(a); });
                g.puntos.push(it.p);
            });
            const lista = Array.from(grupos.values()).sort(function(a, b) {
                const ia = ordenInst.indexOf(a.inst), ib = ordenInst.indexOf(b.inst);
                if (ia !== ib) return ia - ib;
                return ordenNivel(a.nivel, b.nivel) || (a.bits - b.bits);
            });
            return { grupos: lista, niveles: niveles, instituciones: instituciones };
        },

        // ------------------------------------------------------------------
        // Estado y controles
        // ------------------------------------------------------------------

        nuevo: function() {
            return { ver: '', clinica: 'cateteres', modo: 'nivel', nivSel: new Set(), insSel: new Set(), reinicia: true, nivAuto: false, pendNiveles: null, gen: 0, seq: 0, claves: [], opciones: { niveles: [], instituciones: [] } };
        },

        /** Selector múltiple (casillas, filtro, «Todos» / «Ninguno») con el menú de los desplegables de marcadores. */
        _multi: function(container, fila, titulo, vacio, alCambiar) {
            const g = document.createElement('div');
            g.className = 'siarhe-control-group siarhe-ctl-marcadores';
            const l = document.createElement('label'); l.textContent = titulo; g.appendChild(l);
            const field = document.createElement('div'); field.className = 'mc-field';
            const trigger = document.createElement('div'); trigger.className = 'mc-trigger is-placeholder';
            const menu = document.createElement('div'); menu.className = 'mc-menu';
            const caja = document.createElement('div'); caja.className = 'siarhe-cs-search';
            const buscar = document.createElement('input'); buscar.type = 'text'; buscar.placeholder = 'Filtrar...'; buscar.setAttribute('aria-label', 'Filtrar ' + titulo.toLowerCase());
            caja.appendChild(buscar);
            const acciones = document.createElement('div'); acciones.className = 'mc-acciones';
            const bTodos = document.createElement('button'); bTodos.type = 'button'; bTodos.textContent = 'Todos';
            const bNinguno = document.createElement('button'); bNinguno.type = 'button'; bNinguno.textContent = 'Ninguno';
            acciones.appendChild(bTodos); acciones.appendChild(bNinguno);
            const lista = document.createElement('div'); lista.className = 'siarhe-cs-options';
            menu.appendChild(caja); menu.appendChild(acciones); menu.appendChild(lista);
            field.appendChild(trigger); field.appendChild(menu); g.appendChild(field); fila.appendChild(g);
            [caja, acciones, lista].forEach(function(el) { el.addEventListener('click', function(e) { e.stopPropagation(); }); });

            let opciones = [], sel = new Set();
            const visibles = function() {
                const q = norm(buscar.value);
                return opciones.filter(function(o) { return norm(o.valor).indexOf(q) !== -1; });
            };
            const resumen = function() {
                const activas = opciones.filter(function(o) { return sel.has(o.valor); });
                if (!activas.length) return '';
                if (activas.length === 1) return activas[0].valor;
                return activas.length === opciones.length ? 'Todos (' + activas.length + ')' : activas.length + ' seleccionados';
            };
            const pinta = function() {
                const r = resumen();
                trigger.innerHTML = '';
                const s = document.createElement('span'); s.textContent = r || vacio; trigger.appendChild(s);
                trigger.classList.toggle('is-placeholder', !r);
                lista.innerHTML = '';
                const items = visibles();
                if (!items.length) {
                    const v = document.createElement('div'); v.className = 'mc-vacio'; v.textContent = opciones.length ? 'Sin coincidencias' : 'Sin datos para esta selección';
                    lista.appendChild(v);
                }
                items.forEach(function(o) {
                    const opt = document.createElement('label'); opt.className = 'mc-option';
                    const chk = document.createElement('input'); chk.type = 'checkbox'; chk.checked = sel.has(o.valor);
                    const t = document.createElement('span'); t.textContent = o.valor;
                    const n = document.createElement('em'); n.className = 'mc-n'; n.textContent = Number(o.n).toLocaleString('es-MX');
                    chk.addEventListener('change', function() { if (chk.checked) sel.add(o.valor); else sel.delete(o.valor); pinta(); alCambiar(); });
                    opt.appendChild(chk); opt.appendChild(t); opt.appendChild(n);
                    lista.appendChild(opt);
                });
            };
            buscar.addEventListener('input', pinta);
            bTodos.addEventListener('click', function() { visibles().forEach(function(o) { sel.add(o.valor); }); pinta(); alCambiar(); });
            bNinguno.addEventListener('click', function() {
                if (norm(buscar.value)) visibles().forEach(function(o) { sel.delete(o.valor); }); else sel.clear();
                pinta(); alCambiar();
            });
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const abierto = menu.classList.contains('open');
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(function(m) { m.classList.remove('open'); });
                if (!abierto) { menu.classList.add('open'); buscar.value = ''; pinta(); setTimeout(function() { buscar.focus(); }, 50); }
            });
            return { el: g, set: function(ops, seleccion) { opciones = ops; sel = seleccion; pinta(); } };
        },

        _select: function(fila, id, titulo) {
            const g = document.createElement('div');
            g.className = 'siarhe-control-group siarhe-ctl-marcadores';
            const l = document.createElement('label'); l.textContent = titulo; g.appendChild(l);
            const s = document.createElement('select'); s.className = 'siarhe-metric-select'; s.id = id;
            g.appendChild(s); fila.appendChild(g);
            return { el: g, sel: s };
        },

        /**
         * Botón y panel «Estilo de marcadores», como el de RHEMX: forma, color de relleno y de borde de cada nivel de
         * atención, solo para la vista del visitante (`pos.estilos`, no se guarda en el sitio). Cuando la forma la decide
         * la institución o el tipo de clínica, el selector de forma se oculta. Devuelve { sync(visible) }.
         */
        _panelEstilos: function(container, state, fila) {
            const FORMAS = app.utils.NOMBRES_FORMA;
            const img = container.dataset.img || '';
            const hex = function(c) {
                if (/^#[0-9a-f]{6}$/i.test(c || '')) return c;
                const col = (typeof d3 !== 'undefined' && c) ? d3.color(c) : null;
                return col ? col.formatHex() : '#000000';
            };

            const grupo = document.createElement('div');
            grupo.className = 'siarhe-control-group siarhe-control-group--action';
            grupo.innerHTML = '<button type="button" class="siarhe-btn siarhe-estilos-btn" aria-expanded="false" title="Forma y colores de los marcadores">' +
                (img ? '<span class="siarhe-ctl-icon" aria-hidden="true" style="--ico: url(&quot;' + img + 'icon-paleta.svg&quot;)"></span> ' : '') +
                '<span class="siarhe-btn-texto">Estilo de marcadores</span></button>';
            fila.appendChild(grupo);
            const boton = grupo.querySelector('button');

            const panel = document.createElement('div');
            panel.className = 'siarhe-palette-panel siarhe-estilos-panel';
            panel.hidden = true;
            panel.setAttribute('role', 'group');
            panel.setAttribute('aria-label', 'Estilo de los marcadores');
            panel.innerHTML = '<div class="siarhe-palette-head"><strong>Estilo de marcadores</strong><span>Solo cambia tu vista; no se guarda en el sitio.</span></div>' +
                '<div class="siarhe-estilos-lista"></div>' +
                '<div class="siarhe-palette-actions"><button type="button" class="siarhe-btn siarhe-estilos-restaurar">Restaurar</button>' +
                '<button type="button" class="siarhe-btn siarhe-estilos-cerrar">Cerrar</button></div>';
            fila.parentNode.insertBefore(panel, fila.nextSibling);
            const lista = panel.querySelector('.siarhe-estilos-lista');

            const cambia = function(nivel, campo, valor) {
                const pos = state.pos, n = norm(nivel);
                pos.estilos = pos.estilos || {};
                pos.estilos[n] = Object.assign({}, app.pos.estilosNivel(state)[n] || ESTILO_OTRO, pos.estilos[n]);
                pos.estilos[n][campo] = valor;
                app.pos.apply(container, state);
            };
            ORDEN_NIVELES.forEach(function(nivel) {
                const f = document.createElement('div');
                f.className = 'siarhe-estilo-fila'; f.dataset.nivel = nivel;
                f.innerHTML = '<strong>' + nivel + '</strong>' +
                    '<label>Forma <select>' + Object.keys(FORMAS).map(function(id) { return '<option value="' + id + '">' + FORMAS[id] + '</option>'; }).join('') + '</select></label>' +
                    '<label>Relleno <input type="color" data-campo="fill"></label><label>Borde <input type="color" data-campo="stroke"></label>';
                lista.appendChild(f);
                f.querySelector('select').addEventListener('change', function(e) { cambia(nivel, 'shape', e.target.value); });
                f.querySelectorAll('input').forEach(function(i) { i.addEventListener('input', function() { cambia(nivel, i.dataset.campo, i.value); }); });
            });

            const refresca = function() {
                const pos = state.pos, estilos = app.pos.estilosNivel(state);
                const sinForma = pos.ver === 'clinicas' || (modoDe(pos.modo) || {}).por === 'institucion';
                lista.querySelectorAll('.siarhe-estilo-fila').forEach(function(f) {
                    const e = estilos[norm(f.dataset.nivel)] || ESTILO_OTRO;
                    f.querySelector('select').value = FORMAS[e.shape] ? e.shape : 'circle';
                    f.toggleAttribute('data-sin-forma', sinForma);
                    f.querySelector('[data-campo="fill"]').value = hex(e.fill);
                    f.querySelector('[data-campo="stroke"]').value = hex(e.stroke);
                });
            };
            const abre = function(abierto) {
                panel.hidden = !abierto;
                boton.setAttribute('aria-expanded', String(abierto));
                if (abierto) refresca();
            };
            boton.addEventListener('click', function() { abre(panel.hidden); });
            panel.querySelector('.siarhe-estilos-cerrar').addEventListener('click', function() { abre(false); });
            panel.querySelector('.siarhe-estilos-restaurar').addEventListener('click', function() {
                state.pos.estilos = null;
                refresca();
                app.pos.apply(container, state);
            });

            return {
                sync: function(visible) {
                    grupo.hidden = !visible;
                    if (!visible) abre(false); else if (!panel.hidden) refresca();
                }
            };
        },

        /** Arma la fila «Marcadores de posición». La llama renderMain en lugar del desplegable por grupo. */
        build: function(container, state, wrapper) {
            const fila = wrapper.querySelector('.siarhe-controls-row[data-fila="posicion"]');
            if (!fila) return;
            const pos = state.pos = app.pos.nuevo();
            const hayEstab = !!app.pos.claveEstab(state);
            const hayClinicas = CLINICAS.some(function(c) { return !!(state.markerUrls && state.markerUrls[c.clave]) && !!state.markerLabels[c.clave]; });

            // Sin marcadores del sistema solo quedan los desplegables por grupo, como antes
            if (!hayEstab && !hayClinicas) {
                pos.ver = 'otros';
                app.controls.buildMarkerRow(container, state, wrapper, 'posicion', { soloOtros: true, marcador: true });
                return;
            }

            const ui = pos.ui = {};
            ui.ver = app.pos._select(fila, 'siarhe-pos-ver', 'Ver');
            ui.clinica = app.pos._select(fila, 'siarhe-pos-clinica', 'Clínica');
            ui.modo = app.pos._select(fila, 'siarhe-pos-modo', 'Por');
            const cambia = function() { app.pos.apply(container, state); };
            ui.inst = app.pos._multi(container, fila, 'Instituciones', 'Elige instituciones', cambia);
            // Al tocar los niveles la lista deja de seguir sola a «todos los que haya»
            ui.niv = app.pos._multi(container, fila, 'Niveles', 'Elige niveles', function() { pos.nivAuto = false; cambia(); });
            ui.estilos = app.pos._panelEstilos(container, state, fila);
            ui.otros = app.controls.buildMarkerRow(container, state, wrapper, 'posicion', { soloOtros: true }) || [];

            const opcion = function(v, t) { const o = document.createElement('option'); o.value = v; o.textContent = t; return o; };
            ui.ver.sel.appendChild(opcion('', 'Sin marcadores'));
            if (hayEstab) ui.ver.sel.appendChild(opcion('establecimientos', (state.markerGrupos || {}).establecimientos || 'Establecimientos de Salud'));
            if (hayClinicas) ui.ver.sel.appendChild(opcion('clinicas', (state.markerGrupos || {}).clinicas || 'Clínicas'));
            if (ui.otros.length) ui.ver.sel.appendChild(opcion('otros', 'Otros marcadores'));
            CLINICAS.forEach(function(c) { ui.clinica.sel.appendChild(opcion(c.id, c.nombre)); });
            ui.clinica.sel.appendChild(opcion('ambas', 'Ambas'));
            MODOS.forEach(function(m) { ui.modo.sel.appendChild(opcion(m.id, m.nombre)); });

            ui.ver.sel.addEventListener('change', function() { app.pos.elige(container, state, { ver: ui.ver.sel.value }); });
            ui.clinica.sel.addEventListener('change', function() { app.pos.elige(container, state, { clinica: ui.clinica.sel.value }); });
            ui.modo.sel.addEventListener('change', function() { app.pos.elige(container, state, { modo: ui.modo.sel.value }); });
            app.pos.sync(state);
        },

        /** Cambia Ver, Clínica o Por desde los selectores (o desde un enlace) y vuelve a dibujar. */
        elige: function(container, state, cambio) {
            const pos = state.pos;
            if (!pos) return Promise.resolve();
            if (cambio.ver !== undefined && cambio.ver !== pos.ver) {
                app.pos._apagaReales(container, state);
                pos.ver = cambio.ver;
                if (pos.ver === 'clinicas' && !clinicasDe(pos.clinica).some(function(c) { return app.pos.hayClinica(state, c); })) {
                    const c = CLINICAS.find(function(x) { return app.pos.hayClinica(state, x); });
                    if (c) pos.clinica = c.id;
                }
            }
            if (cambio.clinica !== undefined) pos.clinica = cambio.clinica;
            if (cambio.modo !== undefined) pos.modo = cambio.modo;
            pos.reinicia = true;
            return app.pos.apply(container, state);
        },

        /** Apaga los marcadores de posición reales (los de «Otros marcadores»): en la fila solo se ve una familia a la vez. */
        _apagaReales: function(container, state) {
            Array.from(state.activeMarkers).forEach(function(k) {
                if (k.charAt(0) !== '@' && (state.markerLabels[k] || {}).tipo === 'posicion') state.activeMarkers.delete(k);
            });
            const fila = container.querySelector('.siarhe-controls-row[data-fila="posicion"]');
            if (fila) fila.querySelectorAll('.mc-check').forEach(function(chk) { chk.checked = false; });
        },

        /** Pone los selectores de acuerdo con el estado: opciones sin datos deshabilitadas y controles que no aplican ocultos. */
        sync: function(state) {
            const pos = state.pos, ui = pos && pos.ui;
            if (!ui) return;
            const sistema = pos.ver === 'establecimientos' || pos.ver === 'clinicas';
            // «Por»: si el modo elegido no tiene datos (p. ej. al cambiar de año), pasa al primero que sí
            if (sistema && !(modoDe(pos.modo) && app.pos.modoValido(state, pos, modoDe(pos.modo)))) {
                const m = MODOS.find(function(x) { return app.pos.modoValido(state, pos, x); });
                if (m) { pos.modo = m.id; pos.reinicia = true; }
            }
            ui.ver.sel.value = pos.ver;
            Array.from(ui.clinica.sel.options).forEach(function(o) {
                o.disabled = !clinicasDe(o.value).some(function(c) { return app.pos.hayClinica(state, c); });
            });
            ui.clinica.sel.value = pos.clinica;
            ui.clinica.sel.title = 'Las clínicas sin datos en ' + app.pos.anio(state) + ' ni de toda la historia están deshabilitadas';
            Array.from(ui.modo.sel.options).forEach(function(o) {
                const m = modoDe(o.value);
                o.hidden = pos.ver === 'establecimientos' && m.serie === 'todas';
                o.disabled = o.hidden || !app.pos.modoValido(state, pos, m);
            });
            ui.modo.sel.value = pos.modo;
            ui.modo.sel.title = sistema ? 'Las opciones sin datos en ' + app.pos.anio(state) + ' están deshabilitadas' : '';
            const porInst = sistema && (modoDe(pos.modo) || {}).por === 'institucion';
            ui.clinica.el.hidden = pos.ver !== 'clinicas';
            ui.modo.el.hidden = !sistema;
            ui.inst.el.hidden = !porInst;
            ui.niv.el.hidden = !sistema;
            if (ui.estilos) ui.estilos.sync(sistema);
            ui.otros.forEach(function(el) { el.hidden = pos.ver !== 'otros'; });
        },

        /** Quita del mapa los marcadores virtuales de la selección anterior. */
        _limpia: function(state) {
            const pos = state.pos;
            (pos.claves || []).forEach(function(k) {
                state.activeMarkers.delete(k);
                delete state.markersData[k]; delete state.markerLabels[k]; delete state.markerStyles[k];
            });
            pos.claves = [];
        },

        _cargando: function(container, texto) {
            const loading = container.querySelector('.siarhe-loading-overlay');
            if (!loading) return;
            if (!texto) { loading.style.display = 'none'; return; }
            const msg = loading.querySelector('p');
            if (msg) msg.textContent = texto;
            loading.style.cssText += ';position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(248,250,252,0.9);display:flex;flex-direction:column;justify-content:center;align-items:center;z-index:100;';
        },

        /** Capa completa de un archivo (ya filtrada a la entidad de la página), como marcador de origen oculto. */
        _capa: async function(container, state, fuente) {
            const k = '@src:' + fuente.url;
            if (!state.markersData[k]) {
                state.markerLabels[k] = { label: 'Marcadores', tipo: 'posicion', archivo: fuente.archivo, oculto: true };
                state.markerUrls[k] = fuente.url;
                await app.controls.loadMarkerData(k, state, container);
            }
            return state.markersData[k] || [];
        },

        /** Descarga lo necesario, reparte las unidades y las dibuja. Se llama al cambiar cualquier control o el año. */
        apply: async function(container, state) {
            const pos = state.pos;
            if (!pos || !pos.ui) return;
            const turno = ++pos.seq;
            app.pos.sync(state);
            const sistema = pos.ver === 'establecimientos' || pos.ver === 'clinicas';
            const pinta = function() {
                if (app.map) { app.map.updateMarkers(state); app.map.renderMarkerLegend(state); }
                app.controls.updateMarkerDropdownText(state);
                if (app.urlState) app.urlState.write(container, state);
            };
            if (!sistema) { app.pos._limpia(state); pos.vistos = null; pinta(); return; }

            const fuentes = app.pos.fuentes(state, pos);
            const capas = [];
            const pendientes = fuentes.filter(function(f) { return !state.markersData['@src:' + f.url]; });
            if (pendientes.length) { app.pos._cargando(container, 'Cargando marcadores...'); await app.utils.nextFrame(); }
            for (const f of fuentes) {
                try { capas.push({ bit: f.bit, archivo: f.archivo, points: await app.pos._capa(container, state, f) }); }
                catch (err) { console.error('[SIARHE] No se pudo cargar la capa de marcadores ' + f.url, err); }
            }
            if (pendientes.length) app.pos._cargando(container, null);
            if (turno !== pos.seq) return; // el usuario ya eligió otra cosa mientras se descargaba

            const items = app.pos.combina(capas);
            const estilos = app.pos.estilosNivel(state);
            let res = app.pos.clasifica(items, pos, estilos);
            if (pos.reinicia) {
                // Selección inicial: las instituciones se eligen siempre (cada una toma su forma en el orden en que se marca);
                // los niveles van todos en las clínicas (pocas unidades) y ninguno en los establecimientos (miles)
                const todo = pos.ver === 'clinicas';
                pos.insSel = new Set();
                res = app.pos.clasifica(items, pos, estilos);
                const pend = pos.pendNiveles;
                pos.nivSel = new Set(res.niveles.map(function(o) { return o.valor; }).filter(function(v) { return pend ? pend.has(norm(v)) : todo; }));
                // Por institución (y en las clínicas) los niveles empiezan todos marcados y siguen a las instituciones elegidas
                pos.nivAuto = !pend && (todo || (modoDeId(pos.modo) || {}).por === 'institucion');
                pos.pendNiveles = null;
                pos.reinicia = false;
                res = app.pos.clasifica(items, pos, estilos);
            }
            if (pos.nivAuto) {
                pos.nivSel = new Set(res.niveles.map(function(o) { return o.valor; }));
                res = app.pos.clasifica(items, pos, estilos);
            }
            pos.opciones = { niveles: res.niveles, instituciones: res.instituciones };
            pos.ui.inst.set(res.instituciones, pos.insSel);
            pos.ui.niv.set(res.niveles, pos.nivSel);

            // Lo que quedo a la vista, para la leyenda: totales, tipos de clinica, niveles e instituciones con unidades
            const vistos = { total: 0, bits: { 1: 0, 2: 0, 3: 0 }, niveles: [], instituciones: [] };
            res.grupos.forEach(function(g) {
                vistos.total += g.puntos.length;
                if (vistos.niveles.indexOf(g.nivel) === -1) vistos.niveles.push(g.nivel);
                if (g.inst && vistos.instituciones.indexOf(g.inst) === -1) vistos.instituciones.push(g.inst);
            });
            if (pos.ver === 'clinicas') items.forEach(function(it) {
                const n = it.p.nivel_atencion || SIN_NIVEL, i = it.p.institucion || SIN_INSTITUCION;
                if (pos.nivSel.has(n) && ((modoDe(pos.modo) || {}).por !== 'institucion' || pos.insSel.has(i))) vistos.bits[it.bits]++;
            });
            pos.vistos = vistos;

            app.pos._limpia(state);
            const gen = ++pos.gen;
            res.grupos.forEach(function(g, i) {
                const k = '@pos:' + gen + ':' + i;
                state.markerLabels[k] = { label: g.label, tipo: 'posicion', archivo: g.archivo, archivos: g.archivos, virtual: true };
                state.markerStyles[k] = g.estilo;
                state.markersData[k] = g.puntos.map(function(p) { const c = Object.assign({}, p); c.tipo = k; delete c._uid; return c; });
                state.activeMarkers.add(k);
                pos.claves.push(k);
            });
            pinta();
        },

        /** Catálogo de establecimientos del año que se ve (ya filtrado a la entidad de la página), para el buscador. */
        unidades: function(container, state) {
            const k = app.pos.claveEstab(state), url = app.pos.urlEstab(state);
            if (!url) return Promise.resolve([]);
            return app.pos._capa(container, state, { url: url, bit: 0, archivo: (state.markerLabels[k] || {}).archivo || '' });
        },

        /**
         * Deja a la vista el marcador de una unidad del catálogo (resultado del buscador): pasa a «Establecimientos de
         * Salud» si se veía otra cosa y agrega su nivel (y su institución, si se agrupa por institución) a lo elegido.
         * Devuelve el punto ya dibujado, o null si no se pudo.
         */
        muestraUnidad: async function(container, state, p) {
            const pos = state.pos;
            if (!pos || !pos.ui || !p || !app.pos.urlEstab(state)) return null;
            const nivel = p.nivel_atencion || SIN_NIVEL, inst = p.institucion || SIN_INSTITUCION;
            if (pos.ver !== 'establecimientos') {
                app.pos._apagaReales(container, state);
                pos.ver = 'establecimientos';
                pos.modo = 'nivel';
                pos.nivSel = new Set();
                pos.insSel = new Set();
                pos.nivAuto = false;
            }
            pos.reinicia = false;
            if ((modoDe(pos.modo) || {}).por === 'institucion') pos.insSel.add(inst);
            pos.nivSel.add(nivel);
            await app.pos.apply(container, state);
            for (const k of pos.claves) {
                const d = (state.markersData[k] || []).find(function(x) {
                    return p.clues ? x.clues === p.clues : (x.lat === p.lat && x.lon === p.lon && x.nombre === p.nombre);
                });
                if (d) return d;
            }
            return null;
        },

        /**
         * Modelo de la leyenda de los marcadores por tema, igual que la de RHEMX: titulo, resumen y grupos de simbolos.
         * Por nivel: un grupo con el simbolo de cada nivel. Por institucion: «Nivel de atencion (color)» y «Institucion (forma)»
         * en columnas. Las dos clinicas juntas: «Nivel de atencion (color)» y «Clinica (forma)». Devuelve null si no hay nada.
         *   { titulo, texto, grupos: [{ titulo, columnas, items: [{ texto, estilo: { shape, fill, stroke } }] }] }
         */
        leyenda: function(state, lugar) {
            const pos = state.pos, v = pos && pos.vistos;
            if (!pos || !v || !v.total || (pos.ver !== 'establecimientos' && pos.ver !== 'clinicas')) return null;
            const modo = modoDe(pos.modo) || MODOS[0];
            const porInst = modo.por === 'institucion', clinicas = pos.ver === 'clinicas', todas = modo.serie === 'todas';
            const num = function(n) { return Number(n).toLocaleString('es-MX'); };
            const alcance = lugar ? 'en ' + lugar : 'en la República Mexicana';
            const anio = app.pos.anio(state);
            const estilos = app.pos.estilosNivel(state);
            const GRIS = '#475569';

            const nombre = !porInst ? 'por nivel de atención'
                : (v.instituciones.length === 1 ? 'de ' + v.instituciones[0] : 'de ' + v.instituciones.length + ' instituciones');
            const nombreClinicas = { cateteres: 'Clínicas con reportes de catéteres', heridas: 'Clínicas de heridas', ambas: 'Clínicas de catéteres y de heridas' }[pos.clinica];
            const titulo = (clinicas ? nombreClinicas : 'Unidades de salud') + ' ' + nombre;
            let texto;
            if (!clinicas) texto = num(v.total) + ' unidades en operación ' + alcance;
            else if (pos.clinica === 'ambas') texto = num(v.total) + ' clínicas ' + alcance + ': ' + num(v.bits[1]) + ' de catéteres, ' + num(v.bits[2]) + ' de heridas y ' + num(v.bits[3]) + ' con las dos (' + (todas ? 'toda la historia' : anio) + ')';
            else texto = num(v.total) + ' clínicas ' + (pos.clinica === 'heridas' ? 'de heridas' : 'con reportes') + ' ' + (todas ? 'en toda la historia' : 'en ' + anio) + ' ' + alcance;

            const tipoUnico = clinicas && pos.clinica !== 'ambas' ? (CLINICAS.find(function(c) { return c.id === pos.clinica; }) || {}).bit : 0;
            const niveles = v.niveles.slice().sort(ordenNivel);
            const corto = function(n) { return n.replace(' Nivel', ''); };
            const circulos = niveles.map(function(n) { const e = estilos[norm(n)] || ESTILO_OTRO; return { texto: corto(n), estilo: { shape: 'circle', fill: e.fill, stroke: e.stroke } }; });
            let grupos;
            if (clinicas && pos.clinica === 'ambas') {
                grupos = [
                    { titulo: 'Nivel de atención (color)', items: circulos },
                    { titulo: 'Clínica (forma)', items: [1, 2, 3].filter(function(b) { return v.bits[b] > 0; }).map(function(b) {
                        return { texto: NOMBRE_CLINICA[b].replace(/^Cl[ií]nicas? de /, '').replace(/^./, function(c) { return c.toUpperCase(); }).replace(' y de ', ' y '), estilo: { shape: FORMA_CLINICA[b], fill: GRIS, stroke: '#ffffff' } };
                    }) }
                ];
            } else if (!porInst) {
                grupos = [{ titulo: '', items: niveles.map(function(n) {
                    const e = estilos[norm(n)] || ESTILO_OTRO;
                    return { texto: corto(n), estilo: { shape: tipoUnico ? FORMA_CLINICA[tipoUnico] : e.shape, fill: e.fill, stroke: e.stroke } };
                }) }];
            } else {
                const orden = Array.from(pos.insSel);
                grupos = [
                    { titulo: 'Nivel de atención (color)', items: circulos },
                    { titulo: 'Institución (forma)', columnas: true, items: v.instituciones.map(function(nom) {
                        const idx = Math.max(0, orden.indexOf(nom));
                        return { texto: nom, estilo: { shape: FORMAS_INSTITUCION[idx % FORMAS_INSTITUCION.length], fill: aclara(GRIS, Math.floor(idx / FORMAS_INSTITUCION.length)), stroke: '#ffffff' } };
                    }) }
                ];
            }
            return { titulo: titulo, texto: texto, grupos: grupos };
        },

        /** Restablecer: sin marcadores. */
        reset: function(container, state) {
            const pos = state.pos;
            if (!pos || !pos.ui) return;
            pos.seq++;
            app.pos._limpia(state);
            pos.ver = ''; pos.reinicia = true; pos.pendNiveles = null; pos.vistos = null; pos.estilos = null;
            app.pos.sync(state);
        },

        // ------------------------------------------------------------------
        // Enlace (hash de la URL)
        // ------------------------------------------------------------------

        /** Valor de `p` en el enlace: ver~clinica~modo (sin las listas elegidas: al abrirlo se usa la selección inicial). */
        aEnlace: function(state) {
            const pos = state.pos;
            if (!pos || !pos.ui || (pos.ver !== 'establecimientos' && pos.ver !== 'clinicas')) return '';
            const partes = [pos.ver, pos.ver === 'clinicas' ? pos.clinica : '', pos.modo];
            if (pos.ver === 'establecimientos' && pos.nivSel.size) partes.push(Array.from(pos.nivSel).join('|'));
            return partes.join('~');
        },

        deEnlace: function(container, state, valor) {
            const pos = state.pos;
            if (!pos || !pos.ui || !valor) return Promise.resolve();
            const p = String(valor).split('~');
            if (p[0] !== 'establecimientos' && p[0] !== 'clinicas') return Promise.resolve();
            if (!Array.from(pos.ui.ver.sel.options).some(function(o) { return o.value === p[0]; })) return Promise.resolve();
            pos.ver = p[0];
            if (p[1] && (p[1] === 'ambas' || CLINICAS.some(function(c) { return c.id === p[1]; }))) pos.clinica = p[1];
            if (modoDe(p[2])) pos.modo = p[2];
            pos.pendNiveles = p[3] ? new Set(p[3].split('|').map(norm)) : null;
            pos.reinicia = true;
            return app.pos.apply(container, state);
        },

        /**
         * Enlaces anteriores (k=ESTAB_1,CATETER...): un marcador del sistema se traduce a la selección equivalente.
         * Devuelve true si la clave quedó atendida aquí (el llamador ya no la activa como marcador suelto).
         */
        adopta: function(state, clave) {
            const pos = state.pos, m = (state.markerLabels || {})[clave] || {};
            if (!pos || !pos.ui || m.tipo !== 'posicion') return false;
            if (m.grupo === 'establecimientos') {
                if (pos.ver !== 'establecimientos') { pos.ver = 'establecimientos'; pos.modo = 'nivel'; pos.pendNiveles = new Set(); }
                if (m.filtro_val && pos.pendNiveles) pos.pendNiveles.add(norm(m.filtro_val));
                pos.reinicia = true;
                return true;
            }
            const c = CLINICAS.find(function(x) { return x.clave === clave; });
            if (c && pos.ver !== 'establecimientos') {
                pos.clinica = pos.ver === 'clinicas' && pos.clinica !== c.id ? 'ambas' : c.id;
                pos.ver = 'clinicas';
                pos.reinicia = true;
                return true;
            }
            return false;
        }
    };
})(window.SiarheDataViz);
