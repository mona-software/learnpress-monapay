# MONA Pay for LearnPress

Add **MONA Pay bank transfer (VietQR)** to LearnPress. A student places a course order, sees a dynamic VietQR for the exact tuition and `LP{order_id}` transfer content, then a signed MONA Pay webhook completes the LearnPress order and activates course enrollment.

MONA Pay provides automatic bank-transfer confirmation with dynamic VietQR, virtual accounts, and webhooks. Funds go directly to your bank account; MONA Pay does not hold funds. Supported banks and connection status: https://monapay.vn/ngan-hang.

## Tiếng Việt

### Cài đặt

1. Nén thư mục thành `learnpress-monapay.zip`, cài tại **WordPress → Plugins → Add New → Upload Plugin**.
2. Bật LearnPress trước, sau đó kích hoạt **MONA Pay for LearnPress**.
3. Vào **LearnPress → Settings → Payments → MONA Pay** và nhập Client ID, Client Secret, thông tin VietQR/VA cùng Webhook Secret.
4. Đặt tiền tệ LearnPress là `VND`.
5. Trong MONA Pay Dashboard, tạo webhook `HMAC_SHA256`, payload `application/json`, URL:
   `https://your-site.example/wp-json/learnpress-monapay/v1/webhook`

Form cài đặt và checkout dùng nonce của LearnPress/WordPress. Endpoint webhook là server-to-server nên xác thực bằng raw-body HMAC và cửa sổ thời gian 5 phút, không dùng nonce trình duyệt.

### Luồng chạy

LearnPress tạo order → SDK chính thức `monapay/php-sdk` tạo VietQR → trang nhận đơn hiện QR, số tiền và `LP{id}` → MONA Pay gửi payload phẳng → plugin kiểm `X-Mona-Timestamp` + `X-Mona-Signature`, loại giao dịch không phải tiền vào/trùng/thiếu tiền/sai nội dung → gọi `LP_Order::payment_complete()` để hoàn tất đơn và ghi danh.

### Kiểm thử

```bash
composer install
composer test
sh tests/check-package.sh
```

Ảnh chụp cần bổ sung trước khi nộp: `docs/screenshot-*.png` (xem `docs/screenshots.md`).

## English

### Install and configure

1. Zip this directory as `learnpress-monapay.zip`, then upload it under **WordPress → Plugins → Add New**.
2. Activate LearnPress first, then activate this plugin.
3. Open **LearnPress → Settings → Payments → MONA Pay** and enter the Client ID, Client Secret, VietQR/virtual-account values, and Webhook Secret.
4. Set the LearnPress currency to `VND`.
5. Create an `HMAC_SHA256`, `application/json` webhook in the MONA Pay Dashboard using:
   `https://your-site.example/wp-json/learnpress-monapay/v1/webhook`

The LearnPress settings and checkout forms are protected by the platform's WordPress nonces. The server-to-server webhook authenticates the raw body with HMAC and a five-minute timestamp window.

### Payment flow

LearnPress creates an order → the official `monapay/php-sdk` creates its dynamic VietQR → the received-order screen displays the QR, exact amount, and `LP{id}` → MONA Pay posts a flat payload → the plugin verifies the signature, direction, duplicate transaction code, amount, and transfer content → `LP_Order::payment_complete()` completes the order and grants enrollment.

Documentation: https://monapay.vn/docs · Product: https://monapay.vn

### External service disclosure

This plugin requires MONA Pay and calls `https://api.monapay.vn` when a student starts MONA Pay checkout. It sends the order amount, `LP{order_id}`, VietQR beneficiary configuration, and payer email when available to generate the QR. MONA Pay sends transaction amount, content, account number, direction, and transaction code to the configured webhook for confirmation. No data is sent until the merchant enables/configures the method and a student selects it.

Terms: https://monapay.vn/dieu-khoan · Privacy: https://monapay.vn/chinh-sach-bao-mat

License: MIT. The bundled official MONA Pay PHP SDK is also MIT licensed; see `vendor/monapay/php-sdk/LICENSE`.

Official SDK source: https://github.com/themonagroup/monapay-php
