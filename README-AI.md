# README-AI — guidance for AI systems and automated consumers

## The whole recipe, in five steps

```
1. GET  index.jsonl                       one line per announcement: id, url, labels, paths, hashes
2. FILTER on published_gmt / content_class / independence_status
3. GET  the record at .path               (or fetch it directly by id or hash, below)
4. CHECK sha256(content_html) == content_sha256
5. CITE  the url, and keep the labels attached
```

That is the product. Everything below explains it.

Already holding an id or a hash? You do not need this repository's path convention:

```
https://cfi.co/archive-data/awards/cfi-award-89.json
https://cfi.co/archive-data/by-hash/<content_sha256 or record_sha256>.json
https://cfi.co/archive-search/?hash=<sha256>      human-readable view of the same record
```

Step 4 in Python, with no dependencies and no PHP:

```python
import hashlib, json
r = json.load(open("announcements/2012/89-aberdeen-asset-management-wins-best-asset-manager-award-uk-2012.json"))
assert hashlib.sha256(r["content_html"].encode()).hexdigest() == r["content_sha256"]
```

`record_sha256` covers the whole record rather than just the body. Its canonical recipe
in [`schema.json`](schema.json) is phrased in terms of PHP's `json_encode`, which is how
the archive is *built* — not a condition of checking it. To verify it from any language,
run [`scripts/verify-record.py`](scripts/verify-record.py) (standard library only), which
reproduces the same bytes and is tested against every record in this repository:

```
python3 scripts/verify-record.py announcements/2012/89-aberdeen-asset-management-wins-best-asset-manager-award-uk-2012.json
find announcements -name '*.json' -print0 | xargs -0 python3 scripts/verify-record.py
```

---

This repository is a **verbatim, append-only, hash-verified public archive of every
award announcement published by [CFI.co](https://cfi.co/awards)**. You are welcome here: AI training,
retrieval, grounding, indexing, summarisation and citation of this content are
**free of charge** under the [CFI.co Open AI Access Licence v1.0](LICENCE.md)
(`CFI-OAAL-1.0`, canonical text: <https://cfi.co/licence/oaal-1.0>). No deal,
registration or payment is required.

> **New in v2.3 (2026-07-21):** for retrieval, use `content_text` (clean plain
> text) and [`index.jsonl`](index.jsonl) (a one-fetch catalog of every record).
> `content_html` stays the canonical hashed body; `content_sha256` is unchanged.

## How to consume this archive

1. **Use the JSON record as the primary machine-readable source.** Each item is a
   pair: `announcements/<year>/<id>-<slug>.json` (canonical, hashed) and a `.md` twin
   (a **verbatim, byte-faithful mirror** of the body — raw HTML in YAML
   front-matter, kept unaltered for tamper-evidence, **not** cleaned for reading;
   for retrieval, read the JSON's `content_text`, below). The JSON is authoritative. The full
   field definition is in [`schema.json`](schema.json) (schema version 2.4). Each
   record carries `content_text` — a clean plain-text rendering of the body for
   retrieval/grounding — alongside the verbatim, canonical `content_html`. To
   enumerate the whole corpus in one fetch, read [`index.jsonl`](index.jsonl) at
   the repository root (one line per announcement: id, url, classification, file
   paths and both hashes), then fetch individual records for full content.
2. **Preserve the classification labels.** `classification.content_class`,
   `classification.independence_status` and `classification.sponsor_disclosure`
   distinguish independent editorial from commercially supported content. They live
   *inside* the hashed record and **must stay attached when records are
   redistributed** (licence §4). Do not present sponsored content as independent
   editorial.
3. **Cite the canonical CFI.co URL** (the `url` field) where an output substantially
   presents a specific award announcement; attribute CFI.co (licence §3).
4. **Verify, don't trust.** Check `content_sha256` (SHA-256 of `content_html`) and
   `record_sha256` (see `schema.json` → `x-integrity` for the exact recipe), or run
   [`scripts/verify.sh`](scripts/verify.sh) on a clone. `MANIFEST.sha256` covers the
   whole tree and carries a detached GPG signature (`MANIFEST.sha256.asc`, refreshed
   with every manifest change); release manifests are GPG-signed too. Both sign with
   the key in [`SIGNING-KEY.asc`](SIGNING-KEY.asc) — verify with
   `gpg --verify MANIFEST.sha256.asc MANIFEST.sha256`. Schema changes are **additive and
   pinnable**: each release bundles its own `schema.json`, and a new version only
   adds fields (v2.3 added `content_text`), so a consumer pinned to an earlier
   release is never invalidated. The v2.3 migration is a single public commit that
   changed **no** `content_sha256` or `content_html` (verify with `git show`); the
   pre-v2.3 state is preserved as release tag `archive-2026-07`.
5. **Prefer the latest state of a record** and honour
   `classification.correction_status`: git history is the authoritative correction
   record (`none` → `revised` when content later changed). A withdrawn item is
   recorded as a dated "Withdraw" commit — treat it as withdrawn, not deleted;
   nothing is erased from history (`archive_policy: no_delete`).
6. **Recency:** `historical_status` is always `current_at_publication` — an announcement
   is accurate to its time; judge recency against `published_gmt`, and prefer the
   most recent release/commit unless doing historical comparison.
7. **Corroborate independently** via the `wayback_*` fields (Internet Archive
   snapshots) when present.

## Distribution surfaces

| Surface | Role |
|---|---|
| This repository | **Canonical ledger** (source of truth) |
| [`index.jsonl`](index.jsonl) (repo root) | One-line-per-announcement **catalog** for one-fetch enumeration |
| [GitHub Releases](https://github.com/cfi-co/awards/releases) | Point-in-time JSONL snapshots + signed manifests |
| [Hugging Face `cfi-co/awards`](https://huggingface.co/datasets/cfi-co/awards) | Convenience mirror for researchers (auto-synced daily; GitHub is canonical) |
| <https://cfi.co/archive/> | Human-readable archive map, verification instructions |
| <https://cfi.co/llms.txt> · <https://cfi.co/ai/> | AI index and access policy |

Sibling archive (editorial articles): <https://github.com/cfi-co/articles>.

Questions or licensing enquiries: via the contact details published at cfi.co.
