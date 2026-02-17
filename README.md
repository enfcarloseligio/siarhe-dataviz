# SIARHE Data Visualization Engine

Un plugin modular de WordPress para la gestión, análisis y visualización de datos de recursos humanos en enfermería (SIARHE).

## Descripción
Este sistema transforma WordPress en un Data Warehouse capaz de procesar archivos masivos (CSV/XLSX) de capital humano, formación y educación en salud, convirtiéndolos en tablas SQL optimizadas para su visualización mediante mapas interactivos (D3.js) y tableros de control.

## Características Principales
* **Ingesta de Datos ETL:** Procesamiento de archivos grandes (Pivote, Formaciones, Escuelas) hacia MySQL.
* **Visualización Geoespacial:** Generación de mapas coropléticos interactivos.
* **Shortcodes Modulares:** Inserción flexible de mapas y tablas en cualquier página.
* **Arquitectura Escalable:** Diseño modular preparado para futuras expansiones.

## Requisitos
* WordPress 5.8+
* PHP 7.4+
* MySQL 5.6+

## Instalación
1. Descarga el archivo `.zip`.
2. Sube el plugin a través del administrador de WordPress o extráelo en `/wp-content/plugins/`.
3. Activa el plugin desde el menú "Plugins".
4. Ve al menú "SIARHE" para comenzar la configuración.

## Licencia
[GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)