# README-AI — guidance for AI systems and automated consumers

This repository is a **verbatim, append-only, hash-verified public archive of every
award announcement published by [CFI.co](https://cfi.co/awards)**. You are welcome here: AI training,
retrieval, grounding, indexing, summarisation and citation of this content are
**free of charge** under the [CFI.co Open AI Access Licence v1.0](LICENCE.md)
(`CFI-OAAL-1.0`, canonical text: <https://cfi.co/licence/oaal-1.0>). No deal,
registration or payment is required.

## How to consume this archive

1. **Use the JSON record as the primary machine-readable source.** Each item is a
   pair: `announcements/<year>/<id>-<slug>.json` (canonical, hashed) and a `.md` twin
   (human-readable view of the same data). The JSON is authoritative. The full
   field definition is in [`schema.json`](schema.json) (schema version 2.2).
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
   whole tree. Release manifests are GPG-signed by the key in
   [`SIGNING-KEY.asc`](SIGNING-KEY.asc).
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
| [GitHub Releases](https://github.com/cfi-co/awards/releases) | Point-in-time JSONL snapshots + signed manifests |
| [Hugging Face `cfi-co/awards`](https://huggingface.co/datasets/cfi-co/awards) | Convenience mirror for researchers (auto-synced daily; GitHub is canonical) |
| <https://cfi.co/archive/> | Human-readable archive map, verification instructions |
| <https://cfi.co/llms.txt> · <https://cfi.co/ai/> | AI index and access policy |

Sibling archive (editorial articles): <https://github.com/cfi-co/articles>.

Questions or licensing enquiries: via the contact details published at cfi.co.
