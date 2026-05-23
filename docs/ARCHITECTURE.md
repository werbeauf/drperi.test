# werbeauf-customs — Architecture reference (evergreen)

_Originally generated 2026-05-22, drift-corrected and renamed evergreen 2026-05-23._

Structural map of the plugin: modules, public API surface, WordPress entry points, filter contracts, class hierarchy, database access, external integrations, asset enqueue map, WPML coverage, high-fan-out symbols, dead-code candidates, auto-loader contract, cross-tool verdict. Regenerate after major refactors with the same CodeGraph + GitNexus queries (Phase B items 6–18 below).

Tools used: **CodeGraph** (54 files / 216 nodes / 302 edges) + **GitNexus** (106 files / 729 nodes / 1102 edges / 31 flows).

> **CLAUDE.md drift status (2026-05-23):** all CLAUDE.md drift flagged in §B7 and §B9 has been resolved. The shortcode tables now list all 13 shortcodes and the filter tables list all 6 filters + 1 action.

---

## Phase A — head-to-head completion

| Q | Question | GitNexus baseline | CodeGraph (MCP) | Winner |
|---|---|---|---|---|
| Q1 | Impact of `wa_get_product_volume` (upstream) | 1 caller, LOW | 4 symbols affected = 1 external caller (`wa_render_at_a_glance` @ at-a-glance.php:56) + the definition file + self | **Tie** — both agree on 1 caller |
| Q2 | Impact of `Werbeauf_Single_Product_Renderer::icon_paths` (depth 3) | 9 affected, 6 flows, depth 3 | **14 symbols** across 4 files: `single-product-renderer.php` (5 internal: icon, icon_svg, render_product_facts, render_trust_row, class), `admin/icon-preview-admin.php` (wa_icon_preview_enqueue), `admin/product-facts-column.php` (wa_product_facts_column_render), `includes/shortcodes/at-a-glance.php` (3 funcs: wa_render_at_a_glance, _facts, _keypoints) | **CodeGraph** — reaches the admin-column + at-a-glance transitively that GitNexus undercounts |
| Q3 | Phorest inbound sync flow | Missed `woo-sync.php` cron-hook entry | `wa_phorest_sync_all_linked` @ woo-sync.php:245 + `wa_phorest_apply_to_woo` @ woo-sync.php:175 + `wa_phorest_sync_order` @ order-sync.php:64, with source inline | **CodeGraph** (CLI + MCP both) |
| Q4 | Catalog of `wa_*` functions | Cypher truncated at 50 rows | **131 functions total** in the index (CLI uncapped; MCP `codegraph_search` caps at 100 even with limit=200) | **CodeGraph CLI** for completeness; MCP loses to its own CLI here |

**Q3 nuance unchanged:** CodeGraph finds files via semantic/keyword match on names + content, not by tracing cron-hook edges. WordPress-hook blind spot remains, but discovery mechanism is different from GitNexus's, so the two tools miss *different* things.

---

## Phase B — structural map of werbeauf-customs

### B6. Module map

Auto-loader globs `includes/<dir>/*.php` in fixed order, then `admin/*.php`. **Filesystem matches the contract** (auto-loader contract verified at werbeauf-customs.php:55+):

```
core (5)  → acf (5)  → layout (3)  → woocommerce (4)  → phorest (4)  → sso (7)  → shortcodes (11)  → admin/ (10)
```

`sso` added in 2.1.0 — see `docs/SSO.md`.

Off-loader: `templates/` (2 files, included from layout/), `assets/js/` (8 JS), `werbeauf-customs.php` + `uninstall.php` (root). **Total indexed: 54 files**.

| Layer | Files | Purpose |
|---|---|---|
| `includes/core/` | admin-tweaks, divi-toggle-fix, enqueue, footer-menus, wpml-helpers | Cross-cutting bootstrap |
| `includes/acf/` | footer-fields, icon-choices, product-volume-field, single-product-fields, trust-items-icon-extend | ACF Local Field Groups (hooks `acf/init`) |
| `includes/layout/` | footer-controller, header-controller, wc-shell | Header/footer/WC-shell control + body-class scoping |
| `includes/woocommerce/` | flyout-cart, icon-extensions, non-base-location-prices, single-product-renderer | WC layer; single-product-renderer is the only class |
| `includes/phorest/` | newsletter, order-sync, stock-sync, woo-sync | Phorest API integration (inbound + outbound) |
| `includes/shortcodes/` | at-a-glance, blog-archive-grid, category-products, footer-menu, global-blog1-header, global-blog3-authorbox, newsletter-form, product-references, shop-filter, shop-layout, shop-layout-short | Public shortcodes (lazy-register assets) |
| `admin/` | admin-docu, admin-menu, icon-preview-admin, phorest-{api,data,newsletter,stocks}, product-cat-colors, product-facts-column | Admin UI + Phorest tooling + product list-table columns |

