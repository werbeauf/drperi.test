# Footer

Custom Footer fuer die Dr.-Peri-Site. Loest den Default-Divi-Footer ab, sobald kein Theme-Builder-Footer aktiv ist. Liefert Brand, Kontakt, Newsletter, Oeffnungszeiten, Latest-Posts und zwei Footer-Menues auf einer Dark-Surface (Main accent `#475e76`).

---

## Was wo lebt

| Zweck | Datei |
|---|---|
| Controller (Mode-Decision + Inject) | [`includes/layout/footer-controller.php`](../includes/layout/footer-controller.php) |
| Markup-Template | [`templates/footer.php`](../templates/footer.php) |
| ACF-Field-Group (Footer-Inhalte) | [`includes/acf/footer-fields.php`](../includes/acf/footer-fields.php) |
| Zweite Nav-Location `footer-menu-2` | [`includes/core/footer-menus.php`](../includes/core/footer-menus.php) |
| CSS | [`assets/css/10-layout/footer.css`](../assets/css/10-layout/footer.css) |
| Newsletter-Form (Shortcode) | [`includes/shortcodes/newsletter-form.php`](../includes/shortcodes/newsletter-form.php) + [`assets/css/40-blocks/newsletter.css`](../assets/css/40-blocks/newsletter.css) |
| CSS-Enqueue | [`includes/core/enqueue.php`](../includes/core/enqueue.php) — Block `if ( $wa_show_fallback_footer )` |

---

## Controller-Logik

`includes/layout/footer-controller.php`

1. `wa_compute_footer_mode()` (Hook `wp` Prio 1): wenn Divi Theme Builder ein eigenes Footer-Layout overridet (`et_theme_builder_overrides_layout('et_footer_layout')`), wird `$wa_show_fallback_footer = false` gesetzt — Divi rendert. Sonst `true` -> Custom-Footer kommt.
2. Body-Class `werbeauf-footer-active` wird gesetzt, wenn der Custom-Footer kommt. Der Inline-`<style>` im `wp_head` blendet Divis Default-Footer-Markup (`#main-footer`, `#footer-bottom` etc.) per `display: none !important` aus.
3. Auf `wp_footer` Prio 5 wird `templates/footer.php` includiert.

`includes/core/enqueue.php` enqueued `wa-footer` und (vorab fuer den `<head>`) `wa-newsletter` nur wenn `$wa_show_fallback_footer` gesetzt ist — das Newsletter-Stylesheet muss vorab in den Head, weil der Shortcode erst spaet (`wp_footer` Prio 5) im Template laeuft.

---

## Layout — zwei Zeilen + Bottom-Stack

```
┌─────────────────────────────────────────────────────────────────────┐
│  ROW 1  (align-items: start, padding-bottom + border-bottom)        │
│  ┌──────────────┬──────────────┬───────────────────────────────┐    │
│  │ 1/4          │ 1/4          │ 2/4                           │    │
│  │ BRAND        │ NEWSLETTER   │ NEWSLETTER FORM               │    │
│  │ - Logo       │ heading +    │ (.wa-newsletter--footer)      │    │
│  │ - Address    │ intro lead   │ Vorname / Nachname / Email /  │    │
│  │ - Email      │              │ Consent / Submit              │    │
│  │ - Phone      │              │                               │    │
│  │ - Socials    │              │                               │    │
│  └──────────────┴──────────────┴───────────────────────────────┘    │
│                                                                      │
│  ROW 2  (align-items: start)                                         │
│  ┌──────────┬──────────────────┬──────────────┬──────────────┐       │
│  │ 1/4      │ 1/4              │ 1/4          │ 1/4          │       │
│  │ HOURS    │ AKTUELLE         │ FOOTER       │ FOOTER       │       │
│  │ Mo - Sa  │ BEITRAEGE        │ MENU 2       │ MENU 3       │       │
│  │ als <dl> │ 3 latest blog    │ (theme_loc:  │ (theme_loc:  │       │
│  │          │ posts            │  footer-     │  footer-     │       │
│  │          │                  │  menu-2)     │  menu-3)     │       │
│  └──────────┴──────────────────┴──────────────┴──────────────┘       │
│                                                                      │
│        ────── divider ──────                                         │
│   Impressum · Datenschutz · AGB   (legal-menu, theme_loc footer-menu) │
│        © {year} {ACF copyright_text}                                  │
└─────────────────────────────────────────────────────────────────────┘
```

`#wa-footer` ist Full-Bleed-Chrome — Container `width: 100%; max-width: 1400px` (nicht `.wa-row` 80%). Padding `clamp(3rem, 6vw, 5rem) 0` symmetrisch (chrome-tighter als DESIGN.md §5 Content-Section-Spec — bewusste Exception, dokumentiert in `footer.css` §1).

