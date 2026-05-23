# Design Rules — Dr. Peri Skincare

Komplette Designsystem-Referenz fuer die Webseite. Tokens, Typografie, Farben, Layout, Komponenten und Buttons. Diese Datei beschreibt die **Sollwerte** — die Implementierung lebt in [`assets/css/00-base/tokens.css`](../assets/css/00-base/tokens.css) und [`assets/css/00-base/style.css`](../assets/css/00-base/style.css).

Skala: Modular Scale 1.250 · `clamp()`-basiert · Fluid 360 → 1280px Viewport · keine Media-Query-Spruenge.

> **Font-Loading:** Montserrat wird vom Divi-Theme als Webseiten-Font geladen. Das Plugin bringt **kein eigenes Font-Setup** mit — `font-family: var(--font-base)` referenziert die Divi-Schrift.

---

## 1. Farb-System

**Ab sofort nur diese 5 Farben** — ueber das ganze System hinweg konsistent. Keine 50/100/300/700/900-Skala mehr. Hierarchie und Kontrast werden ueber **Hintergrund**, **Schatten** und **Typografie** erzeugt, nicht ueber Farb-Abstufungen.

| Token | Hex | Rolle |
|---|---|---|
| `--color-accent` | `#475e76` | **Main accent** — Headings, Body-Text, Buttons, Eyebrows, Links, primary CTAs, Akzent-Rahmen, Placeholder, Hover-Zustaende, Pre-Background |
| `--color-accent-2` | `#769cc1` | **Second accent** — Overlines, Kontrast-Highlights, Hover-Wechsel auf Primary-Buttons |
| `--color-bg-soft` | `#f7f8fa` | **Hintergrund 1** — Tag-Hintergrund, Quote-Card-Hintergrund, Hover-Fill (auf Outline-Buttons), Section-Soft |
| `--color-bg-mid` | `#e5eaee` | **Hintergrund 2** — Borders, Image-Pedestals, Divider, Card-Trennung |
| `--color-bg` | `#ffffff` | **Weiss** — Default-Page-Background, Card-Surface |

```css
--color-accent:     #475e76;
--color-accent-2:   #769cc1;
--color-bg-soft:    #f7f8fa;
--color-bg-mid:     #e5eaee;
--color-bg:         #ffffff;

/* Aliases fuer semantische Lesbarkeit */
--color-text:       var(--color-accent);
--color-heading:    var(--color-accent);
--color-border:     var(--color-bg-mid);

/* Gedimter Text fuer Meta/Captions/Streichpreis: Main mit reduzierter Opacity */
--color-text-muted: rgba(71, 94, 118, 0.6);
```

**WCAG Kontrast (alle gegen `#ffffff`):**

- `#475e76` → 7.7:1 (AAA fuer Body und UI)
- `#769cc1` → 3.5:1 (AA Large Text und UI-Components, **nicht** fuer Body)

**Wichtig:** Second accent `#769cc1` darf **nicht** als Body- oder Lese-Text auf Weiss verwendet werden. Er ist fuer Akzent-Spots reserviert (Overlines, Hover-Zustaende, Decorative Highlights).

---

## 2. Fluide Typografie-Skala

Alle Stufen skalieren fluide zwischen 360px (Mobile) und 1280px (Desktop) via `clamp()`.

| Token | Klein | Gross | Weight | Line-Height | Verwendung |
|---|---|---|---|---|---|
| `--fs-display` | 40px | 80px | 800 | 1.1 | Hero-Display (nur fuer das markante Display-Statement) |
| `--fs-h1` | 32px | 48px | 700 | 1.15 | Page-Title, Section-Hero |
| `--fs-h2` | 26px | 36px | 700 | 1.2 | Sub-Section-Headlines |
| `--fs-h3` | 22px | 28px | 600 | 1.3 | Block-Headlines, Featured-Sektionen |
| `--fs-h4` | 19px | 22px | 600 | 1.35 | Subheads |
| `--fs-h5` | 18px | 20px | 600 | 1.4 | Card-Headlines |
| `--fs-h6` | 16px | 18px | 600 | 1.45 | Sidebar-Titel, Form-Section |
| `--fs-lead` | 17px | 20px | 400 | 1.6 | Lead-Texte, Intros |
| `--fs-body` | 15px | 16px | 400 | 1.75 | Standard-Fliesstext |
| `--fs-small` | 13px | 14px | 400 | 1.6 | Meta, Datum, Autor |
| `--fs-caption` | 11px | 12px | 500 | 1.5 | Quellenangaben |
| `--fs-overline` | 12px | 14px | 600 | 1.5 | Eyebrows, Section-Labels (uppercase, letter-spacing 0.12em) |

