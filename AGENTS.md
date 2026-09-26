# AGENTS.md

- Read `BRIEF-CHUNG.md`, `BRIEF.md`, and `REPORT.md` before changing this integration.
- Treat `ref/openapi.json`, `ref/llms.txt`, `ref/monapay-php/`, and `ref/woocommerce-monapay/` as the MONA Pay sources of truth.
- Never invent API endpoints or webhook envelopes. MONA Pay transaction webhooks are flat JSON signed as `HMAC-SHA256(timestamp + "." + raw_body)`.
- Keep `monapay/php-sdk` as the API client; WordPress code may only adapt its transport.
- Preserve LearnPress `payment_complete()` because it triggers course enrollment.
- Do not commit credentials, publish, deploy, or send external webhooks.
- Run `composer test` and `sh tests/check-package.sh` before handoff.

