# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Project-wide context (Herd, WP-CLI aliases, drperi DB credentials, theme setup, dev workflow) lives in the parent `CLAUDE.md` files at `~/WEB/CLAUDE.md` and `~/WEB/Clients/drperi/CLAUDE.md`. Read those first if you need environment- or site-level info. **This file is plugin-internal only.**

## What this plugin is

`werbeauf-customs` v2.7.0 — Dr. Peri's site-specific WordPress + WooCommerce customizations on top of Divi. Owns: layout shells, custom header/footer, single-product hero, shop layout, Phorest sync, flyout cart, admin pages. **All custom code for this site lives here** (the `Divi-child` theme is intentionally empty).

The other in-tree plugin `werbeauf-ai-blog-system/` is independent and has its own docs — do not cross-pollute.

## Auto-loader (read this first)

`werbeauf-customs.php` `glob()`s every `.php` from `includes/<dir>/` in **this fixed order**, then `admin/`:

```
core → acf → layout → woocommerce → phorest → sso → shortcodes → admin/
```

Order matters: `layout/` consumes `core/` hooks; `shortcodes/` use helpers from `woocommerce/`. Within a folder, files load alphabetically.

**Implications when adding a file:**
- No manual `require_once` list — drop a `.php` into the right subdir and it loads.
- No class-instantiation gate. WC-dependency checks live inside individual files (early `return` if `! class_exists( 'WooCommerce' )`).
- Anything `acf/` does runs on `acf/init`, so its alphabetical position is irrelevant.
- `templates/header.php` and `templates/footer.php` are markup-only — the controller logic that decides which to render lives in `includes/layout/`.

The auto-loader stops after `admin/` — no test/staging bundle. (Previously experimental code under `test/` has been promoted into the production folders above.)

## CSS layering (5 layers)

Frontend CSS is split into numeric folders under `assets/css/`. `includes/core/enqueue.php` registers them in numeric order — the cascade follows folder names:

| Layer | Purpose | Examples |
|-------|---------|----------|
| `00-base/` | CSS variables / tokens, global typography & reset | `tokens.css`, `style.css` |
| `10-layout/` | Page shells (1400px container, body-class scoping) | `wc-shell.css`, `content-shell.css`, `header.css`, `footer.css` |
| `20-components/` | Reusable UI | `product-card.css`, `breadcrumb.css`, `notices.css`, `flyout-cart.css` |
| `30-pages/` | Page-type-specific | `shop-archive.css`, `single-product.css`, `cart.css`, `checkout.css`, `account.css`, `wc-blocks.css` |
| `40-blocks/` | Shortcode/composition blocks (often self-enqueued by the shortcode) | `shop-layout.css`, `trust-badges.css`, `accordion.css`, `detail-block.css` |

**Editing rule:** when changing one page's layout, edit `30-pages/` first. Only fall back to `10-layout/wc-shell.css` for shared container behavior.

**Enqueue conditions** (see `includes/core/enqueue.php`):
- `00-base` always.
- `wa-sticky-offset` JS always (sub-sticky elements need `--wa-header-h` regardless of which header is active).
- `10-layout/header.css|footer.css` only when `$wa_show_fallback_header|footer` globals are set by the controllers (i.e. no Divi Theme Builder template defined).
- WC layers gate on `is_woocommerce()` plus presence of `[woocommerce_cart|checkout|my_account]` shortcodes (resolved via `wa_post_has_wc_shortcode()`), so legacy-shortcode pages get the same styles as native endpoints.
- Card-grid layer (`product-card`, `category-card`) gates on `wa_should_load_products_grid()` which also detects WC product shortcodes in singular content.

## Body classes (scoping)

Set by `includes/layout/wc-shell.php` and `includes/woocommerce/single-product-renderer.php`. CSS in this plugin is scoped to these:

| Class | Where | Effect |
|-------|-------|--------|
| `wa-woocommerce` | All WC pages | Layout shell + WC component styles |
| `wa-content-shell` | Legal pages (AGB, Datenschutz, Impressum, Widerruf, Versand, Zahlung) | Container without WC tokens |
| `wa-single-product` | Single product pages | Hero-specific tokens (overrides `wa-woocommerce`) |
| `wa-lang-{lang}` | Always (auto) | Added by `wpml-helpers.php` for language-specific overrides |

