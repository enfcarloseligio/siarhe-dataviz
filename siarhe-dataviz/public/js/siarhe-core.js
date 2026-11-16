/* /public/js/siarhe-core.js */
/**
 * SIARHE DataViz - Módulo Principal (Core)
 * ------------------------------------------------------------------
 * Gestiona la inicialización de la visualización, la definición del
 * Estado Global (State), las utilidades compartidas, la descarga
 * de datos crudos (bases estáticas y geometrías TopoJSON / GeoJSON) y el
 * cálculo de métricas en memoria.
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
        RANGE: [ getVar('--s-map-c1', '#9b2247'), getVar('--s-map-c2', '#ffa500'), getVar('--s-map-c3', '#e6d194'), getVar('--s-map-c4', '#008000'), getVar('--s-map-c5', '#006400') ],
        // ☀️ NUEVOS COLORES GLOBALES PARA RANGOS PERSONALIZADOS AÑADIDOS
        CUSTOM_RANGE: [ getVar('--s-map-range-1', '#e6f4ea'), getVar('--s-map-range-2', '#9fd5b5'), getVar('--s-map-range-3', '#3faf7b'), getVar('--s-map-range-4', '#1e5b4f') ],
        MONO: [ getVar('--s-map-mono-min', '#e5e0d3'), getVar('--s-map-mono-max', '#a57100') ],
        ZERO: getVar('--s-map-zero', '#b5b4c3'),
        NULL: getVar('--s-map-null', '#000000')
    };

    /**
     * Paleta vigente de una instancia del mapa: la que el visitante personalizo (solo vive en su
     * navegador, state.customColors) o, si no hay, la configurada en el sitio (app.colors).
     * Con paleta personalizada pal.custom es true y sus colores tienen prioridad sobre los de cada metrica.
     */
    app.palette = function(state) {
        return (state && state.customColors) ? Object.assign({ custom: true }, app.colors, state.customColors) : app.colors;
    };

    // Objeto de ordenamiento compartido (usado por la tabla)
    app.sortConfig = { key: 'tasaTotal', direction: 'desc' };

    /**
     * Disposición de la página: «contenedor» (ancho de lectura) o «canvas» (ancho de la ventana). El sitio define la
     * de inicio (Configuración › General) y el visitante la cambia con el conmutador que va arriba de la primera
     * visualización; su elección se recuerda en el navegador (localStorage «siarheLayout») y vale para todas las
     * visualizaciones de la página.
     */
    app.layout = {
        CLAVE: 'siarheLayout',

        // Ancho útil de la ventana (sin la barra de desplazamiento): con él la disposición «canvas» sale del contenedor del tema
        fijaAncho: function() {
            document.documentElement.style.setProperty('--siarhe-vw', document.documentElement.clientWidth + 'px');
        },

        aplica: function(wrappers, modo) {
            const canvas = modo === 'canvas';
            if (canvas) {
                app.layout.fijaAncho();
                if (!app.layout._escucha) { window.addEventListener('resize', app.layout.fijaAncho); app.layout._escucha = true; }
            }
            document.querySelectorAll('.siarhe-viz-wrapper').forEach(function(w) { w.classList.toggle('siarhe-layout-canvas', canvas); });
            document.querySelectorAll('[data-siarhe-layout]').forEach(function(b) {
                b.setAttribute('aria-pressed', String(b.dataset.siarheLayout === modo));
            });
            // El mapa y sus leyendas se acomodan al ancho nuevo
            window.dispatchEvent(new Event('resize'));
        },

        init: function(wrappers) {
            wrappers = Array.from(wrappers || []);
            if (!wrappers.length) return;
            const primero = wrappers[0];
            let modo = primero.classList.contains('siarhe-layout-canvas') ? 'canvas' : 'contenedor';
            try {
                const guardado = window.localStorage.getItem(app.layout.CLAVE);
                if (guardado === 'canvas' || guardado === 'contenedor') modo = guardado;
            } catch (e) {}

            // La barra la imprime el servidor (siarhe-view.php) con su espacio ya reservado; solo se arma aquí si falta
            let barra = document.querySelector('.siarhe-barra-pagina');
            if (!barra) {
                const img = primero.dataset.img || '';
                const ico = function(n) { return img ? '<span class="siarhe-ctl-icon" aria-hidden="true" style="--ico: url(&quot;' + img + n + '.svg&quot;)"></span>' : ''; };
                barra = document.createElement('div');
                barra.className = 'siarhe-barra-pagina';
                barra.innerHTML = '<div class="siarhe-layout-toggle" role="group" aria-label="Ancho de la página">' +
                    '<button type="button" class="siarhe-layout-op" data-siarhe-layout="contenedor" title="Contenedor: ancho limitado, más cómodo de leer">' + ico('icon-contenedor') + ' Contenedor</button>' +
                    '<button type="button" class="siarhe-layout-op" data-siarhe-layout="canvas" title="Canvas: todo el ancho de la ventana, ideal para ver mejor el mapa y la tabla">' + ico('icon-canvas') + ' Canvas</button>' +
                    '</div>';
                primero.insertBefore(barra, primero.firstChild);
            }
            barra.addEventListener('click', function(e) {
                const b = e.target.closest('[data-siarhe-layout]');
                if (!b) return;
                app.layout.aplica(wrappers, b.dataset.siarheLayout);
                try { window.localStorage.setItem(app.layout.CLAVE, b.dataset.siarheLayout); } catch (err) {}
            });
            // Solo si el visitante eligió otro ancho se redibuja; con el del sitio la página queda como la dibujó el servidor
            if (modo !== (primero.classList.contains('siarhe-layout-canvas') ? 'canvas' : 'contenedor')) app.layout.aplica(wrappers, modo);
            else {
                if (modo === 'canvas') { app.layout.fijaAncho(); window.addEventListener('resize', app.layout.fijaAncho); app.layout._escucha = true; }
                document.querySelectorAll('[data-siarhe-layout]').forEach(function(b) { b.setAttribute('aria-pressed', String(b.dataset.siarheLayout === modo)); });
            }
        }
    };

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

        /**
         * Tasa con los decimales de RHEMX: uno desde 10, dos entre 1 y 10 y tres por debajo de 1 (una tasa pequeña
         * con dos decimales se quedaría en 0.00). El cero se escribe «0».
         */
        fmtTasa: function(v) {
            if (v === null || v === undefined || isNaN(v)) return 'S/D';
            if (v === 0) return '0';
            const d = v >= 10 ? 1 : (v >= 1 ? 2 : 3);
            return v.toLocaleString('es-MX', { minimumFractionDigits: d, maximumFractionDigits: d });
        },

        /** Escapa texto de datos (CSV/GeoJSON/config) antes de insertarlo en innerHTML. */
        esc: function(val) {
            return (val === undefined || val === null) ? '' : String(val).replace(/[&<>"']/g, function(c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        },

        /**
         * Texto de datos en formato "Nombre Propio" (para tooltips).
         * Solo transforma las palabras escritas TODO en mayusculas; las que ya traen minusculas se respetan.
         * Reglas: los conectores (de, del, la, las, los, y...) van en minusculas (salvo al inicio);
         * las siglas de hasta 3 letras (UMF, HGZ, DIF) y una lista de siglas conocidas se conservan;
         * lo que lleva numeros o puntos (A.C., IMSS-02) se conserva. No usar con CLUES.
         */
        titleCase: function(val) {
            if (val === undefined || val === null) return '';
            const CONECTORES = ['a', 'al', 'ante', 'con', 'de', 'del', 'e', 'el', 'en', 'entre', 'la', 'las', 'lo', 'los', 'o', 'para', 'por', 'sin', 'sobre', 'u', 'un', 'una', 'y'];
            const CORTAS_NO_SIGLA = ['SAN', 'SUR', 'RIO', 'RÍO', 'VIA', 'VÍA', 'LUZ', 'MAR', 'SOL', 'PAZ', 'DON', 'SAL', 'NO', 'SU', 'MAS', 'MÁS', 'DOS', 'UNO', 'FE', 'PIE'];
            const SIGLAS = ['IMSS', 'ISSSTE', 'PEMEX', 'SEDENA', 'SEMAR', 'UNEME', 'CAAPS', 'UNAM', 'CRIT', 'ISSSTECALI', 'ISSEMYM', 'ISSSTESON', 'ISSTE', 'SESA', 'SSA'];
            const LET = 'A-Za-zÁÉÍÓÚÜÑáéíóúüñ';
            let first = true;
            const palabra = function(w) {
                const esPrimera = first; first = false;
                if (/\d/.test(w) || w.indexOf('.') >= 0) return w;
                if (w !== w.toUpperCase() || w === w.toLowerCase()) return w; // ya tiene minusculas (o no tiene letras)
                const lower = w.toLowerCase();
                if (CONECTORES.indexOf(lower) >= 0) return esPrimera ? lower.charAt(0).toUpperCase() + lower.slice(1) : lower;
                if (SIGLAS.indexOf(w) >= 0) return w;
                if (w.length <= 3 && CORTAS_NO_SIGLA.indexOf(w) < 0) return w;
                return lower.charAt(0).toUpperCase() + lower.slice(1);
            };
            const re = new RegExp('^([^' + LET + '0-9]*)(.*?)([^' + LET + '0-9]*)$');
            return String(val).trim().replace(/\S+/g, function(token) {
                const m = token.match(re);
                if (!m || !m[2]) return token;
                // Guiones y diagonales separan palabras (IMSS-BIENESTAR, ISSSTE/IMSS)
                const partes = m[2].split(/([\-\/])/).map(function(x) { return /^[\-\/]$/.test(x) ? x : palabra(x); });
                return m[1] + partes.join('') + m[3];
            });
        },

        /** Medicion opcional de rendimiento: solo escribe en la consola si la URL lleva ?siarhe_perf=1. */
        perf: function(label, startMs, extra) {
            if (app.utils._perfOn === undefined) app.utils._perfOn = /[?&]siarhe_perf=1/.test(window.location.search);
            if (!app.utils._perfOn) return;
            console.log('[SIARHE perf] ' + label + ': ' + (performance.now() - startMs).toFixed(1) + ' ms' + (extra ? ' (' + extra + ')' : ''));
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

        // Triangulo con la punta hacia abajo (clinicas de heridas), del mismo tamano que d3.symbolTriangle
        symbolTriangleDown: {
            draw: function(context, size) {
                const y = -Math.sqrt(size / (Math.sqrt(3) * 3)), x = Math.sqrt(3) * y;
                context.moveTo(0, -y * 2);
                context.lineTo(-x, y);
                context.lineTo(x, y);
                context.closePath();
            }
        },

        // Cuadrado más chico (80 % del lado) y elipse vertical con la misma área que el círculo: formas de RHEMX que d3 no trae
        symbolSquareSmall: {
            draw: function(context, size) { const l = Math.sqrt(size) * 0.8; context.rect(-l / 2, -l / 2, l, l); }
        },
        symbolEllipse: {
            draw: function(context, size) {
                const r = Math.sqrt(size / Math.PI), a = r / 1.4, b = r * 1.4, k = 0.5523;
                context.moveTo(a, 0); context.bezierCurveTo(a, b * k, a * k, b, 0, b); context.bezierCurveTo(-a * k, b, -a, b * k, -a, 0);
                context.bezierCurveTo(-a, -b * k, -a * k, -b, 0, -b); context.bezierCurveTo(a * k, -b, a, -b * k, a, 0); context.closePath();
            }
        },

        /**
         * Formas de los marcadores: las mismas 14 de RHEMX (geoespacial.js, FORMAS), con sus nombres. `FORMAS_DE_LINEA` son las
         * hechas de líneas finas (más, equis, asterisco, ye): su borde toma el color del relleno, si no se verían blancas.
         */
        NOMBRES_FORMA: {
            circle: 'Círculo', ellipse: 'Elipse', square: 'Cuadrado', triangle: 'Triángulo', diamond: 'Diamante', star: 'Estrella', cross: 'Cruz',
            plus: 'Más', times: 'Equis', wye: 'Ye', triangle2: 'Triángulo invertido', diamond2: 'Rombo', square2: 'Cuadrado chico', asterisk: 'Asterisco'
        },
        FORMAS_DE_LINEA: ['plus', 'times', 'asterisk', 'wye'],

        getD3Shape: function(shapeName) {
            if (typeof d3 === 'undefined') return null;
            const shapes = {
                circle: d3.symbolCircle, ellipse: app.utils.symbolEllipse, square: d3.symbolSquare, triangle: d3.symbolTriangle,
                diamond: d3.symbolDiamond, star: d3.symbolStar, cross: d3.symbolCross, plus: d3.symbolPlus, times: d3.symbolTimes,
                wye: d3.symbolWye, triangle2: app.utils.symbolTriangleDown, diamond2: d3.symbolDiamond2,
                square2: app.utils.symbolSquareSmall, asterisk: d3.symbolAsterisk
            };
            return shapes[shapeName] || d3.symbolCircle;
        },

        getMarkerStyle: function(type, state) {
            const e = state.markerStyles[type];
            if (!e) return { shape: 'circle', fill: '#000000', fill2: '', stroke: '#ffffff' };
            // Formas de líneas finas: el borde es el relleno (como en RHEMX)
            if (app.utils.FORMAS_DE_LINEA.indexOf(e.shape) !== -1 && e.stroke !== e.fill) return Object.assign({}, e, { stroke: e.fill });
            return e;
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

        createRangeEvaluator: function(metricConfig, statsDict, pal) {
            const P = pal || app.colors;
            const useColors = metricConfig.custom_colors === true;
            const useRanges = metricConfig.custom_ranges === true;
            const color = (n) => (!P.custom && useColors && metricConfig['r' + n + '_color']) ? metricConfig['r' + n + '_color'] : P.CUSTOM_RANGE[n - 1];
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
                if (value === null || value === undefined || isNaN(value)) return P.NULL;
                if (value === 0) return P.ZERO;
                for (let i = 0; i < ranges.length; i++) {
                    if (ranges[i].test(value)) return ranges[i].color;
                }
                return P.NULL;
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
        console.warn('[SIARHE] La copia local de D3 no está disponible o está incompleta; se usa la del CDN.');
        app._d3Fallback.then(function() {
            if (usable()) callback(); else if (onFail) onFail();
        }).catch(function() { if (onFail) onFail(); });
    };

    // ==========================================
    // 3a. GEOMETRÍAS (TopoJSON / GeoJSON) Y MARCO GEOESTADÍSTICO
    // ==========================================
    // El mapa base y la capa detallada se cargan por marco quinquenal (data-geos): { base: { marco: { url, fecha, ref } },
    // loc: { marco: { url } } }. Cada año de datos usa el marco de su quinquenio (ver SiarheTopo.marcoPara).
    app.geo = {
        /** Carga topojson-client (copia local) la primera vez que llega una topología. */
        ensureTopo: function() {
            if (window.topojson && typeof window.topojson.feature === 'function') return Promise.resolve();
            const url = (window.SiarheDataVizConfig || {}).topojson;
            if (!url) return Promise.reject(new Error('No está configurada la librería para leer TopoJSON'));
            if (!app._topoLib) {
                app._topoLib = new Promise(function(resolve, reject) {
                    const s = document.createElement('script');
                    s.src = url;
                    s.onload = resolve;
                    s.onerror = function() { app._topoLib = null; reject(new Error('No se pudo cargar topojson-client')); };
                    document.head.appendChild(s);
                });
            }
            return app._topoLib;
        },

        /** Descarga una geometría y la devuelve como colección de elementos, sea TopoJSON o GeoJSON. */
        load: function(url) {
            return d3.json(url).then(function(data) {
                if (!window.SiarheTopo || !window.SiarheTopo.esTopologia(data)) return data;
                return app.geo.ensureTopo().then(function() {
                    return window.SiarheTopo.aColeccion(data, window.topojson, d3.geoArea);
                });
            });
        },

        /** Scripts que el worker de geometrías necesita para leer una topología. */
        workerLibs: function() {
            const cfg = window.SiarheDataVizConfig || {};
            return [cfg.topoHelper, cfg.topojson].filter(Boolean);
        },

        /** Marco (clave de state.geos.base o .loc) que corresponde a un año, o null si no hay ninguno cargado. */
        marcoFor: function(anio, mapa) {
            return window.SiarheTopo ? window.SiarheTopo.marcoPara(anio, Object.keys(mapa || {})) : null;
        },

        /** URL de la capa detallada (municipios o localidades) para un año, o '' si no hay. */
        locUrl: function(state, anio) {
            const loc = (state.geos && state.geos.loc) || {};
            const m = app.geo.marcoFor(anio, loc);
            return (m && loc[m] && loc[m].url) || '';
        },

        /** Fuente y fecha de corte del marco en uso, en el pie de la visualización. */
        updateFooter: function(container, state) {
            const info = state.geos && state.geos.base ? state.geos.base[state.marco] : null;
            if (!info) return;
            container.querySelectorAll('.siarhe-geo-ref').forEach(function(n) { n.textContent = info.ref || ''; });
            container.querySelectorAll('.siarhe-geo-date').forEach(function(n) { n.textContent = info.fecha || ''; });
        }
    };

    // ==========================================
    // 3b. ESTADO COMPARTIBLE POR URL (hash)
    // ==========================================
    // Formato: #siarhe-<CVE_ENT>=<querystring codificada>  (varias instancias se separan con ';')
    //   a = año de los datos, cmp = año comparado, u = unidad (plazas | personas), m = metrica, e = escala de color, v = vista (detalle), k = marcadores activos (separados por coma),
    //   p = marcadores de posicion por tema: ver~clinica~modo[~niveles separados por |]
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

            // cmp = año comparado; buildBaseSelectors lo valida contra los años cargados
            if (qs.get('cmp')) state._cmpInicial = qs.get('cmp');

            state._urlPending = {
                view: qs.get('v') === 'detalle' ? 'detalle' : 'base',
                markers: (qs.get('k') || '').split(',').filter(Boolean),
                pos: qs.get('p') || '',
                foco: qs.get('f') || '',
                etiquetas: qs.get('etq') === '1'
            };
        },

        /** Se llama DESPUES de renderizar los controles: activa la vista detalle y los marcadores. */
        applyDeferred: function(container, state) {
            const p = state._urlPending;
            if (!p) return;
            state._urlPending = null;

            // etq=1: los nombres de los lugares a la vista, como los dejó quien copió el enlace
            if (p.etiquetas && app.map) {
                app.map.setLabels(state, true);
                if (state.syncLabelsBtn) state.syncLabelsBtn();
            }

            if (p.view === 'detalle') {
                const sel = container.querySelector('#siarhe-vista-select');
                const optDetalle = sel ? sel.querySelector('option[value="detalle"]') : null;
                if (optDetalle && !optDetalle.disabled) {
                    sel.value = 'detalle';
                    sel.dispatchEvent(new Event('change'));
                }
            }

            if ((p.markers.length > 0 || p.pos || p.foco) && app.controls) {
                (async function() {
                    // p = marcadores de posicion por tema (siarhe-posicion.js); k = marcadores sueltos. Un enlace anterior con
                    // marcadores del sistema en k (ESTAB_1, CATETER...) se traduce a la seleccion equivalente.
                    let adoptado = false;
                    if (p.pos && app.pos) await app.pos.deEnlace(container, state, p.pos);
                    for (const type of p.markers) {
                        if (!p.pos && app.pos && app.pos.adopta(state, type)) { adoptado = true; continue; }
                        if (app.controls.markerUrl(state, type) && !state.activeMarkers.has(type)) {
                            await app.controls.toggleMarker(type, state, container);
                            const chk = container.querySelector('.mc-check[value="' + CSS.escape(type) + '"]');
                            if (chk) chk.checked = true;
                            if (state.pos && state.pos.ui && !state.pos.ver && (state.markerLabels[type] || {}).tipo === 'posicion') { state.pos.ver = 'otros'; app.pos.sync(state); }
                        }
                    }
                    if (adoptado) await app.pos.apply(container, state);
                    // f = lugar o unidad que se buscó (ent:09, mun:28032, loc:..., clues:...): el mapa vuelve a ese lugar
                    if (p.foco && state.buscador) await state.buscador.aplicaFoco(p.foco);
                })();
            }
        },

        write: function(container, state) {
            clearTimeout(app.urlState._timer);
            app.urlState._timer = setTimeout(function() { app.urlState.flush(container, state); }, 200);
        },

        /**
         * Dirección de la página de una entidad (Configuración › Enlaces y Rutas) con lo que se está viendo en el `#`: año,
         * unidad, indicador, estilo y marcadores, y `foco` (`mun:28009`, `clues:…`) si se llegó desde el buscador. La vista
         * de municipios del mapa nacional no viaja: en la página del estado los municipios son el mapa base. Devuelve ''
         * si la entidad no tiene página configurada.
         */
        enlaceEntidad: function(state, cve, foco) {
            cve = String(cve || '').padStart(2, '0');
            const url = state.entityUrls && state.entityUrls[cve];
            if (!url) return '';
            const qs = app.urlState._params(state);
            qs.delete('v'); qs.delete('f');
            if (foco) qs.set('f', foco);
            const str = qs.toString();
            return String(url).split('#')[0] + (str ? '#siarhe-' + cve + '=' + encodeURIComponent(str) : '');
        },

        // Parámetros del enlace de una visualización (lo que difiere de como abrió)
        _params: function(state) {
            const d = state._urlDefaults || {};
            const qs = new URLSearchParams();

            if (state.currentMetric && state.currentMetric !== d.metric) qs.set('m', state.currentMetric);

            const defMode = app.urlState._norm((state.metricas[state.currentMetric] || {}).scale_type);
            if (state.colorMode && app.urlState._norm(state.colorMode) !== defMode) qs.set('e', app.urlState._norm(state.colorMode));

            if (state.base && state._baseInicial) {
                if (String(state.base.anio) !== String(state._baseInicial.anio)) qs.set('a', state.base.anio);
                if (state.base.unidad !== state._baseInicial.unidad) qs.set('u', state.base.unidad);
            }
            if (state.base && state.comparar) qs.set('cmp', state.comparar);
            if (state.isGeoLocMode) qs.set('v', 'detalle');
            const sueltos = state.activeMarkers ? Array.from(state.activeMarkers).filter(function(k) { return k.charAt(0) !== '@'; }) : [];
            if (sueltos.length > 0) qs.set('k', sueltos.join(','));
            const posEnlace = app.pos ? app.pos.aEnlace(state) : '';
            if (posEnlace) qs.set('p', posEnlace);
            if (state.foco) qs.set('f', state.foco);
            if (state.showLabels) qs.set('etq', '1');
            return qs;
        },

        flush: function(container, state) {
            if (!state.metricas) return;
            const qs = app.urlState._params(state);

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
        // Base estatica en JSON optimizado (formato "siarhe-estatico"): devuelve filas con las mismas llaves que d3.csv,
        // calculando las tasas que el archivo declara en "tasas" (conteo / poblacion x por) para que processCSV no cambie.
        jsonToRows: function(j) {
            if (!j || j.formato !== 'siarhe-estatico' || !Array.isArray(j.columnas) || !Array.isArray(j.filas)) return [];
            const cols = j.columnas;
            const pobIdx = cols.findIndex(c => c.toLowerCase().startsWith('pob'));
            const tasas = Object.entries(j.tasas || {});
            const sinPob = (j.sin_poblacion === undefined) ? null : j.sin_poblacion;
            return j.filas.map(f => {
                const d = {};
                for (let i = 0; i < cols.length; i++) d[cols[i]] = (f[i] === null || f[i] === undefined) ? '' : f[i];
                const pob = pobIdx >= 0 ? f[pobIdx] : null;
                tasas.forEach(([t, def]) => {
                    const c = d[def[0]];
                    d[t] = (pob > 0 && typeof c === 'number') ? c / pob * def[1] : (sinPob === null ? '' : sinPob);
                });
                return d;
            });
        },

        // Descarga una base estatica (JSON optimizado o CSV) y devuelve sus filas con las llaves del CSV
        loadBase: function(url) {
            return /\.json(\?|$)/i.test(url) ? d3.json(url).then(app.data.jsonToRows) : d3.csv(url);
        },

        // Pone las filas como la base vigente: reconstruye el mapa de datos y actualiza totales y el año que muestra la pagina
        setBase: function(container, state, rows, anio) {
            state.columnas = new Set(Object.keys(rows[0] || {}));
            state.csvData = app.data.processCSV(rows, state.metricas);
            state.dataMap.clear();
            state.csvData.forEach(row => {
                let targetKey = state.isNacional ? row.CVE_ENT : row.CVE_MUN;
                if (!state.isNacional && !targetKey) targetKey = row.CVE_ENT;
                if (targetKey) state.dataMap.set(targetKey, row);
            });
            app.data.calcularSumaExactaEnfermeras(container, state);
            app.data.calcularTotalHeader(container, state);
            if (anio) container.querySelectorAll('.siarhe-dynamic-year').forEach(n => { n.textContent = anio; });
        },

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
                // El personal que labora en el extranjero (clave 34) tiene su propio apartado: no entra en el total ni en la tabla
                row.isExtranjero = (row.CVE_ENT === '34');

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
                    if (isEstado || (row.isSpecial && !row.isExtranjero)) {
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
            if (!headerDiv) { if (app.cifras) app.cifras.dibuja(container, state); return; }

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

            // Desde la 0.1.17.0 el encabezado lleva las tarjetas de cifras de RHEMX; el texto anterior solo queda si no hay cifras
            if (app.cifras && app.cifras.dibuja(container, state)) return;
            const absFmt = totalAbs.toLocaleString('es-MX');
            const valFmt = (valorMuestra === null || valorMuestra === undefined) ? '—'
                : (info.tipo === 'tasa' ? app.utils.fmtTasa(valorMuestra) : valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 }));

            headerDiv.innerHTML = info.tipo === 'tasa'
                ? `<span style="margin-right:15px;">${app.utils.esc(info.fullLabel)}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span><span>Enfermeras: <strong style="color:#0A66C2; font-size:1.2em;">${absFmt}</strong></span>`
                : `<span>${app.utils.esc(info.fullLabel)}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span>`;
        }
    };

    // ==========================================
    // 4 bis. CIFRAS Y TEXTOS QUE CAMBIAN CON LO ELEGIDO (port de dibujaIndicadores, dibujaResumen y dibujaNota de RHEMX)
    // ==========================================
    /**
     * Tarjetas de cifras sobre el mapa, resumen en texto y nota al pie. Se recalculan con el año, la unidad y el indicador
     * (`app.data.calcularTotalHeader` las llama en cada cambio). El servidor deja escrito el resumen y la nota de las plazas con
     * su tamaño final, para que la página no se recorra cuando el JS los completa.
     */
    app.cifras = {
        // Textos de cada unidad de conteo (los de RHEMX, `UNIDADES`)
        UNIDADES: {
            plazas: { etiqueta: 'Plazas de enfermería', tarjeta: 'Personal de Enfermería', nota: '(plazas de enfermería)', resumen: 'plazas de enfermería', plural: 'plazas', corto: 'plazas' },
            personas: { etiqueta: 'Recursos Humanos de Enfermería', tarjeta: 'Recursos Humanos de Enfermería', nota: '(personas estimadas)', resumen: 'recursos humanos de enfermería', plural: 'recursos humanos', corto: 'recursos humanos' }
        },
        TOTAL: 'enfermeras_total',

        /**
         * Nombre del indicador elegido como lo dice RHEMX (`nombreVariable`): el total es «Plazas de enfermería» o «Recursos
         * Humanos de Enfermería» según la unidad de conteo; los demás, su nombre («Primer nivel», «Licenciatura»…). Lo usan el
         * título de la leyenda y el tooltip.
         */
        nombreVariable: function(state) {
            const info = (state.metricas || {})[state.currentMetric] || {};
            const abs = info.tipo === 'absoluto' ? state.currentMetric
                : (app.utils.findAbsoluteKeyForPair(info.pair || state.currentMetric, state.metricas || {}) || info.pair || null);
            if (!abs || abs === app.cifras.TOTAL) return app.cifras.unidad(state).etiqueta;
            return (app.controls && app.controls.indicatorName) ? app.controls.indicatorName(info) : (info.nombre || info.label || '');
        },

        unidad: function(state, container) {
            const u = (state.base && state.base.unidad) || (container && container.dataset.unidad) || 'plazas';
            return app.cifras.UNIDADES[u] || app.cifras.UNIDADES.plazas;
        },

        /**
         * Cifras del lugar con la base y el indicador vigentes (pura, probada en tests/prueba_controles.js). Suma los lugares y los
         * registros sin lugar asignado; el extranjero (clave 34) no entra, como en el resto del plugin. Con la geometría compara las
         * claves: un municipio del SIARHE sin polígono y sin población es «nuevo» (no localizado en INEGI); con población, se creó
         * después del marco geoestadístico (sin polígono).
         */
        calcula: function(state) {
            const T = app.cifras.TOTAL, filas = state.csvData || [];
            const info = (state.metricas || {})[state.currentMetric] || {};
            const abs = info.tipo === 'absoluto' ? state.currentMetric
                : (app.utils.findAbsoluteKeyForPair(info.pair || state.currentMetric, state.metricas || {}) || null);
            const lugares = filas.filter(function(r) { return !r.isTotal && !r.isSpecial && !r.isExtranjero; });
            const sinLugar = filas.filter(function(r) { return !r.isTotal && r.isSpecial && !r.isExtranjero; });
            const suma = function(lista, k) { return lista.reduce(function(a, r) { return a + (Number(r[k]) || 0); }, 0); };
            const c = {
                pob: suma(lugares, 'poblacion'),
                total: suma(lugares, T) + suma(sinLugar, T),
                sinLugar: suma(sinLugar, T),
                abs: abs, esTotal: !abs || abs === T,
                cnt: abs ? suma(lugares, abs) + suma(sinLugar, abs) : null,
                nombre: (app.controls && app.controls.indicatorName) ? app.controls.indicatorName(info) : (info.nombre || info.label || ''),
                lugares: lugares.length, conRegistros: 0, nuevos: [], sinPoligono: [], conGeo: false
            };
            const feats = state.geoData && state.geoData.features;
            if (feats && feats.length) {
                c.conGeo = true;
                const claves = new Set(feats.map(function(f) { return app.utils.getGeoKey(f.properties, state.isNacional); }));
                c.lugares = claves.size;
                lugares.forEach(function(r) {
                    const k = state.isNacional ? r.CVE_ENT : (r.CVE_MUN || r.CVE_ENT);
                    if (claves.has(k)) { if ((Number(r[T]) || 0) > 0) c.conRegistros++; }
                    else (r.poblacion > 0 ? c.sinPoligono : c.nuevos).push({ clave: k, nombre: r.estado });
                });
            } else {
                c.conRegistros = lugares.filter(function(r) { return (Number(r[T]) || 0) > 0; }).length;
            }
            return c;
        },

        // Pinta las tarjetas, el resumen y la nota; devuelve false si no hay datos (se queda el encabezado de antes)
        dibuja: function(container, state) {
            if (!state.csvData || !state.csvData.length) return false;
            const c = app.cifras.calcula(state);
            app.cifras._tarjetas(container, state, c);
            app.cifras._resumen(container, state, c);
            app.cifras._nota(container, state, c);
            return true;
        },

        _lugar: function(container) {
            return container.dataset.lugar || '';
        },

        _anio: function(container, state) {
            return String((state.base && state.base.anio) || container.dataset.anio || '');
        },

        _tarjetas: function(container, state, c) {
            const nodo = container.querySelector('.siarhe-dynamic-total');
            if (!nodo) return;
            const u = app.cifras.unidad(state, container), fmtN = function(v) { return (Number(v) || 0).toLocaleString('es-MX'); };
            const tasa = function(n) { return c.pob > 0 ? (n / c.pob) * 1000 : null; };
            const lugar = app.cifras._lugar(container), anio = app.cifras._anio(container, state);
            const tiles = [
                ['Población', fmtN(c.pob), 'INEGI, ' + lugar],
                [u.tarjeta, fmtN(c.total), lugar + ', ' + anio, u.nota]
            ];
            if (c.esTotal) tiles.push(['Tasa por 1,000 habitantes', app.utils.fmtTasa(tasa(c.total)), u.tarjeta, u.nota]);
            else {
                const part = c.total > 0 ? (c.cnt / c.total) * 100 : null;
                tiles.push([c.nombre, fmtN(c.cnt), part === null ? '' : part.toLocaleString('es-MX', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' % de ' + u.plural],
                    ['Tasa por 1,000 habitantes', app.utils.fmtTasa(tasa(c.cnt)), c.nombre]);
            }
            const esc = app.utils.esc, hayNota = tiles.some(function(t) { return t[3]; });
            nodo.classList.add('siarhe-kpis');
            nodo.removeAttribute('style');
            nodo.innerHTML = tiles.map(function(t) {
                return '<div class="siarhe-kpi"><div class="siarhe-kpi-titulo">' + esc(t[0]) + '</div><div class="siarhe-kpi-valor">' + esc(t[1]) + '</div>' +
                    '<div class="siarhe-kpi-periodo">' + esc(t[2]) + '</div>' + (hayNota ? '<p class="siarhe-kpi-nota">' + (t[3] ? esc(t[3]) : '&nbsp;') + '</p>' : '') + '</div>';
            }).join('');
        },

        _resumen: function(container, state, c) {
            const nodo = container.querySelector('.siarhe-resumen');
            if (!nodo) return;
            const esc = app.utils.esc, u = app.cifras.unidad(state, container);
            const fmtN = function(v) { return (Number(v) || 0).toLocaleString('es-MX'); };
            const cifra = function(v) { return '<strong class="siarhe-cifra">' + esc(v) + '</strong>'; };
            const lugar = state.isNacional ? 'la República Mexicana' : esc(app.cifras._lugar(container)), anio = app.cifras._anio(container, state);
            let donde;
            if (state.isNacional) {
                donde = 'las ' + cifra(32) + ' entidades federativas' + (c.sinLugar > 0 ? ' (y ' + cifra(fmtN(c.sinLugar)) + ' sin entidad asignada)' : '');
            } else {
                const detalle = ['en ' + cifra(fmtN(c.conRegistros)) + ' de ellos hay registros'];
                if (c.nuevos.length) detalle.push((c.nuevos.length === 1 ? 'se identificó ' : 'se identificaron ') + cifra(fmtN(c.nuevos.length)) + ' ' + (c.nuevos.length === 1 ? 'municipio nuevo en el SIARHE no localizado' : 'municipios nuevos en el SIARHE no localizados') + ' en INEGI');
                if (c.sinLugar > 0) detalle.push(cifra(fmtN(c.sinLugar)) + ' no tienen municipio asignado');
                donde = 'los ' + cifra(fmtN(c.lugares)) + ' municipios de INEGI (' + detalle.join('; ') + ')';
            }
            const partes = ['En el año ' + cifra(anio) + ', ' + lugar + ' cuenta con un total de ' + cifra(fmtN(c.total)) + ' ' + u.resumen +
                ' distribuidos en ' + donde + ', según el corte del SIARHE y la población de INEGI.'];
            if (!c.esTotal) {
                const pct = c.total > 0 ? (c.cnt / c.total) * 100 : 0;
                partes.push('De ese total, ' + cifra(fmtN(c.cnt)) + ' (' + cifra(pct.toLocaleString('es-MX', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %') +
                    ') corresponden a «' + esc(c.nombre) + '», una tasa de ' + cifra(app.utils.fmtTasa(c.pob > 0 ? (c.cnt / c.pob) * 1000 : null)) + ' por cada 1,000 habitantes.');
            }
            // Comparación de dos años: cómo cambió el lugar y cuántos lugares suben, bajan o se quedan igual
            const cmb = app.cambio.calcula(state), ct = cmb && cmb.mapa.get('total');
            if (ct && ct.delta !== null) {
                const C = app.cambio, s = cmb.stats, U = C.UMBRAL;
                const medida = C.esTasa(state) ? 'la tasa por 1,000 habitantes' : 'el número de ' + u.plural;
                const nivel = state.isNacional ? 'entidades' : 'municipios';
                partes.push('Entre ' + cifra(state.comparar) + ' y ' + cifra(anio) + ', ' + medida + ' de ' + lugar + ' pasó de ' + cifra(C.fmtMedida(state, ct.v2)) +
                    ' a ' + cifra(C.fmtMedida(state, ct.v1)) + ' (' + cifra(C.fmtCambio(state, ct.delta)) + (ct.pct === null ? '' : ', ' + cifra(C.fmtPorcentaje(ct.pct))) + '). ' +
                    'De ' + (state.isNacional ? 'las ' : 'los ') + cifra(fmtN(s.total)) + ' ' + nivel + ' con dato en ambos años, ' + cifra(fmtN(s.suben)) + ' suben ' + U + ' % o más, ' +
                    cifra(fmtN(s.bajan)) + ' bajan ' + U + ' % o más y ' + cifra(fmtN(s.iguales)) + ' cambian menos de ' + U + ' % (sin cambio importante).');
            }
            nodo.innerHTML = partes.join(' ');
        },

        _nota: function(container, state, c) {
            const nodo = container.querySelector('.siarhe-nota-mapa');
            if (!nodo) return;
            const esc = app.utils.esc, rotulo = function(t) { return '<em>' + t + '</em>'; };
            const u = app.cifras.unidad(state, container), personas = u === app.cifras.UNIDADES.personas;
            const lugarTotal = state.isNacional ? 'nacional' : 'estatal';
            const lista = function(l) {
                const n = l.map(function(x) { return esc(x.nombre) + ' (' + esc(x.clave) + ')'; });
                return n.length > 6 ? n.slice(0, 6).join('; ') + ' y ' + (n.length - 6) + ' más' : n.join('; ');
            };
            const partes = [rotulo('Nota estadística.') + ' Tasa por 1,000 habitantes = (' + (personas ? 'recursos humanos de enfermería estimados' : 'plazas de enfermería') + ' ÷ población) × 1,000. ' +
                (personas ? 'Los recursos humanos de enfermería son estimados: se cuentan las plazas con ingreso «Primer» (una por persona estimada; el SIARHE no trae CURP, por lo que las personas no se pueden verificar). '
                    : 'La plaza es la unidad estándar del conteo; una persona puede ocupar más de una. ') +
                'El total ' + lugarTotal + ' es la suma de ' + (state.isNacional ? 'las 32 entidades federativas más los registros sin entidad asignada' : 'los municipios más los registros clasificados como «No Disponible»') + '. ' +
                'Cada registro cuenta una sola vez por variable; el 0 y el sin dato (S/D) no entran en el cálculo de los cuartiles.'];
            if (app.cambio.calcula(state)) {
                const C = app.cambio, anio = esc(app.cifras._anio(container, state)), cmp = esc(state.comparar);
                // Si los lugares con población en los dos años tienen la misma, ambos años usan el mismo censo (un municipio
                // nuevo sin población en un año no cuenta)
                let comunes = 0, iguales = 0;
                (state.csvData || []).forEach(function(r) {
                    if (r.isTotal || r.isSpecial || !(r.poblacion > 0)) return;
                    const r2 = state.cmp.porClave.get(app.cambio._clave(state, r));
                    if (!r2 || !(r2.poblacion > 0)) return;
                    comunes++;
                    if (r2.poblacion === r.poblacion) iguales++;
                });
                const mismaPob = comunes > 0 && iguales === comunes;
                partes.push(rotulo('Nota estadística (comparación).') + ' El cambio es el valor de ' + anio + ' menos el de ' + cmp + '; el porcentaje lo mide frente al valor de ' + cmp + '. ' +
                    'El color sigue el cambio porcentual frente al año comparado: un cambio menor de ' + C.UMBRAL + ' % se considera sin cambio importante y queda en el color neutro; desde ' + C.UMBRAL +
                    ' % se nota con un tono suave y el color se intensifica hasta ' + C.EXTREMO + ' %, que toma el color más intenso (verde si sube, guinda si baja). ' +
                    (mismaPob ? 'Ambos años usan la misma población de INEGI, así que el cambio de la tasa viene solo del cambio en las ' + u.plural + '. '
                        : 'Cada año usa la población del censo o conteo de INEGI que le corresponde, por lo que parte del cambio de la tasa puede deberse al cambio de población y no solo a las ' + u.plural + '. ') +
                    'El año del mapa es siempre el más reciente de los dos. Un lugar sin dato en uno de los años se toma como 0 en ese año (un municipio nuevo cuenta como un incremento desde 0 y su ficha lo indica); ' +
                    'queda en S/D solo si no tiene dato en ninguno de los dos, y con 0 en ambos años tiene su propio color.');
            }
            if (c.nuevos.length) {
                const uno = c.nuevos.length === 1;
                partes.push(rotulo('Nota estadística (municipios).') + (uno ? ' Se identificó ' : ' Se identificaron ') + c.nuevos.length.toLocaleString('es-MX') + (uno ? ' municipio' : ' municipios') +
                    ' en la base del SIARHE ' + (uno ? 'no localizado' : 'no localizados') + ' en los registros de INEGI: ' + lista(c.nuevos) + '. ' +
                    (uno ? 'Se lista en la tabla, con su clave, sin población ni tasa, y su conteo está incluido en el total.' : 'Se listan en la tabla, con su clave, sin población ni tasa, y su conteo está incluido en el total.'));
            }
            if (c.sinPoligono.length) {
                const n = c.sinPoligono.length;
                partes.push(rotulo('Nota específica.') + ' <sup>a</sup> Sin polígono: ' + lista(c.sinPoligono) + ' (' + (n === 1 ? 'municipio creado' : 'municipios creados') +
                    ' después del marco geoestadístico de INEGI que usa el mapa); aparece' + (n === 1 ? '' : 'n') + ' solo en la tabla con sus ' + u.plural + '.');
            }
            nodo.innerHTML = partes.join('<br>');
        }
    };

    // ==========================================
    // 4 ter. COMPARACIÓN DE DOS AÑOS (port de la sección 3b de geoespacial.js de RHEMX)
    // ==========================================
    /**
     * Con «Comparar con» el mapa pinta, en cada lugar, el cambio del indicador elegido (tasa o número) entre el año del mapa y
     * el año comparado: valor del año del mapa menos valor del año comparado. El color sigue el cambio porcentual con cinco
     * puntos fijos (-25 %, -5 %, 0, +5 % y +25 %): un cambio menor de UMBRAL % es «sin cambio importante» (color neutro) y desde
     * EXTREMO % toma el color más intenso. No aplica a la población ni a las localidades de un estado. El año del mapa es
     * siempre el más reciente de los dos. La base del año comparado (misma unidad) queda en `state.cmp`; los cambios se
     * calculan al vuelo con el indicador vigente (`calcula`, probada en tests/prueba_controles.js).
     */
    app.cambio = {
        UMBRAL: 5,
        EXTREMO: 25,
        COLORES: { BAJA: '#9b2247', BAJA_CLARO: '#e3a0b6', NEUTRO: '#efeadd', SUBE_CLARO: '#9fd5b5', SUBE: '#006400' },

        // Los cinco colores, de la baja más fuerte a la subida más fuerte
        rangos: function() {
            const c = app.cambio.COLORES;
            return [c.BAJA, c.BAJA_CLARO, c.NEUTRO, c.SUBE_CLARO, c.SUBE];
        },

        // Clave de un renglón, la misma del mapa de datos (state.dataMap)
        _clave: function(state, r) {
            return state.isNacional ? r.CVE_ENT : (r.CVE_MUN || r.CVE_ENT);
        },

        // La comparación aplica a los indicadores (no a la población) y no a las localidades de un estado
        aplica: function(state) {
            return state.currentMetric !== 'poblacion' && !(!state.isNacional && state.isGeoLocMode);
        },

        activa: function(state) {
            const b = state.base, c = state.cmp;
            return !!(b && state.comparar && state.comparar !== String(b.anio) && c && c.anio === state.comparar &&
                c.unidad === b.unidad && app.cambio.aplica(state));
        },

        /**
         * Descarga la base del año comparado con la unidad vigente y la deja en `state.cmp` (si ya está, no la vuelve a
         * pedir). Sin año comparado, o si ese año no tiene base de la unidad, la quita. Devuelve una promesa.
         */
        prepara: function(state) {
            const b = state.base;
            if (!b || !state.comparar || state.comparar === String(b.anio)) { state.cmp = null; return Promise.resolve(); }
            const info = (b.bases[state.comparar] || {})[b.unidad];
            if (!info) { state.cmp = null; return Promise.resolve(); }
            if (state.cmp && state.cmp.url === info.url) return Promise.resolve();
            const anio = state.comparar, unidad = b.unidad;
            return app.data.loadBase(info.url).then(function(rows) {
                state.cmp = app.cambio.deFilas(state, app.data.processCSV(rows, state.metricas), anio, unidad, info.url);
            }).catch(function(err) {
                console.error('[SIARHE] No se pudo cargar la base del año comparado', err);
                state.cmp = null;
            });
        },

        // Base del año comparado indexada por clave, con su renglón de total aparte
        deFilas: function(state, filas, anio, unidad, url) {
            const porClave = new Map();
            let total = null;
            filas.forEach(function(r) {
                if (r.isTotal) { total = r; return; }
                if (r.isSpecial || r.isExtranjero) return;
                const k = app.cambio._clave(state, r);
                if (k) porClave.set(k, r);
            });
            return { anio: String(anio), unidad: unidad, url: url || '', porClave: porClave, total: total };
        },

        /**
         * Cambios del indicador vigente: { mapa: clave -> cambio, stats }. Cada cambio es { v1 (año del mapa), v2 (año
         * comparado), delta, pct, clase, ceros, nuevo, sinDatoBase, sinDatoMapa }; el total va con la clave «total». Un valor
         * sin dato en uno de los dos años se toma como 0 en ese año (un municipio nuevo es un incremento desde 0); solo queda
         * S/D si no hay dato en ninguno. `stats` cuenta los lugares del mapa que suben, bajan o no cambian. Devuelve null si
         * la comparación no está activa.
         */
        calcula: function(state) {
            if (!app.cambio.activa(state)) return null;
            const m = state.currentMetric, cmp = state.cmp, memo = state._cambio;
            if (memo && memo.datos === state.csvData && memo.cmp === cmp && memo.m === m && memo.geo === state.geoData) return memo;
            const U = app.cambio.UMBRAL, mapa = new Map();
            const valor = function(r) {
                const x = r ? r[m] : null;
                return (x === null || x === undefined || x === '' || isNaN(Number(x))) ? null : Number(x);
            };
            const agrega = function(clave, r1, r2) {
                const v1 = valor(r1), v2 = valor(r2);
                const sinDato = v1 === null && v2 === null;
                const a = v1 === null ? 0 : v1, b = v2 === null ? 0 : v2;
                const delta = sinDato ? null : a - b;
                const pct = delta !== null && b > 0 ? (delta / b) * 100 : null;
                const clase = delta === null ? null : (delta === 0 || (pct !== null && Math.abs(pct) < U)) ? 'igual' : delta > 0 ? 'sube' : 'baja';
                mapa.set(clave, { v1: v1, v2: v2, delta: delta, pct: pct, clase: clase, ceros: !sinDato && a === 0 && b === 0,
                    nuevo: !r2, sinDatoBase: v2 === null && v1 !== null, sinDatoMapa: v1 === null && v2 !== null });
            };
            let total = null;
            (state.csvData || []).forEach(function(r) {
                if (r.isTotal) { total = r; return; }
                if (r.isSpecial || r.isExtranjero) return;
                const k = app.cambio._clave(state, r);
                if (k && !mapa.has(k)) agrega(k, r, cmp.porClave.get(k) || null);
            });
            if (total) agrega('total', total, cmp.total);

            // Resumen de los lugares que se ven en el mapa (los que tienen polígono; sin geometría, todos)
            const feats = state.geoData && state.geoData.features;
            const conPoligono = feats && feats.length ? new Set(feats.map(function(f) { return app.utils.getGeoKey(f.properties, state.isNacional); })) : null;
            const validos = [];
            mapa.forEach(function(c, k) { if (k !== 'total' && c.clase && (!conPoligono || conPoligono.has(k))) validos.push(c); });
            const cuenta = function(clase) { return validos.filter(function(c) { return c.clase === clase; }).length; };
            state._cambio = { datos: state.csvData, cmp: cmp, m: m, geo: state.geoData, mapa: mapa,
                stats: { suben: cuenta('sube'), bajan: cuenta('baja'), iguales: cuenta('igual'), total: validos.length } };
            return state._cambio;
        },

        /**
         * Posición del cambio sobre los cinco puntos de la leyenda (0 = -25 % o menos, 0.25 = -5 %, 0.5 = sin cambio importante,
         * 0.75 = +5 %, 1 = +25 % o más). Un lugar que antes valía 0 y ahora tiene valor no tiene porcentaje: cuenta como el extremo.
         */
        posicion: function(c) {
            const U = app.cambio.UMBRAL, E = app.cambio.EXTREMO;
            if (c.clase === 'igual') return 0.5;
            const p = c.pct === null ? E : Math.abs(c.pct);
            const avance = Math.max(0, Math.min(1, (p - U) / (E - U)));
            return c.clase === 'sube' ? 0.75 + 0.25 * avance : 0.25 - 0.25 * avance;
        },

        /** Color de un cambio según el estilo del mapa: degradado, monocromático (de menos a más) o rangos. */
        colorDe: function(state) {
            const pal = app.palette(state), modo = state.colorMode, R = app.cambio.rangos();
            const mono = modo === 'monocromatico' || modo === 'mono';
            const escala = mono ? d3.interpolate(pal.MONO[0], pal.MONO[pal.MONO.length - 1]) : d3.scaleLinear().domain([0, 0.25, 0.5, 0.75, 1]).range(R);
            return function(c) {
                if (!c || !c.clase) return pal.NULL;
                if (c.ceros) return pal.ZERO;
                const t = app.cambio.posicion(c);
                if (modo !== 'rangos') return escala(t);
                return R[c.clase === 'igual' ? 2 : t >= 1 ? 4 : t > 0.5 ? 3 : t <= 0 ? 0 : 1];
            };
        },

        // Colores de la leyenda en degradado (de la baja más fuerte a la subida más fuerte)
        coloresLeyenda: function(state) {
            const modo = state.colorMode;
            if (modo === 'monocromatico' || modo === 'mono') {
                const pal = app.palette(state), f = d3.interpolate(pal.MONO[0], pal.MONO[pal.MONO.length - 1]);
                return [f(0), f(0.25), f(0.5), f(0.75), f(1)];
            }
            return app.cambio.rangos();
        },

        // Formatos de RHEMX: la medida con los decimales de la tasa o en enteros, el cambio con signo y el porcentaje
        esTasa: function(state) { return ((state.metricas || {})[state.currentMetric] || {}).tipo === 'tasa'; },
        signo: function(v) { return v > 0 ? '+' : v < 0 ? '-' : ''; },
        fmtMedida: function(state, v) {
            if (v === null || v === undefined) return 'S/D';
            return app.cambio.esTasa(state) ? app.utils.fmtTasa(v) : Math.round(v).toLocaleString('es-MX');
        },
        fmtCambio: function(state, v) {
            return (v === null || v === undefined) ? 'S/D' : app.cambio.signo(v) + app.cambio.fmtMedida(state, Math.abs(v));
        },
        fmtPorcentaje: function(p) {
            return (p === null || p === undefined) ? 'S/D'
                : app.cambio.signo(p) + Math.abs(p).toLocaleString('es-MX', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %';
        },
        // «desde 0» cuando el valor anterior era 0 (o no existía) y ahora hay valor
        fmtPctDe: function(c) {
            if (!c || c.delta === null || c.delta === undefined) return 'S/D';
            return c.delta === 0 ? '0 %' : c.pct === null ? 'desde 0' : app.cambio.fmtPorcentaje(c.pct);
        },

        // Rótulo de la medida (tooltip y columnas): la tasa o la unidad de conteo
        rotuloMedida: function(state) {
            if (app.cambio.esTasa(state)) return 'Tasa por 1,000 habitantes';
            return (state.base && state.base.unidad) === 'personas' ? 'Recursos humanos' : 'Plazas';
        }
    };

    // ==========================================
    // 5. ORQUESTADOR DE INICIALIZACIÓN
    // ==========================================
    /**
     * Navegación dentro de la página, como en RHEMX: entrar a un estado (o volver a la República) sin cambiar de página.
     * La dirección que entrega la vista de una entidad viene en `SiarheDataVizConfig.vista`; el mapa estático no la entrega
     * (cada entidad tiene su página, ver `app.urlState.enlaceEntidad`), así que aquí no hace nada. Queda para el mapa
     * dinámico de SQL, donde todo vivirá en un solo lugar.
     */
    app.nav = {
        CLAVE: 'siarhe-en',

        disponible: function() { return !!(window.SiarheDataVizConfig || {}).vista; },

        // Entidad pedida en el enlace, o ''
        delEnlace: function() {
            const parte = (window.location.hash || '').replace(/^#/, '').split(';').find(function(p) { return p.indexOf(app.nav.CLAVE + '=') === 0; });
            const cve = parte ? parte.slice(app.nav.CLAVE.length + 1) : '';
            return /^\d{2}$/.test(cve) ? cve : '';
        },

        // Deja en el enlace solo la entidad a la que se entró (las elecciones de la vista anterior ya no aplican)
        _anota: function(cve, origen) {
            const raw = (window.location.hash || '').replace(/^#/, '');
            if (raw && !raw.split(';').every(function(p) { return p.indexOf('siarhe-') === 0; })) return;
            const nuevo = cve && cve !== origen ? '#' + app.nav.CLAVE + '=' + cve : '';
            try { window.history.replaceState(null, '', window.location.pathname + window.location.search + nuevo); } catch (e) {}
        },

        /**
         * Pone la vista de la entidad `cve` en lugar de `container`. `opts.foco` (`mun:28009`, `clues:…`) se aplica cuando
         * la vista nueva termina de cargar. Devuelve la visualización nueva, o null si no se pudo (queda la anterior).
         */
        ir: async function(container, cve, opts) {
            opts = opts || {};
            const plantilla = (window.SiarheDataVizConfig || {}).vista;
            if (!plantilla || !/^\d{2}$/.test(cve || '') || cve === container.dataset.cveEnt) return null;
            const origen = container.dataset.origen || container.dataset.cveEnt;
            const cargando = container.querySelector('.siarhe-loading-overlay');
            if (cargando) { cargando.style.display = 'flex'; const p = cargando.querySelector('p'); if (p) p.textContent = 'Cargando…'; }
            let nuevo = null;
            try {
                const url = plantilla.replace('{ent}', cve).replace('{mode}', (container.dataset.mode || 'MT').trim());
                const r = await fetch(url, { credentials: 'same-origin' });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                const hallado = doc.querySelector('.siarhe-viz-wrapper');
                if (hallado) nuevo = document.importNode(hallado, true);
            } catch (err) {
                console.warn('[SIARHE] No se pudo cargar la vista de la entidad ' + cve, err);
            }
            if (!nuevo) { if (cargando) cargando.style.display = 'none'; return null; }

            if (document.fullscreenElement && document.exitFullscreen) { try { await document.exitFullscreen(); } catch (e) {} }
            nuevo.dataset.origen = origen;
            nuevo.classList.toggle('siarhe-layout-canvas', container.classList.contains('siarhe-layout-canvas'));
            const barra = container.querySelector('.siarhe-barra-pagina');
            if (container._siarhe && container._siarhe.tooltip) { try { container._siarhe.tooltip.remove(); } catch (e) {} }
            container.replaceWith(nuevo);
            if (barra) nuevo.insertBefore(barra, nuevo.firstChild);
            app.nav._anota(cve, origen);
            app.initVisualization(nuevo);

            if (opts.foco) {
                await app.nav._espera(nuevo);
                const st = nuevo._siarhe;
                if (st && st.buscador) { await st.buscador.aplicaFoco(opts.foco); if (app.urlState) app.urlState.write(nuevo, st); }
            }
            return nuevo;
        },

        // Vuelve a la entidad con la que abrió la página
        regresa: function(container) {
            return container.dataset.origen ? app.nav.ir(container, container.dataset.origen) : Promise.resolve(null);
        },

        // Espera a que la visualización termine su primera carga (o 20 s)
        _espera: function(container) {
            return new Promise(function(resuelve) {
                const t0 = Date.now();
                (function mira() {
                    if ((container.classList.contains('siarhe-cargado') && container._siarhe && container._siarhe.buscador) || Date.now() - t0 > 20000) resuelve();
                    else setTimeout(mira, 120);
                })();
            });
        }
    };

    /**
     * Centrado óptico de los textos de los controles. El panel centra cada caja de línea en su control, pero cada fuente
     * reparte su alto de forma distinta (ascendentes, descendentes): con la Roboto del sitio los rótulos en mayúsculas
     * («BUSCAR», «TEMA») y los títulos de fila quedaban 1.5 px arriba del centro y los botones entre 0.5 y 1 px. Aquí se mide,
     * con la fuente que de verdad usa el sitio, cuánto se desvía el centro de las mayúsculas del centro de su caja, y se
     * corrige con variables CSS que mueven solo el texto (los iconos se quedan donde están; ver «CENTRADO ÓPTICO» en la hoja).
     */
    app.centraTextos = function(container) {
        const cv = (app.centraTextos._cv = app.centraTextos._cv || document.createElement('canvas').getContext('2d'));
        if (!cv || typeof cv.measureText !== 'function') return;
        const VARS = ['rotulo', 'titulo', 'boton', 'conm', 'tabla'];
        VARS.forEach(function(v) { container.style.removeProperty('--siarhe-aj-' + v); });   // se mide sin corrección
        const metricas = function(el, texto) {
            const cs = getComputedStyle(el);
            cv.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
            const m = cv.measureText(texto || 'H'), h = cv.measureText('H');
            if (!m.fontBoundingBoxAscent && m.fontBoundingBoxAscent !== 0) return null;   // navegador sin estas métricas
            return { A: m.fontBoundingBoxAscent, D: m.fontBoundingBoxDescent, cap: h.actualBoundingBoxAscent };
        };
        const textoDe = function(el) {
            const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, { acceptNode: function(n) { return n.textContent.trim() ? 1 : 2; } });
            return w.nextNode();
        };
        // Desvío (px; negativo = arriba) del centro de las mayúsculas del primer texto de `el` respecto al centro de `caja`
        const desvio = function(el, caja) {
            const n = el && caja && el.offsetHeight && caja.offsetHeight ? textoDe(el) : null;
            if (!n) return null;
            const m = metricas(n.parentElement, n.textContent.trim());
            if (!m) return null;
            const rg = document.createRange(); rg.selectNodeContents(n);
            const rr = rg.getClientRects()[0];
            if (!rr) return null;
            const base = rr.top + (rr.height - (m.A + m.D)) / 2 + m.A;
            const cb = caja.getBoundingClientRect();
            return (base - m.cap / 2) - (cb.top + cb.height / 2);
        };
        const pon = function(v, d) {
            if (d === null || !isFinite(d) || Math.abs(d) > 6) return;   // sin dato o un valor absurdo: se deja como está
            const px = Math.round(-d * 4) / 4;
            if (px) container.style.setProperty('--siarhe-aj-' + v, px + 'px');
        };
        const q = function(sel) { return Array.from(container.querySelectorAll(sel)).find(function(e) { return e.offsetHeight > 0 && textoDe(e); }) || null; };
        const rotulo = q('.siarhe-controls-layout .siarhe-control-group > label');
        const dRotulo = rotulo ? desvio(rotulo, rotulo.closest('.siarhe-control-group')) : null;
        pon('rotulo', dRotulo);
        // El título de la fila se centra con el primer control de su renglón; si va en su propio renglón (tableta y móvil) no
        // hay con qué alinearlo y no se corrige
        const titulo = q('.siarhe-controls-layout .siarhe-fila-titulo');
        const vecino = titulo ? Array.from(titulo.parentElement.children).find(function(e) { return e !== titulo && e.offsetHeight > 0; }) : null;
        if (titulo && vecino) {
            const a = titulo.getBoundingClientRect(), b = vecino.getBoundingClientRect();
            if (a.top < b.bottom && b.top < a.bottom) pon('titulo', desvio(titulo, vecino));
        }
        const boton = q('.siarhe-controls-layout .siarhe-btn');
        pon('boton', boton ? desvio(boton, boton) : null);
        const conm = q('.siarhe-conm-op');
        pon('conm', conm ? desvio(conm, conm.closest('.siarhe-conm') || conm) : null);
        // El botón de la tabla escribe su texto con ::before (no hay nodo que medir): se calcula con las métricas de su letra
        const tabla = container.querySelector('.siarhe-table-toggle');
        if (tabla && tabla.offsetHeight) {
            const m = metricas(tabla, 'Mostrar tabla');
            if (m) pon('tabla', (m.A - m.D - m.cap) / 2);
        }
    };

    app.initVisualization = function(container) {
        const cveEnt = container.dataset.cveEnt;
        let geojsonUrl = container.dataset.geojson;
        let csvUrl = container.dataset.csv;
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
            isNacional: isNacional, lugar: container.dataset.lugar || '',
            tooltip: null, markerTrigger: null, lastClickTime: 0,
            rawCSVDataCache: {},
            initialTransform: d3.zoomIdentity
        };

        if (!geojsonUrl && mode.includes('M')) return;

        // Año y unidad iniciales: los de la base mas reciente, o los que traiga la URL (a = año, u = unidad) si existen
        state._baseInicial = { anio: container.dataset.anio || '', unidad: container.dataset.unidad || 'plazas' };
        try {
            const bases = JSON.parse(container.dataset.bases || '{}') || {};
            const qs = app.urlState._parse(container);
            const a = qs && qs.get('a'), u = (qs && qs.get('u')) || state._baseInicial.unidad;
            if (a && bases[a] && bases[a][u]) {
                csvUrl = bases[a][u].url;
                container.dataset.anio = a; container.dataset.unidad = u;
                container.querySelectorAll('.siarhe-dynamic-year').forEach(n => { n.textContent = a; });
            }
        } catch (e) { /* sin bases o URL invalida: se usa la base inicial */ }

        // Marco geoestadístico del año inicial: mapa base y capa detallada (municipios o localidades)
        const geos = safeParseJSON(container.dataset.geos, {}) || {};
        state.geos = { base: geos.base || {}, loc: geos.loc || {} };
        state.marco = app.geo.marcoFor(container.dataset.anio, state.geos.base);
        if (state.marco && state.geos.base[state.marco]) geojsonUrl = state.geos.base[state.marco].url;
        state.geoLocUrl = app.geo.locUrl(state, container.dataset.anio) || container.dataset.geojsonLoc || '';
        app.geo.updateFooter(container, state);

        // Descarga paralela de archivos (Solo trae lo necesario)
        Promise.all([
            geojsonUrl && mode.includes('M') ? app.geo.load(geojsonUrl) : Promise.resolve(null),
            csvUrl ? app.data.loadBase(csvUrl) : Promise.resolve(null)
        ])
        .then(([geo, csv]) => {
            state.geoData = geo;

            // Metrica, escala, vista y marcadores iniciales pueden venir en el hash de la URL
            if (app.urlState) app.urlState.apply(container, state);

            if (csv) {
                state.columnas = new Set(Object.keys(csv[0] || {}));
                state.csvData = app.data.processCSV(csv, state.metricas);
                state.csvData.forEach(row => {
                    let targetKey = state.isNacional ? row.CVE_ENT : row.CVE_MUN;
                    if (!state.isNacional && !targetKey) targetKey = row.CVE_ENT;
                    if (targetKey) state.dataMap.set(targetKey, row);
                });
                app.data.calcularSumaExactaEnfermeras(container, state);
                app.data.calcularTotalHeader(container, state);
            }

            container._siarhe = state;

            // Callback centralizado para actualizaciones en cadena (Selector Métrica)
            const onMetricUpdate = () => {
                if (state.syncComparar) state.syncComparar();   // «Comparar con» no aplica a la población ni a las localidades
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
            }

            // El overlay se oculta hasta que todo se haya construido (antes se ocultaba antes de renderizar)
            if (loading) loading.style.display = 'none';
            container.classList.add('siarhe-cargado'); // termina el esqueleto de carga

            // Centrado óptico de los textos del panel con la fuente del sitio (y otra vez cuando terminen de cargar las fuentes)
            app.centraTextos(container);
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(function() { app.centraTextos(container); });

            // Vista detalle y marcadores guardados en la URL (necesitan los controles ya renderizados)
            if (app.urlState) app.urlState.applyDeferred(container, state);
        })
        .catch(err => {
            console.error("[SIARHE Core] Falla en la red o parseo:", err);
            if(loading) loading.innerHTML = "❌ Error en la red o lectura de datos.";
            container.classList.add('siarhe-cargado'); // el aviso de error sustituye al esqueleto
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

    app.layout.init(wrappers);
    app.ensureD3(
        function() {
            // Un enlace copiado dentro de un estado (`siarhe-en=28`) abre la página y entra a ese estado
            const entra = wrappers.length === 1 && app.nav.disponible() ? app.nav.delEnlace() : '';
            wrappers.forEach(container => {
                if (entra && entra !== container.dataset.cveEnt) {
                    const resto = (window.location.hash || '').replace(/^#/, '');
                    app.nav.ir(container, entra).then(function(nuevo) {
                        if (nuevo) { try { window.history.replaceState(null, '', '#' + resto); } catch (e) {} }
                        else app.initVisualization(container);
                    });
                } else app.initVisualization(container);
            });
        },
        function() {
            wrappers.forEach(container => {
                const loading = container.querySelector('.siarhe-loading-overlay');
                if (loading) loading.innerHTML = '❌ Error: no se pudo cargar la librería de gráficos (D3.js).';
                container.classList.add('siarhe-cargado');
            });
        }
    );
});