# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress plugin (`siarhe-dataviz/`, the plugin root is the nested folder) that renders choropleth maps (D3.js v7) and tables of Mexican nursing human-resources data (SIARHE). UI text, comments and identifiers are in Spanish; match that. Requires WP 5.8+, PHP 7.4+.

There is **no build step, package manager, linter or test suite**. Development = edit PHP/JS/CSS and reload in a WordPress install that has the plugin folder in `wp-content/plugins/`. Version is declared twice in `siarhe-dataviz/siarhe-dataviz.php` (plugin header and `SIARHE_VERSION`); bump both together (commit messages follow the version, e.g. `0.0.5.42`).

## Architecture

**Backend (PHP)**
- `siarhe-dataviz.php` defines constants (`SIARHE_PATH/URL`, `SIARHE_UPLOAD_DIR/URL` → `wp-content/uploads/siarhe-data/{geojson,raw,processed,static-min}`), loads modules, and boots `Siarhe_Admin` and `Siarhe_Shortcodes` on `plugins_loaded`. Activation installs tables via `Siarhe_DB_Installer` and creates the upload folders.
- `includes/class-siarhe-shortcodes.php` registers shortcodes dynamically per entity from `siarhe_get_entities()` (`includes/helpers.php`): `[siarhe_mapa_{M|T|MT}_CVE_ENT_{nn}]` (M = map, T = table, MT = both; `33` = national). `render_viz()` looks up the active GeoJSON and "static_min" CSV for the entity in the `{prefix}siarhe_static_assets` table (`es_activo = 1`) and includes `public/partials/siarhe-view.php`.
- `public/partials/siarhe-view.php` is the real frontend entry point: it enqueues the JS modules conditionally by mode, reads all admin config options, and emits a container whose `data-*` attributes (`data-geojson`, `data-csv`, `data-marker-config`, `data-metricas`, `data-tooltips`, …) carry configuration to JS as JSON. Script versions are each file's `filemtime`, so browsers cache them until they change.
- `admin/class-siarhe-admin.php` handles uploads/updates/deletes through `admin_post_siarhe_*` actions (GeoJSON, localidades, static CSV, markers) and registers settings options: `siarhe_map_options` (colors), `siarhe_theme_options`, `siarhe_links_map`, `siarhe_metricas_config`, `siarhe_tooltip_config`, `siarhe_marcadores_config` (JSON string). UI lives in `admin/partials/` (settings split into `settings-tabs/` and `upload-tabs/`). `admin/partials/siarhe-settings.php` is one template shared by two menu screens: **Personalizar** (`siarhe-customize`: tabs `tema`, `mapa`) and **Configuración** (`siarhe-settings`: `enlaces`, `tooltip`, `metricas`, `marcadores`); tab slugs/option groups are declared at the top of that file.
- `includes/db/` holds the installer (`dbDelta`), data loader and query classes.
- `enqueue_frontend_scripts()` in the shortcodes class registers the frontend CSS (with the single `:root` variables block from `siarhe_get_css_variables_css()` in `helpers.php`, which also holds the shared defaults and entity catalog) and registers D3 from its CDN. `siarhe-core.js` (`app.ensureD3`) falls back to the bundled copy in `public/js/libs/` if the CDN fails; Select2 in the admin has the same CDN-first/local-fallback pattern (`admin/libs/`). Do not name folders `vendor/` (ignored by git and excluded from the build zip).

**Frontend (JS, `public/js/`)** — plain scripts (no bundler) sharing one global namespace `window.SiarheDataViz` (`app`). Load order is enforced through WP script dependencies in `siarhe-view.php`:
`siarhe-core.js` (state, `app.utils`, `app.math`, `app.data` CSV/GeoJSON loading and metric computation, `app.initVisualization(container)` which parses the `data-*` config) → `siarhe-controls.js` (`app.controls`: selectors/filters UI) → `siarhe-tooltips.js` (`app.tooltips`) → `siarhe-map.js` (`app.map`, D3 SVG map, zoom, markers, fullscreen) and `siarhe-table.js` (`app.table`). Map and tooltips scripts load only when the mode includes `M`; table only for `T`. Map colors come from CSS variables (`--s-map-*`, `--s-th-*`, …) injected by `wp_add_inline_style` from `siarhe_map_options` and read in `core.js`.

Marker names/labels and metric definitions are configured dynamically in admin options (not hard-coded in JS); keep new marker/metric behavior driven by those configs.

Marker `tipo` values: `posicion` (fixed symbol), `espectro` (bubble sized by value) and `spike` (triangle whose height and color follow the value; the picked color is the tallest spike and lower values fade toward `fill2` or a lightened version). Espectro and spike share the "Densidad" selector (`app.utils.markerGroup`) and the numeric column config (`espectro_col`). Marker geometry for all types lives in `app.map._markerShape` (used by the initial paint and the zoom handler). Marker colors (`m_<key>_fill`, `m_<key>_fill2`, ...) are edited in Personalizar > Mapa & Colores; data/behavior in Configuración > Marcadores.