### B7. Public API surface

**Within-plugin direct callers** (hook callbacks NOT counted as edges — see B16). Numbers from `codegraph_callers`:

| Symbol | Defining file | Direct callers | Notes |
|---|---|---|---|
| `wa_phorest_api` | `phorest/order-sync.php:16` + `phorest/stock-sync.php:17` (intentional twin, both `function_exists`-guarded) | 6 | Verified 2026-05-23: both definitions wrapped in `if ( ! function_exists( 'wa_phorest_api' ) ) :` (order-sync.php:15, stock-sync.php:16). Either file can load first without conflict. |
| `wa_wpml_current_lang` | `core/wpml-helpers.php:30` | 7 | Used by other WPML helpers + shortcodes + renderer + footer template |
| `icon_svg` (static method) | `single-product-renderer.php:748` | 5 | External-facing static API of the renderer class |
| `wa_get_options_field` | `core/wpml-helpers.php:128` | 3 | WPML-aware ACF options reader — should be used everywhere instead of `get_field('x','option')` (see B14) |
| `wa_phorest_apply_to_woo` | `phorest/woo-sync.php:175` | 2 | Re-entry guarded by static `$running` array |
| `wa_get_product_volume` | `acf/product-volume-field.php:75` | 1 | Only `wa_render_at_a_glance` consumes it externally |
| `wa_wpml_object_id` | `core/wpml-helpers.php:79` | 1 | Internal: only `wa_get_term_by_slug_localized` uses it |
| `wa_wpml_is_default_lang_post` | `core/wpml-helpers.php:98` | 1 | Internal: only `wa_phorest_apply_to_woo` uses it |

**`drperi_*` legacy surface (back-compat only — do not extend):**

- Shortcode `[drperi_shop_layout]` — alias of `wa_shop_layout` (registered at shop-layout.php:59)
- Shortcode `[drperi_shop_layout_short]` — **NO `wa_` equivalent yet** (lives at shop-layout-short.php:44 as primary registration)

The remaining 100+ `wa_*` functions are either hook callbacks (registered with `add_action`/`add_filter`), shortcode renderers (registered with `add_shortcode`), or AJAX handlers (`wp_ajax_*`) — see B8.

### B8. WordPress entry points

#### Shortcodes (13 registrations)

| Shortcode | Callback | File |
|---|---|---|
| `[wa_shop_layout]` | wa_shop_layout_shortcode | shortcodes/shop-layout.php:58 |
| `[drperi_shop_layout]` (alias) | wa_shop_layout_shortcode | shortcodes/shop-layout.php:59 |
| `[drperi_shop_layout_short]` | wa_shop_layout_short_shortcode | shortcodes/shop-layout-short.php:44 |
| `[wa_shop_filter]` | wa_shop_filter_shortcode | shortcodes/shop-filter.php:46 |
| `[wa_category_products]` | wa_category_products_shortcode | shortcodes/category-products.php:63 |
| `[wa_newsletter_signup]` | wa_newsletter_render_shortcode | shortcodes/newsletter-form.php:56 |
| `[wa_footer_menu]` | wa_render_footer_menu_by_location | shortcodes/footer-menu.php:13 |
| `[wa_product_ref]` | wa_render_product_ref_block | shortcodes/product-references.php:41 |
| `[wa_product_refs]` | wa_render_product_ref_inline | shortcodes/product-references.php:42 |
| `[wa_at_a_glance]` | wa_render_at_a_glance | shortcodes/at-a-glance.php:54 |
| `[blog-archive-grid]` | wa_blog_archive_grid_render | shortcodes/blog-archive-grid.php:40 |
| `[global-blog1-header]` | inline closure | shortcodes/global-blog1-header.php:10 |
| `[global-blog3-authorbox]` | inline closure | shortcodes/global-blog3-authorbox.php:16 |