## WPML — always use the helpers

Canonical WPML wrappers in `includes/core/wpml-helpers.php`. **Never** call `get_field( $key, 'option' )` or `get_term_by( 'slug', ... )` directly in feature code — use:

- `wa_get_options_field( $group, $key, $default )` — ACF Options read with fallback chain `options_{current_lang}` → `options_{default_lang}` → `options`. Use everywhere instead of `get_field( $key, 'option' )`.
- `wa_get_term_by_slug_localized( $slug, $taxonomy )` — `get_term_by('slug', ...)` that auto-resolves to current language via `wpml_object_id`. Defaults to `product_cat`.
- `wa_wpml_current_lang()` / `wa_wpml_default_lang()` / `wa_wpml_is_default_lang_post( $id )` — defensive (fall back to `'de'` if WPML inactive).

Phorest inbound sync only overwrites the title on default-language posts (translations stay manual). FAQ JSON-LD includes `inLanguage`. WPML add-ons in use: `acfml`, `wpml-media-translation`, `wpml-string-translation`, `woocommerce-multilingual`. **Full doc:** `docs/WPML.md`.

## Phorest integration

Lives in `includes/phorest/`. Bidirectional sync between Phorest salon software and WooCommerce.

- **Inbound** (`woo-sync.php`): products (name, price, SKU, barcode, stock) pulled hourly via WP-Cron + manual trigger from admin. Meta key `_phorest_product_id`.
- **Outbound** (`stock-sync.php`, `order-sync.php`): WC order events → Phorest stock adjustments (DEDUCT/INCREASE). **Orders themselves are NOT synced** — WooCommerce owns order management.
- **Newsletter** (`newsletter.php`): opt-in form REST endpoint feeding Phorest client newsletter consent.
- **API:** `https://api-gateway-eu.phorest.com/third-party-api-server`, HTTP Basic auth.
- **Admin UI:** under WP-Admin → "Dr. Peri" → Phorest API / Produkte / Lager / Newsletter Log.

Endpoints, credentials, server access (`svr.werbeauf.com:2222`) and full sync flow: **`docs/PHOREST.md`**.

## Admin menu pattern

All admin pages hang off the ACF Options-page parent slug `dr-peri` (registered by ACF Pro, not this plugin). `admin/admin-menu.php` registers submenus at priority 999 so the parent exists. Submenus are defined as a `[Title, slug, file, render_function]` array — to add one, append to that list. The render callback either calls a function that's already loaded or `require_once`s the file from `admin/` and then calls its render function.

## Shortcodes (registered — 13 total)

Full reference: `docs/SHORTCODES.md`. Registered in `includes/shortcodes/`:

| Shortcode | File | Notes |
|---|---|---|
| `[wa_shop_layout]` + `[drperi_shop_layout]` alias | `shop-layout.php` | Full shop archive. Default `featured_category="sets"` is drperi-specific. |
| `[drperi_shop_layout_short]` | `shop-layout-short.php` | Reduced variant (no `wa_` equivalent yet). |
| `[wa_shop_filter]` | `shop-filter.php` | Pill-bar category filter (client-side JS). |
| `[wa_category_products]` | `category-products.php` | Auto-detects current product cat (Divi Theme Builder). |
| `[wa_footer_menu]` | `footer-menu.php` | Renders `footer-menu` theme location. |
| `[wa_newsletter_signup]` | `newsletter-form.php` | Phorest newsletter opt-in (regular + footer variants). |
| `[wa_product_ref]` / `[wa_product_refs]` | `product-references.php` | Set/bundle component refs (table-row + inline-list). |
| `[wa_at_a_glance]` | `at-a-glance.php` | Editorial spec-sheet: products grouped by `product_cat` with image/price/facts/keypoints. |
| `[blog-archive-grid]` | `blog-archive-grid.php` | Blog CPT archive with theme filter + sort + pagination. |
| `[global-blog1-header]` | `global-blog1-header.php` | Blog post header (inline closure). |
| `[global-blog3-authorbox]` | `global-blog3-authorbox.php` | Author box at end of blog post (inline closure). |

## Custom Footer

