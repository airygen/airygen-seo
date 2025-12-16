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
OUT_DIR = LANG_DIR / "untranslated"
PO_PREFIX = "airygen-seo-"


def resolve_po_files(lang: str | None) -> list[Path]:
    if lang:
        target = LANG_DIR / f"{PO_PREFIX}{lang}.po"
        return [target]
    return sorted(LANG_DIR.glob(f"{PO_PREFIX}*.po"))


def extract_lang_code(po_path: Path) -> str:
    name = po_path.stem
    return name.replace(PO_PREFIX, "", 1)


def export_po(po_path: Path) -> Path:
    if not po_path.exists():
        raise FileNotFoundError(f"PO file not found: {po_path}")

    po = polib.pofile(str(po_path))
    payload: dict[str, str] = {}
    for entry in po:
        if entry.obsolete:
            continue
        if not entry.msgid:
            continue
        if entry.msgid_plural:
            # Keep this JSON in strict msgid->msgstr shape.
            payload[entry.msgid] = entry.msgstr_plural.get("0", "")
        else:
            payload[entry.msgid] = entry.msgstr

    lang = extract_lang_code(po_path)
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    out_path = OUT_DIR / f"{lang}.json"
    out_path.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    return out_path


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Export airygen-seo PO msgid/msgstr pairs to JSON."
    )
    parser.add_argument(
        "--lang",
        help="Language code (e.g. ko_KR). Omit to export all languages.",
    )
    args = parser.parse_args()

    files = resolve_po_files(args.lang)
    if not files:
        print("No PO files found.", file=sys.stderr)
        return 1

    for po_file in files:
        out = export_po(po_file)
        print(f"Exported: {po_file} -> {out}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
