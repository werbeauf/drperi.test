# werbeauf-customs — Changelog

Reverse chronological. Each release bumps `Version:` in `werbeauf-customs.php`, the `WERBEAUF_PLUGIN_VERSION` constant, and the version refs in the two CLAUDE.md files (project + plugin). Use `bin/bump-version.sh <new-version>` to propagate.

---

## 2.7.0 — 2026-05-23 (Phase H — Review-Request 14 Tage nach Versand)

Closes the original master plan. Adds the deferred customer-review-request as a 7th workflow rule + the matching template.

### Was die Regel macht

- **Trigger**: order status = `completed`, modified ≥ 14 Tage her (20160 Minuten).
- **Channel**: customer.
- **Tone**: `review`.
- **Frequenz**: einmalig (`max_repeats=1`).
- **Default**: **AUS**. Q5 hat das Feature als "später" parkiert; ich shippe es gebaut-aber-deaktiviert, damit drperi entscheidet wann es live geht. Aktivieren: Dr. Peri → SSO Login → Workflow-Regeln → "Review-Anfrage 14 Tage nach Versand" anhaken → speichern.

### Template `customer-review-request.php`

Klinik-warm, dankbar, kein Druck:

- Heading: "Wie gefallen Ihnen unsere Produkte?"
- Lead: "vor zwei Wochen haben Sie bei uns Ihre Bestellung #X erhalten. Wir hoffen, alles ist gut angekommen ..."
- Soft-ask: "Falls Sie einen Moment Zeit haben: Ihre kurze Bewertung hilft anderen Kundinnen ..."
- CTA: **"Bewertung abgeben"** (filterable URL — Default: WC My-Account view-order page)
- Disclaimer: "Dauert weniger als eine Minute. Sie koennen den Text spaeter jederzeit aendern."
- Sign-off: "Herzlichen Dank fuer Ihr Vertrauen."

### Filter `wa_sso_review_request_url`

Default zeigt auf `WC_Order::get_view_order_url()`. Drperi kann das auf Google Reviews / Trustpilot / eine eigene Review-Page umleiten:

```php
add_filter( 'wa_sso_review_request_url', function ( $default, $order ) {
    return 'https://search.google.com/local/writereview?placeid=XXXXX';
}, 10, 2 );
```

Verifiziert: Filter feuert, Google-Maps-URL erscheint im gerenderten HTML statt der Default-URL.

### Subject

`Wie gefallen Ihnen Ihre Produkte von Dr. Peri Skincare?` — rule-spezifischer Override, kein Bestellnummer-Lärm im Betreff (anders als bei Reminders/Eskalationen — hier ist die Bestellung Vergangenheit und die Mitarbeiterin braucht keine Order-Disambiguation).

### Pflicht-Hinweis bevor du aktivierst

1. Postmark-Account-Approval muss durch sein (sonst landen Reviews-Emails an `*@gmail.com` etc. nicht).
2. `wa_sso_review_request_url`-Filter konfigurieren (default-URL ist eine Account-Seite, nicht ideal für tatsächliche Reviews).
3. Falls drperi gar nicht aktiv Reviews sammelt → einfach aus lassen, die Workflow-Engine ist davon unberührt.

### Smoke tests (alle grün)

- Rule-count: 6 → 7 ✓
- Template-count: 5 → 6 ✓
- Default disabled (`enabled=false`) — keine Überraschungs-Sends ✓
- Preview rendert: brand color, "Bewertung abgeben"-CTA, "vor zwei Wochen"-Copy alle drin ✓
- Subject: rule-spezifisch ("Wie gefallen Ihnen Ihre Produkte von Dr. Peri Skincare?") ✓
- `wa_sso_review_request_url` Filter funktioniert (Google Maps URL injiziert) ✓

### Master-Plan: vollständig

| Batch | Inhalt | Status |
|---|---|---|
| A | Foundations | ✅ 2.2.0 |
| B | Postmark Setup | ✅ user-side |
| C | Workflow Engine | ✅ 2.3.0 |
| D | Template System | ✅ 2.4.0 |
| E | Action Suite | ✅ 2.5.0 |
| F | WC Brand Wrapper | ✅ 2.4.0 |
| G | Polish | ✅ 2.6.0 |
| **H** | **Review-Request** | ✅ **2.7.0 — THIS RELEASE** |

Active Order System ist nun vollständig — keine Master-Plan-Items mehr offen.

---

## 2.6.0 — 2026-05-23 (Batch G — Polish: Pending-Template + Resend + Preview)

Closes the master-plan. Three small but daily-useful additions on top of the active order system.

### G.1 · `customer-pending-recovery` template + rule-specific routing

The pending-cart and failed-payment cases need different tones — both went through `customer-recovery.php` before.

