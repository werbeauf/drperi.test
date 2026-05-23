<?php
/* ============================================================
   DATEI: admin/icon-preview-admin.php
   ZWECK: Zeigt Icon-SVGs als Preview links neben den Optionen in
          den ACF Select2 Dropdowns (Facts, Keypoints, Trust-Items).

   Wir hooken in den ACF-Filter "select2_args" und ueberschreiben
   templateResult + templateSelection, damit das SVG VOR dem Label
   gerendert wird. Die SVG-Map wird per wp_localize_script aus
   Werbeauf_Single_Product_Renderer::icon_svg() gespeist.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'acf/input/admin_enqueue_scripts', 'wa_icon_preview_enqueue' );

function wa_icon_preview_enqueue() {
    if ( ! class_exists( 'Werbeauf_Single_Product_Renderer' ) ) {
        return;
    }

    $paths   = Werbeauf_Single_Product_Renderer::icon_paths();
    $svg_map = array();
    foreach ( array_keys( $paths ) as $key ) {
        $svg_map[ $key ] = Werbeauf_Single_Product_Renderer::icon_svg( $key );
    }

    // Field-Keys, deren Dropdown-Eintraege Icon-Previews bekommen.
    $field_keys = array(
        'field_wa_product_facts_icon',
        'field_wasp_trust_icon',
    );

    $handle = 'wa-icon-preview';
    wp_register_script( $handle, '', array( 'acf-input', 'jquery' ), '0.1.0', true );
    wp_enqueue_script( $handle );
    wp_localize_script(
        $handle,
        'waIconPreview',
        array(
            'svg'  => $svg_map,
            'keys' => $field_keys,
        )
    );

    wp_add_inline_script( $handle, wa_icon_preview_js() );

    wp_register_style( $handle, '', array(), '0.1.0' );
    wp_enqueue_style( $handle );
    wp_add_inline_style( $handle, wa_icon_preview_css() );
}

function wa_icon_preview_js() {
    return <<<'JS'
(function($){
    if ( typeof acf === 'undefined' || ! window.waIconPreview ) return;

    var svgMap = window.waIconPreview.svg || {};
    var watchedKeys = window.waIconPreview.keys || [];

    function escapeText(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderResult(item) {
        if ( ! item.id ) {
            return escapeText( item.text );
        }
        var svg = svgMap[ item.id ] || '';
        var inner = svg
            ? '<span class="wa-icon-preview-svg">' + svg + '</span>'
            : '<span class="wa-icon-preview-svg wa-icon-preview-svg--empty"></span>';
        return '<span class="wa-icon-preview-row">'
            + inner
            + '<span class="wa-icon-preview-label">' + escapeText( item.text ) + '</span>'
            + '</span>';
    }

    function renderSelection(item) {
        return escapeText( item.text || '' );
    }

    var hookApi = acf.addFilter ? acf : ( acf.add_filter ? { addFilter: acf.add_filter } : null );
    if ( ! hookApi ) return;

    hookApi.addFilter( 'select2_args', function( options, $select, settings, field, instance ) {
        try {
            var key = field && field.get ? field.get('key') : null;
            if ( ! key || watchedKeys.indexOf( key ) === -1 ) {
                return options;
            }
            options.templateResult    = renderResult;
            options.templateSelection = renderSelection;
            options.escapeMarkup      = function(m){ return m; };
        } catch(e) {
            // Stumm bei API-Aenderungen in ACF -- Fallback bleibt der
            // unmodifizierte Select2.
        }
        return options;
    });
})(jQuery);
JS;
}

function wa_icon_preview_css() {
    return <<<'CSS'
.wa-icon-preview-row {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1;
}
.wa-icon-preview-svg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
    color: #475e76;
}
.wa-icon-preview-svg svg {
    width: 100%;
    height: 100%;
    display: block;
}
.wa-icon-preview-svg--empty {
    background: #e5eaee;
    border-radius: 3px;
    opacity: 0.5;
}
.wa-icon-preview-label {
    font-size: 13px;
}
.select2-results__option .wa-icon-preview-row {
    padding: 2px 0;
}
.select2-selection__rendered .wa-icon-preview-row {
    line-height: inherit;
}
CSS;
}
