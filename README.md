# MONA Pay for LearnPress

WordPress plugin that adds a MONA Pay bank transfer (VietQR) payment method to LearnPress: the student pays a course order with a dynamic VietQR, and a signed MONA Pay webhook completes the order so LearnPress grants enrollment.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- LearnPress installed and active
- LearnPress currency set to `VND`
- A MONA Pay account with API client credentials, VietQR/virtual-account details and a webhook secret

## Install

The plugin loads the official [`monapay/php-sdk`](https://github.com/mona-software/monapay-php) from `vendor/autoload.php`, so install the dependencies before packaging:

```bash
git clone https://github.com/mona-software/learnpress-monapay.git
cd learnpress-monapay
composer install --no-dev
sh build-zip.sh
```

`build-zip.sh` creates `learnpress-monapay.zip`. Upload it under **Plugins → Add New → Upload Plugin**, activate LearnPress first, then activate **MONA Pay for LearnPress**.

## Configuration

Open **LearnPress → Settings → Payments → MONA Pay** and fill in:

| Setting | Notes |
| --- | --- |
| Enable/Disable | Turns the payment method on |
| Title, Description | Shown to students at checkout |
| Client ID, Client Secret | MONA Pay API client credentials |
| Account number | VietQR beneficiary account (`owner_number`) |
| Account owner type | `ORG` (organization) or `PER` (individual) |
| Merchant ID, Terminal ID | From your MONA Pay VietQR setup |
| Virtual account prefix | `virtualAccountPrefix`, up to 10 characters |
| Beneficiary name | Name shown on the QR |
| Webhook Secret | Shared secret for webhook signatures |

The method only appears at checkout when the currency is `VND` and every field from Client ID to Webhook Secret is filled in.

In the MONA Pay dashboard, create a webhook with signature type `HMAC_SHA256`, content type `application/json`, and this URL (also shown under the Webhook Secret field):

```
https://your-site.example/wp-json/learnpress-monapay/v1/webhook
```

## Usage

1. A student checks out a course with MONA Pay. The plugin calls the MONA Pay API through the PHP SDK to create a dynamic VietQR for the exact order total, with order code and transfer memo `LP{order_id}`.
2. The order-received page shows the QR, the amount and the transfer memo.
3. When the transfer arrives, MONA Pay posts a flat JSON payload to the webhook. The plugin verifies `X-Mona-Signature` (HMAC-SHA256 over `timestamp.raw_body`) and rejects requests whose `X-Mona-Timestamp` is more than five minutes off.
4. The plugin accepts only income transactions, matches the `LP{order_id}` memo to an order paid with MONA Pay, rejects underpayments, ignores already-processed transaction codes, then calls `LP_Order::payment_complete()` so LearnPress completes the order and grants enrollment.

The webhook is a server-to-server endpoint, so it is authenticated by the HMAC signature and timestamp rather than a WordPress nonce. The settings and checkout forms use the standard LearnPress/WordPress nonces.

### External service

The plugin calls `https://api.monapay.vn` only when a student starts MONA Pay checkout. It sends the order amount, the `LP{order_id}` identifier, the VietQR beneficiary settings and, when available, the payer email. MONA Pay later sends the transaction amount, transfer memo, account number, direction and transaction code to the webhook. Nothing is sent until the merchant configures the method and a student selects it. See [monapay.vn](https://monapay.vn) for the service terms and privacy policy, and [monapay.vn/docs](https://monapay.vn/docs) for the API reference.

## Development

```bash
composer install
composer test
sh tests/check-package.sh
```

`composer test` runs the PHPUnit suite for webhook signatures and payment matching. `tests/check-package.sh` checks the package structure and PHP syntax; it expects `vendor/autoload.php`, so run it after installing dependencies into `vendor/` as shown in [Install](#install).

## License

MIT. See [LICENSE](LICENSE). The bundled MONA Pay PHP SDK is also MIT licensed.

**MONA Pay is part of MONA Cloud by The MONA Group.**
