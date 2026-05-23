# WPML-Kompatibilitaet · Werbeauf Customs

Dr. Peri laeuft DE (default) + EN als Zweitsprache. WPML-Stack:
`acfml`, `wpml-string-translation`, `wpml-media-translation`,
`woocommerce-multilingual` (WCML).

Diese Doku ist Single-Source-of-Truth fuer:
- Welche Plugin-Mechanismen WPML-aware sind (Code-Patterns)
- Welche Aufgaben der Customer einmalig im WPML-Admin erledigen muss
- Bekannte Caveats und Verhalten ueber Sprachen hinweg

---

## 1. Plugin-Side Code-Patterns

### 1.1 Zentrale Helpers (`includes/core/wpml-helpers.php`)

| Funktion | Was sie liefert |
|---|---|
| `wa_wpml_current_lang()` | ISO-Code der aktuellen Sprache (`'de'` / `'en'`). Default-Lang wenn WPML inaktiv. |
| `wa_wpml_default_lang()` | ISO-Code der Default-Sprache (Fallback `'de'`). |
| `wa_wpml_object_id( $id, $type, $return_original_if_missing, $lang )` | Wrapper um `apply_filters( 'wpml_object_id', ... )` mit defensivem Default. |
| `wa_wpml_is_default_lang_post( $post_id )` | true wenn der Post in der Default-Sprache liegt. Genutzt vom Phorest-Sync. |
| `wa_get_options_field( $group, $key, $default )` | ACF-Options-Read mit Fallback `options_{lang}` -> `options_{default}` -> plain `options`. |
| `wa_get_term_by_slug_localized( $slug, $taxonomy )` | Term-Lookup by Slug, bei Mismatch in der Current-Lang fallback ueber Default-Lang + `wpml_object_id` Mapping. |

Body-Class `wa-lang-de` / `wa-lang-en` wird automatisch via `body_class` Filter gesetzt -- erlaubt sprachspezifische CSS-Overrides.

### 1.2 Wo werden die Patterns konsumiert?

| Bereich | Datei | Pattern |
|---|---|---|
| Footer-Inhalte (Address, Hours, Newsletter-Heading, ...) | `templates/footer.php` (`wa_get_footer_field`) | Wrapper um `wa_get_options_field('footer', $key)` |
| Trust-Items im Single-Product Hero | `includes/woocommerce/single-product-renderer.php::get_trust_items()` | `wa_get_options_field('single_product', 'trust_items', [])` |
| Header CTA-Button | `templates/header.php` (inline pattern) | `options_{lang}` Fallback (legacy, gleicher Effekt) |
| Footer-Blog-Posts-Query | `templates/footer.php:111` | `'lang' => wa_wpml_current_lang()` als WP_Query-Arg |
| FAQ JSON-LD Schema | `single-product-renderer.php::output_faq_schema()` | `'inLanguage' => wa_wpml_current_lang()` im Schema-Array |
| Shop-Filter Slug-Lookups | `includes/shortcodes/shop-filter.php` | `wa_get_term_by_slug_localized(...)` |
| Shop-Layout Featured-Category | `includes/shortcodes/shop-layout.php` | passthrough an `[wa_shop_filter]` (das nutzt den Localized-Helper) |
| Category-Products Shortcode | `includes/shortcodes/category-products.php` | `wa_get_term_by_slug_localized(...)` an 4 Stellen |
| Test Set-Table | `test/shortcodes/set-table.php` | `wa_get_term_by_slug_localized(...)` |
| Phorest Inbound-Sync (Title) | `includes/phorest/woo-sync.php:189` | `wa_wpml_is_default_lang_post( $post_id )` -- Title nur fuer DE-Post |
| Newsletter REST-Antworten | `includes/phorest/newsletter.php:336` | `__( ..., 'werbeauf-customs' )` |
| Newsletter Privacy-URL | `includes/shortcodes/newsletter-form.php:64` | `home_url( '/datenschutz/' )` -- WPML rewritet auf `/en/privacy/` falls Page uebersetzt + URL-Translation aktiv |

### 1.3 Textdomain

- Plugin-Header: `Text Domain: werbeauf-customs`
- `Domain Path: /languages`
- Loader: `add_action( 'plugins_loaded', 'wa_load_plugin_textdomain' )` in `werbeauf-customs.php`
- Alle `__()`/`_e()`/`esc_html__()`/`_n()` Calls verwenden `'werbeauf-customs'`.

---

## 2. Customer-Side WPML-Setup-Aufgaben (einmalig)

Diese Schritte muessen im WPML-Admin von Hand durchlaufen werden, sonst zeigt die EN-Site weiter DE-Inhalte.

### 2.1 Plugin-Strings registrieren

In WPML 4.x gibt es **keinen eigenen Menupunkt "Theme & Plugin Localization" mehr** -- die Funktion ist in die String-Translation-Seite integriert. Drei Wege:

