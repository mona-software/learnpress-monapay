=== MONA Pay for LearnPress ===
Contributors: themonagroup
Tags: learnpress, vietqr, bank transfer, vietnam, payment
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/license/mit

Automatic bank transfer confirmation for LearnPress with dynamic VietQR, virtual accounts, and signed webhooks.

== Description ==

MONA Pay for LearnPress adds “MONA Pay bank transfer (VietQR)” to the LearnPress checkout. It creates an order-specific VietQR, verifies the signed flat transaction webhook, completes the LearnPress order, and lets LearnPress grant course enrollment.

Funds go directly to the merchant's bank account; MONA Pay does not hold funds. See the current supported-bank list at https://monapay.vn/ngan-hang.

= External service =

This plugin requires a MONA Pay account and calls `https://api.monapay.vn` only when a student starts MONA Pay checkout. It sends the order amount, an `LP{order_id}` identifier, beneficiary configuration, and the payer email when available so MONA Pay can generate the dynamic VietQR. MONA Pay later sends the bank transaction amount, transfer content, account number, direction, and transaction code back to the configured webhook so the plugin can confirm the order. No data is sent until the merchant enables and configures this payment method and a student selects it.

Service: https://monapay.vn/  
Terms: https://monapay.vn/dieu-khoan  
Privacy policy: https://monapay.vn/chinh-sach-bao-mat

Features:

* Dynamic VietQR with the exact tuition and `LP{order_id}` content.
* HMAC-SHA256 verification over the unmodified request body.
* Five-minute replay window and transaction-code idempotency.
* Amount, transfer content, income direction, and payment-method matching.
* English source strings and Vietnamese translation catalog.

== Installation ==

1. Install and activate LearnPress.
2. Upload and activate this plugin.
3. Set the LearnPress currency to VND.
4. Configure Client ID, Client Secret, VietQR values, and Webhook Secret under LearnPress > Settings > Payments > MONA Pay.
5. Configure an HMAC_SHA256 webhook using the callback URL shown in the settings.

Full setup instructions are in README.md. MONA Pay documentation: https://monapay.vn/docs.

== Frequently Asked Questions ==

= Is MONA Pay a wallet that holds my funds? =

No. The bank transfer goes directly to your bank account. MONA Pay confirms the transfer automatically.

= Which banks are supported? =

Do not assume a bank is live. Check https://monapay.vn/ngan-hang for the current list and connection status.

= How is the webhook authenticated? =

The plugin verifies `X-Mona-Signature` with HMAC-SHA256 over `timestamp.raw_body` and rejects requests outside a five-minute time window.

== Screenshots ==

1. MONA Pay selected at LearnPress checkout. (TODO: docs/screenshot-checkout.png)
2. Dynamic VietQR on the order-received page. (TODO: docs/screenshot-vietqr.png)
3. Gateway settings and webhook URL. (TODO: docs/screenshot-settings.png)
4. Completed order and course enrollment. (TODO: docs/screenshot-completed.png)

== Changelog ==

= 1.0.0 =
* Initial release with dynamic VietQR, signed webhook verification, order completion, and PHPUnit coverage.
