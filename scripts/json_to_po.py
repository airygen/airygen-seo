#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
from pathlib import Path
import sys

try:
    import polib
except ImportError:
    print("Missing dependency: polib", file=sys.stderr)
    print("Install with: python3 -m pip install polib", file=sys.stderr)
    sys.exit(1)


ROOT_DIR = Path(__file__).resolve().parents[1]
LANG_DIR = ROOT_DIR / "languages"
JSON_DIR = LANG_DIR / "untranslated"
PO_PREFIX = "airygen-seo-"


def resolve_json_files(lang: str | None) -> list[Path]:
    if lang:
        return [JSON_DIR / f"{lang}.json"]
    return sorted(JSON_DIR.glob("*.json"))


def apply_json_to_po(json_path: Path) -> Path:
    if not json_path.exists():
        raise FileNotFoundError(f"JSON file not found: {json_path}")

    lang = json_path.stem
    po_path = LANG_DIR / f"{PO_PREFIX}{lang}.po"
    if not po_path.exists():
        raise FileNotFoundError(f"PO file not found: {po_path}")

    payload = json.loads(json_path.read_text(encoding="utf-8"))
    if not isinstance(payload, dict):
        raise ValueError(f"Invalid JSON shape in {json_path}: expected object")

    po = polib.pofile(str(po_path))
    po.wrapwidth = 0
    updated = 0
    for entry in po:
        if entry.obsolete:
            continue
        if not entry.msgid:
            continue
        if entry.msgid not in payload:
            continue

        value = payload[entry.msgid]
        if isinstance(value, str):
            if entry.msgid_plural:
                entry.msgstr_plural["0"] = value
            else:
                entry.msgstr = value
            updated += 1
            continue

        # Optional plural shape support:
        # { "Some msgid": { "0": "...", "1": "..." } }
        if isinstance(value, dict) and entry.msgid_plural:
            for plural_key, plural_value in value.items():
                if isinstance(plural_value, str):
                    entry.msgstr_plural[str(plural_key)] = plural_value
            updated += 1

    po.save(str(po_path))
    print(f"Updated {updated} entries in: {po_path}")
    return po_path


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Write msgstr values from JSON back into airygen-seo PO files."
    )
    parser.add_argument(
        "--lang",
        help="Language code (e.g. ko_KR). Omit to apply all JSON files.",
    )
    args = parser.parse_args()

    files = resolve_json_files(args.lang)
    if not files:
        print("No JSON files found.", file=sys.stderr)
        return 1

    for json_file in files:
        apply_json_to_po(json_file)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