- **New template `customer-pending-recovery.php`** — cart-abandoned copy: "Sie haben kürzlich Produkte ausgewählt, die Bestellung aber noch nicht abgeschlossen. Ihr Warenkorb ist noch reserviert." Soft, no-pressure tone (per Q3 default-off).
- **Rule-specific override** via new `'template_slug'` field in rule definitions. `pending_customer_recovery` now points at the new template; `failed_customer_recovery` keeps using `customer-recovery.php`.
- **Subject builder** extended to accept `$rule_slug` and route rule-specific subjects: pending → "Ihre Bestellung #X wartet auf Sie"; failed → "Brauchen Sie Hilfe bei Ihrer Bestellung #X?"; apology → "Entschuldigung wegen Verzögerung Ihrer Bestellung #X". Each tone gets its own headline so staff and customers can distinguish at-a-glance.

### G.2 · Workflow log: per-row "Erneut senden"

- **Resend button** added to each row of the Workflow-Aktivität table on the settings page.
- **Confirmation prompt** ("Diese Email erneut senden?") before submit — prevents fat-finger replays.
- **New `admin-post_wa_sso_workflow_resend` handler**: validates the rule still exists, validates the order still exists, then calls `wa_workflow_dispatch()` (same code path as the cron). Records a new log entry. Bypasses throttling intentionally — admin is taking explicit action.
- **Use cases**: Postmark approval-pending fails → fix at Postmark side → click resend; legitimate retry after the customer reports never receiving the original.

### G.3 · Template-Vorschau section (preview + test-send)

New section on the SSO settings page between Workflow-Regeln and Selbst-Test.

- **Template dropdown** auto-populated from `wa_sso_available_template_slugs()` (scans `email-templates/*.php`, excludes `_partials/`). Currently 5 options.
- **Order-ID input** prefilled with the most recent order in WC.
- **"Vorschau anzeigen" button** → renders the template through the same dispatch path the cron uses → returns the HTML into an inline `<iframe name="wa_sso_preview_frame">` via standard form-target redirection (no JS needed; pure HTML form mechanics). Email-isolated visual layout.
- **"Test-Email senden" button** → real `wp_mail()` to the configured recipient (defaults to current admin). Subject prefixed with `[TEST]`. Goes through Postmark same as production.
- **New `admin-post` handlers**: `wa_sso_template_preview` (returns raw HTML for the iframe) + `wa_sso_template_test` (sends via wp_mail, returns toast).
- **Internal helpers**: `wa_sso_render_template_for_preview($slug, $order_id)` reuses the live render dispatcher with a pseudo-rule that maps `template_slug → channel → tone`. Subject-resolution falls back to rule-specific override → channel/tone default → tone-only default.

### Smoke tests (all pass)

| Template | Subject | Routing |
|---|---|---|
| `admin-reminder` | `[Dr. Peri Skincare] Erinnerung: Bestellung #2159 wartet` | ✓ |
| `admin-escalation` | `[Dr. Peri Skincare] DRINGEND: Bestellung #2159 — Entscheidung nötig` | ✓ |
| `customer-recovery` | `Brauchen Sie Hilfe bei Ihrer Bestellung #2159? — Dr. Peri Skincare` | ✓ |
| `customer-pending-recovery` | `Ihre Bestellung #2159 wartet auf Sie — Dr. Peri Skincare` | ✓ rule-specific |
| `customer-apology` | `Entschuldigung wegen Verzögerung Ihrer Bestellung #2159 — Dr. Peri Skincare` | ✓ |

- Resend dispatch on `processing_reminder` for order #2159 fired correctly, returned ok=Y, recipient=office@drperi.at ✓
- Slug splitter correctly handles multi-segment slugs (`customer-pending-recovery` → channel=customer, tone=recovery) ✓

### Master-plan completion

All scoped batches now shipped. Remaining items per the original plan:

- ✅ Batch A — Foundations (token refactor + 7d TTL + history block) — **2.2.0**
- ✅ Batch B — Email Infrastructure (Postmark) — done by user
- ✅ Batch C — Workflow Engine — **2.3.0**
- ✅ Batch D — Template System — **2.4.0**
- ✅ Batch E — Action Suite (tracking, notes, pickup, label, mailto) — **2.5.0**
- ✅ Batch F — WC Brand Wrapper — **2.4.0** (bundled with D)
- ✅ Batch G — Polish (pending-recovery, resend, preview) — **2.6.0** (this release)
- ⏳ Phase H — Review request T+14d after completed — **deferred per Q5**

The Active Order System is feature-complete relative to the master plan.

---

## 2.5.0 — 2026-05-23 (Batch E — Action Suite: tracking, notes, pickup, label, mailto)