**CLAUDE.md drift:** the SHORTCODES section lists 9 shortcodes; the plugin actually registers 13. Missing from CLAUDE.md: `[wa_at_a_glance]`, `[blog-archive-grid]`, `[global-blog1-header]`, `[global-blog3-authorbox]`.

#### REST API (1 route)

| Route | Method | File |
|---|---|---|
| `wa/v1/newsletter` | `register_rest_route` | phorest/newsletter.php:276 |

#### AJAX handlers (8 actions, all admin-only)

| Action | File:line |
|---|---|
| `wa_phorest_sync_products` | admin/phorest-data.php:80 |
| `wa_phorest_clear_cache` | admin/phorest-data.php:105 |
| `wa_phorest_manual_stock` | admin/phorest-stocks.php:12 |
| `wa_phorest_newsletter_clear` | admin/phorest-newsletter.php:20 |
| `wa_phorest_resync_order` | phorest/order-sync.php:368 |
| `wa_phorest_sync_single_product` | phorest/woo-sync.php:149 |
| `wa_phorest_test_connection` | admin/phorest-api.php:35 |

**No `wp_ajax_nopriv_*`** — all AJAX is admin-only by design.

#### Cron

| Hook constant | Recurrence | Bootstrap | Callback |
|---|---|---|---|
| `WA_PHOREST_CRON_HOOK` | `hourly` | `wp` action (woo-sync.php:283) — schedules via `wp_schedule_event` (line 285) if not already scheduled | callback at woo-sync.php:289 — runs full inbound sync |

#### Block registrations

**None.** Plugin does not register Gutenberg blocks.

#### Meta-boxes (2)

| Hook | File:line |
|---|---|
| `add_meta_boxes` (Phorest order box) | phorest/order-sync.php:284 |
| `add_meta_boxes` (Phorest product box) | phorest/woo-sync.php:20 |

#### CPTs / taxonomies / post-statuses

**None registered.** Plugin uses WC's `product` CPT and `product_cat` taxonomy.

#### Significant `add_action` count

~60 sites total. Concentrations:
- `includes/woocommerce/single-product-renderer.php` — **28 hooks** (the entire single-product layout pipeline)
- `includes/phorest/woo-sync.php` — 8 hooks (incl. cron + product save + AJAX)
- `includes/layout/wc-shell.php` — 10+ hooks (WC shell layout)

#### Significant `add_filter` count

~25 sites. Notable:
- **`body_class`: 5 separate add_filter sites** (wpml-helpers, single-product-renderer, footer-controller, header-controller, wc-shell.wa_woocommerce_body_class). Each adds its own class for scoping. Coordinated via the conventions documented in CLAUDE.md "Body classes" table.
- WC product/order list-table columns: 6 filter sites
- ACF `acf/load_field/key=field_*`: 2 sites (icon-choices + trust-items-icon-extend)

### B9. Filter contracts exposed by this plugin

| Filter / action | Where fired | Args passed | Default return | Internal consumers | Documented in CLAUDE.md? |
|---|---|---|---|---|---|
| `wa_icon_paths` (filter) | single-product-renderer.php:768 | `array $svg_paths` | 5 base SVG paths | `includes/woocommerce/icon-extensions.php:19` → `wa_extend_icon_paths` (adds 5: day/night/day_night/fragrance_free/ph_neutral) | ✅ Yes |
| `wa_keypoint_label_html` (filter) | single-product-renderer.php:291 | `$html, $row, $product` | original `$html` | none | ✅ Yes — already noted as "no default consumer" |
| `wa_flyout_cart_active` (filter) | flyout-cart.php:36 | `bool true` | `true` | none (external override point) | ❌ **DRIFT — not in CLAUDE.md filter table** |
| `wa_wc_shell_excluded_page_ids` (filter) | wc-shell.php:410 | `array $ids` | excluded IDs list | none (external override point) | ❌ **DRIFT — not in CLAUDE.md filter table** |
| `wa_content_shell_slugs` (filter) | wc-shell.php:428 | `array $slugs` | content-shell slug list | none (external override point) | ⚠️ Mentioned inline in wc-shell.php:415 docblock; not in CLAUDE.md filter table |
| `wa_account_endpoint_titles` (filter) | wc-shell.php:474 | `$titles, $endpoint` | WC default titles | none (external override point) | ❌ **DRIFT — not in CLAUDE.md filter table** |
| `wa_phorest_after_sync` (action) | admin/phorest-data.php:94 | none | — | `phorest/woo-sync.php:278` → `wa_phorest_sync_all_linked` | ❌ Not documented as an extension point |

