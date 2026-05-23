=== SSO Login — Passwordless Email Buttons for WooCommerce ===
Contributors: werbeauf
Tags: woocommerce, sso, passwordless, magic-link, admin-email, order-management
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let WooCommerce shop staff act on orders straight from admin emails — one click logs them in, one more confirms the action. No password.

== Description ==

SSO Login adds passwordless, one-click action buttons to WooCommerce admin emails (new-order notifications and any others you opt in). Designed for non-technical shop staff (cosmetic salons, boutiques, retail) who shouldn't have to remember a WP password just to mark an order as shipped.

= How it works =

1. A new order arrives. WooCommerce sends the standard admin email.
2. If the recipient address maps to a WordPress user with an allowed role, this plugin injects three buttons under the order table: **View Order**, **Mark Processing**, **Mark Completed**.
3. The recipient clicks **Mark Completed** in their email client.
4. They land on a clean confirm page — already logged in. One more click confirms the action.
5. They're redirected to the WooCommerce order edit screen with a green success notice. The order status is now "completed" and any stock-sync hooks (e.g. native WC inventory, Phorest, custom integrations) have fired.

= Security =

* Tokens are 256-bit random values (`random_bytes(32)`), encoded base64url.
* Stored as SHA-256 hashes in the database — never plaintext.
* Single-use: marked consumed atomically on first successful login.
* Time-limited: 15 minutes default, configurable 1–1440.
* Bound to one user + one action + one set of arguments. A "view order #5" token can't be replayed as "mark order #6 completed".
* WordPress nonce + capability re-check on the confirm POST (defense against email link prefetchers / Outlook ATP / Gmail tab preview that open every link in incoming email).
* Auth cookie secure-flagged on HTTPS sites.
* Daily cron cleanup; admin can revoke all outstanding tokens with one click.
* Full audit log of every token use in the settings page.

= Built-in actions =

* `dashboard` — log in and go to wp-admin.
* `view_order` — log in and open a specific order's edit screen.
* `mark_processing` — log in, confirm, set order status to "processing".
* `mark_completed` — log in, confirm, set order status to "completed".
* `cancel_order` — log in, confirm, cancel the order. Restricted to unpaid statuses (`pending`, `on-hold`); refuses paid orders with a hint to use the WooCommerce refund flow.

= Extension points =

Register your own action:

`add_action( 'wa_sso_register_actions', function () {
    wa_sso_register_action( 'my_action', [
        'label'       => 'My Action',
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'my_action_handler',
        'redirect_cb' => fn( $args ) => admin_url(),
        'summary_cb'  => fn( $args ) => 'Doing my action on …',
    ] );
} );`

Generate a one-click URL:

`$url = wa_sso_action_url( $user_id, 'my_action', [ 'order_id' => 123 ] );`

Render an email-friendly button (inline-CSS, Outlook-tested):

`echo wa_sso_button( $url, 'My Action' );`

Filters: `wa_sso_inject_into_email_ids`, `wa_sso_allowed_ip`, `wa_sso_trust_forwarded_for`. Actions: `wa_sso_register_actions`, `wa_sso_token_created`, `wa_sso_token_consumed`, `wa_sso_login_success`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Settings → SSO Login**.
4. Check **Feature aktiv**.
5. Pick your token lifetime (15 min recommended).
6. Confirm which roles are allowed to log in via SSO (Administrator + Shop-Manager recommended).
7. Make sure your WooCommerce admin email recipients (**WooCommerce → Settings → Emails → New Order**) are mapped to existing WP users with those roles.
8. Save. Place a test order to verify the buttons appear.

== Frequently Asked Questions ==

= What happens if an email client prefetches the link? =

The prefetch hits the login endpoint, consumes the token, and is redirected to the confirm page. The prefetch never POSTs the confirm form, so no state change happens. The first time a *human* opens the email, they may find the token already consumed — they'll see a "link already used" page and can request a fresh notification. In practice the friction is rare; the security guarantee is absolute.

= Can multiple admins use the same recipient address? =

Currently the plugin generates tokens for the *first* WP user mapped to an address in the recipient list. For per-admin tokens, send one email per recipient (planned for v2). Workaround: give each admin their own WC admin email recipient, and they'll each get their own buttons.

= Does this work without WooCommerce? =

The token + login + confirm machinery is WC-agnostic. Only the built-in `view_order` / `mark_processing` / `mark_completed` actions require WooCommerce — they'll silently skip if WC isn't active. You can register your own non-WC actions via `wa_sso_register_action`.

= Does it bypass 2FA? =

Yes — by design, since the intent is one-click access from email. If your site uses 2FA and you want SSO tokens to also require a second factor, hook `wa_sso_login_success` and trigger your 2FA prompt before the redirect.

= GDPR / privacy =

The plugin logs the IP that created and the IP that consumed each token (audit). IP collection can be disabled by returning `null` from a filter on `wa_sso_client_ip` if you wish; see source. Consumed tokens are kept for 30 days by default, then auto-deleted.

== Screenshots ==

1. Settings page under **Settings → SSO Login**.
2. Admin email with injected action buttons.
3. Standalone confirm page.
4. Audit log of recent tokens.

== Changelog ==

= 1.0.1 =
* Add `cancel_order` action. Restricted to unpaid statuses; refuses paid orders to prevent stuck-money scenarios. Auto-injected as a 4th button (red, "Stornieren") in the new-order admin email.
* Fix: WC email-object check now uses `isset( $email->id )` (property) instead of `method_exists( $email, 'id' )` (was always false → silent bail).
* Add: recipient-status diagnosis panel + yellow warning banner on the settings page when no WC New Order recipient maps to an allowed WP user.
* Add: "Selbst-Test" button on settings page — sends a real SSO test email to the logged-in admin (bypasses WC's broken test-email path).

= 1.0.0 =
* Initial release.
* 4 built-in actions: `dashboard`, `view_order`, `mark_processing`, `mark_completed`.
* Settings page with role whitelist, TTL config, audit log.
* Daily cron cleanup of expired + old-consumed tokens.
* WooCommerce HPOS-aware admin URL helper.
* WPML-aware via parent plugin's textdomain.

== Upgrade Notice ==

= 1.0.0 =
First release. Activate, configure under Settings → SSO Login, done.