Adds 4 new SSO actions + a tracking-number input field on the existing "Versandt" flow. The salon staff now has 9 one-click actions from every admin email, covering the daily workflow end-to-end.

### E.1 · Confirm page supports user inputs

- **Extended action spec** with optional `'inputs'` array (`name`, `type` of `text` or `textarea`, `label`, `placeholder`, `required`, `rows`).
- **Confirm GET** renders fields under the order summary, above the Bestaetigen-Button. Honest Sie-form labels, red required-asterisk for mandatory fields.
- **Confirm POST** collects `wa_sso_input_<name>` fields → sanitizes per type (`sanitize_text_field` / `sanitize_textarea_field`) → merges into `$args` server-side before invoking the handler. Required-field check happens BEFORE token consume — invalid form doesn't burn the token.

### E.2 · Combined "Versandt + Tracking" + 4 new actions

- **`mark_completed` extended** with optional `tracking_number` text input. On confirm: status → `completed`, and if tracking-number provided: saved to `_tracking_number` order-meta + private order note logged + `wa_sso_tracking_number_added($order_id, $tracking)` action fired. Downstream tracking-email plugins can hook this.
- **`add_internal_note`** — textarea (5 rows), required. Saves a private WC order note prefixed with the staff member's display name. Shows up in the Bestell-Verlauf-Block of the next admin email (built in Batch A).
- **`mark_picked_up`** — for local in-store pickup. Sets status → `completed` with note "Vor Ort abgeholt — erfasst von {Name}." Also writes `_picked_up_locally=1` + `_picked_up_at` meta for downstream reporting. Idempotent if already completed.
- **`print_label`** — safe action (no confirm). Logs in, redirects to `apply_filters( 'wa_sso_print_label_url', $default, $order_id )`. Default: WC order edit screen. Austrian-Post or similar plugins can hook the filter to return their print URL.
- **`contact_customer`** — safe action. Generates a `mailto:` URL with prefilled `Subject: Ihre Bestellung #1234 bei Dr. Peri Skincare` and a Sie-form greeting in the body. Opens the staff member's local email client. No outbound mail from our system — they reply from their real address. Filterable via `wa_sso_contact_customer_mailto`.

### E.3 · Email injection: primary + secondary button rows

Visual hierarchy in the WC New Order admin email:

**Primary row** (large coloured buttons):
- Bestellung ansehen · In Bearbeitung · Als versandt markieren · Stornieren

**Secondary row** (smaller text-links, dot-separated):
- Vor Ort abgeholt · Notiz hinzufuegen · Label drucken · Kundin schreiben

Keeps the visual weight on the 4 daily actions without losing access to the utility ones.

### New hooks for downstream integrations

| Hook | Type | Args | Use |
|---|---|---|---|
| `wa_sso_after_status_change` | action | `$order_id, $target_status, $args` | Fires after any SSO-driven status change. Used internally to save tracking-number on `completed`. |
| `wa_sso_tracking_number_added` | action | `$order_id, $tracking_number` | Fires when a tracking-number is saved via the SSO flow. Hook this from a tracking-email plugin to notify the customer. |
| `wa_sso_print_label_url` | filter | `$default_url, $order_id` | Override the print-label redirect URL — Austrian-Post / DHL / other plugins return their own. |
| `wa_sso_contact_customer_mailto` | filter | `$mailto_url, $order` | Customise the mailto: (e.g. add cc:, change body template). |

### Smoke tests (all pass)

- 9 actions correctly registered (4 safe + 5 confirm; 2 with input fields)
- `add_internal_note` saves a note to order #2159 ✓
- `mark_picked_up` is idempotent on already-completed orders ✓
- `wa_sso_build_mailto_for_order(2159)` returns a valid mailto: with RFC2396-encoded subject + greeting body ✓
- `print_label` default redirect lands on the HPOS admin edit URL ✓
- WC New Order admin email rendering finds all 8 button labels ✓

### Notes / limitations

- No Austrian Post plugin currently installed → `print_label` falls back to the WC order edit screen. When/if drperi installs the Austrian-Post WordPress integration, one filter hook (`wa_sso_print_label_url`) wires it up — no code change here needed.
- `order-status-tracking-emails-for-woocommerce` plugin is currently INACTIVE. If reactivated, the `wa_sso_tracking_number_added` hook can be wired to its email trigger.
- Customer-facing emails (`contact_customer` mailto, recovery/apology workflow templates) still depend on Postmark account approval for non-drperi.at recipients. Account is in review.

---

## 2.4.0 — 2026-05-23 (Batch D + F — Klinik-Branded Email-Templates + WC Brand-Wrapper)

