"""Arma una página estática que carga el front del plugin con datos reales de RHEMX (sin WordPress), para verlo en un navegador.
Uso: python arma.py config.json salida.html [contenedor|canvas] [entidad]
  entidad: clave de dos dígitos (33 = República, por defecto). Con otra clave arma la vista de ese estado; las vistas se
  guardan como vista-<clave>.html junto a la salida para probar la navegación dentro del mapa (entrar a un estado y volver).
Se sirve con un servidor local desde la carpeta que contiene siarhe-dataviz y rhemx."""
import sys, json, html, os

cfg = json.load(open(sys.argv[1], encoding='utf-8'))
P = '/siarhe-dataviz/siarhe-dataviz/public/'
R = '/rhemx/'
raiz = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..')).replace('\\', '/')  # carpeta que contiene siarhe-dataviz y rhemx

def hay(url):
    return os.path.exists(raiz + url)

ENT = sys.argv[4] if len(sys.argv) > 4 else '33'
NACIONAL = ENT == '33'
# Archivo de la base de la entidad: 33_republica-mexicana.json, 28_tamaulipas.json...
def base_de(anio, carpeta):
    d = raiz + f'{R}data/processed/mapas/{anio}/{carpeta}'
    if not os.path.isdir(d): return ''
    hallado = [f for f in os.listdir(d) if f.startswith(ENT + '_') and f.endswith('.json')]
    return f'{R}data/processed/mapas/{anio}/{carpeta}/{hallado[0]}' if hallado else ''
NOMBRE = 'República Mexicana'

bases = {}
for anio in ('2025', '2024', '2019'):
    u = {}
    for unidad, carpeta in (('plazas', 'json'), ('personas', 'json_personas')):
        url = base_de(anio, carpeta)
        if hay(url): u[unidad] = {'url': url, 'fecha': '01/ene/' + anio}
    if u: bases[anio] = u

geos = {'base': {}, 'loc': {}}
for marco in ('2015', '2020', '2025'):
    carpeta = '2016' if marco == '2015' else marco
    b = f'{R}web/data/geo/{carpeta}/' + ('33_entidades.json' if NACIONAL else f'{ENT}_municipios.json')
    l = f'{R}web/data/geo/{carpeta}/' + ('33_municipios.json' if NACIONAL else f'{ENT}_localidades.json')
    if hay(b): geos['base'][marco] = {'url': b, 'fecha': '—', 'ref': 'Marco geoestadístico ' + marco}
    if hay(l): geos['loc'][marco] = {'url': l}

cat = {a: f'{R}web/data/maps/clinicas_cateteres_{a}.json' for a in ('2024', '2025') if hay(f'{R}web/data/maps/clinicas_cateteres_{a}.json')}
cat['total'] = f'{R}web/data/maps/clinicas_cateteres_historico.json'
archivos = {
    'establecimientos-salud.csv': {'2026': f'{R}data/processed/CLUES/2026/unidades_clues_2026.json'},
    'clinicas-cateteres.csv': cat,
    'clinicas-heridas.csv': {'total': f'{R}web/data/maps/clinicas_heridas_historico.json'},
}
estilos_def = {'ESTAB_1': ('cross', '#1E5B4F'), 'ESTAB_2': ('cross', '#A57F2C'), 'ESTAB_3': ('cross', '#9B2247'), 'ESTAB_6': ('triangle', '#57595C'), 'ESTAB_NE': ('circle', '#475569')}
labels, urls, estilos = {}, {}, {}
# Estilo de cada marcador: el del sistema (siarhe_marker_style, sin nada guardado) que entrega config.php
estilos_cfg = dict(zip(cfg.get('claves', []), cfg.get('estilos', [])))
for k, m in cfg['marcadores'].items():
    if m.get('archivo') not in archivos: continue
    labels[k] = m; urls[k] = archivos[m['archivo']]
    f = estilos_def.get(k, ('circle', '#0A66C2'))
    estilos[k] = estilos_cfg.get(k) or {'shape': f[0], 'fill': f[1], 'fill2': '', 'stroke': '#ffffff'}

def at(v):
    return html.escape(v if isinstance(v, str) else json.dumps(v, ensure_ascii=False), quote=True)

