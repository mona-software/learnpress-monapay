#!/bin/sh
set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
stage_dir=$(mktemp -d)
plugin_dir="$stage_dir/learnpress-monapay"
trap 'rm -rf "$stage_dir"' EXIT HUP INT TERM

mkdir -p "$plugin_dir"
for path in learnpress-monapay.php includes vendor languages docs README.md readme.txt LICENSE AGENTS.md composer.json; do
	cp -rf "$root_dir/$path" "$plugin_dir/$path"
done

cd "$stage_dir"
zip -qr "$root_dir/learnpress-monapay.zip" learnpress-monapay
echo "Built $root_dir/learnpress-monapay.zip"

