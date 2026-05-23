<?php
/* ============================================================
   DATEI: includes/phorest/newsletter.php
   ZWECK: Newsletter-Anbindung an Phorest /client mit
          Search-then-Create Dedupe-Logik.

   Wiederverwendet:
     - wa_phorest_api( $method, $path, $body ) aus order-sync.php
     - wa_phorest_active / wa_phorest_business_id Settings

   Public APIs:
     - wa_phorest_newsletter_subscribe( $email, $first, $last, $source )
     - REST: POST /wp-json/wa/v1/newsletter
     - WC Store API extension namespace 'wa-newsletter' fuer
       checkout.cart.extensions['wa-newsletter'].consent (bool)

   DSGVO: emailMarketingConsent darf NUR durch explizite User-
   Aktion (Checkbox) auf true gesetzt werden -- siehe Validation
   in wa_nl_rest_subscribe() und Hook fuer WC-Blocks.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WA_NL_LOG_OPTION' ) )   { define( 'WA_NL_LOG_OPTION',   'wa_newsletter_log' ); }
if ( ! defined( 'WA_NL_LOG_MAX' ) )      { define( 'WA_NL_LOG_MAX',      100 ); }
if ( ! defined( 'WA_NL_NONCE_ACTION' ) ) { define( 'WA_NL_NONCE_ACTION', 'wa_newsletter' ); }
if ( ! defined( 'WA_NL_RATE_LIMIT' ) )   { define( 'WA_NL_RATE_LIMIT',   5 ); }
if ( ! defined( 'WA_NL_RATE_WINDOW' ) )  { define( 'WA_NL_RATE_WINDOW',  60 ); }
if ( ! defined( 'WA_NL_TIME_GATE' ) )    { define( 'WA_NL_TIME_GATE',    3 ); }

// Phorest Client-Category, der bei jedem Push (Create + Update) gesetzt
// wird. Dient zur Newsletter-Segmentierung in Phorest.
if ( ! defined( 'WA_NL_PHOREST_CATEGORY_ID' ) ) {
    define( 'WA_NL_PHOREST_CATEGORY_ID', 'f8-7zDrclzLELKLTN17GFQ' );
}

/* ----------------------------------------------------------
   1. Core subscribe function -- Search/Update/Create Dedupe.
   Returns string status ('created' | 'updated' | 'already_subscribed')
   on success, WP_Error on failure.
---------------------------------------------------------- */
function wa_phorest_newsletter_subscribe( $email, $first_name, $last_name, $source = 'widget' ) {
    if ( ! get_option( 'wa_phorest_active', 0 ) ) {
        return new WP_Error( 'phorest_inactive', __( 'Phorest API ist deaktiviert.', 'werbeauf-customs' ) );
    }

    $email = sanitize_email( $email );
    if ( ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', __( 'Ungueltige E-Mail-Adresse.', 'werbeauf-customs' ) );
    }
    // Normalisierung: alle Newsletter-Emails landen lowercase in Phorest, damit
    // zukuenftig keine Casing-Drifts neue Duplikate erzeugen.
    $email = strtolower( $email );

    $first_name = wa_nl_clean_name( $first_name );
    $last_name  = wa_nl_clean_name( $last_name );
    if ( $first_name === '' || $last_name === '' ) {
        return new WP_Error( 'invalid_name', __( 'Vor- und Nachname sind erforderlich.', 'werbeauf-customs' ) );
    }

    $business_id = get_option( 'wa_phorest_business_id', '' );
    if ( $business_id === '' ) {
        return new WP_Error( 'missing_config', __( 'Phorest Business ID fehlt.', 'werbeauf-customs' ) );
    }

    if ( ! function_exists( 'wa_phorest_api' ) ) {
        return new WP_Error( 'missing_api', __( 'Phorest API-Wrapper nicht verfuegbar.', 'werbeauf-customs' ) );
    }

    $client_path = 'api/business/' . rawurlencode( $business_id ) . '/client';

    // 1. Search Phorest by email -- defensiv: archivierte einschliessen,
    //    zweite Page falls noetig, dann clientseitig case-insensitiv matchen.
    $existing = wa_nl_find_phorest_client_by_email( $business_id, $email );

    if ( is_array( $existing ) && ! empty( $existing['clientId'] ) ) {
        $current_consent = ! empty( $existing['emailMarketingConsent'] );

        if ( $current_consent ) {
            wa_nl_log( $email, 'already_subscribed', $source );
            return 'already_subscribed';
        }

        // 2. Update Consent. Phorest PUT erwartet das volle Objekt -- wir
        //    schicken den existierenden Datensatz zurueck, nur Consent-Flag flippt.
        $update_payload = $existing;
        unset( $update_payload['_links'], $update_payload['_embedded'] );
        $update_payload['emailMarketingConsent'] = true;
        $update_payload['clientCategoryIds']     = wa_nl_merge_category_ids(
            $existing['clientCategoryIds'] ?? array(),
            WA_NL_PHOREST_CATEGORY_ID
        );

        $updated = wa_phorest_api(
            'PUT',
            $client_path . '/' . rawurlencode( $existing['clientId'] ),
            $update_payload
        );

        if ( is_wp_error( $updated ) ) {
            wa_nl_log( $email, 'error', $source );
            return $updated;
        }

        wa_nl_log( $email, 'updated', $source );
        return 'updated';
    }

    // 3. Create new client (kein Match -> echt neuer Datensatz).
    $created = wa_phorest_api( 'POST', $client_path, array(
        'firstName'             => $first_name,
        'lastName'              => $last_name,
        'email'                 => $email,
        'emailMarketingConsent' => true,
        'clientCategoryIds'     => array( WA_NL_PHOREST_CATEGORY_ID ),
    ) );

    if ( is_wp_error( $created ) ) {
        wa_nl_log( $email, 'error', $source );
        return $created;
    }

    wa_nl_log( $email, 'created', $source );
    return 'created';
}