**CLAUDE.md drift:** the "Extension points (filters)" table covers 2 filters; plugin actually exposes **5 filters + 1 custom action**. Recommend extending the table after user review.

### B10. Class hierarchy + method overrides

**Only 1 class in the entire plugin:**

```
Werbeauf_Single_Product_Renderer  (extends: nothing, implements: nothing)
  includes/woocommerce/single-product-renderer.php:14
  30 methods total
```

| Visibility | Static? | Count | Notable members |
|---|---|---|---|
| `public` (instance) | no | 25 | `__construct`, `init_renderer`, `add_body_class`, `open_wrapper`/`grid`/`gallery_col`/`action_block` + matching `close_*`, `render_sale_flash`/`category_label`/`divider`/`short_description_more_link`/`features`/`product_facts`/`key_points`/`faq`/`output_faq_schema`/`trust_row`/`meta_compact`/`related_products`/`detail_block`/`reviews_section` |
| `public static` | yes | 2 | `icon_svg`, `icon_paths` — the externally-callable API (5 callers across files) |
| `private` (instance) | no | 3 | `get_trust_items`, `icon`, `accordion_icon_svg` |

No method overrides (no inheritance), no `parent::` calls. Instantiated once on `wp` action in `__construct` → `init_renderer` registers 28 WC hooks.

### B11. Database access

**Custom table (1):** `{$wpdb->prefix}wa_phorest_stock_log` — Phorest stock adjustment audit log. Created by `wa_phorest_stock_maybe_install` on `init` hook (idempotent — uses `dbDelta`).

**Direct `$wpdb` SQL: 5 sites, all in `admin/phorest-stocks.php`:**
- `wpdb->esc_like` (line 95)
- `wpdb->prepare` (line 102)
- `wpdb->get_var` (line 104) — COUNT(*)
- `wpdb->get_results` (line 105) — paged list query
- `wpdb->get_results` (line 108) — aggregate stats query

**Post-meta keys catalog:**

| Key | Type | Usage |
|---|---|---|
| `WA_PHOREST_LINK_META` (constant) | per-product | Phorest productId link (7 sites read/write/delete) |
| `_wc_gtin` | per-product | Native WC 9.2+ GTIN — populated from Phorest `barcode` (3 sites) |
| `_phorest_last_sync` | per-product | timestamp of last successful inbound sync |
| `_phorest_purchase_synced` | per-order | flag — order was synced to Phorest |
| `_phorest_purchase_error` | per-order | error message storage |

**Meta namespaces:** `_phorest_*`, `_wc_*` (one borrowed key), and `WA_PHOREST_LINK_META` (constant-defined, not literal underscore-prefix).

### B12. External integrations

#### Phorest API

- **Base URL:** `https://api-gateway-eu.phorest.com/third-party-api-server` (default; overridable via `wa_phorest_api_url` option)
- **Auth:** HTTP Basic (per `docs/PHOREST.md`)
- **Endpoints called:**

| Method | Path | Initiating symbol | Purpose |
|---|---|---|---|
| GET | products list | `wa_phorest_fetch_products` (admin/phorest-data.php:15) | inbound product sync |
| GET | client lookup | `wa_nl_find_phorest_client_by_email` (newsletter.php:148) | newsletter dedup |
| GET | client search | inline (order-sync.php:136) | order-sync client lookup |
| POST | client create | `wa_phorest_get_or_create_client` (order-sync.php:152) | order-sync client provisioning |
| POST | client create | inline (newsletter.php:112) | newsletter signup |
| POST | client update | inline (newsletter.php:96) | newsletter dedup-update |
| POST | `api/business/{bid}/branch/{bid}/purchase` | `wa_phorest_sync_order` (order-sync.php:99) | order → Phorest purchase |
| POST | `api/business/{bid}/branch/{bid}/stock/adjustment` | `wa_phorest_send_stock_adjustment` (stock-sync.php:158) + manual admin (phorest-stocks.php:35) | stock DEDUCT/INCREASE |