The workflow engine now sends emails that look like Dr. Peri — not like a generic admin notification. Combined with Batch F (Q8: "Jetzt"): the standard WC customer emails (Bestellbestätigung, Versand, Refund) get the same brand wrapper, so customers see one coherent visual identity across the entire purchase flow.

### D.1 · File-based template system

New directory `includes/sso/email-templates/`:

- **`_partials/header.php`** — full HTML doctype, brand logo (from Divi theme option `et_divi.divi_logo`), 3px accent-line below the logo in brand primary color, optional H1 heading. Outlook-safe `<table>`-based layout, max-width 600px.
- **`_partials/footer.php`** — brand-name, address (HTML-stripped from ACF footer field), phone + email line with mailto/tel links, site URL. Color matches our muted-grey footer convention (`#9aa9b6`).
- **`admin-reminder.php`** — Klinik-sachlich, no-aggression tone. Order summary table (Bestellung / Kundin / Summe / Status). SSO-Button for one-click WP-Admin entry. Footer hint about next reminder timing.
- **`admin-escalation.php`** — eskalations-Banner (`#d63638` background), explicit "Entscheidung erforderlich" copy. CTA button in escalation-red. Auto-cancel countdown.
- **`customer-recovery.php`** — soft-follow-up after failed payment. Personalised greeting (`Liebe Frau {Lastname}`), reassuring tone ("manchmal werden Kreditkarten blockiert"), 3 paths forward (retry-link / phone / email reply).
- **`customer-apology.php`** — 3-day-unshipped apology to customer. Klinik-honest copy. View-order CTA + direct contact line.

All templates are **Sie-form throughout**, Klinik-style copy (not babysprache, not aggressive — matches Q6).

### D.2 · Template-loader + brand-asset resolver

- **`wa_workflow_brand_assets()`** in `workflow.php`: reads logo from Divi theme option, primary color from CSS tokens (`#475e76` matches `--color-accent`), contact from ACF Footer-options (`address` / `phone` / `email` via existing WPML-aware helpers). Filterable for portability.
- **`wa_workflow_render_template( $slug, $vars )`**: loads PHP file from `email-templates/`, injects vars via `extract()`, captures via output buffer. Returns empty string if file missing → render dispatcher falls back to the inline renderer.
- **`wa_workflow_subject_for()`**: per-channel + per-tone subject builder. Examples: `[Dr. Peri Skincare] DRINGEND: Bestellung #2159 — Entscheidung nötig` / `Brauchen Sie Hilfe bei Ihrer Bestellung #2159? — Dr. Peri Skincare`.
- **`wa_workflow_render_template_vars`** filter for downstream extension.
- Verified all 4 templates render against order #2159: logo URL inlined, brand primary color inlined, phone number inlined. Subjects are professional.

### F · WC standard customer email brand wrapper (new file `wc-email-brand.php`)

Wrap the WooCommerce-default customer emails (Bestellbestätigung, On-Hold, Processing, Completed, Refunded, Failed) in the same drperi brand without modifying WC's settings or rewriting WC's templates.

- **6 `pre_option_*` filters** on `woocommerce_email_base_color`, `_background_color`, `_body_background_color`, `_text_color`, `_footer_text_color`, `_header_image` — return our brand values at runtime without writing to the DB. WC-Settings-UI stays editable; if drperi ever wants to override, just adjust there and our filters return the user's saved value.
- **`woocommerce_email_styles` filter** (priority 99) — appends ~20 lines of CSS at the end of WC's compiled stylesheet: 8px rounded corners, 3px accent-color header border, button-style harmonisation. Plays nice with Emogrifier (WC's CSS-to-inline-style processor).
- **Default "Additional Content"** for Processing + Completed customer emails — Sie-form, drperi-tone, phone + email signature included. Only applied if the WC admin hasn't already set custom content.
- **Master toggle** via `wa_wc_email_brand_active` filter, default tied to SSO `enabled` setting. Disabling SSO disables the wrapper.
- **Verified end-to-end**: real `WC_Email_Customer_Processing_Order` render through `style_inline()` returns HTML with `#475e76` (17 hits), `#9aa9b6` footer color (4 hits), `border-bottom: 3px solid` header accent line, drperi logo URL, custom additional content.

### Net result

Before: generic-purple WC emails for customers, raw inline HTML for our workflow reminders.

After: every email — whether a customer's Bestellbestätigung or a 3am admin reminder — uses the same logo, same `#475e76` primary, same `#9aa9b6` footer color, same Sie-form Klinik-tone. Consistent identity across all channels.

### What's next

The active order system is now visually coherent and functionally complete (engine + templates + brand). Remaining batches:

- **Batch E** — Tracking-Number-Flow (combined "Versandt + Tracking-Nr" confirm page + Austrian-Post-Plugin integration) + remaining SSO actions (`add_internal_note`, `mark_picked_up`, `print_label`, `contact_customer`).
- **Batch G** — Logs-UI polishing + Test-Tab for per-template preview-send.
- **Phase H** (later) — Review request T+14d after completed.

---

## 2.3.0 — 2026-05-23 (Batch C — Workflow-Engine: zeitgesteuerte Reminders + Recovery + Eskalation)

The "Active Order System" goes live. Hourly cron scans orders in defined statuses and dispatches reminders, customer recovery emails, and admin escalations — all configurable from one settings panel.

### C.1 · New file `includes/sso/workflow.php`

- **New custom table `{prefix}wa_workflow_log`** (auto-installs on init priority 6, idempotent via `wa_workflow_db_version` option). Schema: `order_id`, `rule_slug`, `sent_at`, `channel`, `recipient`, `send_status`, `send_error`. Indices on order_id + rule_slug + sent_at.
- **6 pre-seeded rules** matching the master-plan decisions:
  - `processing_reminder` — admin reminder, T+24h, repeat 24h, max 5
  - `processing_customer_apology` — customer apology, T+72h, once (Q4: default-on)
  - `failed_customer_recovery` — customer follow-up, T+2h, once
  - `failed_admin_reminder` — admin reminder, T+24h, once
  - `failed_admin_escalation` — admin escalation, T+72h, once
  - `pending_customer_recovery` — customer recovery, T+30min, once (Q3: **default-off**)
- **Hourly cron `wa_workflow_scan`** (registered on `wp` action, runs +10min from now first time). Scan loop:
  - Skip if SSO globally disabled or workflow paused (Q7)
  - For each enabled rule: `wc_get_orders( status=trigger_status, limit=200 )` + PHP-side age check (avoids HPOS/CPT date-query fragility)
  - Throttle: `max_repeats` count check + `repeat_minutes` interval check
  - Render + send via `wp_mail` + log to `wa_workflow_log`
- **Status-change cleanup** via `woocommerce_order_status_changed` hook (priority 50): when an order leaves a trigger status (e.g. processing → completed), all log entries for rules whose `trigger_status` no longer matches are deleted. Result: no orphan reminders.
- **Built-in renderer** (admin + customer variants, 4 tones: reminder / escalation / apology / recovery). Will be superseded by file-based templates in Batch D.
- **Hooks** for downstream extension: `wa_workflow_pre_send` (filter), `wa_workflow_sent` + `wa_workflow_scan_complete` (actions).

### C.2 · Settings-UI: "Workflow-Regeln" section + Pause-Toggle (Q7)

- **New section between Empfaenger-Status and Selbst-Test** on Settings → SSO Login (under Dr. Peri).
- **Global Pause-Toggle** ("Urlaubs-Modus"): cron runs but sends nothing. Verhindert peinliche "Wo bleibt mein Versand?"-Reminders während Schließzeit.
- **Per-rule grid**: Enable/Disable, after_minutes, repeat_minutes (0 = einmalig), max_repeats. Trigger-status + channel + tone are read-only badges (configured in code).
- **"Scan jetzt manuell ausfuehren"-Button** for testing without waiting for the hourly cron.
- **Activity-log table** (last 25 entries): timestamp, rule, order, channel, recipient, send status with inline error if failed.
- Two new `admin-post` handlers: `wa_sso_workflow_save` (form submit) and `wa_sso_workflow_run_now` (test scan trigger).

### C.3 · Uninstall + end-to-end smoke test

- `uninstall.php` extended: drops `wp_wa_workflow_log` table, deletes options `wa_workflow_rules` / `wa_workflow_paused` / `wa_workflow_db_version`, unschedules `wa_workflow_scan` cron.
- **End-to-end smoke test passed** (1 real Postmark email sent to office@drperi.at):
  - Synthetic processing order backdated 48h via `$order->set_date_modified()` ✓
  - Scan matched the order under `processing_reminder` rule ✓
  - Generated subject + HTML body with SSO "Ansehen"-button + order context ✓
  - `wp_mail` returned true (Postmark accepted same-domain send despite pending-approval) ✓
  - Log entry created with `send_status='sent'` ✓
  - Re-scan: 0 new entries (throttling enforced) ✓
  - Status change processing → completed: cleanup hook deleted the log entry ✓

### Why this changes the daily reality

- Staff no longer needs to remember "have I shipped that 2-day-old order?" — the system will remind them at T+24h with a one-click email.
- Customers with failed payments get a personal recovery email 2h later — not a cold "your payment failed" but a "brauchen Sie Hilfe?" with a retry link.
- After 3 days of unshipped processing → customer gets an "entschuldigen Sie" email AND staff gets a 🚨 escalation reminder.
- All of this can be paused with one checkbox before going on holiday.