/**
 * Find an existing Phorest client by email, defensively.
 *
 * Phorest's `?email=` Query-Filter macht einen exakten String-Match. Wenn die
 * Email mit anderem Casing in Phorest gespeichert ist (z.B. ueber die Phorest-
 * UI manuell mit Grossbuchstaben angelegt), schlaegt der Filter still fehl --
 * Phorest liefert `_embedded.clients = []` und unser Code legt einen Duplikat-
 * Datensatz an.
 *
 * Defense-in-depth Strategie:
 *   1. Search mit ?email=... &includeArchived=true (auch archivierte finden)
 *   2. Falls leer: Search mit Email lowercase (manuelle Casing-Variante)
 *   3. Beide Resultate clientseitig case-insensitiv gegen die Soll-Email matchen
 *   4. Bevorzugt non-archived/non-deleted Treffer
 *
 * @param string $business_id Phorest Business ID.
 * @param string $email       Bereits validierte und lowercased Email-Adresse.
 * @return array|null Phorest Client-Object oder null wenn nichts gefunden.
 */
function wa_nl_find_phorest_client_by_email( $business_id, $email ) {
    $client_path = 'api/business/' . rawurlencode( $business_id ) . '/client';
    $needle      = strtolower( trim( $email ) );
    $candidates  = array();

    $variants = array_unique( array( $email, strtolower( $email ) ) );
    foreach ( $variants as $variant ) {
        $url = $client_path
            . '?email=' . rawurlencode( $variant )
            . '&includeArchived=true'
            . '&size=20';

        $response = wa_phorest_api( 'GET', $url );
        if ( is_wp_error( $response ) ) {
            continue;
        }

        $clients = $response['_embedded']['clients'] ?? $response['clients'] ?? array();
        if ( ! is_array( $clients ) ) {
            continue;
        }

        foreach ( $clients as $c ) {
            if ( ! is_array( $c ) || empty( $c['email'] ) ) {
                continue;
            }
            if ( strtolower( trim( $c['email'] ) ) === $needle ) {
                $candidates[] = $c;
            }
        }

        if ( ! empty( $candidates ) ) {
            break; // Ein Treffer reicht -- nicht weiter suchen.
        }
    }

    if ( empty( $candidates ) ) {
        return null;
    }

    // Bevorzuge active/non-deleted/non-archived Treffer, ansonsten erster.
    foreach ( $candidates as $c ) {
        if ( empty( $c['archived'] ) && empty( $c['deleted'] ) ) {
            return $c;
        }
    }
    return $candidates[0];
}

/**
 * Merge a Phorest clientCategoryId into an existing category list.
 *
 * Phorest PUT ueberschreibt das gesamte Array, deshalb sammeln wir bestehende
 * Categories des Kunden ein und ergaenzen unsere Newsletter-Category nur,
 * falls sie noch nicht enthalten ist (Idempotenz + keine Datenverluste).
 *
 * @param mixed  $existing_ids Wert von $existing['clientCategoryIds']
 *                             (kann fehlen, null, scalar oder Array sein).
 * @param string $add_id       Zu erzwingende Category-ID.
 * @return array Bereinigte, unique, indizierte Category-ID-Liste.
 */