---

## ACF-Field-Group `group_wa_footer`

Registriert via `acf_add_local_field_group()` auf `acf/init`, Location `options_page == 'dr-peri'`. Top-Level-Group-Field name=`footer`, key=`field_wa_footer`. Sub-Felder:

| name | type | Verwendung |
|---|---|---|
| `address` | textarea (HTML erlaubt) | Adresszeile, oft mit Maps-Link |
| `email` | email | mailto-Link |
| `phone` | text | tel-Link, `preg_replace('/[^+0-9]/','', …)` fuer href |
| `opening_hours` | repeater (`day` + `hours`) | Mo–Sa prefilled, max 10 Zeilen |
| `newsletter_heading` | text | H4 ueber dem Form |
| `newsletter_intro` | text | Lead-Zeile unter der Headline |
| `social_links` | repeater (`platform` select + `url`) | Inline-SVG-Icons aus `wa_footer_social_icon()` |
| `copyright_text` | text | Render: `© {year} {copyright_text}` |

`wa_get_footer_field( $key )` (im Template definiert) macht den WPML-Fallback: `options_{ICL_LANGUAGE_CODE}` -> `options_{default_lang}` -> plain `option`. Lesen aller Felder geht ueber diesen Helper.

---

## Menue-Locations

Drei Locations im Footer:

| Location | Quelle | Wo gerendert |
|---|---|---|
| `footer-menu` | Divi-Parent (`themes/Divi/functions.php`) | Bottom-Strip `.wa-footer-legal` (zwischen Divider und Copyright). Horizontal mit Pipe-Trennern via `.legal-menu`-Stil. Typisch: Impressum / Datenschutz / AGB. |
| `footer-menu-2` | Plugin (`includes/core/footer-menus.php`) | Row 2 — Spalte 3. Spaltentitel = WP-Admin-Menue-Name. |
| `footer-menu-3` | Plugin (`includes/core/footer-menus.php`) | Row 2 — Spalte 4. Spaltentitel = WP-Admin-Menue-Name. |

`includes/core/footer-menus.php` registriert `footer-menu-2` + `footer-menu-3` auf `after_setup_theme` Prio 20 (laeuft nach Divis Prio-10-Default-Registrierung). Spaltentitel werden via `wp_get_nav_menu_object( $location_id )->name` geholt — entspricht dem unter Appearance → Menus vergebenen Namen.

`Divi-child/functions.php` ist per Konvention tabu — Menue-Registrierung muss im Plugin liegen.

---

## Newsletter-Integration

Im Template:

```php
<?php echo do_shortcode( '[wa_newsletter_signup variant="footer"]' ); ?>
```

Variante `footer` versteckt In-Form-Title/Lead (die kommen extern aus ACF), kompaktes Layout.

Submit-Button = White-Outline-Stil (DESIGN.md §7.4 `.third-btn`), implementiert in `assets/css/40-blocks/newsletter.css` mit `!important` (globale Button-Regeln aus `00-base/style.css` haben hohe Specificity).

---

## Latest-Posts-Spalte

`get_posts()` in `templates/footer.php`, `post_type=blog`, `posts_per_page=3`, `orderby=date DESC`. Spalte rendert nicht, wenn keine Posts publiziert sind.

---

## Dark-Surface-Design

Footer steht auf Main accent `#475e76`. DESIGN.md §1 ist Light-First — die Inversion lebt als lokale Aliase im `#wa-footer`-Scope:

```css
#wa-footer {
    --waf-text:        #ffffff;
    --waf-text-soft:   rgba(255, 255, 255, 0.72);
    --waf-text-mute:   rgba(255, 255, 255, 0.55);
    --waf-border:      rgba(255, 255, 255, 0.18);
    --waf-border-soft: rgba(255, 255, 255, 0.10);
    --waf-hover-bg:    rgba(255, 255, 255, 0.08);
}
```

Bewusst **lokal** (nicht in `00-base/tokens.css`) — andere Surfaces brauchen sie nicht.

Overlines (`.wa-footer-coltitle`) auf Dark bleiben in `--waf-text-soft` (nicht `--color-accent-2`): `#769cc1` auf `#475e76` faellt durch WCAG (~2:1 Kontrast). DESIGN.md §1 verbietet accent-2 fuer Body-Text — hier analog auch fuer Labels.

---

## Typografie — Tokens aus DESIGN.md

