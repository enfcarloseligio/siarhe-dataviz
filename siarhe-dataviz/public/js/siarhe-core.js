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
            return { shape: 'circle', fill: '#000000', stroke: '#ffffff' }; 
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

        findRateKeyForAbsolute: function(absKey, metricasConfig) {
            const foundRate = Object.keys(metricasConfig).find(k => 
                metricasConfig[k].pair === absKey && metricasConfig[k].tipo === 'tasa'
            );
            return foundRate || null;
        }
    };

    // ==========================================
    // ☀️ 3. MOTOR MATEMÁTICO DE RANGOS
    // ==========================================
    app.math = {
        isInRange: function(valToEval, minRaw, op, maxRaw, statsDict) {
            const parseVal = (raw) => {
                if (raw === undefined || raw === null || raw === '') return NaN; 
                let v = raw.toString().trim();
                const key = Object.keys(statsDict).find(k => k.toLowerCase() === v.toLowerCase());
                return key ? statsDict[key] : parseFloat(v);
            };

            const minVal = parseVal(minRaw);
            const maxVal = parseVal(maxRaw);

            if (isNaN(minVal) || isNaN(maxVal)) return false;

            let lowerBoundMet = valToEval >= minVal; 
            let upperBoundMet = false;
            const operator = (op || '<').toString().trim();
            
            // 🌟 MATEMÁTICA ESTRICTA (Sin parches, respeta lo que ponga el usuario o el sistema)
            if (operator === '<') upperBoundMet = valToEval < maxVal;
            else if (operator === '<=') upperBoundMet = valToEval <= maxVal;
            else if (operator === '=') upperBoundMet = valToEval === maxVal;
            else upperBoundMet = valToEval < maxVal; // Fallback

            return lowerBoundMet && upperBoundMet;
        },

        createRangeEvaluator: function(metricConfig, statsDict) {
            return function(value) {
                if (value === null || value === undefined || isNaN(value)) return app.colors.NULL;
                if (value === 0) return app.colors.ZERO;

                // 🌟 LÓGICA CORREGIDA: Si el interruptor de colores personalizados está apagado, forzamos los colores por defecto
                const useColors = metricConfig.custom_colors === true;
                const c1 = (useColors && metricConfig.r1_color) ? metricConfig.r1_color : app.colors.CUSTOM_RANGE[0];
                const c2 = (useColors && metricConfig.r2_color) ? metricConfig.r2_color : app.colors.CUSTOM_RANGE[1];
                const c3 = (useColors && metricConfig.r3_color) ? metricConfig.r3_color : app.colors.CUSTOM_RANGE[2];
                const c4 = (useColors && metricConfig.r4_color) ? metricConfig.r4_color : app.colors.CUSTOM_RANGE[3];

                // 🌟 LÓGICA CORREGIDA: Si el interruptor de rangos está apagado, forzamos los valores puros
                const useRanges = metricConfig.custom_ranges === true;

                const r4_min = (useRanges && metricConfig.r4_min) ? metricConfig.r4_min : 'Q3'; 
                const r4_op  = (useRanges && metricConfig.r4_op)  ? metricConfig.r4_op  : '<='; // 🌟 Matemáticamente correcto
                const r4_max = (useRanges && metricConfig.r4_max) ? metricConfig.r4_max : 'Vmax';
                
                const r3_min = (useRanges && metricConfig.r3_min) ? metricConfig.r3_min : 'Q2'; 
                const r3_op  = (useRanges && metricConfig.r3_op)  ? metricConfig.r3_op  : '<';  
                const r3_max = (useRanges && metricConfig.r3_max) ? metricConfig.r3_max : 'Q3';
                
                const r2_min = (useRanges && metricConfig.r2_min) ? metricConfig.r2_min : 'Q1'; 
                const r2_op  = (useRanges && metricConfig.r2_op)  ? metricConfig.r2_op  : '<';  
                const r2_max = (useRanges && metricConfig.r2_max) ? metricConfig.r2_max : 'Q2';
                
                const r1_min = (useRanges && metricConfig.r1_min) ? metricConfig.r1_min : 'Vmin'; 
                const r1_op  = (useRanges && metricConfig.r1_op)  ? metricConfig.r1_op  : '<';  
                const r1_max = (useRanges && metricConfig.r1_max) ? metricConfig.r1_max : 'Q1';

                // Se evalúa de arriba hacia abajo
                if (app.math.isInRange(value, r4_min, r4_op, r4_max, statsDict)) return c4;
                if (app.math.isInRange(value, r3_min, r3_op, r3_max, statsDict)) return c3;
                if (app.math.isInRange(value, r2_min, r2_op, r2_max, statsDict)) return c2;
                if (app.math.isInRange(value, r1_min, r1_op, r1_max, statsDict)) return c1;

                return app.colors.NULL; 
            };
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

            const pairKey = info.pair || state.currentMetric;
            let totalAbs = 0, valorMuestra = null;
            
            const rowTotal = state.csvData.find(r => r.isTotal);
            if (rowTotal) { 
                totalAbs = rowTotal[pairKey] || 0; 
                valorMuestra = rowTotal[state.currentMetric]; 
            } else {
                state.csvData.forEach(row => { 
                    if (!row.isTotal && !row.isSpecial && state.metricas[pairKey] && state.metricas[pairKey].tipo === 'absoluto') {
                        totalAbs += (row[pairKey] || 0); 
                    }
                });
                valorMuestra = (info.tipo === 'tasa') ? null : totalAbs;
            }
            
            const absFmt = totalAbs.toLocaleString('es-MX');
            const valFmt = (valorMuestra === null || valorMuestra === undefined) ? '—' : valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 });
            
            headerDiv.innerHTML = info.tipo === 'tasa' 
                ? `<span style="margin-right:15px;">${info.fullLabel}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span><span>Enfermeras: <strong style="color:#0A66C2; font-size:1.2em;">${absFmt}</strong></span>`
                : `<span>${info.fullLabel}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span>`;
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
        // 🌟 NUEVO: LECTURA DE DICCIONARIO DINÁMICO DE MARCADORES 🌟
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
            colorMode: null, // ☀️ Se arranca en null porque Controls define la escala
            zoom: null, gLegend: null, gMarkerLegend: null, gradientId: null,
            svg: null, gMain: null, gPaths: null, gLabels: null, gMarkers: null, 
            activeMarkers: new Set(), markersData: {}, 
            markerStyles: MARCADOR_ESTILOS, markerUrls: MARCADOR_URLS,
            markerLabels: MARCADOR_LABELS, // 🌟 GUARDADO EN EL ESTADO
            entityUrls: ENTITY_URLS, homeUrl: container.dataset.homeUrl || '', 
            isNacional: isNacional, 
            tooltip: null, markerTrigger: null, lastClickTime: 0,
            // 🌟 NUEVO: Caché para MÚLTIPLES archivos base
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
            if(loading) loading.style.display = 'none';
            state.geoData = geo;
            
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
    console.log("%c 🚀 SIARHE Core V48: Leyenda Estricta <= ", "background: #1e40af; color: #ffffff; padding: 2px 6px; border-radius: 4px;");
    
    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    wrappers.forEach(container => {
        window.SiarheDataViz.initVisualization(container);
    });
});