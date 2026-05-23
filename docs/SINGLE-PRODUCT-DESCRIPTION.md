# Single Product Page — Produktbeschreibung

CSS-Klassen, Hooks und Styling-Optionen für die Produktbeschreibung auf den
Einzelproduktseiten (Dr. Peri).

Quellen:
- `assets/css/30-pages/single-product.css` — Page-Layout, Short-Description, Single-Panel Fallback, `.wa-readmore-link`
- `assets/css/40-blocks/detail-block.css` — Sticky-Tab-Card (Default)
- `assets/css/40-blocks/accordion.css` — Apple-Style Akkordeon (FAQ + Reuse)
- `assets/js/detail-block.js` — Tab-Switching + Smooth-Scroll mit Sticky-Header-Korrektur
- `assets/js/sticky-offset.js` — setzt `--wa-header-h` live auf die Header-Bottom-Linie
- `includes/woocommerce/single-product-renderer.php` — HTML-Output (`render_short_description_more_link()`, `render_detail_block()`)
- Tokens: `assets/css/00-base/tokens.css`

---

## 1. Wann wird welcher Container gerendert?

Der Renderer (`Single_Product_Renderer::render_detail_block()`) entscheidet
anhand der vorhandenen WC-Tabs:

| Bedingung | Container | Klasse |
|-----------|-----------|--------|
| Beide Tabs vorhanden (`description` + `additional_information`) | Sticky-Tab-Card | `.wa-detail-block` |
| Genau einer der beiden Tabs vorhanden | Einzel-Card (Fallback) | `.wa-single-panel` |
| Keiner | nichts | — |

Beide Container nehmen den Inhalt der **Produktbeschreibung** (WordPress/WC
Editor oder Divi → Produktbeschreibung-Modul) auf und stylen alle gängigen
HTML-Elemente automatisch.

---

## 2. Default-Container — `.wa-detail-block` (Sticky-Tab-Card)

Markup-Skelett:

```html
<section class="wa-detail-block" aria-labelledby="wa-detail-block-title">
  <h2 id="wa-detail-block-title"
      class="wa-detail-block__title wa-section__heading">Produktdetails</h2>

  <header class="wa-detail-block__header">
    <nav class="wa-detail-block__nav" role="tablist">
      <button class="wa-detail-block__pill is-active"
              role="tab"
              data-target="wa-panel-description"
              aria-controls="wa-panel-description">Produktdetails</button>
      <button class="wa-detail-block__pill"
              role="tab"
              data-target="wa-panel-additional_information"
              aria-controls="wa-panel-additional_information">Zusätzliche Informationen</button>
    </nav>
  </header>

  <div class="wa-detail-block__panels">
    <div class="wa-detail-block__panel" id="wa-panel-description" role="tabpanel">
      <!-- Produktbeschreibung HTML (Editor-Output) -->
    </div>
    <div class="wa-detail-block__panel" id="wa-panel-additional_information" role="tabpanel" hidden>
      <!-- WC-Attribut-Tabelle -->
    </div>
  </div>
</section>
```

Das Pill-↔-Panel-Mapping läuft über `data-target` (matcht das `id` des Panels). `aria-controls` und `role="tab"`/`role="tabpanel"` werden vom Renderer mitgesetzt.

### 2.1 Klassenübersicht