function wa_nl_merge_category_ids( $existing_ids, $add_id ) {
    if ( ! is_array( $existing_ids ) ) {
        $existing_ids = array();
    }

    $clean = array();
    foreach ( $existing_ids as $id ) {
        if ( is_string( $id ) && $id !== '' ) {
            $clean[] = $id;
        }
    }

    if ( ! in_array( $add_id, $clean, true ) ) {
        $clean[] = $add_id;
    }

    return array_values( array_unique( $clean ) );
}

/* ----------------------------------------------------------
   2. Helpers -- name cleanup, log writer, IP detection.
---------------------------------------------------------- */
function wa_nl_clean_name( $name ) {
    $name = sanitize_text_field( (string) $name );
    $name = trim( $name );
    if ( strlen( $name ) > 50 ) {
        $name = substr( $name, 0, 50 );
    }
    return $name;
}

function wa_nl_log( $email, $status, $source ) {
    $entry = array(
        'time'   => current_time( 'mysql' ),
        'hash'   => sha1( strtolower( $email ) ),
        'status' => $status,
        'source' => $source,
    );
    $log = get_option( WA_NL_LOG_OPTION, array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }
    $log[] = $entry;
    if ( count( $log ) > WA_NL_LOG_MAX ) {
        $log = array_slice( $log, -WA_NL_LOG_MAX );
    }
    update_option( WA_NL_LOG_OPTION, $log, false );
}