`wa_phorest_api()` is the single transport — used by **6 callers** across 5 files. **Note: two definitions exist** (order-sync.php:16 + stock-sync.php:17). PHP will fatal-error on second load unless `function_exists` gated. **Action item: verify the gating.**

#### WordPress core / WC / WPML hooks consumed

- **WC actions consumed (~30 different hook names)**: `woocommerce_init`, `woocommerce_before/after_single_product_summary`, `woocommerce_single_product_summary`, `woocommerce_before/after_single_product`, `woocommerce_order_status_completed/cancelled/refunded`, `woocommerce_loop_add_to_cart_link`, `woocommerce_sale_flash`, `woocommerce_add_to_cart_fragments`, `woocommerce_external_add_to_cart`, `woocommerce_after_shop_loop_item`, `woocommerce_shop_loop_subcategory_title`, `woocommerce_blocks_loaded`, `woocommerce_store_api_checkout_order_processed`, `woocommerce_adjust_non_base_location_prices`, `woocommerce_process_product_meta`, `woocommerce_before_account_navigation`, `woocommerce_before_customer_login_form`, `woocommerce_template_single_*` (re-registered at custom priorities)
- **WPML filters consumed**: `wpml_current_language`, `wpml_object_id`, `wpml_post_language_details` (all via `apply_filters` in `core/wpml-helpers.php`)
- **WC StoreAPI**: `woocommerce_blocks_loaded` + `woocommerce_store_api_checkout_order_processed` → newsletter signup at checkout

#### Outbound HTTP

4 sites total: `wp_remote_request` (order-sync.php:40, stock-sync.php:41), `wp_remote_get` (admin/phorest-data.php:37, admin/phorest-api.php:51). All Phorest-bound.

### B13. Asset enqueue map

**Centralized loader:** `includes/core/enqueue.php` is the single source of truth — 44 enqueue calls inside `wa_enqueue_frontend_assets`, gated by body-class / page-type / shortcode-presence checks (`wa_post_has_wc_shortcode`, `wa_is_content_shell_page`, etc.).

**Always-on enqueues (base + WC shell):**
- Two unconditional `wp_enqueue_style` at enqueue.php:88 + 95 → these are the base tokens + global styles loaded on every page where the action fires (`wp_enqueue_scripts` is page-side, not admin)

**Shortcode lazy-register pattern (7 sites):** each shortcode file registers its assets on `wp_enqueue_scripts` then `wp_enqueue_style()` only inside the shortcode renderer. Good pattern — no styles for shortcodes that aren't on the page.

| Shortcode file | Register handle |
|---|---|
| shop-layout.php:48 | wa-product-card / wa-category-card / wa-shop-layout |
| shop-layout-short.php:34 | wa-product-card / wa-shop-layout |
| shop-filter.php:29 | wa-shop-filter |
| category-products.php:35 | wa-product-card / wa-category-card / wa-shop-layout |
| blog-archive-grid.php:24 | wa-blog-archive-grid (priority 9 = before main enqueue) |
| at-a-glance.php:44 | wa-at-a-glance |
| product-references.php:31 | wa-product-refs |
| newsletter-form.php:21 | wa-newsletter (also re-enqueued in `core/enqueue.php` when on contact pages) |

**WC fragment scripts (2):** `flyout-cart.php:96-99` and `wc-shell.php:599-602` both enqueue `wc-cart-fragments` + `wc-add-to-cart` (intentional double-coverage — flyout-cart guards on its own active check, wc-shell handles the shell page).

**Admin (3 sites):** `icon-preview-admin.php`, `product-cat-colors.php` (wp-color-picker), `product-facts-column.php:103` (`admin_print_styles-edit.php`).

### B14. WPML coverage audit

#### `get_field('x','option')` sites that should use `wa_get_options_field`:

| File:line | Field | Status |
|---|---|---|
| `templates/header.php:14-16` | `'header'` | ✅ Already migrated. Uses `wa_get_options_field('header')` as primary, raw `get_field('header','option')` only as fallback if helper not loaded. |
| `admin/admin-docu.php:673` | inside a `<pre>` code example — docs only, not executed | n/a |

