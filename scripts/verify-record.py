#!/usr/bin/env python3
"""
verify-record.py — verify CFI.co archive records with no PHP and no dependencies.

The canonical recipe for `record_sha256` in schema.json's `x-integrity` is written in
terms of PHP's json_encode. That is an implementation detail of how the archive is
BUILT, not a condition of checking it: "any machine can test this" has to mean any
machine. This script reproduces the same bytes in the standard library alone.

    content_sha256 = SHA-256( content_html, UTF-8 )

    record_sha256  = SHA-256( json_encode(record minus record_sha256,
                                          JSON_PRETTY_PRINT
                                          | JSON_UNESCAPED_SLASHES
                                          | JSON_UNESCAPED_UNICODE) )

Mapping PHP's three flags onto json.dumps:

    JSON_PRETTY_PRINT      -> indent=4 with separators (',', ': ').  PHP indents with
                              four spaces and, unlike Python's default, emits no
                              trailing whitespace on the line before a newline — which
                              is what passing separators explicitly guarantees.
    JSON_UNESCAPED_SLASHES -> Python never escapes "/", so this is the default.
    JSON_UNESCAPED_UNICODE -> ensure_ascii=False.

Key order is part of the hash. json.load preserves document order into a dict and
json.dumps re-emits it, so do NOT sort keys and do NOT round-trip through anything
that reorders (json.loads(..., object_pairs_hook=OrderedDict) is unnecessary on
Python 3.7+, but harmless).

Usage:
    python3 scripts/verify-record.py announcements/2012/89-aberdeen-asset-management-wins-best-asset-manager-award-uk-2012.json
    python3 scripts/verify-record.py articles/2026/*.json
    find announcements -name '*.json' -print0 | xargs -0 python3 scripts/verify-record.py

Exit status: 0 if every record checked passes, 1 otherwise.
"""

import hashlib
import json
import sys


def canonical_record_bytes(record: dict) -> bytes:
    """The exact byte string the archive hashes into record_sha256."""
    stripped = {k: v for k, v in record.items() if k != "record_sha256"}
    text = json.dumps(
        stripped,
        indent=4,
        separators=(",", ": "),
        ensure_ascii=False,
    )
    return text.encode("utf-8")


def check(path: str) -> bool:
    with open(path, "r", encoding="utf-8") as fh:
        record = json.load(fh)

    ok = True

    claimed_content = record.get("content_sha256")
    if claimed_content is not None:
        actual = hashlib.sha256(record["content_html"].encode("utf-8")).hexdigest()
        if actual != claimed_content:
            ok = False
            print(f"FAIL {path}\n  content_sha256 claimed {claimed_content}\n"
                  f"                  computed {actual}")

    claimed_record = record.get("record_sha256")
    if claimed_record is not None:
        actual = hashlib.sha256(canonical_record_bytes(record)).hexdigest()
        if actual != claimed_record:
            ok = False
            print(f"FAIL {path}\n  record_sha256  claimed {claimed_record}\n"
                  f"                  computed {actual}")

    if ok:
        print(f"OK   {path}")
    return ok


def main(argv):
    paths = argv[1:]
    if not paths:
        print(__doc__.strip().splitlines()[0], file=sys.stderr)
        print("usage: verify-record.py <record.json> [record.json ...]", file=sys.stderr)
        return 2

    failures = 0
    for path in paths:
        try:
            if not check(path):
                failures += 1
        except (OSError, ValueError, KeyError) as exc:
            failures += 1
            print(f"ERROR {path}: {exc}")

    total = len(paths)
    print(f"\n{total - failures}/{total} record(s) verified.")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