**Color-Regel fuer Headings:** alle H1-H6 in `--color-accent` (Main). Keine Hierarchie ueber Farbabstufungen — die Hierarchie kommt aus `font-size`, `font-weight` und `line-height`.

```css
--fs-display:  clamp(2.5rem,    1.5rem  + 4.35vw, 5rem);
--fs-h1:       clamp(2rem,      1.3rem  + 3.04vw, 3rem);
--fs-h2:       clamp(1.625rem,  1.13rem + 2.17vw, 2.25rem);
--fs-h3:       clamp(1.375rem,  1.05rem + 1.41vw, 1.75rem);
--fs-h4:       clamp(1.1875rem, 0.99rem + 0.87vw, 1.375rem);
--fs-h5:       clamp(1.125rem,  1rem    + 0.54vw, 1.25rem);
--fs-h6:       clamp(1rem,      0.92rem + 0.33vw, 1.125rem);
--fs-lead:     clamp(1.0625rem, 0.92rem + 0.65vw, 1.25rem);
--fs-body:     clamp(0.9375rem, 0.88rem + 0.27vw, 1rem);
--fs-small:    clamp(0.8125rem, 0.79rem + 0.22vw, 0.875rem);
--fs-caption:  clamp(0.6875rem, 0.66rem + 0.22vw, 0.75rem);
--fs-overline: clamp(0.75rem,   0.68rem + 0.33vw, 0.875rem);
```

---

## 3. Schnittgewichte

Es werden **nur 4 Weights** geladen: 400, 500, 600, 700. Das deckt 95% der Anwendungsfaelle ab und spart ~60% Ladezeit gegenueber dem vollen Set. 300, 800, 900 nur fuer Spezial-Faelle (Hero-Display, Logo).

| Weight | Name | Wann |
|---|---|---|
| 300 | Light | Nur ab 32px+. Unter 24px wirkt Light auf Bildschirmen ausgewaschen. |
| 400 | Regular | Standardfliesstext, Absaetze, Lead-Texte. Das Arbeitstier — 90% des Body-Texts. |
| 500 | Medium | Hervorhebungen, Buttons-Labels, Navigation. Subtile Betonung im Fliesstext. |
| 600 | SemiBold | Subheadings, Card-Titel, H3-H6. Klare Hierarchie ohne Wucht. |
| 700 | Bold | Hauptueberschriften H1, H2, starke CTAs. Reserviert fuer die wichtigsten visuellen Anker. |
| 800-900 | Extra/Black | Nur fuer Hero-Display und Logo. Sparsam, sonst Werbeplakat-Effekt. Nie unter 36px. |

---

## 4. Komponenten-Skala

Komponenten in Grids brauchen eigene Groessen — sie muessen sich der Grid-Optik unterordnen, nicht wie eine H3 wirken.

| Komponente | Token | Klein | Gross | Weight | Color | Begruendung |
|---|---|---|---|---|---|---|
| Blog-Card-Titel | `--fs-card-title` | 18px | 21px | 600 | `--color-accent` | Gross genug zum Scannen, klein genug fuer Grid-Rhythmus |
| Service-/Feature-Box | `--fs-card-title` | 18px | 21px | 600 | `--color-accent` | Gleiches Gewicht wie Blog — visuelle Konsistenz |
| Produktname im Shop | `--fs-product-title` | 16px | 17px | 500 | `--color-accent` | Bewusst zurueckgenommen — Bild ist der Held |
| Sidebar-/Tile-Titel | `--fs-tile-title` | 15px | 15.5px | 500 | `--color-accent` | Listenstil, viele Items uebereinander |
| Preis (primary) | `--fs-price` | 20px | 23px | 700 | `--color-accent` | Conversion-Aktion — muss auffallen |
| Streichpreis | `--fs-price-old` | 14px | 15px | 400 | `--color-text-muted` | Sekundaer, line-through |
| Meta (Datum, Autor) | `--fs-meta` | 12px | 13px | 400 | `--color-text-muted` | Bewusst klein und gedimt |
| Tags / Badges | `--fs-tag` | 11px | 12px | 600 | `--color-accent` auf `--color-bg-soft` | letter-spacing 0.04em, Padding 4px 10px, Pill-Radius |

