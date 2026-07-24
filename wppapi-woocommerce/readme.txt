=== WPPAPI para WooCommerce ===
Contributors: iproute-tecnologia
Tags: whatsapp, woocommerce, order notifications, wppapi, lgpd
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transactional WooCommerce order updates via WhatsApp (WPPAPI): created, paid, shipped and completed — with LGPD opt-in and retry.

== Description ==

**WPPAPI para WooCommerce** sends order notifications via WhatsApp using the managed [WPPAPI](https://api.wpp-api.com) API (Z-API-style per-instance gateway). The plugin UI is in Brazilian Portuguese; this readme follows the WordPress.org directory guidelines in English.

The plugin is **transactional by design**: it only sends order updates to customers who explicitly opted in at checkout. This keeps your store aligned with the Brazilian LGPD and protects your WhatsApp number from spam-related bans.

= Available events =

Each event can be enabled/disabled individually and has an editable message template:

1. **Order created** — fired when a new order is placed.
2. **Payment approved** — when the order moves to "Processing".
3. **Order shipped** — fired from the "WhatsApp (WPPAPI) — Envio" meta box on the order screen: enter the tracking code and click "Salvar e notificar envio". If the order already has a tracking code from another plugin (`_tracking_code` or WooCommerce Shipment Tracking), it is used as fallback for the `{rastreio}` placeholder.
4. **Order completed** — when the order moves to "Completed".

= Placeholders =

`{nome}` (customer first name), `{pedido}` (order number), `{total}` (formatted order total), `{rastreio}` (tracking code).

= Features =

* LGPD opt-in checkbox at checkout ("Aceito receber atualizações do pedido por WhatsApp"), stored on the order and visible in the admin. **No message is sent without opt-in.**
* Automatic normalization of the billing phone to Brazilian E.164 (55 + area code + number, with ninth-digit handling).
* Async sending via Action Scheduler (bundled with WooCommerce), with wp-cron fallback, and retries with 5 min / 30 min / 2 h backoff.
* "Test connection" button that queries the instance status.
* Log of the last 50 messages (time, order, phone, event, status, HTTP code, error).
* HPOS (Custom Order Tables) compatible. The token is stored in an option and never displayed in plain text (last 4 characters only).

== Installation ==

1. Upload the `wppapi-woocommerce` folder to `/wp-content/plugins/` (or install the .zip from the Plugins screen).
2. Activate the plugin. WooCommerce 8.0+ must be active.
3. Go to **WooCommerce → WPPAPI** and enter the Base URL (default `https://api.wpp-api.com`), Instance ID and Token of your WPPAPI instance.
4. Click **Save settings** and then **Test connection**.
5. Adjust the message template of each event and save.

== Frequently Asked Questions ==

= Can this plugin be used for marketing blasts? =

No. The plugin is transactional by design: it only sends order updates to customers who opted in at checkout. This protects your number from spam bans and keeps the store aligned with LGPD.

= How does the "Order shipped" event work? =

WooCommerce has no native "shipped" status. The plugin adds a **"WhatsApp (WPPAPI) — Envio"** meta box on the order screen: enter the tracking code and click **"Salvar e notificar envio"**. The code is stored on the order (`_wppapi_tracking_code` meta) and the event message is queued. If you use another tracking plugin (`_tracking_code` or WooCommerce Shipment Tracking), its code is used as fallback for the `{rastreio}` placeholder.

= What if the customer does not check the opt-in? =

No message is sent for that order. The log records the event as "Ignored (no opt-in)".

= What happens if the API call fails? =

Sending is asynchronous (Action Scheduler, with wp-cron fallback). On failure the plugin retries up to 3 times, with 5 minute, 30 minute and 2 hour intervals. Every attempt is recorded in the log.

= Which phone formats are accepted? =

The order billing phone is normalized to E.164 without "+": inputs with or without the 55 country code and with or without the ninth digit are converted to 55 + area code + number (9-digit mobiles).

= Is the token stored safely? =

The token is saved in a WordPress option, sent only in the `Client-Token` header (never in the URL) and never displayed in plain text — only the last 4 characters.

== Screenshots ==

1. Settings screen (WooCommerce → WPPAPI): credentials, connection test, templates for the 4 events and opt-in text.
2. LGPD opt-in checkbox at checkout.
3. "WhatsApp (WPPAPI) — Envio" meta box on the order screen, with tracking field and "Salvar e notificar envio" button.
4. Log of the last 50 messages with status, HTTP code and error.

== Changelog ==

= 1.0.0 =
* Initial release: 4 order events (created, payment approved, shipped, completed), editable templates with placeholders, LGPD opt-in at checkout, async sending with retry (Action Scheduler with wp-cron fallback), connection test, log of the last 50 messages and HPOS compatibility.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