Distance measuring (`app.map.initMeasure/setMeasureMode/measureAddPoint/renderMeasure`, only created when a `posicion` marker exists): toolbar ruler button toggles a mode where clicking position markers measures straight-line (great-circle) distances in two types (`ms.mode`): `chain` (route A→B→C with per-leg and total, up to 10 points) and `radial` (first marker is the center; each other marker gets its own independent distance, up to 20, with nearest/farthest summary); clicking an already-selected marker removes it (route A-B-C becomes A-C; in radial mode removing the center promotes the next point); the panel can copy the result or download it as UTF-8 CSV (`_measureCsv`, one row per point; text fields are guarded against Excel formula injection); the SVG layer `gMeasure` redraws on zoom with constant label size and a results panel (`.siarhe-measure-panel`) lists legs/total with undo/clear/copy. Spike and espectro markers are deliberately not measurable.

## Notes

- `.gitignore` excludes `*.csv`, `*.json`, `*.geojson`, `*.xlsx` — data files aren't versioned.
- `estructura.bat`/`estructura.txt` just dump the directory tree.

## Roadmap / pending work

Agreed direction: this is the **static** version of the map (admin uploads GeoJSON + CSV). A **dynamic map backed by SQL** comes later; the admin "Gestor de Bases" is where it will live.

### Admin (backend) overhaul — proposed, awaiting go-ahead

Findings that motivate it: the action buttons in the upload tables misalign (inline `<form style="display:inline">` around the delete button + `.button-small` + dashicons baselines; fix with a flex row); Dashboard and Gestor de Bases are identical 6-line placeholders; partials total ~5,500 lines mixing PHP, inline JS and 20–90 inline `style=""` attributes each (400+ overall); the three upload tabs (geojson/localidades/static) are ~90% duplicated (~530 lines each); `siarhe-admin.css` is only ~275 lines with no design tokens; `tab-mapa.php` refreshes previews with a 400 ms `setInterval`; the UI uses native `alert()`/`confirm()`; no loading/empty states or upload progress.

Open decisions (recommended defaults in bold):
1. Style: **stay inside the WordPress admin look with a modern component layer** (cards, tokens; respects WP admin color schemes and dark modes) vs. a fully custom app-like skin.
2. Order: **Phase 1 + Phase 2 (Dashboard) first, then Phase 3, Gestor de Bases last.**

Phases:
- **Phase 1 – design system + fixes:** CSS variables (colors, spacing scale, radii, shadows) and reusable components (cards, buttons, badges, tables, grid forms); flex `.siarhe-row-actions` with uniform sizes; move inline styles to classes; consistent page header (title, description, actions); replace `alert`/`confirm` with a modal + toasts; replace the preview `setInterval` with events; responsive on mobile.
- **Phase 2 – real Dashboard:** 34-entity matrix showing which have GeoJSON / CSV / localidades / markers and last update; copy-shortcode button per entity (`[siarhe_mapa_MT_CVE_ENT_09]`); health checks (upload dir writable, `upload_max_filesize`, PHP memory, plugin version, D3 CDN reachability).
- **Phase 3 – unify and improve uploads:** one parametrized upload component for GeoJSON/localidades/static CSV; drag & drop, progress bar, preview of the first rows before uploading; **version history UI with restore** (the last 3 versions already live in `_versions/` but there is no UI to use them); search/filters on every table.
- **Phase 4 – Gestor de Bases (dynamic map):** ingest CSV/XLSX into SQL in batches of 50 or 100 rows via AJAX (`admin-ajax`, nonces + `current_user_can`) with progress, resume, validation report and import log. Reuse the Phase 3 upload component and the existing CSV validation (`validate_csv_file`).

Additional admin ideas: export/import of the whole configuration as one JSON (colors, metrics, markers, tooltips) to move staging → production; audit log ("who uploaded/changed what"; today only the last editor is stored); a dedicated plugin capability so editors can upload data without being administrators; "restore defaults" button for the color settings.

### Map / frontend — known open items
- Spike markers: heights are fixed at 25–100 viewBox units (×1.5 on touch), linear between the marker's min and max value. Possible follow-ups: make max height and scale type (linear / square root / percentile cap) configurable in Personalizar; with outliers most spikes sit near the minimum height. Heights are not comparable across entity pages (min/max are computed per loaded dataset).
- Markers have no keyboard focus (thousands of points); selection for measuring is mouse/touch only. The measure panel is the accessible part (live announcements, copy, CSV).
- On non-fullscreen mobile the legends top out around 6 px text because of the map's small width; fullscreen gets ~10 px.
- D3/Select2 CDN fallback only triggers on failure or an incomplete library; a very slow (not failing) CDN waits for the browser timeout (no explicit timeout yet).
- Marker CSVs (10-14 MB, ~41k rows) are downloaded, parsed, filtered and grouped in `public/js/siarhe-worker.js` (Web Worker, URL passed via `data-worker`); `loadMarkerData` in `siarhe-controls.js` falls back to the main-thread path (`d3.csv`) if workers are unavailable or fail. Both paths must keep the same filtering/grouping logic. The static-base CSV (small) and GeoJSON still load on the main thread.
- Large data files (>= 100 KB) are read from a copy (`*.geojson.json` / `*.csv.txt`, made by `siarhe_data_read_url()` / `siarhe_build_read_copy()` in `includes/helpers.php`) because many servers serve `.csv`/`.geojson` as `application/octet-stream` and do not compress them; the original stays untouched (backups, versions, download). Option `siarhe_read_copies` turns it off.
- Marker rendering: one SVG `<path>` per point; zoom only refreshes visible markers (`_refreshMarkerGeometry`), events are delegated on the markers layer. `?siarhe_perf=1` logs timings to the console. If national zoom is still slow, the next step is canvas for `posicion` markers.

