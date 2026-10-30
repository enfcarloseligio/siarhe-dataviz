/* /public/js/siarhe-core.js */
/**
 * SIARHE DataViz - Módulo Principal (Core)
 * ------------------------------------------------------------------
 * Gestiona la inicialización de la visualización, la definición del
 * Estado Global (State), las utilidades compartidas, la descarga
 * de datos crudos (CSV/GeoJSON) y el cálculo de métricas en memoria.
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    // ==========================================
    // 1. CONSTANTES GLOBALES Y ESTILOS BASE
    // ==========================================
    app.constants = {
        // 🌟 MARCADOR_NOMBRES ESTABA QUEMADO AQUÍ. AHORA SE CARGA DINÁMICAMENTE DESDE LA BASE DE DATOS.
    };

    // Extracción de variables CSS configuradas en el backend
    const styles = getComputedStyle(document.documentElement);
    const getVar = (n, f) => styles.getPropertyValue(n).trim() || f;

    app.colors = {
        RANGE: [ getVar('--s-map-c1', '#eff3ff'), getVar('--s-map-c2', '#bdd7e7'), getVar('--s-map-c3', '#9ecae1'), getVar('--s-map-c4', '#6baed6'), getVar('--s-map-c5', '#08519c') ],
        // ☀️ NUEVOS COLORES GLOBALES PARA RANGOS PERSONALIZADOS AÑADIDOS
        CUSTOM_RANGE: [ getVar('--s-map-range-1', '#ffedd5'), getVar('--s-map-range-2', '#fdba74'), getVar('--s-map-range-3', '#f59e0b'), getVar('--s-map-range-4', '#b45309') ],
        MONO: [ getVar('--s-map-mono-min', '#f0f9ff'), getVar('--s-map-mono-max', '#0369a1') ], 
        ZERO: getVar('--s-map-zero', '#d9d9d9'),
        NULL: getVar('--s-map-null', '#000000')
    };

    // Objeto de ordenamiento compartido (usado por la tabla)
    app.sortConfig = { key: 'tasa', direction: 'desc' };

    // ==========================================
    // 2. UTILIDADES DE PROCESAMIENTO (Parsers)
    // ==========================================
    app.utils = {
        /** Duracion de animacion respetando 'prefers-reduced-motion' (0 si el usuario lo pide). */
        motionMs: function(ms) {
            try {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : ms;
            } catch (e) { return ms; }
        },

        /**
         * Cede el hilo principal hasta el siguiente cuadro (con respaldo por timer si la pestana
         * esta en segundo plano). Permite trocear trabajos largos sin congelar la pagina.
         */
        nextFrame: function() {
            return new Promise(function(resolve) {
                let done = false;
                const go = function() { if (!done) { done = true; resolve(); } };
                requestAnimationFrame(go);
                setTimeout(go, 50);
            });
        },

        /** Escapa texto de datos (CSV/GeoJSON/config) antes de insertarlo en innerHTML. */
        esc: function(val) {
            return (val === undefined || val === null) ? '' : String(val).replace(/[&<>"']/g, function(c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        },

        cleanKey: function(val) { 
            return (val === undefined || val === null) ? "" : val.toString().trim(); 
        },
        
        getGeoKey: function(props, isNacional) { 
            if (!isNacional) {
                return app.utils.cleanKey(props.CVE_MUN || props.cve_mun || props.ID || props.id);
            }
            return app.utils.cleanKey(props.CVE_ENT || props.cve_ent || props.ID || props.id); 
        },
        
        /**
         * Parsea valores numéricos. Asigna null de forma estricta a celdas vacías o con "S/D"
         * para garantizar que el mapa y tabla no asuman ceros por error en datos inexistentes.
         */
        parseNum: function(val) {
            if (val === undefined || val === null || val.toString().trim() === "" || val.toString().trim().toUpperCase() === "S/D") {
                return null;
            }
            const n = parseFloat(val.toString().replace(/,/g, '').replace(/\s/g, ''));
            return isNaN(n) ? null : n;
        },
        
        getD3Shape: function(shapeName) {
            if (typeof d3 === 'undefined') return null;
            const shapes = { 'circle': d3.symbolCircle, 'square': d3.symbolSquare, 'triangle': d3.symbolTriangle, 'diamond': d3.symbolDiamond, 'star': d3.symbolStar, 'cross': d3.symbolCross };
            return shapes[shapeName] || d3.symbolCircle;
        },
        
        getMarkerStyle: function(type, state) {
            if (state.markerStyles[type]) return state.markerStyles[type];
            return { shape: 'circle', fill: '#000000', fill2: '', stroke: '#ffffff' }; 
        },
        
        // Tipos de marcador que codifican un valor numerico (burbuja o pico)
        isDensity: function(tipo) {
            return tipo === 'espectro' || tipo === 'spike';
        },

        // Grupo del selector de controles: los picos comparten el selector "Densidad" con los espectros
        markerGroup: function(tipo) {
            return tipo === 'spike' ? 'espectro' : tipo;
        },

        getColValue: function(row, possibleNames) {
            const keys = Object.keys(row);
            for (let name of possibleNames) {
                const target = name.toLowerCase();
                const foundKey = keys.find(k => k.trim().toLowerCase() === target);
                if (foundKey) return row[foundKey].trim();
            }
            return "";
        },

        // 🌟 CORRECCIÓN ARQUITECTÓNICA: Helper Inteligente para Tasas
        // Busca en el diccionario qué columna CSV REAL tiene tipo="tasa" y pertenece al gafete(pairGroup)
        findRateKeyForAbsolute: function(pairGroup, metricasConfig) {
            const foundRate = Object.keys(metricasConfig).find(k => 
                (metricasConfig[k].pair || k) === pairGroup && metricasConfig[k].tipo === 'tasa'
            );
            return foundRate || null;
        },

        // 🌟 NUEVO HELPER INTELIGENTE: Busca la columna absoluta real basada en el gafete
        findAbsoluteKeyForPair: function(pairGroup, metricasConfig) {
            const foundAbs = Object.keys(metricasConfig).find(k => 
                (metricasConfig[k].pair || k) === pairGroup && metricasConfig[k].tipo === 'absoluto'
            );
            return foundAbs || null;
        }
    };

    // ==========================================
    // ☀️ 3. MOTOR MATEMÁTICO DE RANGOS
    // ==========================================
    app.math = {
        /**
         * Resuelve una vez los limites de un rango (Vmin, Q1, Q2... o numeros) y devuelve
         * un predicado reutilizable, para no recalcularlos por cada municipio.
         */
        buildRange: function(minRaw, op, maxRaw, statsDict) {
            const lower = {};
            Object.keys(statsDict).forEach(k => { lower[k.toLowerCase()] = statsDict[k]; });
            const parseVal = (raw) => {
                if (raw === undefined || raw === null || raw === '') return NaN;
                const v = raw.toString().trim();
                const hit = lower[v.toLowerCase()];
                return hit !== undefined ? hit : parseFloat(v);
            };

            const minVal = parseVal(minRaw);
            const maxVal = parseVal(maxRaw);
            if (isNaN(minVal) || isNaN(maxVal)) return function() { return false; };

            const operator = (op || '<').toString().trim();
            // MATEMATICA ESTRICTA (respeta lo que ponga el usuario o el sistema)
            if (operator === '<=') return function(v) { return v >= minVal && v <= maxVal; };
            if (operator === '=')  return function(v) { return v >= minVal && v === maxVal; };
            return function(v) { return v >= minVal && v < maxVal; };
        },

        isInRange: function(valToEval, minRaw, op, maxRaw, statsDict) {
            return app.math.buildRange(minRaw, op, maxRaw, statsDict)(valToEval);
        },

        createRangeEvaluator: function(metricConfig, statsDict) {
            const useColors = metricConfig.custom_colors === true;
            const useRanges = metricConfig.custom_ranges === true;
            const color = (n) => (useColors && metricConfig['r' + n + '_color']) ? metricConfig['r' + n + '_color'] : app.colors.CUSTOM_RANGE[n - 1];
            const pick = (n, field, def) => (useRanges && metricConfig['r' + n + '_' + field]) ? metricConfig['r' + n + '_' + field] : def;

            // Rangos de mayor a menor, resueltos una sola vez
            const defs = [
                [4, 'Q3',   '<=', 'Vmax'],
                [3, 'Q2',   '<',  'Q3'],
                [2, 'Q1',   '<',  'Q2'],
                [1, 'Vmin', '<',  'Q1']
            ];
            const ranges = defs.map(d => ({
                color: color(d[0]),
                test: app.math.buildRange(pick(d[0], 'min', d[1]), pick(d[0], 'op', d[2]), pick(d[0], 'max', d[3]), statsDict)
            }));

            return function(value) {
                if (value === null || value === undefined || isNaN(value)) return app.colors.NULL;
                if (value === 0) return app.colors.ZERO;
                for (let i = 0; i < ranges.length; i++) {
                    if (ranges[i].test(value)) return ranges[i].color;
                }
                return app.colors.NULL;
            };
        }
    };


    /**
     * Garantiza que D3 este disponible. Se usa primero el del CDN (ya cargado como dependencia);
     * si fallo, no cargo o trae una version sin las funciones que usa el plugin, se carga la copia local.
     * callback() cuando D3 sirve; onFail() si tampoco funciona el respaldo.
     */
    app.ensureD3 = function(callback, onFail) {
        const REQUIRED = ['select', 'geoMercator', 'geoPath', 'geoArea', 'geoCentroid', 'zoom', 'zoomIdentity',
                          'scaleLinear', 'symbol', 'csv', 'json', 'pointer', 'quantile', 'interpolate'];
        const usable = function() {
            return typeof window.d3 !== 'undefined' && REQUIRED.every(function(k) { return window.d3[k] !== undefined; });
        };

        if (usable()) { callback(); return; }

        const url = (window.SiarheDataVizConfig || {}).d3Fallback;
        if (!url) { if (onFail) onFail(); return; }

        if (!app._d3Fallback) {
            app._d3Fallback = new Promise(function(resolve, reject) {
                const s = document.createElement('script');
                s.src = url;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }
        console.warn('[SIARHE] D3 del CDN no disponible o incompleto; usando la copia local.');
        app._d3Fallback.then(function() {
            if (usable()) callback(); else if (onFail) onFail();
        }).catch(function() { if (onFail) onFail(); });
    };

    // ==========================================
    // 3b. ESTADO COMPARTIBLE POR URL (hash)
    // ==========================================
    // Formato: #siarhe-<CVE_ENT>=<querystring codificada>  (varias instancias se separan con ';')
    //   m = metrica, e = escala de color, v = vista (detalle), k = marcadores activos (separados por coma)
    // Solo se escribe lo que difiere del estado inicial, asi la URL limpia sigue limpia.
    app.urlState = {
        _timer: null,

        _key: function(container) { return 'siarhe-' + (container.dataset.cveEnt || ''); },

        _norm: function(mode) {
            if (mode === 'degradado' || mode === 'quartiles') return 'cuartiles';
            if (mode === 'mono') return 'monocromatico';
            return mode || 'cuartiles';
        },

        _parse: function(container) {
            const raw = (window.location.hash || '').replace(/^#/, '');
            if (!raw) return null;
            const key = app.urlState._key(container) + '=';
            const part = raw.split(';').find(function(p) { return p.indexOf(key) === 0; });
            if (!part) return null;
            try { return new URLSearchParams(decodeURIComponent(part.slice(key.length))); } catch (e) { return null; }
        },

        /** Se llama ANTES de renderizar: fija metrica y escala, y deja pendiente vista y marcadores. */
        apply: function(container, state) {
            state._urlDefaults = { metric: state.currentMetric };
            state._urlPending = null;

            const qs = app.urlState._parse(container);
            if (!qs) return;

            const m = qs.get('m');
            if (m && state.metricas && state.metricas[m]) state.currentMetric = m;

            const e = qs.get('e');
            if (e && ['cuartiles', 'monocromatico', 'rangos'].indexOf(e) !== -1) state.colorMode = e;

            state._urlPending = {
                view: qs.get('v') === 'detalle' ? 'detalle' : 'base',
                markers: (qs.get('k') || '').split(',').filter(Boolean)
            };
        },

        /** Se llama DESPUES de renderizar los controles: activa la vista detalle y los marcadores. */
        applyDeferred: function(container, state) {
            const p = state._urlPending;
            if (!p) return;
            state._urlPending = null;

            if (p.view === 'detalle') {
                const sel = container.querySelector('#siarhe-vista-select');
                if (sel && sel.querySelector('option[value="detalle"]')) {
                    sel.value = 'detalle';
                    sel.dispatchEvent(new Event('change'));
                }
            }

            if (p.markers.length > 0 && app.controls) {
                (async function() {
                    for (const type of p.markers) {
                        if (state.markerUrls && state.markerUrls[type] && !state.activeMarkers.has(type)) {
                            await app.controls.toggleMarker(type, state, container);
                            const chk = container.querySelector('.mc-check[value="' + CSS.escape(type) + '"]');
                            if (chk) chk.checked = true;
                        }
                    }
                })();
            }
        },

        write: function(container, state) {
            clearTimeout(app.urlState._timer);
            app.urlState._timer = setTimeout(function() { app.urlState.flush(container, state); }, 200);
        },

        flush: function(container, state) {
            if (!state.metricas) return;
            const d = state._urlDefaults || {};
            const qs = new URLSearchParams();

            if (state.currentMetric && state.currentMetric !== d.metric) qs.set('m', state.currentMetric);

            const defMode = app.urlState._norm((state.metricas[state.currentMetric] || {}).scale_type);
            if (state.colorMode && app.urlState._norm(state.colorMode) !== defMode) qs.set('e', app.urlState._norm(state.colorMode));

            if (state.isGeoLocMode) qs.set('v', 'detalle');
            if (state.activeMarkers && state.activeMarkers.size > 0) qs.set('k', Array.from(state.activeMarkers).join(','));

            const raw = (window.location.hash || '').replace(/^#/, '');
            // Si la pagina usa el hash para otra cosa (ancla propia), no lo tocamos
            if (raw && !raw.split(';').every(function(p) { return p.indexOf('siarhe-') === 0; })) return;

            const key = app.urlState._key(container);
            const parts = raw ? raw.split(';').filter(function(p) { return p && p.indexOf(key + '=') !== 0; }) : [];
            const str = qs.toString();
            if (str) parts.push(key + '=' + encodeURIComponent(str));

            const newHash = parts.length ? '#' + parts.join(';') : '';
            if (newHash === window.location.hash || (!newHash && !window.location.hash)) return;
            history.replaceState(null, '', window.location.pathname + window.location.search + newHash);
        }
    };

    // ==========================================
    // 4. CAPA DE DATOS (Data Layer)
    // ==========================================
    app.data = {
        processCSV: function(data, metricasConfig) {
            return data.map(d => {
                const row = {};
                row.CVE_ENT = app.utils.cleanKey(d.CVE_ENT || d.cve_ent || d.mapa || d.id);
                row.CVE_MUN = app.utils.cleanKey(d.CVE_MUN || d.cve_mun); 
                
                row.id_legacy = (d.id || "").toString().trim();
                row.estado = (d.estado || d.Entidad || d.municipio || "").trim();
                
                const keyPob = Object.keys(d).find(k => k.toLowerCase().startsWith('pob'));
                row.poblacion = app.utils.parseNum(d[keyPob]);
                
                Object.keys(metricasConfig).forEach(k => { 
                    if (k !== 'poblacion') row[k] = app.utils.parseNum(d[k]); 
                });
                
                row.isTotal = (row.id_legacy === '9999' || row.CVE_ENT === '9999' || row.CVE_ENT === '33' || row.estado.toLowerCase().includes('total'));
                row.isSpecial = (row.id_legacy === '8888' || row.CVE_ENT === '8888' || row.CVE_ENT === '34' || row.CVE_ENT === '88' || row.CVE_MUN === '8888');
                
                return row;
            });
        },

        calcularSumaExactaEnfermeras: function(container, state) {
            const sumNode = container.querySelector('.siarhe-dynamic-nurses-sum');
            const countNodes = container.querySelectorAll('.siarhe-dynamic-mun-count'); 
            
            let sumaTotal = 0;
            let munCount = 0;

            state.csvData.forEach(row => {
                if (row.isTotal) return;

                if (state.isNacional) {
                    const cveNum = parseInt(row.CVE_ENT, 10);
                    const isEstado = (cveNum >= 1 && cveNum <= 32);
                    if (isEstado || row.isSpecial) {
                        sumaTotal += (row.enfermeras_total || 0);
                    }
                } else {
                    sumaTotal += (row.enfermeras_total || 0);
                    if (!row.isSpecial) munCount++;
                }
            });

            if (sumNode) sumNode.textContent = sumaTotal.toLocaleString('es-MX');
            if (!state.isNacional && countNodes.length > 0) {
                countNodes.forEach(node => { node.textContent = munCount; });
            }
        },

        calcularTotalHeader: function(container, state) {
            const headerDiv = container.querySelector('.siarhe-dynamic-total');
            if (!headerDiv) return;
            
            const info = state.metricas[state.currentMetric];
            if(!info) return;

            // 🌟 CORRECCIÓN ARQUITECTÓNICA:
            // Extraemos el gafete (pairGroup) y buscamos el nombre REAL de la columna absoluta
            const pairGroup = info.pair || state.currentMetric;
            const absCsvKey = app.utils.findAbsoluteKeyForPair(pairGroup, state.metricas);

            let totalAbs = 0, valorMuestra = null;
            
            const rowTotal = state.csvData.find(r => r.isTotal);
            if (rowTotal) { 
                totalAbs = absCsvKey ? (rowTotal[absCsvKey] || 0) : 0; 
                valorMuestra = rowTotal[state.currentMetric]; 
            } else {
                state.csvData.forEach(row => { 
                    // Sumamos usando absCsvKey (el nombre real de la columna), no el gafete
                    if (!row.isTotal && !row.isSpecial && absCsvKey && state.metricas[absCsvKey] && state.metricas[absCsvKey].tipo === 'absoluto') {
                        totalAbs += (row[absCsvKey] || 0); 
                    }
                });
                valorMuestra = (info.tipo === 'tasa') ? null : totalAbs;
            }
            
            const absFmt = totalAbs.toLocaleString('es-MX');
            const valFmt = (valorMuestra === null || valorMuestra === undefined) ? '—' : valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 });
            
            headerDiv.innerHTML = info.tipo === 'tasa' 
                ? `<span style="margin-right:15px;">${app.utils.esc(info.fullLabel)}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span><span>Enfermeras: <strong style="color:#0A66C2; font-size:1.2em;">${absFmt}</strong></span>`
                : `<span>${app.utils.esc(info.fullLabel)}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span>`;
        }
    };

    // ==========================================
    // 5. ORQUESTADOR DE INICIALIZACIÓN
    // ==========================================
    app.initVisualization = function(container) {
        const cveEnt = container.dataset.cveEnt;
        const geojsonUrl = container.dataset.geojson;
        const csvUrl = container.dataset.csv;
        const mode = (container.dataset.mode || '').trim();
        const isNacional = (cveEnt === '33' || container.dataset.slug === 'republica-mexicana');
        const loading = container.querySelector('.siarhe-loading-overlay');

        if (typeof d3 === 'undefined') { 
            console.error("[SIARHE Core] D3.js es requerido pero no está cargado."); 
            if(loading) loading.innerHTML = "❌ Error: D3.js no se ha cargado.";
            return; 
        }

        // Parseo seguro de Data Atributos
        const safeParseJSON = (data, fallback) => { try { return JSON.parse(data || '{}'); } catch(e) { return fallback; } };
        
        let MARCADOR_ESTILOS = safeParseJSON(container.dataset.markerConfig, {});
        let MARCADOR_URLS = safeParseJSON(container.dataset.markerUrls, {});
        let MARCADOR_LABELS = safeParseJSON(container.dataset.markerLabels, {});
        let ENTITY_URLS = safeParseJSON(container.dataset.entityUrls, {});
        let METRICAS_CONFIG = safeParseJSON(container.dataset.metricas, {});
        
        let TOOLTIP_CONFIG = safeParseJSON(container.dataset.tooltips, {
            geo_pob: true, geo_abs: true, geo_rate: true,
            geo_order: ['pob', 'abs', 'rate'],
            mk_inst: true, mk_mun: true, mk_clues: true,
            mk_tipo: true, mk_nivel: true, mk_juris: true
        });

        // Estado Global de la Instancia
        let state = {
            geoData: null, csvData: [], dataMap: new Map(),
            metricas: METRICAS_CONFIG,
            tooltipConfig: TOOLTIP_CONFIG, 
            currentMetric: 'tasa_total',
            colorMode: null,
            zoom: null, gLegend: null, gMarkerLegend: null, gradientId: null,
            svg: null, gMain: null, gPaths: null, gLabels: null, gMarkers: null, 
            activeMarkers: new Set(), markersData: {}, 
            markerStyles: MARCADOR_ESTILOS, markerUrls: MARCADOR_URLS,
            markerLabels: MARCADOR_LABELS, 
            entityUrls: ENTITY_URLS, homeUrl: container.dataset.homeUrl || '', 
            isNacional: isNacional, 
            tooltip: null, markerTrigger: null, lastClickTime: 0,
            rawCSVDataCache: {},
            initialTransform: d3.zoomIdentity 
        };

        if (!geojsonUrl && mode.includes('M')) return;

        // Descarga paralela de archivos (Solo trae lo necesario)
        Promise.all([
            geojsonUrl && mode.includes('M') ? d3.json(geojsonUrl) : Promise.resolve(null),
            csvUrl ? d3.csv(csvUrl) : Promise.resolve(null)
        ])
        .then(([geo, csv]) => {
            state.geoData = geo;

            // Metrica, escala, vista y marcadores iniciales pueden venir en el hash de la URL
            if (app.urlState) app.urlState.apply(container, state);
            
            if (csv) {
                state.csvData = app.data.processCSV(csv, state.metricas);
                state.csvData.forEach(row => { 
                    let targetKey = state.isNacional ? row.CVE_ENT : row.CVE_MUN;
                    if (!state.isNacional && !targetKey) targetKey = row.CVE_ENT; 
                    if (targetKey) state.dataMap.set(targetKey, row); 
                });
                app.data.calcularSumaExactaEnfermeras(container, state);
                app.data.calcularTotalHeader(container, state);
            }

            // Callback centralizado para actualizaciones en cadena (Selector Métrica)
            const onMetricUpdate = () => {
                if (app.map && mode.includes('M')) app.map.updateVisuals(container, state);
                app.data.calcularTotalHeader(container, state);
                if (app.table && mode.includes('T')) app.table.update(container, state);
                if (app.urlState) app.urlState.write(container, state);
            };

            // Delegación de renderizado a Módulos (si existen)
            if (mode.includes('M')) {
                if (app.map) app.map.render(container, state, cveEnt);
                if (csv && app.controls) {
                    app.controls.renderMain(container, state, onMetricUpdate, { targetSelector: '.siarhe-controls-placeholder', showMarkers: true });
                }
                if (app.controls) app.controls.setupActionButtons(container, state);
            } else if (mode === 'T' && csv) {
                if (app.controls) {
                    app.controls.renderMain(container, state, onMetricUpdate, { targetSelector: '.siarhe-table-controls-placeholder', showMarkers: false });
                }
            }
            
            if (mode.includes('T') && csv && app.table) {
                app.table.init(container, state);
                if (app.controls) app.controls.setupExcelExport(container, state);
            }

            // El overlay se oculta hasta que todo se haya construido (antes se ocultaba antes de renderizar)
            if (loading) loading.style.display = 'none';

            // Vista detalle y marcadores guardados en la URL (necesitan los controles ya renderizados)
            if (app.urlState) app.urlState.applyDeferred(container, state);
        })
        .catch(err => { 
            console.error("[SIARHE Core] Falla en la red o parseo:", err); 
            if(loading) loading.innerHTML = "❌ Error en la red o lectura de datos."; 
        });
    };

})(window.SiarheDataViz);

// ==========================================
// 5. INYECCIÓN AL CARGAR EL DOM
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log("%c 🚀 SIARHE Core V50: Multi-Pass Logic <= ", "background: #1e40af; color: #ffffff; padding: 2px 6px; border-radius: 4px;");
    
    const app = window.SiarheDataViz;
    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    app.ensureD3(
        function() { wrappers.forEach(container => { app.initVisualization(container); }); },
        function() {
            wrappers.forEach(container => {
                const loading = container.querySelector('.siarhe-loading-overlay');
                if (loading) loading.innerHTML = '❌ Error: no se pudo cargar la librería de gráficos (D3.js).';
            });
        }
    );
});