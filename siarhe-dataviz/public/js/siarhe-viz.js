// 📢 LOG INICIAL
console.log("%c 🚀 SIARHE JS V9: Leyenda Espaciada y Formato Miles (10,000.00)", "background: #222; color: #bada55");

document.addEventListener('DOMContentLoaded', function() {

    if (typeof d3 === 'undefined') {
        console.error("❌ ERROR CRÍTICO: D3.js no cargó.");
        return;
    }

    // ==========================================
    // 1. CONFIGURACIÓN Y METADATOS
    // ==========================================
    const METRICAS = {
        'tasa_total':             { label: 'Tasa Total', fullLabel: 'Tasa Total (x 1,000 hab)', tipo: 'tasa', pair: 'enfermeras_total' },
        'enfermeras_total':       { label: 'Total Enf.', fullLabel: 'Total de Enfermeras',      tipo: 'absoluto', pair: 'enfermeras_total' },
        'poblacion':              { label: 'Población',  fullLabel: 'Población Total',          tipo: 'absoluto', pair: 'poblacion' },
        
        'tasa_primer':            { label: 'Tasa 1er Nivel', fullLabel: 'Tasa 1er Nivel',       tipo: 'tasa', pair: 'enfermeras_primer' },
        'enfermeras_primer':      { label: 'Enf. 1er Nivel', fullLabel: 'Enfermeras 1er Nivel', tipo: 'absoluto', pair: 'enfermeras_primer' },
        
        'tasa_segundo':           { label: 'Tasa 2do Nivel', fullLabel: 'Tasa 2do Nivel',       tipo: 'tasa', pair: 'enfermeras_segundo' },
        'enfermeras_segundo':     { label: 'Enf. 2do Nivel', fullLabel: 'Enfermeras 2do Nivel', tipo: 'absoluto', pair: 'enfermeras_segundo' },
        
        'tasa_tercer':            { label: 'Tasa 3er Nivel', fullLabel: 'Tasa 3er Nivel',       tipo: 'tasa', pair: 'enfermeras_tercer' },
        'enfermeras_tercer':      { label: 'Enf. 3er Nivel', fullLabel: 'Enfermeras 3er Nivel', tipo: 'absoluto', pair: 'enfermeras_tercer' },
        
        'tasa_administrativas':   { label: 'Tasa Admin.', fullLabel: 'Tasa Administrativas',     tipo: 'tasa', pair: 'enfermeras_administrativas' },
        'enfermeras_administrativas': { label: 'Enf. Admin.', fullLabel: 'Enfermeras Administrativas', tipo: 'absoluto', pair: 'enfermeras_administrativas' }
    };

    // --- LEER COLORES (Variables CSS) ---
    const styles = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => {
        const val = styles.getPropertyValue(name).trim();
        return val !== '' ? val : fallback;
    };

    const COLOR_RANGE = [
        getVar('--s-map-c1', '#eff3ff'), getVar('--s-map-c2', '#bdd7e7'),
        getVar('--s-map-c3', '#9ecae1'), getVar('--s-map-c4', '#6baed6'),
        getVar('--s-map-c5', '#08519c')
    ];
    const COLOR_ZERO = getVar('--s-map-zero', '#d9d9d9'); 
    const COLOR_NULL = getVar('--s-map-null', '#000000'); 

    // Orden inicial: Tasa de Mayor a Menor (ranking)
    let sortConfig = { key: 'metric', direction: 'desc' };

    // ==========================================
    // 2. HELPER CLAVE (ESTRICTO)
    // ==========================================
    function cleanKey(val) {
        if (val === undefined || val === null) return "";
        return val.toString().trim();
    }

    function getGeoKey(props) {
        return cleanKey(props.ID || props.CVE_ENT || props.cve_ent || props.id); 
    }

    // Inicializar
    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    wrappers.forEach(initVisualization);

    function initVisualization(container) {
        const geojsonUrl = container.dataset.geojson;
        const csvUrl     = container.dataset.csv;
        const mode       = container.dataset.mode;
        const loading    = container.querySelector('.siarhe-loading-overlay');

        let state = {
            geoData: null,
            csvData: [],
            dataMap: new Map(),
            currentMetric: 'tasa_total',
            zoom: null,
            gLegend: null,
            gradientId: null
        };

        if (!geojsonUrl) return;

        Promise.all([
            d3.json(geojsonUrl),
            csvUrl ? d3.csv(csvUrl) : Promise.resolve(null)
        ]).then(([geo, csv]) => {
            if(loading) loading.style.display = 'none';
            state.geoData = geo;

            if (csv) {
                state.csvData = processCSV(csv);
                state.csvData.forEach(row => {
                    if (row.CVE_ENT) state.dataMap.set(row.CVE_ENT, row);
                });
                calcularTotalHeader(container, state);
            }

            if (csv && mode.includes('M')) {
                renderControls(container, state, () => {
                    updateMapVisuals(container, state);
                    calcularTotalHeader(container, state);
                    if (mode.includes('T')) updateTable(container, state);
                });
            }
            
            if (mode.includes('M')) renderMap(container, state);
            if (mode.includes('T')) initTableStructure(container, state);

        }).catch(err => {
            console.error(err);
            if(loading) loading.innerHTML = "❌ Error: " + err.message;
        });
    }

    // ==========================================
    // 3. PROCESAMIENTO
    // ==========================================
    function processCSV(data) {
        return data.map(d => {
            const row = {};
            let rawCve = d.CVE_ENT || d.cve_ent || d.mapa || d.id || "";
            row.CVE_ENT = cleanKey(rawCve); 
            
            row.id_legacy = (d.id || "").toString().trim();
            row.estado = (d.estado || d.Entidad || d.municipio || "").trim();
            
            const keyPob = Object.keys(d).find(k => k.toLowerCase().startsWith('pob'));
            row.poblacion = parseNum(d[keyPob]);

            Object.keys(METRICAS).forEach(k => {
                if (k !== 'poblacion') row[k] = parseNum(d[k]);
            });

            row.isTotal = (row.id_legacy === '9999' || row.CVE_ENT === '33' || row.estado.toLowerCase().includes('total'));
            row.isSpecial = (row.id_legacy === '8888' || row.CVE_ENT === '34' || row.CVE_ENT === '88');

            return row;
        });
    }

    function parseNum(val) {
        if (val === undefined || val === null || val === '') return 0;
        const clean = val.toString().replace(/,/g, '').replace(/\s/g, '');
        const num = parseFloat(clean);
        return isNaN(num) ? 0 : num;
    }

    // ==========================================
    // 4. HEADER DINÁMICO
    // ==========================================
    function calcularTotalHeader(container, state) {
        const headerDiv = container.querySelector('.siarhe-dynamic-total');
        if (!headerDiv) return;

        const metricKey = state.currentMetric;
        const info = METRICAS[metricKey];
        const pairKey = info.pair || metricKey;

        let totalAbsoluto = 0;
        let valorMuestra = 0;
        
        const rowTotal = state.csvData.find(r => r.isTotal);

        if (rowTotal) {
            totalAbsoluto = rowTotal[pairKey];
            valorMuestra = rowTotal[metricKey];
        } else {
            state.csvData.forEach(row => {
                if (!row.isTotal && !row.isSpecial) {
                    if (METRICAS[pairKey].tipo === 'absoluto') totalAbsoluto += row[pairKey];
                }
            });
            valorMuestra = (info.tipo === 'tasa') ? 0 : totalAbsoluto;
        }
        
        const absFmt = totalAbsoluto.toLocaleString('es-MX');
        const valFmt = valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 });

        let html = ``;
        if (info.tipo === 'tasa') {
            html = `<span style="margin-right:15px;">${info.fullLabel}: <strong style="color:#2271b1; font-size:1.2em;">${valFmt}</strong></span>`;
            html += `<span>Enfermeras: <strong style="color:#2271b1; font-size:1.2em;">${absFmt}</strong></span>`;
        } else {
            html = `<span>${info.fullLabel}: <strong style="color:#2271b1; font-size:1.2em;">${valFmt}</strong></span>`;
        }
        headerDiv.innerHTML = html;
    }

    // ==========================================
    // 5. MAPA (RENDER)
    // ==========================================
    function renderMap(container, state) {
        const mapDiv = container.querySelector('.siarhe-map-container');
        const width = mapDiv.clientWidth || 800;
        const height = 600; 
        mapDiv.innerHTML = '';

        const svg = d3.select(mapDiv).append("svg")
            .attr("width", "100%")
            .attr("height", height)
            .attr("viewBox", `0 0 ${width} ${height}`)
            .style("background-color", "#e6f0f8");

        const defs = svg.append("defs");
        state.gradientId = `grad-${Math.random().toString(36).substr(2, 5)}`;
        const linearGradient = defs.append("linearGradient")
            .attr("id", state.gradientId)
            .attr("x1", "0%").attr("y1", "100%") 
            .attr("x2", "0%").attr("y2", "0%");

        linearGradient.selectAll("stop")
            .data(COLOR_RANGE)
            .enter().append("stop")
            .attr("offset", (d, i) => `${(i / (COLOR_RANGE.length - 1)) * 100}%`)
            .attr("stop-color", d => d);

        const gMain = svg.append("g").attr("class", "map-layer");
        
        // GRUPO LEYENDA (Posición superior izquierda, con margen)
        state.gLegend = svg.append("g")
            .attr("class", "siarhe-legend-group")
            .attr("transform", `translate(30, 40)`); 

        const projection = d3.geoMercator().fitSize([width, height], state.geoData);
        const path = d3.geoPath().projection(projection);
        const tooltip = d3.select("body").append("div").attr("class", "siarhe-tooltip").style("opacity", 0);

        gMain.selectAll("path")
            .data(state.geoData.features)
            .enter().append("path")
            .attr("d", path)
            .attr("class", "siarhe-feature")
            .attr("stroke", "#fff").attr("stroke-width", "0.5")
            .style("fill", COLOR_NULL)
            .on("mouseover", (e, d) => showTooltip(e, d, state, tooltip))
            .on("mousemove", (e) => {
                tooltip.style("left", (e.pageX + 15) + "px").style("top", (e.pageY - 28) + "px");
            })
            .on("mouseout", function() {
                d3.select(this).style("stroke", "#fff").style("stroke-width", "0.5");
                tooltip.style("display", "none");
            })
            .on("click", (e, d) => handleMapClick(d, state));

        const zoom = d3.zoom().scaleExtent([1, 8]).on("zoom", (e) => gMain.attr("transform", e.transform));
        svg.call(zoom);

        updateMapVisuals(container, state);
    }

    function updateMapVisuals(container, state) {
        const metric = state.currentMetric;
        const values = state.csvData
            .filter(d => !d.isTotal && !d.isSpecial && d[metric] > 0)
            .map(d => d[metric])
            .sort(d3.ascending);

        if(values.length === 0) return;

        const min = d3.min(values);
        const max = d3.max(values);
        const q1 = d3.quantile(values, 0.25);
        const q2 = d3.quantile(values, 0.50);
        const q3 = d3.quantile(values, 0.75);

        const colorScale = d3.scaleLinear()
            .domain([min, q1, q2, q3, max])
            .range(COLOR_RANGE)
            .clamp(true);

        d3.select(container).selectAll("path.siarhe-feature")
            .transition().duration(500)
            .style("fill", d => {
                const props = d.properties;
                let cve = getGeoKey(props); 
                const row = state.dataMap.get(cve);
                
                if (!row) return COLOR_NULL;
                if (row[metric] === 0) return COLOR_ZERO;
                return colorScale(row[metric]);
            });

        renderGradientLegend(state, {min, q1, q2, q3, max});
    }

    // --- LEYENDA MEJORADA (Espaciado + Comas) ---
    function renderGradientLegend(state, domainStats) {
        const g = state.gLegend;
        g.html(""); 

        const metricLabel = METRICAS[state.currentMetric].label;
        const domain = [domainStats.min, domainStats.q1, domainStats.q2, domainStats.q3, domainStats.max];

        // 1. Aumentamos altura del fondo blanco para dar espacio abajo
        const barHeight = 150; 
        const chipsHeight = 60; // Espacio para chips
        const totalHeight = barHeight + chipsHeight + 30; // Margen extra

        g.append("rect")
            .attr("x", -10).attr("y", -25)
            .attr("width", 130) // Más ancho para números con comas (10,000.00)
            .attr("height", totalHeight) 
            .attr("fill", "rgba(255,255,255,0.85)")
            .attr("rx", 5)
            .style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");

        g.append("text")
            .attr("x", 0).attr("y", -10)
            .text(metricLabel)
            .style("font-size", "11px").style("font-weight", "bold").style("fill", "#333");

        const barWidth = 15;

        g.append("rect")
            .attr("x", 0).attr("y", 10)
            .attr("width", barWidth).attr("height", barHeight)
            .style("fill", `url(#${state.gradientId})`)
            .style("stroke", "#ccc");

        const step = barHeight / (domain.length - 1);
        domain.forEach((val, i) => {
            const yPos = 10 + barHeight - (i * step); 
            g.append("line").attr("x1", barWidth).attr("x2", barWidth + 5).attr("y1", yPos).attr("y2", yPos).style("stroke", "#666");
            
            // FORMATO: Miles con comas y 2 decimales siempre
            const labelText = val.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            g.append("text").attr("x", barWidth + 8).attr("y", yPos + 4)
                .text(labelText)
                .style("font-size", "10px").style("fill", "#666");
        });

        const offset = barHeight + 35;
        
        // Chips
        const gZero = g.append("g").attr("transform", `translate(0, ${offset})`);
        gZero.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_ZERO).style("stroke","#ccc");
        gZero.append("text").attr("x", 18).attr("y", 10).text("0.00").style("font-size","11px").style("fill","#555");

        const gNull = g.append("g").attr("transform", `translate(0, ${offset + 20})`);
        gNull.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_NULL).style("stroke","#ccc");
        gNull.append("text").attr("x", 18).attr("y", 10).text("S/D").style("font-size","11px").style("fill","#555");
    }

    // ==========================================
    // 6. TOOLTIP & NAV
    // ==========================================
    function showTooltip(event, d, state, tooltip) {
        const props = d.properties;
        let cve = getGeoKey(props);
        const row = state.dataMap.get(cve);
        const nombre = row ? row.estado : (props.NOM_ENT || props.NOMBRE || "Sin Datos");

        let html = `<div style="font-weight:bold; border-bottom:1px solid #777; padding-bottom:3px; margin-bottom:5px;">${nombre}</div>`;

        if (row) {
            const pairKey = METRICAS[state.currentMetric].pair || state.currentMetric;
            const pairLabel = METRICAS[pairKey].label.replace("Total ", "");
            
            html += `<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:2px;"><span style="color:#ccc;">Población:</span> <b>${row.poblacion.toLocaleString('es-MX')}</b></div>`;
            
            if (METRICAS[state.currentMetric].tipo === 'tasa') {
                html += `<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:2px;"><span style="color:#ccc;">${pairLabel}:</span> <b>${row[pairKey].toLocaleString('es-MX')}</b></div>`;
                html += `<div style="display:flex; justify-content:space-between; font-size:12px; margin-top:4px; padding-top:4px; border-top:1px dashed #555; color:#ffcc00;"><span>${METRICAS[state.currentMetric].label}:</span> <b>${row[state.currentMetric].toFixed(2)}</b></div>`;
            } else {
                html += `<div style="display:flex; justify-content:space-between; font-size:12px; color:#ffcc00;"><span>${METRICAS[state.currentMetric].label}:</span> <b>${row[state.currentMetric].toLocaleString('es-MX')}</b></div>`;
            }
        } else {
            html += `<span style="color:#ff6b6b; font-size:12px;">Sin datos disponibles</span>`;
        }

        d3.select(event.target).style("stroke", "#333").style("stroke-width", "2").raise();
        tooltip.style("display", "block").style("opacity", 1).html(html)
               .style("left", (event.pageX + 15) + "px").style("top", (event.pageY - 28) + "px");
    }

    function handleMapClick(d, state) {
        let cve = getGeoKey(d.properties);
        const row = state.dataMap.get(cve);
        
        if (row && row.estado) {
            const slug = row.estado.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, "-");
            window.location.href = `/entidad/${slug}/`;
        }
    }

    function renderControls(container, state, onUpdate) {
        const ph = container.querySelector('.siarhe-controls-placeholder');
        if (!ph) return;
        ph.innerHTML = '';
        const div = document.createElement('div'); div.className = 'siarhe-controls';
        const label = document.createElement('label'); label.innerText = 'Indicador: ';
        const select = document.createElement('select'); select.className = 'siarhe-metric-select';
        
        Object.entries(METRICAS).forEach(([key, info]) => {
            const opt = document.createElement('option');
            opt.value = key; opt.textContent = info.fullLabel;
            if (key === state.currentMetric) opt.selected = true;
            select.appendChild(opt);
        });
        select.addEventListener('change', (e) => {
            state.currentMetric = e.target.value;
            onUpdate();
        });
        div.append(label, select); ph.appendChild(div);
    }

    // ==========================================
    // 7. TABLA INTERACTIVA
    // ==========================================
    function initTableStructure(container, state) {
        const tableDiv = container.querySelector('.siarhe-table-container');
        tableDiv.innerHTML = '';
        const table = document.createElement('table');
        table.className = 'siarhe-data-table';
        
        const thead = document.createElement('thead');
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        table.appendChild(tbody);
        tableDiv.appendChild(table);

        state.tableElements = { table, thead, tbody };
        updateTable(container, state);
    }

    function updateTable(container, state) {
        if (!state.csvData.length || !state.tableElements) return;
        
        const { thead, tbody } = state.tableElements;
        const metric = state.currentMetric;
        const info = METRICAS[metric];
        const pairKey = info.pair || metric;

        const columns = [
            { key: 'estado', label: 'Entidad Federativa', isNum: false },
            { key: 'poblacion', label: 'Población', isNum: true },
            { key: pairKey, label: METRICAS[pairKey].label, isNum: true },
            { key: metric, label: info.label, isNum: true, isSortKey: true } 
        ];

        let activeSortKey = sortConfig.key === 'metric' ? metric : sortConfig.key;

        thead.innerHTML = '';
        const hRow = document.createElement('tr');
        columns.forEach(col => {
            const th = document.createElement('th');
            let arrow = '↕';
            if (activeSortKey === col.key) {
                arrow = sortConfig.direction === 'asc' ? '▲' : '▼';
            }
            th.innerHTML = `${col.label} <span style="font-size:10px; color:#999;">${arrow}</span>`;
            th.style.cursor = 'pointer';
            th.onclick = () => {
                if (activeSortKey === col.key) {
                    sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    sortConfig.key = col.key;
                    sortConfig.direction = 'desc'; 
                }
                updateTable(container, state); 
            };
            hRow.appendChild(th);
        });
        thead.appendChild(hRow);

        const fixedRows = state.csvData.filter(r => r.isTotal || r.isSpecial);
        const bodyRows = state.csvData.filter(r => !r.isTotal && !r.isSpecial);

        bodyRows.sort((a, b) => {
            const valA = a[activeSortKey];
            const valB = b[activeSortKey];
            if (valA < valB) return sortConfig.direction === 'asc' ? -1 : 1;
            if (valA > valB) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });

        tbody.innerHTML = '';
        const allRows = [...bodyRows, ...fixedRows]; 

        allRows.forEach(row => {
            const tr = document.createElement('tr');
            if (row.isTotal) tr.className = 'siarhe-row-total';
            if (row.isSpecial) tr.style.backgroundColor = '#fcfcfc'; 

            columns.forEach(col => {
                const td = document.createElement('td');
                let val = row[col.key];
                
                if (col.key === 'estado' && !row.isTotal && !row.isSpecial) {
                    td.innerHTML = `<strong>${val}</strong>`;
                } else if (col.isNum) {
                    if (col.key.includes('tasa')) {
                        td.textContent = val.toFixed(2);
                    } else {
                        td.textContent = val.toLocaleString('es-MX');
                    }
                } else {
                    td.textContent = val;
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }
});