function wa_nl_get_ip() {
    foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
        if ( empty( $_SERVER[ $key ] ) ) {
            continue;
        }
        $ip  = is_string( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : '';
        $ip  = trim( explode( ',', $ip )[0] );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

/* ----------------------------------------------------------
   3. REST endpoint POST /wa/v1/newsletter
---------------------------------------------------------- */
add_action( 'rest_api_init', function () {
    register_rest_route( 'wa/v1', '/newsletter', array(
        'methods'             => 'POST',
        'callback'            => 'wa_nl_rest_subscribe',
        'permission_callback' => '__return_true', // Form-Nonce + Rate-Limit + Honeypot covern CSRF.
    ) );
} );

function wa_nl_rest_subscribe( $request ) {
    $params = $request->get_params();

    // Eigenes Form-Nonce-Feld -- absichtlich NICHT '_wpnonce', weil das von
    // rest_cookie_check_errors() vor unserem permission_callback gepruefte
    // Standard-Feld ist und dort gegen die 'wp_rest'-Action verifiziert
    // wird (was einen 403 "Die Cookie-Pruefung ist fehlgeschlagen" liefert,
    // bevor wir hier ankommen).
    $nonce = $params['_wa_nl_nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, WA_NL_NONCE_ACTION ) ) {
        return new WP_REST_Response( array( 'message' => __( 'Sicherheits-Check fehlgeschlagen. Bitte Seite neu laden.', 'werbeauf-customs' ) ), 403 );
    }

    if ( ! empty( $params['_hp'] ) ) {
        return new WP_REST_Response( array( 'message' => __( 'Ungueltige Anfrage.', 'werbeauf-customs' ) ), 422 );
    }

    $rendered_at = (int) ( $params['_t'] ?? 0 );
    if ( $rendered_at <= 0 || ( time() - $rendered_at ) < WA_NL_TIME_GATE ) {
        return new WP_REST_Response( array( 'message' => __( 'Bitte etwas langsamer.', 'werbeauf-customs' ) ), 422 );
    }

    $ip      = wa_nl_get_ip();
    $rl_key  = 'wa_nl_rl_' . md5( $ip );
    $rl_hits = (int) get_transient( $rl_key );
    if ( $rl_hits >= WA_NL_RATE_LIMIT ) {
        return new WP_REST_Response( array( 'message' => __( 'Zu viele Anfragen. Bitte spaeter erneut versuchen.', 'werbeauf-customs' ) ), 429 );
    }
    set_transient( $rl_key, $rl_hits + 1, WA_NL_RATE_WINDOW );

    $consent_raw = $params['consent'] ?? '';
    $consent     = in_array( $consent_raw, array( '1', 1, true, 'true', 'on', 'yes' ), true );
    if ( ! $consent ) {
        return new WP_REST_Response( array( 'message' => __( 'Einwilligung erforderlich.', 'werbeauf-customs' ) ), 422 );
    }

    $email      = (string) ( $params['email']      ?? '' );
    $first_name = (string) ( $params['first_name'] ?? '' );
    $last_name  = (string) ( $params['last_name']  ?? '' );
    $source     = sanitize_key( $params['source'] ?? 'widget' );
    if ( ! in_array( $source, array( 'widget', 'footer', 'checkout' ), true ) ) {
        $source = 'widget';
    }

    $result = wa_phorest_newsletter_subscribe( $email, $first_name, $last_name, $source );

    if ( is_wp_error( $result ) ) {
        $code = $result->get_error_code();
        $http = in_array( $code, array( 'invalid_email', 'invalid_name' ), true ) ? 422
              : ( $code === 'phorest_inactive' ? 503 : 500 );
        return new WP_REST_Response( array( 'message' => $result->get_error_message() ), $http );
    }

    $messages = array(
        'created'            => __( 'Du bist eingetragen -- vielen Dank!', 'werbeauf-customs' ),
        'updated'            => __( 'Newsletter aktiviert -- vielen Dank!', 'werbeauf-customs' ),
        'already_subscribed' => __( 'Du bist bereits eingetragen.', 'werbeauf-customs' ),
    );

    return new WP_REST_Response( array(
        'status'  => $result,
        'message' => $messages[ $result ] ?? __( 'Erfolgreich.', 'werbeauf-customs' ),
    ), 200 );
}

/* ----------------------------------------------------------
   4. WC-Blocks Checkout -- Store API extension.
   Stellt cart.extensions['wa-newsletter'].consent bereit und
   speichert den Wert in der WC-Session, damit der Order-Processed-
   Hook ihn nach dem Place-Order auslesen kann.
---------------------------------------------------------- */
add_action( 'woocommerce_blocks_loaded', 'wa_nl_register_store_api_extension' );
function wa_nl_register_store_api_extension() {
    if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
        return;
    }
    if ( ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartSchema' ) ) {
        return;
    }

    woocommerce_store_api_register_endpoint_data( array(
        'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
        'namespace'       => 'wa-newsletter',
        'data_callback'   => function () {
            $session = WC()->session;
            $consent = $session ? (bool) $session->get( 'wa_nl_consent', false ) : false;
            return array( 'consent' => $consent );
        },
        'schema_callback' => function () {
            return array(
                'consent' => array(
                    'description' => 'Newsletter consent for Phorest sync after order place',
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
            );
        },
        'schema_type'     => ARRAY_A,
    ) );

    if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
        woocommerce_store_api_register_update_callback( array(
            'namespace' => 'wa-newsletter',
            'callback'  => function ( $data ) {
                if ( ! WC()->session ) {
                    return;
                }
                WC()->session->set( 'wa_nl_consent', ! empty( $data['consent'] ) );
            },
        ) );
    }
}

/* ----------------------------------------------------------
   5. Order placed via WC-Blocks -> subscribe if consent.
---------------------------------------------------------- */
add_action( 'woocommerce_store_api_checkout_order_processed', 'wa_nl_after_blocks_order', 20, 1 );
function wa_nl_after_blocks_order( $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        return;
    }
    $session = WC()->session;
    if ( ! $session ) {
        return;
    }

    $consent = (bool) $session->get( 'wa_nl_consent', false );
    // Reset session flag immediately so it doesn't leak into the next order.
    $session->set( 'wa_nl_consent', false );

    if ( ! $consent ) {
        return;
    }

    $email = $order->get_billing_email();
    $first = $order->get_billing_first_name();
    $last  = $order->get_billing_last_name();

    if ( ! is_email( $email ) || $first === '' || $last === '' ) {
        return;
    }

    $result = wa_phorest_newsletter_subscribe( $email, $first, $last, 'checkout' );

    if ( is_wp_error( $result ) ) {
        $order->add_order_note( 'Newsletter-Anmeldung fehlgeschlagen: ' . $result->get_error_message() );
    } else {
        $labels = array(
            'created'            => 'Newsletter: Neuer Phorest-Client mit Consent angelegt.',
            'updated'            => 'Newsletter: Bestehender Client um Consent ergaenzt.',
            'already_subscribed' => 'Newsletter: Client war bereits angemeldet.',
        );
        $order->add_order_note( $labels[ $result ] ?? ( 'Newsletter: ' . $result ) );
    }
}