```css
--fs-card-title:    clamp(1.125rem,  0.97rem + 0.65vw, 1.3125rem);
--fs-product-title: clamp(1rem,      0.94rem + 0.27vw, 1.0625rem);
--fs-tile-title:    clamp(0.9375rem, 0.91rem + 0.13vw, 0.96875rem);
--fs-price:         clamp(1.25rem,   1.1rem  + 0.65vw, 1.4375rem);
--fs-price-old:     clamp(0.875rem,  0.85rem + 0.11vw, 0.9375rem);
--fs-meta:          clamp(0.75rem,   0.73rem + 0.11vw, 0.8125rem);
--fs-tag:           clamp(0.6875rem, 0.66rem + 0.13vw, 0.75rem);
```

---

## 5. Layout-Standards (Section + Row)

Jede Page besteht aus **Sections** (vertikale Bloecke mit eigenem Hintergrund) und **Rows** (zentrierte Inhalts-Container innerhalb der Section). Das Spacing-System ist fest definiert — nicht pro Section variieren.

### Section

Aeusserer Wrapper. Steuert das **vertikale Atmen** (top/bottom Padding) und das **horizontale Gutter** auf Mobile/Tablet. Hintergrund (Weiss / `--color-bg-soft` / `--color-bg-mid`) wird hier gesetzt.

| Viewport | Top/Bottom Padding | Left/Right Padding |
|---|---|---|
| `>= 981px` (Desktop) | `clamp(3rem, 10vw, 7rem)` | `0` |
| `<= 980px` (Tablet) | `clamp(3rem, 10vw, 7rem)` | `2.5rem` |
| `<= 767px` (Mobile) | `clamp(3rem, 10vw, 7rem)` | `1.5rem` |

**Top/Bottom Padding** ist konstant ueber alle Viewports — nur der `clamp()`-Wert skaliert (48px Mobile → 112px Desktop). Das sorgt fuer ruhigen, lesbaren vertikalen Rhythmus.

**Left/Right Padding** existiert nur unterhalb 981px. Auf Desktop uebernimmt das die Row (zentriert mit `width: 80%`).

### Row

Innerer Inhalts-Container. Zentriert via `margin: 0 auto`, gedeckelt durch `max-width`. Auf Desktop ist die Row schmaler als die Section (80% Breite) — das gibt automatisch luftige Seiten-Margins. Auf Tablet/Mobile fluten Rows in voller Breite, weil dort die Section selbst das Gutter macht.

| Viewport | Width | Max-Width |
|---|---|---|
| `>= 981px` (Desktop) | `80%` | `1400px` |
| `<= 980px` (Tablet) | `100%` | `1400px` |
| `<= 767px` (Mobile) | `100%` | `1400px` |

**Abstand Row zu Row** (vertikales Spacing zwischen mehreren Rows in derselben Section): `clamp(4rem, 8vw, 6rem)` (= 64px Mobile → 96px Desktop).

### CSS-Implementation

```css
/* Section ----------------------------------------------------- */
.wa-section {
    padding: clamp(3rem, 10vw, 7rem) 0;
}
@media (max-width: 980px) {
    .wa-section { padding: clamp(3rem, 10vw, 7rem) 2.5rem; }
}
@media (max-width: 767px) {
    .wa-section { padding: clamp(3rem, 10vw, 7rem) 1.5rem; }
}

/* Section-Hintergrund-Varianten */
.wa-section--soft  { background: var(--color-bg-soft); }
.wa-section--mid   { background: var(--color-bg-mid); }
.wa-section--white { background: var(--color-bg); }

/* Row --------------------------------------------------------- */
.wa-row {
    width: 80%;
    max-width: 1400px;
    margin: 0 auto;
}
@media (max-width: 980px) {
    .wa-row { width: 100%; }
}

/* Mehrere Rows in einer Section: vertikales Spacing zwischen ihnen */
.wa-row + .wa-row {
    margin-top: clamp(4rem, 8vw, 6rem);
}
```

### Markup-Pattern

