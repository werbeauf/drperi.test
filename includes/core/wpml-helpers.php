<?php
/* ============================================================
   DATEI: includes/core/wpml-helpers.php
   ZWECK: Zentrale WPML-Kompatibilitaets-Helpers fuer das Plugin.

   Alle Helpers sind defensive: sie liefern sinnvolle Defaults
   wenn WPML nicht aktiv ist, sodass das Plugin auch auf Sites
   ohne WPML laeuft. Das Reaktivieren auf nicht-WPML-Sites ist
   trivialer Failover.

   Kanonische Patterns:
     - ACF Options-Reads ueber wa_get_options_field()
     - Term-Lookups by Slug ueber wa_get_term_by_slug_localized()
     - Phorest-Sync prueft wa_wpml_is_default_lang_post()
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Liefert den ISO-Code der aktuellen Sprache.
 *
 * Konsultiert zuerst `$sitepress->get_current_language()` (folgt
 * `switch_lang()` Calls), faellt dann auf die Request-Konstante
 * `ICL_LANGUAGE_CODE` und auf `wpml_current_language` Filter zurueck.
 *
 * @return string z.B. 'de' oder 'en'. Default-Lang wenn WPML inaktiv.
 */
function wa_wpml_current_lang() {
    if ( ! empty( $GLOBALS['sitepress'] ) && method_exists( $GLOBALS['sitepress'], 'get_current_language' ) ) {
        $lang = $GLOBALS['sitepress']->get_current_language();
        if ( $lang ) {
            return $lang;
        }
    }
    $filtered = apply_filters( 'wpml_current_language', null );
    if ( $filtered ) {
        return $filtered;
    }
    if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
        return ICL_LANGUAGE_CODE;
    }
    return wa_wpml_default_lang();
}

/**
 * Liefert den ISO-Code der WPML-Default-Sprache.
 *
 * @return string Default 'de' wenn WPML inaktiv.
 */
function wa_wpml_default_lang() {
    if ( ! empty( $GLOBALS['sitepress'] ) && method_exists( $GLOBALS['sitepress'], 'get_default_language' ) ) {
        $lang = $GLOBALS['sitepress']->get_default_language();
        if ( $lang ) {
            return $lang;
        }
    }
    if ( function_exists( 'icl_get_default_language' ) ) {
        $lang = icl_get_default_language();
        if ( $lang ) {
            return $lang;
        }
    }
    return 'de';
}

/**
 * Resolved eine Object-ID (post oder term) auf die aktuelle (oder
 * angegebene) Sprache via WPML-Filter. Wenn keine Translation
 * existiert, wird (per Default) die Original-ID zurueckgeliefert.
 *
 * @param int    $id        Original-ID
 * @param string $type      'post', 'product', 'product_cat', 'category', etc.
 * @param bool   $return_original_if_missing
 * @param string|null $lang ISO-Code, default = current language
 * @return int|null
 */
function wa_wpml_object_id( $id, $type = 'post', $return_original_if_missing = true, $lang = null ) {
    $id = (int) $id;
    if ( ! $id ) {
        return null;
    }
    if ( $lang === null ) {
        $lang = wa_wpml_current_lang();
    }
    return apply_filters( 'wpml_object_id', $id, $type, (bool) $return_original_if_missing, $lang );
}

/**
 * Prueft ob ein Post in der Default-Sprache liegt.
 * Genutzt vom Phorest-Sync: nur Default-Lang-Produkte bekommen
 * den Title vom Phorest ueberschrieben.
 *
 * @param int $post_id
 * @return bool true wenn Post in Default-Sprache (oder WPML inaktiv).
 */
function wa_wpml_is_default_lang_post( $post_id ) {
    $post_id = (int) $post_id;
    if ( ! $post_id ) {
        return true;
    }
    if ( ! defined( 'ICL_LANGUAGE_CODE' ) ) {
        return true;
    }
    $info = apply_filters( 'wpml_post_language_details', null, $post_id );
    if ( ! is_array( $info ) || empty( $info['language_code'] ) ) {
        return true;
    }
    return $info['language_code'] === wa_wpml_default_lang();
}

