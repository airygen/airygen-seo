#!/usr/bin/env python3
"""Translate a single Airygen untranslated JSON locale file in batches."""

from __future__ import annotations

import argparse
import json
import re
import sys
import time
from pathlib import Path

from deep_translator import GoogleTranslator
from deep_translator.exceptions import TooManyRequests
from deep_translator.exceptions import TranslationNotFound


PROTECTED_TERMS = sorted(
	{
		"Airygen",
		"Google",
		"WordPress",
		"JSON-LD",
		"SEO",
		"API",
		"URL",
		"URLs",
		"Markdown",
		"Topic Cluster",
		"Table of Contents",
		"Related Posts",
		"Local SEO",
		"Schema Markup",
		"Site Verification",
		"Code Snippets",
		"Link Suggestions",
		"Link Counter",
		"Content Blueprint",
		"Article Builder",
		"SERP CTR Booster",
		"Instant Indexing",
		"Broken Link Checker",
		"Markdown for Agents",
		"LLMs.txt",
		"Robots Meta",
		"On-Page SEO",
		"Social Media Tags",
		"WooCommerce SEO",
		"Author SEO",
		"Taxonomy SEO",
		"Image SEO",
		"Sitewide SEO",
		"Score Calculator",
		"Daily Digest",
		"Microsoft Teams",
		"Telegram",
		"Discord",
		"Elementor",
	},
	key=len,
	reverse=True,
)

PLACEHOLDER_RE = re.compile(
	r"%(?:\d+\$)?[sd]|%%|\{[^{}]+\}|\$\{[^}]+\}|\$[A-Za-z_][A-Za-z0-9_]*|<[^>]+>"
)

EXACT_GLOSSARY = {
	"vi": {
		"Meta tag": "Thẻ meta",
		"Schema Markup": "Đánh dấu dữ liệu có cấu trúc",
		"Structured Data": "Dữ liệu có cấu trúc",
	},
	"id": {
		"Meta tag": "Tag meta",
		"Schema Markup": "Markup data terstruktur",
		"Structured Data": "Data terstruktur",
	},
	"ur": {
		"Meta tag": "میٹا ٹیگ",
		"Schema Markup": "اسٹرکچرڈ ڈیٹا مارک اپ",
		"Structured Data": "اسٹرکچرڈ ڈیٹا",
	},
	"hi": {
		"Meta tag": "मेटा टैग",
		"Schema Markup": "स्ट्रक्चर्ड डेटा मार्कअप",
		"Structured Data": "संरचित डेटा",
	},
	"bn": {
		"Meta tag": "মেটা ট্যাগ",
		"Schema Markup": "স্ট্রাকচার্ড ডেটা মার্কআপ",
		"Structured Data": "স্ট্রাকচার্ড ডেটা",
	},
}


def mask_text(text: str) -> tuple[str, dict[str, str]]:
	replacements: dict[str, str] = {}
	index = 0

	def put(token: str) -> str:
		nonlocal index
		key = f"AGTK{index}X"
		index += 1
		replacements[key] = token
		return key

	masked = text
	for term in PROTECTED_TERMS:
		masked = masked.replace(term, put(term))

	masked = PLACEHOLDER_RE.sub(lambda match: put(match.group(0)), masked)
	return masked, replacements


def unmask_text(text: str, replacements: dict[str, str]) -> str:
	for key, value in replacements.items():
		text = text.replace(key, value)
	return text


def main() -> int:
	parser = argparse.ArgumentParser()
	parser.add_argument("--file", required=True)
	parser.add_argument("--target", required=True)
	parser.add_argument("--chunk-size", type=int, default=20)
	parser.add_argument("--sleep", type=float, default=0.4)
	args = parser.parse_args()

	path = Path(args.file)
	data = json.loads(path.read_text(encoding="utf-8"))
	glossary = EXACT_GLOSSARY.get(args.target, {})
	keys = [key for key, value in data.items() if value == ""]
	total = len(keys)

	if total == 0:
		print(f"{args.file}: nothing to translate", flush=True)
		return 0

	translator = GoogleTranslator(source="en", target=args.target)

	for start in range(0, total, args.chunk_size):
		chunk = keys[start : start + args.chunk_size]
		payload: list[str] = []
		replacements: list[tuple[str, dict[str, str]]] = []

		for key in chunk:
			if key in glossary:
				data[key] = glossary[key]
				continue
			masked, repl = mask_text(key)
			payload.append(masked)
			replacements.append((key, repl))

		if payload:
			translated_values = None
			for _attempt in range(5):
				try:
					translated_values = translator.translate_batch(payload)
					if not isinstance(translated_values, list):
						translated_values = [translated_values]
					break
				except TooManyRequests:
					time.sleep(8.0)
				except Exception:
					break

			if translated_values is None:
				translated_values = []
				for item in payload:
					for _attempt in range(5):
						try:
							translated_values.append(translator.translate(item))
							break
						except TooManyRequests:
							time.sleep(8.0)
						except TranslationNotFound:
							translated_values.append(item)
							break
						except Exception:
							translated_values.append(item)
							break

			for translated, (original_key, repl) in zip(translated_values, replacements):
				data[original_key] = unmask_text(str(translated).strip(), repl)

		if (start + args.chunk_size) % 200 == 0 or (start + args.chunk_size) >= total:
			path.write_text(
				json.dumps(data, ensure_ascii=False, indent=2) + "\n",
				encoding="utf-8",
			)
			print(
				f"{args.file}: translated {min(start + args.chunk_size, total)}/{total}",
				flush=True,
			)

		time.sleep(args.sleep)

	path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
	print(f"{args.file}: done ({total})", flush=True)
	return 0


if __name__ == "__main__":
	sys.exit(main())
