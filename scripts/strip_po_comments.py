#!/usr/bin/env python3
import re
from pathlib import Path


REFERENCE_LINE_PREFIX = "#:"
BUILD_PREFIX = "build/"
LINE_NUMBER_PATTERN = re.compile(r":\d+$")


def normalize_reference_token(token: str) -> str | None:
    token = token.strip()
    if not token:
        return None
    token = LINE_NUMBER_PATTERN.sub("", token)
    if not token.startswith(BUILD_PREFIX):
        return None
    return token


def normalize_block(block: str) -> str:
    lines = block.splitlines(keepends=True)
    build_references: list[str] = []
    seen_references: set[str] = set()
    first_reference_index: int | None = None
    filtered_lines: list[str | None] = []

    for index, line in enumerate(lines):
        if not line.startswith(REFERENCE_LINE_PREFIX):
            filtered_lines.append(line)
            continue

        if first_reference_index is None:
            first_reference_index = len(filtered_lines)

        for token in line[len(REFERENCE_LINE_PREFIX) :].split():
            normalized_token = normalize_reference_token(token)
            if normalized_token is None or normalized_token in seen_references:
                continue
            seen_references.add(normalized_token)
            build_references.append(normalized_token)

    if first_reference_index is not None and build_references:
        for offset, reference in enumerate(build_references):
            filtered_lines.insert(
                first_reference_index + offset,
                f"{REFERENCE_LINE_PREFIX} {reference}\n",
            )

    return "".join(line for line in filtered_lines if line is not None)


def strip_source_references(po_path: Path) -> None:
    original = po_path.read_text(encoding="utf-8")
    blocks = re.split(r"(\n\s*\n)", original)
    normalized_parts = [
        normalize_block(part) if not re.fullmatch(r"\n\s*\n", part) else part
        for part in blocks
    ]
    updated = "".join(normalized_parts)
    if updated != original:
        po_path.write_text(updated, encoding="utf-8")


def main() -> None:
    languages_dir = Path("languages")
    pot_path = languages_dir / "airygen-seo.pot"
    if pot_path.exists():
        strip_source_references(pot_path)

    for po_path in sorted(languages_dir.glob("airygen-seo-*.po")):
        strip_source_references(po_path)


if __name__ == "__main__":
    main()