| Klasse | Funktion / Styling |
|--------|--------------------|
| `.wa-detail-block` | Card-Wrapper. Weißer Hintergrund (`--wa-surface`), `border-radius: var(--wa-radius)` (16 px), `box-shadow: var(--wa-shadow)`, **kein** `overflow:hidden` (sonst bricht Sticky). |
| `.wa-detail-block__title` | H2 oberhalb der Pills. Padding `clamp(24-36 px) clamp(20-32 px) 0`. Erbt Schriftgrößen aus `.wa-section__heading`. |
| `.wa-detail-block__header` | Sticky-Bar mit Pills. `position: sticky`, `top: var(--wa-header-h, 76px)`, `z-index: 5`. Auf Mobile (`<= 720 px`) `top: var(--wa-header-h, 60px)`. |
| `.wa-detail-block__nav` | Flex-Container für die Pills. Auf Mobile horizontal scrollbar (`overflow-x: auto`, ohne sichtbare Scrollbar). |
| `.wa-detail-block__pill` | Inaktive Pill: transparent, `border: 1px solid var(--wa-border)`, `color: var(--color-accent)`, `radius: var(--radius-pill)`, Padding `9px 18px`. Hover: `background: var(--color-bg-soft)`. |
| `.wa-detail-block__pill.is-active` | Aktive Pill: `background + border-color: var(--color-accent)`, `color: #fff`. |
| `.wa-detail-block__pill:focus-visible` | `outline: 2px solid var(--color-accent)`. |
| `.wa-detail-block__panels` | Inner-Padding der Panel-Zone: `clamp(20-32 px)`. |
| `.wa-detail-block__panel` | Inhalts-Container der Beschreibung. Body-Größe (`--fs-body`), `line-height: 1.7`, Textfarbe `#475e76` (= volle `--wa-text`). |
| `.wa-detail-block__panel[hidden]` | Wird vom JS toggle-bar gemacht (`display: none`). |

### 2.2 Typografie innerhalb der Beschreibung

Alle Editor-Elemente werden über `.wa-detail-block__panel` getargeted:

| Element | Styling |
|---------|---------|
| `.wa-detail-block__panel > h2:first-child` | **Versteckt** (WC rendert intern ein doppeltes „Beschreibung"-H2). |
| `.wa-detail-block__panel h2`, `h3` | `--fs-h5` (18→20 px), weight 600, line-height 1.4, `color: var(--color-heading)`, Margin `18px 0 10px`. |
| `.wa-detail-block__panel p:last-child` | Margin-Bottom `0` (sonst Browser-Default). |
| `.wa-detail-block__panel a` | `color: var(--wa-accent)`, kein Underline. Hover: animierte Underline (`border-bottom: 1px solid var(--wa-accent)`). |
| `.wa-detail-block__panel ul`, `ol` | `padding-left: 1.2em`, Margin `0 0 14px`. |
| `.wa-detail-block__panel li` | `margin-bottom: 6px`. |
| `.wa-detail-block__panel *` | **Niemals kursiv** — `font-style: normal !important` (Schutz vor `<em>`/`<i>`/Theme-Kursivklassen). |

### 2.3 Tabellen (Zusätzliche Informationen)

WooCommerce rendert die Attribute als
`<table class="shop_attributes woocommerce-product-attributes">`.

| Selector | Styling |
|----------|---------|
| `.shop_attributes` / `.woocommerce-product-attributes` | `width: 100%`, `border-collapse: collapse`, transparent. |
| `… th` | `text-align: left`, `padding: 12px 0`, weight 600, `--fs-small`, `color: var(--color-heading)`, Bottom-Hairline `--wa-border-2`, `width: 35%`. |
| `… td` | `padding: 12px 0`, `--fs-body`, `color: var(--wa-text)`, gleiche Hairline. |
| Letzte Reihe | Hairline entfernt. |

### 2.4 Mobile-Verhalten

- `< 720 px`: Pill-Nav scrollt horizontal (Scrollbar versteckt), Pills bleiben in Originalgröße.
- `< 480 px`: Panel-Schrift fällt auf `--fs-small` (13→14 px) zurück, `line-height: 1.65`.

### 2.5 Reduced Motion

`@media (prefers-reduced-motion: reduce)` schaltet alle Transitions auf den Pills und Panel-Links ab.

---

## 3. Fallback — `.wa-single-panel` (nur ein Tab)

Wird gerendert, wenn entweder *nur* die Beschreibung oder *nur* die Zusatzinfos
existieren. Markup:

```html
<section class="wa-single-panel"
         id="wa-panel-description"
         aria-label="Produktdetails">
  <div class="wa-single-panel__body">
    <!-- Produktbeschreibung HTML -->
  </div>
</section>
```

Wichtig: `id="wa-panel-{key}"` bleibt erhalten, damit der Read-More-Link aus der Short Description (Sektion 6) auch im Single-Panel-Modus ein gültiges Sprungziel hat.

| Klasse | Styling |
|--------|---------|
| `.wa-single-panel` | Card: `--wa-surface`, `border-radius: var(--wa-radius)`, `box-shadow: var(--wa-shadow)`, Padding `32px` (Mobile: `24px 20px`), Margin-Top `56px` / `40px`. |
| `.wa-single-panel__body` | `--fs-body`, `line-height: 1.75`, `color: var(--wa-text)` (= `#475e76`, volle Textfarbe — konsistent mit `.wa-detail-block__panel`). Mobile: `--fs-small`, `1.7`. |
| `.wa-single-panel__body > h2:first-child` | Versteckt (analog Detail-Block). |
| `.wa-single-panel__body h2`, `h3` | `--fs-h5`, weight 600, `color: var(--color-heading)`, Margin `18px 0 10px`. |
| `.wa-single-panel__body p` | Margin `0 0 14px`, letzter `0`. |
| `.wa-single-panel__body a` | `color: var(--wa-accent)`. Hover: `var(--wa-accent-d)` (= `--color-accent-2`). |
| `.wa-single-panel__body ul`, `ol` | `padding-left: 1.2em`, Margin `0 0 14px`. |
| `.wa-single-panel__body li` | `margin-bottom: 6px`. |

---

## 4. Wiederverwendbares Akkordeon (`.wa-accordion`)

Falls die Beschreibung in einem Akkordeon-Layout aufgebrochen werden soll
(z. B. „Beschreibung / Inhaltsstoffe / Anwendung"), liegt das Borderless-Pattern
aus `40-blocks/accordion.css` bereit:

```html
<div class="wa-accordion">
  <details class="wa-accordion__item" open>
    <summary class="wa-accordion__head">
      <span class="wa-accordion__title">Beschreibung</span>
      <svg class="wa-accordion__icon" …>
        <line class="wa-accordion__icon-h" …/>
        <line class="wa-accordion__icon-v" …/>
      </svg>
    </summary>
    <div class="wa-accordion__body">
      <!-- Produktbeschreibung HTML -->
    </div>
  </details>
</div>
```

| Klasse | Styling |
|--------|---------|
| `.wa-accordion` | Borderless-Liste, nur Top-Hairline (`--wa-border`). Margin-Top `clamp(4rem, 8vw, 6rem)`. |
| `.wa-accordion__item` | Transparent, Bottom-Hairline. |
| `.wa-accordion__item[open]` | Kein zusätzlicher Schatten — Apple-pure. |
| `.wa-accordion__head` | `--fs-h6` (16→18 px), weight 600, `color: var(--color-heading)`, Padding `clamp(20-26 px) 0`. Hover: Farbe → `--wa-accent`. |
| `.wa-accordion__head:focus-visible` | `outline: 2px solid var(--wa-accent)`. |
| `.wa-accordion__icon` | 18 × 18 px. Plus → Minus über `transform: scaleY(0)` der `.wa-accordion__icon-v`-Linie wenn `[open]`. |
| `.wa-accordion__body` | `--fs-body`, `line-height: 1.7`, `color: #475e76`, Padding-Bottom `clamp(22-30 px)`. Mobile (`<= 767 px`): `--fs-small`, `1.65`. |
| `.wa-accordion__body h2`/`h3` | `--fs-h5`, weight 600. |
| `.wa-accordion__body p` | Margin `0 0 14px`. |
| `.wa-accordion__body a` | `color: var(--wa-accent)`, animierte Underline. Hover: `--wa-accent-d`. |
| `.wa-accordion__body ul`/`ol` | `padding-left: 1.2em`, Margin `0 0 14px`. |
| Smooth-Open | `@supports (interpolate-size: allow-keywords)` aktiviert weiches Auf/Zu (280 ms). |

> Default WooCommerce-Tabs (`.woocommerce-tabs`) sind global ausgeblendet
> (`display: none !important`), wenn `body.wa-single-product` aktiv ist.

---

## 5. Short Description + Read-More-Link

### 5.1 Short Description

WooCommerce rendert das **Produkt-Excerpt** als `<div class="woocommerce-product-details__short-description">` in `.summary` (rechte Spalte des Hero-Layouts). Hook: `woocommerce_template_single_excerpt` (Priority 12).

Styling (in `single-product.css`):

| Selector | Wert |
|---|---|
| `.summary .woocommerce-product-details__short-description` | `font-size: var(--fs-lead)`, `line-height: 1.6`, `color: var(--wa-text)` (volle Textfarbe — keine `--wa-text-soft`-Dimmung mehr) |
| `… p` | `margin: 0 0 10px` — alle Absätze einheitlich, kein `:first-child`-Lead-Override |

**Inhaltspflege:** Die Short Description ist ein normaler WP-Editor-Bereich mit HTML-Support. `<strong>` für Inline-Akzente, `<ol>`/`<ul>` für Listen, `<a href="#wa-panel-description">` für In-Page-Links zur Long Description (siehe 5.2 + 5.3).

### 5.2 Read-More-Link `.wa-readmore-link`

Direkt **unter** der Short Description rendert `Single_Product_Renderer::render_short_description_more_link()` einen dezenten Text-Link mit Pfeil-Icon — **nur wenn das Produkt eine nicht-leere Long Description hat** (`$product->get_description()` mit `wp_strip_all_tags()`-Trim-Check).

Markup:

```html
<a href="#wa-panel-description"
   class="wa-readmore-link"
   data-wa-readmore="description">
  <span class="wa-readmore-link__text">Produktbeschreibung lesen</span>
  <svg class="wa-readmore-link__arrow" viewBox="0 0 24 24" …>
    <path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>
  </svg>
</a>
```

Hook-Reihenfolge in `init_renderer()`:

```text
woocommerce_template_single_excerpt          (Prio 12) — WC Default
render_short_description_more_link()         (Prio 12, danach registriert)
render_key_points()                          (Prio 13)
```

| Klasse | Styling |
|---|---|
| `.wa-readmore-link` | Inline-Flex, kein Button-Look. `font-size: 0.875rem`, `line-height: 1.4`, `color: var(--wa-text-soft)`, kein Underline, `border-bottom: 1px solid transparent`. Transitions `160ms ease`. |
| `.wa-readmore-link:hover` / `:focus-visible` | Farbe → `var(--wa-accent)`, `border-bottom-color: currentColor`, `gap: 9px` (Pfeil rückt etwas nach rechts). |
| `.wa-readmore-link__arrow` | 14×14px, `flex-shrink: 0`. |

**Verhalten beim Klick** (siehe 5.3):

- **Detail-Block-Modus** (2 Tabs vorhanden) → Pill „Produktdetails" wird aktiviert + Smooth-Scroll zum Block. URL-Hash wird **nicht** gesetzt (im Single-Panel-Modus auch nicht).
- **Single-Panel-Modus** (nur eine Beschreibung) → Smooth-Scroll direkt zum `.wa-single-panel`.

### 5.3 JS-Handler — `assets/js/detail-block.js`

Übersicht des Verhaltens und der nicht-offensichtlichen Robustheits-Fixes:

**Capture-Phase Document-Listener.** Klicks auf `a[href*="#wa-panel-"]` werden in der **Capture-Phase** auf `document` abgefangen (`addEventListener('click', handlePanelLinkClick, true)`) und mit `e.preventDefault() + e.stopImmediatePropagation()` abgebrochen.

Grund: Divi (oder andere Frontend-Skripte) rufen bei Anchor-Klicks `stopPropagation()` in der Bubble-Phase auf — ohne Capture würde unser delegierter Handler nie erreicht werden, und der eingebaute Anchor-Smooth-Scroll von Divi würde mit falschem Header-Offset feuern.

**Header-Höhen-Messung.** `getStickyOffset()` misst direkt am DOM:

1. `header.et-l--header` (Divi Theme Builder Header, falls aktiv) → `getBoundingClientRect().bottom`
2. `#wa-header` (eigener Fallback-Header) → `getBoundingClientRect().bottom`
3. CSS-Variable `--wa-header-h` (Live-getrackt von `sticky-offset.js`)
4. `76` als Worst-Case-Fallback

Direktmessung statt CSS-Variable, weil `--wa-header-h` zum Klick-Zeitpunkt aus dem Top-Zustand noch der **Flow-Wert** sein kann, nicht der finale **Sticky-Wert** (Divi-Sticky-Modul ändert die Header-Höhe beim Scrollen).

**Iterative Scroll-Korrektur.** `scrollToElement(el)` macht ein erstes `window.scrollTo({ behavior: 'smooth' })` und wartet dann auf `scrollend` (oder Timeout-Fallback bei Browsern ohne Support). Wenn die Section-Top mehr als 2px vom Wunschpunkt (`stickyTop + SCROLL_BUFFER`) entfernt sitzt, wird **nachjustiert** — bis zu 3 Iterationen. Konvergiert in der Regel nach 1-2 Pässen, weil der Header nach dem ersten Scroll bereits im Sticky-Endzustand ist.

Konstanten:

| Const | Wert | Zweck |
|---|---|---|
| `SCROLL_BUFFER` | `24` | px-Abstand zwischen Sticky-Header-Unterkante und Section-Top |
| `MAX_PASSES` | `3` | maximale Korrektur-Iterationen |
| `TOLERANCE` | `2` | px-Schwelle, ab der eine Korrektur ausgelöst wird |

**Reduced Motion.** Bei `prefers-reduced-motion: reduce` wird mit `behavior: 'auto'` gesprungen und die iterative Korrektur ausgelassen.

**Tab-Anker per URL-Hash.** Beim Page-Load wird `location.hash` ausgelesen — wenn er auf ein `.wa-detail-block__panel` matcht, wird der Tab aktiviert + gescrollt (Deep-Linking auf bestimmte Tab-Zustände).

---

## 6. Design-Tokens (für eigene Erweiterungen)

Die Beschreibungs-Container greifen auf den Page-Scope `body.wa-single-product`
zu. Dort sind diese Tokens verfügbar:

| Token | Wert |
|-------|------|
| `--wa-bg` | `--color-bg-soft` (`#f7f8fa`) — Page-Background |
| `--wa-surface` | `--color-bg` (`#ffffff`) — Card-Background |
| `--wa-text` | `--color-accent` (`#475e76`) — **Body-Text inkl. Short Description, Long Description und Read-More-Link-Hover** |
| `--wa-text-soft` | `rgba(71, 94, 118, 0.72)` — Read-More-Link-Default, Meta-Texte, Captions (kein Lese-Fließtext mehr) |
| `--wa-text-mute` | `--color-text-muted` (`rgba(71, 94, 118, 0.6)`) — Labels/Eyebrows |
| `--wa-accent` | `#475e76` — Links/Pills aktiv |
| `--wa-accent-d` | `#769cc1` — Link-Hover |
| `--wa-border` | `rgba(71, 94, 118, 0.12)` — Hairlines |
| `--wa-border-2` | `rgba(71, 94, 118, 0.06)` — sehr leise Hairlines (Tabellen, Reviews) |
| `--wa-radius` | `--radius-xl` (16 px) — Card-Radius |
| `--wa-radius-sm` | `--radius-lg` (12 px) |
| `--wa-shadow` | `--shadow-sm` |
| `--wa-shadow-lg` | `--shadow-md` |

Schriftskala (Auszug aus `tokens.css`):

| Token | Bereich |
|-------|---------|
| `--fs-body` | 15 → 16 px |
| `--fs-small` | 13 → 14 px |
| `--fs-lead` | 17 → 20 px |
| `--fs-h5` | 18 → 20 px (Inline-Headings) |
| `--fs-h6` | 16 → 18 px (Akkordeon-Heads) |
| `--fs-h3` | 22 → 28 px (Section-Headings) |

---

## 7. Inhaltspflege — was wird automatisch gestylt?

Innerhalb der Beschreibung werden folgende Editor-Elemente **ohne weitere
Klassen** korrekt gestylt:

- Überschriften `h2`–`h6` (das **erste** `h2` wird ausgeblendet, da WC ein
  Duplikat erzeugt — direkt mit `h3`/`h4` arbeiten oder einleitenden Fließtext
  zuerst setzen).
- Absätze `p`
- Listen `ul`, `ol`, `li`
- Links `a` (Hover-Underline, kein Standard-Underline)
- `<strong>`, `<em>`, `<i>`, `<cite>` — **nie kursiv**, einheitlich aufrecht.
- WooCommerce-Attributtabellen (`.shop_attributes` /
  `.woocommerce-product-attributes`).

Nicht automatisch gestylt (bewusst):

- `<blockquote>`, `<figure>`, `<img>` mit Caption — bei Bedarf eigene Regeln in
  `40-blocks/detail-block.css` ergänzen.
- Eingebettete Galerien/Shortcodes — werden vom Editor übernommen.

---

## 8. Anpassungs-Patterns

### 8.1 Eigene Sektion *innerhalb* der Beschreibung hervorheben

```html
<div class="wa-detail-block__panel">
  <h3>Inhaltsstoffe</h3>
  <p>…</p>
  <div class="wa-product-keypoints">
    <div class="wa-product-keypoints__row">
      <span class="wa-product-keypoints__label">pH-Wert</span>
      <span class="wa-product-keypoints__value">5.5</span>
    </div>
  </div>
</div>
```

`.wa-product-keypoints` ist global im `body.wa-single-product`-Scope verfügbar (Definition in `single-product.css`, Block `.wa-product-keypoints` / `__row` / `__label` / `__value`).

### 8.2 Akkordeon-Items in einer Card-Hülle (FAQ-Pattern)

```html
<section class="wa-product-faq">
  <h2 class="wa-section__heading">Häufige Fragen</h2>
  <details class="wa-accordion__item">
    <summary class="wa-accordion__head">…</summary>
    <div class="wa-accordion__body">…</div>
  </details>
</section>
```

`.wa-product-faq` (Definition in `single-product.css`, Block `.wa-product-faq` / `__head` / `__title` / `__description`) hängt sich automatisch eine zarte Hairline an das erste Item.

### 8.3 Eigene Pill-Reihe ergänzen

Im Renderer `single-product-renderer.php` werden die Pills aus `$panels`
generiert. Für zusätzliche Tabs gleiche Klassen `.wa-detail-block__pill` +
`.wa-detail-block__panel` plus `data-target="wa-panel-<id>"` (zeigt auf das
`id`-Attribut des Panels) verwenden. Das JS in `assets/js/detail-block.js`
toggelt anhand `data-target`.

### 8.4 Inline-Anker zur Long Description

Der automatisch gerenderte Read-More-Link (Sektion 5.2) reicht in 99% der Fälle. Wenn doch ein **manueller Inline-Link** im Short-Description-Fließtext nötig ist:

```html
<p>… mehr Details siehe weiter unten oder <a href="#wa-panel-description">hier</a>.</p>
```

Wichtig: Anker **muss** `#wa-panel-description` heißen — der Click-Handler in `detail-block.js` matcht alle `a[href*="#wa-panel-"]` und übernimmt Tab-Aktivierung + Smooth-Scroll mit Sticky-Korrektur. Eine Custom-Klasse ist nicht nötig (und würde keinen Effekt haben — der Match läuft über das `href`-Attribut).

Veraltete Anker wie `#tab-description` (klassischer WC-Default) funktionieren **nicht**, weil unser Custom-Renderer die Default-WC-Tabs durch eigene Panels ersetzt.

---

## 9. Spezifitäts-Hinweise

- Alle Page-Regeln stehen unter `body.wa-single-product …` (Body-Klasse vom
  Renderer gesetzt). Eigene Overrides daher mindestens mit derselben Spezifität
  schreiben.
- Divi-Module-Wrapper (`.et_pb_wc_description`, `.et_pb_wc_tabs`, …) werden auf
  `width: 100% !important` zurückgesetzt — beim Hinzufügen neuer Divi-WC-Module
  ggf. den Reset-Block in `single-product.css` (Header-Kommentar „Reset WP-Container-Optik nur auf Single-Product") erweitern.
- Default WC-Tabs (`.woocommerce-tabs`) sind hart per `display: none` versteckt
  — bei eigener Tab-Lösung Selector entfernen oder eigenen Wrapper benutzen.