**Net: 0 production WPML gaps** for `get_field` (verified 2026-05-23).

#### `get_term_by('slug', ...)` sites that should use `wa_get_term_by_slug_localized`:

| File:line | Taxonomy | Status |
|---|---|---|
| `shortcodes/shop-filter.php:75-77` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/shop-filter.php:96-98` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/blog-archive-grid.php:85-87` | dynamic `$taxonomy` | ✅ Already migrated (ternary fallback) |
| `shortcodes/category-products.php:107-109` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/category-products.php:118-120` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/category-products.php:125-127` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/category-products.php:179-181` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `shortcodes/at-a-glance.php:80-82` | `product_cat` | ✅ Already migrated (ternary fallback) |
| `core/wpml-helpers.php:206`, `:229` | (internals of the helper itself) | n/a |

All sites use the `function_exists( 'wa_get_term_by_slug_localized' ) ? wa_...(...) : get_term_by(...)` pattern. Raw `get_term_by` runs only if the helper isn't loaded (defensive — wpml-helpers.php loads first in the auto-loader, so practically always loaded).

**Net: 0 production WPML gaps** for `get_term_by` (verified 2026-05-23).

#### Other `get_field()` calls that need scrutiny

Most `get_field()` calls pass a product ID as second arg, which ACFML handles via product translation — these are OK as-is. But `templates/header.php:16` was the only options-context drift.

### B15. High-fan-out symbols (top 10)

Within-plugin direct callers (hook callbacks NOT counted; see B16). Tied symbols listed in source order:

| Rank | Symbol | Direct callers | Role |
|---|---|---|---|
| 1 | `wa_wpml_current_lang` | 7 | WPML language helper — load-bearing for body class, term lookup, options reader, FAQ schema |
| 2 | `wa_phorest_api` | 6 | Phorest HTTP transport — every Phorest call goes through it |
| 3 | `icon_svg` (static method) | 5 | Public SVG icon API of the renderer class |
| 4 | `wa_get_options_field` | 3 | WPML-aware ACF options reader |
| 5 | `wa_phorest_apply_to_woo` | 2 | Phorest → WC product write-back |
| 6 | `wa_render_at_a_glance` | (shortcode callback — see B16) | At-a-glance shortcode entry |
| 7 | `wa_wpml_object_id` | 1 | WPML object-ID translator |
| 8 | `wa_wpml_is_default_lang_post` | 1 | Phorest title-write guard |
| 9 | `wa_get_product_volume` | 1 | Product-volume helper |
| 10 | `wa_load_plugin_textdomain` | (hook callback) | Bootstrap |

**Conclusion:** the plugin's load-bearing pieces are the WPML helpers + the Phorest transport. The renderer is mostly hook-callback-driven (low direct fan-out, high hook fan-out). Changing `wa_wpml_current_lang` or `wa_phorest_api` is the riskiest move in the plugin.

### B16. Dead / unreferenced symbols (with hook false-positive cross-check)

**Codegraph reports 0 callers for the following — cross-checked manually:**

| Symbol | Codegraph callers | Actual usage | Verdict |
|---|---|---|---|
| `wa_render_at_a_glance` | 0 | Registered via `add_shortcode('wa_at_a_glance', ...)` at at-a-glance.php:54 | **False positive — alive** |
| `wa_extend_facts_icon_choices` | 0 | Registered via `add_filter('acf/load_field/key=field_wa_product_facts_icon', ...)` at icon-choices.php:14 | **False positive — alive** |
| `wa_load_plugin_textdomain` | 0 | Registered via `add_action('plugins_loaded', ...)` at werbeauf-customs.php:29 | **False positive — alive** |

All zero-caller hits in this plugin are hook callbacks, exactly as predicted by the CLAUDE.md WordPress-hook blind spot warning. **No actual dead code identified.**

**Filter consumers with no internal handlers** (extension points awaiting external use): `wa_keypoint_label_html`, `wa_flyout_cart_active`, `wa_wc_shell_excluded_page_ids`, `wa_account_endpoint_titles`. These are intentional — they exist for downstream override and are not dead.

### B17. Auto-loader contract verification

`werbeauf-customs.php:55` declares:
```php
$wa_include_dirs = array( 'core', 'acf', 'layout', 'woocommerce', 'phorest', 'shortcodes' );
```

Then globs `includes/<dir>/*.php` for each, then globs `admin/*.php`. **Filesystem matches:**

| Directory | Exists | Files |
|---|---|---|
| `includes/core/` | ✅ | 5 PHP |
| `includes/acf/` | ✅ | 5 PHP |
| `includes/layout/` | ✅ | 3 PHP |
| `includes/woocommerce/` | ✅ | 4 PHP |
| `includes/phorest/` | ✅ | 4 PHP |
| `includes/shortcodes/` | ✅ | 11 PHP |
| `admin/` | ✅ | 10 PHP (+ `email-sync.json` non-PHP, not auto-loaded) |

**No ordering bugs detected:** layer N never declares a function-level dependency on a same-tick symbol in a later-loaded layer. All cross-layer consumption is via hooks (which fire later) or shortcode renderers (called at template render time).

### B18. Cross-tool verdict

| Question type | GitNexus | CodeGraph | Notes |
|---|---|---|---|
| Multi-hop impact (depth 2-3) | 9 hits, 6 flows | **14 hits, 4 files** | CodeGraph reaches transitive admin + shortcode consumers GitNexus undercounts |
| Single-caller impact | 1 ✓ | 1 ✓ | Tie |
| Symbol catalog by prefix | Cypher truncated at 50 | **131 (CLI) / 100 (MCP cap)** | CodeGraph CLI wins; MCP search has a hard 100-cap |
| File discovery via semantic match | Edge-only — misses cron-wired files | **Finds via name/content** | CodeGraph wins on "find me the Phorest sync file" |
| Cypher queries (e.g. "all Methods of Class X") | **Native** | No equivalent | GitNexus wins |
| Module clustering (`:Community`) | **Native (34 clusters)** | File-tree only | GitNexus wins for grouping |
| Diff scope check (`detect_changes`) | **Native** | No equivalent | GitNexus wins |
| Safe rename across files | **Native (`gitnexus_rename`)** | No equivalent | GitNexus wins |
| Multi-symbol source dump | grep / read manually | **`codegraph_explore` in one call** | CodeGraph wins |
| Focused task context | manual `query` + `context` | **`codegraph_context` in one call** | CodeGraph wins (composes search+node+callers+callees) |
| WordPress hook edges | **Blind** | **Blind** | Both fail — must grep `add_action`/`add_filter` manually |
| ACF field references | Blind | Blind | Both miss `get_field()` consumer relationships |
| Cron callback edges | **Blind** | **Blind** | Both miss `wp_schedule_event` callback as edge |

**Recommendation: keep BOTH.**

- **CodeGraph wins for:** impact-radius analysis (more complete), task context (one call), source-dump-many-symbols (one call), semantic file discovery, raw catalog completeness.
- **GitNexus wins for:** cypher catalog queries, community/cluster grouping, diff-scope detection, safe rename, multi-flow process tracing (Q3-style).
- **Tie / both fail:** WordPress hook tracing, ACF field consumers, cron-callback edges → fall back to grep + read.

---

## Drift status (CLAUDE.md vs reality)

All items resolved 2026-05-23.

| Original drift | Status |
|---|---|
| "Shortcodes (registered)" listed 9, plugin registers 13 | ✅ Fixed — CLAUDE.md tables now list all 13 (project + plugin) |
| "Extension points (filters)" listed 2, plugin exposes 6 filters + 1 action | ✅ Fixed — CLAUDE.md tables now complete (project + plugin) |
| `templates/header.php:16` should use `wa_get_options_field` | ✅ Already correct — line 14 calls helper, raw `get_field` is fallback |
| 8 shortcode `get_term_by` sites should use `wa_get_term_by_slug_localized` | ✅ Already correct — all 8 sites use ternary fallback pattern |
| `wa_phorest_api()` declared twice without guard | ✅ Already correct — both definitions wrapped in `function_exists` guard |

---

## Verdict

Two healthy code-intelligence tools, complementary scopes, no reason to drop either. CodeGraph carries the impact + context + completeness wins; GitNexus carries cypher + clustering + diff-aware refactor wins. The blind spots are identical (WordPress hooks), so neither tool replaces the existing rule "always cross-check with the source." Drift between CLAUDE.md and the plugin is small but real — recommend a follow-up doc-sync after user review.