/**
 * Liest ein Feld aus einer ACF-Options-Gruppe mit WPML-Fallback-Chain:
 *
 *   1. options_{current_language}
 *   2. options_{default_language}
 *   3. plain options
 *
 * Identische Semantik wie das fruehere lokale wa_get_footer_field(),
 * aber generisch fuer beliebige Options-Gruppen.
 *
 * @param string      $group   ACF Options-Group-Name (z.B. 'footer', 'single_product').
 * @param string|null $key     Sub-Key innerhalb der Gruppe. null = ganze Gruppe.
 * @param mixed       $default Default wenn nicht gefunden.
 * @return mixed
 */
function wa_get_options_field( $group, $key = null, $default = null ) {
    if ( ! function_exists( 'get_field' ) ) {
        return $default;
    }

    $value        = false;
    $current_lang = wa_wpml_current_lang();
    $default_lang = wa_wpml_default_lang();

    // 1. Aktuelle Sprache.
    if ( $current_lang ) {
        $value = get_field( $group, 'options_' . $current_lang );
    }

    // 2. Default-Sprache als Fallback (wenn current != default).
    if ( empty( $value ) && $default_lang && $default_lang !== $current_lang ) {
        $value = get_field( $group, 'options_' . $default_lang );
    }

    // 3. Plain options als letzter Fallback.
    if ( empty( $value ) ) {
        $value = get_field( $group, 'option' );
    }

    if ( empty( $value ) ) {
        return $default;
    }

    // Wenn kein Sub-Key, ganze Gruppe (oder Skalar) zurueckgeben.
    if ( $key === null ) {
        return $value;
    }

    if ( is_array( $value ) && isset( $value[ $key ] ) ) {
        return $value[ $key ];
    }

    return $default;
}

/**
 * Liefert den Term in der AKTUELLEN Sprache fuer einen gegebenen Slug,
 * auch wenn der Slug im Code in der Default-Sprache notiert ist.
 *
 * Strategie:
 *   1. Direkt versuchen (klappt wenn Slugs in beiden Sprachen identisch).
 *   2. Default-Sprache temporaer einstellen, Term holen, ID auf Current-Lang
 *      mappen via wpml_object_id Filter.
 *
 * Bei inaktivem WPML reduziert sich das zu einem normalen get_term_by()-Call.
 *
 * @param string $slug
 * @param string $taxonomy default 'product_cat'.
 * @return WP_Term|null
 */
/**
 * Body-Class fuer aktuelle Sprache (z.B. wa-lang-de / wa-lang-en).
 * Erlaubt CSS-Targeting bei sprachspezifischen Overrides.
 */
add_filter( 'body_class', 'wa_wpml_body_class' );
function wa_wpml_body_class( $classes ) {
    if ( ! is_array( $classes ) ) {
        return $classes;
    }
    $lang = wa_wpml_current_lang();
    if ( $lang ) {
        $classes[] = 'wa-lang-' . sanitize_html_class( $lang );
    }
    return $classes;
}

function wa_get_term_by_slug_localized( $slug, $taxonomy = 'product_cat' ) {
    $slug = (string) $slug;
    if ( '' === $slug ) {
        return null;
    }

    // 1. Direkt versuchen.
    $term = get_term_by( 'slug', $slug, $taxonomy );
    if ( $term && ! is_wp_error( $term ) ) {
        return $term;
    }

    // 2. WPML-Path: Default-Sprache als Quelle nehmen, ID auf Current-Lang mappen.
    if ( empty( $GLOBALS['sitepress'] ) ) {
        return null;
    }

    /** @var \SitePress|object $sitepress */
    $sitepress = $GLOBALS['sitepress'];
    if ( ! method_exists( $sitepress, 'switch_lang' ) ) {
        return null;
    }

    $default = wa_wpml_default_lang();
    $current = wa_wpml_current_lang();
    if ( $default === $current ) {
        return null;
    }

    $sitepress->switch_lang( $default );
    $default_term = get_term_by( 'slug', $slug, $taxonomy );
    $sitepress->switch_lang( $current );

    if ( ! $default_term || is_wp_error( $default_term ) ) {
        return null;
    }

    $local_id = wa_wpml_object_id( $default_term->term_id, $taxonomy, true, $current );
    if ( ! $local_id ) {
        return null;
    }

    $local_term = get_term( (int) $local_id, $taxonomy );
    return ( $local_term && ! is_wp_error( $local_term ) ) ? $local_term : null;
}