### Limitations + next steps

- Default templates are inline-rendered (functional but bare). **Batch D** replaces them with a file-based template system + Klinik-branded partials (header/footer).
- Customer recovery emails to non-drperi.at addresses will be blocked by Postmark until the account is fully approved (currently in review). Same-domain sends (admin → office@drperi.at) work today.
- Reminder copy is generic — Batch D adds proper Sie-form Klinik-touch copy with the proper tone-of-voice per scenario.

---

## 2.2.0 — 2026-05-23 (Batch A — Foundations of the Active Order System)

Three coordinated changes that turn the SSO module from "click email = action" into a distraction-tolerant workflow primitive. Sets the stage for the reminder engine + recovery flows in Batch B-D.

### A.1 · Token consumption refactor (the big architectural shift)

Tokens are no longer consumed at the moment of clicking the email link. For **confirm-actions** (state-changing — `mark_processing`, `mark_completed`, `cancel_order`), the token stays alive until the user actually clicks "Bestaetigen" on the confirm page.

- **`includes/sso/login.php`**: removed the unconditional `wa_sso_consume_token()` call. Auth cookie still gets set on every successful login click. For confirm-actions: redirect to `/?wa_sso_confirm=TOKEN` (token in URL value). For safe-actions (`view_order`, `dashboard`): consume immediately as before.
- **`includes/sso/confirm.php`**: complete rewrite of the endpoint handler.
  - URL/POST param value is now the raw token (was `1`).
  - `action_slug` + `action_args` are read from the **token row server-side** (was: passed via form fields → tamperable).
  - **Replaced WP nonce** with token-based CSRF — the token IS the CSRF token (single-use + bound to user + bound to action). Stronger than WP nonces, no 12-24h nonce-expiry problem.
  - **Session auto-recovery**: if the auth cookie expired between login click and confirm POST (e.g. user comes back after 3 days), the confirm endpoint re-establishes the session from the token's user_id. Token IS authentication proof.
  - POST handler: validate token → consume atomically → run handler. Single-use guarantee enforced at the place where it matters (state change), not at login.
- **Smoke-tested via curl** (6 assertions, all pass):
  - Login click does NOT consume token ✓
  - Token survives second login click (distraction tolerance) ✓
  - Confirm POST consumes + executes (`completed → processing`) ✓
  - Replay blocked with 403 "bereits benutzt" ✓
  - Final landing on WC order edit screen ✓
  - Session auto-recovery (cookie clearance test) ✓

**Email scanner threat model unchanged**: scanners GET-prefetch the login link → get a session cookie they don't use → redirect to confirm page → render form → don't POST. Token stays alive for the real user.

### A.2 · Token TTL bumped to 7 days default, max 14 days

Clinic-style workflow: staff is constantly with customers; a 15-minute link is unusable in practice. New defaults make the link survive weekends.

- **`includes/sso/00-bootstrap.php`**: default `token_ttl_minutes` 15 → **10080** (7 days).
- **`includes/sso/tokens.php`**: clamp max 1440 → **20160** (14 days).
- **`includes/sso/settings.php`**: TTL select options expanded — 15 min / 1 h / 4 h / 24 h / 3 d / 7 d / 14 d. Custom values outside the list are preserved and labeled "(benutzerdefiniert)".

Existing user settings are NOT touched — they keep their previously-chosen TTL until the next Save in the UI.

### A.3 · Order history block at the bottom of admin emails

Every admin email (`new_order` + future reminders) now ends with a self-contained context block so staff doesn't need to open wp-admin to see what's going on.

- **`includes/sso/emails.php`**: new `wa_sso_render_order_history_block()` adds:
  - Current status (translated label, e.g. "In Bearbeitung") + timestamp of last modification.
  - Last 5 internal order notes (newest first), each with timestamp + author + truncated content (200 chars max so emails stay compact).
  - Empty-state message when there are no notes yet.
- **Existing "Schnellaktionen" block** now shows the actual TTL from settings (was hardcoded "15 Min"). New helper `wa_sso_format_ttl_label()` renders minutes/hours/days correctly.
- Verified on a real order render: all expected fragments present (Bestell-Verlauf header, Aktueller Status line, dynamic TTL label).

---

## 2.1.5 — 2026-05-23 (Formal-Sie throughout SSO admin copy)

Dr. Peri is a clinic-style salon — formal "Sie" address everywhere, no informal "du". Audit + conversion of all user-facing German strings in the SSO module.

