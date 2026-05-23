# SSO Login — passwordless email-button login

Self-contained module inside `werbeauf-customs/includes/sso/`. Lets cosmetic-salon staff act on new orders straight from the admin email — one click logs them in, one more click confirms a state-changing action. No password. Tokens are single-use, time-limited, tied to one user and one action.

The module is designed to be **portable** — no ACF dependency, no project-specific globals, standard WP/WC APIs only. Could be extracted to a standalone plugin (see `SSO-readme.txt` for the WP.org-style readme).

---

## Why this design

**Email link prefetching is the threat model.** Outlook ATP, Gmail tab preview, Microsoft SafeLinks, corporate spam filters, and browser link-prefetch features all open every link in incoming email. If the link directly executes "mark as completed", those scanners would mark every order as completed.

**Solution: a two-step flow.**
1. The email link consumes the SSO token + sets the WP auth cookie. This is *safe* to be triggered by a scanner — it just logs in as the recipient (the scanner doesn't have the cookie, so the redirect just yields a confirm page).
2. The confirm page renders a standalone HTML form with a single big "Bestaetigen" button. Submitting it requires a WP nonce + capability check + the active session cookie. Scanners don't do form POSTs.

Net UX: two clicks (email button → confirm), one decision.

For *safe* actions (view-only, dashboard navigation), `confirm => false` skips the confirm page and just redirects after login.

---

## Files

| File | Purpose |
|---|---|
| `00-bootstrap.php` | Constants, settings defaults + getter, role-check helper, self-install of token table on `init` |
| `tokens.php` | Custom-table DDL via `dbDelta`; `wa_sso_create_token` / `validate_token` / `consume_token` / `revoke_user_tokens` / `cleanup_expired` / `recent_tokens` |
| `actions.php` | `wa_sso_register_action` + 5 built-ins (`dashboard`, `view_order`, `mark_processing`, `mark_completed`, `cancel_order`) + HPOS-aware admin-URL helper |
| `login.php` | `?wa_sso=TOKEN` endpoint handler (hooked on `init` priority 9) |
| `confirm.php` | `?wa_sso_confirm=1&action=…&args=…` page (GET render + POST handler), standalone non-admin HTML, success-toast transient |
| `emails.php` | `wa_sso_button()` / `wa_sso_button_row()` / `wa_sso_action_url()` + auto-injection into WC `new_order` admin email |
| `settings.php` | Settings → SSO Login page (enable, TTL, allowed roles, inject toggle, audit retention, revoke-all, audit table, help text) |
| `cron.php` | Daily cleanup via `WA_SSO_CRON_HOOK` |

---

## Database

One custom table: `{prefix}wa_sso_tokens`.

```
id              BIGINT UNSIGNED PK
token_hash      CHAR(64)         -- SHA-256 hex of raw token (raw is never stored)
user_id         BIGINT UNSIGNED  -- WP user the token logs in
action_slug     VARCHAR(64)      -- registered action
action_args     LONGTEXT         -- JSON, e.g. {"order_id":1234}
expires_at      DATETIME         -- UTC
consumed_at     DATETIME NULL    -- UTC, set on first successful use
created_at      DATETIME         -- UTC
created_ip      VARCHAR(45) NULL
consumed_ip     VARCHAR(45) NULL
```

Indices: `UNIQUE token_hash`, `user_id`, `expires_at`. Schema version stored in option `wa_sso_db_version`.

---

## Security model

- **Token entropy**: 32 bytes from `random_bytes()` → base64url (~43 chars). 256-bit.
- **At-rest**: SHA-256 hash only. Raw token exists in the URL once and in the user's inbox.
- **Comparison**: `hash_equals()` (constant-time).
- **Single-use**: `consumed_at` is set atomically in `consume_token()`. The `wpdb->update` with `consumed_at IS NULL` predicate prevents race.
- **Time-limited**: TTL configurable 1–1440 min (default 15).
- **Bound to user + action**: a token for "view order #5" cannot be replayed to "mark order #6 completed".
- **Capability re-check**: at login (in `login.php`), AND again at confirm POST (in `confirm.php`). Even if a token was created for a user who later lost the role, it's blocked.
- **Confirm-page CSRF**: standard WP nonce `wa_sso_confirm_action` on the POST.
- **Auth cookie**: `wp_set_auth_cookie($user_id, false, is_ssl())` — secure flag follows HTTPS, "remember me" off.
- **Revocation**: `wa_sso_revoke_user_tokens($uid)` or via "Alle Tokens zurueckziehen" in settings.
- **Audit**: every create + consume logged in the same table with timestamps + IPs. Settings UI shows last 25.
- **Cleanup**: daily cron deletes expired-unused immediately, deletes consumed older than `audit_retention_days` (default 30).

---

## Recommendations — designing email actions

These are the design principles I'd apply for any new action wired into the email flow:

### 1. Safe vs state-changing → pick `confirm`

- Pure navigation (view-only, dashboard) → `confirm => false`. One click email → done.
- ANY state change (order status, refund, stock, customer note, file delete) → `confirm => true`. Two clicks total.
- When in doubt: `confirm => true`. The friction cost is one extra click. The cost of a scanner accidentally triggering it is high.

### 2. Bind everything to user + action + args

The token row already encodes `user_id + action_slug + JSON args`. Don't put state in the URL beyond what the action callback reads from `$args`. Don't trust query params at the confirm POST — re-read from the form's hidden fields (which were signed by the GET nonce path).

### 3. Capability mapping

Each action declares its `capability`. Built-ins use `edit_shop_orders` (WC stock manager). For new actions:
- WC order changes → `edit_shop_orders`
- Stock adjustments → `manage_woocommerce`
- File operations → `manage_options`
- Customer messaging → `edit_users`

Don't use `manage_options` as a catch-all — it bloats the blast radius if a token leaks.

### 4. Handler return contract

Handlers return an array:
```php
return [
    'success'  => true,
    'message'  => 'Bestellung 1234 ist jetzt "Versandt".',
    'redirect' => optional override URL,
];
```

`success=false` triggers the error page (with `message` shown). On success the message lands in a 5-minute transient and shows as an admin notice after redirect.

### 5. Summary callbacks for the confirm page

Implement `summary_cb` for any state-changing action. The confirm page shows it as a one-line context so the user knows *exactly* what they're about to do.

```php
'summary_cb' => function ($args) {
    return wa_sso_order_summary($args['order_id'] ?? 0);
    // -> "Bestellung #1234 von Maria Musterfrau · 89,90 EUR"
}
```

### 6. Redirect destinations

After a successful state change, redirect to the **WP-admin edit screen** for the changed object. The user lands somewhere with familiar chrome and can immediately verify the change. Don't redirect back to the email page or to a random success page.

### 7. Extending — pattern

```php
add_action( 'wa_sso_register_actions', function () {
    wa_sso_register_action( 'add_tracking_number', [
        'label'       => __( 'Tracking-Nummer hinzufuegen', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => function ( $args, $user_id ) {
            $order = wc_get_order( (int) ( $args['order_id'] ?? 0 ) );
            if ( ! $order ) return [ 'success' => false, 'message' => 'Bestellung nicht gefunden.' ];
            $order->update_meta_data( '_tracking_number', sanitize_text_field( $args['tracking'] ?? '' ) );
            $order->save();
            return [ 'success' => true, 'message' => 'Tracking-Nummer gespeichert.' ];
        },
        'redirect_cb' => fn( $args ) => wa_sso_admin_order_url( (int) ( $args['order_id'] ?? 0 ) ),
        'summary_cb'  => fn( $args ) => wa_sso_order_summary( (int) ( $args['order_id'] ?? 0 ) ),
    ] );
} );
```

Then create tokens for it:

```php
$url = wa_sso_action_url( $user_id, 'add_tracking_number', [
    'order_id' => 1234,
    'tracking' => 'DHL 1234 5678 9012',
] );
echo wa_sso_button( $url, __( 'Tracking eintragen', 'werbeauf-customs' ) );
```

### 8. Other suggested actions for v2

| Action | Use case | Confirm? |
|---|---|---|
| `print_label` | Open shipping-label PDF directly | no (navigation) |
| `add_internal_note` | Add a note to the order (from a Slack-style email button) | yes |
| `on_hold_paid` | Bank transfer arrived → flip `on-hold` to `processing`. Lives in the `on-hold` admin email | yes |
| `refund_partial` | Partial refund from low-stock alert | yes |
| `reorder_from_phorest` | Trigger Phorest restock — wires into `includes/phorest/` | yes |
| `mark_picked_up` | Local pickup confirmation (no shipping) | yes |
| `assign_to_me` | Self-assign for in-house workflow tracking | no |
| `view_customer` | Open customer profile | no |

**Note on `cancel_order` (shipped in 2.1.3):** restricted to `pending` + `on-hold` only. Refuses paid orders (`processing` / `completed`) with a message pointing the staff to the WC refund flow — cancelling a paid order without a refund leaves the customer's money sitting with the shop. Idempotent on already-cancelled orders.

---

## Email integration

### Automatic (current)

`includes/sso/emails.php` hooks `woocommerce_email_after_order_table` for the `new_order` admin email. When the recipient list contains an email mapped to a WP user with an allowed role, it renders 3 buttons (View / In Bearbeitung / Versandt).

**Multi-recipient note:** WC sends one email to all recipients. The current implementation generates tokens for the *first* WP-user recipient only. For per-user tokens, send the email per-recipient — that's Phase 2 work.

### Manual (in custom email templates)

```php
$url = wa_sso_action_url( $user_id, 'view_order', [ 'order_id' => $order_id ] );
if ( ! is_wp_error( $url ) ) {
    echo wa_sso_button( $url, __( 'Bestellung ansehen', 'werbeauf-customs' ) );
}
```

For multiple buttons:

```php
echo wa_sso_button_row( [
    [ 'url' => $view_url, 'label' => 'Ansehen', 'bg' => '#0073aa' ],
    [ 'url' => $done_url, 'label' => 'Versandt', 'bg' => '#00a32a' ],
] );
```

### Extending the inject filter

Restrict or expand which WC emails get button injection:

```php
add_filter( 'wa_sso_inject_into_email_ids', function ( $ids ) {
    $ids[] = 'cancelled_order';
    return $ids;
} );
```

---

## Settings reference

Settings → SSO Login.

| Field | Option key | Default |
|---|---|---|
| Feature aktiv | `enabled` | `false` (master-off) |
| Token-Lebensdauer | `token_ttl_minutes` | `15` |
| Erlaubte Benutzerrollen | `allowed_roles` | `['administrator', 'shop_manager']` |
| In WC-Bestell-Emails einfuegen | `inject_into_wc_email` | `true` |
| Audit-Aufbewahrung | `audit_retention_days` | `30` |

All keys live in option `wa_sso_settings` (autoload=no).

---

## Filters / actions exposed

| Hook | Type | Fires | Use |
|---|---|---|---|
| `wa_sso_register_actions` | action | `init` priority 7, after built-ins registered | Add your own actions |
| `wa_sso_token_created` | action | After successful insert | Audit / monitoring |
| `wa_sso_token_consumed` | action | After successful consume | Audit / monitoring |
| `wa_sso_login_success` | action | After auth cookie set | Notifications / Slack |
| `wa_sso_allowed_ip` | filter | At login endpoint | IP whitelist (return `false` to deny) |
| `wa_sso_trust_forwarded_for` | filter | In `wa_sso_client_ip()` | Trust `X-Forwarded-For` if behind a proxy |
| `wa_sso_inject_into_email_ids` | filter | In WC email auto-injection | Add other email IDs |

---

## Test plan (manual smoke check)

1. **Enable feature.** Settings → SSO Login → check "Feature aktiv" → set TTL to 15 min → check Administrator + Shop-Manager → Save.
2. **Verify table installed.** `wp db query "SHOW TABLES LIKE 'wp_wa_sso_tokens'"` should return the table.
3. **Generate a token by hand.**
   ```bash
   wp eval 'echo wa_sso_action_url( get_current_user_id(), "dashboard", [] );'
   ```
   Open the URL in an incognito window → should land in `wp-admin`.
4. **Reuse the same URL** → should show "Link bereits benutzt" error page.
5. **State-changing action.**
   ```bash
   wp eval 'echo wa_sso_action_url( 1, "mark_processing", [ "order_id" => 123 ] );'
   ```
   Open URL → should land on the standalone confirm page with "Bestellung #123 …" summary → click Bestaetigen → order status should be `processing` and you should land on the order edit screen with a green success notice.
6. **Cap mismatch.** Generate a token for a user without `edit_shop_orders` and a state-change action → should show "Keine Berechtigung".
7. **TTL expiry.** Set TTL to 1 min → generate URL → wait 90 s → open → should show "Link ist abgelaufen".
8. **WC email injection.** Place a test order while the WC admin email recipient is set to a WP-user-mapped address → open the resulting admin email → should see 3 buttons under the order table.
9. **Multi-recipient.** Set recipient to `notawpuser@example.com, shopmgr@example.com` (where shopmgr is a WP user) → should still inject buttons (uses first WP user found).
10. **Revoke all.** Generate 2 tokens, do NOT use them, click "Alle Tokens zurueckziehen" → opening either URL should show error.
11. **Cleanup cron.** `wp cron event run wa_sso_cleanup` → check DB row count drops.
12. **Disable feature.** Settings → uncheck "Feature aktiv" → save → opening a token URL should show "SSO-Login ist deaktiviert".
13. **Uninstall.** `wp plugin uninstall werbeauf-customs --deactivate` → table dropped, options removed, cron unscheduled.

---

## Performance characteristics

- Token creation: one INSERT.
- Token validation + consume: one SELECT + one UPDATE.
- Endpoint hit on every request (`init` priority 9): trivially fast — early `empty( $_GET['wa_sso'] )` return.
- Cron cleanup: two DELETEs per day, indexed on `expires_at` and `consumed_at`. Linear in row count, negligible for any plausible volume.
- No autoloaded options (settings option uses `autoload=false`).

---

## Future work (Phase 2+)

- **Per-recipient email dispatch** so multi-admin sites get one personal token per admin (currently first-found only).
- **Mobile deep links.** Token URLs can also include `app://` schemes for native apps.
- **Slack integration.** Convert each email button into a Slack interactive message button — same token system, different transport.
- **2FA bypass policy.** If the site uses 2FA, decide whether SSO tokens should skip it (UX) or require it post-login (security). Currently bypasses (no integration hook).
- **Rate limiting.** Add per-IP and per-user create-rate limits to prevent token-spray.
- **Webhook receivers.** Some actions could be triggered by external systems (Zapier / n8n) using the same token + action plumbing.
- **WP-CLI commands.** `wp wa-sso create-token <user> <action>` for manual generation + auditing.
