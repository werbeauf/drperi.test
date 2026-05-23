# Werbeauf Customs

> **Version:** see `werbeauf-customs.php` header. Release history: [CHANGELOG.md](CHANGELOG.md). Full structural map: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). All architecture/conventions: [CLAUDE.md](CLAUDE.md).

WordPress + WooCommerce Customizations fuer Dr. Peri Skincare. Dieses Plugin baut auf Divi auf und ergaenzt:

- Eigenes Layout-Shell fuer alle WC-Pages (Shop, Cart, Checkout, Account, Single-Product) sowie rechtliche Content-Pages (AGB, Datenschutz, Impressum, ...)
- Benutzerdefiniertes Single-Product-Hero (Gallery + Summary + Trust + Accordion)
- Shop-Layout-Shortcode `[wa_shop_layout]` fuer die Shop-Archive-Seite
- Phorest-API-Sync (Produkte, Stock, Orders) inkl. Admin-Settings
- Custom Header / Footer (Fallback, wenn Divi Theme Builder nichts definiert)
- Flyout-Cart Drawer

## Struktur

```
werbeauf-customs/
├── werbeauf-customs.php        # Bootstrap mit Auto-Loader
├── README.md                    # Diese Datei
├── docs/
│   ├── DESIGN.md                # Designsystem: Tokens, Farben, Typo, Buttons, Layout
│   ├── PHOREST.md               # Phorest-API Doku (Endpunkte, Sync-Flow)
│   ├── SHORTCODES.md            # Alle Shortcodes mit Attributen
│   ├── SINGLE-PRODUCT-DESCRIPTION.md  # Single-Product: Detail-Block / Single-Panel / Akkordeon
│   └── style-guide.html         # Montserrat Type-Scale (Preview im Browser)
├── admin/
│   ├── admin-menu.php           # WP-Admin-Menue
│   ├── admin-docu.php           # In-Admin-Doku
│   ├── phorest-api.php          # API-Settings-Page
│   ├── phorest-data.php         # Produkt-Sync UI
│   └── phorest-stocks.php       # Stock-Sync UI
├── assets/
│   ├── css/
│   │   ├── 00-base/             # tokens.css, style.css (Buttons + Utility) — immer
│   │   ├── 10-layout/           # Shells: wc-shell, content-shell, header, footer
│   │   ├── 20-components/       # Cards, Filter, Breadcrumb, Notices, Page-Title, Flyout
│   │   ├── 30-pages/            # Seitenspezifisch: shop-archive, single-product, cart, checkout, account, wc-blocks
│   │   └── 40-blocks/           # Shortcode-/Komponenten-Bloecke: shop-layout, trust-badges, accordion, detail-block
│   └── js/                      # Frontend-Scripts (Header, Sticky-Offset, Flyout, Single-Product, Detail-Block, Filter, Divi-Toggles)
├── includes/
│   ├── core/                    # enqueue.php, admin-tweaks.php, divi-toggle-fix.php
│   ├── acf/                     # Local Field Group Registrierungen (Single-Product-Felder etc.)
│   ├── layout/                  # header-controller.php, footer-controller.php, wc-shell.php (Body-Klassen)
│   ├── woocommerce/             # single-product-renderer.php, flyout-cart.php
│   ├── phorest/                 # woo-sync.php, stock-sync.php, order-sync.php
│   └── shortcodes/              # shop-layout.php, shop-filter.php, footer-menu.php, category-products.php, ...
└── templates/
    ├── header.php               # Custom-Header Markup
    └── footer.php               # Custom-Footer Markup
```

## CSS-Layering

CSS ist in 5 Ebenen organisiert. Layer-Prefix in den Foldern stellt sicher, dass die Cascade in derselben Reihenfolge greift, in der `enqueue.php` registriert.

| Layer | Verantwortung | Beispiel |
|-------|---------------|----------|
| `00-base` | Tokens (CSS-Variablen) + globale Typo/Reset | `tokens.css`, `style.css` |
| `10-layout` | Page-Shells (1400px-Container, Padding, Body-Class-Scoping) | `wc-shell.css`, `content-shell.css`, `header.css`, `footer.css` |
| `20-components` | Wiederverwendbare UI-Komponenten | `product-card.css`, `breadcrumb.css`, `notices.css`, `flyout-cart.css` |
| `30-pages` | Page-Type-spezifischer Code | `shop-archive.css`, `single-product.css`, `cart.css`, `checkout.css`, `account.css` |
| `40-blocks` | Shortcode-/Composition-Bloecke | `shop-layout.css`, `trust-badges.css`, `accordion.css` |

