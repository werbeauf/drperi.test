# Shortcodes

Alle Shortcodes aus `werbeauf-customs`. Alphabetisch.

## `[wa_at_a_glance]`

"Auf einen Blick"-Tabelle: gruppiert publizierte Produkte nach `product_cat` und rendert pro Zeile **Bild · Produkt · Preis · Facts · Keypoints**. Pro Gruppe zeigt links ein 10px breiter Color-Bar in den Term-Farben (Term-Meta `wa_bg_color` / `wa_fg_color`); die Identitaet trag ein Cat-Chip neben dem Produktnamen.

Editorial-Spec-Sheet Design: weisser Container mit soft shadow, Hierarchie ueber `--fs-product-title` / `--fs-price` / `--fs-meta`, Facts als Soft-Pill-Chips (wrap), Keypoints als Label/Value-Stack (Overline in `--color-accent-2` ueber Body in `--color-accent`). Hover-State auf den Zeilen.

**Datei:** [includes/shortcodes/at-a-glance.php](../includes/shortcodes/at-a-glance.php)
**Stylesheet (self-enqueued):** `wa-at-a-glance` -> [assets/css/40-blocks/at-a-glance.css](../assets/css/40-blocks/at-a-glance.css)

**Responsive Breakpoints:**
- `> 1100px` — full Desktop, 5 Spalten, Header-Row sichtbar
- `<= 1100px` — kompakter Desktop (kleineres Bild, engere Gaps)
- `<=  980px` — Mobile-Stack: Header-Row aus, Cat-Bar wird Banner mit Text, Bild oben gedeckelt + zentriert, Name + Preis als Label/Value, Facts/Keypoints als eigene Section
- `<=  767px` — gleiche Struktur, engeres Padding + kleineres Bild

**Datenquellen pro Produkt:**
- Bild: ACF `clean_featured_image` via `wa_test_get_product_clean_image()` (defensive — leeres Array wenn Helper fehlt)
- Volumen: WC-Attribut "Inhalt" -> Fallback ACF `volume_ml` via `wa_test_get_product_volume()` (defensive)
- Preis: `WC_Product::get_price_html()` (Sale + Variable korrekt)
- Facts: ACF `facts` Repeater (max 3 Items)
- Keypoints: ACF `keypoints` Repeater (max 5 Items)
- Icons: `Werbeauf_Single_Product_Renderer::icon_svg()`

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `categories` | `reinigung,vorbeugung,pflege,specials,sets` | Slug-Liste in Renderreihenfolge. Leere oder nicht-gefundene Slugs werden ueberspringen. |
| `order` | `menu_order` | WP_Query orderby pro Kategorie. ASC. |

**Beispiele:**

```text
[wa_at_a_glance]

[wa_at_a_glance categories="reinigung,vorbeugung,pflege,specials"]

[wa_at_a_glance categories="pflege" order="title"]
```

---

## `[wa_category_products]`

Listet die Produkte der **aktuell aufgerufenen** WooCommerce-Produktkategorie. Gedacht fuer Divi Theme Builder Layouts, die auf "All Product Category Archive Pages" zugewiesen sind — eine einzige Shortcode-Zeile, die auf jeder `/produkt-kategory/<slug>/`-URL automatisch die richtigen Produkte zeigt.

Render-Reihenfolge: `<h1>` Kategorie-Name -> Term-Description -> `[products]`-Grid -> "Andere Kategorien entdecken" mit `[product_categories]` (ohne aktuelle Kategorie).

**Datei:** [includes/shortcodes/category-products.php](../includes/shortcodes/category-products.php)
**Stylesheets (auto-enqueued):** `wa-product-card`, `wa-category-card`, `wa-shop-layout`

**Kontext-Erkennung (in dieser Reihenfolge):**

1. Attribut `category="slug"` (explizit, gewinnt immer)
2. `is_product_category()` -> aktuelles `get_queried_object()`
3. URL-Parameter `?product_cat=slug`
4. Attribut `fallback="slug"` (wenn nichts erkannt wurde)

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `category` | (auto) | Slug einer Kategorie. Ueberschreibt die Auto-Erkennung. |
| `fallback` | (leer) | Slug der Kategorie, wenn kein Kontext erkannt wurde. |
| `limit` | `12` | Anzahl Produkte (pro Seite) |
| `columns` | `4` | Spaltenanzahl |
| `orderby` | `menu_order` | `menu_order`, `title`, `date`, `price`, `popularity`, `rating`, `rand` |
| `order` | `ASC` | `ASC` oder `DESC` |
| `paginate` | `yes` | Pagination aktivieren? |
| `on_sale` | (leer) | `yes` -> nur Sale-Produkte |
| `best_selling` | (leer) | `yes` -> nach Bestseller sortieren |
| `show_title` | `yes` | Kategorie-Name als `<h1>` ausgeben |
| `show_description` | `yes` | Term-Description ausgeben |
| `show_browse_other` | `yes` | Block "Andere Kategorien entdecken" am Ende anzeigen |
| `browse_other_title` | `Andere Kategorien entdecken` | H3-Text fuer den "Andere Kategorien"-Block |
| `browse_columns` | `5` | Spaltenanzahl im "Andere Kategorien"-Block |
| `browse_parent` | `0` | Slug oder ID einer Eltern-Kategorie -> nur deren direkte Kinder. `0` = Top-Level. |
| `browse_hide_empty` | `yes` | Leere Kategorien (0 Produkte) ausblenden? |

