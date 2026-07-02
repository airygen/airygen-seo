#!/usr/bin/env python3
"""Lint translated JSON files in languages/untranslated before writing back to PO.

Checks every languages/untranslated/<locale>.json for:
  - empty msgstr values (still untranslated)
  - printf placeholder mismatches between msgid and msgstr (%s, %1$s, %d, %%)
  - brace/variable token mismatches ({token}, ${var})
  - HTML tag mismatches (<code>, <a ...>, ...)

Exit code 1 when any error is found, so it can gate an automated pipeline.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parents[1]
JSON_DIR = ROOT_DIR / "languages" / "untranslated"

PRINTF_RE = re.compile(r"%(?:\d+\$)?[sd]|%%")
TOKEN_RE = re.compile(r"\{[^{}]+\}|\$\{[^}]+\}")
HTML_TAG_RE = re.compile(r"</?[a-zA-Z][^>]*>")


def tokens(pattern: re.Pattern[str], text: str) -> Counter[str]:
    return Counter(pattern.findall(text))


def lint_file(json_path: Path) -> tuple[int, int]:
    data = json.loads(json_path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        print(f"[{json_path.name}] ERROR invalid shape: expected object")
        return 1, 0

    errors = 0
    empty = 0
    for msgid, msgstr in data.items():
        if not isinstance(msgstr, str):
            print(f"[{json_path.name}] ERROR non-string value for: {msgid!r}")
            errors += 1
            continue
        if msgstr == "":
            empty += 1
            continue
        for label, pattern in (
            ("printf placeholder", PRINTF_RE),
            ("brace/variable token", TOKEN_RE),
            ("HTML tag", HTML_TAG_RE),
        ):
            expected = tokens(pattern, msgid)
            actual = tokens(pattern, msgstr)
            if expected != actual:
                print(
                    f"[{json_path.name}] ERROR {label} mismatch\n"
                    f"  msgid:  {msgid!r}\n"
                    f"  msgstr: {msgstr!r}\n"
                    f"  expected {dict(expected)}, got {dict(actual)}"
                )
                errors += 1
    return errors, empty


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--lang",
        help="Language code (e.g. ko_KR). Omit to lint all JSON files.",
    )
    parser.add_argument(
        "--allow-empty",
        action="store_true",
        help="Do not treat remaining empty msgstr values as an error.",
    )
    args = parser.parse_args()

    files = (
        [JSON_DIR / f"{args.lang}.json"]
        if args.lang
        else sorted(JSON_DIR.glob("*.json"))
    )
    files = [path for path in files if path.exists()]
    if not files:
        print("No JSON files found in languages/untranslated.", file=sys.stderr)
        return 1

    total_errors = 0
    for json_path in files:
        errors, empty = lint_file(json_path)
        total_errors += errors
        if empty and not args.allow_empty:
            print(f"[{json_path.name}] ERROR {empty} entries still untranslated")
            total_errors += 1
        else:
            status = "OK" if errors == 0 else f"{errors} error(s)"
            print(f"[{json_path.name}] {status}, {empty} empty")

    return 1 if total_errors else 0


if __name__ == "__main__":
    raise SystemExit(main())
