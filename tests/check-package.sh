#!/bin/sh
set -eu

required_files="learnpress-monapay.php includes/functions.php includes/class-learnpress-monapay-gateway.php includes/class-learnpress-monapay-webhook.php vendor/autoload.php vendor/monapay/php-sdk/src/Client.php readme.txt README.md LICENSE AGENTS.md"
for file in $required_files; do
	test -s "$file"
done

grep -q "Text Domain:       learnpress-monapay" learnpress-monapay.php
grep -q "X-Mona-Signature" README.md
grep -q "timestamp.*raw_body" includes/functions.php
grep -q "payment_complete" includes/class-learnpress-monapay-webhook.php
grep -q "LP' .*order->get_id" includes/class-learnpress-monapay-gateway.php
grep -q "monapay/php-sdk" composer.json
grep -q "External service" readme.txt
grep -q "https://monapay.vn/chinh-sach-bao-mat" readme.txt

if grep -R -E "client_secret[[:space:]]*[:=][[:space:]]*['\"][^'\"]{8}" --exclude-dir=ref --exclude='BRIEF*.md' .; then
	echo "Potential hard-coded secret found" >&2
	exit 1
fi

echo "PASS: package structure and security invariants"

if command -v php >/dev/null 2>&1; then
	find . -type f -name '*.php' -not -path './ref/*' -print | while IFS= read -r file; do
		php -l "$file" >/dev/null
	done
	echo "PASS: PHP syntax"
else
	echo "SKIP: PHP syntax (php executable not installed)"
fi