layout = sys.argv[3] if len(sys.argv) > 3 else 'contenedor'
tooltips = {'geo_pob': True, 'geo_abs': True, 'geo_rate': True, 'geo_order': ['pob', 'abs', 'rate'], 'mk_inst': True, 'mk_mun': True, 'mk_clues': True, 'mk_tipo': True, 'mk_nivel': True, 'mk_juris': True}
scripts = ['libs/d3.v7.min.js', 'siarhe-topo.js', 'siarhe-capas.js', 'siarhe-core.js', 'siarhe-controls.js', 'siarhe-posicion.js', 'siarhe-tooltips.js', 'siarhe-map.js', 'siarhe-table.js']

doc = f'''<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Arnés SIARHE DataViz</title>
<link rel="stylesheet" href="{P}css/siarhe-frontend.css">
<style>{cfg.get('css', '')}</style>
<style>body {{ margin: 0; font-family: Roboto, Arial, sans-serif; background: #fff; }} .tema {{ max-width: 1240px; margin: 0 auto; padding: 20px 50px; }} button:hover {{ color: #fff; }}</style>
<script>window.SiarheDataVizConfig = {json.dumps({'d3Fallback': P + 'js/libs/d3.v7.min.js', 'topojson': P + 'js/libs/topojson-client.min.js', 'topoHelper': P + 'js/siarhe-topo.js', 'capas': P + 'js/siarhe-capas.js', 'xlsx': P + 'js/libs/xlsx.full.min.js', 'vista': '/siarhe-dataviz/builds/pruebas/vista-{ent}.html'})};</script>
</head><body><div class="tema">
<div class="siarhe-viz-wrapper{' siarhe-layout-canvas' if layout == 'canvas' else ''}" style="--s-canvas-margin: 2.5%;" id="siarhe-viz-{ENT}"
 data-cve-ent="{ENT}" data-slug="{'republica-mexicana' if NACIONAL else 'entidad-' + ENT}" data-mode="MT"
 data-geojson="{at(geos['base']['2025']['url'])}" data-csv="{at(bases['2025']['plazas']['url'])}" data-img="{P}img/"
 data-temas="{at(cfg['temas'])}" data-bases="{at(bases)}" data-anio="2025" data-unidad="plazas"
 data-geojson-loc="{at(geos['loc'].get('2025', {}).get('url', ''))}" data-geos="{at(geos)}"
 data-worker="{P}js/siarhe-worker.js" data-geo-worker="{P}js/siarhe-geo-worker.js"
 data-marker-config="{at(estilos)}" data-marker-urls="{at(urls)}" data-marker-labels="{at(labels)}" data-marker-grupos="{at(cfg['grupos'])}"
 data-entity-urls="{at({})}" data-metricas="{at(cfg['metricas'])}" data-tooltips="{at(tooltips)}" data-home-url="">
  <section id="siarhe-map-section" class="siarhe-section-map siarhe-block-card">
    <header class="siarhe-header"><h2 class="siarhe-title">{NOMBRE if NACIONAL else 'Entidad ' + ENT}</h2><div class="siarhe-dynamic-total"></div></header>
    <div class="siarhe-controls-placeholder"></div>
    <div class="siarhe-map-container"><div class="siarhe-loading-overlay"><div class="spinner"></div><p>Cargando mapa interactivo...</p></div></div>
    <div class="siarhe-map-footer">Pie del mapa</div>
    <div class="siarhe-ref-clinica" data-ref-archivo="clinicas-cateteres.csv" hidden>Referencia de catéteres</div>
    <div class="siarhe-ref-clinica" data-ref-archivo="clinicas-heridas.csv" hidden>Referencia de heridas</div>
  </section>
  <section id="siarhe-table-section" class="siarhe-section-table siarhe-block-card siarhe-table-collapsible">
    <details class="siarhe-table-details"><summary class="siarhe-table-summary"><h2>Tabla</h2></summary><div class="siarhe-table-body">
    <div class="siarhe-table-wrapper"><div class="siarhe-table-container"></div></div></div></details>
  </section>
</div></div>
{''.join(f'<script src="{P}js/{s}"></script>' for s in scripts)}
</body></html>'''
open(sys.argv[2], 'w', encoding='utf-8').write(doc)
# La misma página sirve de «vista» de la entidad para la navegación dentro del mapa
open(os.path.join(os.path.dirname(os.path.abspath(sys.argv[2])), f'vista-{ENT}.html'), 'w', encoding='utf-8').write(doc)
print('bases', list(bases), 'marcos', list(geos['base']), 'loc', list(geos['loc']), 'marcadores', list(labels))