```html
<section class="wa-section wa-section--white">
    <div class="wa-row">
        <h2>Erste Row</h2>
        <p>Inhalt der ersten Row.</p>
    </div>
    <div class="wa-row">
        <!-- Automatischer Abstand zur ersten Row via clamp(4rem, 8vw, 6rem) -->
        <h2>Zweite Row</h2>
        <p>Inhalt der zweiten Row.</p>
    </div>
</section>
```

### Mapping zu Divi

In Divi entspricht das:

- `.wa-section` ↔ Divi-Section (`.et_pb_section`)
- `.wa-row` ↔ Divi-Row (`.et_pb_row`)

Die WC-Layout-Shell in [`assets/css/10-layout/wc-shell.css`](../assets/css/10-layout/wc-shell.css) erzwingt diese Werte bereits fuer alle WC-Pages auf `body.wa-woocommerce .et_pb_section` und `body.wa-woocommerce .et_pb_row`. Bei eigenständigen Code-Modulen die `.wa-section` / `.wa-row`-Klassen verwenden.

---

## 6. Akzent-Verwendung in der Praxis

### Main accent `#475e76`

Die durchgehende Marken-Farbe. Verwendet fuer **alles Strukturelle und Lesbare**:

- **Headings** H1-H6 (alle gleichfarbig)
- **Body-Text** (Standard `--fs-body`)
- **Lead-Texte** (`--fs-lead`)
- **Eyebrows / Section-Labels** — `--fs-overline`, weight 600, uppercase, letter-spacing 0.12em
- **Links** — weight 500, text-underline-offset 3px
- **Primary CTAs** — Akzent-Hintergrund mit weissem Text (siehe Buttons)
- **Border-Highlights** — Quote-Card-Border-Left, Card-Active-States
- **Placeholder** — Empty-State-Grafik-Icons, Empty-Text
- **Pre / Code-Blocks** — als Hintergrund (mit hellem Text)

### Second accent `#769cc1`

Bewusst sparsam fuer **Kontrast-Akzente** — nicht fuer lesbaren Text auf Weiss.

- **Overlines** — extra-kleine Pre-Headings ueber besonderen Sections
- **Primary-Button-Hover** — visueller Wechsel zur kontrastreichen Variante
- **Decorative Highlights** — z. B. eine markante Linie unter einer Hero-H1
- **Active-States** in Tabs / Filter-Pills
- **Icon-Tints** auf Trust-Badges, Feature-Indikatoren

### Hintergruende

- `--color-bg` (`#ffffff`) — Default fuer Pages und Cards
- `--color-bg-soft` (`#f7f8fa`) — Tag-Hintergrund, Quote-Cards, Outline-Button-Hover, Section-Soft
- `--color-bg-mid` (`#e5eaee`) — Borders, Divider, Image-Pedestals, Card-Trennung

**Regel:** Second accent niemals fuer Fliesstext. Main accent niemals fuer Hintergruende. Alle anderen Farb-Abstufungen sind ab sofort tabu — Hierarchie kommt aus Hintergrund + Typografie.

---

## 7. Buttons

Drei Button-Varianten. Alle teilen sich denselben Padding-Rhythmus (`.9em 2em`), Radius (`--radius-sm`), Font-Spec (weight 600, letter-spacing 0.05em, uppercase) und Transition. Sie unterscheiden sich nur in Background, Text-Color und Border.

Implementierung greift auf alle Divi-, WordPress- und WooCommerce-Button-Selektoren (`.et_pb_button`, `.woocommerce a.button`, `.wc-block-components-button`, etc.) — siehe [`assets/css/00-base/style.css`](../assets/css/00-base/style.css), Sektion 4 ("Globale Buttons") und 5 ("Button-Varianten").

### 7.1 Schriftgroesse — fix in Pixel

**Bewusst nicht ueber `--fs-small` getokent**, weil Divi Visual Builder pro Button-Modul Inline-Regeln mit hoher Specificity setzt (`body #page-container .et_pb_section .et_pb_button_0 { font-size: 16px !important }`). Wir matchen dieselbe Spezifitaet via Specificity-Booster (siehe 7.5) und setzen feste Px-Werte:

| Viewport | Schriftgroesse |
|---|---|
| Desktop (Default) | `16px` |
| `<= 980px` (Tablet) | `15px` |
| `<= 767px` (Mobile) | `14px` |

Gilt **identisch** fuer Primary, Outline (`.second-btn`) und White-Outline (`.third-btn`) — alle drei rendern visuell auf derselben Groesse.

