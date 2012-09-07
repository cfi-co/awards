# CFI.co Awards — Public Transparency Archive

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
