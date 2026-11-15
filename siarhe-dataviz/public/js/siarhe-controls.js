/**
 * SIARHE DataViz - Módulo de Controles (Controls)
 * ------------------------------------------------------------------
 * Gestiona la interfaz de usuario (UI), como los selectores desplegables
 * de indicadores (ahora con buscador), la lógica de activación de marcadores
 * en memoria RAM y las funciones de exportación de datos (Excel y PNG).
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.controls = {

        renderMain: function(container, state, onUpdate, opts = { targetSelector: '.siarhe-controls-placeholder', showMarkers: true }) {

            const isTableOnly = (opts.targetSelector === '.siarhe-table-controls-placeholder');


            const ph = container.querySelector(opts.targetSelector);
            if (!ph) return;
            ph.innerHTML = '';

            const wrapper = document.createElement('div');
            wrapper.className = 'siarhe-controls-layout';

            if (isTableOnly) {
                wrapper.innerHTML = `
                    <div class="siarhe-controls-row" data-fila="medida">
                        <span class="siarhe-fila-titulo">Unidad y medida</span>
                        <div class="siarhe-control-group siarhe-control-group--conm" id="c-medida"></div>
                    </div>
                    <div class="siarhe-controls-row" data-fila="vista">
                        <span class="siarhe-fila-titulo">Datos de la tabla</span>
                        <div class="siarhe-control-group" id="c-tema"><label>Tema</label></div>
                        <div class="siarhe-control-group" id="c-indicador"><label>Indicador de la Tabla</label></div>
                    </div>
                `;
            } else {
                const hayTabla = !!container.querySelector('#siarhe-table-section');
                // Icono de linea (public/img) pintado con el color del texto del boton
                const ico = (n) => container.dataset.img ? `<span class="siarhe-ctl-icon" aria-hidden="true" style="--ico: url('${container.dataset.img}${n}.svg')"></span>` : '';
                wrapper.innerHTML = `
                    <div class="siarhe-controls-row" data-fila="buscar">
                        <span class="siarhe-fila-titulo">Buscar, tabla e imágenes</span>
                        <div class="siarhe-control-group siarhe-control-group--buscar" id="c-buscar"><label for="siarhe-buscar-entrada">Buscar</label></div>
                        <div class="siarhe-control-group siarhe-control-group--action">
                            <button type="button" class="siarhe-btn siarhe-btn-share" title="Copia el enlace de esta vista (año, unidad, indicador, vista y marcadores)">
                                ${ico('icon-enlace')} <span class="siarhe-btn-texto">Copiar enlace</span>
                            </button>
                        </div>
                        ${hayTabla ? `<div class="siarhe-control-group siarhe-control-group--action">
                            <button type="button" class="siarhe-btn siarhe-btn-ver-tabla" title="Ir a la tabla de datos">
                                ${ico('icon-table')} <span class="siarhe-btn-texto">Ver tabla</span>
                            </button>
                        </div>` : ''}
                        <div class="siarhe-control-group siarhe-control-group--action">
                            <button type="button" class="siarhe-btn siarhe-btn-download-png" title="Descarga la imagen del mapa; lleva los nombres si las etiquetas están a la vista" aria-label="Descargar el mapa en PNG">
                                ${ico('icon-camera')} <span class="siarhe-btn-texto">PNG</span>
                            </button>
                        </div>
                    </div>
                    <div class="siarhe-controls-row" data-fila="medida">
                        <span class="siarhe-fila-titulo">Unidad y medida</span>
                        <div class="siarhe-control-group siarhe-control-group--conm" id="c-medida"></div>
                    </div>
                    <div class="siarhe-controls-row" data-fila="vista">
                        <span class="siarhe-fila-titulo">Vista del mapa</span>
                        <div class="siarhe-control-group" id="c-tema"><label>Tema</label></div>
                        <div class="siarhe-control-group" id="c-indicador"><label>Indicador</label></div>
                        <div class="siarhe-control-group" id="c-vista"><label>Vista Geográfica</label></div>
                        <div class="siarhe-control-group" id="c-escala"><label>Estilo de Mapa</label></div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-etiquetas">
                            <button type="button" class="siarhe-btn siarhe-labels-btn" aria-pressed="false" title="Mostrar los nombres en el mapa">
                                ${ico('icon-etiquetas')} <span class="siarhe-btn-texto">Ver etiquetas</span>
                            </button>
                        </div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-ancho">
                            <button type="button" class="siarhe-btn siarhe-layout-btn" aria-pressed="false" title="Usar todo el ancho de la ventana o el ancho normal de la página">
                                ${ico('icon-entidad')} <span class="siarhe-btn-texto">Ancho completo</span>
                            </button>
                        </div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-personalizar">
                            <button type="button" class="siarhe-btn siarhe-palette-btn" aria-expanded="false" title="Cambiar los colores del mapa (solo en tu navegador)">
                                <svg class="siarhe-reset-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.3-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.4a3.6 3.6 0 0 0 3.6-3.6C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="11.5" r="1"/></svg>
                                <span>Personalizar</span>
                            </button>
                        </div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-reset">
                            <button type="button" class="siarhe-btn siarhe-reset-btn" title="Volver a la configuración inicial">
                                <svg class="siarhe-reset-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg>
                                <span>Restablecer</span>
                            </button>
                        </div>
                    </div>
                    <div class="siarhe-controls-row" data-fila="posicion">
                        <span class="siarhe-fila-titulo">Marcadores de posición</span>
                    </div>
                    <div class="siarhe-controls-row" data-fila="densidad">
                        <span class="siarhe-fila-titulo">Marcadores de densidad</span>
                    </div>
                `;

                const closeBtn = document.createElement('button');
                closeBtn.className = 'siarhe-close-controls-btn';
                closeBtn.innerHTML = '✖';
                closeBtn.title = 'Ocultar panel';
                wrapper.appendChild(closeBtn);

                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    wrapper.classList.add('is-hidden');
                    const mapDiv = container.querySelector('.siarhe-map-container');
                    // 🌟 INYECTA LA CLASE MÁGICA PARA QUE APAREZCA 🎛️
                    if (mapDiv) mapDiv.classList.add('controls-are-hidden');
                });
            }

            document.addEventListener('click', () => {
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
            });

            let toastBox = null;
            if (!isTableOnly) {
                toastBox = container.querySelector('.siarhe-smart-toast');
                if (!toastBox) {
                    toastBox = document.createElement('div');
                    toastBox.className = 'siarhe-smart-toast';
                    container.appendChild(toastBox);
                }
            }

            let toastTimeout;
            const showSmartToast = (markerKey, markerLabel) => {
                if (!toastBox) return;
                toastBox.innerHTML = `
                    <div class="siarhe-toast-header">
                        <strong>💡 Sugerencia de análisis</strong>
                        <button class="siarhe-toast-close" title="Cerrar">✖</button>
                    </div>
                    <div style="color: #475569; line-height: 1.4;">
                        Existen datos de <strong>${markerLabel}</strong> vinculados a este indicador.
                    </div>
                    <button class="siarhe-toast-btn">📍 Mostrar en mapa</button>
                `;

                toastBox.querySelector('.siarhe-toast-close').onclick = () => toastBox.classList.remove('show');
                toastBox.querySelector('.siarhe-toast-btn').onclick = () => {
                    toastBox.classList.remove('show');
                    const chk = container.querySelector(`.mc-check[value="${markerKey}"]`);
                    if (chk && !chk.checked) {
                        chk.checked = true;
                        app.controls.toggleMarker(markerKey, state, container);
                    } else if (!chk) {
                        app.controls.toggleMarker(markerKey, state, container);
                    }
                };

                toastBox.classList.add('show');
                clearTimeout(toastTimeout);
                toastTimeout = setTimeout(() => { toastBox.classList.remove('show'); }, 8000);
            };

            const checkSpectrumLink = (metricKey) => {
                if (!opts.showMarkers || isTableOnly || !toastBox) return;
                const linkedMarkerKey = Object.keys(state.markerLabels).find(k => {
                    const mk = state.markerLabels[k];
                    return app.utils.isDensity(mk.tipo) && mk.espectro_pair === metricKey;
                });

                if (linkedMarkerKey && !state.activeMarkers.has(linkedMarkerKey)) {
                    const mkLabel = state.markerLabels[linkedMarkerKey].label || linkedMarkerKey;
                    showSmartToast(linkedMarkerKey, mkLabel);
                } else {
                    toastBox.classList.remove('show');
                }
            };

            const cInd = wrapper.querySelector('#c-indicador');
            const customSelectInd = document.createElement('div');
            customSelectInd.className = 'siarhe-custom-select';

            const triggerInd = document.createElement('div');
            triggerInd.className = 'siarhe-cs-trigger';

            const menuInd = document.createElement('div');
            menuInd.className = 'siarhe-cs-menu';

            const searchBoxInd = document.createElement('div');
            searchBoxInd.className = 'siarhe-cs-search';
            searchBoxInd.addEventListener('click', e => e.stopPropagation());
            const searchInputInd = document.createElement('input');
            searchInputInd.type = 'text';
            searchInputInd.placeholder = '🔍 Buscar indicador...';
            searchBoxInd.appendChild(searchInputInd);
            menuInd.appendChild(searchBoxInd);

            const optionsContainerInd = document.createElement('div');
            optionsContainerInd.className = 'siarhe-cs-options';

            // ☀️ OBTENER LA ESCALA PREDETERMINADA AL CARGAR CON SEGURIDAD EXTREMA
            if (!state.colorMode) {
                const infoCurrent = state.metricas[state.currentMetric] || {};
                state.colorMode = infoCurrent.scale_type || 'cuartiles';
            }

            Object.entries(state.metricas).forEach(([key, info]) => {
                const opt = document.createElement('div');
                opt.className = 'siarhe-cs-option';
                opt.dataset.key = key;

                if (key === state.currentMetric) {
                    opt.classList.add('selected');
                    triggerInd.innerHTML = `<span>${app.utils.esc(app.controls.indicatorName(info))}</span>`;
                }

                opt.textContent = app.controls.indicatorName(info);

                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    state.currentMetric = key;
                    app.sortConfig = { key: 'tasa', direction: 'desc' };

                    // ☀️ ACTUALIZAR LA ESCALA SEGÚN LA NUEVA MÉTRICA SELECCIONADA
                    const newInfo = state.metricas[key] || {};
                    state.colorMode = newInfo.scale_type || 'cuartiles';
                    const selectEscala = container.querySelector('#siarhe-escala-select');
                    if (selectEscala) selectEscala.value = state.colorMode;

                    triggerInd.innerHTML = `<span>${app.utils.esc(app.controls.indicatorName(info))}</span>`;
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    menuInd.classList.remove('open');
                    if (state.refreshMeasure) state.refreshMeasure();

                    onUpdate();
                    checkSpectrumLink(key);
                });
                optionsContainerInd.appendChild(opt);
            });

            menuInd.appendChild(optionsContainerInd);
            customSelectInd.append(triggerInd, menuInd);
            cInd.appendChild(customSelectInd);

            searchInputInd.addEventListener('keyup', (e) => {
                const val = e.target.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => {
                    const text = o.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                    o.style.display = text.includes(val) ? '' : 'none';
                });
            });

            triggerInd.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menuInd.classList.contains('open');
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));

                if (!isOpen) {
                    menuInd.classList.add('open');
                    searchInputInd.value = '';
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.style.display = '');
                    setTimeout(() => searchInputInd.focus(), 50);
                }
            });

            if (!isTableOnly) {
                if (opts.showMarkers) {
                    state._mcDrops = [];
                    // Posicion: recorrido por tema (Ver, Clinica, Por...) de siarhe-posicion.js; densidad: un desplegable por grupo
                    if (app.pos) app.pos.build(container, state, wrapper);
                    else app.controls.buildMarkerRow(container, state, wrapper, 'posicion');
                    app.controls.buildMarkerRow(container, state, wrapper, 'espectro');
                }

                // ☀️ RENDERIZAR OPCIONES DE ESCALA CONECTADAS AL ESTADO
                const cEscala = wrapper.querySelector('#c-escala');
                const currScale = state.colorMode || 'cuartiles';

                cEscala.innerHTML += `
                    <select class="siarhe-metric-select" id="siarhe-escala-select">
                        <option value="cuartiles" ${currScale === 'cuartiles' || currScale === 'degradado' ? 'selected' : ''}>Degradado por Colores</option>
                        <option value="monocromatico" ${currScale === 'monocromatico' || currScale === 'mono' ? 'selected' : ''}>Escala Monocromática</option>
                        <option value="rangos" ${currScale === 'rangos' ? 'selected' : ''}>Rangos</option>
                    </select>
                `;
                cEscala.querySelector('select').addEventListener('change', (e) => {
                    state.colorMode = e.target.value;
                    state.realStyleMode = e.target.value;
                    if (app.map) app.map.updateVisuals(container, state);
                });

                const cVista = wrapper.querySelector('#c-vista');
                const labelNivel1 = state.isNacional ? 'Entidades Federativas' : 'Municipios';
                const labelNivel2 = state.isNacional ? 'Municipios' : 'Localidades';
                // La capa detallada depende del marco geoestadístico del año que se ve (state.geoLocUrl): la opción existe
                // si algún marco la tiene y se desactiva en los años cuyo marco no la trae.
                const hayDetalle = !!state.geoLocUrl || Object.keys((state.geos && state.geos.loc) || {}).length > 0;

                cVista.innerHTML += `
                    <select class="siarhe-metric-select" id="siarhe-vista-select">
                        <option value="base">${labelNivel1}</option>
                        ${hayDetalle ? `<option value="detalle"${state.geoLocUrl ? '' : ' disabled'}>${labelNivel2}</option>` : ''}
                    </select>
                `;

                cVista.querySelector('select').addEventListener('change', (e) => {
                    const val = e.target.value;
                    const loading = container.querySelector('.siarhe-loading-overlay');
                    const setLoading = (text) => {
                        if (!loading) return;
                        loading.style.display = 'flex';
                        const p = loading.querySelector('p');
                        if (p) p.textContent = text;
                    };
                    const hideLoading = () => { if (loading) loading.style.display = 'none'; };

                    if (!state.baseGeoData) state.baseGeoData = state.geoData;

                    if (val === 'base') {
                        state.isGeoLocMode = false;
                        if (app.map) app.map.updateGeography(container, state);
                        onUpdate();
                    }
                    else if (val === 'detalle' && state.geoLocUrl) {
                        setLoading('Cargando ' + labelNivel2 + '...');

                        const dataPromise = state.locGeoData ? Promise.resolve(state.locGeoData) : app.controls.loadLocGeo(container, state, state.geoLocUrl, (pct) => setLoading('Preparando ' + labelNivel2 + '... ' + pct + '%'));
                        dataPromise.then(data => {
                            if (!data || !Array.isArray(data.features) || data.features.length === 0) {
                                throw new Error('La capa detallada esta vacia o no es valida');
                            }
                            state.locGeoData = data;
                            state.isGeoLocMode = true;
                            if (!app.map) return null;
                            // Los poligonos se dibujan por lotes; el overlay muestra el avance
                            return app.map.updateGeography(container, state, (pct) => {
                                if (pct < 100) setLoading('Dibujando ' + labelNivel2 + '... ' + pct + '%');
                            });
                        }).then(() => {
                            hideLoading();
                            onUpdate();
                        }).catch(err => {
                            console.error("[SIARHE] Error al cargar la capa detallada", err);
                            hideLoading();
                            state.isGeoLocMode = false;
                            e.target.value = 'base';
                        });
                    }
                });
            }

            const resetBtn = wrapper.querySelector('.siarhe-reset-btn');
            if (resetBtn) resetBtn.addEventListener('click', () => app.controls.resetAll(container, state, onUpdate));

            if (!isTableOnly) app.controls.initPalette(container, state, wrapper, onUpdate);

            // Boton de ancho: el sitio define la disposicion por defecto (contenedor o canvas) y el visitante puede cambiarla;
            // su eleccion se recuerda en el navegador (localStorage «siarheLayout»)
            const layoutBtn = wrapper.querySelector('.siarhe-layout-btn');
            if (layoutBtn) {
                const fijaAncho = function() { document.documentElement.style.setProperty('--siarhe-vw', document.documentElement.clientWidth + 'px'); };
                let escucha = false;
                const pon = function(canvas) {
                    if (canvas) { fijaAncho(); if (!escucha) { window.addEventListener('resize', fijaAncho); escucha = true; } }
                    container.classList.toggle('siarhe-layout-canvas', canvas);
                    layoutBtn.setAttribute('aria-pressed', canvas ? 'true' : 'false');
                    const t = layoutBtn.querySelector('.siarhe-btn-texto');
                    if (t) t.textContent = canvas ? 'Ancho normal' : 'Ancho completo';
                };
                let guardado = null;
                try { guardado = window.localStorage.getItem('siarheLayout'); } catch (e) {}
                pon(guardado === 'canvas' || guardado === 'contenedor' ? guardado === 'canvas' : container.classList.contains('siarhe-layout-canvas'));
                layoutBtn.addEventListener('click', function() {
                    const canvas = !container.classList.contains('siarhe-layout-canvas');
                    pon(canvas);
                    try { window.localStorage.setItem('siarheLayout', canvas ? 'canvas' : 'contenedor'); } catch (e) {}
                });
            }

            // Boton «Ver etiquetas»: nombres sobre el mapa, sin encimarse (app.map.setLabels)
            const labelsBtn = wrapper.querySelector('.siarhe-labels-btn');
            if (labelsBtn) {
                state.syncLabelsBtn = function() {
                    const on = !!state.showLabels;
                    labelsBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    labelsBtn.classList.toggle('is-active', on);
                    const t = labelsBtn.querySelector('.siarhe-btn-texto');
                    if (t) t.textContent = on ? 'Ocultar etiquetas' : 'Ver etiquetas';
                };
                labelsBtn.addEventListener('click', function() {
                    if (app.map) app.map.setLabels(state, !state.showLabels);
                    state.syncLabelsBtn();
                });
                state.syncLabelsBtn();
            }

            ph.appendChild(wrapper);
            app.controls.buildBaseSelectors(container, state, wrapper, onUpdate);
            app.controls.buildThemeSelector(container, state, wrapper);
            app.controls.buildMeasureSwitch(container, state, wrapper);
            if (!isTableOnly) app.controls.buildSearch(container, state, wrapper);
            app.controls.addControlIcons(container, wrapper);
            app.controls.syncIndicatorOptions(container, state);
            app.controls.enhanceDropdowns(container, wrapper);
            app.controls.updateMarkerDropdownText(state);
        },

        /**
         * Panel "Personalizar": el visitante cambia los colores del mapa para su vista y para la imagen
         * que descargue. Los cambios viven solo en el navegador (state.customColors); no se guardan en el sitio.
         */
        initPalette: function(container, state, wrapper, onUpdate) {
            const btn = wrapper.querySelector('.siarhe-palette-btn');
            const row = wrapper.querySelector('.siarhe-controls-row[data-fila="vista"]');
            if (!btn || !row) return;

            const hex = (c) => { try { const k = d3.color(c); return k ? k.formatHex() : '#000000'; } catch (e) { return '#000000'; } };
            const norm = (m) => (m === 'mono' || m === 'monocromatico') ? 'monocromatico' : (m === 'rangos' ? 'rangos' : 'cuartiles');

            // Colores de los rangos: si la metrica trae los suyos se muestran esos
            const rangeColors = () => {
                const info = state.metricas[state.currentMetric] || {};
                return [1, 2, 3, 4].map(n => (info.custom_colors === true && info['r' + n + '_color']) ? info['r' + n + '_color'] : app.colors.CUSTOM_RANGE[n - 1]);
            };
            const current = () => state.customColors || { RANGE: app.colors.RANGE, MONO: app.colors.MONO, CUSTOM_RANGE: rangeColors(), ZERO: app.colors.ZERO, NULL: app.colors.NULL };

            const GROUPS = [
                { id: 'cuartiles', title: 'Degradado por colores', key: 'RANGE', labels: ['Mínimo', 'Q1', 'Q2', 'Q3', 'Máximo'] },
                { id: 'monocromatico', title: 'Escala monocromática', key: 'MONO', labels: ['Claro', 'Oscuro'] },
                { id: 'rangos', title: 'Rangos', key: 'CUSTOM_RANGE', labels: ['Rango 1', 'Rango 2', 'Rango 3', 'Rango 4'] },
                { id: 'especiales', title: 'Valores especiales', singles: [['ZERO', 'Valor 0'], ['NULL', 'Sin dato (S/D)']] }
            ];

            const panel = document.createElement('div');
            panel.className = 'siarhe-palette-panel';
            panel.hidden = true;
            panel.setAttribute('role', 'group');
            panel.setAttribute('aria-label', 'Personalizar colores del mapa');

            const head = document.createElement('div');
            head.className = 'siarhe-palette-head';
            const hTitle = document.createElement('strong'); hTitle.textContent = 'Personalizar colores del mapa';
            const hNote = document.createElement('span'); hNote.textContent = 'Solo cambia tu vista y la imagen que descargues. No se guarda en el sitio.';
            head.appendChild(hTitle); head.appendChild(hNote);
            panel.appendChild(head);

            const groupsEl = document.createElement('div');
            groupsEl.className = 'siarhe-palette-groups';
            const inputs = [];

            GROUPS.forEach(g => {
                const fs = document.createElement('fieldset');
                fs.className = 'siarhe-palette-group';
                fs.setAttribute('data-style', g.id);
                const lg = document.createElement('legend'); lg.textContent = g.title; fs.appendChild(lg);
                const items = g.singles ? g.singles.map(x => ({ key: x[0], idx: null, label: x[1] }))
                                        : current()[g.key].map((_, i) => ({ key: g.key, idx: i, label: g.labels[i] || ('Color ' + (i + 1)) }));
                items.forEach(it => {
                    const lab = document.createElement('label');
                    lab.className = 'siarhe-palette-item';
                    const inp = document.createElement('input');
                    inp.type = 'color';
                    inp.setAttribute('aria-label', g.title + ': ' + it.label);
                    const txt = document.createElement('span'); txt.textContent = it.label;
                    lab.appendChild(inp); lab.appendChild(txt);
                    fs.appendChild(lab);
                    inputs.push({ el: inp, key: it.key, idx: it.idx });
                });
                groupsEl.appendChild(fs);
            });
            panel.appendChild(groupsEl);

            const actions = document.createElement('div');
            actions.className = 'siarhe-palette-actions';
            const restore = document.createElement('button');
            restore.type = 'button'; restore.className = 'siarhe-btn siarhe-palette-restore'; restore.textContent = 'Restaurar colores del sitio';
            const close = document.createElement('button');
            close.type = 'button'; close.className = 'siarhe-btn siarhe-palette-close'; close.textContent = 'Cerrar';
            actions.appendChild(restore); actions.appendChild(close);
            panel.appendChild(actions);

            row.insertAdjacentElement('afterend', panel);

            const refresh = () => {
                const pal = current();
                inputs.forEach(i => { i.el.value = hex(i.idx === null ? pal[i.key] : pal[i.key][i.idx]); });
                const mode = norm(state.colorMode || (state.metricas[state.currentMetric] || {}).scale_type);
                groupsEl.querySelectorAll('.siarhe-palette-group').forEach(f => { const id = f.getAttribute('data-style'); f.hidden = !(id === mode || id === 'especiales'); });
                restore.disabled = !state.customColors;
                btn.classList.toggle('is-modified', !!state.customColors);
            };
            state._paletteRefresh = refresh;

            let pending = false;
            const schedule = () => {
                if (pending) return;
                pending = true;
                requestAnimationFrame(() => { pending = false; if (typeof onUpdate === 'function') onUpdate(); refresh(); });
            };

            inputs.forEach(i => i.el.addEventListener('input', () => {
                if (!state.customColors) {
                    const c = current();
                    state.customColors = { RANGE: c.RANGE.slice(), MONO: c.MONO.slice(), CUSTOM_RANGE: c.CUSTOM_RANGE.slice(), ZERO: c.ZERO, NULL: c.NULL };
                }
                if (i.idx === null) state.customColors[i.key] = i.el.value; else state.customColors[i.key][i.idx] = i.el.value;
                schedule();
            }));

            const setOpen = (open) => {
                panel.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) refresh();
            };
            btn.addEventListener('click', () => setOpen(panel.hidden));
            close.addEventListener('click', () => { setOpen(false); btn.focus(); });
            restore.addEventListener('click', () => { state.customColors = null; refresh(); if (typeof onUpdate === 'function') onUpdate(); });
            panel.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });

            // Si cambian el estilo o el indicador con el panel abierto, se reflejan sus colores
            container.addEventListener('change', (e) => { if (!panel.hidden && e.target && e.target.id === 'siarhe-escala-select') refresh(); });
            panel.addEventListener('pointerenter', () => { if (!state.customColors) refresh(); });
        },

        toggleMarker: async function(type, state, container) {
            const loading = container.querySelector('.siarhe-loading-overlay');
            const msg = loading ? loading.querySelector('p') : null;
            const mkLabel = (state.markerLabels[type] || {}).label || type;

            const showLoading = (text) => {
                if (!loading) return;
                if (msg) msg.textContent = text;
                loading.style.position = 'absolute'; loading.style.top = '0'; loading.style.left = '0';
                loading.style.width = '100%'; loading.style.height = '100%';
                loading.style.background = 'rgba(248, 250, 252, 0.9)';
                loading.style.display = 'flex'; loading.style.flexDirection = 'column';
                loading.style.justifyContent = 'center'; loading.style.alignItems = 'center';
                loading.style.zIndex = '100';
            };

            try {
                if (state.activeMarkers.has(type)) {
                    state.activeMarkers.delete(type);
                } else {
                    state.activeMarkers.add(type);
                    if (!state.markersData[type]) {
                        showLoading(`Procesando ${mkLabel}...`);
                        await app.utils.nextFrame(); // deja pintar el overlay antes del trabajo pesado
                        await app.controls.loadMarkerData(type, state, container);
                    }
                }
                // Sin await a proposito: la aparicion progresiva de los marcadores ya es el indicador de avance
                if (app.map) app.map.updateMarkers(state);
            } catch (e) {
                console.error(`[SIARHE] Error cargando marcadores ${type}:`, e);
                state.activeMarkers.delete(type); // no dejamos el tipo "activo" sin datos
                if (msg) {
                    msg.textContent = `No se pudo cargar ${mkLabel}`;
                    await new Promise(r => setTimeout(r, 1800));
                }
                if (app.map) app.map.updateMarkers(state);
            } finally {
                if (loading) loading.style.display = 'none'; // siempre, incluso si algo fallo
                app.controls.updateMarkerDropdownText(state);
                if (app.urlState) app.urlState.write(container, state);
            }
        },

        /**
         * Archivo de un marcador para el año que se ve. state.markerUrls[tipo] es { periodo: url } con los años cargados y,
         * si existe, la capa "total" (todas las unidades de la historia). Se usa el del año; si no hay, el total; si tampoco,
         * el año anterior más cercano y, en último caso, el más antiguo que exista. Devuelve '' si no hay ninguno.
         */
        markerUrl: function(state, type) {
            const urls = state.markerUrls ? state.markerUrls[type] : null;
            if (!urls) return '';
            if (typeof urls === 'string') return urls;
            const anio = String((state.base && state.base.anio) || (state.containerEl && state.containerEl.dataset.anio) || '');
            if (urls[anio]) return urls[anio];
            if (urls.total) return urls.total;
            const anios = Object.keys(urls).map(Number).filter(function(a) { return a > 0; }).sort(function(a, b) { return a - b; });
            if (!anios.length) return '';
            let elegido = null;
            anios.forEach(function(a) { if (a <= Number(anio)) elegido = a; });
            return urls[String(elegido === null ? anios[0] : elegido)] || '';
        },

        /**
         * Tras cambiar de año: los marcadores cuyo archivo cambia se descartan y, si estaban activos, se vuelven a cargar
         * con el archivo del año nuevo.
         */
        refreshMarkersForYear: async function(container, state) {
            const tipos = Object.keys(state.markersData || {});
            const recargar = [];
            state._markerUrlUsed = state._markerUrlUsed || {};
            tipos.forEach(function(type) {
                if (type.charAt(0) === '@') return; // capas y grupos de siarhe-posicion.js: los rehace app.pos.apply
                const url = app.controls.markerUrl(state, type);
                if (state._markerUrlUsed[type] === url) return;
                delete state.markersData[type];
                if (state.activeMarkers.has(type)) recargar.push(type);
            });
            for (const type of recargar) {
                try { await app.controls.loadMarkerData(type, state, container); }
                catch (err) {
                    console.error('[SIARHE] No se pudo cargar el marcador ' + type + ' del año elegido:', err);
                    state.activeMarkers.delete(type);
                    const chk = container.querySelector('.mc-check[value="' + CSS.escape(type) + '"]');
                    if (chk) chk.checked = false;
                }
            }
            if (recargar.length && app.map) { app.map.updateMarkers(state); app.map.renderMarkerLegend(state); }
            app.controls.updateMarkerDropdownText(state);
            if (app.pos && state.pos && state.pos.ui) await app.pos.apply(container, state);
        },

        /**
         * Worker que prepara la capa detallada (municipios o localidades): descarga el GeoJSON, lo proyecta y genera
         * trazos en tres niveles de detalle fuera del hilo principal. Si no se puede usar, devuelve null.
         */
        _geoWorker: function(container) {
            if (app._geoWorkerFailed) return null;
            if (app._geoWorker) return app._geoWorker;
            const src = container.dataset.geoWorker;
            if (!src || typeof Worker === 'undefined') return null;
            try {
                const w = new Worker(src);
                w._pending = new Map();
                w._seq = 0;
                w.onmessage = (e) => {
                    const m = e.data || {};
                    const p = w._pending.get(m.id);
                    if (!p) return;
                    if (m.ok === undefined) { if (p.onProgress && m.progress !== undefined) p.onProgress(m.progress); return; }
                    w._pending.delete(m.id);
                    if (m.ok) p.resolve(m); else p.reject(new Error(m.error || 'error en el worker'));
                };
                w.onerror = () => {
                    app._geoWorkerFailed = true;
                    app._geoWorker = null;
                    w._pending.forEach(p => p.reject(new Error('el worker no pudo ejecutarse')));
                    w._pending.clear();
                    try { w.terminate(); } catch (err) {}
                };
                app._geoWorker = w;
                return w;
            } catch (err) {
                app._geoWorkerFailed = true;
                return null;
            }
        },

        /**
         * Capa detallada lista para dibujar. Con el worker devuelve { _prepared: true, features: [{ properties, bounds, d: [3 trazos] }] };
         * sin el, la coleccion de elementos tal cual (se proyecta y dibuja en el hilo principal).
         */
        loadLocGeo: function(container, state, url, onProgress) {
            const worker = app.controls._geoWorker(container);
            const p = state.projection;
            const near0 = (v) => Math.abs(v) < 1e-9;
            // El worker solo reproduce Mercator sin rotacion y centrada en [0,0] (la proyeccion del mapa)
            const reproducible = worker && p && typeof p.scale === 'function' && p.rotate().every(near0) && p.center().every(near0);
            if (!reproducible) return app.geo.load(url);

            const t0 = performance.now();
            return new Promise((resolve, reject) => {
                const id = ++worker._seq;
                worker._pending.set(id, { resolve: resolve, reject: reject, onProgress: onProgress });
                worker.postMessage({ id: id, url: url, proj: { scale: p.scale(), translate: p.translate() }, tolerances: [0.25, 0.06], libs: app.geo.workerLibs() });
            }).then((res) => {
                app.utils.perf('worker de geometrias: capa detallada lista', t0, res.features.length + ' poligonos');
                return { type: 'FeatureCollection', _prepared: true, features: res.features };
            }).catch((err) => {
                console.warn('[SIARHE] El worker de geometrias no pudo preparar la capa, se usa el modo normal:', err);
                return app.geo.load(url);
            });
        },

        /**
         * Worker (hilo en segundo plano) que lee y procesa los CSV de marcadores. Uno por pagina; si el
         * navegador no lo soporta o falla, devuelve null y se usa el procesamiento normal.
         */
        _markerWorker: function(container) {
            if (app._markerWorkerFailed) return null;
            if (app._markerWorker) return app._markerWorker;
            const src = container.dataset.worker;
            if (!src || typeof Worker === 'undefined') return null;
            try {
                const w = new Worker(src);
                w._pending = new Map();
                w._seq = 0;
                w.onmessage = (e) => {
                    const m = e.data || {};
                    const p = w._pending.get(m.id);
                    if (!p) return;
                    w._pending.delete(m.id);
                    if (m.ok) p.resolve(m); else p.reject(new Error(m.error || 'error en el worker'));
                };
                w.onerror = () => {
                    app._markerWorkerFailed = true;
                    app._markerWorker = null;
                    w._pending.forEach(p => p.reject(new Error('el worker no pudo ejecutarse')));
                    w._pending.clear();
                    try { w.terminate(); } catch (err) {}
                };
                app._markerWorker = w;
                return w;
            } catch (err) {
                app._markerWorkerFailed = true;
                return null;
            }
        },

        /**
         * Descarga, filtra y agrupa el CSV de un tipo de marcador. El trabajo se trocea por tiempo
         * (cede el hilo cada ~12 ms) para no disparar el aviso "la pagina esta tardando".
         * Las cargas simultaneas del mismo tipo comparten una sola Promise.
         */
        loadMarkerData: function(type, state, container) {
            state._markerLoads = state._markerLoads || {};
            if (state._markerLoads[type]) return state._markerLoads[type];

            const task = (async () => {
                const url = app.controls.markerUrl(state, type);
                if (!url) throw new Error(`No hay archivo cargado para el marcador ${type}`);

                // Camino rapido: el worker descarga, lee, filtra y agrupa sin bloquear la pagina
                const worker = app.controls._markerWorker(container);
                if (worker) {
                    try {
                        const cfg = state.markerLabels[type] || {};
                        const spec = {
                            type: type,
                            isNacional: !!state.isNacional,
                            currentEnt: (container.dataset.cveEnt || '').toString().padStart(2, '0'),
                            filtroCol: cfg.filtro_col ? cfg.filtro_col.toLowerCase().trim() : null,
                            filtroVal: cfg.filtro_val ? cfg.filtro_val.toString().toLowerCase().trim() : null,
                            agruparCol: cfg.agrupar_col ? cfg.agrupar_col.toLowerCase().trim() : null,
                            reglas: (cfg.reglas_tooltip || []).map(r => ({ col: r.col, val: r.val, label: r.label })),
                            espectroCol: cfg.espectro_col || ''
                        };
                        const tW = performance.now();
                        const res = await new Promise((resolve, reject) => {
                            const id = ++worker._seq;
                            worker._pending.set(id, { resolve: resolve, reject: reject });
                            worker.postMessage({ id: id, url: url, spec: spec, libs: [(window.SiarheDataVizConfig || {}).capas].filter(Boolean) });
                        });
                        state.markersData[type] = res.points;
                        (state._markerUrlUsed = state._markerUrlUsed || {})[type] = url;
                        app.utils.perf('worker: ' + type + ' (descarga y lectura ' + res.readMs + ' ms, procesado ' + res.procMs + ' ms)', tW, res.rows + ' filas -> ' + res.points.length + ' puntos');
                        return;
                    } catch (err) {
                        console.warn('[SIARHE] El worker no pudo procesar ' + type + ', se usa el modo normal:', err);
                    }
                }

                let rawData;
                if (state.rawCSVDataCache && state.rawCSVDataCache[url]) {
                    rawData = state.rawCSVDataCache[url];
                } else {
                    const tCsv = performance.now();
                    // Capa en JSON o CSV: las dos dan las mismas filas
                    rawData = /\.json(\?|$)/i.test(url) ? window.SiarheCapas.aObjetos(await d3.json(url)) : await d3.csv(url);
                    app.utils.perf('archivo de ' + type + ' descargado y leido', tCsv, rawData.length + ' filas');
                    if (!state.rawCSVDataCache) state.rawCSVDataCache = {};
                    state.rawCSVDataCache[url] = rawData;
                }
                if (!Array.isArray(rawData)) rawData = [];
                const tProc = performance.now();

                const config = state.markerLabels[type] || {};
                const filtroCol = config.filtro_col ? config.filtro_col.toLowerCase().trim() : null;
                const filtroVal = config.filtro_val ? config.filtro_val.toString().toLowerCase().trim() : null;
                const agruparCol = config.agrupar_col ? config.agrupar_col.toLowerCase().trim() : null;
                const reglas = config.reglas_tooltip || [];

                // 🚀 OPTIMIZACIÓN: resolvemos los nombres REALES de columna una sola
                // vez por CSV (no por fila). Antes, cada fila pagaba varios
                // Object.keys().find() (uno por cada campo, ~12 por fila), lo cual
                // es muy costoso en datasets nacionales grandes.
                const norm = window.SiarheCapas.normaliza;
                const colMap = {};
                if (rawData.length > 0) {
                    Object.keys(rawData[0]).forEach(k => { colMap[norm(k)] = k; });
                }
                const resolveKey = (names) => {
                    for (let n of names) {
                        const real = colMap[norm(n)];
                        if (real) return real;
                    }
                    return null;
                };
                const fastVal = (d, key) => (key && d[key] !== undefined && d[key] !== null) ? d[key].toString().trim() : '';

                const latKey = resolveKey(['latitud', 'lat']);
                const lonKey = resolveKey(['longitud', 'lon']);
                const cveEntKey = resolveKey(['cve_ent', 'cve ent', 'clave de la entidad', 'clave_entidad', 'entidad']);
                const cluesKey = resolveKey(['clues']);
                const institucionKey = resolveKey(['institución', 'institucion', 'nombre de la institucion', 'clave_institucion']);
                const nombreKey = resolveKey(['nombre_unidad', 'nombre de la unidad', 'unidad']);
                const municipioKey = resolveKey(['municipio']);
                const tipoEstabKey = resolveKey(['tipo_establecimiento', 'tipo establecimiento', 'nombre tipo establecimiento']);
                const tipologiaKey = resolveKey(['nombre_tipologia', 'nombre tipologia', 'nombre de tipologia']);
                const nivelAtencionKey = resolveKey(['nivel_atencion', 'nivel atencion']);
                const cveNivelKey = resolveKey(['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                const jurisdiccionKey = resolveKey(['jurisdiccion', 'jurisdicción']);
                const estratoKey = resolveKey(['estrato_unidad', 'estrato unidad']);

                const filtroColKey = filtroCol ? resolveKey([filtroCol]) : null;
                const agruparColKey = agruparCol ? resolveKey([agruparCol]) : null;
                const reglasResolved = reglas.map(r => ({ ...r, _colKey: resolveKey([r.col]) }));


                const currentEnt = (container.dataset.cveEnt || '').toString().padStart(2, '0');

                // Cede el hilo cuando el trozo actual supera ~12 ms
                let sliceStart = performance.now();
                const tick = async () => {
                    if (performance.now() - sliceStart > 12) {
                        await app.utils.nextFrame();
                        sliceStart = performance.now();
                    }
                };

                // 1) Filtrado
                const filteredData = [];
                for (let i = 0; i < rawData.length; i++) {
                    const d = rawData[i];
                    const lat = parseFloat(fastVal(d, latKey));
                    const lon = parseFloat(fastVal(d, lonKey));

                    let keep = !isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0;

                    if (keep && !state.isNacional && cveEntKey) {
                        // Sin clave de entidad el punto no se puede asignar a ningun mapa: no se dibuja
                        const cveEntMarker = fastVal(d, cveEntKey);
                        if (!cveEntMarker || cveEntMarker.padStart(2, '0') !== currentEnt) keep = false;
                    }

                    if (keep && filtroCol && filtroVal) {
                        keep = !!filtroColKey && fastVal(d, filtroColKey).toLowerCase() === filtroVal;
                    }

                    if (keep) filteredData.push(d);
                    if ((i & 127) === 0) await tick();
                }

                const buildPoint = (d) => ({
                    clues: fastVal(d, cluesKey),
                    institucion: fastVal(d, institucionKey),
                    nombre: fastVal(d, nombreKey),
                    municipio: fastVal(d, municipioKey),
                    lat: parseFloat(fastVal(d, latKey)),
                    lon: parseFloat(fastVal(d, lonKey)),
                    cve_ent: fastVal(d, cveEntKey),
                    tipo_estab: fastVal(d, tipoEstabKey),
                    tipologia: fastVal(d, tipologiaKey),
                    nivel_atencion: fastVal(d, nivelAtencionKey),
                    cve_nivel: fastVal(d, cveNivelKey),
                    jurisdiccion: fastVal(d, jurisdiccionKey),
                    estrato: fastVal(d, estratoKey),
                    tipo: type,
                    _raw: d
                });

                // 2) Agrupado (o mapeo directo)
                let processedData = [];
                if (agruparCol) {
                    const mapGroup = new Map();
                    if (agruparColKey) {
                        for (let i = 0; i < filteredData.length; i++) {
                            const d = filteredData[i];
                            const groupKey = fastVal(d, agruparColKey);

                            if (groupKey) {
                                let targetObj = mapGroup.get(groupKey);
                                if (!targetObj) {
                                    targetObj = buildPoint(d);
                                    targetObj._agrupados_total = 0;
                                    targetObj._conteo_reglas = {};
                                    reglasResolved.forEach(r => { targetObj._conteo_reglas[r.label] = 0; });
                                    mapGroup.set(groupKey, targetObj);
                                }
                                targetObj._agrupados_total += 1;

                                reglasResolved.forEach(r => {
                                    if (r._colKey) {
                                        const rowVal = fastVal(d, r._colKey).toLowerCase();
                                        const targetVal = (r.val === undefined || r.val === null ? '' : r.val).toString().toLowerCase().trim();
                                        if (rowVal === targetVal) targetObj._conteo_reglas[r.label] += 1;
                                    }
                                });
                            }
                            if ((i & 127) === 0) await tick();
                        }
                    }
                    processedData = Array.from(mapGroup.values());
                } else {
                    for (let i = 0; i < filteredData.length; i++) {
                        processedData.push(buildPoint(filteredData[i]));
                        if ((i & 127) === 0) await tick();
                    }
                }

                state.markersData[type] = processedData;
                (state._markerUrlUsed = state._markerUrlUsed || {})[type] = url;
                app.utils.perf('filtrado y agrupado de ' + type, tProc, processedData.length + ' puntos');
            })();

            state._markerLoads[type] = task;
            const cleanup = () => { delete state._markerLoads[type]; };
            task.then(cleanup, cleanup);
            return task;
        },

        /**
         * Devuelve TODO a la configuracion inicial, como al recargar la pagina:
         * indicador, estilo de mapa, vista base, marcadores apagados, zoom, avisos y URL.
         */
        resetAll: function(container, state, onUpdate) {
            // 1) Menus y avisos abiertos
            container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
            const toast = container.querySelector('.siarhe-smart-toast');
            if (toast) toast.classList.remove('show');
            if (state.tooltip) state.tooltip.style('opacity', 0).style('display', 'none');

            // 2) Indicador y estilo de mapa iniciales
            const defaults = state._urlDefaults || {};
            if (defaults.metric && state.metricas[defaults.metric]) state.currentMetric = defaults.metric;
            const info = state.metricas[state.currentMetric] || {};
            state.colorMode = info.scale_type || 'cuartiles';
            state.realStyleMode = state.colorMode;
            app.sortConfig = { key: 'tasa', direction: 'desc' };

            const trigger = container.querySelector('#c-indicador .siarhe-cs-trigger');
            if (trigger) trigger.innerHTML = `<span>${app.utils.esc(app.controls.indicatorName(info))}</span>`;
            container.querySelectorAll('#c-indicador .siarhe-cs-option').forEach(o => {
                o.classList.toggle('selected', o.dataset.key === state.currentMetric);
            });
            state.medida = info.tipo === 'tasa' ? 'tasa' : (info.tipo ? 'absoluto' : state.medida);
            if (state.refreshThemes) state.refreshThemes(); else app.controls.syncIndicatorOptions(container, state);
            if (state.refreshMeasure) state.refreshMeasure();
            const selEscala = container.querySelector('#siarhe-escala-select');
            if (selEscala) selEscala.value = (state.colorMode === 'degradado' || state.colorMode === 'quartiles') ? 'cuartiles' : (state.colorMode === 'mono' ? 'monocromatico' : state.colorMode);

            // 3) Etiquetas y marcadores apagados
            if (app.map) app.map.setLabels(state, false);
            if (state.syncLabelsBtn) state.syncLabelsBtn();
            if (app.pos) app.pos.reset(container, state);
            state.activeMarkers.clear();
            container.querySelectorAll('.mc-check').forEach(chk => { chk.checked = false; });
            if (app.map) app.map.updateMarkers(state);
            app.controls.updateMarkerDropdownText(state);

            // 4) Vista geografica base
            state.isGeoLocMode = false;
            const selVista = container.querySelector('#siarhe-vista-select');
            if (selVista) selVista.value = 'base';
            if (app.map) app.map.updateGeography(container, state);

            // 5) Medicion de distancias fuera y limpia
            if (app.map && app.map.setMeasureMode) app.map.setMeasureMode(state, false);

            // 5b) Zoom y desplazamiento iniciales
            if (state.svg && state.zoom && state.initialTransform) {
                state.svg.transition().duration(app.utils.motionMs(600)).call(state.zoom.transform, state.initialTransform);
            }

            // 5c) Colores personalizados por el visitante
            state.customColors = null;
            if (typeof state._paletteRefresh === 'function') state._paletteRefresh();

            // 6) Recolorear mapa, tabla y totales; la URL vuelve a quedar limpia
            if (typeof onUpdate === 'function') onUpdate();
            if (app.urlState) app.urlState.flush(container, state);
        },

        /**
         * Accesibilidad de los selectores personalizados: tab stop, roles ARIA, flechas/Enter/Espacio/Escape
         * y etiquetas para los <select> nativos.
         */
        enhanceDropdowns: function(container, root) {
            let uid = 0;
            const sync = () => root.querySelectorAll('[aria-haspopup="listbox"]').forEach(t => {
                const m = t.nextElementSibling;
                t.setAttribute('aria-expanded', m && m.classList.contains('open') ? 'true' : 'false');
            });

            root.querySelectorAll('.siarhe-cs-trigger, .mc-trigger').forEach(trigger => {
                if (trigger.style.cursor === 'not-allowed') { trigger.setAttribute('aria-disabled', 'true'); return; }
                const menu = trigger.nextElementSibling;
                if (!menu || !/(siarhe-cs-menu|mc-menu)/.test(menu.className)) return;

                uid++;
                trigger.id = trigger.id || ('siarhe-dd-' + uid + '-' + Math.random().toString(36).slice(2, 6));
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('role', 'button');
                trigger.setAttribute('aria-haspopup', 'listbox');
                trigger.setAttribute('aria-expanded', 'false');

                const group = trigger.closest('.siarhe-control-group');
                const label = group ? group.querySelector('label') : null;
                if (label) {
                    label.id = label.id || (trigger.id + '-lbl');
                    trigger.setAttribute('aria-labelledby', label.id + ' ' + trigger.id);
                }

                const list = menu.querySelector('.siarhe-cs-options');
                if (list) list.setAttribute('role', 'listbox');
                menu.querySelectorAll('.siarhe-cs-option, .mc-option').forEach(o => {
                    o.setAttribute('role', 'option');
                    o.setAttribute('tabindex', '-1');
                });

                trigger.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!menu.classList.contains('open')) trigger.click();
                        const first = menu.querySelector('[role="option"]');
                        if (e.key === 'ArrowDown' && first) setTimeout(() => first.focus(), 60);
                    }
                });

                menu.addEventListener('keydown', (e) => {
                    const opts = Array.from(menu.querySelectorAll('[role="option"]')).filter(o => o.style.display !== 'none');
                    const i = opts.indexOf(document.activeElement);

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const n = opts[i + 1] || opts[0];
                        if (n) n.focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (i > 0) opts[i - 1].focus();
                        else { const inp = menu.querySelector('input[type="text"]'); if (inp) inp.focus(); }
                    } else if ((e.key === 'Enter' || e.key === ' ') && i >= 0) {
                        e.preventDefault();
                        opts[i].click();
                        if (!menu.classList.contains('open')) trigger.focus();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        menu.classList.remove('open');
                        sync();
                        trigger.focus();
                    }
                    setTimeout(sync, 0);
                });
            });

            // <select> nativos: su <label> visible no esta enlazado, se lo damos como nombre accesible
            root.querySelectorAll('select.siarhe-metric-select').forEach(sel => {
                const group = sel.closest('.siarhe-control-group');
                const label = group ? group.querySelector('label') : null;
                if (label) sel.setAttribute('aria-label', label.textContent.trim());
            });

            // aria-expanded sigue a las aperturas/cierres hechos por clic
            container.addEventListener('click', () => setTimeout(sync, 0), true);
        },

        /**
         * Agrupa los marcadores disponibles de un tipo (posicion | espectro). Primero van los grupos del sistema (campo `grupo`
         * del marcador; rotulos en data-marker-grupos: Establecimientos de Salud, Clinicas), en el orden de ese catalogo y aunque
         * tengan un solo marcador. Los demas se agrupan por archivo: un archivo con varios marcadores es un grupo propio y los
         * archivos de un solo marcador se juntan en un grupo final. En cada grupo el nombre de la opcion pierde lo que repite del
         * titulo («Establecimientos de Salud: Primer Nivel» -> «Primer Nivel»; «Clinicas de cateteres» -> «Cateteres»).
         * Devuelve [{ titulo, claves: [...], corto: { clave: texto } }].
         */
        markerGroups: function(state, typeFilter) {
            const claves = Object.keys(state.markerUrls || {}).filter(function(k) {
                return k.charAt(0) !== '@' && app.controls.markerUrl(state, k) && app.utils.markerGroup((state.markerLabels[k] || {}).tipo) === typeFilter;
            });
            let rotulos = state.markerGrupos;
            if (!rotulos) {
                try { rotulos = JSON.parse((state.containerEl && state.containerEl.dataset.markerGrupos) || '{}') || {}; } catch (e) { rotulos = {}; }
                state.markerGrupos = rotulos;
            }
            const etiqueta = function(k) { return (state.markerLabels[k] || {}).label || k; };
            const mayus = function(t) { return t.charAt(0).toUpperCase() + t.slice(1); };
            const norm = app.controls._normBusqueda;
            // Nombres cortos de un grupo: se quita el prefijo «Titulo: » o «Titulo de » cuando todos lo comparten
            const cortos = function(ks, titulo) {
                const corto = {};
                const quita = function(t) {
                    const i = t.indexOf(': ');
                    if (i > 0 && (!titulo || norm(t.slice(0, i)) === norm(titulo))) return [t.slice(0, i), t.slice(i + 2)];
                    if (titulo && norm(t).indexOf(norm(titulo) + ' de ') === 0) return [titulo, mayus(t.slice(titulo.length + 4))];
                    return null;
                };
                const partes = ks.map(function(k) { return quita(etiqueta(k)); });
                const comun = ks.length > 0 && partes.every(function(x) { return x && x[1] && norm(x[0]) === norm(partes[0][0]); });
                ks.forEach(function(k, i) { corto[k] = comun ? partes[i][1] : etiqueta(k); });
                return { corto: corto, prefijo: comun ? partes[0][0] : '' };
            };

            const grupos = [];
            const delSistema = {};
            claves.forEach(function(k) {
                const g = (state.markerLabels[k] || {}).grupo;
                if (g && rotulos[g]) (delSistema[g] = delSistema[g] || []).push(k);
            });
            Object.keys(rotulos).forEach(function(g) {
                if (delSistema[g]) grupos.push({ titulo: rotulos[g], claves: delSistema[g], corto: cortos(delSistema[g], rotulos[g]).corto, sistema: g });
            });

            const porArchivo = {}, orden = [], sueltos = [];
            claves.forEach(function(k) {
                const m = state.markerLabels[k] || {};
                if (m.grupo && rotulos[m.grupo]) return;
                const arch = m.archivo || k;
                if (!porArchivo[arch]) { porArchivo[arch] = []; orden.push(arch); }
                porArchivo[arch].push(k);
            });
            orden.forEach(function(arch) {
                const ks = porArchivo[arch];
                if (ks.length < 2) { sueltos.push(ks[0]); return; }
                const c = cortos(ks, '');
                const deArchivo = String(arch).replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ');
                grupos.push({ titulo: c.prefijo || mayus(deArchivo), claves: ks, corto: c.corto });
            });
            if (sueltos.length) {
                const corto = {};
                sueltos.forEach(function(k) { corto[k] = etiqueta(k); });
                grupos.push({ titulo: grupos.length ? 'Otros marcadores' : 'Marcadores', claves: sueltos, corto: corto });
            }
            return grupos;
        },

        /**
         * Fila de marcadores (posicion o densidad): un desplegable por grupo (markerGroups) con casillas, buscador y los atajos
         * «Todos» / «Ninguno», que actuan sobre lo que el buscador deja ver. Varios grupos pueden estar activos a la vez.
         */
        buildMarkerRow: function(container, state, wrapper, typeFilter, opts) {
            opts = opts || {};
            const fila = wrapper.querySelector('.siarhe-controls-row[data-fila="' + (typeFilter === 'posicion' ? 'posicion' : 'densidad') + '"]');
            if (!fila) return [];
            // soloOtros: sin los grupos del sistema (establecimientos y clinicas), que en posicion atiende siarhe-posicion.js
            let grupos = app.controls.markerGroups(state, typeFilter);
            if (opts.soloOtros) grupos = grupos.filter(function(g) { return !g.sistema; });
            const creados = [];
            const nuevoControl = function(titulo) {
                const g = document.createElement('div');
                g.className = 'siarhe-control-group siarhe-ctl-marcadores';
                const l = document.createElement('label'); l.textContent = titulo; g.appendChild(l);
                fila.appendChild(g);
                creados.push(g);
                return g;
            };

            if (!grupos.length && opts.soloOtros && !opts.marcador) return creados;
            if (!grupos.length) {
                const g = nuevoControl(typeFilter === 'posicion' ? 'Marcadores' : 'Densidad');
                const t = document.createElement('div');
                t.className = 'mc-trigger is-placeholder is-disabled'; t.setAttribute('aria-disabled', 'true');
                t.innerHTML = '<span>No disponibles</span>';
                g.appendChild(t);
                return creados;
            }

            const sinAcentos = app.controls._normBusqueda;
            grupos.forEach(function(grupo) {
                const g = nuevoControl(grupo.titulo);
                const field = document.createElement('div'); field.className = 'mc-field';
                const trigger = document.createElement('div'); trigger.className = 'mc-trigger is-placeholder trigger-' + typeFilter;
                trigger.innerHTML = '<span>Sin marcadores</span>';
                const menu = document.createElement('div'); menu.className = 'mc-menu';
                const caja = document.createElement('div'); caja.className = 'siarhe-cs-search';
                caja.addEventListener('click', function(e) { e.stopPropagation(); });
                const buscar = document.createElement('input'); buscar.type = 'text'; buscar.placeholder = 'Filtrar...';
                buscar.setAttribute('aria-label', 'Filtrar ' + grupo.titulo.toLowerCase());
                caja.appendChild(buscar);
                const acciones = document.createElement('div'); acciones.className = 'mc-acciones';
                acciones.addEventListener('click', function(e) { e.stopPropagation(); });
                const bTodos = document.createElement('button'); bTodos.type = 'button'; bTodos.textContent = 'Todos';
                const bNinguno = document.createElement('button'); bNinguno.type = 'button'; bNinguno.textContent = 'Ninguno';
                acciones.appendChild(bTodos); acciones.appendChild(bNinguno);
                const opciones = document.createElement('div'); opciones.className = 'siarhe-cs-options';

                grupo.claves.forEach(function(key) {
                    const opt = document.createElement('div'); opt.className = 'mc-option';
                    opt.innerHTML = '<input type="checkbox" class="mc-check" value="' + app.utils.esc(key) + '"> <span>' + app.utils.esc(grupo.corto[key]) + '</span>';
                    const chk = opt.querySelector('input');
                    chk.checked = state.activeMarkers.has(key);
                    opt.addEventListener('click', function(e) {
                        if (e.target.tagName !== 'INPUT') { chk.checked = !chk.checked; app.controls.toggleMarker(key, state, container); }
                    });
                    chk.addEventListener('click', function(e) { e.stopPropagation(); app.controls.toggleMarker(key, state, container); });
                    opciones.appendChild(opt);
                });

                const visibles = function() {
                    return Array.from(opciones.querySelectorAll('.mc-option')).filter(function(o) { return o.style.display !== 'none'; })
                        .map(function(o) { return o.querySelector('.mc-check'); });
                };
                buscar.addEventListener('input', function() {
                    const q = sinAcentos(buscar.value);
                    opciones.querySelectorAll('.mc-option').forEach(function(o) { o.style.display = sinAcentos(o.textContent).indexOf(q) !== -1 ? '' : 'none'; });
                });
                // «Todos»: activa uno por uno (cada marcador puede descargar su archivo); «Ninguno» apaga los visibles de una vez
                bTodos.addEventListener('click', async function() {
                    bTodos.disabled = true;
                    for (const chk of visibles()) {
                        if (state.activeMarkers.has(chk.value)) continue;
                        chk.checked = true;
                        await app.controls.toggleMarker(chk.value, state, container);
                        if (!state.activeMarkers.has(chk.value)) chk.checked = false; // no se pudo cargar
                    }
                    bTodos.disabled = false;
                });
                bNinguno.addEventListener('click', function() {
                    visibles().forEach(function(chk) { chk.checked = false; state.activeMarkers.delete(chk.value); });
                    if (app.map) { app.map.updateMarkers(state); app.map.renderMarkerLegend(state); }
                    app.controls.updateMarkerDropdownText(state);
                    if (app.urlState) app.urlState.write(container, state);
                });

                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const abierto = menu.classList.contains('open');
                    container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(function(m) { m.classList.remove('open'); });
                    if (!abierto) {
                        menu.classList.add('open'); buscar.value = '';
                        opciones.querySelectorAll('.mc-option').forEach(function(o) { o.style.display = ''; });
                        setTimeout(function() { buscar.focus(); }, 50);
                    }
                });

                menu.appendChild(caja); menu.appendChild(acciones); menu.appendChild(opciones);
                field.appendChild(trigger); field.appendChild(menu); g.appendChild(field);
                state._mcDrops.push({ trigger: trigger, grupo: grupo });
            });
            return creados;
        },

        /** Resumen de cada desplegable de marcadores: «Sin marcadores», el nombre del unico activo, «Todos (n)» o «n seleccionados». */
        markerSummary: function(grupo, activos) {
            const sel = grupo.claves.filter(function(k) { return activos.has(k); });
            if (!sel.length) return '';
            if (sel.length === 1) return grupo.corto[sel[0]];
            if (sel.length === grupo.claves.length) return 'Todos (' + sel.length + ')';
            return sel.length + ' seleccionados';
        },

        updateMarkerDropdownText: function(state) {
            (state._mcDrops || []).forEach(function(d) {
                const texto = app.controls.markerSummary(d.grupo, state.activeMarkers);
                const span = document.createElement('span');
                span.textContent = texto || 'Sin marcadores';
                d.trigger.innerHTML = '';
                d.trigger.appendChild(span);
                d.trigger.title = d.grupo.claves.filter(function(k) { return state.activeMarkers.has(k); }).map(function(k) { return d.grupo.corto[k]; }).join(', ');
                d.trigger.classList.toggle('is-placeholder', !texto);
            });
        },

        /** Icono de linea (los mismos de RHEMX) antes del titulo de cada control; se pinta con el color del texto. */
        addControlIcons: function(container, wrapper) {
            const base = container.dataset.img;
            if (!base) return;
            const iconos = {
                'c-anio': 'icon-calendario', 'c-unidad': 'icon-institucion', 'c-tema': 'icon-nivel', 'c-indicador': 'icon-grafico-pastel',
                'c-escala': 'icon-paleta', 'c-buscar': 'icon-buscar', 'c-vista': 'icon-entidad'
            };
            const etiquetas = Object.keys(iconos).map(function(id) { return [wrapper.querySelector('#' + id + ' > label'), iconos[id]]; });
            wrapper.querySelectorAll('.siarhe-ctl-marcadores > label').forEach(function(l) { etiquetas.push([l, 'icon-marcadores']); });
            etiquetas.forEach(function(par) {
                const label = par[0];
                if (!label || label.querySelector('.siarhe-ctl-icon')) return;
                const i = document.createElement('span');
                i.className = 'siarhe-ctl-icon';
                i.setAttribute('aria-hidden', 'true');
                i.style.setProperty('--ico', 'url("' + base + par[1] + '.svg")');
                label.insertBefore(i, label.firstChild);
            });
        },

        /** Nombre corto con que un indicador aparece en el selector (la tasa y la frecuencia de un indicador comparten el nombre). */
        indicatorName: function(info) {
            info = info || {};
            return info.nombre || info.label || info.fullLabel || '';
        },

        /**
         * Claves de la familia de una metrica (su tasa y su frecuencia), unidas por el campo pair. Solo cuentan las columnas que la
         * base cargada trae, para las metricas opcionales. Devuelve { tasa: clave|null, absoluto: clave|null }.
         */
        metricFamily: function(state, key) {
            const info = state.metricas[key] || {};
            const par = info.pair || key;
            const fam = { tasa: null, absoluto: null };
            Object.keys(state.metricas).forEach(function(k) {
                const m = state.metricas[k];
                if ((m.pair || k) !== par) return;
                if (m.opcional && state.columnas && !state.columnas.has(k)) return;
                const t = m.tipo === 'tasa' ? 'tasa' : 'absoluto';
                if (!fam[t]) fam[t] = k;
            });
            return fam;
        },

        /**
         * Oculta del selector de indicadores las metricas que no corresponden: opcionales cuya columna no trae la base cargada (p. ej.
         * las areas de especializacion en una base antigua), de otro tema o de otra medida (tasa / frecuencia). Una familia que no tiene
         * la medida elegida se muestra con la que si tiene. Si el indicador activo deja de estar disponible, pasa al primero visible.
         */
        syncIndicatorOptions: function(container, state, reselect) {
            if (!state.columnas) return;
            const tema = state.temaActual;
            const medida = state.medida || 'tasa';
            const visible = function(key) {
                const info = state.metricas[key] || {};
                if (info.opcional && !state.columnas.has(key)) return false;
                if (tema && (info.tema || 'general') !== tema) return false;
                const fam = app.controls.metricFamily(state, key);
                const propia = info.tipo === 'tasa' ? 'tasa' : 'absoluto';
                return propia === medida || !fam[medida];
            };
            container.querySelectorAll('.siarhe-cs-option[data-key]').forEach(function(o) { o.hidden = !visible(o.dataset.key); });
            if (reselect && !visible(state.currentMetric)) {
                const prim = container.querySelector('.siarhe-cs-option[data-key]:not([hidden])');
                if (prim) prim.click();
            }
        },

        /** Texto sin acentos ni mayusculas, para comparar nombres al buscar. */
        _normBusqueda: function(t) {
            return String(t == null ? '' : t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
        },

        /**
         * Buscador de lugares: entidades (mapa nacional) o municipios en la vista base y, con la vista detallada cargada,
         * municipios y localidades. Al elegir un resultado el mapa se acerca a ese lugar y lo resalta. Lista tipo combobox
         * (flechas, Enter y Esc).
         */
        buildSearch: function(container, state, wrapper) {
            const host = wrapper.querySelector('#c-buscar');
            if (!host || !app.map) return;
            const norm = app.controls._normBusqueda;
            const MAX_POR_GRUPO = 8;

            const entrada = document.createElement('input');
            entrada.type = 'search'; entrada.id = 'siarhe-buscar-entrada'; entrada.className = 'siarhe-metric-select siarhe-buscar-entrada';
            entrada.placeholder = state.isNacional ? 'Estado o municipio' : 'Municipio o localidad';
            entrada.autocomplete = 'off';
            entrada.setAttribute('role', 'combobox'); entrada.setAttribute('aria-autocomplete', 'list');
            entrada.setAttribute('aria-haspopup', 'listbox'); entrada.setAttribute('aria-expanded', 'false');
            const lista = document.createElement('ul');
            lista.className = 'siarhe-resultados'; lista.id = 'siarhe-resultados-' + Math.random().toString(36).slice(2, 7);
            lista.setAttribute('role', 'listbox'); lista.hidden = true;
            entrada.setAttribute('aria-controls', lista.id);
            host.appendChild(entrada); host.appendChild(lista);

            // Nombre de cada geometria: el de la base cargada (si trae el lugar) o el de las propiedades de la capa
            const nombreBase = function(f) {
                const p = f.properties || {};
                const row = state.dataMap ? state.dataMap.get(app.utils.getGeoKey(p, state.isNacional)) : null;
                return (row && row.estado) || p.NOMGEO || p.NOM_MUN || p.NOM_ENT || '';
            };
            const busca = function(q) {
                const nq = norm(q);
                const grupos = [];
                if (nq.length < 2) return grupos;
                const toma = function(titulo, features, nombreDe, detalleDe, esDetalle) {
                    const items = [];
                    for (let i = 0; i < features.length && items.length < MAX_POR_GRUPO; i++) {
                        const f = features[i], n = nombreDe(f);
                        if (n && norm(n).indexOf(nq) !== -1) items.push({ f: f, texto: n, detalle: detalleDe(f), detalleCapa: esDetalle });
                    }
                    if (items.length) grupos.push({ titulo: titulo, items: items });
                };
                const base = (state.geoData && state.geoData.features) || [];
                if (!state.isGeoLocMode) toma(state.isNacional ? 'Entidades' : 'Municipios', base, nombreBase, function() { return ''; }, false);
                const det = state.isGeoLocMode && state.locGeoData && Array.isArray(state.locGeoData.features) ? state.locGeoData.features : null;
                if (det) {
                    const esLoc = function(f) { return !!(f.properties && (f.properties.NOM_LOC || f.properties.LOCALIDAD)); };
                    const nombreDet = function(f) { const p = f.properties || {}; return p.NOM_LOC || p.LOCALIDAD || p.NOM_MUN || p.MUNICIPIO || p.NOMGEO || ''; };
                    const detalleDet = function(f) { const p = f.properties || {}; return esLoc(f) ? [p.NOM_MUN || p.MUNICIPIO, p.NOM_ENT].filter(Boolean).join(', ') : (p.NOM_ENT || ''); };
                    const locs = det.filter(esLoc), muns = det.filter(function(f) { return !esLoc(f); });
                    if (muns.length) toma('Municipios', muns, nombreDet, detalleDet, true);
                    if (locs.length) toma('Localidades', locs, nombreDet, detalleDet, true);
                }
                return grupos;
            };

            let items = [], activo = -1;
            const cierra = function() { lista.hidden = true; entrada.setAttribute('aria-expanded', 'false'); activo = -1; };
            const marca = function() { lista.querySelectorAll('.siarhe-res-item').forEach(function(li, i) { li.classList.toggle('activo', i === activo); }); };
            const esc = function(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
            const dibuja = function() {
                const grupos = busca(entrada.value);
                items = grupos.reduce(function(a, g) { return a.concat(g.items); }, []);
                let n = 0;
                lista.innerHTML = grupos.map(function(g) {
                    return '<li class="siarhe-res-grupo" role="presentation">' + g.titulo + '</li>' + g.items.map(function(it) {
                        return '<li class="siarhe-res-item" role="option" data-i="' + (n++) + '">' + esc(it.texto) + (it.detalle ? '<small>' + esc(it.detalle) + '</small>' : '') + '</li>';
                    }).join('');
                }).join('') + (!items.length && norm(entrada.value).length >= 2 ? '<li class="siarhe-res-vacio" role="presentation">Sin resultados</li>' : '');
                lista.hidden = !lista.innerHTML;
                entrada.setAttribute('aria-expanded', String(!lista.hidden));
                activo = -1;
            };
            const elige = function(it) {
                cierra();
                entrada.value = it.texto;
                entrada.blur();
                app.map.focusFeature(state, it.f, it.detalleCapa);
                const mapDiv = container.querySelector('.siarhe-map-container');
                if (mapDiv && !document.fullscreenElement) {
                    const r = mapDiv.getBoundingClientRect();
                    if (r.top < 0 || r.bottom > window.innerHeight) mapDiv.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            };
            entrada.addEventListener('input', dibuja);
            entrada.addEventListener('focus', function() { if (norm(entrada.value).length >= 2) dibuja(); });
            entrada.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { cierra(); return; }
                if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && items.length) {
                    e.preventDefault();
                    activo = (activo + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                    marca();
                    const li = lista.querySelector('.siarhe-res-item.activo');
                    if (li && li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Enter' && items.length) {
                    e.preventDefault();
                    elige(items[activo >= 0 ? activo : 0]);
                }
            });
            lista.addEventListener('mousedown', function(e) { e.preventDefault(); });
            lista.addEventListener('click', function(e) {
                const li = e.target.closest('.siarhe-res-item');
                if (li) elige(items[Number(li.dataset.i)]);
            });
            document.addEventListener('pointerdown', function(e) { if (!host.contains(e.target)) cierra(); });
        },

        /**
         * Conmutador de dos opciones (como el de RHEMX): rotulo, pista con perilla y rotulo. Se puede pulsar cualquiera de los tres.
         * def = { etiqueta, a: {valor, texto, titulo}, b: {...}, alCambiar(valor) }. Devuelve { el, set(valor), get(), deshabilita(a, b) };
         * set() solo pinta (no llama a alCambiar), para sincronizarlo desde fuera.
         */
        makeSwitch: function(def) {
            const el = document.createElement('span');
            el.className = 'siarhe-conm'; el.setAttribute('role', 'group'); el.setAttribute('aria-label', def.etiqueta);
            const op = function(o) {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'siarhe-conm-op'; b.textContent = o.texto; b.title = o.titulo || '';
                return b;
            };
            const opA = op(def.a), opB = op(def.b);
            const pista = document.createElement('button');
            pista.type = 'button'; pista.className = 'siarhe-conm-pista'; pista.setAttribute('role', 'switch');
            pista.setAttribute('aria-label', 'Cambiar entre ' + def.a.texto.toLowerCase() + ' y ' + def.b.texto.toLowerCase());
            pista.innerHTML = '<span class="siarhe-conm-perilla"></span>';
            el.appendChild(opA); el.appendChild(pista); el.appendChild(opB);
            let valor = def.a.valor;
            const dis = { a: false, b: false };
            const pinta = function() {
                const enB = valor === def.b.valor;
                opA.classList.toggle('is-active', !enB); opB.classList.toggle('is-active', enB);
                opA.setAttribute('aria-pressed', enB ? 'false' : 'true'); opB.setAttribute('aria-pressed', enB ? 'true' : 'false');
                pista.setAttribute('aria-checked', enB ? 'true' : 'false');
                opA.disabled = dis.a; opB.disabled = dis.b;
                pista.disabled = dis.a || dis.b;
            };
            const elige = function(v) {
                if (v === valor || (v === def.a.valor && dis.a) || (v === def.b.valor && dis.b)) return;
                valor = v; pinta();
                if (def.alCambiar) def.alCambiar(v);
            };
            opA.addEventListener('click', function() { elige(def.a.valor); });
            opB.addEventListener('click', function() { elige(def.b.valor); });
            pista.addEventListener('click', function() { elige(valor === def.a.valor ? def.b.valor : def.a.valor); });
            pinta();
            return {
                el: el,
                get: function() { return valor; },
                set: function(v) { valor = v === def.b.valor ? def.b.valor : def.a.valor; pinta(); },
                deshabilita: function(a, b) { dis.a = !!a; dis.b = !!b; pinta(); }
            };
        },

        /**
         * Conmutador Medida (Tasa / Frecuencia): alterna el indicador entre su tasa por mil habitantes y su frecuencia (numero de
         * plazas o personas). Cada opcion se desactiva si el indicador no tiene esa medida.
         */
        buildMeasureSwitch: function(container, state, wrapper) {
            const host = wrapper.querySelector('#c-medida');
            if (!host) return;
            const info0 = state.metricas[state.currentMetric] || {};
            state.medida = info0.tipo === 'tasa' || !info0.tipo ? 'tasa' : 'absoluto';
            const sw = app.controls.makeSwitch({
                etiqueta: 'Medida del indicador',
                a: { valor: 'tasa', texto: 'Tasa', titulo: 'Tasa por mil habitantes' },
                b: { valor: 'absoluto', texto: 'Frecuencia', titulo: 'Número de plazas o de personas' },
                alCambiar: function(v) {
                    state.medida = v;
                    const destino = app.controls.metricFamily(state, state.currentMetric)[v];
                    if (destino && destino !== state.currentMetric) {
                        const opt = container.querySelector('.siarhe-cs-option[data-key="' + destino + '"]');
                        if (opt) opt.click(); // mismo camino que elegirlo en la lista: escala, tabla, mapa y aviso de marcadores
                    }
                    app.controls.syncIndicatorOptions(container, state);
                    state.refreshMeasure();
                }
            });
            host.appendChild(sw.el);
            state.refreshMeasure = function() {
                const fam = app.controls.metricFamily(state, state.currentMetric);
                sw.deshabilita(!fam.tasa, !fam.absoluto);
                sw.set((state.metricas[state.currentMetric] || {}).tipo === 'tasa' ? 'tasa' : 'absoluto');
            };
            state.refreshMeasure();
        },

        /**
         * Selector de tema (data-temas): agrupa los indicadores como en RHEMX. Muestra solo los temas que tienen algun indicador
         * disponible en la base cargada y se oculta si queda uno solo. Al cambiar de tema, el indicador pasa al primero del tema.
         */
        buildThemeSelector: function(container, state, wrapper) {
            let temas = {};
            try { temas = JSON.parse(container.dataset.temas || '{}') || {}; } catch (e) { temas = {}; }
            const host = wrapper.querySelector('#c-tema');
            if (!host) return;
            const disponibles = function() {
                return Object.keys(temas).filter(function(t) {
                    return Object.keys(state.metricas).some(function(k) {
                        const m = state.metricas[k];
                        return (m.tema || 'general') === t && !(m.opcional && state.columnas && !state.columnas.has(k));
                    });
                });
            };
            const sel = document.createElement('select');
            sel.className = 'siarhe-metric-select'; sel.id = 'siarhe-tema-select';
            host.appendChild(sel);
            const llena = function() {
                const lista = disponibles();
                sel.innerHTML = '';
                lista.forEach(function(t) { const o = document.createElement('option'); o.value = t; o.textContent = temas[t].label; sel.appendChild(o); });
                const actual = (state.metricas[state.currentMetric] || {}).tema || 'general';
                state.temaActual = lista.indexOf(actual) !== -1 ? actual : (lista[0] || null);
                sel.value = state.temaActual;
                host.hidden = lista.length < 2;
            };
            llena();
            state.refreshThemes = function() { llena(); app.controls.syncIndicatorOptions(container, state, true); };
            sel.addEventListener('change', function() {
                state.temaActual = sel.value;
                app.controls.syncIndicatorOptions(container, state, true);
            });
        },

        /**
         * Selectores de año y unidad (plazas / recursos humanos) de las bases estáticas cargadas para la entidad
         * (atributo data-bases). Al cambiar, se descarga esa base y se redibuja todo con onUpdate. Solo aparecen si hay
         * más de una opción.
         */
        buildBaseSelectors: function(container, state, wrapper, onUpdate) {
            let bases = {};
            try { bases = JSON.parse(container.dataset.bases || '{}') || {}; } catch (e) { bases = {}; }
            const anios = Object.keys(bases).sort(function(a, b) { return b - a; });
            if (!anios.length) return;
            state.base = { bases: bases, anio: container.dataset.anio || anios[0], unidad: container.dataset.unidad || 'plazas' };
            if (!bases[state.base.anio]) state.base.anio = anios[0];

            const unidadesDe = function(anio) { return Object.keys(bases[anio] || {}); };
            const filaVista = wrapper.querySelector('.siarhe-controls-row[data-fila="vista"]');
            const filaMedida = wrapper.querySelector('.siarhe-controls-row[data-fila="medida"]');
            if (!filaVista || !filaMedida) return;

            const grupo = function(id, titulo) {
                const g = document.createElement('div');
                g.className = 'siarhe-control-group'; g.id = id;
                const l = document.createElement('label'); l.textContent = titulo; g.appendChild(l);
                return g;
            };
            const gAnio = grupo('c-anio', 'Año de los datos');
            const selAnio = document.createElement('select'); selAnio.className = 'siarhe-metric-select'; selAnio.id = 'siarhe-anio-select';
            anios.forEach(function(a) { const o = document.createElement('option'); o.value = a; o.textContent = a; selAnio.appendChild(o); });
            gAnio.appendChild(selAnio);

            // Unidad de conteo: conmutador Plazas / Recursos humanos; la opcion sin base cargada para el año queda desactivada
            const gUnidad = document.createElement('div');
            gUnidad.className = 'siarhe-control-group siarhe-control-group--conm'; gUnidad.id = 'c-unidad';
            const swUnidad = app.controls.makeSwitch({
                etiqueta: 'Unidad de conteo',
                a: { valor: 'plazas', texto: 'Plazas', titulo: 'Contar plazas de enfermería' },
                b: { valor: 'personas', texto: 'Recursos humanos', titulo: 'Contar recursos humanos estimados (personas)' },
                alCambiar: function(v) { state.base.unidad = v; cambia(); }
            });
            gUnidad.appendChild(swUnidad.el);

            const llenaUnidades = function() {
                const u = unidadesDe(state.base.anio);
                if (u.indexOf(state.base.unidad) === -1) state.base.unidad = u[0];
                swUnidad.deshabilita(u.indexOf('plazas') === -1, u.indexOf('personas') === -1);
                swUnidad.set(state.base.unidad);
            };
            selAnio.value = state.base.anio;
            llenaUnidades();
            gAnio.hidden = anios.length < 2;

            const cambia = function() {
                const info = (bases[state.base.anio] || {})[state.base.unidad];
                if (!info) return;
                const loading = container.querySelector('.siarhe-loading-overlay');
                if (loading) loading.style.display = 'flex';

                // El año puede pertenecer a otro marco geoestadístico: su mapa base se descarga junto con los datos
                const marco = app.map ? app.geo.marcoFor(state.base.anio, state.geos && state.geos.base) : null;
                const cambiaMarco = !!marco && marco !== state.marco && !!state.geos.base[marco];
                const geoNuevo = cambiaMarco ? app.geo.load(state.geos.base[marco].url) : Promise.resolve(null);

                Promise.all([app.data.loadBase(info.url), geoNuevo]).then(function(res) {
                    app.data.setBase(container, state, res[0], state.base.anio);
                    if (cambiaMarco && res[1]) app.controls.applyMarco(container, state, marco, res[1]);
                    else app.controls.syncDetalle(container, state);
                    if (state.refreshThemes) state.refreshThemes(); else app.controls.syncIndicatorOptions(container, state, true);
                    if (loading) loading.style.display = 'none';
                    onUpdate();
                    app.controls.refreshMarkersForYear(container, state);
                }).catch(function(err) {
                    console.error('[SIARHE Controls] No se pudo cargar la base', err);
                    if (loading) loading.style.display = 'none';
                });
            };
            selAnio.addEventListener('change', function() { state.base.anio = selAnio.value; llenaUnidades(); cambia(); });

            filaMedida.insertBefore(gUnidad, filaMedida.children[1] || null);
            filaVista.insertBefore(gAnio, filaVista.children[1] || null);
        },

        /**
         * Capa detallada (municipios o localidades) que corresponde al año que se ve. Si cambia de archivo, la que estaba
         * cargada se descarta y el mapa vuelve a la vista base; la opción del selector se desactiva cuando no hay capa.
         */
        syncDetalle: function(container, state) {
            const url = app.geo.locUrl(state, state.base ? state.base.anio : (container.dataset.anio || ''));
            const selVista = container.querySelector('#siarhe-vista-select');
            if (url !== state.geoLocUrl) {
                state.geoLocUrl = url;
                state.locGeoData = null;
                state._locBuilt = null;
                if (state.isGeoLocMode) {
                    state.isGeoLocMode = false;
                    if (selVista) selVista.value = 'base';
                    if (app.map) app.map.updateGeography(container, state);
                }
            }
            const optDetalle = selVista ? selVista.querySelector('option[value="detalle"]') : null;
            if (optDetalle) optDetalle.disabled = !state.geoLocUrl;
        },

        /**
         * Pone en uso otro marco geoestadístico: cambia los polígonos del mapa base (la capa detallada del marco anterior
         * se descarta y el mapa vuelve a la vista base), ajusta la vista detallada disponible y la fuente del pie.
         */
        applyMarco: function(container, state, marco, geo) {
            const selVista = container.querySelector('#siarhe-vista-select');
            state.isGeoLocMode = false;
            if (selVista) selVista.value = 'base';

            state.marco = marco;
            state.geoLocUrl = null; // obliga a syncDetalle a tomar la capa del marco nuevo
            if (app.map) app.map.swapGeography(container, state, geo);
            app.controls.syncDetalle(container, state);
            app.geo.updateFooter(container, state);
        },

        setupActionButtons: function(container, state) {
            const btnPng = container.querySelector('.siarhe-btn-download-png');

            const btnShare = container.querySelector('.siarhe-btn-share');
            if (btnShare) {
                const txt = btnShare.querySelector('.siarhe-btn-texto') || btnShare;
                const original = txt.textContent;
                let temporizador = null;
                const aviso = function(t) {
                    txt.textContent = t;
                    clearTimeout(temporizador);
                    temporizador = setTimeout(function() { txt.textContent = original; }, 2000);
                };
                btnShare.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if (app.urlState) app.urlState.flush(container, state); // la URL debe reflejar el estado actual
                    const url = window.location.href;
                    try {
                        await navigator.clipboard.writeText(url);
                        aviso('¡Enlace copiado!');
                    } catch (err) {
                        window.prompt('Copia este enlace:', url);
                    }
                });
            }

            const btnTabla = container.querySelector('.siarhe-btn-ver-tabla');
            if (btnTabla) {
                btnTabla.addEventListener('click', (e) => {
                    e.preventDefault();
                    const sec = container.querySelector('#siarhe-table-section');
                    if (!sec) return;
                    const det = sec.querySelector('details.siarhe-table-details');
                    if (det) det.open = true;
                    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    sec.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
                });
            }

            // Un solo botón: la imagen sale como se ve el mapa, con nombres si «Ver etiquetas» está encendido
            if (btnPng) {
                btnPng.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (app.map) app.map.downloadMapAsPNG(container, state, !!state.showLabels);
                });
            }
        },

        setupExcelExport: function(container, state) {
            const btnExcel = container.querySelector('.siarhe-btn-download-excel');
            if (!btnExcel) return;

            btnExcel.addEventListener('click', (e) => {
                e.preventDefault();
                let csvContent = '\uFEFF';
                const colEntidad = state.isNacional ? 'Entidad Federativa' : 'Municipio';
                csvContent += `${colEntidad},Población,Enfermeras,Tasa\n`;

                const metricInfo = state.metricas[state.currentMetric];
                const mKey = state.currentMetric;

                // 🌟 CORRECCIÓN ARQUITECTÓNICA AL EXPORTAR
                // Extraemos el gafete (pairGroup) y buscamos los nombres REALES de las columnas
                const pairGroup = metricInfo.pair || mKey;
                const isPob = (mKey === 'poblacion');

                const enfDataKey = isPob ? null : app.utils.findAbsoluteKeyForPair(pairGroup, state.metricas);
                const tasaDataKey = isPob ? null : ((metricInfo.tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pairGroup, state.metricas));

                const getVal = (r, colId) => {
                    if (colId === 'estado') return r.estado;
                    if (colId === 'poblacion') return r.poblacion;
                    if (colId === 'enfermeras') return isPob ? null : r[enfDataKey];
                    if (colId === 'tasa') return isPob ? null : r[tasaDataKey];
                    return null;
                };

                const rows = state.csvData.filter(r => !r.isTotal && !r.isSpecial).sort((a,b) => {
                    const va = getVal(a, app.sortConfig.key);
                    const vb = getVal(b, app.sortConfig.key);
                    if (va === null && vb !== null) return 1;
                    if (vb === null && va !== null) return -1;
                    if (va === null && vb === null) return 0;
                    if (va < vb) return app.sortConfig.direction === 'asc' ? -1 : 1;
                    if (va > vb) return app.sortConfig.direction === 'asc' ? 1 : -1;
                    return 0;
                });

                const fixed = state.csvData.filter(r => (r.isTotal || r.isSpecial) && !r.isExtranjero);

                [...rows, ...fixed].forEach(r => {
                    const estado = `"${r.estado}"`;
                    const pob = r.poblacion || 0;

                    let enfStr = '-';
                    if (!isPob && enfDataKey && r[enfDataKey] !== null && r[enfDataKey] !== undefined) {
                        enfStr = r[enfDataKey];
                    }

                    let tasaStr = '-';
                    if (!isPob && tasaDataKey && r[tasaDataKey] !== null && r[tasaDataKey] !== undefined) {
                        tasaStr = r[tasaDataKey].toFixed(2);
                    }

                    csvContent += `${estado},${pob},${enfStr},${tasaStr}\n`;
                });

                const anioNode = document.querySelector('.siarhe-dynamic-year');
                const anio = anioNode ? anioNode.innerText : new Date().getFullYear();

                const titleNode = container.querySelector('h2.siarhe-title');
                const entidadNombreRaw = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "Mexico";
                const entidadClean = entidadNombreRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '_');

                const labelRaw = metricInfo.label || metricInfo.fullLabel || 'Datos';
                const labelClean = labelRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\./g, "").replace(/\s+/g, '_');

                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");

                a.download = `${labelClean}_${entidadClean}_${anio}.csv`;
                a.href = url;
                a.style.display = "none";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        }
    };

})(window.SiarheDataViz);