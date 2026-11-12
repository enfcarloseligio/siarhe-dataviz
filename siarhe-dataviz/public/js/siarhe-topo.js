/* /public/js/siarhe-topo.js */
/**
 * SIARHE DataViz - Lectura de geometrías (TopoJSON y GeoJSON)
 * ------------------------------------------------------------------
 * Convierte los archivos de geometría a la colección de elementos que dibuja el mapa. El formato del plugin es el
 * TopoJSON que exporta RHEMX (src/geo_mapas.py): un objeto "datos" con las propiedades "c" (clave) y "n" (nombre) y,
 * en las localidades, "t" (u = urbana, r = rural). Los GeoJSON (FeatureCollection) cargados antes se devuelven sin cambios.
 *
 * El archivo se carga en la página y también dentro del worker de geometrías (importScripts), por eso se publica
 * en `self` y no depende del DOM ni de D3.
 *
 *   1. Propiedades: de la notación corta de RHEMX a los nombres que usa el mapa
 *   2. Conversión de la topología a colección
 *   3. Marco geoestadístico que corresponde a un año
 */
(function(root) {
    'use strict';

    // ==========================================
    // 1. PROPIEDADES
    // ==========================================

    /**
     * Traduce las propiedades cortas de RHEMX a las que lee el mapa. La longitud de la clave dice qué es el elemento:
     * 2 dígitos = entidad, 5 = municipio (entidad + municipio), 9 = localidad (entidad + municipio + localidad).
     * Un elemento que ya trae CVE_ENT o CVE_MUN (GeoJSON anterior) se deja como está.
     */
    function normalizaPropiedades(p) {
        p = p || {};
        if (p.CVE_ENT !== undefined || p.CVE_MUN !== undefined || p.c === undefined) return p;
        const c = String(p.c);
        const n = p.n === undefined || p.n === null ? '' : String(p.n);
        const out = { CVEGEO: c, NOMGEO: n };
        if (c.length === 2) {
            out.CVE_ENT = c; out.NOM_ENT = n;
        } else if (c.length === 5) {
            out.CVE_ENT = c.slice(0, 2); out.CVE_MUN = c.slice(2); out.NOM_MUN = n;
        } else if (c.length === 9) {
            out.CVE_ENT = c.slice(0, 2); out.CVE_MUN = c.slice(2, 5); out.CVE_LOC = c.slice(5); out.NOM_LOC = n;
            out.AMBITO = p.t === 'u' ? 'Urbana' : (p.t === 'r' ? 'Rural' : '');
        }
        return out;
    }

    // ==========================================
    // 2. CONVERSIÓN
    // ==========================================

    /** Indica si el JSON recibido es una topología (TopoJSON). */
    function esTopologia(data) {
        return !!data && data.type === 'Topology' && !!data.objects;
    }

    /**
     * Invierte los anillos de los polígonos que la cuantización dejó al revés. D3 mide las áreas sobre la esfera: un
     * polígono diminuto con el anillo invertido equivale a «toda la esfera menos ese polígono» y pintaría el mapa entero.
     * areaEsferica es d3.geoArea; sin ella (dentro del worker, que proyecta en plano) no hace falta corregir.
     */
    function enderezaGeometria(geometria, areaEsferica) {
        const poligonos = geometria.type === 'Polygon' ? [geometria.coordinates]
            : (geometria.type === 'MultiPolygon' ? geometria.coordinates : []);
        poligonos.forEach(function(anillos) {
            if (areaEsferica({ type: 'Polygon', coordinates: anillos }) > 2 * Math.PI) {
                anillos.forEach(function(anillo) { anillo.reverse(); });
            }
        });
    }

    /**
     * Topología a { type: 'FeatureCollection', features }. Se lee el objeto "datos" (o el primero que exista), se
     * descartan las geometrías vacías y se traducen las propiedades.
     * lib es topojson-client; areaEsferica (opcional) activa la corrección de anillos invertidos.
     */
    function aColeccion(topologia, lib, areaEsferica) {
        const nombres = Object.keys(topologia.objects || {});
        const objeto = topologia.objects.datos || topologia.objects[nombres[0]];
        if (!objeto) return { type: 'FeatureCollection', features: [] };

        const convertido = lib.feature(topologia, objeto);
        const lista = convertido.type === 'FeatureCollection' ? convertido.features : [convertido];
        const features = [];
        lista.forEach(function(f) {
            const g = f && f.geometry;
            if (!g || !Array.isArray(g.coordinates) || g.coordinates.length === 0) return;
            if (typeof areaEsferica === 'function') enderezaGeometria(g, areaEsferica);
            features.push({ type: 'Feature', properties: normalizaPropiedades(f.properties), geometry: g });
        });
        return { type: 'FeatureCollection', features: features };
    }

    // ==========================================
    // 3. MARCO GEOESTADÍSTICO
    // ==========================================

    /**
     * Marco que corresponde a un año entre los disponibles. Los marcos se cargan por quinquenio (2005, 2010, 2015...)
     * y cada uno sirve a los cinco años que empiezan en el suyo. Si el del año no está cargado se usa el anterior más
     * cercano y, si tampoco hay, el más antiguo de los disponibles. Devuelve la clave (texto) o null.
     */
    function marcoPara(anio, disponibles) {
        const marcos = (disponibles || []).map(Number).filter(function(m) { return m > 0; }).sort(function(a, b) { return a - b; });
        if (!marcos.length) return null;
        const quinquenio = Math.floor(Number(anio) / 5) * 5;
        let elegido = null;
        marcos.forEach(function(m) { if (m <= quinquenio) elegido = m; });
        return String(elegido === null ? marcos[0] : elegido);
    }

    root.SiarheTopo = {
        normalizaPropiedades: normalizaPropiedades,
        esTopologia: esTopologia,
        aColeccion: aColeccion,
        marcoPara: marcoPara
    };
})(typeof self !== 'undefined' ? self : this);