Replaces the Divi default footer when no Theme-Builder footer is active. ACF-driven content (address / email / phone, opening-hours repeater, newsletter heading + intro, social links, copyright text), two nav-menu locations (`footer-menu` + `footer-menu-2`), latest 3 `blog` posts, embedded `[wa_newsletter_signup variant="footer"]`. Dark surface (`--color-accent`) with local `--waf-*` text/border aliases.

Controller logic, ACF schema, layout grid, dark-surface tokens, responsive breakpoints, exceptions: **`docs/FOOTER.md`**.

## SSO Login module (`includes/sso/`)

Passwordless email-button login for shop staff. Self-contained, no ACF dependency, portable. **Master-switch off by default** — toggle in Settings → SSO Login.

Architecture in one paragraph: cryptographic single-use tokens stored as SHA-256 hashes in custom table `{prefix}wa_sso_tokens`; tokens bind one WP user + one registered "action" + one set of args. Email links open `?wa_sso=TOKEN` which validates, sets the auth cookie, consumes the token, then EITHER redirects to a safe destination (view-only actions) OR shows a standalone confirm page with a nonce'd POST (state-changing actions). The confirm step protects against email-scanner link prefetching (Outlook ATP / Gmail tab preview).

| Public API | Where |
|---|---|
| `wa_sso_register_action( $slug, $args )` | `includes/sso/actions.php` |
| `wa_sso_action_url( $user_id, $slug, $args )` | `includes/sso/emails.php` |
| `wa_sso_button( $url, $label, $bg, $fg )` | `includes/sso/emails.php` |
| `wa_sso_button_row( array $items )` | `includes/sso/emails.php` |
| `wa_sso_create_token` / `validate` / `consume` / `revoke_user_tokens` | `includes/sso/tokens.php` |

Built-in actions: `dashboard`, `view_order`, `mark_processing`, `mark_completed`. Auto-injects 3 buttons (View / In Bearbeitung / Versandt) into the `new_order` WC admin email when a recipient maps to a WP user with an allowed role.

Full doc: **`docs/SSO.md`**. Portable WP.org-style readme: **`docs/SSO-readme.txt`**.

## Extension points (filters + actions added by this plugin)

All exposed extension points. Filters with no internal consumer are no-ops by default — registered intentionally so downstream code can override without patching the source.

| Hook | Type | Where fired | Args | Default consumer |
|---|---|---|---|---|
| `wa_icon_paths` | filter | `single-product-renderer.php:768` (`icon_paths()` return) | `array $svg_paths` | `includes/woocommerce/icon-extensions.php` — `wa_extend_icon_paths` adds 5 icons (`day`, `night`, `day_night`, `fragrance_free`, `ph_neutral`). |
| `wa_keypoint_label_html` | filter | `single-product-renderer.php:291` (`render_key_points()` `<dt>` HTML) | `$html, $row, $product` | None — filter exists for future markup prepend. |
| `wa_flyout_cart_active` | filter | `flyout-cart.php:36` | `bool true` | None — set false to disable flyout cart globally. |
| `wa_wc_shell_excluded_page_ids` | filter | `wc-shell.php:410` | `array $ids` | None — extend to skip the WC shell on additional page IDs. |
| `wa_content_shell_slugs` | filter | `wc-shell.php:428` | `array $slugs` | None — extend to mark additional pages as content-shell (legal-style). |
| `wa_account_endpoint_titles` | filter | `wc-shell.php:474` | `$titles, $endpoint` | None — override WC My Account endpoint titles. |
| `wa_phorest_after_sync` | action | `admin/phorest-data.php:94` (after manual sync UI run) | none | `phorest/woo-sync.php:278` — `wa_phorest_sync_all_linked` applies fresh Phorest cache to all linked WC products. |

The "right way" to add an icon: register a consumer for `wa_icon_paths` in a new file under `includes/woocommerce/` — the auto-loader picks it up.

## Icon registry & ACF dropdowns

Skincare icons live in two places:

- **Base registry**: `Werbeauf_Single_Product_Renderer::icon_paths()` (in `single-product-renderer.php`).
- **Extensions**: `includes/woocommerce/icon-extensions.php` merges 5 additional SVGs via the `wa_icon_paths` filter.

ACF dropdown loaders in `includes/acf/`:
- `icon-choices.php` — adds the 5 extensions to the Facts repeater dropdown.
- `trust-items-icon-extend.php` — exposes the unified icon set on the Trust-Items ACF Options repeater.