**A. Auto-Register (empfohlen)**
1. WP-Admin > WPML > String Translation
2. Box "Auto register untranslated strings" aufklappen (am unteren Ende der Seite)
3. Auf "Only register strings from pages viewed by administrators" stellen + speichern
4. Als Admin ein paar EN-Frontend-Seiten besuchen (Single-Product, Shop, Newsletter-Form) -- WPML registriert die `__()`-Calls automatisch beim Rendern.

**B. Bulk-Import aus `languages/werbeauf-customs.pot`** (CLI, deterministisch)
```bash
wp eval '
$pot = WP_CONTENT_DIR . "/plugins/werbeauf-customs/languages/werbeauf-customs.pot";
preg_match_all( "/msgid \"((?:[^\"\\\\]|\\\\.)*)\"\nmsgstr/", file_get_contents( $pot ), $m );
foreach ( array_unique( $m[1] ) as $id ) {
    if ( $id === "" ) continue;
    $s = stripcslashes( $id );
    do_action( "wpml_register_single_string", "werbeauf-customs", $s, $s );
}
'
```

**Caveat:** WPML-Auto-Register speichert `name = md5(msgid)` (Konvention fuer
Gettext); der Bulk-Import oben schreibt `name = msgid`. Wenn beide Wege
parallel verwendet werden, entstehen Duplikat-Werte in der String-Tabelle.
Aufraeumen (DE-Backup voraus) mit:

```bash
wp eval '
global $wpdb;
$ids = $wpdb->get_col( "
  SELECT id FROM {$wpdb->prefix}icl_strings
  WHERE context = \"werbeauf-customs\" AND name = value
    AND value IN (
      SELECT v FROM ( SELECT value AS v FROM {$wpdb->prefix}icl_strings
        WHERE context = \"werbeauf-customs\"
        GROUP BY value HAVING COUNT(*) > 1 ) t
    )
" );
if ( $ids ) {
  $in = implode( ",", array_map( "intval", $ids ) );
  $wpdb->query( "DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id IN ($in)" );
  $wpdb->query( "DELETE FROM {$wpdb->prefix}icl_strings WHERE id IN ($in)" );
}
echo count( $ids ) . " duplicate rows deleted\n";
'
```

**C. Per-Klick Suche in der String-Table**
WPML > String Translation > Filter "in domain" auf `werbeauf-customs` setzen -> alle registrierten Strings durchblaettern und EN-Wert eintragen.

Stand 2026-04-30: Plugin hat 94 unique msgids im `.pot`-File. Aktuell sind 118 Strings unter Domain `werbeauf-customs` in der `wp_icl_strings` Tabelle registriert (62 via Auto-Register beim Frontend-Browsing + 56 zusaetzliche Bulk-Import-Strings; nach einmaligem Cleanup 0 Duplikat-Werte). Admin-Strings (Phorest-/Newsletter-Pages) sind nur sichtbar, wenn ein Admin im EN-Modus dorthin navigiert.

### 2.2 ACF Options-Pages auf "Translate" stellen

ACF Options-Page Slug: `dr-peri`. Im WPML-Admin (oder direkt in ACFML) pro Field-Group sicherstellen, dass die Translation-Mode korrekt ist:

| Field Group | Empfohlene Translation-Mode |
|---|---|
| Footer (`group_wa_footer`) | Translate gesamte Group |
| Assets / Single-Product (UI-Group `group_69b03d5413d51`) | Translate gesamte Group |

**Per-Field Empfehlungen** (innerhalb Footer-Group):

| Feld | Mode |
|---|---|
| `address` | Translate |
| `email` | Don't translate |
| `phone` | Don't translate |
| `opening_hours[].day` | Translate |
| `opening_hours[].hours` | Translate |
| `newsletter_heading` | Translate |
| `newsletter_intro` | Translate |
| `social_links[].platform` | Don't translate |
| `social_links[].url` | Don't translate |
| `copyright_text` | Translate |

### 2.3 Single-Product ACF-Field-Groups

| Group | Mode pro Feld |
|---|---|
| `group_wa_product_facts` | Repeater Translate; `text` Translate; `icon` Don't translate (key ist sprachunabhaengig) |
| `group_wa_product_keypoints` | Repeater Translate; `label` + `value` Translate; `icon` Don't translate |
| `group_wa_product_faq` | Repeater Translate; `question` + `answer` Translate; `headline` + `description` Translate |
| `group_wa_product_volume` (test) | `volume_ml` Translate (zB "150 ml" -> "150 ml fl oz"); `clean_featured_image` Don't translate (Asset) |
| `group_wa_product_test_todo` (test) | Empfehlung: Copy (Customer-interner Test-Todo, soll auf beiden Sprachen identisch sein) |

### 2.4 product_cat Term-Slugs

