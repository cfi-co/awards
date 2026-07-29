#!/usr/bin/env bash
# PREFLIGHT (added July 2026 archive review). Exit 2 = "cannot run here"; exit 1 =
# "ran, and the archive did not verify". A stranger must never read the second when
# the first is true, so every environment failure below exits 2, not 1.
if [ -z "${BASH_VERSION:-}" ]; then
  echo "verify.sh requires bash (uses process substitution and pipefail); run: bash scripts/verify.sh" >&2
  exit 2
fi
_missing=""
for _t in php git sha256sum find python3; do
  command -v "$_t" >/dev/null 2>&1 || _missing="$_missing $_t"
done
# sha256sum is GNU; macOS/BSD ship shasum -a 256 or gsha256sum.
if ! command -v sha256sum >/dev/null 2>&1; then
  if command -v gsha256sum >/dev/null 2>&1; then sha256sum(){ gsha256sum "$@"; }; _missing="${_missing/ sha256sum/}"
  elif command -v shasum   >/dev/null 2>&1; then sha256sum(){ shasum -a 256 "$@"; }; _missing="${_missing/ sha256sum/}"
  fi
fi
if [ -n "$_missing" ]; then
  echo "verify.sh cannot run: missing required tool(s):$_missing" >&2
  echo "  (this is an environment problem, not an archive problem)" >&2
  exit 2
fi
# php present is not php working: a php without JSON fails per-record and would be
# read as "the archive is broken" when the machine is.
if ! php -r 'exit(json_encode([1])==="[1]"?0:1);' >/dev/null 2>&1; then
  echo "verify.sh cannot run: php is present but its JSON support is not working" >&2
  exit 2
fi
if [ ! -d announcements ]; then
  echo "verify.sh cannot run: no announcements/ directory here - run it from the repository root" >&2
  exit 2
fi
# Independent re-verification of the CFI.co Awards transparency archive.
# Recomputes content_sha256 + record_sha256 for every announcement and checks
# them against MANIFEST.sha256. Exit 0 = all good; non-zero = mismatch found.
set -euo pipefail
cd "$(dirname "$0")/.."

fail=0 n=0

# 1. Per-record hash check (uses php for exact JSON canonicalisation).
while IFS= read -r -d '' j; do
  n=$((n+1))
  php -r '
    $f=$argv[1]; $r=json_decode(file_get_contents($f),true);
    $want_c=$r["content_sha256"]; $want_r=$r["record_sha256"];
    if (hash("sha256",$r["content_html"])!==$want_c){fwrite(STDERR,"content_sha256 MISMATCH: $f\n");exit(1);}
    unset($r["record_sha256"]);
    $j=json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (hash("sha256",$j)!==$want_r){fwrite(STDERR,"record_sha256 MISMATCH: $f\n");exit(1);}
  ' "$j" || fail=1
done < <(find announcements -name '*.json' -print0)

# 2. Whole-tree manifest check: coverage AND content. Coverage first so a
#    truncated/stale manifest fails loudly instead of silently "passing".
if [ ! -f MANIFEST.sha256 ]; then
  echo "MANIFEST.sha256 missing" >&2; fail=1
else
  expected="$(git ls-files | grep -vxF -e 'MANIFEST.sha256' -e 'MANIFEST.sha256.asc' -e 'MANIFEST.sha256.ots' -e 'MANIFEST.sha256.countersig.asc' | sort)"
  listed="$(cut -c67- MANIFEST.sha256 | sort)"
  if [ "$expected" != "$listed" ]; then
    echo "MANIFEST coverage mismatch — manifest does not list exactly the tracked files (truncated/stale?)" >&2
    diff <(printf '%s\n' "$expected") <(printf '%s\n' "$listed") | head >&2
    fail=1
  fi
  sha256sum -c --quiet MANIFEST.sha256 || fail=1
fi

# 2b. Index/record agreement.
#
#     WHY THIS EXISTS (added during the July 2026 archive review).
#     export.php emits records for published announcements only, and nothing in the
#     pipeline ever deletes. So if an announcement is unpublished, its record file
#     persists - still tracked, still covered by the manifest, still under the
#     signature - while index.jsonl, which is rewritten in full on every run, silently
#     drops it. The record survives and leaves the only machine-readable enumeration of
#     what this archive contains.
#
#     Until this check existed, verify.sh passed in that state: it never referenced
#     index.jsonl at all. A consumer reading the index and a consumer walking the
#     directory would diverge, and nothing reported it.
#
#     Checked in BOTH directions. An orphaned record (file with no index entry) is the
#     unpublish case; a phantom entry (index entry with no file) would mean the index
#     claims something the archive does not hold. Either is a failure.
if [ ! -f index.jsonl ]; then
  echo "index.jsonl missing" >&2; fail=1
else
  idx_ids="$(python3 -c '
import json,sys
out=[]
for line in open("index.jsonl"):
    line=line.strip()
    if not line: continue
    try: out.append(str(json.loads(line)["id"]))
    except Exception as e: sys.exit("index.jsonl: unparseable line: %s" % e)
