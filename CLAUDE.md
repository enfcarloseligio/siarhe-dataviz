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

## Notes

- `.gitignore` excludes `*.csv`, `*.json`, `*.geojson`, `*.xlsx` — data files aren't versioned.
- `estructura.bat`/`estructura.txt` just dump the directory tree.
