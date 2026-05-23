<?php
/* ============================================================
   DATEI: admin/admin-docu.php
   ZWECK: In-Admin-Dokumentation der gesamten werbeauf-customs
          Funktionen. Sticky-Tab-Layout mit 7 Reitern: Uebersicht,
          Shortcodes, Produkt-Inhalte (ACF), Single Product Aufbau,
          Phorest, Newsletter, Technische Referenz.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Liefert Plugin-Meta (Name + Version) aus dem Datei-Header von
 * werbeauf-customs.php. Fallback-Werte falls die Datei umzieht.
 */
function wa_docs_get_plugin_meta() {
    $defaults = array( 'Name' => 'Werbeauf Customs', 'Version' => '—' );
    $file     = WERBEAUF_PLUGIN_PATH . 'werbeauf-customs.php';
    if ( ! file_exists( $file ) ) {
        return $defaults;
    }
    $data = get_file_data( $file, array( 'Name' => 'Plugin Name', 'Version' => 'Version' ) );
    return wp_parse_args( array_filter( $data ), $defaults );
}

function wa_render_docs_content() {
    $meta = wa_docs_get_plugin_meta();

    $tabs = array(
        'overview'  => array( 'label' => 'Übersicht',          'desc' => 'Plugin-Info, Verzeichnis, Body-Klassen' ),
        'shortcodes'=> array( 'label' => 'Shortcodes',         'desc' => 'Alle 7 Shortcodes mit Attributen' ),
        'content'   => array( 'label' => 'Produkt-Inhalte',    'desc' => 'ACF-Felder Facts, Keypoints, FAQ' ),
        'single'    => array( 'label' => 'Single Product',     'desc' => 'Render-Skelett & Hooks' ),
        'phorest'   => array( 'label' => 'Phorest',            'desc' => 'Sync, Stock, Orders, Cron' ),
        'newsletter'=> array( 'label' => 'Newsletter',         'desc' => 'Form, REST, Phorest-Subscribe' ),
        'reference' => array( 'label' => 'Tech. Referenz',     'desc' => 'AJAX, Optionen, DB, Meta-Keys' ),
    );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html( $meta['Name'] ); ?> — Anleitung</h1>
        <span class="wa-docs-version-pill">v<?php echo esc_html( $meta['Version'] ); ?></span>
        <hr class="wp-header-end">

        <style>
            /* Alles unter .wa-docs gescoped, damit kein WP-Admin-CSS-Bleed entsteht. */
            .wa-docs-version-pill{display:inline-block;margin-left:10px;padding:2px 10px;background:#f0f0f1;color:#646970;border:1px solid #c3c4c7;border-radius:999px;font-size:12px;font-weight:600;letter-spacing:.05em;vertical-align:middle}
            .wa-docs{background:#fff;border:1px solid #c3c4c7;border-radius:6px;max-width:1100px;margin:20px 0 40px;box-shadow:0 1px 1px rgba(0,0,0,.04);overflow:hidden}
            .wa-docs__head{padding:18px 24px;border-bottom:1px solid #eaecf0;background:#fcfcfc}
            .wa-docs__head h2{margin:0;font-size:18px;font-weight:600;color:#1d2327}
            .wa-docs__head p{margin:4px 0 0;color:#646970;font-size:13px}

            .wa-docs__tabs{display:flex;gap:0;background:#fff;padding:0 12px;border-bottom:1px solid #c3c4c7;overflow-x:auto;scrollbar-width:none;position:sticky;top:32px;z-index:5}
            .wa-docs__tabs::-webkit-scrollbar{display:none}
            .wa-docs__tab{appearance:none;background:transparent;border:0;border-bottom:2px solid transparent;color:#646970;padding:14px 16px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;letter-spacing:.02em;transition:color 150ms ease,border-color 150ms ease;font-family:inherit}
            .wa-docs__tab:hover{color:#1d2327}
            .wa-docs__tab.is-active{color:#2271b1;border-bottom-color:#2271b1}
            .wa-docs__tab:focus-visible{outline:2px solid #2271b1;outline-offset:-2px;border-radius:3px}

            .wa-docs__panel{padding:24px}
            .wa-docs__panel[hidden]{display:none}

            .wa-docs h3.wa-docs__h3{margin:0 0 6px;font-size:18px;font-weight:600;color:#1d2327}
            .wa-docs__lead{margin:0 0 22px;color:#646970;font-size:13px;line-height:1.6;max-width:80ch}
            .wa-docs__section{margin:32px 0 0}
            .wa-docs__section:first-of-type{margin-top:0}
            .wa-docs__section-title{margin:0 0 14px;text-transform:uppercase;font-size:11px;color:#646970;border-bottom:2px solid #eaecf0;padding-bottom:6px;font-weight:700;letter-spacing:.08em}

            .wa-docs__card{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #72aee6;padding:18px 20px;margin:0 0 16px;border-radius:4px}
            .wa-docs__card--accent{border-left-color:#eac5b9}
            .wa-docs__card--single{border-left-color:#8c97a3}
            .wa-docs__card--success{border-left-color:#00a32a}
            .wa-docs__card--warning{border-left-color:#dba617}
            .wa-docs__card--danger{border-left-color:#d63638}
            .wa-docs__card p{margin:0 0 10px;color:#1d2327;font-size:13px;line-height:1.55}
            .wa-docs__card p:last-child{margin-bottom:0}

            .wa-docs__code{display:inline-block;background:#f0f0f1;color:#1d2327;padding:4px 8px;border:1px solid #ccc;border-radius:3px;font-family:Consolas,Monaco,monospace;font-weight:600;font-size:13px;margin-bottom:10px}
            .wa-docs__inline{background:rgba(0,0,0,.05);padding:1px 5px;border-radius:3px;font-family:Consolas,Monaco,monospace;font-size:12px;color:#1d2327}
            .wa-docs__badge{display:inline-block;background:#dff0d8;color:#3c763d;padding:2px 7px;font-size:10px;border-radius:3px;font-weight:700;margin-left:6px;text-transform:uppercase;letter-spacing:.05em;vertical-align:middle}
            .wa-docs__badge--gray{background:#f0f0f1;color:#646970}
            .wa-docs__badge--blue{background:#e7f1f9;color:#2271b1}
            .wa-docs__badge--orange{background:#fcefd6;color:#996800}

            .wa-docs__table{width:100%;border-collapse:collapse;margin:14px 0 4px;font-size:12.5px;border:1px solid #c3c4c7;background:#fff}
            .wa-docs__table th{background:#f6f7f7;text-align:left;padding:9px 10px;border-bottom:1px solid #c3c4c7;font-weight:600;font-size:12px;color:#1d2327}
            .wa-docs__table td{padding:9px 10px;border-bottom:1px solid #f0f0f1;vertical-align:top;color:#1d2327}
            .wa-docs__table tr:last-child td{border-bottom:0}
            .wa-docs__table td code,.wa-docs__table th code{background:rgba(0,0,0,.05);padding:1px 5px;border-radius:3px;font-size:12px}
            .wa-docs__table--accent tr:nth-child(odd) td{background:#fcfcfc}

            .wa-docs__examples{background:#fcfcfc;border:1px solid #eee;padding:12px 14px;margin-top:12px;font-size:12.5px;border-radius:4px;line-height:1.7}
            .wa-docs__examples code{display:inline-block;background:#fff;border:1px solid #e5e5e5;border-radius:3px;padding:2px 7px;font-size:12px;color:#1d2327;margin:2px 0}
            .wa-docs__examples-label{display:block;color:#646970;font-size:11px;margin:8px 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600}

            .wa-docs__pre{display:block;background:#1d2327;color:#f0f0f1;padding:14px 16px;border-radius:4px;overflow-x:auto;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.6;white-space:pre;margin:10px 0}
            .wa-docs__tree{display:block;background:#fcfcfc;border:1px solid #eaecf0;color:#1d2327;padding:14px 16px;border-radius:4px;overflow-x:auto;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.7;white-space:pre;margin:10px 0}

            .wa-docs__cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin:14px 0}
            .wa-docs__cols .wa-docs__card{margin:0}

            .wa-docs__list{margin:6px 0 0 20px;color:#1d2327;font-size:13px;line-height:1.7}
            .wa-docs__list li{margin:0 0 4px}

            .wa-docs__notice{background:#fcf8e3;border:1px solid #f0e2bd;color:#7a6411;padding:10px 14px;border-radius:4px;font-size:12.5px;margin:10px 0;line-height:1.55}
            .wa-docs__notice--info{background:#e7f1f9;border-color:#bcdaee;color:#1c4d7a}

            .wa-docs__row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:6px}
        </style>

        <div class="wa-docs">
            <header class="wa-docs__head">
                <h2>Werbeauf Customs — In-Admin-Dokumentation</h2>
                <p>Komplette Referenz aller Plugin-Funktionen für Dr. Peri Skincare. Verlinkt aus dem Admin-Menü unter <strong>Dr. Peri → Admin Docu</strong>.</p>
            </header>

            <nav class="wa-docs__tabs" role="tablist" aria-label="Dokumentation">
                <?php $first = true; foreach ( $tabs as $id => $tab ) : ?>
                    <button
                        type="button"
                        class="wa-docs__tab<?php echo $first ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-controls="wa-docs-panel-<?php echo esc_attr( $id ); ?>"
                        aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
                        data-tab="<?php echo esc_attr( $id ); ?>"
                        title="<?php echo esc_attr( $tab['desc'] ); ?>"
                    ><?php echo esc_html( $tab['label'] ); ?></button>
                <?php $first = false; endforeach; ?>
            </nav>

            <?php
            $first = true;
            foreach ( $tabs as $id => $tab ) :
                $hidden = $first ? '' : ' hidden';
                ?>
                <section
                    class="wa-docs__panel"
                    id="wa-docs-panel-<?php echo esc_attr( $id ); ?>"
                    role="tabpanel"
                    aria-labelledby="wa-docs-tab-<?php echo esc_attr( $id ); ?>"
                    data-panel="<?php echo esc_attr( $id ); ?>"
                    <?php echo $hidden; ?>
                >
                    <?php call_user_func( 'wa_docs_render_panel_' . str_replace( '-', '_', $id ) ); ?>
                </section>
                <?php
                $first = false;
            endforeach;
            ?>
        </div>

        <script>
        (function () {
            var docs = document.querySelector('.wa-docs');
            if (!docs) return;
            var tabs = docs.querySelectorAll('.wa-docs__tab');
            var panels = docs.querySelectorAll('.wa-docs__panel');

            function activate(id) {
                tabs.forEach(function (t) {
                    var on = t.getAttribute('data-tab') === id;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                panels.forEach(function (p) {
                    var on = p.getAttribute('data-panel') === id;
                    if (on) { p.removeAttribute('hidden'); }
                    else    { p.setAttribute('hidden', ''); }
                });
            }

            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    var id = t.getAttribute('data-tab');
                    activate(id);
                    if (history.replaceState) {
                        history.replaceState(null, '', '#wa-docs-' + id);
                    }
                });
            });

            // Hash-Sync beim Laden + History-Zurueck.
            function fromHash() {
                var m = (location.hash || '').match(/^#wa-docs-([a-z]+)$/i);
                if (m && docs.querySelector('[data-tab="' + m[1] + '"]')) {
                    activate(m[1]);
                }
            }
            window.addEventListener('hashchange', fromHash);
            fromHash();
        })();
        </script>
    </div>
    <?php
}

/* ============================================================
   PANELS — pro Tab eine Funktion. Reihenfolge wie im $tabs-Array.
============================================================ */

function wa_docs_render_panel_overview() {
    ?>
    <h3 class="wa-docs__h3">Übersicht</h3>
    <p class="wa-docs__lead">
        <strong>werbeauf-customs</strong> ist das zentrale Custom-Plugin der Dr. Peri Site.
        Es ergänzt Divi um ein eigenes Layout-Shell, ein Custom Single-Product-Hero,
        Shop-Layout-Shortcodes, einen Flyout-Cart, die Phorest-Salon-Software-Anbindung
        und einen REST-basierten Newsletter-Flow. Alle PHP-Dateien werden vom Auto-Loader
        in <code>werbeauf-customs.php</code> automatisch geladen.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Was das Plugin liefert</div>
        <div class="wa-docs__cols">
            <div class="wa-docs__card">
                <p><strong>Layout-Shell</strong></p>
                <p>Eigene Shells für alle WC-Pages (Shop, Cart, Checkout, Account, Single-Product) sowie für rechtliche Content-Pages (AGB, Datenschutz, Impressum, …).</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Single-Product-Hero</strong></p>
                <p>Eigenes Markup mit Gallery, Summary, Trust-Badges, Detail-Block und Akkordeon-FAQ inkl. <code>FAQPage</code>-JSON-LD.</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Shortcodes</strong></p>
                <p><code>[wa_shop_layout]</code>, <code>[wa_shop_filter]</code>, <code>[wa_category_products]</code>, <code>[wa_newsletter_signup]</code> &amp; mehr.</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Phorest-Sync</strong></p>
                <p>Stündlicher Produkt-Sync, Stock-Sync bei Bestelländerungen, optionaler Order-Sync, Newsletter-Subscribe.</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Custom Header/Footer</strong></p>
                <p>Fallback-Templates, falls der Divi Theme Builder kein eigenes Layout liefert.</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Flyout-Cart</strong></p>
                <p>Drawer-basierter Mini-Cart, der von der Header-Cart-Toolbar getriggert wird.</p>
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Verzeichnisstruktur</div>
        <pre class="wa-docs__tree">werbeauf-customs/
├── werbeauf-customs.php        Bootstrap + Auto-Loader
├── README.md                   Kurz-Doku für Devs
├── docs/
│   ├── DESIGN.md               Tokens, Type-Scale, Komponenten
│   ├── PHOREST.md              Phorest-API-Referenz
│   ├── SHORTCODES.md           Vollständige Shortcode-Doku
│   └── SINGLE-PRODUCT-DESCRIPTION.md
├── admin/
│   ├── admin-menu.php          Submenüs unter "Dr. Peri"
│   ├── admin-docu.php          DIESE Seite
│   ├── phorest-api.php         API-Settings + Verbindungstest
│   ├── phorest-data.php        Phorest-Produktbrowser + Sync
│   ├── phorest-stocks.php      Lager-Log + manuelle Anpassung
│   ├── phorest-newsletter.php  Newsletter-Log
│   └── product-facts-column.php     Admin-Spalte mit Facts-Vorschau
├── includes/
│   ├── core/        enqueue.php · admin-tweaks.php · divi-toggle-fix.php
│   ├── acf/         single-product-fields.php (Local Field Groups)
│   ├── layout/      header-controller.php · footer-controller.php · wc-shell.php
│   ├── woocommerce/ single-product-renderer.php · flyout-cart.php
│   ├── phorest/     woo-sync.php · stock-sync.php · order-sync.php · newsletter.php
│   └── shortcodes/  shop-layout.php · shop-layout-short.php · shop-filter.php
│                    category-products.php · footer-menu.php · newsletter-form.php
├── assets/
│   ├── css/  00-base · 10-layout · 20-components · 30-pages · 40-blocks
│   └── js/   header · flyout · single-product · shop-filter · newsletter · …
└── templates/
    ├── header.php              Custom-Header Markup
    └── footer.php              Custom-Footer Markup</pre>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Auto-Loader</div>
        <div class="wa-docs__card">
            <p>Es gibt <strong>keine</strong> manuelle <code>require_once</code>-Liste. <code>werbeauf-customs.php</code> lädt alle PHP-Dateien aus diesen Ordnern in fester Reihenfolge:</p>
            <ol class="wa-docs__list">
                <li><code>includes/core/</code> &mdash; Enqueue, Admin-Tweaks, Divi-Fixes, Maintenance</li>
                <li><code>includes/acf/</code> &mdash; ACF Local Field Groups (hängt sich auf <code>acf/init</code>)</li>
                <li><code>includes/layout/</code> &mdash; Header-/Footer-Controller, WC-Shell</li>
                <li><code>includes/woocommerce/</code> &mdash; Single-Product-Renderer, Flyout-Cart</li>
                <li><code>includes/phorest/</code> &mdash; Sync-Engines</li>
                <li><code>includes/shortcodes/</code> &mdash; alle Shortcodes</li>
                <li><code>admin/</code> &mdash; Admin-Pages</li>
            </ol>
            <p>Eine neue Datei in einem dieser Ordner wird beim nächsten Request automatisch eingebunden &mdash; einfach in den richtigen Layer-Ordner ablegen.</p>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Body-Klassen</div>
        <p class="wa-docs__lead">Drei Page-Scopes plus zwei Layout-Klassen markieren, welcher CSS-Layer aktiv ist:</p>
        <table class="wa-docs__table wa-docs__table--accent">
            <thead><tr><th>Klasse</th><th>Wann gesetzt?</th><th>Was bringt sie?</th></tr></thead>
            <tbody>
                <tr><td><code>wa-woocommerce</code></td><td>Alle nativen WC-Pages + Seiten mit <code>[woocommerce_cart]</code>, <code>[woocommerce_checkout]</code>, <code>[woocommerce_my_account]</code></td><td>Layout-Shell + WC-Komponenten-CSS</td></tr>
                <tr><td><code>wa-content-shell</code></td><td>Slugs aus <code>wa_content_shell_slugs()</code> (AGB, Datenschutz, Impressum, Widerruf, Versand, Zahlung)</td><td>Container ohne WC-Tokens</td></tr>
                <tr><td><code>wa-single-product</code></td><td>Single-Product-Pages</td><td>Eigene Tokens für den Hero, überschreibt <code>wa-woocommerce</code></td></tr>
                <tr><td><code>werbeauf-header-active</code></td><td>Wenn Divi Theme Builder kein Header-Layout setzt</td><td>Aktiviert das Fallback-Header-Template</td></tr>
                <tr><td><code>werbeauf-footer-active</code></td><td>Wenn Divi Theme Builder kein Footer-Layout setzt</td><td>Aktiviert das Fallback-Footer-Template</td></tr>
            </tbody>
        </table>
        <div class="wa-docs__notice wa-docs__notice--info">Filter <code>wa_content_shell_slugs</code> erweitert die Liste der Content-Shell-Seiten ohne Code-Änderung.</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">CSS-Layering</div>
        <table class="wa-docs__table">
            <thead><tr><th>Layer</th><th>Verantwortung</th><th>Beispiel</th></tr></thead>
            <tbody>
                <tr><td><code>00-base</code></td><td>Tokens (CSS-Variablen) + globale Typo</td><td><code>tokens.css</code>, <code>style.css</code></td></tr>
                <tr><td><code>10-layout</code></td><td>Page-Shells (1400px-Container, Body-Class-Scoping)</td><td><code>wc-shell.css</code>, <code>header.css</code>, <code>footer.css</code></td></tr>
                <tr><td><code>20-components</code></td><td>Wiederverwendbare UI-Komponenten</td><td><code>product-card.css</code>, <code>flyout-cart.css</code></td></tr>
                <tr><td><code>30-pages</code></td><td>Page-Type-spezifischer Code</td><td><code>shop-archive.css</code>, <code>single-product.css</code></td></tr>
                <tr><td><code>40-blocks</code></td><td>Shortcode-/Composition-Blöcke</td><td><code>shop-layout.css</code>, <code>accordion.css</code></td></tr>
            </tbody>
        </table>
        <div class="wa-docs__notice wa-docs__notice--info"><strong>Goldene Regel:</strong> Layout einer einzelnen Page anpassen &rarr; zuerst in <code>30-pages/</code>. Erst danach <code>10-layout/wc-shell.css</code> für Container-Verhalten.</div>
    </div>
    <?php
}

function wa_docs_render_panel_shortcodes() {
    ?>
    <h3 class="wa-docs__h3">Shortcodes</h3>
    <p class="wa-docs__lead">
        Sieben aktive Shortcodes. Alle nehmen Attribute via <code>shortcode_atts</code>; nicht aufgeführte Attribute werden ignoriert.
        <code>[wa_shop_layout]</code>, <code>[drperi_shop_layout]</code> (Backcompat-Alias) und <code>[drperi_shop_layout_short]</code> sind drperi-spezifisch.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">A. Shop-Archive Layouts</div>

        <div class="wa-docs__card wa-docs__card--accent">
            <span class="wa-docs__code">[wa_shop_layout]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Komplettes Shop-Archive: Intro (H1 + Lead), Featured-Sets-Hero, Hauptkatalog mit Filter-Pillbar und optionaler Kategorien-Block am Ende. Backcompat-Alias: <code>[drperi_shop_layout]</code>.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Attribut</th><th>Default</th><th>Beschreibung</th></tr></thead>
                <tbody>
                    <tr><td><code>intro_title</code></td><td>Shop D. Peri Skincare</td><td>H1 über dem Shop</td></tr>
                    <tr><td><code>intro_text</code></td><td>(lange DE-Version)</td><td>Lead-Absatz unter der H1</td></tr>
                    <tr><td><code>show_featured</code></td><td>yes</td><td>Featured-Hero anzeigen?</td></tr>
                    <tr><td><code>featured_title</code></td><td>Unsere Top-Sets</td><td>H3 über Featured-Hero</td></tr>
                    <tr><td><code>featured_text</code></td><td>(Default-Text)</td><td>Subtitel unter dem H3</td></tr>
                    <tr><td><code>featured_category</code></td><td>sets</td><td>Slug der Featured-Kategorie. Wird gleichzeitig im Hauptkatalog ausgeschlossen. Leerstring deaktiviert beides.</td></tr>
                    <tr><td><code>featured_limit</code></td><td>3</td><td>Anzahl Produkte im Featured-Hero</td></tr>
                    <tr><td><code>featured_columns</code></td><td>3</td><td>Spalten im Featured-Hero</td></tr>
                    <tr><td><code>all_title</code></td><td>Alle Produkte</td><td>H3 über dem Hauptkatalog</td></tr>
                    <tr><td><code>all_limit</code></td><td>12</td><td>Anzahl Produkte im Hauptkatalog</td></tr>
                    <tr><td><code>all_columns</code></td><td>4</td><td>Spalten im Hauptkatalog</td></tr>
                    <tr><td><code>show_categories</code></td><td>yes</td><td>Kategorien-Block am Ende anzeigen?</td></tr>
                    <tr><td><code>categories_title</code></td><td>Kategorien entdecken</td><td>H3 über Kategorien-Block</td></tr>
                    <tr><td><code>categories_columns</code></td><td>5</td><td>Spalten im Kategorien-Block</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Beispiele</span>
                <code>[wa_shop_layout]</code><br>
                <code>[wa_shop_layout featured_category="sets" all_limit="16"]</code><br>
                <code>[wa_shop_layout show_featured="no" show_categories="no"]</code><br>
                <code>[wa_shop_layout intro_title="Pflege &amp; Beauty" featured_category="aktionen"]</code>
            </div>
        </div>

        <div class="wa-docs__card wa-docs__card--accent">
            <span class="wa-docs__code">[drperi_shop_layout_short]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Reduzierte Variante von <code>[wa_shop_layout]</code>: <strong>nur</strong> Featured-Sets-Hero + Hauptkatalog mit Filter. Kein Intro, keine Kategorien.</p>
            <p>Attribute identisch zu <code>[wa_shop_layout]</code>, ohne <code>intro_*</code>, <code>show_categories</code>, <code>categories_*</code>.</p>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Beispiele</span>
                <code>[drperi_shop_layout_short]</code><br>
                <code>[drperi_shop_layout_short featured_category="sets" all_limit="16"]</code><br>
                <code>[drperi_shop_layout_short show_featured="no"]</code>
            </div>
        </div>

        <div class="wa-docs__card">
            <span class="wa-docs__code">[wa_shop_filter]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Pill-Bar mit den WC-Produktkategorien. Filtert die <strong>direkt darauf folgende</strong> <code>ul.products</code> clientseitig per JavaScript anhand der <code>product_cat-{slug}</code>-Klassen, die WC automatisch an jedes Produkt-LI hängt.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Attribut</th><th>Default</th><th>Beschreibung</th></tr></thead>
                <tbody>
                    <tr><td><code>exclude</code></td><td>—</td><td>Komma-Slugs, die <strong>nicht</strong> als Pill erscheinen</td></tr>
                    <tr><td><code>include</code></td><td>—</td><td>Komma-Slugs, wenn gesetzt nur diese als Pills</td></tr>
                    <tr><td><code>parent</code></td><td>—</td><td>Slug einer Eltern-Kategorie &rarr; nur direkte Kinder</td></tr>
                    <tr><td><code>hide_empty</code></td><td>yes</td><td>Leere Kategorien ausblenden?</td></tr>
                    <tr><td><code>all_label</code></td><td>Alle</td><td>Beschriftung des "Alle"-Pills</td></tr>
                    <tr><td><code>target</code></td><td>—</td><td>Optionaler CSS-Selektor der zu filternden <code>ul.products</code></td></tr>
                </tbody>
            </table>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Beispiel</span>
                <code>[wa_shop_filter exclude="sets"]</code><br>
                <code>[products limit="12" columns="4" cat_operator="NOT IN" category="sets"]</code>
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">B. Kategorie-Archiv</div>

        <div class="wa-docs__card">
            <span class="wa-docs__code">[wa_category_products]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Render-Reihenfolge: H1 Kategorie-Name &rarr; Term-Description &rarr; <code>[products]</code>-Grid &rarr; "Andere Kategorien entdecken". Erkennt die aktive Kategorie automatisch via <code>is_product_category()</code> &rarr; URL-Parameter <code>?product_cat=</code> &rarr; Attribut <code>category</code> oder <code>fallback</code>.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Attribut</th><th>Default</th><th>Beschreibung</th></tr></thead>
                <tbody>
                    <tr><td><code>category</code></td><td>(auto)</td><td>Slug überschreibt Auto-Erkennung</td></tr>
                    <tr><td><code>fallback</code></td><td>—</td><td>Slug wenn nichts erkannt</td></tr>
                    <tr><td><code>limit</code></td><td>12</td><td>Produkte pro Seite</td></tr>
                    <tr><td><code>columns</code></td><td>4</td><td>Spaltenanzahl</td></tr>
                    <tr><td><code>orderby</code></td><td>menu_order</td><td>menu_order, title, date, price, popularity, rating, rand</td></tr>
                    <tr><td><code>order</code></td><td>ASC</td><td>ASC oder DESC</td></tr>
                    <tr><td><code>paginate</code></td><td>yes</td><td>Pagination aktivieren?</td></tr>
                    <tr><td><code>on_sale</code></td><td>—</td><td>yes &rarr; nur Sale-Produkte</td></tr>
                    <tr><td><code>best_selling</code></td><td>—</td><td>yes &rarr; nach Bestseller sortieren</td></tr>
                    <tr><td><code>show_title</code></td><td>yes</td><td>H1 ausgeben</td></tr>
                    <tr><td><code>show_description</code></td><td>yes</td><td>Term-Description ausgeben</td></tr>
                    <tr><td><code>show_browse_other</code></td><td>yes</td><td>"Andere Kategorien"-Block am Ende</td></tr>
                    <tr><td><code>browse_other_title</code></td><td>Andere Kategorien entdecken</td><td>H3-Text</td></tr>
                    <tr><td><code>browse_columns</code></td><td>5</td><td>Spalten im Browse-Block</td></tr>
                    <tr><td><code>browse_parent</code></td><td>0</td><td>Parent-Slug/-ID; <code>0</code> = Top-Level</td></tr>
                    <tr><td><code>browse_hide_empty</code></td><td>yes</td><td>Leere Kategorien ausblenden?</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Beispiele</span>
                <code>[wa_category_products]</code><br>
                <code>[wa_category_products limit="16" columns="4"]</code><br>
                <code>[wa_category_products show_title="no" show_description="no"]</code><br>
                <code>[wa_category_products fallback="alle" show_browse_other="no"]</code>
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">C. Newsletter</div>

        <div class="wa-docs__card wa-docs__card--success">
            <span class="wa-docs__code">[wa_newsletter_signup]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Eigenständige Anmelde-Form. Submit per <code>POST /wp-json/wa/v1/newsletter</code> &rarr; Phorest <em>Client Search/Create/Update</em>. Honeypot + Rate-Limit (siehe Tab <em>Newsletter</em>).</p>
            <table class="wa-docs__table">
                <thead><tr><th>Attribut</th><th>Default</th><th>Beschreibung</th></tr></thead>
                <tbody>
                    <tr><td><code>variant</code></td><td>regular</td><td><code>regular</code> (Card mit Titel + Lead) oder <code>footer</code> (kompakt für Footer-Slots)</td></tr>
                    <tr><td><code>title</code></td><td>Newsletter</td><td>H3 über der Form (regular)</td></tr>
                    <tr><td><code>lead</code></td><td>(Default-Text)</td><td>Lead-Absatz (regular)</td></tr>
                    <tr><td><code>button</code></td><td>Anmelden</td><td>Submit-Button-Text</td></tr>
                    <tr><td><code>privacy_url</code></td><td>/datenschutz/</td><td>Link zur Datenschutzseite im Consent-Label</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Beispiele</span>
                <code>[wa_newsletter_signup]</code><br>
                <code>[wa_newsletter_signup variant="footer"]</code><br>
                <code>[wa_newsletter_signup title="Stay in the Loop" button="Abonnieren"]</code>
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">D. Helper</div>

        <div class="wa-docs__card wa-docs__card--single">
            <span class="wa-docs__code">[wa_footer_menu]</span>
            <span class="wa-docs__badge">Werbeauf</span>
            <p>Rendert das WP-Menü der Theme-Location <code>footer-menu</code> als <code>&lt;nav class="legal-menu"&gt;&lt;ul class="wa-footer-ul"&gt;…</code>. Wird im Custom-Footer-Template eingesetzt. Keine Attribute.</p>
        </div>
    </div>

    <div class="wa-docs__notice">
        <strong>Native WC-Shortcodes</strong> (<code>[products]</code>, <code>[product_categories]</code>, <code>[product_category]</code>, …) werden weiterhin verwendet &mdash; teils intern aus den Werbeauf-Shortcodes heraus. Eigene Klassen-Labels und sortierungs-Tweaks aus älteren Plugin-Versionen wurden in v2.0 in das Template-Layer verschoben.
    </div>
    <?php
}

function wa_docs_render_panel_content() {
    ?>
    <h3 class="wa-docs__h3">Produkt-Inhalte (ACF)</h3>
    <p class="wa-docs__lead">
        Drei ACF-Feldgruppen pflegen den Inhalt im Single-Product-Hero und am Seitenende.
        Sie sind <strong>per PHP</strong> registriert (<code>includes/acf/single-product-fields.php</code>) &mdash; kein UI-Setup nötig.
        Alle Gruppen sind an <code>post_type == product</code> gebunden.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">A. Produkt-Facts (unter Bild)</div>
        <div class="wa-docs__card wa-docs__card--success">
            <p>1&ndash;3 kurze Pluspunkte mit Icon &mdash; rendern direkt unter dem Produktbild als <code>ul.wa-product-facts</code>.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Sub-Field</th><th>Typ</th><th>Limit</th><th>Verwendung</th></tr></thead>
                <tbody>
                    <tr><td><code>icon</code></td><td>Select</td><td>—</td><td>Eines von 8 SVG-Icons (siehe unten)</td></tr>
                    <tr><td><code>text</code></td><td>Text</td><td>60 Zeichen</td><td>Kurzer Pluspunkt, z. B. "Vegan"</td></tr>
                </tbody>
            </table>
            <p style="margin-top:14px"><strong>Verfügbare Icons:</strong></p>
            <table class="wa-docs__table">
                <thead><tr><th>Key</th><th>Bedeutung</th></tr></thead>
                <tbody>
                    <tr><td><code>check</code></td><td>Allround</td></tr>
                    <tr><td><code>leaf</code></td><td>Vegan / Naturkosmetik</td></tr>
                    <tr><td><code>shield</code></td><td>Hautverträglich / Schutz</td></tr>
                    <tr><td><code>sparkles</code></td><td>Premium / Glow</td></tr>
                    <tr><td><code>droplet</code></td><td>Feuchtigkeit / Hydration</td></tr>
                    <tr><td><code>flask</code></td><td>Wirkstoff / Lab</td></tr>
                    <tr><td><code>heart</code></td><td>Tierversuchsfrei / Care</td></tr>
                    <tr><td><code>truck</code></td><td>Versand</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__notice wa-docs__notice--info">Repeater hat <code>max=3</code> &mdash; weitere Einträge werden hart abgeschnitten. Frontend-Klasse: <code>wa-product-facts--count-{1|2|3}</code> (steuert das Grid via Container-Query).</div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">B. Produkt-Keypoints (Spec-Liste)</div>
        <div class="wa-docs__card wa-docs__card--success">
            <p>Bis zu 5 Label/Value-Zeilen direkt unter der Kurzbeschreibung. Erscheinen als <code>dl.wa-product-keypoints</code>.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Sub-Field</th><th>Typ</th><th>Limit</th><th>Verwendung</th></tr></thead>
                <tbody>
                    <tr><td><code>label</code></td><td>Text</td><td>40 Zeichen</td><td>z. B. "Verwendungszweck"</td></tr>
                    <tr><td><code>value</code></td><td>Textarea (br)</td><td>—</td><td>z. B. "Milder alkoholfreier Reinigungsschaum für ölige &amp; Mischhaut"</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__examples">
                <span class="wa-docs__examples-label">Empfohlene Labels</span>
                Verwendungszweck · Key Ingredients · Anwendung · Hauttyp · Inhaltsmenge
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">C. Produkt-FAQ (Seitenende + Schema.org)</div>
        <div class="wa-docs__card wa-docs__card--success">
            <p>Frage/Antwort-Block am Seitenende. Wird zusätzlich als <code>FAQPage</code>-JSON-LD im <code>&lt;head&gt;</code> ausgegeben &mdash; Voraussetzung für Google Rich-Results.</p>
            <table class="wa-docs__table">
                <thead><tr><th>Feld</th><th>Typ</th><th>Limit</th><th>Verwendung</th></tr></thead>
                <tbody>
                    <tr><td><code>faq_headline</code></td><td>Text</td><td>—</td><td>H2 über der Liste, Default "Häufige Fragen"</td></tr>
                    <tr><td><code>faq_description</code></td><td>Textarea (br)</td><td>—</td><td>Optionaler Intro-Text</td></tr>
                    <tr><td><code>faq_items.question</code></td><td>Text</td><td>10 Zeilen max.</td><td>Einzelne Frage</td></tr>
                    <tr><td><code>faq_items.answer</code></td><td>Textarea (br)</td><td>—</td><td>Plain Text — HTML wird im JSON-LD entfernt</td></tr>
                </tbody>
            </table>
            <div class="wa-docs__notice"><strong>Wichtig:</strong> Mindestens <em>eine</em> vollständige Frage/Antwort-Kombination ist nötig, damit der Block (und das Schema) gerendert wird. Antworten sollten Plain-Text sein &mdash; alle Tags werden für das Strukturdaten-Schema entfernt.</div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Massen-Vorbefüllung</div>
        <div class="wa-docs__card wa-docs__card--warning">
            <p>Unter <strong>Dr. Peri &rarr; Produkt-Inhalte</strong> findest du eine Batch-Operation, die für alle veröffentlichten Produkte fehlende ACF-Felder mit Platzhaltern befüllt:</p>
            <ul class="wa-docs__list">
                <li><strong>facts</strong> &mdash; 3 Standard-Pluspunkte (Vegan, Tierversuchsfrei, Made in Austria)</li>
                <li><strong>keypoints</strong> &mdash; 5 Label/Value-Stubs zum Editieren</li>
                <li><strong>faq_items</strong> &mdash; 4 Demo-Fragen</li>
                <li><strong>faq_headline</strong> &mdash; "Häufige Fragen" wenn leer</li>
            </ul>
            <p>Sicherheits-Garantien: Die Operation überschreibt <strong>keine</strong> Felder, die bereits Inhalt haben.</p>
            <p>Die Admin-Spalte <em>Facts</em> in der Produktliste (<code>admin/product-facts-column.php</code>) zeigt eine Vorschau mit Icons, sodass auf einen Blick erkennbar ist, welche Produkte schon redaktionell gepflegt sind.</p>
        </div>
    </div>
    <?php
}

function wa_docs_render_panel_single() {
    ?>
    <h3 class="wa-docs__h3">Single Product Aufbau</h3>
    <p class="wa-docs__lead">
        Die Klasse <code>Werbeauf_Single_Product_Renderer</code> in
        <code>includes/woocommerce/single-product-renderer.php</code>
        baut das gesamte Single-Product-Markup neu. Sie hängt sich auf <code>wp</code> ein,
        sobald <code>is_product()</code> wahr ist, deaktiviert die WC-Defaults
        (<code>before_main_content</code>, <code>before_single_product_summary</code>,
        <code>output_product_data_tabs</code>, <code>output_related_products</code>) und
        rendert ein eigenes Skelett.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">HTML-Hierarchie</div>
        <pre class="wa-docs__tree">body.wa-single-product
└── div.wa-product-page                           # Page-Container (1400 px max)
    ├── nav.woocommerce-breadcrumb                # Breadcrumb
    ├── section.wa-product-hero
    │   └── div.wa-product-hero__inner
    │       ├── div.wa-product-hero__gallery      # WC-Gallery + Sale-Flash + Facts
    │       │   ├── div.woocommerce-product-gallery
    │       │   └── ul.wa-product-facts.wa-product-facts--count-{1|2|3}
    │       │       └── li.wa-product-facts__item (icon + text)
    │       └── div.summary.entry-summary
    │           ├── p.wa-product-eyebrow          # Kategorie-Eyebrow
    │           ├── h1.product_title
    │           ├── div.woocommerce-product-rating
    │           ├── div.short-description
    │           ├── dl.wa-product-keypoints       # Label/Value-Liste
    │           ├── ul.wa-product-features        # Features Pills
    │           ├── hr.wa-divider
    │           ├── span.price
    │           └── div.wa-product-action         # CTA-Zone (gefüllt + abgesetzt)
    │               ├── form.cart                 # Quantity + Add to Cart
    │               └── ul.wa-product-trust       # Trust-Badges
    │
    ├── section.wa-detail-block                   # Sticky-Tab-Card (Default)
    │   │  oder section.wa-single-panel           # Fallback bei nur 1 Tab
    │   ├── h2.wa-detail-block__title
    │   ├── header.wa-detail-block__header (sticky)
    │   │   └── nav.wa-detail-block__nav
    │   │       ├── button.wa-detail-block__pill[data-pane="description"].is-active
    │   │       └── button.wa-detail-block__pill[data-pane="additional_information"]
    │   └── div.wa-detail-block__panels
    │       ├── div.wa-detail-block__panel#wa-panel-description
    │       └── div.wa-detail-block__panel#wa-panel-additional_information [hidden]
    │
    ├── section.wa-product-faq                    # FAQ-Block (wenn ACF-Items vorhanden)
    │   ├── h2.wa-section__heading
    │   ├── p.wa-product-faq__description
    │   └── details.wa-accordion__item × N
    │       ├── summary.wa-accordion__head
    │       │   ├── span.wa-accordion__title
    │       │   └── svg.wa-accordion__icon
    │       └── div.wa-accordion__body
    │
    ├── section.wa-product-reviews                # Reviews
    │   └── #reviews
    │
    └── section.related.products.woocommerce.wa-related   # Related Products</pre>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Wichtige Hooks</div>
        <table class="wa-docs__table">
            <thead><tr><th>Hook</th><th>Was passiert</th></tr></thead>
            <tbody>
                <tr><td><code>wp</code> (auf <code>is_product()</code>)</td><td>Renderer-Initialisierung, WC-Defaults aushängen</td></tr>
                <tr><td><code>body_class</code></td><td>Fügt <code>wa-single-product</code> hinzu (überschreibt <code>wa-woocommerce</code>-Tokens)</td></tr>
                <tr><td><code>wp_head</code> (50)</td><td>Gibt <code>FAQPage</code>-JSON-LD aus, wenn ACF-FAQ-Items vorhanden</td></tr>
                <tr><td><code>woocommerce_breadcrumb</code></td><td>Wird vor dem Hero gerendert (statt am Seitenanfang)</td></tr>
                <tr><td><code>woocommerce_sale_flash</code></td><td>Custom <code>.wa-sale-flash</code>-Markup, WC-Default versteckt</td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Detail-Block vs. Single-Panel</div>
        <div class="wa-docs__cols">
            <div class="wa-docs__card">
                <p><strong>Detail-Block</strong> &mdash; rendert wenn beide WC-Tabs <code>description</code> + <code>additional_information</code> Inhalt haben.</p>
                <p>Sticky-Header pinnt unter dem Site-Header (<code>top: var(--wa-header-h)</code>). Pill-Nav scrollt auf Mobile horizontal.</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>Single-Panel</strong> &mdash; Fallback wenn nur <em>einer</em> der Tabs Inhalt hat.</p>
                <p>Reine Card ohne Pill-Nav, identische Typografie. Beide Container sind in <code>40-blocks/detail-block.css</code> bzw. <code>30-pages/single-product.css</code> definiert.</p>
            </div>
        </div>
        <div class="wa-docs__notice wa-docs__notice--info">Komplette CSS-Klassen-Referenz: <code>docs/SINGLE-PRODUCT-DESCRIPTION.md</code>.</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Trust-Badges (global)</div>
        <div class="wa-docs__card">
            <p>Die Trust-Badges in der Action-Zone werden <strong>nicht</strong> pro Produkt gepflegt, sondern aus der globalen ACF-Options-Page <em>Single Product</em>:</p>
            <pre class="wa-docs__pre">get_field( 'single_product', 'option' )[ 'trust_items' ]</pre>
            <p>Frontend: <code>ul.wa-product-trust.wa-product-trust--count-{1-4}</code> (Container-Query-Grid). Maximal 4 Items pro Produkt.</p>
        </div>
    </div>
    <?php
}

function wa_docs_render_panel_phorest() {
    ?>
    <h3 class="wa-docs__h3">Phorest-Integration</h3>
    <p class="wa-docs__lead">
        Bidirektionale Verbindung zur Phorest Salon-Software. Produkte werden <strong>von Phorest nach WooCommerce</strong>
        synchronisiert (stündlich + manuell), Lageränderungen gehen <strong>von WooCommerce zurück nach Phorest</strong> (bei Bestellungsereignissen).
        Bestellungen selbst werden <em>nicht</em> automatisch synchronisiert &mdash; das übernimmt der optionale Order-Sync, falls aktiviert.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Datenfluss</div>
        <pre class="wa-docs__tree">Phorest API
   │
   ├─►  Produktdaten (Name · Preis · SKU · Barcode · Lagerbestand)
   │       └─►  WooCommerce Produkte                  [stündlich + manuell]
   │
   └◄── Lageranpassungen (DEDUCT / INCREASE)
           └◄── WooCommerce Bestellungsereignisse     [automatisch]</pre>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Admin-Pages</div>
        <table class="wa-docs__table">
            <thead><tr><th>Menüpunkt</th><th>Was</th><th>Datei</th></tr></thead>
            <tbody>
                <tr><td>Phorest API</td><td>API-Settings (Business-ID, Branch-ID, Token), Verbindungstest</td><td><code>admin/phorest-api.php</code></td></tr>
                <tr><td>Phorest Produkte</td><td>Produktbrowser, "Sync now"-Button, Cache-Reset</td><td><code>admin/phorest-data.php</code></td></tr>
                <tr><td>Phorest Lager</td><td>Manuelle Anpassung + Verlaufstabelle (Filter, Pagination)</td><td><code>admin/phorest-stocks.php</code></td></tr>
                <tr><td>Newsletter Log</td><td>Newsletter-Subscribe-Log</td><td><code>admin/phorest-newsletter.php</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Produkt-Verknüpfung</div>
        <div class="wa-docs__card">
            <p>Jedes WC-Produkt zeigt in der Sidebar die Box <strong>Phorest API Verknüpfung</strong>. Dort wird über ein Dropdown ein Phorest-Produkt aus dem Cache gewählt. Die Verknüpfung wird in <code>_phorest_product_id</code> gespeichert.</p>
            <p>Nach Speichern erscheint ein <em>Jetzt synchronisieren</em>-Button + Timestamp des letzten Syncs.</p>
            <p>Voraussetzung für den Stock-Sync: Verknüpftes Produkt <strong>und</strong> nicht-leerer Barcode (<code>_wc_gtin</code>, WC-9.2-natives Feld &mdash; wird vom Phorest-Sync gepflegt).</p>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Sync-Auslöser</div>
        <table class="wa-docs__table">
            <thead><tr><th>Trigger</th><th>Was passiert</th></tr></thead>
            <tbody>
                <tr><td><code>woocommerce_process_product_meta</code></td><td>Beim Speichern eines Produkts &rarr; Apply-to-WC</td></tr>
                <tr><td>AJAX <code>wa_phorest_sync_single_product</code></td><td>Manueller Button in der Sidebar</td></tr>
                <tr><td>Action <code>wa_phorest_after_sync</code></td><td>Nach jedem "Sync now" auf der Phorest-Produkte-Seite</td></tr>
                <tr><td>WP Cron <code>wa_phorest_auto_sync</code></td><td>Stündlich automatisch</td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Stock-Sync (WC &rarr; Phorest)</div>
        <table class="wa-docs__table">
            <thead><tr><th>WC-Hook</th><th>Operation</th><th>Bedingung</th></tr></thead>
            <tbody>
                <tr><td><code>woocommerce_order_status_completed</code></td><td>DEDUCT</td><td>Nur wenn <code>_phorest_stock_deducted</code> noch nicht gesetzt</td></tr>
                <tr><td><code>woocommerce_order_status_cancelled</code></td><td>INCREASE</td><td>Nur wenn zuvor abgezogen</td></tr>
                <tr><td><code>woocommerce_order_status_refunded</code></td><td>INCREASE</td><td>Nur wenn zuvor abgezogen</td></tr>
            </tbody>
        </table>
        <div class="wa-docs__notice"><strong>Doppel-DEDUCT-Schutz:</strong> Order-Meta <code>_phorest_stock_deducted</code> (Timestamp) wird beim ersten erfolgreichen Abzug gesetzt &mdash; ein zweiter <code>completed</code>-Trigger ist deshalb harmlos.</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Order-Sync (optional)</div>
        <div class="wa-docs__card">
            <p>Datei: <code>includes/phorest/order-sync.php</code>. Synchronisiert die <strong>Käufe</strong> selbst (nicht nur Lager) bei <code>completed</code>-Status. Speichert pro Order:</p>
            <ul class="wa-docs__list">
                <li><code>_phorest_purchase_synced</code> &mdash; Timestamp</li>
                <li><code>_phorest_purchase_id</code> &mdash; Phorest-Purchase-ID</li>
                <li><code>_phorest_purchase_error</code> &mdash; Fehlermeldung bei Fehler</li>
                <li><code>_phorest_client_id</code> &mdash; Phorest-Client-ID</li>
            </ul>
            <p>Manuelle Wiederholung via Order-Spalte <em>Phorest Sync</em> + Meta-Box (<code>wa_phorest_resync_order</code>).</p>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Manuelle Lager-Anpassung</div>
        <div class="wa-docs__card">
            <p>Auf der Seite <strong>Phorest Lager</strong> oben: Produkt-Dropdown (nur verknüpfte mit Barcode), Menge, DEDUCT/INCREASE. Wird sofort an Phorest gesendet und mit <code>order_id = 0</code> ins Log geschrieben.</p>
            <p>Die Verlaufstabelle hat Filter (Freitext, Operation, Status) und 50 Einträge pro Seite.</p>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Technische Quelle</div>
        <div class="wa-docs__notice wa-docs__notice--info">Komplette API-Referenz inkl. Endpunkten, Felder-Mapping und Deployment-Checkliste: <code>docs/PHOREST.md</code>.</div>
    </div>
    <?php
}

function wa_docs_render_panel_newsletter() {
    ?>
    <h3 class="wa-docs__h3">Newsletter</h3>
    <p class="wa-docs__lead">
        Eigener Newsletter-Flow ohne externes ESP. Anmeldungen gehen direkt
        an die Phorest API (<em>Client Search</em> &rarr; <em>Create</em>/<em>Update</em>) und werden
        zusätzlich lokal in einer Ringbuffer-Option geloggt.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Eintrittspunkte</div>
        <div class="wa-docs__cols">
            <div class="wa-docs__card">
                <p><strong>Shortcode</strong></p>
                <p><code>[wa_newsletter_signup]</code> &mdash; in zwei Varianten: <code>regular</code> (Card) und <code>footer</code> (kompakt).</p>
            </div>
            <div class="wa-docs__card">
                <p><strong>WC-Checkout</strong></p>
                <p>Opt-In-Checkbox in der Order-Note (Block-Checkout). Wird beim erfolgreichen Order-Processing automatisch an Phorest übermittelt.</p>
            </div>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">REST-Endpoint</div>
        <pre class="wa-docs__pre">POST  /wp-json/wa/v1/newsletter

Body:           first_name · last_name · email · consent · _wa_nl_nonce · _t · _hp · source
Form-Nonce:     wa_newsletter   (Hidden Field _wa_nl_nonce)
REST-Nonce:     wp_rest          (Header X-WP-Nonce, automatisch via wp_localize_script)
Honeypot:       Field _hp muss leer bleiben (sonst silent reject)
Rate-Limit:     Transient wa_nl_rl_{ip}   (Backoff bei Spam)</pre>
        <div class="wa-docs__notice"><strong>Zwei Nonces nötig:</strong> Der WP-REST-Layer prüft <code>X-WP-Nonce</code> für Cookie-authentifizierte Requests <em>bevor</em> unser Form-Nonce greift &mdash; sonst Fehler "Cookie-Prüfung fehlgeschlagen". Beide Nonces werden automatisch vom Shortcode gesetzt.</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Phorest-Subscribe-Flow</div>
        <ol class="wa-docs__list">
            <li>REST empfängt POST &rarr; Honeypot &amp; Nonce &amp; Rate-Limit checken</li>
            <li>Phorest-Client mit gleicher E-Mail suchen (<code>GET /client/search?email=…</code>)</li>
            <li>Nicht gefunden &rarr; Client neu anlegen mit <code>marketingConsent: true</code></li>
            <li>Gefunden &rarr; <code>marketingConsent</code> updaten falls noch <code>false</code></li>
            <li>Eintrag in Option <code>wa_newsletter_log</code> (Ringbuffer; sha1-Hash der E-Mail)</li>
        </ol>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Newsletter-Log (Admin)</div>
        <div class="wa-docs__card">
            <p>Unter <strong>Dr. Peri &rarr; Newsletter Log</strong>:</p>
            <ul class="wa-docs__list">
                <li>Tabelle der letzten Anmeldungen (Quelle, Status, sha1-E-Mail)</li>
                <li>Button <em>Log leeren</em> (AJAX <code>wa_phorest_newsletter_clear</code>) &mdash; löscht <code>wa_newsletter_log</code></li>
            </ul>
            <p>Quellen: <code>widget</code> (regular Shortcode), <code>footer</code> (footer-Variante), <code>checkout</code> (WC-Checkout-Opt-In).</p>
        </div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">WC-Checkout-Integration</div>
        <pre class="wa-docs__pre">Hook:           woocommerce_store_api_checkout_order_processed
Endpoint-Reg.:  woocommerce_store_api_register_endpoint_data
Namespace:      wa-newsletter
Session-Key:    wa_nl_consent</pre>
        <p>Im Block-Checkout sieht der Kunde eine Opt-In-Checkbox; bei erfolgreichem Order-Processing löst dasselbe <code>wa_phorest_newsletter_subscribe()</code> aus, das auch der Shortcode nutzt.</p>
    </div>
    <?php
}

function wa_docs_render_panel_reference() {
    ?>
    <h3 class="wa-docs__h3">Technische Referenz</h3>
    <p class="wa-docs__lead">
        Vollständige Listen aller registrierten Shortcodes, AJAX-Actions, REST-Routen,
        WP-Options, Meta-Keys, Cron-Jobs und benutzerdefinierten DB-Tabellen.
        Stand: aktuelle Plugin-Version laut Datei-Header.
    </p>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Shortcodes</div>
        <table class="wa-docs__table">
            <thead><tr><th>Tag</th><th>Datei</th></tr></thead>
            <tbody>
                <tr><td><code>[wa_shop_layout]</code></td><td><code>includes/shortcodes/shop-layout.php</code></td></tr>
                <tr><td><code>[drperi_shop_layout]</code> <span class="wa-docs__badge wa-docs__badge--gray">Alias</span></td><td><code>includes/shortcodes/shop-layout.php</code></td></tr>
                <tr><td><code>[drperi_shop_layout_short]</code></td><td><code>includes/shortcodes/shop-layout-short.php</code></td></tr>
                <tr><td><code>[wa_shop_filter]</code></td><td><code>includes/shortcodes/shop-filter.php</code></td></tr>
                <tr><td><code>[wa_category_products]</code></td><td><code>includes/shortcodes/category-products.php</code></td></tr>
                <tr><td><code>[wa_footer_menu]</code></td><td><code>includes/shortcodes/footer-menu.php</code></td></tr>
                <tr><td><code>[wa_newsletter_signup]</code></td><td><code>includes/shortcodes/newsletter-form.php</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">AJAX-Actions</div>
        <table class="wa-docs__table">
            <thead><tr><th>Action</th><th>Nonce-Action</th><th>Datei</th></tr></thead>
            <tbody>
                <tr><td><code>wa_phorest_newsletter_clear</code></td><td><code>wa_phorest_newsletter</code></td><td><code>admin/phorest-newsletter.php</code></td></tr>
                <tr><td><code>wa_phorest_manual_stock</code></td><td><code>wa_phorest_manual_stock</code></td><td><code>admin/phorest-stocks.php</code></td></tr>
                <tr><td><code>wa_phorest_test_connection</code></td><td><code>wa_phorest_test</code></td><td><code>admin/phorest-api.php</code></td></tr>
                <tr><td><code>wa_phorest_sync_products</code></td><td><code>wa_phorest_data</code></td><td><code>admin/phorest-data.php</code></td></tr>
                <tr><td><code>wa_phorest_clear_cache</code></td><td><code>wa_phorest_data</code></td><td><code>admin/phorest-data.php</code></td></tr>
                <tr><td><code>wa_phorest_resync_order</code></td><td><code>wa_phorest_resync_order</code></td><td><code>includes/phorest/order-sync.php</code></td></tr>
                <tr><td><code>wa_phorest_sync_single_product</code></td><td><code>wa_phorest_sync_single</code></td><td><code>includes/phorest/woo-sync.php</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">REST-Routen</div>
        <table class="wa-docs__table">
            <thead><tr><th>Methode &amp; Pfad</th><th>Nonces</th><th>Datei</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>POST /wp-json/wa/v1/newsletter</code></td>
                    <td>Form: <code>wa_newsletter</code><br>REST: <code>wp_rest</code></td>
                    <td><code>includes/phorest/newsletter.php</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">WP-Cron</div>
        <table class="wa-docs__table">
            <thead><tr><th>Hook</th><th>Frequenz</th><th>Was</th><th>Cleanup</th></tr></thead>
            <tbody>
                <tr><td><code>wa_phorest_auto_sync</code></td><td>hourly</td><td>Phorest-Produkte abrufen, Cache erneuern, verknüpfte WC-Produkte syncen</td><td><code>register_deactivation_hook</code> &rarr; <code>wp_unschedule_event</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Datenbank</div>
        <table class="wa-docs__table">
            <thead><tr><th>Tabelle</th><th>Beschreibung</th><th>Erstellt von</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>{prefix}wa_phorest_stock_log</code></td>
                    <td>Lager-Anpassungs-Log: <code>order_id</code>, <code>product_id</code>, <code>barcode</code>, <code>quantity</code>, <code>operation</code> (DEDUCT/INCREASE), <code>status</code>, <code>error_msg</code>, <code>created_at</code></td>
                    <td><code>dbDelta</code> in <code>includes/phorest/stock-sync.php</code> beim ersten <code>init</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">WP-Options</div>
        <table class="wa-docs__table">
            <thead><tr><th>Option</th><th>Beschreibung</th></tr></thead>
            <tbody>
                <tr><td><code>wa_phorest_active</code></td><td>Integration aktiv (0/1)</td></tr>
                <tr><td><code>wa_phorest_business_id</code></td><td>Phorest Business-ID</td></tr>
                <tr><td><code>wa_phorest_branch_id</code></td><td>Phorest Branch-ID</td></tr>
                <tr><td><code>wa_phorest_api_url</code></td><td>API Base URL</td></tr>
                <tr><td><code>wa_phorest_api_token</code></td><td>Base64-Auth-Token</td></tr>
                <tr><td><code>wa_phorest_last_sync</code></td><td>Letzter Produkt-Sync-Timestamp</td></tr>
                <tr><td><code>wa_phorest_stock_db_version</code></td><td>DB-Schema-Version (<code>1.0</code>)</td></tr>
                <tr><td><code>wa_newsletter_log</code></td><td>Newsletter-Subscribe-Ringbuffer</td></tr>
            </tbody>
        </table>
        <div class="wa-docs__notice wa-docs__notice--info">Transient (nicht in <code>wa_*</code>-Optionen): <code>wa_phorest_products_cache</code> (6h Gültigkeit) und <code>wa_nl_rl_{ip}</code> (Newsletter-Rate-Limit).</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Post-Meta-Keys</div>
        <table class="wa-docs__table">
            <thead><tr><th>Meta-Key</th><th>Post-Type</th><th>Inhalt</th></tr></thead>
            <tbody>
                <tr><td><code>_phorest_product_id</code></td><td>product</td><td>Phorest-Verknüpfungs-ID</td></tr>
                <tr><td><code>_phorest_last_sync</code></td><td>product</td><td>Timestamp letzter Sync</td></tr>
                <tr><td><code>_wc_gtin</code></td><td>product</td><td>EAN/GTIN (WC-9.2-natives Feld; via Phorest-Sync gepflegt)</td></tr>
                <tr><td><code>_phorest_stock_deducted</code></td><td>shop_order</td><td>Timestamp DEDUCT &mdash; Doppel-Schutz</td></tr>
                <tr><td><code>_phorest_purchase_synced</code></td><td>shop_order</td><td>Timestamp Order-Sync</td></tr>
                <tr><td><code>_phorest_purchase_id</code></td><td>shop_order</td><td>Phorest-Purchase-ID</td></tr>
                <tr><td><code>_phorest_purchase_error</code></td><td>shop_order</td><td>Fehlermeldung bei fehlgeschlagenem Order-Sync</td></tr>
                <tr><td><code>_phorest_client_id</code></td><td>shop_order / user</td><td>Phorest-Client-ID</td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Body-Klassen</div>
        <table class="wa-docs__table">
            <thead><tr><th>Klasse</th><th>Quelle</th></tr></thead>
            <tbody>
                <tr><td><code>wa-woocommerce</code></td><td><code>includes/layout/wc-shell.php</code></td></tr>
                <tr><td><code>wa-content-shell</code></td><td><code>includes/layout/wc-shell.php</code> (Filter <code>wa_content_shell_slugs</code>)</td></tr>
                <tr><td><code>wa-single-product</code></td><td><code>includes/woocommerce/single-product-renderer.php</code></td></tr>
                <tr><td><code>werbeauf-header-active</code></td><td><code>includes/layout/header-controller.php</code></td></tr>
                <tr><td><code>werbeauf-footer-active</code></td><td><code>includes/layout/footer-controller.php</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Auto-Loader-Reihenfolge</div>
        <pre class="wa-docs__pre">includes/core/        →  enqueue.php · admin-tweaks.php · divi-toggle-fix.php
includes/acf/         →  single-product-fields.php
includes/layout/      →  header-controller.php · footer-controller.php · wc-shell.php
includes/woocommerce/ →  flyout-cart.php · single-product-renderer.php
includes/phorest/     →  newsletter.php · order-sync.php · stock-sync.php · woo-sync.php
includes/shortcodes/  →  category-products.php · footer-menu.php · newsletter-form.php
                         shop-filter.php · shop-layout.php · shop-layout-short.php
admin/                →  admin-docu.php · admin-menu.php · phorest-api.php · phorest-data.php
                         phorest-newsletter.php · phorest-stocks.php · product-facts-column.php</pre>
        <div class="wa-docs__notice wa-docs__notice--info">Innerhalb eines Layers wird via <code>glob()</code> alphabetisch geladen. Reihenfolge zwischen Layern ist hardcoded in <code>werbeauf-customs.php</code>.</div>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Konstanten</div>
        <table class="wa-docs__table">
            <thead><tr><th>Konstante</th><th>Wert</th></tr></thead>
            <tbody>
                <tr><td><code>WERBEAUF_PLUGIN_URL</code></td><td>Plugin-URL inkl. Trailing-Slash</td></tr>
                <tr><td><code>WERBEAUF_PLUGIN_PATH</code></td><td>Plugin-Pfad inkl. Trailing-Slash</td></tr>
                <tr><td><code>WA_PHOREST_LINK_META</code></td><td><code>_phorest_product_id</code></td></tr>
                <tr><td><code>WA_PHOREST_CRON_HOOK</code></td><td><code>wa_phorest_auto_sync</code></td></tr>
                <tr><td><code>WA_NL_LOG_OPTION</code></td><td><code>wa_newsletter_log</code></td></tr>
            </tbody>
        </table>
    </div>

    <div class="wa-docs__section">
        <div class="wa-docs__section-title">Filter-Hooks</div>
        <table class="wa-docs__table">
            <thead><tr><th>Filter</th><th>Default</th><th>Verwendung</th></tr></thead>
            <tbody>
                <tr><td><code>wa_content_shell_slugs</code></td><td>(Array)</td><td>Liste der Page-Slugs, die <code>wa-content-shell</code> bekommen</td></tr>
                <tr><td><code>wa_account_endpoint_titles</code></td><td>(Array)</td><td>H1-Titel pro WC-Account-Endpoint</td></tr>
                <tr><td><code>wa_flyout_cart_active</code></td><td><code>true</code></td><td>Flyout-Cart pro Page deaktivieren</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}
