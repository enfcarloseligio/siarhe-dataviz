/**
 * SIARHE DataViz - Worker de geometrias (municipios / localidades)
 * ------------------------------------------------------------------
 * Descarga el GeoJSON detallado, lo proyecta y prepara los trazos SVG en TRES niveles de detalle,
 * todo fuera del hilo principal. El GeoJSON nacional de municipios pesa ~37 MB (1.8 millones de vertices):
 * proyectarlo y dibujarlo completo en cada cuadro es lo que volvia lenta la vista por municipios.
 *
 * Recibe { id, url, proj: { scale, translate: [tx, ty] }, tolerances: [t0, t1] }
 * Responde { id, progress } mientras trabaja y al final { id, ok, features: [{ properties, bounds, d: [lod0, lod1, lod2] }] }.
 *  - d[0] y d[1]: simplificados con Douglas-Peucker (tolerancia en unidades del viewBox).
 *  - d[2]: detalle completo (2 decimales).
 * La proyeccion es Mercator con centro [0,0] y sin rotacion (la que usa el mapa); el hilo principal verifica
 * eso antes de pedir el trabajo.
 */
'use strict';

const PI = Math.PI;

/** Indices que conserva Douglas-Peucker sobre un anillo cerrado (se parte en el vertice mas lejano al primero). */
function simplifyRing(xs, ys, n, tol) {
    const keep = new Uint8Array(n);
    keep[0] = 1; keep[n - 1] = 1;
    if (tol <= 0 || n <= 4) { keep.fill(1); return keep; }

    // vertice mas lejano al primero: parte el anillo en dos tramos abiertos
    let m = 0, best = -1;
    for (let i = 1; i < n - 1; i++) {
        const dx = xs[i] - xs[0], dy = ys[i] - ys[0];
        const d2 = dx * dx + dy * dy;
        if (d2 > best) { best = d2; m = i; }
    }
    keep[m] = 1;

    const t2 = tol * tol;
    const stack = [0, m, m, n - 1];
    while (stack.length) {
        const hi = stack.pop(), lo = stack.pop();
        if (hi - lo < 2) continue;
        const ax = xs[lo], ay = ys[lo];
        const dx = xs[hi] - ax, dy = ys[hi] - ay;
        const len2 = dx * dx + dy * dy;
        let maxD = 0, idx = -1;
        for (let i = lo + 1; i < hi; i++) {
            let d2;
            if (len2 === 0) {
                const ex = xs[i] - ax, ey = ys[i] - ay;
                d2 = ex * ex + ey * ey;
            } else {
                let t = ((xs[i] - ax) * dx + (ys[i] - ay) * dy) / len2;
                t = t < 0 ? 0 : (t > 1 ? 1 : t);
                const px = ax + t * dx - xs[i], py = ay + t * dy - ys[i];
                d2 = px * px + py * py;
            }
            if (d2 > maxD) { maxD = d2; idx = i; }
        }
        if (idx !== -1 && maxD > t2) {
            keep[idx] = 1;
            stack.push(lo, idx, idx, hi);
        }
    }
    return keep;
}

/** Trazo "Mx,yLx,y...Z" de un anillo, con los indices conservados y la precision dada (multiplo de 10). */
function ringPath(xs, ys, keep, mult) {
    let out = '', count = 0, lastX = NaN, lastY = NaN;
    for (let i = 0; i < xs.length; i++) {
        if (!keep[i]) continue;
        const x = Math.round(xs[i] * mult) / mult, y = Math.round(ys[i] * mult) / mult;
        if (x === lastX && y === lastY) continue;
        out += (count === 0 ? 'M' : 'L') + x + ',' + y;
        lastX = x; lastY = y; count++;
    }
    return count >= 3 ? out + 'Z' : '';
}

function buildFeature(f, scale, tx, ty, tolerances) {
    const g = f && f.geometry;
    const polys = !g ? [] : (g.type === 'Polygon' ? [g.coordinates] : (g.type === 'MultiPolygon' ? g.coordinates : []));
    const lods = [[], [], []];            // trazos por nivel
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    const k = PI / 180;

    for (let p = 0; p < polys.length; p++) {
        const rings = polys[p];
        for (let r = 0; r < rings.length; r++) {
            const ring = rings[r];
            const n = ring.length;
            if (n < 4) continue;
            const xs = new Float64Array(n), ys = new Float64Array(n);
            for (let i = 0; i < n; i++) {
                const lon = ring[i][0], lat = ring[i][1];
                const x = tx + scale * lon * k;
                const y = ty - scale * Math.log(Math.tan(PI / 4 + lat * k / 2));
                xs[i] = x; ys[i] = y;
                if (x < x0) x0 = x; if (x > x1) x1 = x;
                if (y < y0) y0 = y; if (y > y1) y1 = y;
            }
            for (let l = 0; l < 3; l++) {
                const tol = l < 2 ? tolerances[l] : 0;
                const keep = tol > 0 ? simplifyRing(xs, ys, n, tol) : null;
                const all = keep || new Uint8Array(n).fill(1);
                const s = ringPath(xs, ys, all, l === 2 ? 100 : 10);
                if (s) lods[l].push(s);
            }
        }
    }
    const full = lods[2].join('');
    // Si la simplificacion elimino todo (poligonos diminutos) se usa el nivel siguiente
    const d2 = full;
    const d1 = lods[1].join('') || d2;
    const d0 = lods[0].join('') || d1;
    return { properties: (f && f.properties) || {}, bounds: [x0, y0, x1, y1], d: [d0, d1, d2] };
}

self.onmessage = async function(e) {
    const msg = e.data || {};
    try {
        const res = await fetch(msg.url);
        if (!res.ok) throw new Error('HTTP ' + res.status + ' al descargar ' + msg.url);
        const data = await res.json();
        const feats = Array.isArray(data && data.features) ? data.features : [];
        if (!feats.length) throw new Error('El GeoJSON detallado esta vacio o no es valido');

        const scale = msg.proj.scale, tx = msg.proj.translate[0], ty = msg.proj.translate[1];
        const tol = msg.tolerances || [0.25, 0.06];
        const out = new Array(feats.length);
        let lastPct = -1;
        for (let i = 0; i < feats.length; i++) {
            out[i] = buildFeature(feats[i], scale, tx, ty, tol);
            const pct = Math.floor(((i + 1) / feats.length) * 20) * 5;   // avisos cada 5 %
            if (pct !== lastPct) { lastPct = pct; self.postMessage({ id: msg.id, progress: pct }); }
        }
        self.postMessage({ id: msg.id, ok: true, features: out });
    } catch (err) {
        self.postMessage({ id: msg.id, ok: false, error: String(err && err.message ? err.message : err) });
    }
};