| Element | Token | Quelle |
|---|---|---|
| Logo-Text-Fallback | `--fs-h4`, weight **700** | Brand-Exception (DESIGN.md §3 sagt h4 = 600) |
| Newsletter-Headline | `--fs-h4`, weight 600, line-height 1.35 | DESIGN.md §9 |
| Newsletter-Intro | `--fs-lead`, weight 400, line-height 1.6 | DESIGN.md §9 `.lead` |
| Spalten-Titel | `--fs-overline`, weight 600, letter-spacing 0.12em, uppercase | DESIGN.md §9 `.overline` |
| Adresse / Email / Telefon | `--fs-small`, line-height 1.6 | Chrome-Density-Exception — DESIGN.md §2 stellt `--fs-small` sonst als Meta-Tier ab |
| Oeffnungszeiten | `--fs-small`, tabular-nums fuer Zeit | s.o. |
| Post-Titel | `--fs-tile-title`, weight 500 | DESIGN.md §4 (Sidebar/Tile-Titel) |
| Post-Datum | `--fs-meta`, weight 400, **kein** uppercase | DESIGN.md §4 (Meta) |
| Menue-Links | `--fs-small`, weight 500 | passt zur Tile-Density |
| Copyright | `--fs-meta`, weight 400 | DESIGN.md §4 (Meta) |

---

## Kontakt-Icons

`wa_footer_contact_icon( $type )` im Template liefert 14px-SVGs (Lucide-Style, stroke=currentColor) fuer `address` (Pin), `email` (Envelope), `phone` (Handset). Eingebunden links jeder Kontakt-Zeile via `display: flex; align-items: flex-start; gap: 0.6em;` mit `margin-top: 0.25em` auf dem SVG fuer Baseline-Angleichung an die erste Textzeile.

---

## Responsive Breakpoints

| Breakpoint | Effekt |
|---|---|
| `<= 1100px` | Row 1 → `1fr 1fr 1.5fr` (Form schmaler). Row 2 → 2x2-Grid. `.wa-footer-hours` schrumpft auf `width: max-content` (organisch statt full-stretch). |
| `<= 980px` | Container-Padding `0 2.5rem` (DESIGN.md §5 Tablet). Row-Gap und Container-Gap groesser (`clamp(2.25rem, 4vw, 3rem)`). Row 1 → `1fr 1fr` mit Brand full-width oben. Brand-Col-Kinder verlieren ihre `max-width`. |
| `<= 767px` | Beide Rows → 1 Spalte. Container-Padding `0 1.5rem`. `.wa-footer-col` `align-items: stretch` (statt `flex-start`) — alle Inhalte volle Mobile-Breite. Logo behaelt `align-self: flex-start`. |

---

## CSS-Layer-Konvention

Footer-CSS lebt in `10-layout/` (Page-Shells). Newsletter-Form-CSS in `40-blocks/`. Beide werden ueber `includes/core/enqueue.php` enqueued; Versionen sind dort gepflegt.

| Handle | Datei | Aktuelle Version |
|---|---|---|
| `wa-footer` | `assets/css/10-layout/footer.css` | siehe `enqueue.php` |
| `wa-newsletter` | `assets/css/40-blocks/newsletter.css` | siehe `newsletter-form.php` |

Bei jeder relevanten Aenderung Version bumpen, sonst greift Super-Cache und liefert alte Datei.

---

## Bekannte Exceptions / Trade-offs

- **Container 100% statt 80% Row** — Footer ist Chrome, kein Content-Block. Volle Edge-to-Edge-Marken-Praesenz.
- **Logo-Weight 700** — Brand-Element darf das h4-Tier (weight 600) brechen.
- **`--waf-*` lokal statt global** — andere Surfaces brauchen sie aktuell nicht.
- **`--fs-small` auf Adresse/Email/Telefon/Hours** — Chrome-Density. `--fs-body` wuerde die Brand-Spalte wie Main-Content lesen lassen.
- **`!important` auf Menue-Links und Newsletter-Submit** — Divi-Parent-Theme injiziert globale Nav- und Button-Styles mit hoher Specificity. Beim Cleanup nicht entfernen.
- **`p { padding-bottom: 1em }` Reset** — global aus dem Theme-Stack. In `.wa-footer-contact` und `.wa-newsletter__msg` muss explizit `padding-bottom: 0` bzw. `padding: 12px 16px !important` gesetzt werden.

---

## Verwandte Dokumentation

- [DESIGN.md](DESIGN.md) — globales Design-System (Tokens, Typografie, Buttons)
- [SHORTCODES.md](SHORTCODES.md) — `[wa_newsletter_signup]`, `[wa_footer_menu]`
- [PHOREST.md](PHOREST.md) — Newsletter-Endpoint `/wp-json/wa/v1/newsletter`
