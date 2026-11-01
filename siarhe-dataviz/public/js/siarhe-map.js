/**
 * SIARHE DataViz - Módulo de Mapa (Map & D3)
 * ------------------------------------------------------------------
 * Gestiona la representación visual de los datos GeoJSON y CSV usando D3.js.
 * Se encarga del renderizado de los polígonos, la lógica de colores,
 * el sistema de zoom elástico y la exportación a PNG.
 * (Los tooltips ahora se gestionan en siarhe-tooltips.js)
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.map = {
        
        hexToRgba: function(hex, alpha) {
            let c;
            if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
                c= hex.substring(1).split('');
                if(c.length== 3){
                    c= [c[0], c[0], c[1], c[1], c[2], c[2]];
                }
                c= '0x'+c.join('');
                return 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+','+alpha+')';
            }
            if(hex.startsWith('rgba') || hex.startsWith('rgb')) return hex;
            return `rgba(15, 23, 42, ${alpha})`; 
        },

        /**
         * Coloca y escala las dos leyendas (colores arriba-izquierda, marcadores abajo-izquierda).
         * El texto se mantiene legible en pantalla (>= ~11 px reales) sin que las cajas tapen el mapa:
         * la escala se limita por ancho y por alto del contenedor.
         */
        layoutLegends: function(container, state) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv || !state.gLegend || !state.gMarkerLegend) return;

            const rect = mapDiv.getBoundingClientRect();
            // Proteccion contra division por cero si el mapa esta oculto (otra pestana)
            if (rect.width <= 0 || rect.height <= 0) return;

            // Margen unico a los bordes para TODO lo flotante (leyendas, zoom, panel de controles, medicion):
            // 2.5 % del lado menor del mapa en movil, tablet y PC, nunca menor a 5 px. El CSS lo lee con var(--siarhe-edge)
            const edgePx = Math.max(5, Math.round(Math.min(rect.width, rect.height) * 0.025));
            container.style.setProperty('--siarhe-edge', edgePx + 'px');

            const svgW = 1600;
            const svgH = 900;

            const s =Math.min(rect.width / svgW, rect.height / svgH);
            if (s <= 0) return;

            // Margenes vacios del SVG (letterbox) en unidades del viewBox: las leyendas se anclan a las esquinas fisicas
            const emptyX = (rect.width - svgW * s) / 2;
            const emptyY = (rect.height - svgH * s) / 2;
            const left = -(emptyX / s);
            const top = -(emptyY / s);
            const bottom = svgH + (emptyY / s);

            const isFullscreen = mapDiv.classList.contains('is-fullscreen');
            const isMobile = window.innerWidth <= 767;
            const padPx = edgePx;
            const padX = padPx / s;
            const padY = padPx / s;

            const lb = state._legendBox || { w: 175, h: 284, ox: 12, oy: 32 };
            const hasMarkers = !!(state._markerBox && state.activeMarkers && state.activeMarkers.size > 0);
            const mb = hasMarkers ? state._markerBox : { w: 0, h: 0, ox: 12 };

            // Tamano de texto objetivo en pantalla (px reales); el texto base de las leyendas es de 15 unidades
            const targetPx = isMobile ? 10 : 11.5;
            let L = Math.max(1, targetPx / (15 * s));

            // Limites fisicos: alto combinado <= 80 % y ancho <= 46 % (60 % en pantalla completa) del contenedor
            const capH = (0.8 * rect.height) / ((lb.h + mb.h) * s);
            const capW = ((isFullscreen ? 0.6 : 0.46) * rect.width) / (Math.max(lb.w, mb.w) * s);
            L = Math.max(0.5, Math.min(L, capH, capW));

            state.gLegend.attr("transform", `translate(${left + padX + lb.ox * L}, ${top + padY + lb.oy * L}) scale(${L})`);
            state.gMarkerLegend.attr("transform", `translate(${left + padX + mb.ox * L}, ${bottom - padY}) scale(${L})`);
        },

        initResponsiveLegends: function(container, state) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv) return;

            const observer = new ResizeObserver(() => { app.map.layoutLegends(container, state); app.map._onMapResize(state); });
            observer.observe(mapDiv);
            state.legendObserver = observer;
        },

        render: function(container, state, cveEnt) {
            state.containerEl = container; // lo usan las leyendas para reacomodarse al cambiar su contenido
            // Sin geometrias no hay nada que dibujar: avisamos en vez de romper el render
            if (!state.geoData || !Array.isArray(state.geoData.features) || state.geoData.features.length === 0) {
                const emptyDiv = container.querySelector('.siarhe-map-container');
                if (emptyDiv) emptyDiv.textContent = 'No hay datos geográficos disponibles para esta vista.';
                return;
            }
            
            // CSS 

            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv) return;
            mapDiv.innerHTML = '';
            state._cv = null; // el canvas de marcadores se vuelve a crear con el nuevo contenedor

            const width = 1600; 
            const height = 900; 

            const svg = d3.select(mapDiv).append("svg")
                .attr("viewBox", `0 0 ${width} ${height}`)
                .attr("preserveAspectRatio", "xMidYMid meet")
                .style("width", "100%")
                .style("height", "100%")
                .style("background-color", "#e6f0f8") 
                .style("font-family", "'Roboto', Arial, sans-serif"); 
            
            state.svg = svg;

            const defs = svg.append("defs");
            state.gradientId = `grad-${Math.random().toString(36).substr(2, 5)}`;
            defs.append("linearGradient")
                .attr("id", state.gradientId)
                .attr("x1", "0%").attr("y1", "100%")
                .attr("x2", "0%").attr("y2", "0%");

            const gMain = svg.append("g").attr("class", "map-layer"); 
            state.gMain = gMain;
            
            state.gPaths = gMain.append("g").attr("class", "paths-layer");
            state.gLocPaths = gMain.append("g").attr("class", "loc-paths-layer");
            
            state.gLabels = gMain.append("g").attr("class", "labels-layer").style("display", "none").style("pointer-events", "none");
            state.gMarkers = gMain.append("g").attr("class", "markers-layer");
            
            // Capa superior (leyendas y medicion), con el mismo viewBox y ajuste que el SVG principal.
            // Va por encima del canvas de marcadores (si lo hay) para que las leyendas y la medicion no queden tapadas.
            const ovl = d3.select(mapDiv).append("svg")
                .attr("class", "siarhe-overlay-svg")
                .attr("viewBox", `0 0 ${width} ${height}`)
                .attr("preserveAspectRatio", "xMidYMid meet")
                .style("font-family", "'Roboto', Arial, sans-serif");
            state.ovl = ovl;
            state.gMeasureHost = ovl.append("g").attr("class", "siarhe-overlay-main"); // sigue el zoom del mapa

            // Grupos SVG con posición inicial segura (el ResizeObserver lo moverá de inmediato)
            state.gLegend = ovl.append("g").attr("class", "siarhe-legend-group").attr("transform", "translate(20, 20)"); 
            state.gMarkerLegend = ovl.append("g").attr("class", "siarhe-marker-legend-group").attr("transform", `translate(20, ${height - 20})`);

            const projection = d3.geoMercator().fitSize([width, height], state.geoData); 
            state.projection = projection;
            const path = d3.geoPath().projection(projection); 
            // Menos decimales por coordenada = cadenas 'd' mucho mas cortas (miles de poligonos)
            if (typeof path.digits === 'function') path.digits(2);
            state.path = path; 

            let initialTransform = d3.zoomIdentity;
            if (cveEnt === '06' || cveEnt === '31') {
                let lons = [], lats = [];
                state.geoData.features.forEach(f => {
                    let center = d3.geoCentroid(f);
                    if (!isNaN(center[0]) && !isNaN(center[1])) { 
                        lons.push(center[0]); 
                        lats.push(center[1]); 
                    }
                });
                lons.sort(d3.ascending); 
                lats.sort(d3.ascending);
                
                if (lons.length > 0 && lats.length > 0) {
                    let medianLon = lons[Math.floor(lons.length / 2)];
                    let medianLat = lats[Math.floor(lats.length / 2)];
                    let targetK = cveEnt === '06' ? 5.5 : 1.6; 
                    let [x, y] = projection([medianLon, medianLat]);
                    let tx = width / 2 - x * targetK;
                    let ty = height / 2 - y * targetK;
                    initialTransform = d3.zoomIdentity.translate(tx, ty).scale(targetK);
                }
            }
            state.initialTransform = initialTransform;

            d3.selectAll(mapDiv.querySelectorAll(".siarhe-tooltip, .siarhe-stack-card")).remove();
            state.tooltip = d3.select(mapDiv).append("div")
                .attr("class", "siarhe-tooltip")
                .style("opacity", 0)
                .style("display", "none")
                .style("position", "absolute")
                .style("pointer-events", "none")
                .style("z-index", "9999");

            state.gPaths.selectAll("path.siarhe-feature")
                .data(state.geoData.features).enter().append("path")
                .attr("d", path).attr("class", "siarhe-feature")
                .attr("stroke", "#fff").attr("stroke-width", "0.5")
                .style("fill", "#e6f0f8")
                .style("cursor", "pointer")
                .on("mouseover", (e, d) => {
                    if(app.tooltips) app.tooltips.showGeoTooltip(e, d, state, mapDiv);
                }) 
                .on("mousemove", (e) => {
                    const [mx, my] = d3.pointer(e, mapDiv);
                    if (app.tooltips) app.tooltips.position(state, mapDiv, mx, my);
                })
                .on("mouseout", () => { 
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    state.gPaths.selectAll("path.siarhe-feature").attr("stroke", "#fff").attr("stroke-width", 0.5 / k); 
                    if (state.tooltip) state.tooltip.style("opacity", 0).style("display", "none"); 
                })
                .on("click", (e, d) => {
                    const ahora = Date.now();
                    if (ahora - (state.lastClickTime || 0) < 400) { 
                        app.map.handleMapClick(d, state); 
                    } else { 
                        if(app.tooltips) app.tooltips.showGeoTooltip(e, d, state, mapDiv); 
                    }
                    state.lastClickTime = ahora;
                });

            app.map.enableKeyboard(container, state, mapDiv);
            app.map.stackBind(state);

            let maxZoom = state.initialTransform.k * 25; 
            if (maxZoom < 50) maxZoom = 50;
            // Mapa nacional: 50x deja ~84 m por pixel en PC y ~223 m en movil, poco para separar establecimientos de una
            // ciudad. Se amplia hasta ~30 m por pixel (tope 400x); los mapas estatales ya parten de un acercamiento mayor.
            try {
                const gb = d3.geoBounds(state.geoData);
                const latMid = (gb[0][1] + gb[1][1]) / 2;
                const metersPerUnit = 111320 * Math.cos(latMid * Math.PI / 180) / (projection.scale() * Math.PI / 180);
                const mr = mapDiv.getBoundingClientRect();
                const scr = (mr.width > 0 && mr.height > 0) ? Math.min(mr.width / width, mr.height / height) : 0.5;
                const byResolution = Math.round(metersPerUnit / (30 * Math.max(scr, 0.2)));
                if (isFinite(byResolution)) maxZoom = Math.max(maxZoom, Math.min(400, byResolution));
            } catch (err) {}
            const zoom = d3.zoom()
                .scaleExtent([1, maxZoom]) 
                .on("zoom", (e) => {
                    gMain.attr("transform", e.transform);
                    if (state.gMeasureHost) state.gMeasureHost.attr("transform", e.transform);
                    if (state._stack && e.sourceEvent) app.map.stackClose(state);   // el panel esta anclado en pantalla, no al mapa
                    // Animaciones programadas (Restablecer, botones + y -): el canvas se dibuja en el mismo cuadro que el mapa;
                    // si se agendara para el siguiente, los marcadores irian un paso atras durante el movimiento.
                    // Con el puntero o el dedo (sourceEvent) se agrupa en un solo dibujo por cuadro.
                    if (e.sourceEvent) app.map._cvSchedule(state); else app.map._cvDraw(state);
                    const k = e.transform.k;
                    
                    state.gLabels.selectAll("text.siarhe-label")
                        .style("font-size", `${14 / k}px`)
                        .attr("stroke-width", 2.5 / k);
                    
                    state.gPaths.selectAll("path.siarhe-feature").attr("stroke-width", 0.5 / k);
                    if (state.isGeoLocMode && state._locBuilt) {
                        app.map._locApplyLod(state, k);
                        if (state._locStrokeK !== k) { // un desplazamiento (misma escala) no toca los miles de bordes
                            state._locStrokeK = k;
                            state.gLocPaths.selectAll("path.siarhe-loc-feature").attr("stroke-width", 0.5 / k);
                        }
                    }

                    // Geometria de marcadores (posicion, espectro y spike): solo los que estan en pantalla
                    app.map._refreshMarkerGeometry(state, e.transform);

                    if (state.measure && state.measure.points.length > 0) app.map.renderMeasure(state, k);
                });
            
            svg.call(zoom).on("dblclick.zoom", null); 
            state.zoom = zoom;
            svg.call(zoom.transform, state.initialTransform);
            
            app.map.renderZoomButtons(container, mapDiv, svg, zoom, state); 
            app.map.updateVisuals(container, state);

            // 🌟 Inicializamos el observador responsivo
            app.map.initResponsiveLegends(container, state);
        },

        /**
         * Registra una sola vez los eventos de la capa de localidades/municipios por delegacion
         * (antes: 3 closures por poligono, decenas de miles en entidades grandes).
         */
        _bindLocEvents: function(layer, state, mapDiv) {
            if (state._locEventsBound) return;
            state._locEventsBound = true;

            const isLoc = (t) => t && t.classList && t.classList.contains('siarhe-loc-feature');
            const zoomK = () => {
                let k = 1;
                if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch (err) {} }
                return k;
            };

            layer
                .on("mouseover", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    d3.select(t).attr("stroke", "#ffffff").attr("stroke-width", 1.5 / zoomK());
                    if (app.tooltips) app.tooltips.showGeoTooltip(e, t.__data__, state, mapDiv);
                })
                .on("mousemove", function(e) {
                    if (!isLoc(e.target)) return;
                    const [mx, my] = d3.pointer(e, mapDiv);
                    if (app.tooltips) app.tooltips.position(state, mapDiv, mx, my);
                })
                .on("mouseout", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    d3.select(t).attr("stroke", "rgba(255, 255, 255, 0.4)").attr("stroke-width", 0.5 / zoomK());
                    if (state.tooltip) state.tooltip.style("opacity", 0).style("display", "none");
                })
                .on("click", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    const ahora = Date.now();
                    if (ahora - (state.lastClickTime || 0) < 400) {
                        app.map.handleMapClick(t.__data__, state);
                    } else if (app.tooltips) {
                        app.tooltips.showGeoTooltip(e, t.__data__, state, mapDiv);
                    }
                    state.lastClickTime = ahora;
                });
        },

        /**
         * Muestra u oculta la segunda vista (municipios / localidades).
         * Los poligonos se construyen UNA sola vez, en lotes por cuadro (sin congelar la pagina),
         * y despues solo se muestran con un fundido. Devuelve una Promise que se resuelve al terminar.
         * onProgress(pct) permite informar el avance en el overlay de carga.
         */
        /** Nivel de detalle de la capa de municipios segun el zoom: 0 (muy simplificado), 1 o 2 (completo). */
        _locLodFor: function(k) {
            return k < 2.5 ? 0 : (k < 10 ? 1 : 2);
        },

        /** Cambia el trazo de cada municipio al nivel que corresponde al zoom (solo si el nivel cambio o se fuerza). */
        _locApplyLod: function(state, k, force) {
            const data = state.locGeoData;
            if (!state.isGeoLocMode || !data || !data._prepared || state._locBuilt !== data || !state.gLocPaths) return;
            const lod = app.map._locLodFor(k);
            if (!force && state._locLod === lod) return;
            state._locLod = lod;
            const nodes = state.gLocPaths.node().children;
            for (let i = 0; i < nodes.length; i++) {
                const f = nodes[i].__data__;
                if (f && f.d) nodes[i].setAttribute("d", f.d[lod]);
            }
        },

        updateGeography: function(container, state, onProgress) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv || !state.gLocPaths || !state.path) return Promise.resolve();

            const layer = state.gLocPaths;

            if (!(state.isGeoLocMode && state.locGeoData)) {
                state._locBuildGen = (state._locBuildGen || 0) + 1; // cancela una construccion en curso
                layer.style("display", "none");
                return Promise.resolve();
            }

            const features = Array.isArray(state.locGeoData.features) ? state.locGeoData.features : [];
            if (features.length === 0) {
                layer.style("display", "none");
                return Promise.resolve();
            }

            app.map._bindLocEvents(layer, state, mapDiv);

            const zoomK = () => {
                let k = 1;
                if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch (e) {} }
                return k;
            };

            const reveal = () => {
                layer.style("display", "block").style("opacity", 0)
                    .transition().duration(app.utils.motionMs(400)).style("opacity", 1);
                layer.selectAll("path.siarhe-loc-feature").attr("stroke-width", 0.5 / zoomK());
                app.map._locApplyLod(state, zoomK(), true);   // el zoom pudo cambiar mientras la capa estaba oculta
            };

            // Ya construida: solo se vuelve a mostrar (antes se recalculaba el 'd' de todos los poligonos)
            if (state._locBuilt === state.locGeoData) {
                reveal();
                return Promise.resolve();
            }

            state._locBuildGen = (state._locBuildGen || 0) + 1;
            const gen = state._locBuildGen;
            layer.selectAll("*").remove();
            layer.interrupt().style("display", "block").style("opacity", 1);

            const ns = "http://www.w3.org/2000/svg";
            const layerNode = layer.node();
            const total = features.length;
            const strokeW = 0.5 / zoomK();
            // Capa preparada por el worker: ya viene proyectada, en tres niveles de detalle
            const prepared = !!state.locGeoData._prepared;
            const lod = prepared ? app.map._locLodFor(zoomK()) : 0;
            if (prepared) state._locLod = lod;
            let i = 0;

            return new Promise((resolve) => {
                const step = () => {
                    if (state._locBuildGen !== gen) { resolve(); return; }

                    const t0 = performance.now();
                    const frag = document.createDocumentFragment();
                    do {
                        const f = features[i++];
                        const dStr = f ? (prepared ? f.d[lod] : state.path(f)) : null;
                        if (dStr) {
                            const el = document.createElementNS(ns, "path");
                            el.setAttribute("class", "siarhe-loc-feature");
                            el.setAttribute("d", dStr);
                            el.setAttribute("stroke", "rgba(255, 255, 255, 0.4)");
                            el.setAttribute("stroke-width", strokeW);
                            el.style.fill = "transparent";
                            el.style.cursor = "pointer";
                            el.__data__ = f;
                            frag.appendChild(el);
                        }
                    } while (i < total && performance.now() - t0 < 10);
                    layerNode.appendChild(frag);

                    if (typeof onProgress === 'function') onProgress(Math.min(100, Math.round((i / total) * 100)));

                    if (i < total) {
                        requestAnimationFrame(step);
                    } else {
                        state._locBuilt = state.locGeoData; // los poligonos ya fueron apareciendo por lotes: sin fundido extra
                        resolve();
                    }
                };
                requestAnimationFrame(step);
            });
        },

        updateVisuals: function(container, state) {
            const metric = state.currentMetric;
            
            const metricConfig = state.metricas[metric] || {};
            const mode = state.colorMode || metricConfig.scale_type || 'cuartiles'; 
            const pal = app.palette(state);
            
            const values = state.csvData
                .filter(d => !d.isTotal && !d.isSpecial && d[metric] !== null && d[metric] > 0)
                .map(d => d[metric])
                .sort(d3.ascending);
            
            if (values.length === 0) {
                state.gPaths.selectAll("path.siarhe-feature").transition().duration(app.utils.motionMs(500))
                    .style("fill", d => {
                        let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                        const row = state.dataMap.get(cve);
                        if (!row || row[metric] === null || row[metric] === undefined) return pal.NULL;
                        return pal.ZERO;
                    });
                const emptyStats = {min: 0, q1: 0, q2: 0, q3: 0, max: 0, Vmin: 0, Q1: 0, Q2: 0, Q3: 0, Vmax: 0};
                app.map.renderLegend(state, emptyStats, mode);
            } else {
                let colorScale;
                let stats = {};

                const min = d3.min(values); 
                const max = d3.max(values);

                let q1 = d3.quantile(values, 0.25); 
                let q2 = d3.quantile(values, 0.50); 
                let q3 = d3.quantile(values, 0.75);
                let domain = [min, q1, q2, q3, max];
                
                if (min === max) {
                    domain = [min * 0.2, min * 0.4, min * 0.6, min * 0.8, max];
                } else {
                    for (let i = 1; i < domain.length; i++) {
                        if (domain[i] <= domain[i-1]) domain[i] = domain[i-1] + 0.000001; 
                    }
                }
                
                stats = { min, q1, q2, q3, max, Vmin: min, Q1: q1, Q2: q2, Q3: q3, Vmax: max };

                if (mode === 'rangos') {
                    const colorEvaluator = app.math.createRangeEvaluator(metricConfig, stats, pal);
                    
                    state.gPaths.selectAll("path.siarhe-feature").transition().duration(app.utils.motionMs(500))
                        .style("fill", d => {
                            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                            const row = state.dataMap.get(cve);
                            if (!row || row[metric] === null || row[metric] === undefined) return pal.NULL; 
                            return colorEvaluator(row[metric]);
                        });
                } else {
                    if (mode === 'cuartiles' || mode === 'degradado' || mode === 'quartiles') {
                        colorScale = d3.scaleLinear().domain(domain).range(pal.RANGE).clamp(true);
                    } else {
                        let monoRange = pal.MONO;
                        if (monoRange.length === 2) {
                            const interpolator = d3.interpolate(monoRange[0], monoRange[1]);
                            monoRange = [ interpolator(0), interpolator(0.25), interpolator(0.50), interpolator(0.75), interpolator(1) ];
                        }
                        colorScale = d3.scaleLinear().domain(domain).range(monoRange).clamp(true);
                    }

                    state.gPaths.selectAll("path.siarhe-feature").transition().duration(app.utils.motionMs(500))
                        .style("fill", d => {
                            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                            const row = state.dataMap.get(cve);
                            
                            if (!row || row[metric] === null || row[metric] === undefined) return pal.NULL; 
                            if (row[metric] === 0) return pal.ZERO; 
                            
                            return colorScale(row[metric]);
                        });
                }

                app.map.renderLegend(state, stats, mode);
            }
            app.map.renderLabels(state);
            app.map.updateAria(state);
            if (app.urlState) app.urlState.write(container, state);
        },

        renderLabels: function(state) {
            let currentK = 1;
            if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

            const baseGeoData = state.baseGeoData || state.geoData;
            if (!baseGeoData || !Array.isArray(baseGeoData.features)) return;

            // Area y centroide se calculan UNA vez por geometria (antes se repetian en cada cambio de metrica)
            if (!state._labelFeatures || state._labelFeaturesSrc !== baseGeoData) {
                const maxAreaFeatures = new Map();
                baseGeoData.features.forEach(d => {
                    let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                    let area = d3.geoArea(d);
                    if (!maxAreaFeatures.has(cve) || area > maxAreaFeatures.get(cve).area) {
                        maxAreaFeatures.set(cve, { feature: d, area: area });
                    }
                });
                state._labelFeatures = Array.from(maxAreaFeatures.values()).map(item => {
                    const c = state.path.centroid(item.feature);
                    return { feature: item.feature, x: isNaN(c[0]) ? 0 : c[0], y: isNaN(c[1]) ? 0 : c[1] };
                });
                state._labelFeaturesSrc = baseGeoData;
            }

            const labels = state.gLabels.selectAll("text.siarhe-label").data(state._labelFeatures);
            labels.exit().remove();

            labels.enter().append("text")
                .attr("class", "siarhe-label")
                .merge(labels)
                .attr("x", d => d.x)
                .attr("y", d => d.y)
                .attr("text-anchor", "middle")
                .attr("fill", "#0F172A")
                .attr("stroke", "#ffffff")
                .attr("stroke-width", 2.5 / currentK)
                .attr("paint-order", "stroke fill")
                .attr("stroke-linejoin", "round")
                .style("font-size", `${14 / currentK}px`)
                .style("font-weight", "bold")
                .text(d => {
                    let cve = app.utils.getGeoKey(d.feature.properties, state.isNacional);
                    const row = state.dataMap.get(cve);
                    return row ? row.estado : (d.feature.properties.NOMGEO || d.feature.properties.NOM_ENT || "");
                });
        },

        renderLegend: function(state, stats, mode) {
            const g = state.gLegend;
            const pal = app.palette(state);
            g.html("");
            g.style("font-family", "Roboto, Arial, sans-serif");

            const metricInfo = state.metricas[state.currentMetric] || {};
            const label = metricInfo.abrev || metricInfo.label || 'Indicador';
            const currentMode = mode || state.colorMode || 'cuartiles';
            const isRangos = (currentMode === 'rangos');

            // Tamanos en unidades del viewBox: layoutLegends() los escala segun pantalla o PNG
            const FT = 17;   // titulo
            const FL = 15;   // etiquetas
            const CH = 8.4;  // ancho aproximado por caracter (para dimensionar la caja)
            const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
            const addLabel = (parent, x, y, txt) => parent.append("text").attr("x", x).attr("y", y).text(txt)
                .style("font-size", FL + "px").style("fill", "#475569");
            const titleW = label.length * 10.2 + 28;

            let boxW, boxH;

            if (isRangos) {
                const useColors = !pal.custom && metricInfo.custom_colors === true;
                const c1 = (useColors && metricInfo.r1_color) ? metricInfo.r1_color : pal.CUSTOM_RANGE[0];
                const c2 = (useColors && metricInfo.r2_color) ? metricInfo.r2_color : pal.CUSTOM_RANGE[1];
                const c3 = (useColors && metricInfo.r3_color) ? metricInfo.r3_color : pal.CUSTOM_RANGE[2];
                const c4 = (useColors && metricInfo.r4_color) ? metricInfo.r4_color : pal.CUSTOM_RANGE[3];

                const parseLabel = (raw) => {
                    if (raw === undefined || raw === null || raw === '') return '';
                    let v = raw.toString().trim();
                    const key = Object.keys(stats).find(k => k.toLowerCase() === v.toLowerCase());

                    let numToFormat = key ? stats[key] : parseFloat(v);

                    if (!isNaN(numToFormat)) {
                        if (metricInfo.tipo === 'tasa') {
                            return numToFormat.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        } else {
                            return numToFormat.toLocaleString('es-MX', {maximumFractionDigits: 2});
                        }
                    }
                    return v;
                };

                const formatOp = (op) => op === '<=' ? '≤' : op;
                const useRanges = metricInfo.custom_ranges === true;

                const r4min = (useRanges && metricInfo.r4_min) ? metricInfo.r4_min : 'Q3';
                const r4max = (useRanges && metricInfo.r4_max) ? metricInfo.r4_max : 'Vmax';
                const r4op  = formatOp((useRanges && metricInfo.r4_op) ? metricInfo.r4_op : '<=');

                const r3min = (useRanges && metricInfo.r3_min) ? metricInfo.r3_min : 'Q2';
                const r3max = (useRanges && metricInfo.r3_max) ? metricInfo.r3_max : 'Q3';
                const r3op  = formatOp((useRanges && metricInfo.r3_op) ? metricInfo.r3_op : '<');

                const r2min = (useRanges && metricInfo.r2_min) ? metricInfo.r2_min : 'Q1';
                const r2max = (useRanges && metricInfo.r2_max) ? metricInfo.r2_max : 'Q2';
                const r2op  = formatOp((useRanges && metricInfo.r2_op) ? metricInfo.r2_op : '<');

                const r1min = (useRanges && metricInfo.r1_min) ? metricInfo.r1_min : 'Vmin';
                const r1max = (useRanges && metricInfo.r1_max) ? metricInfo.r1_max : 'Q1';
                const r1op  = formatOp((useRanges && metricInfo.r1_op) ? metricInfo.r1_op : '<');

                const blocks = [
                    { color: c4, text: `${parseLabel(r4min)} a ${r4op} ${parseLabel(r4max)}` },
                    { color: c3, text: `${parseLabel(r3min)} a ${r3op} ${parseLabel(r3max)}` },
                    { color: c2, text: `${parseLabel(r2min)} a ${r2op} ${parseLabel(r2max)}` },
                    { color: c1, text: `${parseLabel(r1min)} a ${r1op} ${parseLabel(r1max)}` }
                ];

                const maxLen = Math.max.apply(null, blocks.map(b => b.text.length));
                boxW = clamp(Math.max(34 + maxLen * CH + 28, titleW), 190, 380);
                boxH = 250;

                g.append("rect").attr("x", -12).attr("y", -32).attr("width", boxW).attr("height", boxH)
                 .attr("fill", "rgba(255,255,255,0.92)").attr("rx", 6).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.12))");
                g.append("text").attr("x", 0).attr("y", -8).text(label)
                 .style("font-size", FT + "px").style("font-weight", "bold").style("fill", "#0F172A");

                blocks.forEach((b, i) => {
                    const y = 12 + (i * 36);
                    g.append("rect").attr("x", 0).attr("y", y).attr("width", 24).attr("height", 24)
                     .style("fill", b.color).style("stroke", "#cbd5e1");
                    addLabel(g, 34, y + 18, b.text);
                });

                const g0 = g.append("g").attr("transform", `translate(0, ${12 + (4 * 36) + 8})`);
                g0.append("rect").attr("width", 16).attr("height", 16).style("fill", pal.ZERO);
                addLabel(g0, 26, 13, "0.00");

                const gN = g.append("g").attr("transform", `translate(0, ${12 + (4 * 36) + 36})`);
                gN.append("rect").attr("width", 16).attr("height", 16).style("fill", pal.NULL);
                addLabel(gN, 26, 13, "S/D");

            } else {
                const gradient = state.svg.select(`#${state.gradientId}`);
                gradient.selectAll("stop").remove();

                let colorArr = [];
                let domainVals = [stats.min, stats.q1, stats.q2, stats.q3, stats.max];

                if (currentMode === 'cuartiles' || currentMode === 'degradado' || currentMode === 'quartiles') {
                    colorArr = pal.RANGE;
                } else {
                    colorArr = pal.MONO;
                    if (colorArr.length === 2) {
                        const interpolator = d3.interpolate(colorArr[0], colorArr[1]);
                        colorArr = [ interpolator(0), interpolator(0.25), interpolator(0.50), interpolator(0.75), interpolator(1) ];
                    }
                }

                gradient.selectAll("stop")
                    .data(colorArr)
                    .enter().append("stop")
                    .attr("offset", (d, i) => `${(i / (colorArr.length - 1)) * 100}%`)
                    .attr("stop-color", d => d);

                const valStrs = domainVals.map(v => (v !== undefined && v !== null)
                    ? v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : "0.00");
                const maxLen = Math.max.apply(null, valStrs.map(t => t.length));

                const h = 160, w = 20, step = h / 4;
                boxW = clamp(Math.max(w + 12 + maxLen * CH + 28, titleW), 160, 340);
                boxH = 284;

                g.append("rect").attr("x", -12).attr("y", -32).attr("width", boxW).attr("height", boxH)
                 .attr("fill", "rgba(255,255,255,0.92)").attr("rx", 6).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.12))");
                g.append("text").attr("x", 0).attr("y", -8).text(label)
                 .style("font-size", FT + "px").style("font-weight", "bold").style("fill", "#0F172A");

                g.append("rect").attr("x", 0).attr("y", 12).attr("width", w).attr("height", h)
                 .style("fill", `url(#${state.gradientId})`).style("stroke", "#ccc");

                domainVals.forEach((v, i) => {
                    const y = 12 + h - (i * step);
                    g.append("line").attr("x1", w).attr("x2", w + 6).attr("y1", y).attr("y2", y).style("stroke", "#666");
                    addLabel(g, w + 10, y + 5, valStrs[i]);
                });

                const g0 = g.append("g").attr("transform", `translate(0, ${h + 34})`);
                g0.append("rect").attr("width", 16).attr("height", 16).style("fill", pal.ZERO);
                addLabel(g0, 26, 13, "0.00");

                const gN = g.append("g").attr("transform", `translate(0, ${h + 62})`);
                gN.append("rect").attr("width", 16).attr("height", 16).style("fill", pal.NULL);
                addLabel(gN, 26, 13, "S/D");
            }

            state._legendBox = { w: boxW, h: boxH, ox: 12, oy: 32 };
            if (state.containerEl) app.map.layoutLegends(state.containerEl, state);
        },

        /**
         * Navegacion por teclado del mapa (un solo tab stop con "roving tabindex"):
         * flechas recorren las geometrias, Enter abre el detalle, Escape cierra la informacion.
         */
        enableKeyboard: function(container, state, mapDiv) {
            const svg = state.svg;
            if (!svg || !state.gPaths) return;

            const hintId = 'siarhe-kbd-' + Math.random().toString(36).slice(2, 8);
            const unidad = state.isNacional ? 'las entidades federativas' : 'los municipios';
            const hint = document.createElement('p');
            hint.className = 'siarhe-sr-only';
            hint.id = hintId;
            hint.textContent = 'Mapa interactivo. Con el foco en el mapa use las flechas para recorrer ' + unidad +
                ', Enter para abrir el detalle y Escape para cerrar la información.';
            mapDiv.appendChild(hint);

            svg.attr('role', 'group')
               .attr('aria-label', 'Mapa de ' + (state.isNacional ? 'entidades federativas' : 'municipios'))
               .attr('aria-describedby', hintId);

            const nodes = state.gPaths.selectAll('path.siarhe-feature').nodes();
            if (nodes.length === 0) return;
            nodes.forEach((n, i) => n.setAttribute('tabindex', i === 0 ? '0' : '-1'));
            let current = nodes[0];

            const focusNode = (n) => {
                current.setAttribute('tabindex', '-1');
                n.setAttribute('tabindex', '0');
                current = n;
                n.focus();
            };
            const hideTip = () => { if (state.tooltip) state.tooltip.style('opacity', 0).style('display', 'none'); };

            const gNode = state.gPaths.node();

            gNode.addEventListener('keydown', (e) => {
                const idx = nodes.indexOf(e.target);
                if (idx < 0) return;

                let next = null;
                switch (e.key) {
                    case 'ArrowRight': case 'ArrowDown': next = nodes[Math.min(idx + 1, nodes.length - 1)]; break;
                    case 'ArrowLeft': case 'ArrowUp': next = nodes[Math.max(idx - 1, 0)]; break;
                    case 'Home': next = nodes[0]; break;
                    case 'End': next = nodes[nodes.length - 1]; break;
                    case 'Enter': case ' ':
                        e.preventDefault();
                        app.map.handleMapClick(e.target.__data__, state);
                        return;
                    case 'Escape':
                        hideTip();
                        e.target.blur();
                        return;
                    default: return;
                }
                e.preventDefault();
                if (next) focusNode(next);
            });

            // La informacion (tooltip) aparece al enfocar con teclado, igual que con el hover del mouse
            gNode.addEventListener('focusin', (e) => {
                const t = e.target;
                if (nodes.indexOf(t) < 0 || !app.tooltips) return;
                let byKeyboard = true;
                try { byKeyboard = t.matches(':focus-visible'); } catch (err) {}
                if (!byKeyboard) return; // con clic de mouse ya lo muestra el hover
                const r = t.getBoundingClientRect();
                app.tooltips.showGeoTooltip({ clientX: r.left + r.width / 2, clientY: r.top + r.height / 2 }, t.__data__, state, mapDiv);
            });
            gNode.addEventListener('focusout', hideTip);
        },

        /** Etiqueta accesible por geometria ("Oaxaca: Tasa de enfermeras 2.31"); se refresca con la metrica. */
        updateAria: function(state) {
            if (!state.gPaths) return;
            const metric = state.currentMetric;
            const info = state.metricas[metric] || {};
            const metricLabel = info.fullLabel || info.label || '';

            state.gPaths.selectAll('path.siarhe-feature')
                .attr('role', state.isNacional ? 'button' : 'img')
                .attr('aria-label', d => {
                    const props = d.properties || {};
                    const row = state.dataMap.get(app.utils.getGeoKey(props, state.isNacional));
                    const name = row ? row.estado : (props.NOMGEO || props.NOM_ENT || props.NOM_MUN || 'Sin nombre');
                    const v = row ? row[metric] : null;
                    if (v === null || v === undefined || isNaN(v)) return name + ': sin datos';
                    return name + ': ' + metricLabel + ' ' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 2 });
                });
        },

        handleMapClick: function(d, state) {
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            if (state.entityUrls && state.entityUrls[cve]) {
                window.location.href = state.entityUrls[cve];
            }
        },

        /**
         * Actualiza la forma y el borde de los marcadores al cambiar el zoom, sin recorrer los miles que
         * quedan fuera de pantalla: con ~41 mil establecimientos esto era el costo principal de cada paso del zoom.
         * - Un desplazamiento (misma escala) no toca nada, salvo que queden marcadores pendientes por actualizar.
         * - Los marcadores fuera de la region visible se marcan como pendientes (node.__k distinto del zoom actual)
         *   y se actualizan cuando entran a la vista.
         * - Los de posicion comparten la misma forma por tipo: se calcula una vez y se reutiliza.
         */
        // =====================================================================
        // CANVAS DE MARCADORES DE POSICION (cuando hay muchos puntos)
        // =====================================================================
        CANVAS_MIN: 3000,   // con mas marcadores de posicion activos que esto se usa canvas
        CANVAS_MIN_TOUCH: 800, // en tactiles antes: el canvas da un area de toque de 16 px; un simbolo SVG chico es dificil de acertar

        /** ?siarhe_canvas=1 fuerza el canvas, =0 lo apaga; sin parametro es automatico. */
        _cvMode: function() {
            const m = /[?&]siarhe_canvas=(0|1)/.exec(window.location.search);
            return m ? (m[1] === '1' ? 'on' : 'off') : 'auto';
        },

        _cvDecide: function(state, points) {
            const mode = app.map._cvMode();
            if (mode === 'off') return false;
            try { if (!document.createElement('canvas').getContext('2d')) return false; } catch (e) { return false; }
            // Posicion, burbujas y picos se dibujan juntos en el mismo canvas (asi se conserva el orden de apilado)
            const min = app.map._deviceInfo(state).isTouch ? app.map.CANVAS_MIN_TOUCH : app.map.CANVAS_MIN;
            return mode === 'on' ? points.length > 0 : points.length > min;
        },

        /** Crea (una vez por mapa) el canvas, justo despues del SVG principal y antes de la capa superior. */
        _cvEnsure: function(state) {
            let cv = state._cv;
            if (cv && cv.el && cv.el.isConnected) return cv;
            const svgNode = state.svg && state.svg.node();
            if (!svgNode) return null;
            const el = document.createElement('canvas');
            el.className = 'siarhe-canvas-markers';
            el.setAttribute('aria-hidden', 'true');
            svgNode.insertAdjacentElement('afterend', el);
            cv = state._cv = { el: el, ctx: el.getContext('2d'), dpr: 1, w: 0, h: 0, pts: [], qt: null, alpha: 1, hover: null, raf: 0, moveRaf: 0, sig: '' };
            app.map._cvBind(state);
            app.map._cvResize(state);
            return cv;
        },

        _cvResize: function(state) {
            const cv = state._cv;
            if (!cv || !cv.el || !state.svg) return;
            const r = state.svg.node().getBoundingClientRect();
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            if (r.width <= 0 || r.height <= 0) return;
            if (cv.w !== r.width || cv.h !== r.height || cv.dpr !== dpr) {
                cv.w = r.width; cv.h = r.height; cv.dpr = dpr;
                cv.el.width = Math.round(r.width * dpr);
                cv.el.height = Math.round(r.height * dpr);
                cv.sprites = {};
            }
            app.map._cvSchedule(state);
        },

        /** Define los puntos del canvas (posicion, burbujas y picos): proyecta una vez, arma el indice espacial y los muestra con un fundido. */
        _cvSet: function(state, points) {
            if (!points.length && !state._cv) return;
            const cv = app.map._cvEnsure(state);
            if (!cv) return;

            const LEVELS = 32;      // niveles de color de los picos
            const spk = {};         // por tipo de pico: colores por nivel y color del borde
            const pts = [];
            let hasSpk = false, hasBub = false;
            for (let i = 0; i < points.length; i++) {
                const d = points[i];
                const c = state.projection ? state.projection([d.lon, d.lat]) : null;
                if (!c || isNaN(c[0]) || isNaN(c[1])) continue;
                const cfg = state.markerLabels[d.tipo] || {};
                const p = { d: d, x: c[0], y: c[1], i: pts.length, kind: 'pos' };

                if (cfg.tipo === 'spike' || cfg.tipo === 'espectro') {
                    const val = app.map._markerValue(d, cfg, state, d.tipo);
                    if (!(val > 0)) continue;                    // sin valor: no se dibuja (igual que en SVG)
                    const max = (state.espectroMaxVals && state.espectroMaxVals[d.tipo]) || 1;
                    if (cfg.tipo === 'spike') {
                        const min = (state.espectroMinVals && state.espectroMinVals[d.tipo]) || max;
                        p.kind = 'spk';
                        p.t = max > min ? Math.max(0, Math.min(1, (val - min) / (max - min))) : 1;
                        p.lv = Math.round(p.t * (LEVELS - 1));
                        if (!spk[d.tipo]) {
                            const st = app.utils.getMarkerStyle(d.tipo, state);
                            const ramp = app.map._spikeRamp(st);
                            const colors = [];
                            for (let l = 0; l < LEVELS; l++) colors.push(ramp(l / (LEVELS - 1)));
                            const col = d3.color(st.fill);
                            spk[d.tipo] = { colors: colors, edge: col ? col.darker(0.7).formatRgb() : '#7f1d1d' };
                        }
                        hasSpk = true;
                    } else {
                        // Posicion de la burbuja entre el area minima y la maxima (igual que d3.scaleLinear con dominio [1, max])
                        p.kind = 'bub';
                        p.frac = max === 1 ? 0.5 : Math.max(0, Math.min(1, (val - 1) / (max - 1)));
                        hasBub = true;
                    }
                }
                pts.push(p);
            }
            cv.pts = pts; cv.spk = spk; cv.hasSpk = hasSpk; cv.hasBub = hasBub;
            cv.qt = pts.length ? d3.quadtree().x(p => p.x).y(p => p.y).addAll(pts) : null;
            cv.hover = null; cv.sel = null;

            // Fundido de entrada solo si cambia el conjunto de puntos
            const sig = pts.length + ':' + (pts.length ? pts[0].d._uid + '|' + pts[pts.length - 1].d._uid : '');
            const changed = sig !== cv.sig;
            cv.sig = sig;
            const ms = app.utils.motionMs(450);
            if (changed && pts.length && ms > 0) {
                cv.alpha = 0;
                const t0 = performance.now();
                const step = () => {
                    cv.alpha = Math.min(1, (performance.now() - t0) / ms);
                    app.map._cvDraw(state);
                    if (cv.alpha < 1 && state._cv === cv) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            } else {
                cv.alpha = 1;
                app.map._cvSchedule(state);
            }
            if (state.tooltip && !pts.length) state.tooltip.style('display', 'none');
        },

        _cvSchedule: function(state) {
            const cv = state._cv;
            if (!cv || cv.raf) return;
            cv.raf = requestAnimationFrame(() => { cv.raf = 0; app.map._cvDraw(state); });
        },

        /** Geometria de la pantalla: pixeles CSS <-> unidades del viewBox (el SVG se ajusta con "meet"). */
        _cvView: function(state) {
            const cv = state._cv;
            const W = cv.w, H = cv.h;
            if (!W || !H) return null;
            let t;
            try { t = d3.zoomTransform(state.svg.node()); } catch (e) { return null; }
            const sc = Math.min(W / 1600, H / 900);
            return { t: t, k: t.k, sc: sc, ox: (W - 1600 * sc) / 2, oy: (H - 900 * sc) / 2, W: W, H: H };
        },

        /**
         * Medidas (en px de pantalla) de las burbujas y los picos con el zoom actual. Las burbujas escalan entre un area
         * minima y una maxima segun su valor; los picos miden de hMin a hMax segun el suyo y su base es fija.
         */
        _cvDensityDims: function(state, v) {
            const m = app.map._markerMetrics(state, v.k);
            const k2 = v.k * v.k * v.sc * v.sc;
            return {
                m: m,
                aMin: m.symbolSize * 2.5 * m.bubArea * k2, aMax: m.symbolSize * 25 * m.bubArea * k2,
                spkW: m.spk.half, spkMin: m.spk.hMin, spkMax: m.spk.hMax,
                spkHit: m.isTouch ? 9 : 5,           // semi-base minima del area de toque de una espina (px)
                spkStroke: m.strokePx / 1.5,         // los picos usan un borde de 1 (los demas 1.5)
            };
        },

        /** Imagen pequena de un simbolo (relleno y borde), para repetirla miles de veces con drawImage. */
        _cvSprite: function(state, type, areaPx, sc, dpr, bubble) {
            const cv = state._cv;
            const key = type + '|' + (bubble ? 'b' : 'p') + '|' + areaPx.toFixed(2) + '|' + sc.toFixed(4) + '|' + dpr;
            if (!cv.sprites) cv.sprites = {};
            if (cv.sprites[key]) return cv.sprites[key];
            if (Object.keys(cv.sprites).length > 160) cv.sprites = {};   // el zoom genera tamanos nuevos continuamente

            const style = app.utils.getMarkerStyle(type, state);
            const fill = bubble ? (style.fill.startsWith('rgba') ? style.fill : app.map.hexToRgba(style.fill, 0.75)) : style.fill;
            const lw = app.map._strokePx(sc);
            const side = Math.ceil(Math.sqrt(areaPx) * 1.8 + lw * 2 + 4);
            const img = document.createElement('canvas');
            img.width = Math.max(1, Math.ceil(side * dpr)); img.height = img.width;
            const g = img.getContext('2d');
            g.scale(dpr, dpr);
            g.translate(side / 2, side / 2);
            const path = new Path2D(d3.symbol().type(app.utils.getD3Shape(style.shape)).size(areaPx)());
            g.fillStyle = fill; g.strokeStyle = style.stroke; g.lineWidth = lw;
            g.fill(path); g.stroke(path);
            return (cv.sprites[key] = { img: img, side: side, path: path, style: style, fill: fill, lw: lw });
        },

        /** Area (px^2) cuantizada de una burbuja: pasos de ~6 %, para reutilizar imagenes en lugar de una por punto. */
        _cvBubbleArea: function(dims, frac) {
            const a = dims.aMin + (dims.aMax - dims.aMin) * frac;
            const idx = Math.max(0, Math.round(Math.log(a / 4) / 0.05827));    // ln(1.06)
            return { idx: idx, area: 4 * Math.pow(1.06, idx) };
        },

        _cvTriangle: function(ctx, sx, sy, w, h) {
            ctx.beginPath();
            ctx.moveTo(sx - w, sy); ctx.lineTo(sx, sy - h); ctx.lineTo(sx + w, sy);
            ctx.closePath();
        },

        _cvDraw: function(state) {
            const cv = state._cv;
            if (!cv || !cv.el) return;
            const t0 = performance.now();
            const ctx = cv.ctx, dpr = cv.dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, cv.w, cv.h);
            if (!cv.pts.length) return;
            const v = app.map._cvView(state);
            if (!v) return;

            const dims = app.map._cvDensityDims(state, v);
            const areaPos = dims.m.symbolSize * v.k * v.k * v.sc * v.sc;   // area de un marcador de posicion en pantalla (px CSS)
            const pts = cv.pts;
            const margin = 24 + (cv.hasSpk ? dims.spkMax : 0) + (cv.hasBub ? Math.sqrt(dims.aMax / Math.PI) * 1.4 : 0);
            const posSprites = {}, bubSprites = {};
            ctx.globalAlpha = cv.alpha;
            ctx.lineWidth = dims.spkStroke;
            let drawn = 0;
            for (let i = 0; i < pts.length; i++) {
                const p = pts[i];
                const sx = Math.round(v.ox + v.sc * (v.t.x + v.k * p.x));
                if (sx < -margin || sx > v.W + margin) continue;
                const sy = Math.round(v.oy + v.sc * (v.t.y + v.k * p.y));
                if (sy < -margin || sy > v.H + margin) continue;
                const type = p.d.tipo;

                if (p.kind === 'pos') {
                    const sp = posSprites[type] || (posSprites[type] = app.map._cvSprite(state, type, areaPos, v.sc, dpr, false));
                    const h = sp.side / 2;
                    ctx.drawImage(sp.img, sx - h, sy - h, sp.side, sp.side);
                } else if (p.kind === 'bub') {
                    const q = app.map._cvBubbleArea(dims, p.frac);
                    const bs = bubSprites[type] || (bubSprites[type] = {});
                    const sp = bs[q.idx] || (bs[q.idx] = app.map._cvSprite(state, type, q.area, v.sc, dpr, true));
                    const h = sp.side / 2;
                    ctx.drawImage(sp.img, sx - h, sy - h, sp.side, sp.side);
                } else {
                    const col = cv.spk[type];
                    app.map._cvTriangle(ctx, sx, sy, dims.spkW, dims.spkMin + (dims.spkMax - dims.spkMin) * p.t);
                    ctx.fillStyle = col.colors[p.lv]; ctx.strokeStyle = col.edge;
                    ctx.fill(); ctx.stroke();
                }
                drawn++;
            }
            ctx.globalAlpha = 1;

            // Marcador bajo el cursor: borde mas grueso
            [cv.hover, cv.sel].forEach(function(hv) {
                if (!hv) return;
                const sx = Math.round(v.ox + v.sc * (v.t.x + v.k * hv.x)), sy = Math.round(v.oy + v.sc * (v.t.y + v.k * hv.y));
                if (hv.kind === 'spk') {
                    const col = cv.spk[hv.d.tipo];
                    app.map._cvTriangle(ctx, sx, sy, dims.spkW, dims.spkMin + (dims.spkMax - dims.spkMin) * hv.t);
                    ctx.fillStyle = col.colors[hv.lv]; ctx.strokeStyle = col.edge; ctx.lineWidth = dims.spkStroke * 2;
                    ctx.fill(); ctx.stroke();
                } else {
                    const area = hv.kind === 'bub' ? app.map._cvBubbleArea(dims, hv.frac).area : areaPos;
                    const sp = app.map._cvSprite(state, hv.d.tipo, area, v.sc, dpr, hv.kind === 'bub');
                    ctx.save();
                    ctx.translate(sx, sy);
                    ctx.fillStyle = sp.fill; ctx.strokeStyle = sp.style.stroke; ctx.lineWidth = 2 * sp.lw;
                    ctx.fill(sp.path); ctx.stroke(sp.path);
                    ctx.restore();
                }
            });
            app.utils.perf('canvas: dibujo de marcadores', t0, drawn + ' de ' + pts.length + ' visibles');
        },

        /**
         * Marcador bajo el puntero (clientX/clientY), o null. Cada tipo se prueba con su forma:
         * posicion = circulo de toque, burbuja = su radio, pico = su triangulo. Gana el de posicion mas cercano (van encima);
         * si no hay, el de burbuja o pico dibujado al final (el que se ve arriba).
         */
        _cvHit: function(state, clientX, clientY) {
            const cv = state._cv;
            if (!cv || !cv.qt || !cv.pts.length) return null;
            const v = app.map._cvView(state);
            if (!v) return null;
            const r = state.svg.node().getBoundingClientRect();
            const k2 = v.k * v.sc;                                   // unidades del mapa -> px
            const mx = (((clientX - r.left) - v.ox) / v.sc - v.t.x) / v.k;
            const my = (((clientY - r.top) - v.oy) / v.sc - v.t.y) / v.k;

            const dims = app.map._cvDensityDims(state, v);
            const posR = dims.m.isTouch ? 16 : 9;
            const tol = dims.m.isTouch ? 8 : 3;
            const reach = Math.max(posR, cv.hasBub ? Math.sqrt(dims.aMax / Math.PI) + tol : 0, cv.hasSpk ? dims.spkMax + tol : 0);
            const R = reach / k2;

            let bestPos = null, bestPosD = Infinity, bestDen = null;
            cv.qt.visit((node, x0, y0, x1, y1) => {
                if (!node.length) {
                    let n = node;
                    do {
                        const p = n.data;
                        const dx = (mx - p.x) * k2, dy = (my - p.y) * k2;      // px; dy > 0: el puntero esta debajo del punto
                        if (p.kind === 'pos') {
                            const d2 = dx * dx + dy * dy;
                            if (d2 <= posR * posR && d2 < bestPosD) { bestPosD = d2; bestPos = p; }
                        } else if (!bestDen || p.i > bestDen.i) {
                            if (p.kind === 'bub') {
                                const rad = Math.sqrt((dims.aMin + (dims.aMax - dims.aMin) * p.frac) / Math.PI) + tol;
                                if (dx * dx + dy * dy <= rad * rad) bestDen = p;
                            } else {
                                const h = dims.spkMin + (dims.spkMax - dims.spkMin) * p.t;
                                if (dy <= tol && dy >= -h - tol) {
                                    const up = Math.min(1, Math.max(0, -dy / h));
                                    if (Math.abs(dx) <= Math.max(dims.spkW, dims.spkHit) * (1 - up) + tol) bestDen = p;
                                }
                            }
                        }
                    } while ((n = n.next));
                }
                return x0 > mx + R || x1 < mx - R || y0 > my + R || y1 < my - R;
            });
            return bestPos || bestDen;
        },

        /**
         * Marcadores encimados en el canvas: el que esta bajo el puntero (primero) y despues los que tambien cubren su
         * centro, del de mas arriba al de mas abajo. Solo se calcula al hacer clic o tocar. No agrupa: cada marcador sigue siendo uno.
         * Posicion: centros a menos de ~75 % del radio de toque; burbuja y pico: su forma real.
         */
        _cvStackAt: function(state, clientX, clientY) {
            const top = app.map._cvHit(state, clientX, clientY);
            if (!top) return [];
            const cv = state._cv, v = app.map._cvView(state);
            if (!v) return [top.d];
            const k2 = v.k * v.sc;
            const dims = app.map._cvDensityDims(state, v);
            const tol = dims.m.isTouch ? 8 : 3;
            const posR = (dims.m.isTouch ? 16 : 9) * 0.75;
            const reach = Math.max(posR, cv.hasBub ? Math.sqrt(dims.aMax / Math.PI) + tol : 0, cv.hasSpk ? dims.spkMax + tol : 0);
            const R = reach / k2;
            const found = [];
            cv.qt.visit((node, x0, y0, x1, y1) => {
                if (!node.length) {
                    let n = node;
                    do {
                        const p = n.data;
                        if (p !== top) {
                            const dx = (top.x - p.x) * k2, dy = (top.y - p.y) * k2;
                            let ok = false;
                            if (p.kind === 'pos') ok = dx * dx + dy * dy <= posR * posR;
                            else if (p.kind === 'bub') {
                                const rad = Math.sqrt((dims.aMin + (dims.aMax - dims.aMin) * p.frac) / Math.PI) + tol;
                                ok = dx * dx + dy * dy <= rad * rad;
                            } else {
                                const h = dims.spkMin + (dims.spkMax - dims.spkMin) * p.t;
                                if (dy <= tol && dy >= -h - tol) {
                                    const up = Math.min(1, Math.max(0, -dy / h));
                                    ok = Math.abs(dx) <= Math.max(dims.spkW, dims.spkHit) * (1 - up) + tol;
                                }
                            }
                            if (ok) found.push(p);
                        }
                    } while ((n = n.next));
                }
                return x0 > top.x + R || x1 < top.x - R || y0 > top.y + R || y1 < top.y - R;
            });
            found.sort((a, b) => b.i - a.i);                           // mayor indice = dibujado despues = mas arriba
            found.unshift(top);
            state._stackPts = found;                                   // para resaltar el elegido en el canvas
            return found.map(p => p.d);
        },

        /** Marcadores encimados en el punto tocado, en SVG (pocos marcadores): los elementos que hay bajo el puntero. */
        _svgStackAt: function(e) {
            const els = document.elementsFromPoint ? document.elementsFromPoint(e.clientX, e.clientY) : [e.target];
            const out = [], seen = new Set();
            els.forEach(function(el) {
                if (el.classList && el.classList.contains('siarhe-marker') && el.__data__ && !seen.has(el.__data__._uid)) {
                    seen.add(el.__data__._uid);
                    out.push({ d: el.__data__, el: el });
                }
            });
            const first = out.findIndex(o => o.el === e.target);       // el tocado va primero
            if (first > 0) out.unshift(out.splice(first, 1)[0]);
            return out;
        },

        /**
         * Clic o toque sobre un marcador. Con uno solo, no cambia nada. Con varios encimados abre el panel (1 de N);
         * otro toque en el mismo lugar pasa al siguiente. Devuelve true si atendio el clic como pila.
         */
        stackClick: function(state, e, list, nodes) {
            const mapDiv = state.svg.node().parentNode;
            const pt = d3.pointer(e, mapDiv);
            const st = state._stack;
            if (list.length < 2) { app.map.stackClose(state); return false; }

            const same = st && st.list.length === list.length && st.list.every((d, i) => d._uid === list[i]._uid);
            const near = st && Math.hypot(st.ax - pt[0], st.ay - pt[1]) <= 8;
            if (same || near && st.list.some(d => d._uid === list[0]._uid)) {
                st.idx = (st.idx + 1) % st.list.length;
                st.ax = pt[0]; st.ay = pt[1];
            } else {
                state._stack = { list: list, idx: 0, ax: pt[0], ay: pt[1], nodes: nodes || null, pts: nodes ? null : (state._stackPts || null) };
            }
            if (state.tooltip) state.tooltip.style('opacity', 0).style('display', 'none');
            app.map.stackShow(state);
            return true;
        },

        /** Muestra el panel y resalta el marcador actual. */
        stackShow: function(state) {
            const st = state._stack;
            if (!st) return;
            const mapDiv = state.svg.node().parentNode;
            app.tooltips.showStackCard(state, mapDiv, st);
            app.map._stackMark(state);
        },

        /** Resalta el marcador actual de la pila (canvas: borde grueso encima; SVG: borde grueso y al frente). */
        _stackMark: function(state) {
            const st = state._stack, cv = state._cv;
            if (cv) {
                cv.sel = (st && st.pts) ? st.pts[st.idx] : null;
                app.map._cvSchedule(state);
            }
            if (state._stackEl) { app.map._svgUnmark(state, state._stackEl); state._stackEl = null; }
            if (st && st.nodes && st.nodes[st.idx]) {
                const el = st.nodes[st.idx];
                let k = 1; try { k = d3.zoomTransform(state.svg.node()).k; } catch (err) {}
                const mt = app.map._markerMetrics(state, k);
                el.setAttribute('stroke-width', (2.5 * mt.strokePx) / (k * mt.sc));
                if (!app.utils.isDensity((state.markerLabels[el.__data__.tipo] || {}).tipo)) d3.select(el).raise();
                state._stackEl = el;
            }
        },

        _svgUnmark: function(state, el) {
            let k = 1; try { k = d3.zoomTransform(state.svg.node()).k; } catch (err) {}
            const mt = app.map._markerMetrics(state, k);
            el.setAttribute('stroke-width', mt.strokePx / (k * mt.sc));
        },

        /** Cierra el panel (clic en otro lado, zoom, cerrar o cambio de marcadores). */
        stackClose: function(state) {
            if (!state._stack && !(state.stackCard && state.stackCard.style.display !== 'none')) return;
            state._stack = null;
            state._stackPts = null;
            if (state.stackCard) state.stackCard.style.display = 'none';
            app.map._stackMark(state);
        },

        /** Eventos sobre el canvas (que no recibe el puntero): tooltip, resaltado y clic para medir. */
        _cvBind: function(state) {
            const node = state.svg && state.svg.node();
            if (!node || node.__siarheCvBound) return;
            node.__siarheCvBound = true;

            node.addEventListener('mousemove', (e) => {
                const cv = state._cv;
                if (!cv || !cv.pts.length) return;
                cv.lastEvent = e;
                if (cv.moveRaf) return;
                cv.moveRaf = requestAnimationFrame(() => { cv.moveRaf = 0; app.map._cvPointer(state, cv.lastEvent); });
            });
            node.addEventListener('mouseleave', () => {
                const cv = state._cv;
                if (cv && cv.hover) { cv.hover = null; node.style.cursor = ''; app.map._cvSchedule(state); }
            });
        },

        /**
         * Clic o toque sobre los marcadores (canvas o SVG), en captura: un clic sobre un marcador no debe llegar al poligono
         * de abajo (doble clic = cambiar de entidad). Los encimados se calculan solo aqui, no mientras se mueve el puntero.
         */
        stackBind: function(state) {
            const node = state.svg && state.svg.node();
            if (!node || node.__siarheStackBound) return;
            node.__siarheStackBound = true;

            node.addEventListener('click', (e) => {
                const cv = state._cv;
                let list = [], nodes = null;
                if (cv && cv.pts.length) {
                    list = app.map._cvStackAt(state, e.clientX, e.clientY);
                } else if (e.target && e.target.classList && e.target.classList.contains('siarhe-marker')) {
                    const items = app.map._svgStackAt(e);
                    list = items.map(o => o.d); nodes = items.map(o => o.el);
                }
                if (!list.length) { app.map.stackClose(state); return; }     // clic en otro lado: se cierra el panel
                e.stopPropagation();
                if (list.length > 1) { app.map.stackClick(state, e, list, nodes); return; }
                app.map.stackClose(state);
                if (state.measure && state.measure.active) app.map.measureAddPoint(state, list[0]);
            }, true);
        },

        _cvPointer: function(state, e) {
            const cv = state._cv;
            if (!cv || !e || !state.svg || state._stack) return;       // con el panel abierto no hay tooltip al pasar
            const node = state.svg.node();
            const mapDiv = node.parentNode;
            const hit = app.map._cvHit(state, e.clientX, e.clientY);

            if (hit) {
                const changed = cv.hover !== hit;
                cv.hover = hit;
                node.style.cursor = 'pointer';
                if (app.tooltips) app.tooltips.showMarkerTooltip(e, hit.d, state, mapDiv);
                if (changed) app.map._cvSchedule(state);
            } else if (cv.hover) {
                cv.hover = null;
                node.style.cursor = '';
                app.map._cvSchedule(state);
                // Se devuelve el tooltip de la region que esta debajo del puntero
                const tg = e.target;
                if (tg && tg.classList && (tg.classList.contains('siarhe-feature') || tg.classList.contains('siarhe-loc-feature')) && tg.__data__ && app.tooltips) {
                    app.tooltips.showGeoTooltip(e, tg.__data__, state, mapDiv);
                } else if (state.tooltip) {
                    state.tooltip.style('opacity', 0).style('display', 'none');
                }
            }
        },

        /** Para exportar a PNG: crea elementos SVG para los puntos del canvas dentro de la capa de marcadores del clon. */
        _cvMaterialize: function(state, layerEl) {
            const cv = state._cv;
            if (!cv || !cv.pts.length || !layerEl) return;
            const NS = 'http://www.w3.org/2000/svg';
            const v = app.map._cvView(state);
            const metrics = app.map._markerMetrics(state, v ? v.k : undefined);
            const cache = {};
            const byType = {};
            const frag = document.createDocumentFragment();
            for (let i = 0; i < cv.pts.length; i++) {
                const p = cv.pts[i];
                // Posicion: una forma por tipo; burbujas y picos: cada punto tiene su tamano o altura
                const dens = app.utils.isDensity((state.markerLabels[p.d.tipo] || {}).tipo);
                let sh = dens ? null : byType[p.d.tipo];
                if (!sh) {
                    sh = app.map._markerShape(p.d, state, metrics, cache);
                    if (!dens) byType[p.d.tipo] = sh;
                }
                if (!sh.path) continue;
                const el = document.createElementNS(NS, 'path');
                el.setAttribute('d', sh.path);
                el.setAttribute('transform', 'translate(' + p.x + ',' + p.y + ')');
                el.setAttribute('fill', sh.fill);
                el.setAttribute('stroke', sh.stroke);
                el.setAttribute('stroke-width', (metrics.strokePx * sh.strokeW / 1.5) / (metrics.k * metrics.sc));
                frag.appendChild(el);
            }
            layerEl.appendChild(frag);
        },

        /**
         * Rectangulo visible del SVG en unidades del viewBox (1600x900). El viewBox se ajusta con "meet":
         * si el contenedor tiene otra proporcion (pantalla completa vertical en movil, pantallas cuadradas o
         * muy anchas) se ve MAS mapa que el rectangulo 16:9 (arriba y abajo, o a los lados), y esa zona
         * tambien debe actualizarse.
         */
        _visibleUserRegion: function(state) {
            let x0 = 0, x1 = 1600, y0 = 0, y1 = 900;
            const node = state.svg && state.svg.node();
            if (node && node.getBoundingClientRect) {
                const r = node.getBoundingClientRect();
                if (r.width > 0 && r.height > 0) {
                    const sc = Math.min(r.width / 1600, r.height / 900);
                    const vw = r.width / sc, vh = r.height / sc;
                    x0 = (1600 - vw) / 2; x1 = x0 + vw;
                    y0 = (900 - vh) / 2; y1 = y0 + vh;
                }
            }
            return { x0: x0, x1: x1, y0: y0, y1: y1 };
        },

        // Al cambiar el tamano del contenedor (pantalla completa, rotar el telefono) la region visible cambia sin que haya zoom
        _onMapResize: function(state) {
            state._devCache = null;
            app.map._cvResize(state);
            if (!state._mkNodes || !state.svg) return;
            if (state._mkResizeRaf) cancelAnimationFrame(state._mkResizeRaf);
            state._mkResizeRaf = requestAnimationFrame(() => {
                state._mkResizeRaf = 0;
                state._mkStale = true;
                let t = d3.zoomIdentity;
                try { t = d3.zoomTransform(state.svg.node()); } catch (e) {}
                app.map._refreshMarkerGeometry(state, t);
            });
        },

        _refreshMarkerGeometry: function(state, t, all) {
            const nodes = state._mkNodes;
            if (!nodes || nodes.length === 0) return;
            const k = t.k;
            if (!all && state._mkLastK === k && !state._mkStale) return;
            state._mkLastK = k;
            const tZoom = performance.now();

            const metrics = app.map._markerMetrics(state, k);
            const cache = {};
            const fixedPath = {};
            const isDensity = {};

            // Region visible en coordenadas del mapa, con holgura para el tamano del simbolo
            const vr = app.map._visibleUserRegion(state);
            const pad = 80 / k;
            const x0 = (vr.x0 - t.x) / k - pad, x1 = (vr.x1 - t.x) / k + pad;
            const y0 = (vr.y0 - t.y) / k - pad, y1 = (vr.y1 - t.y) / k + pad;
            const sw = metrics.strokePx / (k * metrics.sc);
            let stale = false;

            for (let i = 0; i < nodes.length; i++) {
                const n = nodes[i];
                if (n.__x === undefined) continue;   // aun en la cola de pintado: se pinta con el zoom vigente
                if (n.__k === k) continue;           // ya esta al dia
                if (!all && (n.__x < x0 || n.__x > x1 || n.__y < y0 || n.__y > y1)) { stale = true; continue; }
                const d = n.__data__;
                if (!d) continue;

                if (isDensity[d.tipo] === undefined) isDensity[d.tipo] = app.utils.isDensity((state.markerLabels[d.tipo] || {}).tipo);
                let path;
                if (isDensity[d.tipo]) {
                    path = app.map._markerShape(d, state, metrics, cache).path;
                } else {
                    path = fixedPath[d.tipo];
                    if (path === undefined) path = fixedPath[d.tipo] = app.map._markerShape(d, state, metrics, cache).path;
                }
                n.setAttribute("d", path);
                n.setAttribute("stroke-width", sw);
                n.__k = k;
            }
            state._mkStale = stale;
            app.utils.perf('zoom: geometria de marcadores', tZoom, nodes.length + ' marcadores');
        },

        // Tamano de simbolo segun el zoom actual (compartido por updateMarkers, el zoom y el pintado por cuadros)
        /**
         * Dispositivo segun el CONTENEDOR del mapa (no solo la ventana) y si el puntero es tactil.
         * kind: 'phone' (< 520 px), 'tablet' (< 900 px o puntero tactil) o 'pc'. isTouch: puntero tactil o mapa angosto.
         * Se guarda 200 ms para no forzar una medicion de la pagina en cada marcador y cada cuadro.
         */
        _deviceInfo: function(state) {
            const now = performance.now();
            if (state._devCache && now - state._devCache.t < 200) return state._devCache;
            let w = 0, h = 0;
            const node = state.svg && state.svg.node && state.svg.node();
            if (node && node.getBoundingClientRect) { const r = node.getBoundingClientRect(); w = r.width; h = r.height; }
            if (!w || !h) { w = window.innerWidth || 1100; h = w * 9 / 16; }
            let coarse = false;
            try { coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches); } catch (e) {}
            const kind = w < 520 ? 'phone' : ((w < 900 || coarse) ? 'tablet' : 'pc');
            return (state._devCache = { t: now, w: w, h: h, coarse: coarse, kind: kind, isTouch: coarse || w < 700, sc: Math.min(w / 1600, h / 900) });
        },

        /**
         * Tamano del simbolo segun el zoom. Se define en PIXELES DE PANTALLA (ancho de una cruz): constante hasta k=4,
         * se reduce suavemente hasta un minimo en k=25 y ahi se queda. Antes el minimo estaba en unidades del mapa y
         * los marcadores volvian a crecer al acercarse mucho.
         * symbolSize: area en unidades del mapa (la usan el SVG, el canvas y las burbujas); la cruz de d3 mide 3*sqrt(area/5).
         */
        /**
         * Grosor del borde de los marcadores en PIXELES de pantalla. Como el simbolo mide pixeles fijos, el borde no
         * puede depender de la escala del contenedor: en pantalla completa de PC (escala ~1) llegaba a 1.5 px y tapaba el
         * color del relleno. Se conserva lo que ya se veia bien (movil ~0.4 px, PC normal ~1 px) y se topa en 1 px.
         */
        _strokePx: function(sc) {
            return Math.min(1, Math.max(0.4, 1.5 * sc));
        },

        _markerMetrics: function(state, kOverride) {
            let k = kOverride;
            if (!k) {
                k = 1;
                if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }
            }

            const dev = app.map._deviceInfo(state);
            const SIZES = { phone: [11, 8], tablet: [15, 10], pc: [14, 8] };   // [tamano base, tamano minimo] en px
            const base = SIZES[dev.kind][0], min = SIZES[dev.kind][1];
            let px = base;
            if (k >= 25) px = min;
            else if (k > 4) px = base * Math.pow(min / base, Math.log(k / 4) / Math.log(25 / 4));

            const sc = dev.sc > 0.05 ? dev.sc : 0.5;
            const extentUser = px / sc;                          // ancho en unidades del viewBox
            const areaScreen = 5 * Math.pow(extentUser / 3, 2);  // area equivalente en pantalla
            // Burbujas y espinas (densidad): tamano en pixeles de pantalla que CRECE al acercar (de k=4 a k=25), para que
            // con zoom sean mas faciles de ver y de tocar. Antes se definian en unidades del mapa: en el movil la base de
            // una espina medida ~3 px.
            const zt = k <= 4 ? 0 : (k >= 25 ? 1 : Math.log(k / 4) / Math.log(25 / 4));
            const lerp = (a, b) => a + (b - a) * zt;
            const SPK = {                                       // [con poco zoom, con mucho zoom]
                pc:     { half: [3.4, 4.5], hMin: [17, 22], hMax: [69, 88] },
                tablet: { half: [3.4, 5.0], hMin: [18, 24], hMax: [74, 96] },
                phone:  { half: [2.4, 6.0], hMin: [11, 24], hMax: [46, 96] },
            }[dev.kind];
            const BUB = { pc: 1.25, tablet: 1.3, phone: 1.35 }[dev.kind];   // multiplicador lineal de las burbujas a mucho zoom
            const bubMul = lerp(1, BUB);
            return {
                k: k, isTouch: dev.isTouch, kind: dev.kind, sc: sc, strokePx: app.map._strokePx(sc), symbolSize: areaScreen / (k * k),
                bubArea: bubMul * bubMul,
                spk: { half: lerp(SPK.half[0], SPK.half[1]), hMin: lerp(SPK.hMin[0], SPK.hMin[1]), hMax: lerp(SPK.hMax[0], SPK.hMax[1]) },
            };
        },

        // Valor numerico de un punto de densidad: columna configurada o numero de registros agrupados
        _markerValue: function(d, config, state, type) {
            if (config.espectro_col && config.espectro_col.trim() !== '') {
                const realCol = state._espectroColKeyCache ? state._espectroColKeyCache[type] : null;
                return (realCol && d._raw) ? (parseFloat(d._raw[realCol]) || 0) : 0;
            }
            return d._agrupados_total || 1;
        },

        // Degradado de un spike: el color elegido es el pico mas alto; hacia abajo se aclara
        // (o llega al color bajo si la persona definio uno). Devuelve t (0..1) -> color.
        _spikeRamp: function(styleCfg) {
            const hi = styleCfg.fill || '#d63638';
            let ramp;
            try {
                const lo = styleCfg.fill2 || d3.interpolateLab(hi, '#ffffff')(0.78);
                const interp = d3.interpolateLab(lo, hi);
                ramp = function(t) { return interp(Math.max(0, Math.min(1, t))); };
            } catch (e) {
                ramp = function() { return hi; };
            }
            return ramp;
        },

        /**
         * Forma de un marcador segun su tipo. Devuelve { path, fill, stroke, strokeW }.
         * - posicion: simbolo fijo
         * - espectro: burbuja con area proporcional al valor
         * - spike: triangulo delgado cuya altura y color dependen del valor (altura constante en pantalla)
         */
        _markerShape: function(d, state, metrics, cache) {
            const type = d.tipo;
            const config = state.markerLabels[type] || {};
            const styleCfg = cache['style:' + type] || (cache['style:' + type] = app.utils.getMarkerStyle(type, state));
            const max = (state.espectroMaxVals && state.espectroMaxVals[type]) || 1;

            if (config.tipo === 'spike') {
                const val = app.map._markerValue(d, config, state, type);
                if (!(val > 0)) return { path: '', fill: styleCfg.fill, stroke: 'none', strokeW: 0 };

                // Escala lineal entre el menor y el mayor valor del marcador:
                // el menor mide SPIKE_MIN_H unidades y el mayor SPIKE_MAX_H (unidades del viewBox)
                const min = (state.espectroMinVals && state.espectroMinVals[type]) || max;
                const t = max > min ? Math.max(0, Math.min(1, (val - min) / (max - min))) : 1;
                // Medidas en pixeles de pantalla (metrics.spk) convertidas a unidades del mapa: constantes al hacer zoom
                const sp = metrics.spk;
                const h = (sp.hMin + (sp.hMax - sp.hMin) * t) / (metrics.sc * metrics.k);
                const w = sp.half / (metrics.sc * metrics.k);
                const ramp = cache['ramp:' + type] || (cache['ramp:' + type] = app.map._spikeRamp(styleCfg));
                const edge = cache['edge:' + type] || (cache['edge:' + type] = (function() {
                    const c = d3.color(styleCfg.fill);
                    return c ? c.darker(0.7).formatRgb() : '#7f1d1d';
                })());
                return { path: 'M' + (-w) + ',0L0,' + (-h) + 'L' + w + ',0Z', fill: ramp(t), stroke: edge, strokeW: 1 };
            }

            const symbol = cache['sym:' + type] || (cache['sym:' + type] = d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)));

            if (config.tipo === 'espectro') {
                const val = app.map._markerValue(d, config, state, type);
                let size = 0;
                if (val > 0) {
                    const scale = cache['scale:' + type] || (cache['scale:' + type] = d3.scaleLinear()
                        .domain([1, max])
                        .range([metrics.symbolSize * 2.5 * metrics.bubArea, metrics.symbolSize * 25 * metrics.bubArea]).clamp(true));
                    size = scale(val);
                }
                const fill = styleCfg.fill.startsWith('rgba') ? styleCfg.fill : app.map.hexToRgba(styleCfg.fill, 0.75);
                return { path: symbol.size(size)(), fill: fill, stroke: styleCfg.stroke, strokeW: 1.5 };
            }

            return { path: symbol.size(metrics.symbolSize)(), fill: styleCfg.fill, stroke: styleCfg.stroke, strokeW: 1.5 };
        },

        updateMarkers: function(state) {
            if (!state.gMarkers) return Promise.resolve();

            let allPoints = [];
            state.activeMarkers.forEach(type => {
                const data = state.markersData[type];
                if (!Array.isArray(data)) return;
                // Id estable por punto: permite a d3 reutilizar los nodos ya pintados al activar/desactivar otro tipo
                if (data.length > 0 && data[0]._uid === undefined) {
                    for (let i = 0; i < data.length; i++) data[i]._uid = type + ':' + i;
                }
                allPoints = allPoints.concat(data);
            });

            let hasEspectro = false;
            state.activeMarkers.forEach(type => { if (app.utils.isDensity((state.markerLabels[type] || {}).tipo)) hasEspectro = true; });

            // Orden de apilado: burbujas (grandes antes que chicas) < picos (norte atras, sur al frente) < posicion
            const rank = (p) => {
                const t = (state.markerLabels[p.tipo] || {}).tipo;
                return t === 'espectro' ? 0 : (t === 'spike' ? 1 : 2);
            };
            allPoints.sort((a, b) => {
                const ra = rank(a), rb = rank(b);
                if (ra !== rb) return ra - rb;
                if (ra === 0) return (b._agrupados_total || 1) - (a._agrupados_total || 1);
                if (ra === 1) return (b.lat || 0) - (a.lat || 0);
                return 0;
            });

            // Resolvemos la columna real y el maximo de "espectro" UNA vez por tipo (no por punto)
            state.espectroMaxVals = {};
            state.espectroMinVals = {};
            state._espectroColKeyCache = {};
            state.activeMarkers.forEach(type => {
                const config = state.markerLabels[type] || {};
                if (app.utils.isDensity(config.tipo)) {
                    const data = state.markersData[type] || [];
                    let max = 0, min = 0;
                    if (config.espectro_col && config.espectro_col.trim() !== '') {
                        const sample = data.find(d => d._raw);
                        const realCol = sample ? Object.keys(sample._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim()) : null;
                        state._espectroColKeyCache[type] = realCol || null;
                        max = d3.max(data, d => realCol ? (parseFloat(d._raw[realCol]) || 0) : 0);
                        min = d3.min(data, d => { const v = realCol ? (parseFloat(d._raw[realCol]) || 0) : 0; return v > 0 ? v : undefined; });
                    } else {
                        max = d3.max(data, d => d._agrupados_total || 1);
                        min = d3.min(data, d => d._agrupados_total || 1);
                    }
                    state.espectroMaxVals[type] = max || 1;
                    state.espectroMinVals[type] = min || max || 1; // menor valor > 0: ancla el pico mas bajo
                }
            });

            // Reparto: con muchos marcadores (posicion, burbujas y picos) se dibujan todos en canvas, un dibujo por cuadro
            // en vez de miles de elementos SVG; con pocos puntos siguen en SVG
            app.map.stackClose(state);          // los puntos cambian: el panel de encimados ya no aplica
            const cvOn = app.map._cvDecide(state, allPoints);
            const cvPoints = cvOn ? allPoints : [];
            const svgPoints = cvOn ? [] : allPoints;

            const markers = state.gMarkers.selectAll("path.siarhe-marker").data(svgPoints, d => d._uid);
            markers.exit().remove();

            // Solo los nodos NUEVOS se crean, se enlazan a eventos y se pintan; los existentes no se tocan
            const entered = markers.enter().append("path")
                .attr("class", "siarhe-marker")
                .style("cursor", "pointer")
                .style("opacity", 0)
                .style("transition", app.utils.motionMs(1) === 0 ? "none" : "opacity 0.5s ease-out");

            // Un solo manejador de eventos en el grupo (no uno por marcador): con decenas de miles de puntos
            // evita cientos de miles de escuchadores. Se registra una vez por grupo.
            const mkLayer = state.gMarkers.node();
            if (mkLayer && !mkLayer.__siarheDelegated) {
                mkLayer.__siarheDelegated = true;
                const isMarker = (e) => e.target && e.target.classList && e.target.classList.contains('siarhe-marker');
                const zoomK = () => { let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} } return k; };

                state.gMarkers
                    .on("mouseover", function(e) {
                        if (!isMarker(e) || state._stack) return;
                        const node = e.target, d = node.__data__;
                        if (!d) return;
                        const k = zoomK();
                        const config = state.markerLabels[d.tipo] || {};

                        const mt = app.map._markerMetrics(state, k);
                        node.setAttribute("stroke-width", (2 * mt.strokePx) / (k * mt.sc));
                        if (!app.utils.isDensity(config.tipo)) { d3.select(node).raise(); }

                        const mapDiv = node.closest('.siarhe-map-container');
                        if (app.tooltips && mapDiv) app.tooltips.showMarkerTooltip(e, d, state, mapDiv);
                    })
                    .on("mouseout", function(e) {
                        if (!isMarker(e) || e.target === state._stackEl) return;
                        const kz = zoomK(), mz = app.map._markerMetrics(state, kz);
                        e.target.setAttribute("stroke-width", mz.strokePx / (kz * mz.sc));
                        if (state.tooltip) state.tooltip.style("display", "none");
                    });
                // El clic (medir, marcadores encimados) se atiende en app.map.stackBind
            }

            // El orden en el DOM define el apilado: solo hace falta reordenar si hay burbujas de espectro
            if (hasEspectro) entered.merge(markers).order();

            // Lista de nodos para el zoom y modo denso: con muchos puntos se prioriza la velocidad de dibujo
            state._mkNodes = state.gMarkers.selectAll("path.siarhe-marker").nodes();
            state._mkStale = false;
            state.gMarkers.classed("siarhe-markers-dense", svgPoints.length > 5000);
            app.map._cvSet(state, cvPoints);

            app.map.renderMarkerLegend(state);

            const tPaint = performance.now();
            const nuevos = entered.size();
            return app.map._enqueueMarkers(state, entered.nodes()).then(function() { app.utils.perf('marcadores pintados', tPaint, nuevos + ' nuevos de ' + allPoints.length); });
        },

        /**
         * Encola los marcadores nuevos agrupados por unidad geografica (entidad en el mapa nacional,
         * municipio en el estatal) para que aparezcan como una oleada region por region.
         * Devuelve una Promise que se resuelve cuando la cola queda vacia.
         */
        _enqueueMarkers: function(state, nodes) {
            state._markerQueue = state._markerQueue || [];
            state._markerWaiters = state._markerWaiters || [];

            if (nodes.length > 0) {
                const groups = new Map();
                nodes.forEach(n => {
                    const d = n.__data__ || {};
                    const key = state.isNacional ? (d.cve_ent || '') : (d.municipio || '');
                    if (!groups.has(key)) groups.set(key, []);
                    groups.get(key).push(n);
                });
                Array.from(groups.keys()).sort().forEach(key => {
                    state._markerQueue.push({ nodes: groups.get(key), i: 0 });
                });
            }

            if (!state._markerPainting && state._markerQueue.length === 0) return Promise.resolve();

            // Ritmo constante: la cola completa se reparte en ~70 cuadros (~1.2 s), sin importar cuantos
            // marcadores tenga cada region. Asi el flujo es parejo y no en rafagas por entidad/municipio.
            let pending = 0;
            state._markerQueue.forEach(g => { pending += g.nodes.length - g.i; });
            state._markerRate = Math.max(20, Math.ceil(pending / 70));

            return new Promise(resolve => {
                state._markerWaiters.push(resolve);
                if (!state._markerPainting) {
                    state._markerPainting = true;
                    // Doble rAF: garantiza que los nodos nuevos (opacity 0) ya se estilizaron y el fundido se anime
                    requestAnimationFrame(() => requestAnimationFrame(() => app.map._paintMarkerFrame(state)));
                }
            });
        },

        // Cada cuadro pinta ~_markerRate marcadores (siguiendo el orden por region) con tope de ~12 ms
        _paintMarkerFrame: function(state) {
            const queue = state._markerQueue;
            const metrics = app.map._markerMetrics(state);
            const cache = {};
            const t0 = performance.now();
            const rate = state._markerRate || 50;
            let painted = 0;

            while (queue.length > 0 && painted < rate) {
                const g = queue[0];
                while (g.i < g.nodes.length && painted < rate) {
                    app.map._paintMarkerNode(g.nodes[g.i++], state, metrics, cache);
                    painted++;
                    if ((painted & 31) === 0 && performance.now() - t0 > 12) { painted = rate; break; }
                }
                if (g.i >= g.nodes.length) queue.shift();
            }

            if (queue.length > 0) {
                requestAnimationFrame(() => app.map._paintMarkerFrame(state));
            } else {
                state._markerPainting = false;
                const waiters = state._markerWaiters || [];
                state._markerWaiters = [];
                waiters.forEach(resolve => resolve());
            }
        },

        _paintMarkerNode: function(node, state, metrics, cache) {
            if (!node.isConnected) return; // se desactivo el tipo mientras esperaba en la cola
            const d = node.__data__;
            if (!d) return;

            const coords = state.projection([d.lon, d.lat]);
            if (!coords || isNaN(coords[0]) || isNaN(coords[1])) {
                node.style.display = 'none'; // coordenada fuera de proyeccion: no se dibuja en (0,0)
                return;
            }

            const shape = app.map._markerShape(d, state, metrics, cache);

            node.setAttribute("d", shape.path);
            node.setAttribute("transform", `translate(${coords[0]}, ${coords[1]})`);
            node.setAttribute("fill", shape.fill);
            node.setAttribute("stroke", shape.stroke);
            node.setAttribute("stroke-width", (metrics.strokePx * shape.strokeW / 1.5) / (metrics.k * metrics.sc));
            node.__x = coords[0]; node.__y = coords[1]; node.__k = metrics.k; // para actualizar solo lo visible al hacer zoom
            node.style.opacity = 1; // dispara el fundido de entrada
        },

        renderMarkerLegend: function(state) {
            app.map.updateMeasureAvailability(state);
            const g = state.gMarkerLegend;
            g.html("");
            g.style("font-family", "Roboto, Arial, sans-serif");

            const actives = Array.from(state.activeMarkers);
            if (actives.length === 0) {
                state._markerBox = null;
                if (state.containerEl) app.map.layoutLegends(state.containerEl, state);
                return;
            }

            // Tamanos en unidades del viewBox (layoutLegends los escala); la caja crece hacia arriba desde abajo
            const FT = 17, FL = 15, ROW = 26;
            const labels = actives.map(tipo => {
                const cfg = state.markerLabels[tipo] || {};
                let text = cfg.label || tipo;
                // Los picos indican su valor maximo: es la referencia de escala de la altura
                if (cfg.tipo === 'spike' && state.espectroMaxVals && state.espectroMaxVals[tipo] > 0) {
                    const fmt = (v) => Number(v).toLocaleString('es-MX');
                    const vMax = state.espectroMaxVals[tipo];
                    const vMin = state.espectroMinVals ? state.espectroMinVals[tipo] : vMax;
                    text += vMin < vMax ? ` (${fmt(vMin)} a ${fmt(vMax)})` : ` (${fmt(vMax)})`;
                }
                return text;
            });
            const maxLen = Math.max.apply(null, labels.map(t => String(t).length));
            const boxWidth = Math.max(230, Math.min(460, 40 + maxLen * 8.6 + 28), 18 * 10.2 + 28);
            const boxHeight = (actives.length * ROW) + 38;
            const startY = -boxHeight;

            g.append("rect")
                .attr("x", -12).attr("y", startY)
                .attr("width", boxWidth)
                .attr("height", boxHeight)
                .attr("fill", "rgba(255,255,255,0.92)")
                .attr("rx", 6)
                .style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.12))");

            g.append("text").attr("x", 0).attr("y", startY + 24).text("Marcadores Activos")
                .style("font-size", FT + "px").style("font-weight", "bold").style("fill", "#0F172A");

            const defs = state.svg ? state.svg.select('defs') : null;

            actives.forEach((tipo, i) => {
                const yPos = startY + 24 + ((i + 1) * ROW);
                const styleCfg = app.utils.getMarkerStyle(tipo, state);
                const cfg = state.markerLabels[tipo] || {};

                if (cfg.tipo === 'spike' && defs && !defs.empty()) {
                    // Pico con el degradado real: oscuro arriba (valor alto), claro abajo (valor bajo)
                    const gid = `${state.gradientId}-spike-${i}`;
                    defs.select('#' + gid).remove();
                    const ramp = app.map._spikeRamp(styleCfg);
                    const grad = defs.append('linearGradient').attr('id', gid)
                        .attr('x1', '0%').attr('y1', '100%').attr('x2', '0%').attr('y2', '0%');
                    grad.append('stop').attr('offset', '0%').attr('stop-color', ramp(0));
                    grad.append('stop').attr('offset', '100%').attr('stop-color', ramp(1));

                    g.append("path")
                        .attr("d", "M0,0L5,-18L10,0Z")
                        .attr("transform", `translate(3, ${yPos - 1})`)
                        .attr("fill", `url(#${gid})`)
                        .attr("stroke", ramp(1))
                        .attr("stroke-width", 0.8);
                } else {
                    g.append("path")
                        .attr("d", d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(90)())
                        .attr("transform", `translate(8, ${yPos - 5})`)
                        .attr("fill", styleCfg.fill)
                        .attr("stroke", styleCfg.stroke)
                        .attr("stroke-width", 1);
                }

                g.append("text").attr("x", 26).attr("y", yPos).text(labels[i])
                    .style("font-size", FL + "px").style("fill", "#475569");
            });

            state._markerBox = { w: boxWidth, h: boxHeight, ox: 12 };
            if (state.containerEl) app.map.layoutLegends(state.containerEl, state);
        },

        // =====================================================================
        // MEDICION DE DISTANCIAS entre marcadores de posicion (linea recta, circulo maximo)
        //  - Ruta encadenada ('chain'): A->B->C con distancia por tramo y total (planificar rutas aproximadas)
        //  - Desde un centro ('radial'): el primer marcador es el centro y cada uno de los demas
        //    se mide por separado contra el (ej. una escuela y las unidades de salud cercanas)
        // =====================================================================

        _distanceKm: function(a, b) {
            return d3.geoDistance([a.lon, a.lat], [b.lon, b.lat]) * 6371.0088; // radio medio terrestre
        },

        _formatDistance: function(km) {
            if (km < 1) return Math.round(km * 1000).toLocaleString('es-MX') + ' m';
            const decimals = km < 100 ? 1 : 0;
            return km.toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' km';
        },

        /** Ruta encadenada: distancia de cada tramo y total. */
        _measureSummary: function(points) {
            const segments = [];
            let total = 0;
            for (let i = 1; i < points.length; i++) {
                const km = app.map._distanceKm(points[i - 1], points[i]);
                segments.push(km);
                total += km;
            }
            return { segments: segments, total: total };
        },

        /** Desde un centro: distancia independiente del primer punto a cada uno de los demas. */
        _radialSummary: function(points) {
            if (points.length < 2) return { spokes: [], sorted: [], nearest: null, farthest: null };
            const center = points[0];
            const spokes = points.slice(1).map((p, i) => ({ index: i + 1, p: p, km: app.map._distanceKm(center, p) }));
            const sorted = spokes.slice().sort((a, b) => a.km - b.km);
            return { spokes: spokes, sorted: sorted, nearest: sorted[0], farthest: sorted[sorted.length - 1] };
        },

        _measureMax: function(ms) {
            return ms.mode === 'radial' ? 20 : 10;
        },

        _pointLabel: function(p) {
            return p.name + (p.clues ? ' (' + p.clues + ')' : '');
        },

        /**
         * Crea la capa SVG, el panel de resultados y el estado de la herramienta.
         * Solo se activa si el mapa tiene marcadores de posicion configurados.
         */
        initMeasure: function(container, state, mapDiv, toggleBtn) {
            const ms = state.measure = { active: false, mode: 'chain', points: [], btn: toggleBtn, mapDiv: mapDiv, note: '' };

            state.gMeasure = (state.gMeasureHost || state.gMain).append('g').attr('class', 'measure-layer').style('pointer-events', 'none');
            if (state.gMeasureHost && state.svg) { try { state.gMeasureHost.attr('transform', d3.zoomTransform(state.svg.node())); } catch (e) {} }

            const panel = document.createElement('div');
            panel.className = 'siarhe-measure-panel';
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-label', 'Medición de distancias');
            panel.hidden = true;
            panel.innerHTML =
                '<div class="siarhe-measure-head">' +
                    '<strong class="siarhe-measure-title">Medir distancia</strong>' +
                    '<span class="siarhe-measure-total"></span>' +
                    '<span class="siarhe-measure-actions">' +
                        '<button type="button" data-act="undo" title="Quitar el último punto">Deshacer</button>' +
                        '<button type="button" data-act="clear" title="Quitar todos los puntos">Limpiar</button>' +
                        '<button type="button" data-act="copy" title="Copiar el resultado">Copiar</button>' +
                        '<button type="button" data-act="download" title="Descargar el detalle en CSV (se abre en Excel)">Descargar</button>' +
                        '<button type="button" data-act="close" title="Salir de la medición (Esc)">Salir</button>' +
                    '</span>' +
                '</div>' +
                '<div class="siarhe-measure-modes" role="group" aria-label="Tipo de medición">' +
                    '<button type="button" data-mode="chain" aria-pressed="true" title="Suma los tramos entre puntos consecutivos">Ruta encadenada</button>' +
                    '<button type="button" data-mode="radial" aria-pressed="false" title="Mide cada marcador contra el primero (el centro)">Desde un centro</button>' +
                '</div>' +
                '<p class="siarhe-measure-hint"></p>' +
                '<details class="siarhe-measure-details"><summary>Detalle</summary><ul class="siarhe-measure-list"></ul></details>' +
                '<span class="siarhe-sr-only" aria-live="polite"></span>';
            mapDiv.appendChild(panel);
            ms.panel = panel;

            panel.addEventListener('click', (e) => {
                const modeBtn = e.target.closest('button[data-mode]');
                if (modeBtn) {
                    ms.mode = modeBtn.getAttribute('data-mode');
                    ms.note = '';
                    ms.panel.querySelector('.siarhe-sr-only').textContent = '';
                    // Los puntos se conservan: el primero pasa a ser el centro (o vuelve a ser inicio de ruta)
                    ms.points = ms.points.slice(0, app.map._measureMax(ms));
                    app.map.renderMeasure(state);
                    return;
                }
                const btn = e.target.closest('button[data-act]');
                if (!btn) return;
                const act = btn.getAttribute('data-act');
                if (act === 'undo') { ms.points.pop(); ms.note = ''; app.map.renderMeasure(state); }
                else if (act === 'clear') { ms.points = []; ms.note = ''; app.map.renderMeasure(state); }
                else if (act === 'close') { app.map.setMeasureMode(state, false); if (ms.btn) ms.btn.focus(); }
                else if (act === 'copy') { app.map.copyMeasure(state, btn); }
                else if (act === 'download') { app.map.downloadMeasure(state, container); }
            });

            // Escape sale del modo de medicion
            container.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && ms.active) {
                    e.preventDefault();
                    app.map.setMeasureMode(state, false);
                    if (ms.btn) ms.btn.focus();
                }
            });
        },

        // El boton de medir solo se habilita si hay al menos un marcador de posicion activo
        updateMeasureAvailability: function(state) {
            const ms = state.measure;
            if (!ms || !ms.btn) return;
            const labels = state.markerLabels || {};
            const enabled = Array.from(state.activeMarkers || []).some(k => (labels[k] || {}).tipo === 'posicion');
            if (!enabled && ms.active) app.map.setMeasureMode(state, false);
            // Sin marcadores de posicion activos el boton ni se muestra (el vacio lo ocupan los demas controles)
            ms.btn.style.display = enabled ? '' : 'none';
        },

        setMeasureMode: function(state, on) {
            const ms = state.measure;
            if (!ms) return;
            ms.active = !!on;
            ms.note = '';
            if (!on) ms.points = []; // al salir se limpia la medicion (el tipo elegido se conserva)
            if (ms.btn) {
                ms.btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                ms.btn.classList.toggle('is-active', !!on);
            }
            ms.panel.hidden = !on;
            ms.mapDiv.classList.toggle('is-measuring', !!on);
            app.map.renderMeasure(state);
        },

        /**
         * Se llama al tocar un marcador con el modo medicion activo.
         * Tocar un marcador que ya esta en la medicion lo quita: en ruta, A-B-C pasa a A-C;
         * en "desde un centro", deja de medirse ese destino (si era el centro, simplemente se deselecciona).
         */
        measureAddPoint: function(state, d) {
            const ms = state.measure;
            if (!ms || !ms.active) return;

            const cfg = state.markerLabels[d.tipo] || {};
            const max = app.map._measureMax(ms);
            const idx = ms.points.findIndex(p => p.uid === d._uid);
            let announce = '';

            if (cfg.tipo !== 'posicion') {
                ms.note = 'Solo se pueden medir marcadores de posición.';
            } else if (idx !== -1) {
                const removed = ms.points.splice(idx, 1)[0];
                ms.note = 'Se quitó: ' + removed.name + '.';
                announce = 'Se quitó ' + removed.name + '. ';
            } else if (ms.points.length >= max) {
                ms.note = 'Máximo ' + max + ' puntos. Toca uno ya elegido para quitarlo, o usa Deshacer o Limpiar.';
            } else {
                ms.note = '';
                ms.points.push({
                    uid: d._uid, lon: d.lon, lat: d.lat,
                    name: d.nombre || d.clues || cfg.label || 'Punto',
                    clues: d.clues || ''
                });
                announce = 'Se añadió ' + ms.points[ms.points.length - 1].name + '. ';
            }
            app.map.renderMeasure(state);

            // Anuncio para lector de pantalla: lo ocurrido y el resultado vigente
            const live = ms.panel.querySelector('.siarhe-sr-only');
            if (live && announce) {
                let result = '';
                if (ms.points.length > 1) {
                    if (ms.mode === 'radial') {
                        const rs = app.map._radialSummary(ms.points);
                        result = 'Más cercano: ' + app.map._formatDistance(rs.nearest.km) + '.';
                    } else {
                        result = 'Total: ' + app.map._formatDistance(app.map._measureSummary(ms.points).total) + '.';
                    }
                }
                live.textContent = announce + result;
            }
        },

        /** Dibuja la medicion sobre el mapa (etiquetas de tamano constante al hacer zoom) y actualiza el panel. */
        renderMeasure: function(state, kOverride) {
            const ms = state.measure;
            if (!ms || !state.gMeasure) return;

            const g = state.gMeasure;
            g.selectAll('*').remove();

            const pts = ms.points;
            const radial = ms.mode === 'radial';
            const metrics = app.map._markerMetrics(state, kOverride);
            const k = metrics.k;
            const f = metrics.isTouch ? 1.4 : 1;
            const xy = pts.map(p => state.projection([p.lon, p.lat]));
            const fmt = app.map._formatDistance;

            // Linea con contorno blanco + trazo punteado y su distancia en el punto medio
            const drawLeg = (a, b, km) => {
                if (!a || !b) return;
                const dPath = 'M' + a[0] + ',' + a[1] + 'L' + b[0] + ',' + b[1];
                g.append('path').attr('d', dPath).attr('fill', 'none')
                    .attr('stroke', '#ffffff').attr('stroke-width', 6 * f / k).attr('stroke-linecap', 'round');
                g.append('path').attr('d', dPath).attr('fill', 'none')
                    .attr('stroke', '#0F172A').attr('stroke-width', 2.5 * f / k)
                    .attr('stroke-dasharray', (9 * f / k) + ',' + (6 * f / k)).attr('stroke-linecap', 'round');
                g.append('text')
                    .attr('x', (a[0] + b[0]) / 2).attr('y', (a[1] + b[1]) / 2 - 8 * f / k)
                    .attr('text-anchor', 'middle')
                    .attr('paint-order', 'stroke').attr('stroke', '#ffffff').attr('stroke-width', 4 * f / k).attr('stroke-linejoin', 'round')
                    .style('font-size', (15 * f / k) + 'px').style('font-weight', 'bold').style('fill', '#0F172A')
                    .style('font-family', 'Roboto, Arial, sans-serif')
                    .text(fmt(km));
            };

            const chainSum = radial ? null : app.map._measureSummary(pts);
            const radialSum = radial ? app.map._radialSummary(pts) : null;

            if (radial) {
                radialSum.spokes.forEach(s => drawLeg(xy[0], xy[s.index], s.km));
            } else {
                for (let i = 1; i < pts.length; i++) drawLeg(xy[i - 1], xy[i], chainSum.segments[i - 1]);
            }

            // Puntos: en "desde un centro" el primero es el centro (relleno azul, "C"); los demas llevan su numero
            xy.forEach((c, i) => {
                if (!c) return;
                const isCenter = radial && i === 0;
                g.append('circle').attr('cx', c[0]).attr('cy', c[1]).attr('r', (isCenter ? 11 : 9) * f / k)
                    .attr('fill', isCenter ? '#0A66C2' : '#ffffff').attr('stroke', isCenter ? '#ffffff' : '#0A66C2')
                    .attr('stroke-width', 2.5 * f / k);
                g.append('text').attr('x', c[0]).attr('y', c[1]).attr('dy', '0.35em').attr('text-anchor', 'middle')
                    .style('font-size', (11 * f / k) + 'px').style('font-weight', 'bold').style('fill', isCenter ? '#ffffff' : '#0A66C2')
                    .style('font-family', 'Roboto, Arial, sans-serif').text(isCenter ? 'C' : (radial ? i : i + 1));
            });

            // ----- Panel -----
            const panel = ms.panel;
            const totalEl = panel.querySelector('.siarhe-measure-total');
            const hintEl = panel.querySelector('.siarhe-measure-hint');
            const listEl = panel.querySelector('.siarhe-measure-list');
            const detailsEl = panel.querySelector('.siarhe-measure-details');

            panel.querySelectorAll('button[data-mode]').forEach(b => {
                b.setAttribute('aria-pressed', b.getAttribute('data-mode') === ms.mode ? 'true' : 'false');
            });

            if (radial) {
                totalEl.textContent = radialSum.nearest
                    ? 'Más cercano: ' + fmt(radialSum.nearest.km) + (radialSum.spokes.length > 1 ? ' · Más lejano: ' + fmt(radialSum.farthest.km) : '')
                    : '';
            } else {
                totalEl.textContent = pts.length > 1 ? 'Total: ' + fmt(chainSum.total) : '';
            }

            let hasPosition = false;
            state.activeMarkers.forEach(type => { if ((state.markerLabels[type] || {}).tipo === 'posicion') hasPosition = true; });
            const max = app.map._measureMax(ms);
            let hint;
            if (ms.note) hint = ms.note;
            else if (!hasPosition) hint = 'Activa un marcador en "Marcadores (Posición)" para poder medir.';
            else if (radial) {
                if (pts.length === 0) hint = 'Toca el marcador que será el centro (por ejemplo, una escuela).';
                else if (pts.length === 1) hint = 'Ahora toca los marcadores a medir: cada uno se une al centro con su propia distancia.';
                else hint = 'Distancias independientes desde el centro. Toca más marcadores o toca uno ya elegido para quitarlo (máx. ' + max + ').';
            } else {
                if (pts.length === 0) hint = 'Toca un marcador de posición para iniciar la ruta.';
                else if (pts.length === 1) hint = 'Toca otro marcador para medir la distancia en línea recta.';
                else hint = 'Línea recta entre marcadores. Toca más para añadir tramos o toca uno ya elegido para quitarlo (máx. ' + max + ').';
            }
            hintEl.textContent = hint;

            listEl.innerHTML = '';
            const addLi = (text, cls) => {
                const li = document.createElement('li');
                if (cls) li.className = cls;
                li.textContent = text;
                listEl.appendChild(li);
            };
            if (radial) {
                if (pts.length > 0) addLi('Centro: ' + app.map._pointLabel(pts[0]), 'siarhe-measure-center');
                radialSum.sorted.forEach(s => addLi(s.index + '. ' + app.map._pointLabel(s.p) + ' → ' + fmt(s.km)));
            } else {
                pts.forEach((p, i) => {
                    if (i > 0) addLi('↓ ' + fmt(chainSum.segments[i - 1]), 'siarhe-measure-seg');
                    addLi((i + 1) + '. ' + app.map._pointLabel(p));
                });
            }
            detailsEl.style.display = pts.length > 1 ? '' : 'none';

            panel.querySelector('[data-act="undo"]').disabled = pts.length === 0;
            panel.querySelector('[data-act="clear"]').disabled = pts.length === 0;
            panel.querySelector('[data-act="copy"]').disabled = pts.length < 2;
            panel.querySelector('[data-act="download"]').disabled = pts.length < 2;
        },

        // Celda CSV: comillas si hace falta y, en texto libre, apostrofo si empieza como formula de Excel (= + - @)
        _csvCell: function(value, isText) {
            let t = (value === undefined || value === null) ? '' : String(value);
            if (isText && /^[=+\-@\t\r]/.test(t)) t = "'" + t;
            return /[",\r\n]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
        },

        /** Detalle de la medicion actual en CSV (una fila por punto), segun el tipo de medicion. */
        _measureCsv: function(state) {
            const ms = state.measure;
            const pts = ms.points;
            const cell = app.map._csvCell;
            const num = (v, d) => (typeof v === 'number' && isFinite(v)) ? v.toFixed(d) : '';
            const rows = [];

            if (ms.mode === 'radial') {
                const rs = app.map._radialSummary(pts);
                const rank = new Map(rs.sorted.map((s, i) => [s.index, i + 1]));
                rows.push(['tipo_medicion', 'rol', 'numero', 'nombre', 'clues', 'latitud', 'longitud', 'distancia_al_centro_km', 'ranking_cercania']);
                pts.forEach((p, i) => {
                    const spoke = i > 0 ? rs.spokes[i - 1] : null;
                    rows.push([
                        cell('desde_centro'), cell(i === 0 ? 'centro' : 'destino'), cell(i === 0 ? 'C' : i),
                        cell(p.name, true), cell(p.clues, true), num(p.lat, 6), num(p.lon, 6),
                        spoke ? num(spoke.km, 3) : '', spoke ? rank.get(spoke.index) : ''
                    ]);
                });
            } else {
                const sum = app.map._measureSummary(pts);
                rows.push(['tipo_medicion', 'orden', 'nombre', 'clues', 'latitud', 'longitud', 'tramo_km', 'acumulado_km']);
                let acc = 0;
                pts.forEach((p, i) => {
                    if (i > 0) acc += sum.segments[i - 1];
                    rows.push([
                        cell('ruta_encadenada'), i + 1, cell(p.name, true), cell(p.clues, true),
                        num(p.lat, 6), num(p.lon, 6), i > 0 ? num(sum.segments[i - 1], 3) : '', num(acc, 3)
                    ]);
                });
            }
            // BOM UTF-8 para que Excel respete acentos
            return '\ufeff' + rows.map(r => r.join(',')).join('\r\n') + '\r\n';
        },

        downloadMeasure: function(state, container) {
            const ms = state.measure;
            if (!ms || ms.points.length < 2) return;

            const pad = (n) => String(n).padStart(2, '0');
            const now = new Date();
            const stamp = now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '-' + pad(now.getHours()) + pad(now.getMinutes());
            const slug = (container && container.dataset && container.dataset.slug) ? container.dataset.slug : 'mapa';
            const kind = ms.mode === 'radial' ? 'desde_centro' : 'ruta';

            const blob = new Blob([app.map._measureCsv(state)], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'siarhe_medicion_' + kind + '_' + slug + '_' + stamp + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },

        copyMeasure: async function(state, btn) {
            const ms = state.measure;
            const fmt = app.map._formatDistance;
            const label = app.map._pointLabel;
            const lines = [];

            if (ms.mode === 'radial') {
                const rs = app.map._radialSummary(ms.points);
                lines.push('Medición desde un centro (línea recta)');
                lines.push('Centro: ' + label(ms.points[0]));
                rs.sorted.forEach(s => lines.push('   ' + s.index + '. ' + label(s.p) + ' → ' + fmt(s.km)));
                if (rs.nearest) lines.push('Más cercano: ' + label(rs.nearest.p) + ' (' + fmt(rs.nearest.km) + ')');
            } else {
                const sum = app.map._measureSummary(ms.points);
                lines.push('Medición de ruta (línea recta)');
                ms.points.forEach((p, i) => {
                    if (i > 0) lines.push('   → ' + fmt(sum.segments[i - 1]));
                    lines.push((i + 1) + '. ' + label(p));
                });
                lines.push('Total: ' + fmt(sum.total));
            }
            const text = lines.join('\n');

            const original = btn.textContent;
            try {
                await navigator.clipboard.writeText(text);
                btn.textContent = '¡Copiado!';
                setTimeout(() => { btn.textContent = original; }, 1800);
            } catch (err) {
                window.prompt('Copia el resultado:', text);
            }
        },

        renderZoomButtons: function(container, mapDiv, svg, zoom, state) {
            const ctrlDiv = document.createElement('div'); 
            ctrlDiv.className = 'zoom-controles'; 
            mapDiv.appendChild(ctrlDiv);
            
            const createBtn = (l, t, cb) => { 
                const b = document.createElement('button'); 
                b.className = 'boton'; b.innerHTML = l; b.title = t; b.setAttribute('aria-label', t); b.onclick = cb; 
                ctrlDiv.appendChild(b); 
                return b;
            };
            
            createBtn('+', 'Acercar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 1.5); });
            createBtn('–', 'Alejar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 0.6); });
            createBtn('⟳', 'Reset', (e) => { e.preventDefault(); svg.transition().duration(750).call(zoom.transform, state.initialTransform); });

            // Medir distancias: solo tiene sentido si existen marcadores de posicion configurados
            const hasPositionMarkers = Object.keys(state.markerLabels || {}).some(k => (state.markerLabels[k] || {}).tipo === 'posicion' && state.markerUrls && state.markerUrls[k]);
            if (hasPositionMarkers) {
                const rulerIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 17 17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/></svg>';
                const btnMeasure = createBtn(rulerIcon, 'Medir distancia entre marcadores de posición', (e) => {
                    e.preventDefault();
                    app.map.setMeasureMode(state, !state.measure.active);
                });
                btnMeasure.setAttribute('aria-pressed', 'false');
                app.map.initMeasure(container, state, mapDiv, btnMeasure);
                app.map.updateMeasureAvailability(state);
            }
            
            const btnFullscreen = document.createElement('button');
            btnFullscreen.className = 'boton'; 
            btnFullscreen.innerHTML = '⤢'; 
            btnFullscreen.title = 'Pantalla Completa';
            const mapContainer = mapDiv; 
            
            const btnShowControls = document.createElement('button');
            btnShowControls.className = 'boton btn-show-controls'; 
            btnShowControls.innerHTML = '🎛️'; 
            btnShowControls.title = 'Mostrar Controles';

            btnShowControls.onclick = (e) => {
                e.preventDefault();
                const controlsEl = container.querySelector('.siarhe-controls-layout');
                if (controlsEl) controlsEl.classList.remove('is-hidden');
                mapContainer.classList.remove('controls-are-hidden');
            };

            btnFullscreen.onclick = (e) => {
                e.preventDefault();
                
                const controlsEl = container.querySelector('.siarhe-controls-layout');

                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (mapContainer.requestFullscreen) { mapContainer.requestFullscreen(); } 
                    else if (mapContainer.webkitRequestFullscreen) { mapContainer.webkitRequestFullscreen(); } 
                    else if (mapContainer.msRequestFullscreen) { mapContainer.msRequestFullscreen(); }
                    
                    btnFullscreen.innerHTML = '⤡'; 
                    mapContainer.classList.add('is-fullscreen');
                    
                    mapContainer.classList.remove('controls-are-hidden'); 
                    if (controlsEl) {
                        mapContainer.appendChild(controlsEl);
                        controlsEl.classList.add('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                } else {
                    if (document.exitFullscreen) { document.exitFullscreen(); } 
                    else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
                    else if (document.msExitFullscreen) { document.msExitFullscreen(); }
                    
                    btnFullscreen.innerHTML = '⤢'; 
                    mapContainer.classList.remove('is-fullscreen');
                    mapContainer.classList.remove('controls-are-hidden');
                    
                    if (controlsEl) {
                        const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');
                        if(controlsPlaceholder) controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                }
            };

            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    btnFullscreen.innerHTML = '⤢'; 
                    mapContainer.classList.remove('is-fullscreen');
                    mapContainer.classList.remove('controls-are-hidden');
                    
                    const controlsEl = mapContainer.querySelector('.siarhe-controls-layout');
                    if (controlsEl) {
                        const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');
                        if(controlsPlaceholder) controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                }
            });
            
            ctrlDiv.appendChild(btnFullscreen);

            if (!state.isNacional && state.homeUrl) {
                createBtn('🏠', 'Ir a Nacional', (e) => { e.preventDefault(); window.location.href = state.homeUrl; });
            }

            ctrlDiv.appendChild(btnShowControls);
        },

        downloadMapAsPNG: function(container, state, withLabels) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            const svgNode = mapDiv.querySelector('svg');
            if (!svgNode) return;

            if (withLabels) state.gLabels.style("display", "block");

            setTimeout(() => {
                // Marcadores SVG fuera de pantalla pueden tener un tamano anterior: se actualizan todos antes de clonar
                try { app.map._refreshMarkerGeometry(state, d3.zoomTransform(svgNode), true); } catch (e) {}
                const clone = svgNode.cloneNode(true);

                // La capa superior (leyendas y medicion) y los marcadores del canvas viven fuera del SVG principal:
                // se incorporan al clon para que el PNG salga completo
                const ovlNode = state.ovl && state.ovl.node();
                if (ovlNode) {
                    ovlNode.querySelectorAll('.siarhe-legend-group, .siarhe-marker-legend-group').forEach(g => clone.appendChild(g.cloneNode(true)));
                    const mg = state.gMeasure && state.gMeasure.node();
                    const mapLayerEl = clone.querySelector('.map-layer');
                    if (mg && mapLayerEl) mapLayerEl.appendChild(mg.cloneNode(true));
                }
                const mkLayerEl = clone.querySelector('.markers-layer');
                if (mkLayerEl) app.map._cvMaterialize(state, mkLayerEl);

                // Lienzo ampliado: el mapa conserva su proporcion (antes se estiraba 1600x900 -> 1600x820)
                // y las leyendas se anclan a las esquinas del PNG con un tamano pensado para presentaciones.
                const areaW = 1920, areaH = 820;
                const vbW = areaW / (areaH / 900);
                const vbX = -(vbW - 1600) / 2;
                clone.setAttribute('viewBox', `${vbX} 0 ${vbW} 900`);
                clone.setAttribute('width', areaW);
                clone.setAttribute('height', areaH);

                const lb = state._legendBox || null;
                const mb = (state.activeMarkers && state.activeMarkers.size > 0) ? state._markerBox : null;
                const padU = 28;
                const totalH = (lb ? lb.h : 0) + (mb ? mb.h : 0);
                let L = 1.5; // ~22 px de texto en el PNG final
                if (totalH > 0) L = Math.min(L, ((900 - 2 * padU) * 0.92) / totalH);

                const legendEl = clone.querySelector('.siarhe-legend-group');
                const markerLegendEl = clone.querySelector('.siarhe-marker-legend-group');
                if (legendEl && lb) legendEl.setAttribute('transform', `translate(${vbX + padU + lb.ox * L}, ${padU + lb.oy * L}) scale(${L})`);
                if (markerLegendEl && mb) markerLegendEl.setAttribute('transform', `translate(${vbX + padU + mb.ox * L}, ${900 - padU}) scale(${L})`);

                const svgData = new XMLSerializer().serializeToString(clone);
                const canvas = document.createElement("canvas");
                canvas.width = 1920; 
                canvas.height = 1080; 
                const ctx = canvas.getContext("2d"); 

                const img = new Image();
                img.onload = function() {
                    ctx.fillStyle = "#e6f0f8"; ctx.fillRect(0, 0, 1920, 1080); 
                    ctx.fillStyle = "#ffffff"; ctx.fillRect(0, 0, 1920, 120); 
                    ctx.fillRect(0, 940, 1920, 140); 
                    
                    ctx.drawImage(img, 0, 120, 1920, 820); 
                    
                    const metricInfo = state.metricas[state.currentMetric];
                    const anioNode = document.querySelector('.siarhe-dynamic-year'); 
                    const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                    const titleNode = container.querySelector('h2.siarhe-title'); 
                    const entidadNombre = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "México";
                    
                    const titleText = `${metricInfo.fullLabel} en ${entidadNombre} (${anio})`;
                    ctx.fillStyle = "#0A66C2"; 
                    ctx.font = "bold 38px 'IBM Plex Sans', Arial, sans-serif"; 
                    ctx.textAlign = "center"; 
                    ctx.fillText(titleText, 1920 / 2, 75);
                    
                    let refText = "Fuente de Datos: SIARHE.";
                    const refNodes = container.querySelectorAll('.siarhe-ref-col');
                    refNodes.forEach(node => {
                        if (node.innerHTML.includes('dashicons-groups') || node.innerHTML.includes('Datos de Enfermería')) {
                            const pTag = node.querySelector('p');
                            if (pTag) refText = pTag.innerText.replace(/\n/g, ' | ');
                        }
                    });

                    ctx.fillStyle = "#475569"; 
                    ctx.font = "20px 'Roboto', sans-serif"; 
                    ctx.textAlign = "center";
                    
                    let words = refText.split(' '), line = '', y = 1000;
                    for(let n = 0; n < words.length; n++) {
                      let testLine = line + words[n] + ' '; 
                      let metrics = ctx.measureText(testLine); 
                      let testWidth = metrics.width;
                      if (testWidth > 1800 && n > 0) { 
                          ctx.fillText(line, 1920 / 2, y); line = words[n] + ' '; y += 28;
                      } else { line = testLine; }
                    }
                    ctx.fillText(line, 1920 / 2, y);
                    
                    const labelClean = (metricInfo.label || metricInfo.fullLabel).normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\./g, "").replace(/\s+/g, '_');
                    const entidadClean = entidadNombre.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '_');
                    const nombreArchivo = `${labelClean}_${entidadClean}_${anio}${withLabels ? '_Etiquetas' : ''}.png`;
                    
                    const a = document.createElement("a"); a.download = nombreArchivo; a.href = canvas.toDataURL("image/png"); a.click();
                    if (withLabels) state.gLabels.style("display", "none");
                };
                img.src = "data:image/svg+xml;base64," + btoa(unescape(encodeURIComponent(svgData)));
            }, 150);
        }
    };

})(window.SiarheDataViz);