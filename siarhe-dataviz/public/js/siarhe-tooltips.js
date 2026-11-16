/**
 * SIARHE DataViz - Módulo de Tooltips
 * ------------------------------------------------------------------
 * Gestiona exclusivamente la construcción y renderizado del HTML 
 * de los tooltips flotantes para polígonos (GeoJSON) y marcadores.
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.tooltips = {

        /**
         * Coloca el tooltip junto al puntero sin que se salga del contenedor del mapa:
         * a la derecha por defecto; si no cabe, pasa a la izquierda; si no cabe abajo, sube;
         * si no cabe arriba, baja. Sirve igual para mouse y toque (movil).
         */
        position: function(state, mapDiv, mx, my) {
            const tip = state.tooltip && state.tooltip.node();
            if (!tip || !mapDiv || state._fijo) return;   // la ficha fija de un marcador elegido no sigue al puntero
            const W = mapDiv.clientWidth, H = mapDiv.clientHeight, M = 6;
            tip.style.maxWidth = Math.max(160, Math.min(320, W - 2 * M)) + 'px';
            const tw = tip.offsetWidth, th = tip.offsetHeight;
            if (!tw || !th) return;

            let left = mx + 15;
            if (left + tw > W - M) left = mx - 15 - tw;
            let top = my - 28;
            if (top + th > H - M) top = my - th - 12;
            if (top < M) top = my + 18;

            left = Math.max(M, Math.min(left, W - tw - M));
            top = Math.max(M, Math.min(top, H - th - M));
            state.tooltip.style('left', left + 'px').style('top', top + 'px');
        },

        /**
         * Tooltip de un estado o municipio con la estructura de RHEMX (`textoTooltip`): un estado lleva su nombre; un municipio,
         * el estado como nombre y debajo «Municipio: …». Tras una línea, la población, el indicador elegido (con el total, «Plazas
         * de enfermería» o «Recursos Humanos de Enfermería») y la tasa por 1,000 habitantes; y, tras otra, el aviso del clic.
         * Las etiquetas van en el color de Configuración › Tooltips; el orden y qué datos se muestran también salen de ahí.
         */
        showGeoTooltip: function(event, d, state, mapDiv) {
            if (state._fijo) return;   // no tapa la ficha fija del marcador elegido
            const esc = app.utils.esc;
            const p = d.properties || {};
            const cve = app.utils.getGeoKey(p, state.isNacional);
            let row = state.dataMap.get(cve);
            const wrapper = mapDiv.closest('.siarhe-viz-wrapper');
            // Los nombres van como vienen (los de RHEMX ya traen mayúsculas y acentos); solo los que llegan en mayúsculas se ajustan
            const nom = function(v) { v = String(v || ''); return /[a-zà-ÿ]/.test(v) ? v : app.utils.titleCase(v); };

            // En la capa detallada (municipios de la República o localidades de un estado) el plugin no tiene cifras propias:
            // se muestran las del lugar que la contiene, con un aviso
            let deQuien = '';
            if (!row && state.isGeoLocMode && p.CVE_ENT) {
                row = state.dataMap.get(state.isNacional ? String(p.CVE_ENT).padStart(2, '0') : String(p.CVE_ENT).padStart(2, '0') + String(p.CVE_MUN || '').padStart(3, '0'));
                if (row) deQuien = state.isNacional ? 'Cifras del estado' : 'Cifras del municipio';
            }

            const tt = state.tooltipConfig || {};
            const order = tt.geo_order || ['pob', 'abs', 'rate'];
            const lblColor = tt.highlight_color || '#06b6d4';
            const bgColor = tt.bg_color || '#0f172a';
            const bgOpacity = (tt.bg_opacity !== undefined ? parseInt(tt.bg_opacity, 10) : 90) / 100;
            const txtColor = tt.text_color || '#f8fafc';
            const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);

            const sep = '<div class="siarhe-ficha-sep"></div>';
            const fila = function(etq, val) {
                return (val === '' || val === null || val === undefined) ? '' :
                    '<div class="siarhe-ficha-fila"><span style="color:' + lblColor + ';">' + esc(etq) + ':</span> <strong>' + esc(val) + '</strong></div>';
            };
            const nota = function(t) { return '<div class="siarhe-ficha-fila"><em>' + esc(t) + '</em></div>'; };
            const fmtN = function(v) { return (v === null || v === undefined || v === '' || isNaN(Number(v))) ? 'S/D' : Number(v).toLocaleString('es-MX'); };

            // Encabezado: la entidad como nombre y, debajo, el municipio y la localidad
            const lugarPagina = wrapper ? (wrapper.dataset.lugar || '') : '';
            let cabecera;
            if (state.isNacional && !state.isGeoLocMode) {
                cabecera = '<div class="siarhe-ficha-nombre">' + esc(nom(row ? row.estado : (p.NOMGEO || p.NOM_ENT || 'S/D'))) + '</div>';
            } else if (state.isNacional) {
                cabecera = '<div class="siarhe-ficha-nombre">' + esc(nom(row ? row.estado : (p.NOM_ENT || 'S/D'))) + '</div>' +
                    fila('Municipio', nom(p.NOMGEO || p.NOM_MUN || p.MUNICIPIO || 'S/D'));
            } else if (!state.isGeoLocMode) {
                cabecera = '<div class="siarhe-ficha-nombre">' + esc(lugarPagina || 'S/D') + '</div>' +
                    fila('Municipio', nom(row ? row.estado : (p.NOMGEO || p.NOM_MUN || 'S/D')));
            } else {
                cabecera = '<div class="siarhe-ficha-nombre">' + esc(lugarPagina || 'S/D') + '</div>' +
                    fila('Municipio', nom(row ? row.estado : (p.NOM_MUN || p.MUNICIPIO || 'S/D'))) +
                    fila('Localidad', nom(p.NOMGEO || p.NOM_LOC || p.LOCALIDAD || ''));
            }

            // Datos: población, el indicador elegido y su tasa (calculada como en RHEMX si la base no la trae)
            const mKey = state.currentMetric;
            const info = state.metricas[mKey] || {};
            const absKey = info.tipo === 'absoluto' ? mKey : (app.utils.findAbsoluteKeyForPair(info.pair || mKey, state.metricas) || info.pair || null);
            const pob = row ? row.poblacion : null;
            const blocks = {};
            if (tt.geo_pob !== false) blocks.pob = fila('Población', fmtN(pob));
            if (mKey === 'poblacion') {
                blocks.abs = fila(app.cifras.unidad(state).etiqueta, fmtN(row ? row[app.cifras.TOTAL] : null));
            } else {
                const cnt = row && absKey ? row[absKey] : null;
                if (tt.geo_abs !== false) blocks.abs = fila(app.cifras.nombreVariable(state), fmtN(cnt));
                if (tt.geo_rate !== false) {
                    const tasaKey = info.tipo === 'tasa' ? mKey : (absKey ? app.utils.findRateKeyForAbsolute(absKey, state.metricas) : null);
                    let tasa = row && tasaKey ? row[tasaKey] : null;
                    if ((tasa === null || tasa === undefined) && pob > 0 && cnt !== null && cnt !== undefined) tasa = (Number(cnt) / pob) * 1000;
                    blocks.rate = fila('Tasa por 1,000 habitantes', (tasa === null || tasa === undefined || isNaN(tasa)) ? 'S/D' : app.utils.fmtTasa(tasa));
                }
            }
            let datos = deQuien ? nota(deQuien) : '';
            order.forEach(function(k) { if (blocks[k]) datos += blocks[k]; });
            if (mKey === 'poblacion' && blocks.abs && order.indexOf('abs') === -1) datos += blocks.abs;
            // Un estado dice además cuántos municipios tiene (contados en la capa 2 de la República, como RHEMX en sus datos)
            if (state.isNacional && !state.isGeoLocMode) {
                state._ultimoGeo = { d: d, x: event.clientX, y: event.clientY };
                const porEnt = app.tooltips.municipiosPorEntidad(state, wrapper, mapDiv);
                const n = porEnt ? porEnt.get(String(cve).padStart(2, '0')) : 0;
                if (n) datos += fila('Municipios', fmtN(n));
            }
            // Comparación de dos años (textoTooltip de RHEMX con `vista.cambio`): el indicador, su valor en cada año y el cambio
            const cmb = app.cambio ? app.cambio.calcula(state) : null;
            if (cmb && row) {
                const C = app.cambio, c = cmb.mapa.get(C._clave(state, row)) || { delta: null };
                const anio = String((state.base && state.base.anio) || ''), medida = C.rotuloMedida(state);
                const avisos = (c.nuevo ? nota('Municipio nuevo: no existía en ' + state.comparar + '; se toma como 0')
                    : c.sinDatoBase ? nota('Sin dato en ' + state.comparar + ': se toma como 0') : c.sinDatoMapa ? nota('Sin dato en ' + anio + ': se toma como 0') : '') +
                    (c.clase === 'igual' && !c.ceros ? nota('Sin cambio importante (menos de ' + C.UMBRAL + ' %)') : '');
                datos = (deQuien ? nota(deQuien) : '') + '<div class="siarhe-ficha-fila"><strong>' + esc(app.cifras.nombreVariable(state)) + '</strong></div>' +
                    fila(medida + ', ' + state.comparar, C.fmtMedida(state, c.v2)) + fila(medida + ', ' + anio, C.fmtMedida(state, c.v1)) +
                    fila('Cambio', C.fmtCambio(state, c.delta) + ' (' + C.fmtPctDe(c) + ')') + avisos;
            }

            // Aviso del clic: en la República, un estado con página propia se abre con doble clic
            let pista = '';
            if (state.isNacional && !state.isGeoLocMode && app.urlState && app.urlState.enlaceEntidad && app.urlState.enlaceEntidad(state, cve)) {
                pista = sep + nota('Doble clic para ver sus municipios');
            }

            let htmlResumenMarcadores = '';
            try {
                if (state.activeMarkers && state.activeMarkers.size > 0) {
                    const mapCveEnt = wrapper && wrapper.dataset.cveEnt ? wrapper.dataset.cveEnt.toString().padStart(2, '0') : '';
                    const mapCveMun = (state.isGeoLocMode && d.properties.CVE_MUN) ? d.properties.CVE_MUN.toString().padStart(3, '0') : cve;

                    state.activeMarkers.forEach(type => {
                        const config = state.markerLabels[type] || {};
                        
                        if (config.resumen_geo === true || config.resumen_geo === 'true') {
                            const markerData = state.markersData[type] || [];
                            
                            let totalPuntos = 0;
                            let totalRegistros = 0;
                            let sumReglas = {};

                            markerData.forEach(mk => {
                                let match = false;
                                const mkEnt = mk.cve_ent ? mk.cve_ent.toString().padStart(2, '0') : null;

                                if (state.isNacional) {
                                    if (mkEnt === cve) match = true;
                                } else {
                                    let mkMun = null;
                                    if (mk._raw) {
                                        mkMun = app.utils.getColValue(mk._raw, ['cve_mun', 'cve mun', 'clave del municipio', 'municipio', 'clave_municipio']);
                                        if (mkMun) mkMun = mkMun.toString().padStart(3, '0');
                                    }
                                    if (mkEnt === mapCveEnt && mkMun === mapCveMun) match = true;
                                }

                                if (match) {
                                    totalPuntos++;
                                    totalRegistros += (mk._agrupados_total !== undefined) ? mk._agrupados_total : 1;

                                    if (mk._conteo_reglas) {
                                        Object.entries(mk._conteo_reglas).forEach(([rLabel, rCount]) => {
                                            if (!sumReglas[rLabel]) sumReglas[rLabel] = 0;
                                            sumReglas[rLabel] += rCount;
                                        });
                                    }
                                }
                            });

                            if (totalPuntos > 0) {
                                const mkStyle = app.utils.getMarkerStyle(type, state);
                                const mkLabelColor = mkStyle.fill.startsWith('rgba') ? mkStyle.fill.replace(/[^,]+(?=\))/, '1') : mkStyle.fill;
                                const mkLabelNombre = config.label || type;

                                htmlResumenMarcadores += `
                                    <div style="margin-top:12px; padding-top:8px; border-top:1px dashed rgba(255,255,255,0.3);">
                                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; color:${mkLabelColor}; font-weight:bold; display:flex; align-items:center; gap:5px;">
                                            <div style="width:8px; height:8px; background:${mkLabelColor}; border-radius:50%;"></div>
                                            ${app.utils.esc(mkLabelNombre)}
                                        </div>
                                `;

                                htmlResumenMarcadores += `
                                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px;">
                                        <span style="opacity:0.8;">Puntos Físicos (Unidades):</span> 
                                        <strong>${totalPuntos.toLocaleString('es-MX')}</strong>
                                    </div>`;

                                htmlResumenMarcadores += `
                                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;">
                                        <span style="opacity:0.9;">Total Registros:</span> 
                                        <strong style="color:#f8fafc;">${totalRegistros.toLocaleString('es-MX')}</strong>
                                    </div>`;

                                if (Object.keys(sumReglas).length > 0) {
                                    Object.entries(sumReglas).forEach(([rLabel, rVal]) => {
                                        if (rVal > 0) {
                                            htmlResumenMarcadores += `
                                                <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px; padding-left:10px; border-left:1px solid rgba(255,255,255,0.1);">
                                                    <span style="opacity:0.75;">↳ ${app.utils.esc(rLabel)}:</span> 
                                                    <strong style="color:#f8fafc; font-size:10px;">${rVal.toLocaleString('es-MX')}</strong>
                                                </div>`;
                                        }
                                    });
                                }
                                htmlResumenMarcadores += `</div>`;
                            }
                        }
                    });
                }
            } catch (error) {
                console.error("[SIARHE] Error calculando resumen geográfico:", error);
            }

            const html = '<div class="siarhe-ficha" style="color:' + txtColor + ';">' + cabecera + sep + datos + htmlResumenMarcadores + pista + '</div>';
            
            if (mapDiv) {
                const [mx, my] = d3.pointer(event, mapDiv);
                state.tooltip.html(html)
                    .style("background-color", finalBgColor)
                    .style("color", txtColor)
                    .style("border-radius", "6px")
                    .style("padding", "10px 14px")
                    .style("box-shadow", "0 4px 15px rgba(0,0,0,0.2)")
                    .style("display", "block")
                    .style("opacity", 1);
                app.tooltips.position(state, mapDiv, mx, my);
            }
        },

        /**
         * Municipios de cada estado (Map clave de entidad → número) contados en la capa 2 de la República (municipios del país).
         * Esa capa pesa ~2 MB y el plugin no la baja al abrir la página (PageSpeed): la pide la primera vez que se pasa por un
         * estado y, al llegar, vuelve a dibujar el tooltip si sigue a la vista. Mientras tanto devuelve null (sin la fila).
         */
        municipiosPorEntidad: function(state, container, mapDiv) {
            const capa = state.locGeoData;
            if (capa && Array.isArray(capa.features) && capa.features.length) {
                if (state._munPorEnt && state._munPorEnt.capa === capa) return state._munPorEnt.mapa;
                const mapa = new Map();
                capa.features.forEach(function(f) {
                    const p = f.properties || {};
                    const e = String(p.CVE_ENT || String(p.CVEGEO || '').slice(0, 2)).padStart(2, '0');
                    mapa.set(e, (mapa.get(e) || 0) + 1);
                });
                state._munPorEnt = { capa: capa, mapa: mapa };
                return mapa;
            }
            const url = state.geoLocUrl;
            if (!url || !container || state._pideMunicipios || state._municipiosFallo === url || !(app.controls && app.controls.loadLocGeo)) return null;
            state._pideMunicipios = app.controls.loadLocGeo(container, state, url).then(function(data) {
                state._pideMunicipios = null;
                if (!(data && Array.isArray(data.features) && data.features.length)) { state._municipiosFallo = url; return; }
                if (state.geoLocUrl === url && !state.locGeoData) state.locGeoData = data;
                // Si el puntero sigue sobre un estado, el tooltip se vuelve a dibujar ya con los municipios
                const u = state._ultimoGeo, tip = state.tooltip && state.tooltip.node();
                if (u && tip && tip.style.display === 'block' && !state._fijo && !state.isGeoLocMode) {
                    app.tooltips.showGeoTooltip({ clientX: u.x, clientY: u.y }, u.d, state, mapDiv);
                }
            }).catch(function() { state._pideMunicipios = null; state._municipiosFallo = url; });
            return null;
        },

        showMarkerTooltip: function(event, d, state, mapDiv) {
            const card = app.tooltips.markerCard(d, state);
            const [mx, my] = d3.pointer(event, mapDiv);

            state.tooltip.html(card.html)
                .style("background-color", card.bg)
                .style("color", card.txt)
                .style("border-radius", "6px")
                .style("padding", "10px 14px")
                .style("box-shadow", "0 4px 15px rgba(0,0,0,0.2)")
                .style("display", "block")
                .style("opacity", 1);
            app.tooltips.position(state, mapDiv, mx, my);
        },

        /** Contenido (HTML) y colores de la tarjeta de un marcador; lo usan el tooltip y el panel de marcadores apilados. */
        /** Marcador del sistema (establecimientos de salud, clínicas o los virtuales de «Marcadores de posición»). */
        esDelSistema: function(d, state) {
            const tipo = String(d && d.tipo || '');
            if (tipo.charAt(0) === '@') return true;
            const m = (state.markerLabels || {})[tipo] || {};
            return !!(m.sistema || m.grupo === 'establecimientos' || m.grupo === 'clinicas');
        },

        /**
         * Ficha de una unidad del sistema con la estructura de RHEMX (`fichaMarcador`): el nombre; institución, CLUES (y la
         * anterior), tipo, nivel y personal de enfermería; tras una línea, jurisdicción, ubicación y zona; y, tras otra, lo propio
         * de la clínica (catéteres y heridas). Los textos van como vienen de RHEMX (sin cambiar mayúsculas). Los colores son los
         * de Configuración › Tooltips.
         */
        fichaSistema: function(d, state, colores) {
            const esc = app.utils.esc, raw = d._raw || {}, col = function(nombres) { return app.utils.getColValue(raw, nombres); };
            const fila = function(etq, val) {
                return (val === '' || val === null || val === undefined) ? '' :
                    '<div class="siarhe-ficha-fila"><span style="color:' + colores.etiqueta + ';">' + esc(etq) + ':</span> <strong style="color:' + colores.valor + ';">' + esc(val) + '</strong></div>';
            };
            const sep = '<div class="siarhe-ficha-sep"></div>';
            const personas = state.base && state.base.unidad === 'personas';
            const n = app.utils.parseNum(col(personas ? ['recursos humanos estimados', 'personas'] : ['plazas de enfermeria', 'plazas']));
            const tipo = [d.tipo_estab, d.tipologia].filter(Boolean).join(' - ');
            // Ubicación como RHEMX: entidad, municipio y localidad (las capas de RHEMX no traen el nombre de la entidad: sale de su clave)
            let entidad = col(['entidad', 'nom_ent']);
            if (!entidad) {
                const ce = d.cve_ent ? String(d.cve_ent).padStart(2, '0') : '';
                const filaEnt = state.isNacional && ce && state.dataMap ? state.dataMap.get(ce) : null;
                entidad = filaEnt ? filaEnt.estado : (!state.isNacional ? state.lugar : '');
            }
            const ubicacion = [entidad, d.municipio, col(['localidad', 'nom_loc'])].filter(Boolean).join(', ');
            let zona = String(d.estrato || '').trim();
            if (zona === '1') zona = 'Rural'; else if (zona === '2') zona = 'Urbano';
            let html = '<div class="siarhe-ficha-nombre">' + esc(d.nombre || d.clues || 'Unidad') + '</div>' +
                fila('Institución', d.institucion) + fila('CLUES', d.clues) + fila('CLUES anterior', col(['clues anterior', 'anterior'])) +
                fila('Tipo', tipo) + fila('Nivel', d.nivel_atencion) +
                (n === null ? '' : fila(personas ? 'Recursos Humanos de Enfermería' : 'Personal de Enfermería', n.toLocaleString('es-MX'))) +
                sep + fila('Jurisdicción', d.jurisdiccion) + fila('Ubicación', ubicacion) + fila('Zona', zona);
            // Lo propio de cada clínica: catéteres (reportes y, en toda la historia, los años con reportes) y heridas
            const secciones = [];
            const reportes = app.utils.parseNum(col(['reportes']));
            if (reportes !== null) {
                const anios = col(['anios reportados', 'anios']), primer = col(['primer anio', 'primer']), ultimo = col(['ultimo anio', 'ultimo']);
                secciones.push('<div class="siarhe-ficha-fila"><strong>Clínica de catéteres</strong></div>' +
                    fila(anios ? 'Reportes de catéteres (toda la historia)' : 'Reportes de catéteres' + (state.base && state.base.anio ? ' en ' + state.base.anio : ''), reportes.toLocaleString('es-MX')) +
                    (anios ? fila('Años con reportes', anios + (primer && ultimo ? ' (' + primer + '–' + ultimo + ')' : '')) : ''));
            }
            const reporta = col(['reporta']);
            if (reporta !== '' && reporta !== null && reporta !== undefined && String(reporta) !== '-1') {
                secciones.push('<div class="siarhe-ficha-fila"><strong>Clínica de heridas</strong></div>' +
                    fila('Reporta en la plataforma de heridas', /^(1|true|s[ií])$/i.test(String(reporta)) ? 'Sí' : 'No'));
            }
            if (secciones.length) html += sep + secciones.join(sep);
            return html;
        },

        markerCard: function(d, state) {
            const tc = function(v) { return app.utils.titleCase(v); };
            const tt = state.tooltipConfig || {};
            const hlVar = tt.mk_highlight_var || 'none';
            const hlColor = tt.mk_highlight_color || '#06b6d4';
            const bgColor = tt.mk_bg_color || '#0f172a';
            const bgOpacity = (tt.mk_bg_opacity !== undefined ? parseInt(tt.mk_bg_opacity, 10) : 90) / 100;
            const txtColor = tt.mk_text_color || '#f8fafc';
            const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);
            // Los marcadores del sistema llevan la ficha de RHEMX; los propios del sitio, la configurable de abajo
            if (app.tooltips.esDelSistema(d, state)) {
                const html = '<div class="siarhe-ficha" style="color:' + txtColor + ';">' + app.tooltips.fichaSistema(d, state, { etiqueta: '#06B6D4', valor: txtColor }) + '</div>';
                return { html: html, bg: finalBgColor, txt: txtColor };
            }
            const mkOrder = (tt.mk_order || ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun']).slice();
            // CLUES anterior y personal de enfermería de la unidad (como en RHEMX): van tras la CLUES y tras el nivel aunque el orden
            // guardado en Configuración › Tooltips sea anterior a estos datos
            [['mk_anterior', 'mk_clues'], ['mk_personal', 'mk_nivel']].forEach(function(par) {
                if (mkOrder.indexOf(par[0]) !== -1) return;
                const i = mkOrder.indexOf(par[1]);
                mkOrder.splice(i === -1 ? mkOrder.length : i + 1, 0, par[0]);
            });

            let zonaTexto = "";
            if (d.estrato) {
                const estLower = d.estrato.toString().toLowerCase().trim();
                if (estLower === '1' || estLower === 'rural') zonaTexto = "Rural";
                else if (estLower === '2' || estLower === 'urbano') zonaTexto = "Urbana";
                else zonaTexto = tc(d.estrato);
            }
            const titleLabel = zonaTexto ? `Establecimiento en Zona ${zonaTexto}` : (state.markerLabels[d.tipo] ? state.markerLabels[d.tipo].label : d.tipo);

            let blocks = {};
            
            if (tt.mk_inst !== false && d.institucion) blocks['mk_inst'] = { label: 'Institución', value: tc(d.institucion) };
            if (tt.mk_clues !== false && d.clues) blocks['mk_clues'] = { label: 'CLUES', value: d.clues };
            if (tt.mk_nivel !== false && d.nivel_atencion) blocks['mk_nivel'] = { label: 'Nivel', value: tc(d.nivel_atencion) };
            if (d._raw) {
                const anterior = app.utils.getColValue(d._raw, ['clues anterior', 'anterior']);
                if (tt.mk_clues !== false && anterior) blocks['mk_anterior'] = { label: 'CLUES anterior', value: anterior };
                // Plazas o recursos humanos de enfermería que el SIARHE registra en la unidad, según la unidad de conteo elegida
                const personas = state.base && state.base.unidad === 'personas';
                const n = app.utils.parseNum(app.utils.getColValue(d._raw, personas ? ['recursos humanos estimados', 'personas'] : ['plazas de enfermeria', 'plazas']));
                if (n !== null) blocks['mk_personal'] = { label: personas ? 'Recursos humanos de enfermería' : 'Plazas de enfermería', value: n.toLocaleString('es-MX') };
            }
            if (tt.mk_juris !== false && d.jurisdiccion) blocks['mk_juris'] = { label: 'Jurisdicción', value: tc(d.jurisdiccion) };
            
            if (tt.mk_tipo !== false && (d.tipo_estab || d.tipologia)) {
                let valTipo = [];
                if (d.tipo_estab) valTipo.push(tc(d.tipo_estab));
                if (d.tipologia) valTipo.push(tc(d.tipologia));
                blocks['mk_tipo'] = { label: 'Tipo', value: valTipo.join(' - ') };
            }
            
            if (tt.mk_mun !== false && (d._raw)) {
                let ubicacion = [];
                const colEntidad = app.utils.getColValue(d._raw, ['entidad', 'nom_ent']);
                const colLocalidad = app.utils.getColValue(d._raw, ['localidad', 'nom_loc']);
                if (colEntidad) ubicacion.push(tc(colEntidad));
                if (d.municipio) ubicacion.push(tc(d.municipio));
                if (colLocalidad) ubicacion.push(tc(colLocalidad));
                if (ubicacion.length > 0) blocks['mk_mun'] = { label: 'Ubicación', value: ubicacion.join(', ') };
            }

            let htmlStandard = '';
            let htmlHighlight = '';

            mkOrder.forEach(itemKey => {
                if (itemKey === 'mk_separator') {
                    htmlStandard += `<div style="border-top: 1px dashed rgba(255,255,255,0.2); margin: 6px 0;"></div>`;
                    return;
                }

                if (!blocks[itemKey]) return; 
                const b = blocks[itemKey];

                if (hlVar === itemKey) {
                    htmlHighlight = `
                        <div style="margin-top:6px; padding-top:6px; border-top:1px solid rgba(255,255,255,0.2);">
                            <span style="font-size:11px; opacity:0.8;">${app.utils.esc(b.label)}:</span><br>
                            <strong style="color:${hlColor}; font-size:12px;">${app.utils.esc(b.value)}</strong>
                        </div>`;
                } else {
                    htmlStandard += `
                        <div style="font-size:11px; line-height: 1.4; margin-bottom: 3px;">
                            <span style="color:#06B6D4; opacity:0.9;">${app.utils.esc(b.label)}:</span> 
                            <strong style="color:${txtColor}; font-family:'IBM Plex Sans', sans-serif;">${app.utils.esc(b.value)}</strong>
                        </div>`;
                }
            });

            // Clínica de catéteres: reportes del periodo y, en la capa de toda la historia, los años con reportes (como en RHEMX)
            let htmlClinica = '';
            if (d._raw) {
                const reportes = app.utils.parseNum(app.utils.getColValue(d._raw, ['reportes']));
                if (reportes !== null) {
                    const anios = app.utils.getColValue(d._raw, ['anios reportados', 'anios']);
                    const primer = app.utils.getColValue(d._raw, ['primer anio', 'primer']), ultimo = app.utils.getColValue(d._raw, ['ultimo anio', 'ultimo']);
                    const fila = function(etq, val) {
                        return `<div style="font-size:11px; line-height: 1.4; margin-bottom: 3px;"><span style="color:#06B6D4; opacity:0.9;">${app.utils.esc(etq)}:</span> <strong style="color:${txtColor};">${app.utils.esc(val)}</strong></div>`;
                    };
                    htmlClinica = `<div style="border-top: 1px dashed rgba(255,255,255,0.2); margin: 6px 0;"></div><div style="font-size:11px; font-weight:700; margin-bottom:3px;">Clínica de catéteres</div>` +
                        fila(anios ? 'Reportes de catéteres (toda la historia)' : 'Reportes de catéteres', reportes.toLocaleString('es-MX')) +
                        (anios ? fila('Años con reportes', anios + (primer && ultimo ? ' (' + primer + '–' + ultimo + ')' : '')) : '');
                }
            }

            let htmlReglas = '';
            if (d._agrupados_total !== undefined && d._agrupados_total > 0) {
                htmlReglas += `<div style="margin-top:8px; padding-top:8px; border-top:1px dashed rgba(255,255,255,0.3);">`;
                htmlReglas += `<div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; opacity:0.7;">Estadísticas del Punto</div>`;
                
                htmlReglas += `<div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:3px;">
                                <span style="opacity:0.9;">Total Registros:</span> 
                                <strong style="color:#f8fafc;">${d._agrupados_total.toLocaleString('es-MX')}</strong>
                               </div>`;

                if (d._conteo_reglas) {
                    Object.entries(d._conteo_reglas).forEach(([ruleLabel, ruleCount]) => {
                        if (ruleCount > 0) {
                            htmlReglas += `<div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:3px;">
                                            <span style="opacity:0.9;">${app.utils.esc(ruleLabel)}:</span> 
                                            <strong style="color:#f8fafc;">${ruleCount.toLocaleString('es-MX')}</strong>
                                           </div>`;
                        }
                    });
                }
                htmlReglas += `</div>`;
            }

            let html = `<div style="font-family:'Roboto', sans-serif; color:${txtColor};">`;
            html += `<div style="color: #06B6D4; font-weight:bold; font-size:12px; margin-bottom:4px; padding-bottom:4px;">${app.utils.esc(titleLabel)}</div>`;
            html += `<div style="font-weight:bold; font-size:13px; font-family:'IBM Plex Sans', sans-serif; margin-bottom: 8px;">${app.utils.esc(tc(d.nombre) || 'Desconocido')}</div>`;
            // Spike: el valor que define la altura y el color del pico
            const mkCfg = state.markerLabels[d.tipo] || {};
            if (mkCfg.tipo === 'spike' && app.map && app.map._markerValue) {
                const spikeVal = app.map._markerValue(d, mkCfg, state, d.tipo);
                const colName = (mkCfg.espectro_col && mkCfg.espectro_col.trim()) ? mkCfg.espectro_col.trim() : 'Registros';
                html += `<div style='font-size:11px; margin-bottom:6px;'><span style='color:#06B6D4;'>${app.utils.esc(colName)}:</span> <strong>${Number(spikeVal).toLocaleString('es-MX')}</strong></div>`;
            }
            html += htmlStandard;
            html += htmlHighlight;
            html += htmlClinica;
            html += htmlReglas; 
            html += `</div>`;

            return { html: html, bg: finalBgColor, txt: txtColor };
        },

        /**
         * Panel de marcadores apilados (los que se tapan entre si en el mismo punto). A diferencia del tooltip, recibe toques:
         * flechas para pasar al anterior o siguiente, una lista numerada para saltar a cualquiera y, con la medicion
         * activa, un boton para agregar o quitar el marcador mostrado. `stack` = { list, idx, ax, ay } (ax, ay: px dentro del mapa).
         */
        showStackCard: function(state, mapDiv, stack) {
            const esc = app.utils.esc;
            let el = state.stackCard;
            if (!el || !el.isConnected) {
                el = state.stackCard = document.createElement('div');
                el.className = 'siarhe-stack-card';
                el.setAttribute('role', 'dialog');
                el.setAttribute('aria-label', 'Marcadores encimados');
                mapDiv.appendChild(el);
                // Nada de lo que se toque aqui debe llegar al mapa (zoom, arrastre, clic en un poligono)
                ['click', 'dblclick', 'mousedown', 'touchstart', 'pointerdown', 'wheel'].forEach(function(t) {
                    el.addEventListener(t, function(ev) { ev.stopPropagation(); }, { passive: true });
                });
                el.addEventListener('click', function(ev) {
                    const b = ev.target.closest && ev.target.closest('[data-act]');
                    if (!b || !state._stack) return;
                    const st = state._stack, act = b.getAttribute('data-act'), n = st.list.length;
                    if (act === 'prev') st.idx = (st.idx - 1 + n) % n;
                    else if (act === 'next') st.idx = (st.idx + 1) % n;
                    else if (act === 'go') st.idx = parseInt(b.getAttribute('data-i'), 10) || 0;
                    else if (act === 'close') { app.map.stackClose(state); return; }
                    else if (act === 'measure') { app.map.measureAddPoint(state, st.list[st.idx]); }
                    app.map.stackShow(state);
                });
            }

            const list = stack.list, n = list.length, cur = list[stack.idx];
            const card = app.tooltips.markerCard(cur, state);
            const ms = state.measure;
            const cfgCur = state.markerLabels[cur.tipo] || {};

            // Con un solo marcador (la unidad elegida en el buscador) la tarjeta es la ficha fija: solo lleva el botón de cerrar
            el.setAttribute('aria-label', n > 1 ? 'Marcadores encimados' : 'Ficha de la unidad');
            let html = n > 1 ? `<div class="siarhe-stack-head">
                <button type="button" class="siarhe-stack-btn" data-act="prev" aria-label="Marcador anterior">&#9664;</button>
                <span class="siarhe-stack-count" aria-live="polite">${stack.idx + 1} de ${n}</span>
                <button type="button" class="siarhe-stack-btn" data-act="next" aria-label="Marcador siguiente">&#9654;</button>
                <button type="button" class="siarhe-stack-btn siarhe-stack-close" data-act="close" aria-label="Cerrar">&times;</button>
            </div>` : `<div class="siarhe-stack-head siarhe-stack-head-uno">
                <button type="button" class="siarhe-stack-btn siarhe-stack-close" data-act="close" aria-label="Cerrar la ficha">&times;</button>
            </div>`;
            html += card.html;
            if (ms && ms.active && cfgCur.tipo === 'posicion') {
                const inMs = ms.points.some(function(p) { return p.uid === cur._uid; });
                html += `<button type="button" class="siarhe-stack-measure" data-act="measure">${inMs ? 'Quitar de la medición' : 'Agregar a la medición'}</button>`;
            }
            if (n > 1) html += `<ol class="siarhe-stack-list">`;
            if (n > 1) list.forEach(function(d, i) {
                const st = app.utils.getMarkerStyle(d.tipo, state);
                const nm = app.utils.titleCase(d.nombre || d.clues || (state.markerLabels[d.tipo] || {}).label || 'Marcador');
                html += `<li><button type="button" data-act="go" data-i="${i}" class="${i === stack.idx ? 'is-current' : ''}">
                    <span class="siarhe-stack-n">${i + 1}</span>
                    <span class="siarhe-stack-dot" style="background:${esc(st.fill)}"></span>
                    <span class="siarhe-stack-name">${esc(nm)}</span></button></li>`;
            });
            if (n > 1) html += `</ol>`;

            el.style.backgroundColor = card.bg;
            el.style.color = card.txt;
            el.innerHTML = html;
            el.style.display = 'block';

            // Posicion: junto al punto, sin salirse del mapa
            const W = mapDiv.clientWidth, H = mapDiv.clientHeight, M = 6;
            el.style.maxWidth = Math.max(180, Math.min(300, W - 2 * M)) + 'px';
            el.style.maxHeight = Math.max(160, H - 2 * M) + 'px';
            const tw = el.offsetWidth, th = el.offsetHeight;
            let left = stack.ax + 15;
            if (left + tw > W - M) left = stack.ax - 15 - tw;
            let top = stack.ay - 28;
            if (top + th > H - M) top = stack.ay - th - 12;
            if (top < M) top = stack.ay + 18;
            el.style.left = Math.max(M, Math.min(left, W - tw - M)) + 'px';
            el.style.top = Math.max(M, Math.min(top, H - th - M)) + 'px';
        }
    };

})(window.SiarheDataViz);