/**
 * siarhe-dataviz/public/js/siarhe-table.js
 * ------------------------------------------------------------------
 * SIARHE DataViz - Módulo de Tabla (app.table)
 *
 * Tabla de datos de la vista, con las columnas y las opciones de la tabla del mapa de RHEMX
 * (rhemx/web/assets/js/geoespacial.js, dibujaTabla): lugar, clave, población, total de la unidad
 * y su tasa y, con un indicador distinto del total, el conteo y la tasa del indicador; al comparar
 * dos años (app.cambio), el valor de cada año, el cambio y el cambio en %. Se ordena por cualquier
 * columna y se descarga en CSV, Excel o JSON.
 *
 *   1. Configuración
 *   2. Utilidades
 *   3. Estructura (barra de opciones y tabla)
 *   4. Columnas y renglones
 *   5. Dibujo y orden
 *   6. Descargas
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    // ==========================================
    // 1. CONFIGURACIÓN
    // ==========================================
    // Columnas del total de la unidad de conteo (plazas o recursos humanos) en las bases de enfermería
    const COL_TOTAL = 'enfermeras_total';
    const COL_TASA_TOTAL = 'tasa_total';
    const SIN_DATO = 'S/D';
    // SheetJS se pide solo al descargar un Excel: primero la copia del plugin y, si falla, la del CDN
    const SHEETJS_CDN = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
    let promesaSheetJS = null;

    // ==========================================
    // 2. UTILIDADES
    // ==========================================
    const esTexto = function(col) { return col === 'nombre' || col === 'clave'; };
    const sinAcentos = function(t) { return String(t == null ? '' : t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    const fmtN = function(v) { return v.toLocaleString('es-MX'); };
    const vacio = function(v) { return v === null || v === undefined || (typeof v === 'number' && isNaN(v)); };

    // Icono de línea de public/img, pintado con el color del texto del botón
    const icono = function(container, nombre) {
        return container.dataset.img ? '<span class="siarhe-ctl-icon" aria-hidden="true" style="--ico: url(\'' + container.dataset.img + nombre + '.svg\')"></span>' : '';
    };

    const cargaScript = function(src) {
        return new Promise(function(resuelve, rechaza) {
            const s = document.createElement('script');
            s.src = src;
            s.onload = resuelve;
            s.onerror = function() { rechaza(new Error('No se pudo cargar ' + src)); };
            document.head.appendChild(s);
        });
    };

    const cargaSheetJS = function() {
        if (typeof XLSX !== 'undefined' && XLSX.utils && XLSX.writeFile) return Promise.resolve();
        if (!promesaSheetJS) {
            const local = (window.SiarheDataVizConfig || {}).xlsx;
            promesaSheetJS = (local ? cargaScript(local).catch(function() { return cargaScript(SHEETJS_CDN); }) : cargaScript(SHEETJS_CDN))
                .catch(function(err) { promesaSheetJS = null; throw err; });
        }
        return promesaSheetJS;
    };

    const descarga = function(contenido, tipo, nombre) {
        const url = URL.createObjectURL(new Blob([contenido], { type: tipo }));
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    app.table = {

        // ==========================================
        // 3. ESTRUCTURA (BARRA DE OPCIONES Y TABLA)
        // ==========================================

        init: function(container, state) {
            const div = container.querySelector('.siarhe-table-container');
            if (!div) return;
            div.innerHTML = '';

            const table = document.createElement('table');
            table.className = 'siarhe-data-table';
            state.tableElements = { table: table, thead: table.createTHead(), tbody: table.createTBody() };
            div.appendChild(table);

            app.table._barra(container, state, div);
            app.table.update(container, state);
        },

        // Barra de opciones sobre la tabla: ir al mapa, pantalla completa y descargas
        _barra: function(container, state, div) {
            const vista = div.closest('.siarhe-table-wrapper') || div;
            if (vista.querySelector('.siarhe-tabla-barra')) return;
            const hayMapa = !!container.querySelector('#siarhe-map-section');
            const boton = function(clase, ico, texto, titulo) {
                return '<button type="button" class="siarhe-btn ' + clase + '" title="' + titulo + '">' + icono(container, ico) +
                    ' <span class="siarhe-btn-texto">' + texto + '</span></button>';
            };
            const barra = document.createElement('div');
            barra.className = 'siarhe-tabla-barra siarhe-controls-layout';
            barra.innerHTML = '<div class="siarhe-controls-row">' +
                (hayMapa ? boton('siarhe-tabla-mapa', 'icon-chart', 'Ver mapa', 'Ir al mapa') : '') +
                boton('siarhe-tabla-pantalla', 'fs-enter', 'Pantalla completa', 'Ver la tabla en pantalla completa') +
                boton('siarhe-tabla-csv', 'icon-csv', 'CSV', 'Descargar la tabla en CSV') +
                boton('siarhe-tabla-xlsx', 'icon-excel', 'Excel', 'Descargar la tabla en Excel') +
                boton('siarhe-tabla-json', 'icon-json', 'JSON', 'Descargar la tabla en JSON') +
                '</div>';
            vista.insertBefore(barra, vista.firstChild);
            if (hayMapa) app.table._selectores(container, state, barra);

            const alPulsar = function(clase, fn) {
                const b = barra.querySelector('.' + clase);
                if (b) b.addEventListener('click', function(e) { e.preventDefault(); fn(b); });
            };
            alPulsar('siarhe-tabla-mapa', function() {
                if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen();
                const mapa = container.querySelector('#siarhe-map-section');
                const quieto = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (mapa) mapa.scrollIntoView({ behavior: quieto ? 'auto' : 'smooth', block: 'start' });
            });
            alPulsar('siarhe-tabla-pantalla', function() {
                if (document.fullscreenElement === vista) { if (document.exitFullscreen) document.exitFullscreen(); return; }
                const pide = vista.requestFullscreen ? vista.requestFullscreen() : (vista.webkitRequestFullscreen && vista.webkitRequestFullscreen());
                if (pide && pide.catch) pide.catch(function() {});
            });
            document.addEventListener('fullscreenchange', function() {
                const b = barra.querySelector('.siarhe-tabla-pantalla'), dentro = document.fullscreenElement === vista;
                if (!b) return;
                b.setAttribute('aria-pressed', dentro ? 'true' : 'false');
                b.querySelector('.siarhe-btn-texto').textContent = dentro ? 'Salir de pantalla completa' : 'Pantalla completa';
            });
            alPulsar('siarhe-tabla-csv', function() { app.table.aCSV(container, state); });
            alPulsar('siarhe-tabla-xlsx', function() { app.table.aXLSX(container, state); });
            alPulsar('siarhe-tabla-json', function() { app.table.aJSON(container, state); });
        },

        /**
         * Selectores propios de la tabla cuando el shortcode trae mapa y tabla, como en RHEMX: año, tema, indicador, unidad y
         * medida, sin subir al mapa a cambiarlos. Son un espejo de los controles del mapa: cada uno acciona el suyo y se
         * repinta con `state.syncTabla()` (lo llama `update` y cualquier cambio en el panel del mapa). La tabla sola no los
         * necesita: ya lleva sus controles (`renderMain`).
         */
        _selectores: function(container, state, barra) {
            const mapa = container.querySelector('#siarhe-map-section');
            if (!mapa || !app.controls || !app.controls.makeSwitch) return;
            const fila = document.createElement('div');
            fila.className = 'siarhe-controls-row siarhe-tabla-selectores';
            fila.dataset.fila = 'datos';
            fila.innerHTML = '<span class="siarhe-fila-titulo">Datos de la tabla</span>';
            barra.insertBefore(fila, barra.firstChild);

            const grupo = function(titulo, ico) {
                const g = document.createElement('div');
                g.className = 'siarhe-control-group';
                const label = document.createElement('label');
                label.innerHTML = icono(container, ico) + ' ';
                label.appendChild(document.createTextNode(titulo));
                const sel = document.createElement('select');
                sel.className = 'siarhe-metric-select';
                sel.setAttribute('aria-label', titulo + ' de la tabla');
                g.appendChild(label); g.appendChild(sel);
                fila.appendChild(g);
                return { g: g, sel: sel };
            };
            const llena = function(sel, opciones, valor) {
                const firma = opciones.map(function(o) { return o.v + '|' + o.t + '|' + (o.d ? 1 : 0); }).join('~');
                if (sel.dataset.firma !== firma) {
                    sel.innerHTML = '';
                    opciones.forEach(function(o) {
                        const op = document.createElement('option');
                        op.value = o.v; op.textContent = o.t; op.disabled = !!o.d;
                        sel.appendChild(op);
                    });
                    sel.dataset.firma = firma;
                }
                sel.value = valor;
            };

            // Año y tema: selectores nativos del mapa
            const nativo = function(titulo, ico, id) {
                const c = grupo(titulo, ico);
                const origen = function() { return mapa.querySelector('#' + id); };
                c.sel.addEventListener('change', function() {
                    const o = origen();
                    if (!o) return;
                    o.value = c.sel.value;
                    o.dispatchEvent(new Event('change', { bubbles: true }));
                });
                return function() {
                    const o = origen(), g = o && o.closest('.siarhe-control-group');
                    // El año y «Comparar con» se ven siempre: con un solo año quedan desactivados, como en el mapa
                    c.g.hidden = !o || !!(g && g.hidden) || (id === 'siarhe-tema-select' && o.options.length < 2);
                    if (!o) return;
                    llena(c.sel, Array.from(o.options).map(function(x) { return { v: x.value, t: x.textContent, d: x.disabled }; }), o.value);
                    c.sel.disabled = o.disabled;
                    c.sel.title = o.title || '';
                };
            };
            const pintaAnio = nativo('Año', 'icon-calendario', 'siarhe-anio-select');
            const pintaComparar = nativo('Comparar con', 'icon-calendario', 'siarhe-comparar-select');
            const pintaTema = nativo('Tema', 'icon-nivel', 'siarhe-tema-select');

            // Indicador: las opciones visibles del desplegable del mapa
            const ind = grupo('Indicador', 'icon-grafico-pastel');
            const opcionesInd = function() { return Array.from(mapa.querySelectorAll('#c-indicador .siarhe-cs-option[data-key]')); };
            ind.sel.addEventListener('change', function() {
                const o = opcionesInd().find(function(x) { return x.dataset.key === ind.sel.value; });
                if (o) o.click();
            });
            const pintaIndicador = function() {
                const visibles = opcionesInd().filter(function(x) { return !x.hidden; });
                llena(ind.sel, visibles.map(function(x) { return { v: x.dataset.key, t: x.textContent }; }), state.currentMetric);
            };

            // Unidad y medida: conmutadores que accionan el del mapa
            const conmutador = function(id, etiqueta) {
                const origen = function() { return Array.from(mapa.querySelectorAll('#' + id + ' .siarhe-conm-op')); };
                const ops = origen();
                if (ops.length !== 2) return function() {};
                const g = document.createElement('div');
                g.className = 'siarhe-control-group siarhe-control-group--conm';
                const sw = app.controls.makeSwitch({
                    etiqueta: etiqueta + ' de la tabla',
                    a: { valor: 'a', texto: ops[0].textContent, titulo: ops[0].title },
                    b: { valor: 'b', texto: ops[1].textContent, titulo: ops[1].title },
                    alCambiar: function(v) { const o = origen()[v === 'b' ? 1 : 0]; if (o) o.click(); }
                });
                g.appendChild(sw.el);
                fila.appendChild(g);
                return function() {
                    const o = origen();
                    if (o.length !== 2) return;
                    sw.deshabilita(o[0].disabled, o[1].disabled);
                    sw.set(o[1].classList.contains('is-active') ? 'b' : 'a');
                };
            };
            // La medida (Tasa / Frecuencia) no va: la tabla ya muestra las dos columnas (pedido del dueño, 2026-11-16)
            const pintaUnidad = conmutador('c-unidad', 'Unidad de conteo');

            state.syncTabla = function() { pintaAnio(); pintaComparar(); pintaTema(); pintaIndicador(); pintaUnidad(); };
            state.syncTabla();
            // Lo que se cambie en el panel del mapa se refleja aquí aunque no redibuje la tabla
            let pendiente = 0;
            const alCambiar = function() { clearTimeout(pendiente); pendiente = setTimeout(function() { if (container.isConnected) state.syncTabla(); }, 60); };
            mapa.addEventListener('change', alCambiar);
            mapa.addEventListener('click', alCambiar);
        },

        /** Marca (o desmarca) el renglón del lugar con esa clave de datos; lo usa el mapa al pasar el cursor por un lugar. */
        resaltaFila: function(state, clave, on) {
            const tbody = state.tableElements && state.tableElements.tbody;
            if (!tbody) return;
            tbody.querySelectorAll('tr.siarhe-row-resaltada').forEach(function(tr) { tr.classList.remove('siarhe-row-resaltada'); });
            if (!on || clave === undefined || clave === null) return;
            const fila = Array.from(tbody.rows).find(function(tr) { return tr.dataset.clave === String(app.utils.cleanKey(clave)); });
            if (fila) fila.classList.add('siarhe-row-resaltada');
        },

        // ==========================================
        // 4. COLUMNAS Y RENGLONES
        // ==========================================

        /**
         * Columnas de la tabla para el indicador y la unidad que se ven: [{ id, titulo, dato }]. Función pura.
         *   metricas: configuración de métricas; mKey: indicador elegido; unidad: 'plazas' | 'personas'; esNacional.
         */
        columnas: function(metricas, mKey, unidad, esNacional) {
            const info = metricas[mKey] || {};
            const esPob = mKey === 'poblacion';
            const u = unidad === 'personas' ? { boton: 'Recursos humanos', corto: 'recursos humanos' } : { boton: 'Plazas', corto: 'plazas' };
            const grupo = info.pair || mKey;
            const colCnt = esPob ? null : app.utils.findAbsoluteKeyForPair(grupo, metricas);
            const colTasa = esPob ? null : (info.tipo === 'tasa' ? mKey : app.utils.findRateKeyForAbsolute(grupo, metricas));
            const conVariable = !esPob && !!colCnt && colCnt !== COL_TOTAL;
            const nombreVar = (app.controls && app.controls.indicatorName ? app.controls.indicatorName(info) : '') || info.label || mKey;
            const cols = [
                { id: 'nombre', titulo: esNacional ? 'Entidad' : 'Municipio' },
                { id: 'clave', titulo: 'Clave' },
                { id: 'pob', titulo: 'Población', dato: 'poblacion' },
                { id: 'total', titulo: u.boton + ' (total)', dato: COL_TOTAL },
                { id: 'tasaTotal', titulo: 'Tasa por 1,000 hab. (' + u.corto + ')', dato: COL_TASA_TOTAL, tasa: true }
            ];
            if (conVariable) {
                cols.push({ id: 'cnt', titulo: nombreVar + ' (' + u.corto + ')', dato: colCnt });
                if (colTasa) cols.push({ id: 'tasa', titulo: 'Tasa por 1,000 hab. (' + nombreVar + ')', dato: colTasa, tasa: true });
            }
            return cols;
        },

        /**
         * Clave geoestadística de un renglón: la de la entidad (2 dígitos) en la República y la del municipio
         * (entidad + municipio, 5 dígitos) en un estado. Los renglones de total y «No disponible» no llevan clave.
         */
        clave: function(row, esNacional, cveEnt) {
            if (row.isTotal || row.isSpecial) return '';
            if (esNacional) return row.CVE_ENT ? String(row.CVE_ENT).padStart(2, '0') : '';
            const mun = String(row.CVE_MUN || '');
            if (!mun) return '';
            return mun.length > 3 ? mun.padStart(5, '0') : String(cveEnt || row.CVE_ENT || '').padStart(2, '0') + mun.padStart(3, '0');
        },

        // ==========================================
        // 5. DIBUJO Y ORDEN
        // ==========================================

        update: function(container, state) {
            if (!state.csvData || state.csvData.length === 0 || !state.tableElements) return;
            const mKey = state.currentMetric;
            if (!state.metricas[mKey]) return;
            const thead = state.tableElements.thead, tbody = state.tableElements.tbody;

            const unidad = (state.base && state.base.unidad) || container.dataset.unidad || 'plazas';
            const cols = app.table.columnas(state.metricas, mKey, unidad, state.isNacional);
            const cveEnt = container.dataset.cveEnt;
            // Comparación de dos años (app.cambio): el valor de cada año, el cambio y el cambio en %, como en RHEMX
            const cmb = app.cambio ? app.cambio.calcula(state) : null;
            if (cmb) {
                const med = app.cambio.esTasa(state) ? 'Tasa' : (unidad === 'personas' ? 'Recursos humanos' : 'Plazas');
                const anio = (state.base && state.base.anio) || '';
                cols.push({ id: 'v1', titulo: med + ' ' + anio, cambio: true }, { id: 'v2', titulo: med + ' ' + state.comparar, cambio: true },
                    { id: 'delta', titulo: 'Cambio', cambio: true }, { id: 'pct', titulo: 'Cambio (%)', cambio: true });
            }
            const cambioDe = function(r) {
                if (!cmb || r.isSpecial) return null;
                return cmb.mapa.get(r.isTotal ? 'total' : app.cambio._clave(state, r)) || null;
            };
            const valor = function(r, c) {
                if (c.id === 'nombre') return r.estado;
                if (c.id === 'clave') return app.table.clave(r, state.isNacional, cveEnt);
                if (c.cambio) { const x = cambioDe(r); return x ? x[c.id] : null; }
                return r[c.dato];
            };

            // Orden vigente; si su columna ya no está (cambió el indicador), vuelve a la tasa del total
            if (!cols.some(function(c) { return c.id === app.sortConfig.key; })) app.sortConfig = { key: 'tasaTotal', direction: 'desc' };
            const colOrden = cols.find(function(c) { return c.id === app.sortConfig.key; });
            const asc = app.sortConfig.direction === 'asc';

            // Encabezados: se ordena con clic o con teclado y se anuncia el orden
            thead.innerHTML = '';
            const tr = document.createElement('tr');
            cols.forEach(function(c) {
                const th = document.createElement('th');
                const activa = c.id === app.sortConfig.key;
                th.innerHTML = app.utils.esc(c.titulo) + '<span class="siarhe-sort-arrow">' + (activa ? (asc ? '▲' : '▼') : '') + '</span>';
                th.dataset.col = c.id;
                if (!esTexto(c.id)) th.classList.add('siarhe-num');
                th.setAttribute('tabindex', '0');
                th.setAttribute('aria-sort', activa ? (asc ? 'ascending' : 'descending') : 'none');
                th.onclick = function() {
                    app._tableFocusCol = c.id;
                    app.sortConfig = { key: c.id, direction: app.sortConfig.key === c.id ? (asc ? 'desc' : 'asc') : (esTexto(c.id) ? 'asc' : 'desc') };
                    app.table.update(container, state);
                };
                th.onkeydown = function(ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); th.onclick(); } };
                tr.appendChild(th);
            });
            thead.appendChild(tr);

            // El encabezado se reconstruye al ordenar: el foco vuelve a la columna usada
            if (app._tableFocusCol) {
                const focusTh = thead.querySelector('[data-col="' + app._tableFocusCol + '"]');
                app._tableFocusCol = null;
                if (focusTh) focusTh.focus();
            }

            // Lugares ordenados (los vacíos al final) y, después, «No disponible» y el total
            const lugares = state.csvData.filter(function(r) { return !r.isTotal && !r.isSpecial; }).sort(function(a, b) {
                let va = valor(a, colOrden), vb = valor(b, colOrden);
                if (esTexto(colOrden.id)) { va = sinAcentos(va); vb = sinAcentos(vb); }
                if (vacio(va) && vacio(vb)) return 0;
                if (vacio(va)) return 1;
                if (vacio(vb)) return -1;
                return (asc ? 1 : -1) * (typeof va === 'string' ? va.localeCompare(vb, 'es') : va - vb);
            });
            const fijos = state.csvData.filter(function(r) { return (r.isTotal || r.isSpecial) && !r.isExtranjero; })
                .sort(function(a, b) { return (a.isTotal ? 1 : 0) - (b.isTotal ? 1 : 0); });

            tbody.innerHTML = '';
            lugares.concat(fijos).forEach(function(r) {
                const fila = document.createElement('tr');
                fila.className = r.isTotal ? 'siarhe-row-total' : (r.isSpecial ? 'siarhe-row-extra' : 'siarhe-row-lugar');
                cols.forEach(function(c) {
                    const td = document.createElement('td');
                    const v = valor(r, c);
                    if (c.id === 'nombre') td.textContent = v || '';
                    else if (c.id === 'clave') { td.textContent = v; td.dataset.t = 's'; }   // texto: conserva los ceros a la izquierda en Excel
                    else if (c.cambio) {
                        const x = cambioDe(r) || { delta: null };
                        td.classList.add('siarhe-num');
                        td.textContent = c.id === 'pct' ? app.cambio.fmtPctDe(x) : c.id === 'delta' ? app.cambio.fmtCambio(state, x.delta) : app.cambio.fmtMedida(state, v);
                        if (vacio(c.id === 'pct' ? x.delta : v)) td.classList.add('siarhe-sin-dato');
                        if ((c.id === 'delta' || c.id === 'pct') && (x.clase === 'sube' || x.clase === 'baja')) td.classList.add('siarhe-cambio-' + x.clase);
                    } else {
                        td.classList.add('siarhe-num');
                        if (vacio(v)) { td.textContent = SIN_DATO; td.classList.add('siarhe-sin-dato'); }
                        else td.textContent = c.tasa ? app.utils.fmtTasa(v) : fmtN(v);
                    }
                    fila.appendChild(td);
                });
                // Como en RHEMX: el renglón resalta su lugar en el mapa y, en la República, un clic entra al estado
                if (!r.isTotal && !r.isSpecial) {
                    const llave = state.isNacional ? r.CVE_ENT : (r.CVE_MUN || r.CVE_ENT);
                    fila.dataset.clave = String(app.utils.cleanKey(llave));
                    if (app.map && app.map.resalta) {
                        fila.addEventListener('mouseenter', function() { app.map.resalta(state, llave, true); });
                        fila.addEventListener('mouseleave', function() { app.map.resalta(state, llave, false); });
                    }
                    // En la República, el renglón lleva a la página configurada para la entidad (con lo que se está viendo)
                    const destino = state.isNacional && app.urlState ? app.urlState.enlaceEntidad(state, app.table.clave(r, true)) : '';
                    if (destino) {
                        fila.classList.add('siarhe-row-enlace');
                        fila.title = 'Ir a la página de ' + (r.estado || 'la entidad');
                        fila.addEventListener('click', function() {
                            const url = app.urlState.enlaceEntidad(state, app.table.clave(r, true));
                            if (url) window.location.href = url;
                        });
                    }
                }
                tbody.appendChild(fila);
            });
            if (state.syncTabla) state.syncTabla();
        },

        // ==========================================
        // 6. DESCARGAS
        // ==========================================

        // Encabezados y renglones tal como se ven
        _matriz: function(state) {
            const t = state.tableElements && state.tableElements.table;
            if (!t) return { headers: [], rows: [] };
            const headers = Array.from(t.querySelectorAll('thead th')).map(function(th) {
                const copia = th.cloneNode(true), flecha = copia.querySelector('.siarhe-sort-arrow');
                if (flecha) flecha.remove();
                return copia.textContent.trim();
            });
            const rows = Array.from(t.querySelectorAll('tbody tr')).map(function(tr) {
                return Array.from(tr.querySelectorAll('td')).map(function(td) { return td.textContent.trim(); });
            });
            return { headers: headers, rows: rows };
        },

        // Nombre del archivo: indicador, lugar y año
        _nombreArchivo: function(container, state) {
            const limpia = function(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-zA-Z0-9 ]/g, '').trim().replace(/\s+/g, '_'); };
            const info = state.metricas[state.currentMetric] || {};
            const titulo = container.querySelector('h2.siarhe-title');
            const anioNode = container.querySelector('.siarhe-dynamic-year');
            const anio = (state.base && state.base.anio) || (anioNode ? anioNode.textContent : '') || container.dataset.anio || '';
            return [limpia(info.label || info.fullLabel || 'Datos'), limpia(titulo ? titulo.textContent : 'Mexico'), limpia(anio)].filter(Boolean).join('_');
        },

        aCSV: function(container, state) {
            const m = app.table._matriz(state);
            if (!m.headers.length) return;
            // Texto entre comillas; lo que empieza como fórmula se neutraliza para Excel
            const celda = function(v) { return '"' + (/^[=+\-@]/.test(v) && isNaN(Number(v.replace(/,/g, ''))) ? "'" + v : v).replace(/"/g, '""') + '"'; };
            const lineas = [m.headers.map(celda).join(',')].concat(m.rows.map(function(r) { return r.map(celda).join(','); }));
            descarga('﻿' + lineas.join('\n') + '\n', 'text/csv;charset=utf-8;', app.table._nombreArchivo(container, state) + '.csv');
        },

        aXLSX: async function(container, state) {
            const t = state.tableElements && state.tableElements.table;
            if (!t) return;
            try { await cargaSheetJS(); } catch (err) {
                window.alert('No se pudo cargar la librería para generar el archivo de Excel. Revisa tu conexión e inténtalo de nuevo.');
                return;
            }
            const copia = t.cloneNode(true);
            copia.querySelectorAll('.siarhe-sort-arrow').forEach(function(a) { a.remove(); });
            XLSX.writeFile(XLSX.utils.table_to_book(copia, { sheet: 'Datos' }), app.table._nombreArchivo(container, state) + '.xlsx');
        },

        aJSON: function(container, state) {
            const m = app.table._matriz(state);
            if (!m.headers.length) return;
            const lista = m.rows.map(function(r) {
                const obj = {};
                m.headers.forEach(function(h, i) {
                    const v = r[i] || '';
                    // Las claves son texto: como número perderían los ceros iniciales (01001)
                    if (h === 'Clave') { obj[h] = v; return; }
                    const n = parseFloat(v.replace(/,/g, ''));
                    obj[h] = (i > 0 && !isNaN(n)) ? n : (v === SIN_DATO ? null : v);
                });
                return obj;
            });
            descarga(JSON.stringify(lista, null, 2), 'application/json;charset=utf-8;', app.table._nombreArchivo(container, state) + '.json');
        }
    };

})(window.SiarheDataViz);