print("\n".join(sorted(set(out))))')" || fail=1
  rec_ids="$(python3 -c '
import json,glob
out=[]
for f in glob.glob("announcements/**/*.json", recursive=True):
    try: out.append(str(json.load(open(f))["id"]))
    except Exception: pass
print("\n".join(sorted(set(out))))')"
  if [ "$idx_ids" != "$rec_ids" ]; then
    echo "INDEX/RECORD MISMATCH - index.jsonl does not enumerate exactly the record files" >&2
    orph="$(comm -13 <(printf '%s\n' "$idx_ids") <(printf '%s\n' "$rec_ids"))"
    phan="$(comm -23 <(printf '%s\n' "$idx_ids") <(printf '%s\n' "$rec_ids"))"
    n_o=$(printf '%s' "$orph" | grep -c . || true)
    n_p=$(printf '%s' "$phan" | grep -c . || true)
    # Print the total before the sample: a bare head(1) shows ten names with no count,
    # and an operator may reasonably read ten as the whole divergence.
    echo "  orphaned records (file present, absent from index): $n_o total$( [ "$n_o" -gt 10 ] && echo ", first 10 shown" )" >&2
    printf '%s\n' "$orph" | head -10 >&2
    echo "  phantom entries (in index, no record file): $n_p total$( [ "$n_p" -gt 10 ] && echo ", first 10 shown" )" >&2
    printf '%s\n' "$phan" | head -10 >&2
    fail=1
  fi
fi

# 3. Manifest signature (detached).
#
#    The signer key also ships in-tree as SIGNING-KEY.asc, which on its own proves
#    nothing: anyone able to rewrite this repository could replace the manifest, the
#    signature AND the key together, and this check would still pass. The key is kept
#    in-tree for convenience only.
#
#    Trust therefore comes from OUTSIDE the repository. The fingerprint is published
#    independently, on infrastructure GitHub does not control:
#
#      dig +short TXT _archive-key.cfi.co
#      https://keys.openpgp.org/vks/v1/by-fingerprint/B497BDC19FCD487972D5D2B0876FF2AA39133BF8
#
#    A mismatch between the in-tree key and the DNS anchor is treated as tampering and
#    fails hard. If DNS cannot be reached we say so rather than implying we checked --
#    an unreachable anchor is an unverified signature, not a passing one.
#
#    cfi 2026-07-29: direct queries on port 53 (what dig/host use) are blocked on
#    some corporate and ISP networks even when ordinary DNS works fine for everything
#    else - reproduced live, on a real verifier's machine, running exactly this check.
#    DNS-over-HTTPS travels on 443, the same transport as every other fetch this
#    script makes, so it succeeds precisely where dig/host silently cannot. Tried
#    last, and only if the direct queries found nothing.
EXPECT_FPR_DNS="_archive-key.cfi.co"
if [ -f MANIFEST.sha256.asc ] && command -v gpg >/dev/null 2>&1; then
  gpg -q --import SIGNING-KEY.asc 2>/dev/null || true
  intree_fpr=$(gpg --show-keys --with-colons SIGNING-KEY.asc 2>/dev/null | awk -F: '/^fpr:/{print $10; exit}')

  anchor_fpr=""
  if command -v dig >/dev/null 2>&1; then
    anchor_fpr=$(dig +short TXT "$EXPECT_FPR_DNS" 2>/dev/null | tr -d '"' | grep -oE '[A-F0-9]{40}' | head -1)
  elif command -v host >/dev/null 2>&1; then
    anchor_fpr=$(host -t TXT "$EXPECT_FPR_DNS" 2>/dev/null | grep -oE '[A-F0-9]{40}' | head -1)
  fi
  if [ -z "$anchor_fpr" ] && command -v curl >/dev/null 2>&1; then
    anchor_fpr=$(curl -s --max-time 15 -H 'accept: application/dns-json' \
      "https://cloudflare-dns.com/dns-query?name=${EXPECT_FPR_DNS}&type=TXT" 2>/dev/null \
      | grep -oE '[A-F0-9]{40}' | head -1)
  fi

  if [ -z "$anchor_fpr" ]; then
    echo "signing key NOT anchor-checked (no DNS resolver available) — key $intree_fpr taken from the repo itself, which proves nothing on its own" >&2
  elif [ "$anchor_fpr" != "$intree_fpr" ]; then
    echo "SIGNING KEY MISMATCH: repo has $intree_fpr, out-of-band anchor says $anchor_fpr" >&2
    fail=1
  else
    echo "signing key matches out-of-band anchor ($EXPECT_FPR_DNS): $intree_fpr"
  fi

  if gpg --verify MANIFEST.sha256.asc MANIFEST.sha256 2>/dev/null; then
    echo "manifest signature OK"
  else
    echo "manifest signature INVALID" >&2; fail=1
  fi
fi

if [ "$fail" -eq 0 ]; then
  echo "OK — $n announcement records verified, manifest intact."
else
  echo "VERIFICATION FAILED — see messages above." >&2
fi
exit $fail