- **settings.php** (6 strings): "Deine Benutzerrolle" → "Ihre Benutzerrolle"; "Klick auf einen Button" → "Klicken Sie auf einen Button"; warning-banner "Loesung" rewritten with "tragen Sie ... ein" / "legen Sie ... an"; role-denied hint "Fuege/weise" → "Fuegen Sie/weisen Sie"; no-user hint "Lege/aendere" → "Legen Sie/aendern Sie"; invalid-email hint "Pruefe" → "Pruefen Sie".
- **actions.php** (1 string): cancel-order refusal message now says "bitte oeffnen Sie sie im WooCommerce-Admin" (was: "bitte im Admin oeffnen", neutral but ambiguous).
- Audited every German string in `tokens.php`, `login.php`, `confirm.php`, `emails.php`, `00-bootstrap.php` — all already Sie-form or impersonal/passive (e.g. "Dieser Link wurde bereits benutzt", "Sie duerfen diese Aktion nicht ausfuehren"). No changes needed there.
- Code comments + dev docs (`docs/SSO.md`, `CHANGELOG.md`) stay in English/mixed-German — those are for developers, not the salon staff.

---

## 2.1.4 — 2026-05-23 (SSO menu placement + remove product-content-prefill)

**Move SSO Login from Settings → Dr. Peri** (project-side), while keeping the SSO module portable.

- **New filter `wa_sso_settings_menu_parent`** in `includes/sso/settings.php`. Default `'options-general.php'` (= Einstellungen) so the module stays self-contained. Project hooks it to `'dr-peri'` from `admin/admin-menu.php` — one line of project glue, zero coupling.
- **New helper `wa_sso_settings_url( $extra_query = array() )`** that builds the right URL based on the resolved parent (`admin.php?page=…` for custom parents, `options-general.php?page=…` for native Settings parent). All 3 admin-post redirect handlers in `settings.php` now use it.
- Menu registration moved from `add_options_page()` to `add_submenu_page()` with the filterable parent + admin_menu priority 999 (so the Dr. Peri ACF Options parent exists when we hook in).

**Remove the product-content-prefill feature** (was a one-time helper for bulk-pre-filling product Facts / Keypoints / FAQ with placeholder text from the product title — no longer needed now that real content is in place).

- Archived `admin/product-content-prefill.php` → `~/WEB/Archive/plugin-cleanup-2026-05-23/` (reversible — `mv` back if ever needed).
- Removed the **Produkt-Inhalte** submenu entry from `admin/admin-menu.php`.
- Cleaned 3 stale references from `admin/admin-docu.php` (file tree, AJAX-actions table, auto-loader file listing) and 2 from `docs/ARCHITECTURE.md` (admin/ module map, AJAX entry).
- AJAX action `wa_prefill_product_content` and helpers `wa_pcp_*` are gone from the runtime (file is archived; auto-loader no longer picks it up).
- Any ACF content the feature previously wrote stays on the products (the data is real product content now, not placeholder).

---

## 2.1.3 — 2026-05-23 (SSO 5th action: `cancel_order`)

- **New action `cancel_order`** restricted to unpaid statuses (`pending`, `on-hold`). Refuses paid orders (`processing` / `completed`) with a clear hint pointing the staff to the WC refund flow — prevents the stuck-money scenario where a non-technical user cancels a paid order without issuing a refund. Idempotent on already-cancelled orders.
- **Auto-injected** as a 4th button (red `#d63638`, label "Stornieren") in the WC New Order admin email.
- **Smoke-tested** end-to-end via PHP eval: refuse on completed (✓), succeed on fresh pending (✓), idempotent on already-cancelled (✓), action registered correctly (✓).
- Docs: `docs/SSO.md` + `docs/SSO-readme.txt` updated to list 5 built-ins instead of 4. Phase-2 "other actions" list now includes `on_hold_paid` (advance bank-transfer orders from `on-hold` to `processing` — lives in the on-hold admin email, not new-order).

---

## 2.1.2 — 2026-05-23 (SSO UX: end the silent-bail)

The most common reason "no buttons appear in the email" is a perfectly valid SSO config combined with a WC New Order recipient that doesn't map to a WP user — the inject function silently returns, no error, no log, total confusion. This release makes that condition impossible to miss.

- **New** "Empfaenger-Status" section on Settings → SSO Login: lists each WooCommerce New Order recipient with a green/yellow/red status icon, the matched WP user (if any), the user's role, and an inline fix instruction. Three states: ✓ mapped to allowed role → buttons inject; ⚠ mapped but role not in whitelist; ✗ no matching WP user.
- **New** yellow warning banner at the top of the settings page when zero recipients map to allowed users — explains *why* SSO buttons aren't appearing and links directly to WC → Emails → New Order and to WP Users.
- **New** "Selbst-Test" section: one-click button that sends a real SSO test email (dashboard token) to the logged-in admin. Bypasses the WC test-email feature entirely (which uses dummy order data and ignores the "send to" field for our hook) — gives instant end-to-end verification without needing to fix the WC recipient first.
- Public helpers added: `wa_sso_analyze_wc_recipients()` returns the per-recipient mapping result (also usable from custom dashboards). `wa_sso_render_recipient_warning_banner()` and `wa_sso_render_recipient_panel()` render the UI.