**Goldene Regel:** Wenn du das Layout einer einzelnen Page veraendern willst, suche zuerst in `30-pages/`. Erst wenn dort nichts hilft, gehe in `10-layout/wc-shell.css` (das gemeinsame Container-Verhalten).

## Auto-Loader

`werbeauf-customs.php` lädt alle PHP-Dateien aus `includes/` und `admin/` automatisch via `glob()`. Es gibt **keine** manuelle `require_once`-Liste — eine neue Datei in einem der vorgesehenen Subfolder wird automatisch geladen, sofern sie eine `.php`-Endung hat.

**Lade-Reihenfolge** (wichtig fuer Hook-Abhängigkeiten):

`core` → `acf` → `layout` → `woocommerce` → `phorest` → `shortcodes` → `admin/`

Innerhalb eines Folders alphabetisch. `layout/` braucht Hooks aus `core/`. `shortcodes/` nutzen Helpers aus `woocommerce/`. `acf/` haengt sich auf `acf/init` und ist daher unkritisch fuer die Reihenfolge.

## Body-Klassen

Drei Body-Klassen scopen das Styling. Sie werden von [includes/layout/wc-shell.php](includes/layout/wc-shell.php) bzw. [includes/woocommerce/single-product-renderer.php](includes/woocommerce/single-product-renderer.php) gesetzt.

| Klasse | Wann | Was |
|--------|------|-----|
| `wa-woocommerce` | Alle WC-Pages | Layout-Shell + WC-Komponenten |
| `wa-content-shell` | AGB, Datenschutz, Impressum, Widerruf, Versand, Zahlung | Layout-Container ohne WC-Tokens |
| `wa-single-product` | Single-Product-Pages | Eigene Tokens fuer den Hero (ueberschreibt wa-woocommerce) |

## Shortcodes

Komplette Referenz: [docs/SHORTCODES.md](docs/SHORTCODES.md). Quick-Liste:

- `[wa_shop_layout]` — Komplettes Shop-Archive-Layout (Intro + Hero + Hauptkatalog + Kategorien). Backcompat-Alias: `[drperi_shop_layout]`
- `[drperi_shop_layout_short]` — Reduziertes Layout (nur Hero + Hauptkatalog, ohne Intro + Kategorien)
- `[wa_shop_filter]` — Pill-Bar Kategorie-Filter (clientseitiges JS)
- `[wa_category_products]` — Produkte einer (auto-erkannten) Kategorie + "Andere Kategorien"
- `[wa_footer_menu]` — Footer-Menue der Theme-Location `footer-menu`
- `[wa_newsletter_signup]` — Newsletter-Form (REST-Submit, Honeypot, regular/footer-Variante)

## Weitere Dokumentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — Strukturkarte: Module, Public API, WP-Entry-Points, Filter-Contracts, Class-Hierarchy, DB-Zugriff, externe Integrationen, Asset-Map, WPML-Coverage, high-fan-out + dead-code, Auto-Loader-Vertrag, Tool-Verdict
- [docs/DESIGN.md](docs/DESIGN.md) — Designsystem (Tokens, Farben, Typo-Skala, Buttons, Layout-Standards)
- [docs/FOOTER.md](docs/FOOTER.md) — Custom Footer (Controller, ACF-Schema, Dark-Surface Tokens)
- [docs/PHOREST.md](docs/PHOREST.md) — Phorest-API Sync (Produkte, Stock, Orders, Server-Zugang)
- [docs/SHORTCODES.md](docs/SHORTCODES.md) — Komplette Shortcode-Referenz
- [docs/SINGLE-PRODUCT-DESCRIPTION.md](docs/SINGLE-PRODUCT-DESCRIPTION.md) — Single-Product Detail-Block / Akkordeon
- [docs/ORDER-STATUSES.md](docs/ORDER-STATUSES.md) — WooCommerce-Bestellstatus + Phorest-Stock-Trigger
- [docs/WPML.md](docs/WPML.md) — WPML-Setup + Helper-API
- [docs/style-guide.html](docs/style-guide.html) — Browser-Preview der Typo-Skala
- [CHANGELOG.md](CHANGELOG.md) — Release-Log (bump via `bin/bump-version.sh`)

## Wichtige Hinweise

- `wp-config.php` und `.credentials` **nie** in Git
- Plugin ist **drperi-spezifisch** — Phorest-Sync und der Default `featured_category="sets"` von `[wa_shop_layout]` passen nur fuer Dr. Peri
- Cache nach Asset-Aenderungen leeren: `dev-flush` oder Hard-Refresh (Cmd+Shift+R)
- Mockup-Workflow: Layouts in `dev/shop-layout-mockup.html` iterieren, dann in `40-blocks/shop-layout.css` lifte