**Beispiele:**

```text
[wa_category_products]

[wa_category_products limit="16" columns="4"]

[wa_category_products show_title="no" show_description="no"]

[wa_category_products category="sets"]

[wa_category_products fallback="alle" limit="20"]

[wa_category_products show_browse_other="no"]

[wa_category_products browse_other_title="Weitere Kategorien" browse_columns="4"]
```

---

## `[wa_footer_menu]`

Rendert das WordPress-Menue der Theme-Location `footer-menu` als `<nav class="legal-menu"><ul class="wa-footer-ul">...`. Wird im Custom-Footer-Template fuer die rechtlichen Links (AGB, Datenschutz, Impressum, ...) verwendet.

**Datei:** [includes/shortcodes/footer-menu.php](../includes/shortcodes/footer-menu.php)
**Stylesheet:** Die `.legal-menu`-Item-Styles liegen in [assets/css/10-layout/footer.css](../assets/css/10-layout/footer.css) (Sektion 6 "LEGAL MENU"). Wird **nur** geladen, wenn der Fallback-Footer aktiv ist (kein Divi-TB-Footer). Wenn der Shortcode innerhalb eines Divi-TB-Footers eingesetzt werden soll, muss `footer.css` separat enqueued werden.

**Attribute:** keine.

**Beispiel:**

```text
[wa_footer_menu]
```

---

## `[wa_product_ref]` + `[wa_product_refs]`

Verlinkt andere WooCommerce-Produkte aus der **Long-** oder **Short-Description** heraus. Zwei Shortcodes mit klar getrennten Use-Cases:

### `[wa_product_refs ids="..."]` — Inline-Liste fuer Short-Description

Self-closing. Liefert eine kommagetrennte Linkliste der Produktnamen. Bei genau zwei IDs wird `und` als Konjunktion eingefuegt; bei drei oder mehr `…, …, … und …`. Ungueltige / nicht-publizierte IDs werden still uebersprungen.

```text
Eine sanfte Kombination fuer Tag und Nacht. Enthaelt
[wa_product_refs ids="123,124,125"].
```

Render: `Enthaelt <a>PERICLEAN FOAM</a>, <a>PERITONIC AHA</a> und <a>PERICLEAR CREME</a>.`

Mit optionalem Title (als `<strong>` mit Linebreak vorangestellt) und Custom-Eintraegen ohne Link:

```text
[wa_product_refs ids="123,124,125" title="Das Set besteht aus" extra="Zusatztube|Pinsel"]
```

Render:
```html
<strong>Das Set besteht aus</strong><br>
<a>PERICLEAN FOAM</a>, <a>PERITONIC AHA</a>, <a>PERICLEAR CREME</a>, Zusatztube und Pinsel
```

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `ids` | (leer) | Kommaseparierte Liste von Produkt-IDs |
| `title` | (leer) | Optionaler Titel, wird als `<strong>…</strong><br>` vor die Liste gesetzt |
| `extra` | (leer) | Pipe-getrennte (`\|`) Liste von Custom-Eintraegen ohne Link, werden ans Ende der Liste gehaengt |

### `[wa_product_ref id="..."] ... [/wa_product_ref]` — Tabellen-Zeile fuer Long-Description

Enclosing. Rendert eine Zeile **Bild · Name + Text · Zum-Produkt-Link**. Mehrere Bloecke nacheinander ergeben automatisch eine zusammenhaengende Tabelle (Border-Top auf der ersten, Border-Bottom auf jeder Zeile). Auf Mobile wechselt das Layout zu Image-links / Body+CTA-rechts.

Body-Text-Quellen (Reihenfolge):
1. Inhalt zwischen Open- und Close-Tag
2. Short-Description des referenzierten Produkts (Fallback)
3. leer (Card rendert nur Image + Name + Link)

```text
Folgende Produkte sind enthalten – sorgfaeltig ausgewaehlt und optimal aufeinander abgestimmt.

[wa_product_ref id="123"]
Der klaerende Reinigungsschaum reinigt die Haut porentief mit 3 % Glykolsaeure ...
[/wa_product_ref]

[wa_product_ref id="124" image="https://drperi.test/wp-content/uploads/sonderpic.jpg"]
Das revitalisierende Gesichtstonic kombiniert Fruchtsaeuren mit Hamamelis ...
[/wa_product_ref]
```

