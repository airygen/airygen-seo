#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LANG_DIR="${ROOT_DIR}/languages"
POT_FILE="${LANG_DIR}/airygen-seo.pot"

# Edit this list to control supported locales.
SUPPORTED_LOCALES=(
	zh_TW
	zh_CN
	de_DE
	fr_FR
	es_ES
	pt_PT
	pt_BR
	it_IT
	ja
	ko_KR
	ru_RU
	ar
	vi
	id_ID
	ur
	hi_IN
	bn_BD
)

if [[ ! -f "${POT_FILE}" ]]; then
	echo "Error: POT file not found: ${POT_FILE}" >&2
	echo "Please generate it first (e.g. make i18n.check)." >&2
	exit 1
fi

created=0
skipped=0

for locale in "${SUPPORTED_LOCALES[@]}"; do
	target="${LANG_DIR}/airygen-seo-${locale}.po"
	if [[ -f "${target}" ]]; then
		echo "Skip existing: ${target}"
		((skipped+=1))
		continue
	fi

	cp "${POT_FILE}" "${target}"
	echo "Created: ${target}"
	((created+=1))
done

echo
echo "Done. Created ${created} file(s), skipped ${skipped} existing file(s)."