Wenn der Customer Term-Slugs in DE+EN identisch haelt (z.B. EN-Slug = DE-Slug "sets"), funktioniert der hardcoded Slug-Lookup direkt. Sonst greift der `wa_get_term_by_slug_localized()` Helper und mappt automatisch.

**Empfehlung**: Slugs identisch halten -- spart die Helper-Round-Trip-DB-Lookups.

### 2.5 Per-Sprache Term-Meta Farben

Auf jedem `product_cat` Term-Edit-Screen pro Sprache muessen die Color-Picker (Hintergrund + Schrift) gepflegt werden. Translated Terms haben separate `term_id` -> separate Term-Meta -> separate Farben. Die Farben sind sprachunabhaengig, aber pro Term-ID separat zu setzen.

### 2.6 WCML Product-Translation-Setup

WCML > Settings > Sicherstellen dass:
- Products werden in jede Sprache dupliziert
- Translation-Mode der Produkte: Translate (manuell oder via Translation-Service)
- Currency: optional pro Sprache

### 2.7 WPML "Translation of URLs" / Permalinks

Damit `home_url('/datenschutz/')` auf der EN-Site korrekt zur EN-Datenschutz-Page rewritet, muss:
- Die Datenschutz-Page in EN existieren
- WPML "Translation of URLs" aktiv sein
- Idealerweise im EN-Permalink ein eigener Slug sein (z.B. `/privacy/`)

### 2.8 Nav-Menus pro Sprache

WPML supportet pro Sprache eigene Nav-Menus an den Theme-Locations `footer-menu` / `footer-menu-2` / `footer-menu-3`. Customer pflegt das in WP-Admin > Design > Menues > Locations pro Sprache.

### 2.9 Blog-Posts (CPT `blog`)

Damit der Footer auf der EN-Site EN-Blog-Posts zeigt, muessen mind. 3 EN-Translations vorhanden sein. Sonst zeigt der Footer einen leeren Block (kein DE-Fallback -- das ist Absicht).

---

## 3. Phorest-Sync-Verhalten ueber Sprachen

| Sync | Verhalten |
|---|---|
| Title (`name`) | Nur Default-Sprache (DE) wird ueberschrieben. EN-Translations behalten ihren manuell uebersetzten Title. |
| SKU (`productId`) | Auf alle Sprachen synchronisiert (sprachunabhaengig). |
| Stock | Auf alle Sprachen synchronisiert. |
| Price | Auf alle Sprachen synchronisiert. |
| Outbound Stock-Sync (Order -> Phorest) | Order-Item-Product-ID wird verwendet; meta `_phorest_product_id` muss auf beiden Translations gesetzt sein (ist es by default, da WCML Meta dupliziert). |

Wenn der Customer ein DE-Produkt-Title in Phorest aendert -> nur DE-WC-Produkt aktualisiert. EN-Title bleibt manuell gepflegt.

---

## 4. Caveats / Bekannte Eigenheiten

- **Test-Bundle Pages-List Pfade** sind in DE notiert (`/auf-einen-blick/`, `/ueber-uns/`). `home_url()` rewritet sie auf der EN-Site, IF die EN-Pages existieren und WPML-URL-Translation aktiv ist. Sonst: 404 bei Klick.
- **Trust-Items Icon-Keys** sind sprachunabhaengig (z.B. `'leaf'`, `'check'`). Die Labels in den ACF-Dropdowns kommen ueber String-Translation.
- **WPML Body-Class** wird VOR den existierenden Klassen `wa-woocommerce` / `wa-content-shell` / `wa-single-product` gesetzt -- Reihenfolge im DOM nicht garantiert, fuer CSS irrelevant.
- **Phorest Newsletter Payload** sendet keine `language` Property an Phorest. Wenn der Customer das braucht, ist eine separate Erweiterung in `includes/phorest/newsletter.php::wa_phorest_newsletter_subscribe()` notwendig.

---

## 5. Verifikation nach Setup

1. WP-Admin > WPML > String Translation > Plugin-Strings durchscrollen, EN-Werte pflegen.
2. Frontend: `https://drperi.test/en/` aufrufen.
3. DevTools > `<body>` Klassen pruefen -> `wa-lang-en` muss enthalten sein.
4. Footer-Address auf EN: zeigt EN-ACF-Translation, falls gepflegt.
5. Single-Product EN-Variant: Trust-Items, Facts, Keypoints, FAQ in EN.
6. JSON-LD im Single-Product Source-Code: enthaelt `"inLanguage":"en"`.
7. Newsletter-Form auf EN: Submit -> EN-Erfolgsmeldung erscheint.
8. Shop-Page EN: Filter-Pills funktionieren, Featured-Category-Block rendert.
9. Phorest-Cron triggern -> EN-Produkt-Title bleibt unveraendert, DE wird ggf. geupdated.
