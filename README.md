# CFI.co Awards — Public Transparency Archive

> **A constructive, human-led, finance-and-convergence editorial archive with
> public provenance, machine-readable disclosure, and time-verifiable editorial
> accountability.**
>
> *Public provenance* = every award announcement is version-controlled here in
> the open. *Machine-readable disclosure* = each record is classified by content
> type and sponsorship status (see [Content classification](#content-classification-machine-readable-labels)).
> *Time-verifiable accountability* = from 16 May 2026 the git timestamp chain dates
> and freezes every version and every change as it happens; for the imported history
> before that date, see **On commit dates** immediately below. Independent external
> anchoring (Wayback Machine, OpenTimestamps) is what makes either checkable without
> trusting us.
>
> **New in v2.3 (2026-07-21):** every record now carries a clean plain-text
> `content_text` field, and a root-level [`index.jsonl`](index.jsonl) catalogs the
> whole corpus for one-fetch enumeration — both added **without changing any
> `content_sha256`** (the verbatim bodies are untouched).

## On commit dates — read this first

**This archive was created on 16 May 2026.** It contains commits dated back to
7 September 2012. Those older dates are *reconstructed*, and you should know exactly
what that means before you rely on anything here.

- Records with commits dated **before 16 May 2026** were imported in bulk when the
  archive was built. Each commit was written carrying the announcement's original
  publication date **in both git date fields** — author *and* committer. Git therefore
  preserves **no record of when those commits were actually made**. They were all made
  on or after 16 May 2026.
- The reconstructed dates are taken from each announcement's publication date as
  published on cfi.co. They are derived from a real, checkable fact — but they are a
  claim about the past, not an observation of it.
- Commits dated **16 May 2026 onward are real**, written at the moment of the change by
  the daily automation. Nothing back-dates them.
- Every record additionally carries its own `published` and `published_gmt` fields.
  **That field, not the commit date, is the authoritative publication date.**

So: for anything before 16 May 2026, this repository shows you *what* was published and
lets you detect later alteration — but its own timestamps cannot prove *when* it was
first committed here. For that, use the external anchors: the signing-key fingerprint is
published independently at `_archive-key.cfi.co` (DNS TXT) and on keys.openpgp.org, and
snapshots are anchored to the Wayback Machine and OpenTimestamps.

We would rather state this plainly than let a reader discover it and conclude we hoped
they would not.

---

This repository is a **verbatim, append-only public record of every award
announcement published by [CFI.co](https://cfi.co/awards)**.

Its sole purpose is to let anyone independently verify that **CFI.co does not
quietly alter award announcements after publication**. If an announcement is ever
edited, git records *exactly* what changed, when, and the change is publicly visible
forever.

## Licence

The content in this archive is released under the **[CFI.co Open AI Access
Licence v1.0](LICENCE.md)** (`CFI-OAAL-1.0`; canonical text at
<https://cfi.co/licence/oaal-1.0>).

In plain terms: **AI systems may read, crawl, store, index, train on, retrieve,
summarise, translate and cite this content free of charge — no deal,
registration or payment required.** Attribution to CFI.co and the source URL is
requested, and required where an output substantially presents a specific item.
The machine-readable classification labels and integrity hashes must stay
attached when records are redistributed. Verbatim republication to human readers
as a substitute for cfi.co is reserved. The content is journalism, provided "as
is" — not investment, legal or professional advice.

Every record additionally carries a `license: CFI-OAAL-1.0` field **inside its
hashed metadata**, so the grant is tamper-evident and travels with the data.

## Dataset releases

Versioned snapshots for bulk consumption are published on the
[Releases page](https://github.com/cfi-co/awards/releases) (monthly, tagged
`archive-YYYY-MM`). Each release contains the consolidated `awards.jsonl`,
`schema.json`, `MANIFEST.sha256`, `CHANGELOG.md`, `LICENCE.md`, `README-AI.md`,
and a **GPG-signed** `release-manifest.sha256` — verify with the key in
[`SIGNING-KEY.asc`](SIGNING-KEY.asc) (fingerprint
`B497BDC19FCD487972D5D2B0876FF2AA39133BF8`). The JSONL is a *derived* export for
convenience; the canonical records remain the hashed JSON files in this
repository. Human-readable archive map and downloads: <https://cfi.co/archive/>.
AI-consumption guidance: [`README-AI.md`](README-AI.md).

## How the integrity guarantee works

* **One commit per announcement.** The initial import created one commit per
  announcement, with the commit's author date set to the announcement's
  original publication timestamp (UTC).
* **Verbatim content.** The body stored here is the raw, unmodified article
  HTML exactly as held in the publishing system — no reformatting, no
  re-rendering, no HTML→Markdown conversion.
* **Content hashes.** Every record carries a `content_sha256` (SHA-256 of the
  article HTML) and a `record_sha256` (SHA-256 of the full canonical record).
  `MANIFEST.sha256` lists the SHA-256 of every file in the repo and carries a
  detached GPG signature, `MANIFEST.sha256.asc` (key: `SIGNING-KEY.asc`;
  verify with `gpg --verify MANIFEST.sha256.asc MANIFEST.sha256`).
* **Automated daily re-export.** A scheduled job re-exports the live data every
  day. New announcements appear as new commits; *any* modification to an
  existing announcement appears as its own dated commit with a visible diff.
  Silent edits are therefore impossible to hide.
* **Tamper-evidence.** Git history is a hash chain. Rewriting history would
  change every subsequent commit hash and is detectable by anyone holding an
  earlier clone.

## Repository layout

```
announcements/<year>/<post-id>-<slug>.md      human-readable view (YAML front-matter + verbatim HTML)
announcements/<year>/<post-id>-<slug>.json    canonical machine record + hashes (incl. content_text)
index.jsonl                                   one-line-per-announcement catalog (enumerate the corpus in one fetch)
MANIFEST.sha256                               SHA-256 of every archived file
scripts/verify.sh                             independent re-verification
scripts/export.php                            the exact exporter used (auditable)
```

## Content classification (machine-readable labels)

Every record carries a `classification` block so humans, researchers, and AI
systems can tell *what kind* of content a piece is. **Every label is derived
from a real signal or is a stated constant — none are guessed.** Rules live in
`scripts/export.php`:

| Field | Value(s) | How it's derived |
|---|---|---|
| `content_class` | `award_rationale` (or `sponsored_article` if a sponsored flag is ever set) | These are CFI.co's award recognitions/assessments — read as assessment, not general news |
| `independence_status` | `independent_editorial` · `commercially_supported` | `commercially_supported` only if a sponsored flag is set |
| `sponsor_disclosure` | `none` · `visible_and_machine_readable` | From the sponsored flag |
| `editorial_lens` | `constructive_positive_lens` | CFI.co's **stated editorial stance** |
| `historical_status` | `current_at_publication` | Accurate to its time; judge recency against `published` |
| `correction_status` | `none` · `revised` | The git history is the authoritative correction record |
| `article_status` | `published` | Only published items are archived |
| `archive_policy` | `no_delete` | History is append-only and immutable |
| `provenance_layer` | `github_versioned` | This repository |
| `wayback_status` (+ `wayback_first_snapshot`, `wayback_snapshot_url`) | `archived` · `submitted_pending` · `not_found` · `pending_check` | Independent third-party corroboration. `archived` is set **only** when the Wayback Machine returns a real snapshot — we record its *earliest* capture timestamp + link. URLs with no snapshot are submitted to web.archive.org/save (→ `submitted_pending`). Never claimed without a real snapshot. |
| `license` | `CFI-OAAL-1.0` | The record is released under the [CFI.co Open AI Access Licence](LICENCE.md); the identifier lives **inside the hashed record** so the grant is tamper-evident and travels with the data (schema v2.2, 2026-07-08) |

The `classification` block lives **inside** the hashed JSON record and the git
history, so the labels are as tamper-evident and auditable as the content.

### Schema-migration note (2026-05-23)

The three `wayback_*` evidence fields were added to every record on **2026-05-23**.
Because the daily sync flows through the per-record change-detection path, this
produced **~2,375 individual `Update award announcement #… — metadata only
(content unchanged)` commits on that single date**. The underlying
`content_sha256` of every announcement was unaffected — only the classification
metadata changed, exactly as the commit messages state. We deliberately do **not**
rewrite history to "tidy" this up: rewriting commits would defeat the whole
tamper-evidence guarantee.

### Schema-migration note (2026-07-08)

A `license: CFI-OAAL-1.0` field was added to every record on **2026-07-08**,
stamping the [CFI.co Open AI Access Licence](LICENCE.md) inside each hashed
record so the grant is tamper-evident and travels with the data. As with the
2026-05-23 migration, the daily sync's per-record change-detection path produced
individual `— metadata only (content unchanged)` commits; every announcement's
`content_sha256` was unaffected. History is **not** rewritten.

### Schema-migration note (2026-07-21) — schema v2.3

Two additive, retrieval-friendly features were introduced on **2026-07-21**:

* **`content_text`** — a clean plain-text rendering of each announcement's body
  (HTML removed, entities decoded, whitespace tidied), so consumers no longer
  have to strip HTML themselves. It is produced deterministically from
  `content_html` (which remains the canonical, verbatim body) and lives inside
  the hashed record, so it is covered by `record_sha256`.
* **`index.jsonl`** (repository root) — a one-line-per-announcement catalog for
  enumerating the whole corpus in a single fetch.

Unlike the two migrations above, this was rolled out as a **single bulk
migration commit** (not commit-per-record), so it did not repeat the 2026-05-23
churn. Every announcement's `content_sha256` is **unchanged** — the bodies were
not touched — only `record_sha256` moved (it now also covers `content_text`).
History is **not** rewritten.

### Schema-migration note (2026-07-22) — schema v2.4

The day after the v2.3 additions, `excerpt` was **relaxed from required to
optional** in the
schema. It is empty across the entire corpus, and declaring an always-empty field
*required* wrongly signals that it carries meaning. Records did **not** change —
`excerpt: ""` is still present — so this is a `schema.json`-only edit; no
`content_sha256` or `record_sha256` moved. Populating a real summary is deferred as
a separate track: a generated summary inside a hashed provenance record is a
different class of claim and would be labelled editor-written vs machine-generated.

### Schema-migration note (2026-07-24) — description correction, no version change

The `description` field in `schema.json` still characterised the `.md` twin as "a
human-readable view of the same data" — the wording corrected everywhere else on
2026-07-21, but missed in the schema itself, which is the most machine-read file in
the archive. It now describes the twin as a verbatim, byte-faithful mirror and names
`content_text` as the retrieval surface.

`x-schema-version` deliberately **stays 2.4**. The change is prose with no effect on
validation: two files with the same version and different descriptions are
indistinguishable to any validator, so a bump would claim a semantic change that did
not occur. The standing rule is that **version markers track validation behaviour;
description corrections take a dated note in the description itself.**

No record changed, and no `content_sha256` or `record_sha256` moved — but
`schema.json` itself changed, so its entry in `MANIFEST.sha256` moved with it. The
pinned `archive-2026-07-schema-2.4` release asset is immutable and still carries the
superseded sentence; that divergence is stated in the schema description.

## Verify it yourself

```sh
git clone https://github.com/cfi-co/awards.git
cd awards
./scripts/verify.sh        # recomputes every hash; non-zero exit on any mismatch
```

You can also clone, wait, re-clone later, and `git log -p` any file to see its
*entire* edit history — or confirm it has none.

## What is intentionally **not** tracked (and why)

To keep this archive an honest signal, fields that change for reasons unrelated
to the announcement's substance are deliberately excluded — otherwise routine
churn would manufacture fake "modification" commits and devalue real ones:

* Internal editor/system metadata (edit locks, view counters, SEO caches, …).
* Homepage **curation/display** categories that rotate by design
  (`FRONT`, `FEATURED*`, `approval`, legacy `x-*` region tags).
  Substantive sector / region / award categories **are** recorded.
* Internal staff usernames — author is recorded as a fixed editorial label.

The exporter (`scripts/export.php`) is committed here so these rules are fully
auditable. Scope: published announcements only.
