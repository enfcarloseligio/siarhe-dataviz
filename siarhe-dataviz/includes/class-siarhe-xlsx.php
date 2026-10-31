<?php // /includes/class-siarhe-xlsx.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Generador minimo de archivos .xlsx (sin librerias): hojas de texto con
 * encabezado en negritas y columnas con ancho automatico. Usa ZipArchive.
 */
class Siarhe_Xlsx {

    public static function available() {
        return class_exists( 'ZipArchive' );
    }

    private static function col_letter( $i ) {
        $s = '';
        for ( $i = $i + 1; $i > 0; $i = intdiv( $i - 1, 26 ) ) $s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
        return $s;
    }

    private static function esc( $v ) {
        // Quita caracteres de control que XML no admite
        $v = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $v );
        return htmlspecialchars( $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private static function sheet_xml( $rows, $header = true ) {
        $widths = array();
        $data = '';
        foreach ( $rows as $r => $row ) {
            $data .= '<row r="' . ( $r + 1 ) . '">';
            foreach ( array_values( $row ) as $c => $val ) {
                $val = (string) $val;
                $len = function_exists( 'mb_strlen' ) ? mb_strlen( $val ) : strlen( $val );
                $widths[ $c ] = max( isset( $widths[ $c ] ) ? $widths[ $c ] : 8, min( 70, $len + 2 ) );
                // Todo como texto en linea: conserva ceros iniciales (01, 001)
                $style = ( $header && $r === 0 ) ? ' s="1"' : '';
                $data .= '<c r="' . self::col_letter( $c ) . ( $r + 1 ) . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::esc( $val ) . '</t></is></c>';
            }
            $data .= '</row>';
        }
        $cols = '';
        foreach ( $widths as $c => $w ) $cols .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . $w . '" customWidth="1"/>';
        $view = $header ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $view
            . ( $cols ? '<cols>' . $cols . '</cols>' : '' ) . '<sheetData>' . $data . '</sheetData></worksheet>';
    }

    /**
     * Construye el libro. $sheets: array( 'Nombre de hoja' => array( fila, fila... ) ).
     * Devuelve el contenido binario o false si no se pudo crear.
     */
    public static function build( $sheets ) {
        if ( ! self::available() || ! $sheets ) return false;
        $tmp = wp_tempnam( 'siarhe-xlsx' );
        $zip = new ZipArchive();
        if ( $zip->open( $tmp, ZipArchive::OVERWRITE ) !== true ) return false;

        $names = array_keys( $sheets );
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 0;
        foreach ( $names as $name ) {
            $i++;
            $title = mb_substr( preg_replace( '/[\\\\\/\?\*\[\]:]/', ' ', $name ), 0, 31 );
            $ct   .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb   .= '<sheet name="' . self::esc( $title ) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            // La hoja de notas no lleva encabezado en negritas por fila, solo la primera linea
            $zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', self::sheet_xml( $sheets[ $name ], true ) );
        }
        $rels .= '<Relationship Id="rId' . ( $i + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $ct   .= '</Types>';
        $wb   .= '</sheets></workbook>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>';

        $zip->addFromString( '[Content_Types].xml', $ct );
        $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
        $zip->addFromString( 'xl/workbook.xml', $wb );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );
        $zip->addFromString( 'xl/styles.xml', $styles );
        $zip->close();

        $bin = file_get_contents( $tmp );
        @unlink( $tmp );
        return $bin;
    }

    /** Envia el libro como descarga y termina. Devuelve false si no pudo generarlo. */
    public static function send( $filename, $sheets ) {
        $bin = self::build( $sheets );
        if ( $bin === false ) return false;
        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Length: ' . strlen( $bin ) );
        echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput -- binario .xlsx
        exit;
    }
}