`admin/icon-preview-admin.php` renders live SVG previews inside the ACF Select2 dropdowns (Facts / Trust Items).

## product_cat term meta (colours)

`admin/product-cat-colors.php` adds two colour pickers (`wa_bg_color`, `wa_fg_color`) on every `product_cat` term. The values are consumed by `[wa_at_a_glance]` via the inline CSS variables `--wa-cat-bg` / `--wa-cat-fg` on each category group.

## Product extras (volume + clean image)

`includes/acf/product-volume-field.php` registers the field group `group_wa_product_volume` on `product`:
- `volume_ml` (text, fallback when WooCommerce attribute "Inhalt" is empty).
- `clean_featured_image` (image, "Beitragsbild Clean" — transparent product render used in `[wa_at_a_glance]` and elsewhere). **Mark as "Don't translate" in ACFML** so it's shared across DE/EN.

Two helpers are exported by this file:
- `wa_get_product_volume( WC_Product $product ): string` — preferred order: WC attribute → ACF `volume_ml` → empty.
- `wa_get_product_clean_image( int $product_id, string $size = 'medium' ): array` — `[url, alt, id]` from the ACF array field, empty array if not set.

Consumed today by `includes/shortcodes/at-a-glance.php`.

## docs/ directory

Plugin-internal docs (consult before extending the corresponding feature):

- `ARCHITECTURE.md` — **evergreen structural map**: modules, public API, WP entry points, filter contracts, class hierarchy, DB access, external integrations, asset-enqueue map, WPML coverage, high-fan-out + dead-code, auto-loader contract, cross-tool verdict. **Regenerate after major refactors.**
- `DESIGN.md` — design tokens, color/typo scale, button rules, layout standards
- `FOOTER.md` — custom footer controller + ACF schema + dark-surface tokens
- `ORDER-STATUSES.md` — WooCommerce order-status flow + Phorest stock-sync triggers
- `PHOREST.md` — API endpoints, credentials, sync flow, server access
- `SHORTCODES.md` — every shortcode with attributes
- `SINGLE-PRODUCT-DESCRIPTION.md` — single-product detail-block / single-panel / read-more / accordion contract
- `SSO.md` — **SSO Login module**: architecture, security model, action-registration pattern, recommendations for new email actions, manual test plan
- `SSO-readme.txt` — WordPress.org-style readme for the SSO module (used if the module is extracted into its own plugin)
- `WPML.md` — WPML setup + helper API reference
- `style-guide.html` — rendered Montserrat type-scale (open in browser)

Top-level: `CHANGELOG.md` (release log), `bin/bump-version.sh` (version propagation script — usage: `./bin/bump-version.sh 2.1.0 "headline"`).

## Conventions specific to this plugin

- Function/hook prefix: `wa_` (werbeauf). Old `drperi_` prefix only as backcompat aliases (`[drperi_shop_layout]`).
- Comments German, code/identifiers/commit messages English. Plugin texts go through the `werbeauf-customs` textdomain (loaded via `load_plugin_textdomain()`, files in `languages/`).
- Plugin is **drperi-specific** — the Phorest sync and the `featured_category="sets"` default in `[wa_shop_layout]` are not portable.
- After CSS/JS changes: `dev-flush` from project root or hard-reload (Cmd+Shift+R), since `wp-super-cache` is active locally.
- `wp-config.php`, `.credentials`, `wp-content/uploads/`, `herd.yml` stay out of git.

# Code intelligence — graph locations

Two indices are maintained for this plugin. Both are local, both update on file change. **Read these first when picking a tool — they tell you what each graph knows.**

| Graph | Path | Stats (2026-05-23) | Re-sync command |
|---|---|---|---|
| **GitNexus** (Cypher / cluster queries, safe rename, diff scope) | `.gitnexus/` (per-repo, also accessible via MCP) | 729 symbols / 1102 edges / 34 clusters / 31 flows | `npx gitnexus analyze` |
| **CodeGraph** (impact analysis, task context, multi-symbol source dump) | `.codegraph/codegraph.db` (this dir) | 54 files / 216 nodes / 302 edges | `npx -y @colbymchenry/codegraph sync` |