### 7.2 Primary (Default)

Gefuellter Main-Accent-Button mit weissem Text. Beim Hover wechselt der Hintergrund auf den **Second accent** — der einzige Punkt, wo `#769cc1` als Fuellung erscheint.

```css
padding: .9em 2em;
font-size: 16px;          /* 980/767px → 15/14px */
font-weight: 600;
letter-spacing: 0.05em;
text-transform: uppercase;
color: #ffffff;
background: var(--color-accent);
border: 2px solid var(--color-accent);
border-radius: var(--radius-sm);
box-shadow: var(--shadow-cta);

/* Hover */
background: var(--color-accent-2);
border-color: var(--color-accent-2);
box-shadow: var(--shadow-cta-hover);
```

### 7.3 Outline `.second-btn`

Transparent mit Main-Accent-Rahmen. Beim Hover fuellt sich der Hintergrund mit `--color-bg-soft`, Text und Rahmen bleiben Main.

```css
.et_pb_button.second-btn {
    padding: .9em 2em;
    font-size: 16px;       /* 980/767px → 15/14px */
    background: transparent;
    color: var(--color-accent);
    border: 2px solid var(--color-accent);
    box-shadow: none;
}

.et_pb_button.second-btn:hover {
    background: var(--color-bg-soft);
    color: var(--color-accent);
    border-color: var(--color-accent);
}
```

### 7.4 White-Outline `.third-btn`

Gedacht fuer Buttons auf **dunklen Section-Hintergruenden**. Komplett weiss (Text + Rahmen), transparenter Hintergrund.

```css
.et_pb_button.third-btn {
    padding: .9em 2em;
    font-size: 16px;       /* 980/767px → 15/14px */
    background: transparent;
    color: #ffffff;
    border: 2px solid #ffffff;
    box-shadow: none;
}

.et_pb_button.third-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 14px rgba(255, 255, 255, 0.08);
}
```

### 7.5 Specificity-Booster

Am Ende von `style.css` (Sektion 6) liegt ein dedizierter Block, der **nur `font-size`** mit `body #page-container`-Prefix setzt — Specificity 0,1,4,2 schlaegt Divis Modul-spezifische Regeln (0,1,2,1) zuverlaessig. Alle anderen Properties kommen weiterhin aus den Hauptregeln.

Wenn ein neuer Button-Selektor hinzukommt (z. B. ein WC-Block-Button-Typ), muss er sowohl in der Hauptregel (Sektion 4) als auch im Booster (Sektion 6) erfasst werden — sonst springen die Schriftgroessen bei Divi-VB-Module-IDs.

### 7.6 Verwendungsregel