**Manueller Modus** (kein Produkt verlinkt — kein `id`, dafuer `name`):

```text
[wa_product_ref name="Sonder-Edition" image="https://drperi.test/wp-content/uploads/special.jpg"]
Limited Run nur zur Messe – nicht ueber den Shop bestellbar.
[/wa_product_ref]
```

Im manuellen Modus wird die Zeile ohne Produktlink und ohne "Zum Produkt"-CTA gerendert (Modifier-Klassen `wa-product-ref--no-cta`, optional `wa-product-ref--no-image`, falls auch `image` fehlt).

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `id` | `0` | ID des referenzierten Produkts. Wenn gesetzt + gueltig: verlinkter Modus. |
| `image` | (leer) | Optionale Bild-URL — uebersteuert das Produktbild. Im manuellen Modus die einzige Bildquelle. |
| `name` | (leer) | Pflicht im manuellen Modus (wenn `id` fehlt). Wird als nicht-verlinkter Titel ausgegeben. |

**Hinweis:** Den Block-Shortcode in eine eigene Zeile mit Leerzeilen davor/danach setzen — sonst kann WordPress' `wpautop` ein `<p>` um den `<div class="wa-product-ref">` legen (invalid HTML). `shortcode_unautop` raeumt das nur auf, wenn der Shortcode allein im Absatz steht.

**Datei:** [includes/shortcodes/product-references.php](../includes/shortcodes/product-references.php)
**Stylesheet:** [assets/css/40-blocks/product-refs.css](../assets/css/40-blocks/product-refs.css) (`wa-product-refs`, self-enqueued vom Block-Shortcode)

---

## `[wa_newsletter_signup]`

Newsletter-Anmeldeformular mit Vorname, Nachname, E-Mail, Datenschutz-Consent. Submit POSTet via REST an `/wp-json/wa/v1/newsletter`. Honeypot + Time-Trap + WP-Nonces gegen Spam.

**Datei:** [includes/shortcodes/newsletter-form.php](../includes/shortcodes/newsletter-form.php)
**Stylesheet:** [assets/css/40-blocks/newsletter.css](../assets/css/40-blocks/newsletter.css)
**Script:** [assets/js/newsletter.js](../assets/js/newsletter.js)
**REST-Backend:** [includes/phorest/newsletter.php](../includes/phorest/newsletter.php)

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `variant` | `regular` | `regular` (Card-Layout mit Titel + Lead) oder `footer` (kompakte Inline-Form fuer Footer-Slots) |
| `title` | `Newsletter` | H3-Text (nur in `variant=regular` sichtbar) |
| `lead` | `Erhalte Beauty-Insights ...` | Lead-Text (nur in `variant=regular`) |
| `button` | `Anmelden` | Submit-Button-Text |
| `privacy_url` | `/datenschutz/` | Link-Ziel im Consent-Label |

**Beispiele:**

```text
[wa_newsletter_signup]

[wa_newsletter_signup variant="footer"]

[wa_newsletter_signup title="Stay in the Loop" lead="Beauty-Tipps direkt..." button="Eintragen"]
```

---

## `[wa_shop_filter]`

Pill-Bar mit dynamisch ausgelesenen WooCommerce-Produktkategorien. Filtert die rechts darauf folgende `ul.products` clientseitig per JavaScript (basiert auf den `product_cat-{slug}` CSS-Klassen, die WC automatisch an jedes `<li class="product">` haengt).

**Datei:** [includes/shortcodes/shop-filter.php](../includes/shortcodes/shop-filter.php)
**Stylesheet:** [assets/css/20-components/shop-filter.css](../assets/css/20-components/shop-filter.css)
**Script:** [assets/js/shop-filter.js](../assets/js/shop-filter.js)

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `exclude` | (leer) | Kommagetrennte Slugs, die NICHT als Pill erscheinen |
| `include` | (leer) | Kommagetrennte Slugs — wenn gesetzt, NUR diese werden gezeigt |
| `parent` | (leer) | Slug einer Eltern-Kategorie -> nur direkte Kinder als Pills |
| `hide_empty` | `yes` | Leere Kategorien (0 Produkte) ausblenden? |
| `all_label` | `Alle` | Beschriftung des "Alle"-Pills |
| `target` | (leer) | Optionaler CSS-Selector der zu filternden `ul.products`, falls sie nicht in derselben `<section>` liegt |

**Beispiele:**

```text
[wa_shop_filter exclude="sets"]
[products limit="12" columns="4" category="sets" cat_operator="NOT IN" orderby="menu_order" order="DESC"]

[wa_shop_filter include="serum,creme,reinigung"]
[products limit="20" columns="4"]

[wa_shop_filter parent="pflege" all_label="Alle Pflege"]
[products limit="20" columns="4" category="pflege"]
```