---

## 2.1.1 — 2026-05-23 (SSO bugfix — buttons now actually inject)

- **Fix** `includes/sso/emails.php`: the WC email-object guard used `method_exists( $email, 'id' )`, but `WC_Email::$id` is a property — not a method. The check always returned false, so the injection function silently bailed for every admin email. Replaced with `isset( $email->id )`.
- Verified end-to-end: triggering `WC_Email_New_Order` on a real order now generates the expected 3 tokens (view / processing / completed) and the rendered HTML contains the "Schnellaktionen" button row.
- Documented (in this entry, no behaviour change): WC core blocks `WC_Email_New_Order::trigger()` once `_new_order_email_sent` meta is set on an order (since WC 5.0). To re-send for testing, add `add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );`. Real new orders are always notified once — this only matters for re-sends.
- Documented: WC's "Send a test email" feature does NOT use the email address you type in the "send to" field for our injection logic. It still uses the `recipient` from `woocommerce_new_order_settings`. So to test via that UI, make sure the configured Recipient(s) include an address mapped to a WP user with an allowed role.

---

## 2.1.0 — 2026-05-23 (SSO Login module)

**New feature: passwordless email-button login for shop staff (`includes/sso/`).**

- Cryptographic single-use tokens (32-byte entropy, SHA-256 hashed at rest, `hash_equals` comparison, configurable TTL 1–1440 min, 15 min default).
- Custom table `{prefix}wa_sso_tokens` with full audit trail (create + consume timestamps + IPs).
- 4 built-in actions: `dashboard`, `view_order`, `mark_processing`, `mark_completed`. HPOS-aware admin URL routing.
- Email scanner protection: state-changing actions go through a standalone confirm page (WP nonce + cap re-check on POST). Pure-navigation actions skip the confirm.
- Settings page under **Settings → SSO Login**: master switch (off by default), TTL, allowed roles, WC-email auto-injection toggle, audit retention, "revoke all tokens" emergency switch, audit log of last 25 token uses, inline help.
- Auto-injects 3 action buttons (View / In Bearbeitung / Versandt) into the WC `new_order` admin email when a recipient maps to a WP user with an allowed role.
- Helpers: `wa_sso_action_url()`, `wa_sso_button()`, `wa_sso_button_row()`, `wa_sso_register_action()`. Filters: `wa_sso_register_actions`, `wa_sso_inject_into_email_ids`, `wa_sso_allowed_ip`, `wa_sso_trust_forwarded_for`. Actions: `wa_sso_token_created`, `wa_sso_token_consumed`, `wa_sso_login_success`.
- Daily cron `wa_sso_cleanup`: deletes expired-unused tokens immediately, deletes consumed older than `audit_retention_days` (30 by default).
- Auto-loader extended: `core → acf → layout → woocommerce → phorest → sso → shortcodes → admin/`.
- `uninstall.php` extended: drops the SSO table, deletes settings, unschedules cron.
- Full doc: `docs/SSO.md`. WordPress.org-style readme for portable extraction: `docs/SSO-readme.txt`. Translations via plugin textdomain `werbeauf-customs`.

---

## 2.0.2 — 2026-05-23 (docs sync)

**Cleanup, not features.**

- Docs: shortcode + filter tables in both CLAUDE.md files now match reality (13 shortcodes, 6 filters + 1 action; was 7 shortcodes / 2 filters).
- Docs: renamed `WOOCOMMERCE-STATUS.md` → `ORDER-STATUSES.md` (clearer name), fixed stale `phorest-stock-sync.php` paths inside.
- Docs: renamed dated `PLUGIN-ANALYSIS-2026-05-22.md` → evergreen `ARCHITECTURE.md`.
- Docs: added `CHANGELOG.md` (this file) as ongoing release log.
- Docs: added `bin/bump-version.sh` for version propagation.
- Code: verified `wa_phorest_api` duplicate declaration already correctly `function_exists`-guarded (order-sync.php:15 + stock-sync.php:16) — no change.
- Code: verified `templates/header.php` already uses `wa_get_options_field` with raw `get_field` as fallback — no change.
- Code: verified 8 shortcode `get_term_by` sites already use `function_exists`-guarded ternary calling `wa_get_term_by_slug_localized` first — no change.
- No production code changes in this release.

## 2.0.1 and earlier

Pre-changelog history. See `git log` or the analysis snapshot in `docs/ARCHITECTURE.md` for the structural state at 2.0.2.