| Variante | Wann |
|---|---|
| **Primary** | Haupt-Conversion-Aktion auf der Section (z. B. „In den Warenkorb", „Termin buchen", „Mehr erfahren") |
| **Outline** | Sekundaere Aktion neben einem Primary auf hellem Hintergrund (z. B. „Mehr Infos") |
| **White-Outline** | Auf dunklen Hero-Sections oder Banner-Backgrounds — sonst nicht lesbar |

**Pro Section maximal eine Primary** — sonst verliert sie ihre Wirkung.

---

## 8. Best Practices

### Do

- Body in **400**, max-width **65ch**, line-height **>= 1.6**
- Main accent `#475e76` fuer **alles Lesbare** (Body, Headings, Links, CTAs)
- Second accent `#769cc1` **nur** fuer Overlines, Hover-Wechsel und Decorative Highlights
- **`clamp()`** fuer fluide Skalierung — keine harten Breakpoint-Spruenge
- Hierarchie ueber **Hintergrund + Typografie** schaffen, nicht ueber Farb-Abstufungen
- Sections und Rows immer mit den standardisierten Spacing-Werten — nicht ad-hoc anpassen
- Pro Section maximal **eine** Primary-Action

### Don't

- Body in 300 unter 18px — wirkt ausgewaschen
- ALL CAPS in langen Saetzen — schlechte Lesbarkeit
- Mehr als 3 Schnitte parallel auf einer Page
- **Second accent fuer Body-Text** — Kontrast nur 3.5:1, faellt durch WCAG
- Eigene Akzent-Abstufungen erfinden (`--color-accent-700`, `--color-accent-300`, ...) — Palette ist auf 5 Farben fixiert
- Reines `#000000` fuer Fliesstext — wirkt zu hart, ermuedet die Augen
- Eigenes Section/Row-Spacing pro Page — Layout-Standards (Section 5) sind fix
- Feste px-Werte mit @media-Queries stapeln — erzeugt sichtbare Spruenge

---

## 9. CSS-Setup (Copy & Paste)

Das vollstaendige Token-Setup laueft bereits ueber [`assets/css/00-base/tokens.css`](../assets/css/00-base/tokens.css). Wer das System extern nachbauen will, hier die Boilerplate.

> **Font:** Montserrat wird **nicht** vom Plugin geladen. Auf der Dr.-Peri-Site uebernimmt das Divi-Theme die Schrift-Einbindung. Bei externer Nutzung selbst per `<link>` oder `@font-face` einbinden — z. B. `family=Montserrat:wght@400;500;600;700&display=swap`.

```css
:root {
  --font-base: 'Montserrat', system-ui, -apple-system, sans-serif;

  /* 5-Farben-System */
  --color-accent:       #475e76;
  --color-accent-2:     #769cc1;
  --color-bg-soft:      #f7f8fa;
  --color-bg-mid:       #e5eaee;
  --color-bg:           #ffffff;

  /* Semantische Aliases */
  --color-text:         var(--color-accent);
  --color-heading:      var(--color-accent);
  --color-border:       var(--color-bg-mid);
  --color-text-muted:   rgba(71, 94, 118, 0.6);

  /* Fluid Type Scale */
  --fs-display:  clamp(2.5rem,    1.5rem  + 4.35vw, 5rem);
  --fs-h1:       clamp(2rem,      1.3rem  + 3.04vw, 3rem);
  --fs-h2:       clamp(1.625rem,  1.13rem + 2.17vw, 2.25rem);
  --fs-h3:       clamp(1.375rem,  1.05rem + 1.41vw, 1.75rem);
  --fs-h4:       clamp(1.1875rem, 0.99rem + 0.87vw, 1.375rem);
  --fs-h5:       clamp(1.125rem,  1rem    + 0.54vw, 1.25rem);
  --fs-h6:       clamp(1rem,      0.92rem + 0.33vw, 1.125rem);
  --fs-lead:     clamp(1.0625rem, 0.92rem + 0.65vw, 1.25rem);
  --fs-body:     clamp(0.9375rem, 0.88rem + 0.27vw, 1rem);
  --fs-small:    clamp(0.8125rem, 0.79rem + 0.22vw, 0.875rem);
  --fs-caption:  clamp(0.6875rem, 0.66rem + 0.22vw, 0.75rem);
  --fs-overline: clamp(0.75rem,   0.68rem + 0.33vw, 0.875rem);

  /* Komponenten-Skala */
  --fs-card-title:    clamp(1.125rem,  0.97rem + 0.65vw, 1.3125rem);
  --fs-product-title: clamp(1rem,      0.94rem + 0.27vw, 1.0625rem);
  --fs-tile-title:    clamp(0.9375rem, 0.91rem + 0.13vw, 0.96875rem);
  --fs-price:         clamp(1.25rem,   1.1rem  + 0.65vw, 1.4375rem);
  --fs-price-old:     clamp(0.875rem,  0.85rem + 0.11vw, 0.9375rem);
  --fs-meta:          clamp(0.75rem,   0.73rem + 0.11vw, 0.8125rem);
  --fs-tag:           clamp(0.6875rem, 0.66rem + 0.13vw, 0.75rem);
}

/* Base Styles */
html { font-size: 100%; }
body {
  font-family: var(--font-base);
  font-size: var(--fs-body);
  line-height: 1.75;
  color: var(--color-text);
  background: var(--color-bg);
  font-weight: 400;
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
}

/* Headings — alle in Main accent */
h1 { font-size: var(--fs-h1); line-height: 1.15; font-weight: 700; letter-spacing: -0.02em;  color: var(--color-accent); }
h2 { font-size: var(--fs-h2); line-height: 1.2;  font-weight: 700; letter-spacing: -0.015em; color: var(--color-accent); }
h3 { font-size: var(--fs-h3); line-height: 1.3;  font-weight: 600; letter-spacing: -0.01em;  color: var(--color-accent); }
h4 { font-size: var(--fs-h4); line-height: 1.35; font-weight: 600; color: var(--color-accent); }
h5 { font-size: var(--fs-h5); line-height: 1.4;  font-weight: 600; color: var(--color-accent); }
h6 { font-size: var(--fs-h6); line-height: 1.45; font-weight: 600; color: var(--color-accent); }

/* Text-Elemente */
p     { font-size: var(--fs-body); max-width: 65ch; margin-bottom: 1.25rem; }
a     { color: var(--color-accent); font-weight: 500; text-underline-offset: 3px; }
small { font-size: var(--fs-small); color: var(--color-text-muted); }
strong { font-weight: 600; }

/* Layout: Section + Row */
.wa-section { padding: clamp(3rem, 10vw, 7rem) 0; }
@media (max-width: 980px) { .wa-section { padding: clamp(3rem, 10vw, 7rem) 2.5rem; } }
@media (max-width: 767px) { .wa-section { padding: clamp(3rem, 10vw, 7rem) 1.5rem; } }
.wa-section--soft  { background: var(--color-bg-soft); }
.wa-section--mid   { background: var(--color-bg-mid); }
.wa-section--white { background: var(--color-bg); }

.wa-row { width: 80%; max-width: 1400px; margin: 0 auto; }
@media (max-width: 980px) { .wa-row { width: 100%; } }
.wa-row + .wa-row { margin-top: clamp(4rem, 8vw, 6rem); }

/* Utility-Klassen */
.display  { font-size: var(--fs-display); font-weight: 800; line-height: 1.1; letter-spacing: -0.025em; color: var(--color-accent); }
.lead     { font-size: var(--fs-lead); line-height: 1.6; font-weight: 400; color: var(--color-accent); }
.eyebrow,
.section-label {
  font-size: var(--fs-overline); font-weight: 600;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--color-accent);
}
.overline {
  font-size: var(--fs-overline); font-weight: 600;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--color-accent-2);  /* Second accent fuer Kontrast */
}
.caption  { font-size: var(--fs-caption); font-weight: 500; color: var(--color-text-muted); }
.tag, .badge {
  display: inline-block;
  font-size: var(--fs-tag); font-weight: 600;
  letter-spacing: 0.04em; text-transform: uppercase;
  color: var(--color-accent);
  background: var(--color-bg-soft);
  padding: 4px 10px;
  border-radius: 999px;
}

/* Quote-Card */
.quote-card {
  background: var(--color-bg-soft);
  border-left: 3px solid var(--color-accent);
  border-radius: 4px;
  padding: 1.5rem 1.75rem;
}
.quote-card .quote {
  font-size: var(--fs-lead);
  line-height: 1.7;
  color: var(--color-accent);
  font-style: italic;
}
.quote-card .attribution {
  font-size: var(--fs-small);
  color: var(--color-accent);
  font-weight: 500;
  margin-top: 0.75rem;
}
```

Buttons sind oben in Abschnitt 7 separat dokumentiert — sie sind im Plugin auf Divi-/WC-Selektoren gemappt, nicht auf eine generische `.btn`-Klasse.

---

## clamp() erklaert

Die Formel `clamp(MIN, PREFERRED, MAX)` nimmt den mittleren, viewport-basierten Wert — gedeckelt durch Min und Max.

Beispiel `--fs-h1` = `clamp(2rem, 1.3rem + 3.04vw, 3rem)`:
- Bei 360px Viewport: `2rem` (32px) — Min greift
- Bei 1280px Viewport: `3rem` (48px) — Max greift
- Dazwischen: lineare Interpolation auf Basis von `1.3rem + 3.04vw`

Vorteil: keine Media-Queries, keine sichtbaren Spruenge bei Groessenwechseln.

---

## Implementation Note

Implementation in [`assets/css/00-base/tokens.css`](../assets/css/00-base/tokens.css), [`assets/css/00-base/style.css`](../assets/css/00-base/style.css), [`assets/css/10-layout/wc-shell.css`](../assets/css/10-layout/wc-shell.css), [`assets/css/10-layout/content-shell.css`](../assets/css/10-layout/content-shell.css) und allen nachgelagerten Stylesheets ist konform mit dieser Doku.

---

**Visuelle Referenz:** [`docs/style-guide.html`](style-guide.html) — Live-Preview im Browser. Achtung: zeigt noch die historische Palette und ist nicht mehr aktuell.