---

## `[wa_shop_layout]`

Komplettes Shop-Archive-Layout fuer Dr. Peri: Intro (H1 + Lead-Text), optional Featured Sets Hero, Hauptkatalog mit Filter, optional Kategorien-Block am Ende.

Wird typischerweise in einem Divi Code Module auf der Shop-Seite (`is_shop()`) eingesetzt. Die Aussen-Begrenzung uebernimmt die Divi-Row, der Shortcode rendert intern alles auf 100% Breite.

**Backcompat-Alias:** `[drperi_shop_layout]` ist registriert, damit existierende Pages nicht brechen.

**Datei:** [includes/shortcodes/shop-layout.php](../includes/shortcodes/shop-layout.php)
**Stylesheet:** [assets/css/40-blocks/shop-layout.css](../assets/css/40-blocks/shop-layout.css)

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `intro_title` | `Shop D. Peri Skincare` | H1 ueber dem Shop |
| `intro_text` | (lange DE-Version) | Lead-Absatz unter der H1 |
| `show_featured` | `yes` | Featured Sets Hero anzeigen? |
| `featured_title` | `Unsere Top-Sets` | H3 ueber dem Featured-Hero |
| `featured_text` | `Sorgfaeltig kombinierte ...` | Subtitel unter dem H3 |
| `featured_category` | `sets` | Slug der Featured-Kategorie. Diese Kategorie wird gleichzeitig im Hauptkatalog ausgeschlossen, damit nichts doppelt angezeigt wird. Leerstring -> Featured + Exclude deaktiviert |
| `featured_limit` | `3` | Anzahl Produkte im Featured-Hero |
| `featured_columns` | `3` | Spaltenanzahl im Featured-Hero |
| `all_title` | `Alle Produkte` | H3 ueber dem Hauptkatalog |
| `all_limit` | `12` | Anzahl Produkte im Hauptkatalog |
| `all_columns` | `4` | Spaltenanzahl im Hauptkatalog |
| `show_categories` | `yes` | Kategorien-Block am Ende anzeigen? |
| `categories_title` | `Kategorien entdecken` | H3 ueber dem Kategorien-Block |
| `categories_columns` | `5` | Spaltenanzahl im Kategorien-Block |

**Beispiele:**

```text
[wa_shop_layout]

[wa_shop_layout featured_category="sets" all_limit="16"]

[wa_shop_layout show_featured="no" show_categories="no"]

[wa_shop_layout intro_title="Pflege & Beauty" featured_category="aktionen"]

[wa_shop_layout featured_category="" all_limit="20"]
```

**Wichtig:** Der Shortcode self-enqueued seine Stylesheets (`wa-product-card`, `wa-category-card`, `wa-shop-layout`). Auf der Shop-Archive-Page sind diese eh schon geladen via `enqueue.php`; auf einer Nicht-WC-Page wird der Shortcode trotzdem korrekt rendern.

---

## `[drperi_shop_layout_short]`

Reduzierte Variante von `[wa_shop_layout]`. Rendert nur den Featured Sets Hero + den Hauptkatalog mit Filter — **kein** Intro-Block (H1 + Lead) und **kein** Kategorien-Block am Ende. Geeignet fuer Sub-Pages, die schon einen eigenen Page-Title haben (z. B. „Sale" oder eine Aktion-Landing-Page).

**Datei:** [includes/shortcodes/shop-layout-short.php](../includes/shortcodes/shop-layout-short.php)
**Stylesheet:** [assets/css/40-blocks/shop-layout.css](../assets/css/40-blocks/shop-layout.css)

**Attribute (alle optional):**

| Attribut | Default | Beschreibung |
|----------|---------|--------------|
| `show_featured` | `yes` | Featured Sets Hero anzeigen? |
| `featured_title` | `Unsere Top-Sets` | H3 ueber dem Featured-Hero |
| `featured_text` | `Sorgfaeltig kombinierte ...` | Subtitel unter dem H3 |
| `featured_category` | `sets` | Slug der Featured-Kategorie. Wird im Hauptkatalog ausgeschlossen. Leerstring → Featured + Exclude deaktiviert |
| `featured_limit` | `3` | Anzahl Produkte im Featured-Hero |
| `featured_columns` | `3` | Spaltenanzahl im Featured-Hero |
| `all_title` | `Alle Produkte` | H3 ueber dem Hauptkatalog |
| `all_limit` | `12` | Anzahl Produkte im Hauptkatalog |
| `all_columns` | `4` | Spaltenanzahl im Hauptkatalog |

**Beispiele:**

```text
[drperi_shop_layout_short]

[drperi_shop_layout_short featured_category="sets" all_limit="16"]

[drperi_shop_layout_short show_featured="no"]
```
