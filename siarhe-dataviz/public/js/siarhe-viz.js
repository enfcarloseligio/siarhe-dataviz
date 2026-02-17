// 📢 LOG INICIAL
console.log("%c 🚀 SIARHE JS V2: Iniciando...", "background: #222; color: #bada55");

document.addEventListener('DOMContentLoaded', function() {

    if (typeof d3 === 'undefined') {
        console.error("❌ ERROR CRÍTICO: D3.js no cargó.");
        return;
    }

    // ==========================================
    // CONFIGURACIÓN
    // ==========================================
    const METRICAS = {
        'tasa_total':             { label: 'Tasa Total (x 1,000 hab)', tipo: 'tasa' },
        'enfermeras_total':       { label: 'Total de Enfermeras',      tipo: 'absoluto' },
        'poblacion':              { label: 'Población Total',          tipo: 'absoluto' },
        'enfermeras_primer':      { label: 'Enfermeras 1er Nivel',     tipo: 'absoluto' },
        'tasa_primer':            { label: 'Tasa 1er Nivel',           tipo: 'tasa' },
        'enfermeras_segundo':     { label: 'Enfermeras 2do Nivel',     tipo: 'absoluto' },
        'tasa_segundo':           { label: 'Tasa 2do Nivel',           tipo: 'tasa' },
        'enfermeras_tercer':      { label: 'Enfermeras 3er Nivel',     tipo: 'absoluto' },
        'tasa_tercer':            { label: 'Tasa 3er Nivel',           tipo: 'tasa' },
        'enfermeras_administrativas': { label: 'Enf. Administrativas', tipo: 'absoluto' },
        'tasa_administrativas':   { label: 'Tasa Administrativas',     tipo: 'tasa' }
    };

    const COLOR_RANGE = ["#eff3ff", "#c6dbef", "#9ecae1", "#6baed6", "#3182bd", "#08519c"];
    const COLOR_NULL  = "#d9d9d9";

    // Inicializar mapas
    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    wrappers.forEach(initVisualization);

    function initVisualization(container) {
        const cveEnt     = container.dataset.cveEnt;
        const geojsonUrl = container.dataset.geojson;
        const csvUrl     = container.dataset.csv;
        const mode       = container.dataset.mode;
        const loading    = container.querySelector('.siarhe-loading-overlay');

        let state = {
            geoData: null,
            csvData: [],
            dataMap: new Map(),
            currentMetric: 'tasa_total'
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

                // CÁLCULO INICIAL DEL TOTAL
                calcularTotalHeader(container, state, cveEnt);
            }

            if (csv && mode.includes('M')) renderControls(container, state, () => {
                updateMapColors(container, state);
                calcularTotalHeader(container, state, cveEnt); // Recalcular al cambiar métrica
            });
            
            if (mode.includes('M')) renderMap(container, state);
            if (mode.includes('T')) renderTable(container, state);

        }).catch(err => {
            console.error(err);
            if(loading) loading.innerHTML = "❌ Error: " + err.message;
        });
    }

    // --- PROCESAMIENTO ---
    function processCSV(data) {
        return data.map(d => {
            const row = {};
            row.CVE_ENT = d.CVE_ENT ? d.CVE_ENT.toString().padStart(2, '0') : "00";
            row.id_legacy = d.id; // Guardamos ID original para lógica de sumas (8888, 9999)
            row.estado  = d.estado || d.Entidad || "";
            
            const keyPob = Object.keys(d).find(k => k.toLowerCase().startsWith('pob'));
            row.poblacion = parseNum(d[keyPob]);

            Object.keys(METRICAS).forEach(k => {
                if (k !== 'poblacion') row[k] = parseNum(d[k]);
            });
            
            // Marcar especiales para filtrado visual
            row.isSpecial = (['33','34'].includes(row.CVE_ENT) || ['9999','8888'].includes(d.id));
            return row;
        });
    }

    function parseNum(val) {
        if (!val) return 0;
        return parseFloat(val.toString().replace(/,/g, '')) || 0;
    }

    // --- LÓGICA DE SUMATORIA (EL REQUERIMIENTO CLAVE) ---
    function calcularTotalHeader(container, state, cveContext) {
        const headerDiv = container.querySelector('.siarhe-dynamic-total');
        if (!headerDiv) return;

        const metric = state.currentMetric;
        const info = METRICAS[metric];
        let total = 0;
        let etiqueta = info.label;

        // Si es "Tasa", no se suma, se muestra el valor de la fila "Nacional" (33) o se calcula promedio
        // El usuario dijo: "calcula sumando 32 entidades + no disponible (8888)"
        // Esto aplica principalmente a valores ABSOLUTOS (enfermeras_total).
        
        if (info.tipo === 'absoluto') {
            // Sumar 01-32 + 88 (que en tu CSV a veces es 8888 o CVE_ENT 88)
            state.csvData.forEach(row => {
                const cve = parseInt(row.CVE_ENT);
                // Si es estado (1-32) O es "No Disponible" (suelen ser ID 8888 o CVE alta)
                // Ajusta esta lógica según tu CSV exacto. Asumo 88 para No Asignado standard.
                const esEstado = (cve >= 1 && cve <= 32);
                const esNoDisponible = (row.id_legacy === '8888' || row.CVE_ENT === '88');

                if (esEstado || esNoDisponible) {
                    total += row[metric];
                }
            });
        } else {
            // Si es tasa, tomamos el valor pre-calculado de la fila Nacional (33)
            const rowNacional = state.dataMap.get('33');
            total = rowNacional ? rowNacional[metric] : 0;
        }

        // Formatear
        const totalFmt = total.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        headerDiv.innerHTML = `<strong>${etiqueta}:</strong> <span style="color:#2271b1; font-size:1.3em;">${totalFmt}</span>`;
    }

    // --- CONTROLES (CORREGIDO) ---
    function renderControls(container, state, onUpdate) {
        // BUSCAMOS EL PLACEHOLDER ESPECÍFICO
        const placeholder = container.querySelector('.siarhe-controls-placeholder');
        if (!placeholder) {
            console.warn("⚠️ Placeholder de controles no encontrado.");
            return;
        }
        
        // Limpiar
        placeholder.innerHTML = '';

        const div = document.createElement('div');
        div.className = 'siarhe-controls';
        
        const label = document.createElement('label');
        label.innerText = 'Indicador: ';
        
        const select = document.createElement('select');
        select.className = 'siarhe-metric-select';
        
        Object.entries(METRICAS).forEach(([key, info]) => {
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = info.label;
            if (key === state.currentMetric) opt.selected = true;
            select.appendChild(opt);
        });

        select.addEventListener('change', (e) => {
            state.currentMetric = e.target.value;
            onUpdate();
        });

        div.append(label, select);
        placeholder.appendChild(div); // Insertamos DENTRO del placeholder
    }

    // --- MAPA ---
    function renderMap(container, state) {
        const mapDiv = container.querySelector('.siarhe-map-container');
        const width = mapDiv.clientWidth || 800;
        const height = 500;

        mapDiv.innerHTML = ''; // Limpiar loader

        const svg = d3.select(mapDiv).append("svg")
            .attr("width", "100%")
            .attr("height", height)
            .attr("viewBox", `0 0 ${width} ${height}`)
            .style("background-color", "#e6f0f8");

        const tooltip = d3.select("body").append("div")
            .attr("class", "siarhe-tooltip")
            .style("opacity", 0);

        const projection = d3.geoMercator().fitSize([width, height], state.geoData);
        const path = d3.geoPath().projection(projection);

        const g = svg.append("g");
        
        g.selectAll("path")
            .data(state.geoData.features)
            .enter().append("path")
            .attr("d", path)
            .attr("class", "siarhe-feature")
            .attr("stroke", "#fff")
            .attr("stroke-width", "0.5")
            .on("mouseover", (e, d) => showTooltip(e, d, state, tooltip))
            .on("mousemove", (e) => {
                tooltip.style("left", (e.pageX + 15) + "px").style("top", (e.pageY - 28) + "px");
            })
            .on("mouseout", function() {
                d3.select(this).style("stroke", "#fff").style("stroke-width", "0.5");
                tooltip.style("display", "none");
            });

        updateMapColors(container, state);
    }

    function updateMapColors(container, state) {
        const metric = state.currentMetric;
        const values = state.csvData.filter(d => !d.isSpecial && d[metric] > 0).map(d => d[metric]).sort(d3.ascending);
        const colorScale = d3.scaleQuantile().domain(values).range(COLOR_RANGE);

        d3.select(container).selectAll("path.siarhe-feature")
            .transition().duration(500)
            .attr("fill", d => {
                const props = d.properties;
                const cveGeo = props.CVE_ENT || props.cve_ent || props.ID || props.id;
                const cve = cveGeo ? cveGeo.toString().padStart(2, '0') : "00";
                
                const row = state.dataMap.get(cve);
                if (!row || !row[metric]) return COLOR_NULL;
                return colorScale(row[metric]);
            });
    }

    function showTooltip(event, feature, state, tooltip) {
        const props = feature.properties;
        const cveGeo = props.CVE_ENT || props.cve_ent || props.ID || props.id;
        const cve = cveGeo ? cveGeo.toString().padStart(2, '0') : "00";
        
        const row = state.dataMap.get(cve);
        const nombre = row ? row.estado : (props.NOM_ENT || "Sin dato");
        const val = row ? row[state.currentMetric] : 0;
        const valFmt = val.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        const label = METRICAS[state.currentMetric].label;

        d3.select(event.target).style("stroke", "#333").style("stroke-width", "1.5").raise();
        
        tooltip.style("display", "block")
               .style("opacity", 1)
               .html(`<strong>${nombre}</strong><br>${label}: ${valFmt}`)
               .style("left", (event.pageX + 15) + "px")
               .style("top", (event.pageY - 28) + "px");
    }

    // --- TABLA ---
    function renderTable(container, state) {
        const tableDiv = container.querySelector('.siarhe-table-container');
        tableDiv.innerHTML = '';
        
        if (!state.csvData.length) return;

        const table = document.createElement('table');
        table.className = 'siarhe-data-table';

        const thead = table.createTHead();
        const hRow = thead.insertRow();
        ["Entidad", "Total Enfermeras", "Tasa"].forEach(t => {
            const th = document.createElement('th');
            th.textContent = t;
            hRow.appendChild(th);
        });

        const tbody = table.createTBody();
        const sorted = [...state.csvData].sort((a, b) => {
            // Ordenar: Estados primero, Totales (33, 9999) al final
            if (a.isSpecial && !b.isSpecial) return 1;
            if (!a.isSpecial && b.isSpecial) return -1;
            return a.estado.localeCompare(b.estado);
        });

        sorted.forEach(row => {
            const tr = tbody.insertRow();
            if(row.isSpecial) tr.className = 'siarhe-row-total';
            
            tr.insertCell().textContent = row.estado;
            tr.insertCell().textContent = row.enfermeras_total.toLocaleString('es-MX');
            tr.insertCell().textContent = row.tasa_total.toFixed(2);
        });
        tableDiv.appendChild(table);
    }
});