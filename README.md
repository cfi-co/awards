# CFI.co Awards — Public Transparency Archive

> **A constructive, human-led, finance-and-convergence editorial archive with
> public provenance, machine-readable disclosure, and time-verifiable editorial
> accountability.**
>
> *Public provenance* = every award announcement is version-controlled here in
> the open. *Machine-readable disclosure* = each record is classified by content
> type and sponsorship status (see [Content classification](#content-classification-machine-readable-labels)).
> *Time-verifiable accountability* = the git timestamp chain dates and freezes
> every version and every change (independent Wayback Machine corroboration is
> being added on top).

This repository is a **verbatim, append-only public record of every award
announcement published by [CFI.co](https://cfi.co/awards)**.

Its sole purpose is to let anyone independently verify that **CFI.co does not
quietly alter award announcements after publication**. Every announcement is
committed individually and back-dated to its original publication date. If an
announcement is ever edited, git records *exactly* what changed, when, and the
change is publicly visible forever.

## How the integrity guarantee works

* **One commit per announcement.** The initial import created one commit per
  announcement, with the commit's author date set to the announcement's
  original publication timestamp (UTC).
* **Verbatim content.** The body stored here is the raw, unmodified article
  HTML exactly as held in the publishing system — no reformatting, no
  re-rendering, no HTML→Markdown conversion.
* **Content hashes.** Every record carries a `content_sha256` (SHA-256 of the
  article HTML) and a `record_sha256` (SHA-256 of the full canonical record).
  `MANIFEST.sha256` lists the SHA-256 of every file in the repo.
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
announcements/<year>/<post-id>-<slug>.json    canonical machine record + hashes
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