Use-policy + tool-by-tool decision table lives in the project CLAUDE.md (`~/WEB/Clients/drperi/CLAUDE.md` § Code intelligence). Cross-tool verdict per question type: `docs/ARCHITECTURE.md` § B18.

## 🚨 Graph-First Rules — read before any code change

This plugin has two pre-built knowledge graphs (GitNexus + CodeGraph). They are sub-millisecond reads and structurally accurate. **Use them first, grep second.**

### Iron rules

1. **Symbol lookup → `codegraph_search` BEFORE Grep/Glob.** Returns kind + location + signature in one call. Faster, more accurate.
2. **"How does X work" / architecture / trace → `codegraph_context` FIRST.** Composes search + node + callers + callees. One call ≈ five Read calls.
3. **Multi-symbol exploration → ONE `codegraph_explore` instead of many Reads.** Each Read re-loads context.
4. **Refactor planning → `gitnexus_impact` BEFORE editing.** Report blast radius (direct callers, processes, risk level) to the user.
5. **Cross-repo / catalog queries → `gitnexus_cypher` or `gitnexus_query`.** CodeGraph caps at 100; GitNexus uses raw Cypher.

### Plan-mode escalation triggers

Enter Plan Mode automatically when **any** of these fire:

- `gitnexus_impact` returns risk = **HIGH** or **CRITICAL**
- Dependency chain depth ≥ **3**
- Target is a load-bearing symbol — top of the impact map. Currently:
  - `Werbeauf_Single_Product_Renderer::icon_paths` / `::icon_svg` (5+ callers)
  - `wa_wpml_current_lang` (7 callers, WPML base helper)
  - `wa_phorest_api` (6 callers, Phorest transport)
  - `wa_get_options_field` (3 callers, ACF options reader)

### WordPress-specific blind spot — always cross-check

Both GitNexus and CodeGraph are **blind to `add_action` / `add_filter` callbacks, cron callbacks, AJAX handlers, REST routes, and ACF field consumers**. AST parsers don't see hook registration as call-graph edges.

This means impact analysis **under-counts** hook-driven code. Documented in `docs/ARCHITECTURE.md` § B18.

**Rule:** if the symbol you're touching is registered with `add_action` / `add_filter`, OR if it DEFINES a custom filter via `apply_filters( 'wa_*' )` / `do_action( 'wa_*' )`, **also grep the registration site** before considering the impact analysis complete.

### Trust the graph for these (no need to verify)

- Pure-PHP / pure-JS function call edges (one function calls another by name)
- Class inheritance + interface implementation
- File-level `require_once` / `include` / `use` imports
- WC-method calls (`$order->update_status()`, `wc_get_order()`, etc.)

### Verify with grep + Read for these

- `add_action` / `add_filter` registrations (find by hook name, not by callback name)
- `add_shortcode` registrations
- `register_rest_route`
- `wp_schedule_event` + cron callbacks (e.g. `WA_PHOREST_CRON_HOOK`, `WA_WORKFLOW_CRON_HOOK`)
- `wp_ajax_*` action names
- ACF `get_field('foo', $post_id)` — graph doesn't see WHICH posts have that field

### Auto-sync hook (project-level, requires manual finalisation)

A PostToolUse hook is intended at `~/WEB/Clients/drperi/.claude/settings.json` to trigger `codegraph sync` after every Edit/Write on a `.php` file under this plugin. **Debounced to 30s** — multiple edits in the same minute trigger only one sync. Logs to `.claude/sync.log`.

The hook script lives at `.claude/hooks/codegraph-sync-on-edit.sh` (in this plugin, committed). The project-level `settings.json` is intentionally **not** auto-installed by Claude Code agents — it must be created manually because it grants auto-execute privileges. See README / setup notes for the one-time `chmod +x` + settings.json installation steps.

GitNexus has NO auto-sync — re-run `npx gitnexus analyze` manually after large refactors. The graph is fault-tolerant: even a stale GitNexus index returns useful answers, it just won't see brand-new symbols.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **werbeauf-customs** (729 symbols, 1102 relationships, 31 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/werbeauf-customs/context` | Codebase overview, check index freshness |
| `gitnexus://repo/werbeauf-customs/clusters` | All functional areas |
| `gitnexus://repo/werbeauf-customs/processes` | All execution flows |
| `gitnexus://repo/werbeauf-customs/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
